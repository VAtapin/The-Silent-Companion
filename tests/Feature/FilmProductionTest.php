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
use App\Models\Shot;
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

    public function test_structure_suggests_next_shot_code_and_existing_scene_and_shot_can_be_edited(): void
    {
        [$user, $project] = $this->baseData();
        $act = Act::create(['project_id' => $project->id, 'number' => 1, 'title' => 'Ритуал', 'sort_order' => 1, 'status' => 'Черновик']);
        $scene = Scene::create(['act_id' => $act->id, 'code' => 'A1-S01', 'number' => 1, 'title' => 'Чёрный экран', 'action_description' => 'Старое действие', 'status' => 'Черновик']);
        $shot = Shot::create(['scene_id' => $scene->id, 'code' => 'A1-S01-K01', 'number' => 1, 'description' => 'Старый кадр', 'status' => 'Черновик']);

        $this->actingAs($user)->get(route('structure.index'))->assertOk()
            ->assertSee('A1-S01-K02')
            ->assertSee('Код и номер рассчитаны автоматически')
            ->assertSee('Изменить сцену')
            ->assertSee('Существующие элементы редактируются слева');

        $this->put(route('structure.update', ['scene', $scene]), ['action_description' => 'Новое действие сцены', 'status' => 'В работе'])->assertRedirect();
        $this->put(route('structure.update', ['shot', $shot]), ['description' => 'Новое описание кадра', 'status' => 'На проверке'])->assertRedirect();

        $this->assertSame('Новое действие сцены', $scene->fresh()->action_description);
        $this->assertSame('Новое описание кадра', $shot->fresh()->description);
    }

    public function test_ai_assistant_preselects_scene_or_shot_and_loads_its_source_text(): void
    {
        [$user, $project] = $this->baseData();
        $act = Act::create(['project_id' => $project->id, 'number' => 1, 'title' => 'Ритуал', 'sort_order' => 1, 'status' => 'Черновик']);
        $scene = Scene::create(['act_id' => $act->id, 'code' => 'A1-S01', 'number' => 1, 'title' => 'Чёрный экран', 'action_description' => 'Уникальный текст выбранной сцены', 'status' => 'Черновик']);
        $shot = Shot::create(['scene_id' => $scene->id, 'code' => 'A1-S01-K01', 'number' => 1, 'description' => 'Уникальный текст выбранного кадра', 'status' => 'Черновик']);

        $this->actingAs($user)->get(route('ai.index', ['scene' => $scene->id]))->assertOk()
            ->assertSee('Уникальный текст выбранной сцены')
            ->assertSee('Что анализируем')
            ->assertSee('x-model="sourceText"', false);
        $this->get(route('ai.index', ['shot' => $shot->id]))->assertOk()
            ->assertSee('Уникальный текст выбранного кадра')
            ->assertSee('A1-S01-K01');
    }

    public function test_workspace_has_context_help_and_full_guide_under_preparation(): void
    {
        [$user] = $this->baseData();

        $this->actingAs($user)->get(route('help.index'))->assertOk()
            ->assertSee('Как устроена рабочая зона')
            ->assertSee('A2-S06-K03')
            ->assertSee('ИИ-помощник');
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Открыть помощь')
            ->assertSeeInOrder(['Подготовка съёмок', 'ИИ-помощник', 'Помощь', 'Сайт и публикации']);
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
        $publication = Publication::firstOrFail();
        $this->get(route('public.home'))->assertOk()
            ->assertSee('i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', false)
            ->assertDontSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertSee(route('public.publications.show', $publication), false);
        $this->get(route('public.publications.show', $publication))->assertOk()
            ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false);
    }

    public function test_home_shows_only_teaser_and_full_text_is_on_publication_page(): void
    {
        [$user] = $this->baseData();
        $longText = str_repeat('Полный текст материала, который не должен целиком помещаться на главной странице. ', 8);
        $publication = Publication::create(['author_id' => $user->id, 'title' => 'История фильма', 'description' => $longText, 'type' => 'Новость', 'status' => 'Опубликовано', 'is_published' => true, 'published_at' => now()]);

        $this->get(route('public.home'))->assertOk()->assertSee('Читать далее')->assertDontSee($longText);
        $this->get(route('public.publications.show', $publication))->assertOk()->assertSee($longText);
    }

    public function test_public_site_has_russian_english_and_german_routes_with_localized_content(): void
    {
        [$user, $project] = $this->baseData();
        $project->update(['title_en' => 'The Silent Companion', 'title_de' => 'Der stille Begleiter', 'tagline_en' => 'An English tagline', 'tagline_de' => 'Ein deutscher Slogan']);
        $publication = Publication::create(['author_id' => $user->id, 'title' => 'Русский заголовок', 'title_en' => 'English title', 'title_de' => 'Deutscher Titel', 'description' => 'Русский текст', 'description_en' => 'English text', 'description_de' => 'Deutscher Text', 'type' => 'Новость', 'status' => 'Опубликовано', 'is_published' => true, 'published_at' => now()]);

        $this->get('/')->assertOk()->assertSee('<html lang="ru">', false)->assertSee('Русский заголовок');
        $this->get('/en')->assertOk()->assertSee('<html lang="en">', false)->assertSee('The Silent Companion')->assertSee('English title')->assertSee('Read more');
        $this->get('/de')->assertOk()->assertSee('<html lang="de">', false)->assertSee('Der stille Begleiter')->assertSee('Deutscher Titel')->assertSee('Weiterlesen');
        $this->get(route('public.publications.show.en', $publication))->assertOk()->assertSee('English text');
        $this->get(route('public.publications.show.de', $publication))->assertOk()->assertSee('Deutscher Text');
    }

    public function test_public_header_has_compact_flags_and_team_login_is_only_in_footer(): void
    {
        $this->baseData();

        $html = $this->get(route('public.home'))->assertOk()->getContent();
        $header = str($html)->between('<header', '</header>')->toString();
        $footer = str($html)->after('<footer')->toString();

        $this->assertStringContainsString('🇷🇺', $header);
        $this->assertStringContainsString('🇬🇧', $header);
        $this->assertStringContainsString('🇩🇪', $header);
        $this->assertStringNotContainsString(route('login'), $header);
        $this->assertStringContainsString(route('login'), $footer);
    }

    public function test_german_legal_pages_are_linked_from_every_public_language(): void
    {
        $this->baseData();
        $impressumUrl = route('public.legal', 'impressum');
        $privacyUrl = route('public.legal', 'datenschutz');

        foreach (['/', '/en', '/de'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee($impressumUrl, false)
                ->assertSee($privacyUrl, false);
        }

        $this->get($impressumUrl)->assertOk()
            ->assertSee('<html lang="de">', false)
            ->assertSee('<h1>Impressum</h1>', false)
            ->assertSee($privacyUrl, false);
        $this->get($privacyUrl)->assertOk()
            ->assertSee('<html lang="de">', false)
            ->assertSee('<h1>Datenschutzerklärung</h1>', false)
            ->assertSee($impressumUrl, false);
    }

    public function test_legal_pages_can_be_edited_and_restored_from_workspace(): void
    {
        [$user, $project] = $this->baseData();
        $settings = PublicSiteSetting::create(['project_id' => $project->id, 'impressum' => '# Alte Fassung', 'privacy_policy' => '# Alter Datenschutz']);

        $this->actingAs($user)->put(route('publications.legal'), [
            'impressum' => '# Neues Impressum',
            'privacy_policy' => "# Neue Datenschutzerklärung\n\n<script>alert('unsafe')</script>",
        ])->assertRedirect(route('publications.index', ['tab' => 'legal']))->assertSessionHas('success');

        $this->assertSame('# Neues Impressum', $settings->fresh()->impressum);
        $this->assertDatabaseHas('activity_logs', ['subject_type' => PublicSiteSetting::class, 'subject_id' => $settings->id, 'action' => 'Изменение правовых страниц']);
        $this->get(route('public.legal', 'impressum'))->assertOk()->assertSee('<h1>Neues Impressum</h1>', false);
        $this->get(route('public.legal', 'datenschutz'))->assertOk()->assertSee('<h1>Neue Datenschutzerklärung</h1>', false)->assertDontSee('<script>', false);

        $log = ActivityLog::where('subject_type', PublicSiteSetting::class)->where('subject_id', $settings->id)->latest('id')->firstOrFail();
        $this->post(route('activity.restore', $log))->assertRedirect()->assertSessionHas('success');
        $this->assertSame('# Alte Fassung', $settings->fresh()->impressum);

        auth()->logout();
        $this->put(route('publications.legal'), ['impressum' => 'x', 'privacy_policy' => 'y'])->assertRedirect('/login');
    }

    public function test_workspace_is_always_russian_and_has_no_language_switcher(): void
    {
        [$user] = $this->baseData();
        $this->actingAs($user)->withSession(['locale' => 'de'])->get(route('dashboard'))->assertOk()
            ->assertSee('<html lang="ru">', false)
            ->assertSeeInOrder(['Фильм', 'Подготовка съёмок', 'Сайт и публикации', 'Команда', 'Система'])
            ->assertSee('История изменений')
            ->assertDontSee('Language / Sprache / Язык');
    }

    public function test_publication_can_be_translated_to_english_and_german_with_ai(): void
    {
        [$user] = $this->baseData();
        $this->configureOpenAi();
        $publication = Publication::create(['author_id' => $user->id, 'title' => 'Первый тизер', 'description' => 'История о человеке и собаке.', 'type' => 'Новость', 'status' => 'Черновик']);
        $translation = json_encode(['en' => ['title' => 'First teaser', 'description' => 'First paragraph\\r\\n\\r\\nSecond paragraph'], 'de' => ['title' => 'Erster Teaser', 'description' => 'Erster Absatz\\r\\n\\r\\nZweiter Absatz']], JSON_UNESCAPED_UNICODE);
        Http::fake(['api.openai.com/v1/responses' => Http::response(['id' => 'resp_translation', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => $translation]]]], 'usage' => ['input_tokens' => 120, 'output_tokens' => 80]], 200)]);

        $this->actingAs($user)->post(route('publications.translate', $publication))->assertRedirect()->assertSessionHas('success');

        $publication->refresh();
        $this->assertSame('First teaser', $publication->title_en);
        $this->assertSame('Erster Teaser', $publication->title_de);
        $this->assertSame("First paragraph\n\nSecond paragraph", $publication->description_en);
        $this->assertSame("Erster Absatz\n\nZweiter Absatz", $publication->description_de);
        $this->assertStringNotContainsString('\\r\\n', $publication->description_de);
        $this->assertDatabaseHas('ai_requests', ['subject_type' => Publication::class, 'subject_id' => $publication->id, 'action' => 'translate_publication', 'status' => 'Завершён']);
        $this->assertDatabaseHas('ai_usage_records', ['usage_type' => 'Текст', 'input_tokens' => 120, 'output_tokens' => 80]);
    }

    public function test_existing_publication_translations_with_literal_line_breaks_are_repaired(): void
    {
        [$user] = $this->baseData();
        $publication = Publication::create(['author_id' => $user->id, 'title' => 'Публикация', 'type' => 'Новость', 'status' => 'Черновик', 'description_en' => 'One\\r\\n\\r\\nTwo', 'description_de' => 'Eins\\n\\nZwei']);

        $migration = require database_path('migrations/2026_08_21_000003_normalize_publication_translation_line_breaks.php');
        $migration->up();

        $this->assertSame("One\n\nTwo", $publication->fresh()->description_en);
        $this->assertSame("Eins\n\nZwei", $publication->fresh()->description_de);
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

    public function test_publication_media_picker_is_searched_and_loaded_in_pages_of_24(): void
    {
        [$user] = $this->baseData();
        foreach (range(1, 30) as $number) {
            Asset::create(['uploaded_by' => $user->id, 'title' => 'Материал '.$number, 'type' => $number === 30 ? 'Видео' : 'Фото', 'status' => 'Утверждено', 'file_path' => 'assets/'.$number.'.jpg', 'thumbnail_path' => 'thumbs/'.$number.'.jpg', 'mime_type' => $number === 30 ? 'video/mp4' : 'image/jpeg']);
        }

        $this->actingAs($user)->getJson(route('publications.media-options'))->assertOk()
            ->assertJsonCount(24, 'data')
            ->assertJsonPath('page', 1)
            ->assertJsonPath('last_page', 2)
            ->assertJsonPath('total', 30);
        $this->actingAs($user)->get(route('publications.index'))->assertOk()
            ->assertSee('Открыть медиатеку')
            ->assertDontSee(route('assets.preview', Asset::first()), false);
        $this->actingAs($user)->getJson(route('publications.media-options', ['search' => 'Материал 30', 'type' => 'Видео']))->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Материал 30');
    }

    public function test_publication_media_picker_requires_authentication(): void
    {
        $this->get(route('publications.media-options'))->assertRedirect('/login');
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

    public function test_material_metadata_and_links_can_be_edited_without_replacing_file(): void
    {
        [$user, , , $item, $character] = $this->baseData();
        $asset = Asset::create(['uploaded_by' => $user->id, 'title' => 'Старое название', 'type' => 'Фото', 'status' => 'На проверке', 'file_path' => 'assets/original.jpg']);

        $this->actingAs($user)->put(route('assets.update', $asset), [
            'title' => 'Новое название', 'description' => 'Исправленное описание', 'type' => 'Фото', 'status' => 'Утверждено',
            'character_ids' => [$character->id], 'checklist_item_ids' => [$item->id], 'has_usage_permission' => 1,
        ])->assertRedirect(route('assets.show', $asset))->assertSessionHas('success');

        $asset->refresh();
        $this->assertSame('Новое название', $asset->title);
        $this->assertSame('assets/original.jpg', $asset->file_path);
        $this->assertTrue($asset->characters->contains($character));
        $this->assertTrue($asset->checklistItems->contains($item));
    }

    public function test_rejected_or_reshoot_material_requires_clear_comment_and_notifies_uploader(): void
    {
        [$user] = $this->baseData();
        $asset = Asset::create(['uploaded_by' => $user->id, 'title' => 'Пробный кадр', 'type' => 'Фото', 'status' => 'На проверке']);

        $this->actingAs($user)->put(route('assets.status', $asset), ['status' => 'Требуется переснять'])
            ->assertSessionHasErrors(['review_comment' => 'Напишите, что именно нужно исправить или переснять. Этот комментарий увидит загрузивший материал человек.']);
        $this->put(route('assets.status', $asset), ['status' => 'Требуется переснять', 'review_comment' => 'Снять крупнее и без блика.'])->assertRedirect();

        $this->get(route('dashboard'))->assertOk()->assertSee('Требует вашего внимания')->assertSee('Снять крупнее и без блика.');
    }

    public function test_publication_can_be_deleted_without_deleting_media_asset(): void
    {
        [$user] = $this->baseData();
        $asset = Asset::create(['uploaded_by' => $user->id, 'title' => 'Общий кадр', 'type' => 'Фото', 'status' => 'Утверждено']);
        $publication = Publication::create(['author_id' => $user->id, 'title' => 'Удаляемая публикация', 'type' => 'Фото', 'status' => 'Черновик']);
        $publication->assets()->attach($asset);

        $this->actingAs($user)->delete(route('publications.destroy', $publication))->assertRedirect(route('publications.index'))->assertSessionHas('success');

        $this->assertDatabaseMissing('publications', ['id' => $publication->id]);
        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
    }

    public function test_dashboard_summary_cards_are_clickable_and_checklist_filters_work(): void
    {
        [$user, , , $item] = $this->baseData();
        $item->update(['status' => 'Выполнено', 'has_warning' => true]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee(route('checklist.index', ['filter' => 'done']), false)
            ->assertSee(route('checklist.index', ['filter' => 'warning']), false)
            ->assertSee(route('assets.index', ['status' => 'На проверке']), false);
        $this->get(route('checklist.index', ['filter' => 'done']))->assertOk()->assertSee($item->title)->assertSee('выполненные пункты');
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
