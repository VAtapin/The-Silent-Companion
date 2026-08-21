<?php

namespace Tests\Feature;

use App\Models\Act;
use App\Models\ActivityLog;
use App\Models\AiRequest;
use App\Models\Asset;
use App\Models\Character;
use App\Models\ChecklistItem;
use App\Models\ChecklistSection;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Location;
use App\Models\Project;
use App\Models\Publication;
use App\Models\PublicSiteSetting;
use App\Models\Scene;
use App\Models\User;
use App\Services\ChecklistProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FilmProductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_only_public_home_and_cannot_open_workspace(): void
    {
        $this->get('/')->assertOk()->assertDontSee('Производственный чек-лист');
        $this->get('/workspace')->assertRedirect('/login');
        $this->get('/workspace/project')->assertRedirect('/login');
    }

    public function test_guest_cannot_open_direct_private_file(): void
    {
        Storage::fake('local');
        $asset = Asset::create(['title' => 'Приватный кадр', 'type' => 'Фото', 'status' => 'Загружено', 'disk' => 'local', 'file_path' => 'private.png', 'original_name' => 'private.png']);
        Storage::disk('local')->put('private.png', 'secret');
        $this->get(route('assets.download', $asset))->assertRedirect('/login');
        $this->get(route('public.media', $asset))->assertNotFound();
    }

    public function test_material_appears_publicly_only_after_manual_publication(): void
    {
        Storage::fake('local');
        [$user] = $this->baseData();
        $asset = Asset::create(['title' => 'Публичный кадр', 'type' => 'Фото', 'status' => 'Утверждено', 'disk' => 'local', 'file_path' => 'frame.png', 'original_name' => 'frame.png', 'mime_type' => 'image/png']);
        Storage::disk('local')->put('frame.png', 'image');
        $publication = Publication::create(['author_id' => $user->id, 'title' => 'Новый кадр', 'type' => 'Фото', 'status' => 'Черновик', 'is_published' => false]);
        $publication->assets()->attach($asset);
        $this->get('/')->assertDontSee('Новый кадр');
        $this->get(route('public.media', $asset))->assertNotFound();
        $publication->update(['status' => 'Опубликовано', 'is_published' => true, 'published_at' => now()]);
        $this->get('/')->assertOk()->assertSee('Новый кадр');
        $this->get(route('public.media', $asset))->assertOk();
    }

    public function test_user_can_upload_material_and_link_it_to_character_and_checklist(): void
    {
        Storage::fake('local');
        [$user, , , $item, $character] = $this->baseData(1);

        $response = $this->actingAs($user)->post(route('assets.store'), [
            'title' => 'Лицо героя', 'type' => 'Фото', 'status' => 'Утверждено',
            'file' => UploadedFile::fake()->image('hero.jpg'), 'character_ids' => [$character->id],
            'checklist_item_ids' => [$item->id], 'has_usage_permission' => 1,
        ]);

        $asset = Asset::firstOrFail();
        $response->assertRedirect(route('assets.show', $asset));
        Storage::disk('local')->assertExists($asset->file_path);
        $this->assertTrue($asset->characters->contains($character));
        $this->assertTrue($asset->checklistItems->contains($item));
    }

    public function test_material_counts_and_automatically_completes_item_at_minimum(): void
    {
        [$user, , , $item] = $this->baseData(2);
        $this->makeLinkedAsset($user, $item, 'Утверждено');
        $item->refresh();
        $this->assertSame(1, $item->requirements->first()->current_count);
        $this->assertSame(50, $item->progress);
        $this->makeLinkedAsset($user, $item, 'Утверждено');
        $item->refresh();
        $this->assertSame('Выполнено', $item->status);
        $this->assertSame('Автоматически', $item->completion_mode);
        $this->assertSame(100, $item->progress);
    }

    public function test_rejected_material_does_not_count_as_approved(): void
    {
        [$user, , , $item] = $this->baseData(1);
        $this->makeLinkedAsset($user, $item, 'Отклонено');
        $requirement = $item->fresh()->requirements->first();
        $this->assertSame(0, $requirement->current_count);
        $this->assertSame(1, $requirement->rejected_count);
        $this->assertSame('Требуются материалы', $item->fresh()->status);
    }

    public function test_item_can_be_completed_manually_and_warning_is_saved(): void
    {
        [$user, , , $item] = $this->baseData(2);
        $this->actingAs($user)->post(route('checklist.manual', $item), [
            'reason' => 'Тестовый эпизод уже принят режиссёром', 'confirm_missing' => 1,
        ])->assertRedirect();

        $item->refresh();
        $this->assertSame('Выполнено', $item->status);
        $this->assertSame('Вручную с предупреждением', $item->completion_mode);
        $this->assertTrue($item->has_warning);
        $this->assertDatabaseHas('checklist_manual_overrides', ['checklist_item_id' => $item->id, 'is_active' => true]);
    }

    public function test_warning_disappears_after_requirements_are_met(): void
    {
        [$user, , , $item] = $this->baseData(1);
        $this->actingAs($user)->post(route('checklist.manual', $item), ['reason' => 'Временно', 'confirm_missing' => 1]);
        $this->assertTrue($item->fresh()->has_warning);
        $this->makeLinkedAsset($user, $item, 'Утверждено');
        $item->refresh();
        $this->assertFalse($item->has_warning);
        $this->assertSame('Выполнено', $item->status);
        $this->assertDatabaseHas('checklist_manual_overrides', ['checklist_item_id' => $item->id, 'is_active' => false]);
    }

    public function test_document_changes_are_saved_as_versions(): void
    {
        [$user, $project] = $this->baseData();
        $document = Document::create(['project_id' => $project->id, 'title' => 'Сценарий', 'type' => 'Сценарий', 'content' => 'Версия один', 'version' => 1]);
        $document->versions()->create(['user_id' => $user->id, 'version' => 1, 'content' => 'Версия один']);
        $this->actingAs($user)->put(route('documents.update', $document), ['title' => 'Сценарий', 'type' => 'Сценарий', 'content' => 'Версия два', 'change_note' => 'Уточнён финал'])->assertRedirect();
        $this->assertSame(2, $document->fresh()->version);
        $this->assertDatabaseHas('document_versions', ['document_id' => $document->id, 'version' => 2, 'content' => 'Версия два']);
    }

    public function test_document_page_renders_markdown_and_offers_editing(): void
    {
        [$user, $project] = $this->baseData();
        $document = Document::create([
            'project_id' => $project->id,
            'title' => 'Сценарий',
            'type' => 'Сценарий',
            'content' => "# Заголовок\n\n**Жирный текст**",
            'version' => 1,
        ]);

        $this->actingAs($user)->get(route('documents.show', $document))
            ->assertOk()
            ->assertSee('<h1>Заголовок</h1>', false)
            ->assertSee('<strong>Жирный текст</strong>', false)
            ->assertSee('Редактировать сценарий')
            ->assertSee('Сохранить новую версию');
    }

    public function test_document_version_can_be_restored_without_losing_current_text(): void
    {
        [$user, $project] = $this->baseData();
        $document = Document::create(['project_id' => $project->id, 'title' => 'Сценарий', 'type' => 'Сценарий', 'content' => 'Первая версия', 'version' => 1]);
        $first = DocumentVersion::create(['document_id' => $document->id, 'user_id' => $user->id, 'version' => 1, 'content' => 'Первая версия']);
        $this->actingAs($user)->put(route('documents.update', $document), ['title' => 'Сценарий', 'type' => 'Сценарий', 'content' => 'Вторая версия']);

        $this->actingAs($user)->post(route('documents.versions.restore', [$document, $first]))->assertRedirect();

        $this->assertSame(3, $document->fresh()->version);
        $this->assertSame('Первая версия', $document->fresh()->content);
        $this->assertDatabaseHas('document_versions', ['document_id' => $document->id, 'version' => 2, 'content' => 'Вторая версия']);
        $this->assertDatabaseHas('document_versions', ['document_id' => $document->id, 'version' => 3, 'content' => 'Первая версия']);
    }

    public function test_logged_change_can_be_restored_and_restore_is_logged(): void
    {
        [$user, $project] = $this->baseData();
        $act = Act::create(['project_id' => $project->id, 'number' => 1, 'title' => 'Старое название', 'sort_order' => 1, 'status' => 'Черновик']);

        $this->actingAs($user)->put(route('structure.update', ['act', $act]), ['title' => 'Новое название', 'status' => 'В работе'])->assertRedirect();
        $change = ActivityLog::where('subject_type', Act::class)->latest('id')->firstOrFail();

        $this->actingAs($user)->post(route('activity.restore', $change))->assertRedirect();

        $this->assertSame('Старое название', $act->fresh()->title);
        $this->assertSame('Черновик', $act->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['subject_type' => Act::class, 'subject_id' => $act->id, 'action' => 'Восстановление предыдущей версии']);
    }

    public function test_backup_verification_checks_manifest_hashes(): void
    {
        $root = storage_path('framework/testing/backups-'.Str::uuid());
        $name = '2026-08-21_02-30-00';
        $directory = $root.DIRECTORY_SEPARATOR.$name;
        File::ensureDirectoryExists($directory);
        File::put($directory.DIRECTORY_SEPARATOR.'database.sql.gz', 'database-backup');
        File::put($directory.DIRECTORY_SEPARATOR.'storage.zip', 'storage-backup');
        File::put($directory.DIRECTORY_SEPARATOR.'manifest.json', json_encode([
            'database' => ['file' => 'database.sql.gz', 'sha256' => hash('sha256', 'database-backup')],
            'storage' => ['file' => 'storage.zip', 'sha256' => hash('sha256', 'storage-backup')],
        ]));
        config(['backup.path' => $root]);

        try {
            $this->artisan('backup:verify', ['name' => $name])->assertSuccessful();
        } finally {
            File::deleteDirectory($root);
        }
    }

    public function test_user_can_create_scenes_and_shots(): void
    {
        [$user, $project] = $this->baseData();
        $act = Act::create(['project_id' => $project->id, 'number' => 1, 'title' => 'Ритуал', 'sort_order' => 1, 'status' => 'Черновик']);
        $location = Location::create(['name' => 'Кухня', 'status' => 'На проверке']);
        $this->actingAs($user)->post(route('scenes.store'), ['act_id' => $act->id, 'location_id' => $location->id, 'code' => 'A1-S01', 'number' => 1, 'title' => 'Утро', 'status' => 'Черновик'])->assertRedirect();
        $scene = Scene::firstOrFail();
        $this->actingAs($user)->post(route('shots.store'), ['scene_id' => $scene->id, 'code' => 'A1-S01-K01', 'number' => 1, 'description' => 'Миска и корм', 'status' => 'Черновик'])->assertRedirect();
        $this->assertDatabaseHas('shots', ['scene_id' => $scene->id, 'code' => 'A1-S01-K01']);
    }

    public function test_all_active_users_have_equal_write_access(): void
    {
        [$first, $project] = $this->baseData();
        $second = User::factory()->create(['is_active' => true]);
        $act = Act::create(['project_id' => $project->id, 'number' => 1, 'title' => 'Ритуал', 'sort_order' => 1, 'status' => 'Черновик']);
        foreach ([$first, $second] as $index => $user) {
            $this->actingAs($user)->post(route('scenes.store'), ['act_id' => $act->id, 'code' => 'A1-S0'.($index + 1), 'number' => $index + 1, 'title' => 'Сцена '.($index + 1), 'status' => 'Черновик'])->assertRedirect();
        }
        $this->assertCount(2, Scene::all());
    }

    public function test_authorized_user_can_send_text_to_openai_without_overwriting_source(): void
    {
        [$user,$project] = $this->baseData();
        $this->configureOpenAi();
        Http::fake(['api.openai.com/v1/responses' => Http::response(['id' => 'resp_1', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Улучшенный текст']]]], 'usage' => ['input_tokens' => 1000, 'output_tokens' => 500]], 200)]);
        $document = Document::create(['project_id' => $project->id, 'title' => 'Синопсис', 'type' => 'Синопсис', 'content' => 'Исходный текст', 'version' => 1]);
        $response = $this->actingAs($user)->post(route('ai.text'), ['source_text' => 'Исходный текст', 'action' => 'improve', 'subject_type' => 'document', 'subject_id' => $document->id, 'target_field' => 'content']);
        $request = AiRequest::firstOrFail();
        $response->assertRedirect(route('ai.show', $request));
        $this->assertSame('Исходный текст', $document->fresh()->content);
        $this->assertSame('Улучшенный текст', $request->result_text);
        $this->assertDatabaseHas('ai_usage_records', ['request_id' => $request->id, 'input_tokens' => 1000, 'output_tokens' => 500]);
        Http::assertSent(fn ($r) => $r->url() === 'https://api.openai.com/v1/responses' && $r->hasHeader('Authorization', 'Bearer test-secret-key'));
    }

    public function test_ai_result_is_applied_only_by_decision_and_creates_version(): void
    {
        [$user,$project] = $this->baseData();
        $document = Document::create(['project_id' => $project->id, 'title' => 'Сценарий', 'type' => 'Сценарий', 'content' => 'Старая версия', 'version' => 1]);
        $request = AiRequest::create(['user_id' => $user->id, 'subject_type' => $document->getMorphClass(), 'subject_id' => $document->id, 'target_field' => 'content', 'request_type' => 'Текст', 'model' => 'test-model', 'source_text' => 'Старая версия', 'prompt' => 'Улучшить', 'result_text' => 'Новая версия', 'status' => 'Завершён']);
        $this->actingAs($user)->post(route('ai.decision', $request), ['decision' => 'apply'])->assertRedirect();
        $this->assertSame('Новая версия', $document->fresh()->content);
        $this->assertSame(2, $document->fresh()->version);
        $this->assertDatabaseHas('document_versions', ['document_id' => $document->id, 'version' => 2, 'content' => 'Новая версия']);
        $request2 = AiRequest::create(['user_id' => $user->id, 'subject_type' => $document->getMorphClass(), 'subject_id' => $document->id, 'target_field' => 'content', 'request_type' => 'Текст', 'model' => 'test-model', 'source_text' => 'Новая версия', 'prompt' => 'Ещё', 'result_text' => 'Отклонённый текст', 'status' => 'Завершён']);
        $this->actingAs($user)->post(route('ai.decision', $request2), ['decision' => 'reject']);
        $this->assertSame('Новая версия', $document->fresh()->content);
    }

    public function test_openai_image_is_saved_privately_and_not_approved_automatically(): void
    {
        Storage::fake('local');
        [$user] = $this->baseData();
        $this->configureOpenAi();
        Http::fake(['api.openai.com/v1/images/generations' => Http::response(['id' => 'img_1', 'data' => [['b64_json' => base64_encode('png-bytes')]]], 200)]);
        $this->actingAs($user)->post(route('ai.images'), ['title' => 'Туманная улица', 'description' => 'Герой уходит в туман', 'image_type' => 'Концепт-арт', 'size' => '1024x1024', 'quality' => 'low', 'count' => 1])->assertRedirect();
        $asset = Asset::where('source', 'OpenAI API')->firstOrFail();
        $this->assertSame('Создано ИИ', $asset->status);
        $this->assertTrue($asset->is_private);
        Storage::disk('local')->assertExists($asset->file_path);
        $this->assertDatabaseHas('ai_generated_assets', ['asset_id' => $asset->id]);
        $this->assertDatabaseHas('ai_usage_records', ['usage_type' => 'Генерация изображения', 'images' => 1]);
    }

    public function test_openai_image_edit_creates_a_new_private_asset_and_keeps_original(): void
    {
        Storage::fake('local');
        [$user] = $this->baseData();
        $this->configureOpenAi();
        $source = Asset::create(['title' => 'Исходный кадр', 'type' => 'Фото', 'status' => 'Утверждено', 'is_private' => true, 'disk' => 'local', 'file_path' => 'source.png', 'original_name' => 'source.png', 'mime_type' => 'image/png', 'uploaded_by' => $user->id]);
        Storage::disk('local')->put('source.png', 'original-bytes');
        Http::fake(['api.openai.com/v1/images/edits' => Http::response(['id' => 'img_edit_1', 'data' => [['b64_json' => base64_encode('edited-bytes')]]], 200)]);

        $this->actingAs($user)->post(route('ai.images'), ['title' => 'Кадр с туманом', 'description' => 'Добавить лёгкий туман', 'image_type' => 'Начальный кадр', 'size' => '1024x1024', 'quality' => 'low', 'count' => 1, 'source_asset_id' => $source->id])->assertRedirect();

        $generated = Asset::where('source', 'OpenAI API')->firstOrFail();
        $this->assertNotSame($source->id, $generated->id);
        $this->assertSame('original-bytes', Storage::disk('local')->get('source.png'));
        $this->assertSame('edited-bytes', Storage::disk('local')->get($generated->file_path));
        $this->assertDatabaseHas('ai_generated_assets', ['asset_id' => $generated->id, 'source_asset_id' => $source->id]);
        $this->assertDatabaseHas('ai_usage_records', ['usage_type' => 'Редактирование изображения', 'images' => 1]);
    }

    public function test_openai_limit_blocks_new_request_and_api_error_preserves_text(): void
    {
        [$user] = $this->baseData();
        $this->configureOpenAi();
        config(['openai.user_daily_limit' => 1]);
        AiRequest::create(['user_id' => $user->id, 'request_type' => 'Текст', 'model' => 'test-model', 'prompt' => 'Первый', 'status' => 'Завершён']);
        Http::fake();
        $this->actingAs($user)->post(route('ai.text'), ['source_text' => 'Не потерять', 'action' => 'improve'])->assertSessionHasErrors('openai');
        Http::assertNothingSent();
        config(['openai.user_daily_limit' => 20]);
        Http::fake(['api.openai.com/v1/responses' => Http::response(['error' => ['message' => 'Service unavailable']], 503)]);
        $this->actingAs($user)->post(route('ai.text'), ['source_text' => 'Не потерять', 'action' => 'improve']);
        $failed = AiRequest::latest('id')->first();
        $this->assertSame('Ошибка', $failed->status);
        $this->assertSame('Не потерять', $failed->source_text);
    }

    public function test_api_key_is_not_rendered_and_donation_settings_require_login(): void
    {
        [$user,$project] = $this->baseData();
        $this->configureOpenAi();
        $this->actingAs($user)->get(route('ai.index'))->assertOk()->assertDontSee('test-secret-key');
        auth()->logout();
        $this->put(route('publications.donations'), ['title' => 'Поддержка'])->assertRedirect('/login');
        $this->assertDatabaseMissing('donation_settings', ['project_id' => $project->id]);
    }

    public function test_poster_can_be_uploaded_directly_from_public_site_settings(): void
    {
        Storage::fake('local');
        [$user, $project] = $this->baseData();

        $this->actingAs($user)->post(route('publications.poster'), [
            'poster_file' => UploadedFile::fake()->image('poster.jpg', 1200, 1800),
        ])->assertRedirect();

        $poster = Asset::where('title', 'Афиша фильма «Тихий спутник»')->firstOrFail();
        $this->assertSame('Утверждено', $poster->status);
        $this->assertSame($poster->id, PublicSiteSetting::where('project_id', $project->id)->value('poster_asset_id'));
        Storage::disk('local')->assertExists($poster->file_path);
    }

    public function test_public_poster_is_rendered_as_full_screen_background(): void
    {
        Storage::fake('local');
        [$user, $project] = $this->baseData();
        Storage::disk('local')->put('poster.jpg', 'poster');
        $poster = Asset::create(['uploaded_by' => $user->id, 'title' => 'Широкая афиша', 'type' => 'Фото', 'status' => 'Утверждено', 'disk' => 'local', 'file_path' => 'poster.jpg', 'original_name' => 'poster.jpg', 'mime_type' => 'image/jpeg']);
        PublicSiteSetting::create(['project_id' => $project->id, 'poster_asset_id' => $poster->id]);

        $response = $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('min-h-screen', false)
            ->assertSee('object-[68%_center]', false)
            ->assertDontSee('aspect-[2/3]', false)
            ->assertSee('property="og:image"', false)
            ->assertSee('name="twitter:card" content="summary_large_image"', false)
            ->assertSee(route('public.media', $poster), false);

        $response->assertSee('application/ld+json', false)->assertSee('https://schema.org', false);
        $this->get(route('public.sitemap'))->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->assertSee('<urlset', false);
    }

    public function test_publication_can_be_created_and_shown_on_home_with_one_action(): void
    {
        Storage::fake('local');
        [$user] = $this->baseData();
        Storage::disk('local')->put('teaser.jpg', 'image');
        $asset = Asset::create(['uploaded_by' => $user->id, 'title' => 'Тизер', 'type' => 'Фото', 'status' => 'Утверждено', 'disk' => 'local', 'file_path' => 'teaser.jpg', 'original_name' => 'teaser.jpg', 'mime_type' => 'image/jpeg']);

        $this->actingAs($user)->post(route('publications.store'), [
            'title' => 'Первый тизер',
            'description' => 'Материал со съёмок',
            'type' => 'Фото',
            'asset_ids' => [$asset->id],
            'publish_action' => 'publish',
            'sort_order' => 0,
        ])->assertRedirect();

        $publication = Publication::firstOrFail();
        $this->assertTrue($publication->is_published);
        $this->assertSame('Опубликовано', $publication->status);
        $this->assertTrue($publication->assets->contains($asset));
        $this->get(route('public.home'))->assertOk()->assertSee('Первый тизер')->assertSee('Материал со съёмок');
    }

    public function test_visibility_button_repairs_inconsistent_publication_state(): void
    {
        [$user] = $this->baseData();
        $publication = Publication::create(['author_id' => $user->id, 'title' => 'Тизер', 'type' => 'Видео', 'status' => 'Опубликовано', 'is_published' => false]);

        $this->actingAs($user)->post(route('publications.visibility', $publication), ['visible' => 1])->assertRedirect();

        $publication->refresh();
        $this->assertTrue($publication->is_published);
        $this->assertSame('Опубликовано', $publication->status);
        $this->assertNull($publication->unpublished_at);
    }

    public function test_youtube_video_can_be_added_and_embedded_from_publication_form(): void
    {
        [$user] = $this->baseData();

        $this->actingAs($user)->post(route('publications.store'), [
            'title' => 'Видео о фильме', 'description' => 'Первый ролик', 'type' => 'Видео',
            'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ', 'publish_action' => 'publish', 'sort_order' => 0,
        ])->assertRedirect();

        $asset = Asset::where('external_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')->firstOrFail();
        $this->assertSame('dQw4w9WgXcQ', $asset->youtubeId());
        $this->assertTrue(Publication::firstOrFail()->assets->contains($asset));
        $this->get(route('public.home'))->assertOk()->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false);
    }

    public function test_photo_can_be_uploaded_directly_with_publication(): void
    {
        Storage::fake('local');
        [$user] = $this->baseData();

        $this->actingAs($user)->post(route('publications.store'), [
            'title' => 'Фото со съёмок', 'type' => 'Фото', 'media_file' => UploadedFile::fake()->image('set.jpg'),
            'publish_action' => 'draft', 'sort_order' => 0,
        ])->assertRedirect();

        $asset = Asset::where('title', 'Фото со съёмок')->firstOrFail();
        Storage::disk('local')->assertExists($asset->file_path);
        $this->assertTrue(Publication::firstOrFail()->assets->contains($asset));
    }

    public function test_invalid_youtube_url_is_rejected(): void
    {
        [$user] = $this->baseData();
        $this->actingAs($user)->post(route('publications.store'), [
            'title' => 'Видео', 'type' => 'Видео', 'youtube_url' => 'https://example.com/video', 'publish_action' => 'draft',
        ])->assertSessionHasErrors('youtube_url');
    }

    public function test_public_home_does_not_disclose_internal_project_data(): void
    {
        [$user,$project] = $this->baseData();
        $project->update(['camera_rules' => 'СЕКРЕТНЫЕ ПРАВИЛА КАМЕРЫ']);
        Document::create(['project_id' => $project->id, 'title' => 'Секретный сценарий', 'type' => 'Сценарий', 'content' => 'СЕКРЕТНЫЙ ФИНАЛ']);
        $this->get('/')->assertOk()->assertDontSee('СЕКРЕТНЫЕ ПРАВИЛА КАМЕРЫ')->assertDontSee('СЕКРЕТНЫЙ ФИНАЛ')->assertDontSee($user->email);
    }

    private function baseData(int $minimum = 0): array
    {
        $user = User::factory()->create(['is_active' => true]);
        $project = Project::create(['title_ru' => 'Тихий спутник', 'language' => 'Русский', 'updated_by' => $user->id]);
        $section = ChecklistSection::create(['project_id' => $project->id, 'title' => 'Персонажи']);
        $item = ChecklistItem::create(['section_id' => $section->id, 'title' => 'Материалы героя', 'completion_method' => 'Автоматически']);
        if ($minimum > 0) {
            $item->requirements()->create(['asset_type' => 'Фото', 'label' => 'Фотографии героя', 'minimum_count' => $minimum, 'recommended_count' => $minimum, 'is_required' => true, 'approved_only' => true]);
        }
        $character = Character::create(['name' => 'Главный герой', 'status' => 'Требуются материалы']);

        return [$user, $project, $section, $item, $character];
    }

    private function makeLinkedAsset(User $user, ChecklistItem $item, string $status): Asset
    {
        $asset = Asset::create(['title' => 'Материал '.uniqid(), 'type' => 'Фото', 'status' => $status, 'uploaded_by' => $user->id]);
        $asset->checklistItems()->attach($item);
        app(ChecklistProgressService::class)->recalculateForAsset($asset);

        return $asset;
    }

    private function configureOpenAi(): void
    {
        config(['openai.api_key' => 'test-secret-key', 'openai.text_model' => 'test-model', 'openai.image_model' => 'gpt-image-2', 'openai.user_daily_limit' => 20, 'openai.monthly_budget' => 100, 'openai.text_input_cost_per_million' => 1, 'openai.text_output_cost_per_million' => 2]);
    }
}
