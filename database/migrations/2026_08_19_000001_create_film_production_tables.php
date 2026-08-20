<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title_ru');
            $table->string('title_en')->nullable();
            $table->text('tagline')->nullable();
            $table->text('logline')->nullable();
            $table->longText('synopsis')->nullable();
            $table->string('genre')->nullable();
            $table->string('duration')->nullable();
            $table->string('aspect_ratio')->nullable();
            $table->unsignedSmallInteger('frame_rate')->nullable();
            $table->string('resolution')->nullable();
            $table->string('language')->default('Русский');
            $table->longText('visual_principle')->nullable();
            $table->longText('sound_principle')->nullable();
            $table->longText('color_palette')->nullable();
            $table->longText('camera_rules')->nullable();
            $table->string('production_stage')->default('Подготовка');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('project_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'version']);
        });

        Schema::create('characters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('role')->nullable();
            $table->text('description')->nullable();
            $table->string('age')->nullable();
            $table->text('appearance')->nullable();
            $table->text('build')->nullable();
            $table->text('clothing')->nullable();
            $table->text('voice')->nullable();
            $table->text('movement')->nullable();
            $table->text('continuity_details')->nullable();
            $table->text('forbidden_changes')->nullable();
            $table->string('status')->default('Требуются материалы');
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('time_of_day')->nullable();
            $table->string('weather')->nullable();
            $table->text('lighting')->nullable();
            $table->text('color_palette')->nullable();
            $table->text('continuity_elements')->nullable();
            $table->text('sounds')->nullable();
            $table->string('status')->default('На проверке');
            $table->timestamps();
        });

        Schema::create('props', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('Реквизит');
            $table->text('description')->nullable();
            $table->string('color')->nullable();
            $table->string('dimensions')->nullable();
            $table->string('condition')->nullable();
            $table->text('continuity_requirements')->nullable();
            $table->string('status')->default('На проверке');
            $table->timestamps();
        });

        Schema::create('acts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('planned_duration_seconds')->nullable();
            $table->unsignedInteger('actual_duration_seconds')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default('Черновик');
            $table->timestamps();
            $table->unique(['project_id', 'number']);
        });

        Schema::create('scenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('act_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code')->unique();
            $table->unsignedSmallInteger('number');
            $table->string('title');
            $table->longText('action_description')->nullable();
            $table->string('time_of_day')->nullable();
            $table->string('weather')->nullable();
            $table->longText('costume')->nullable();
            $table->longText('dialogue')->nullable();
            $table->longText('sounds')->nullable();
            $table->unsignedInteger('planned_duration_seconds')->nullable();
            $table->unsignedInteger('actual_duration_seconds')->nullable();
            $table->string('status')->default('Черновик');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('character_scene', function (Blueprint $table) {
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scene_id')->constrained()->cascadeOnDelete();
            $table->primary(['character_id', 'scene_id']);
        });

        Schema::create('prop_scene', function (Blueprint $table) {
            $table->foreignId('prop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scene_id')->constrained()->cascadeOnDelete();
            $table->primary(['prop_id', 'scene_id']);
        });

        Schema::create('shots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scene_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code')->unique();
            $table->unsignedSmallInteger('number');
            $table->longText('description');
            $table->text('hero_action')->nullable();
            $table->text('dog_action')->nullable();
            $table->string('shot_size')->nullable();
            $table->text('camera_position')->nullable();
            $table->text('camera_movement')->nullable();
            $table->text('lens')->nullable();
            $table->text('start_frame')->nullable();
            $table->text('end_frame')->nullable();
            $table->unsignedInteger('planned_duration_seconds')->nullable();
            $table->unsignedInteger('actual_duration_seconds')->nullable();
            $table->text('sound')->nullable();
            $table->text('dialogue')->nullable();
            $table->string('creation_method')->nullable();
            $table->string('ai_model')->nullable();
            $table->longText('prompt')->nullable();
            $table->longText('negative_prompt')->nullable();
            $table->unsignedInteger('credits_spent')->default(0);
            $table->boolean('is_selected')->default(false);
            $table->string('status')->default('Черновик');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('scope')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['name', 'scope']);
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('asset_categories')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type');
            $table->string('status')->default('Загружено');
            $table->string('disk')->default('local');
            $table->string('file_path')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->text('external_url')->nullable();
            $table->longText('text_content')->nullable();
            $table->string('view_angle')->nullable();
            $table->date('captured_at')->nullable();
            $table->string('author')->nullable();
            $table->string('source')->nullable();
            $table->boolean('has_usage_permission')->default(false);
            $table->text('comment')->nullable();
            $table->text('review_comment')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('asset_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->morphs('linkable');
            $table->timestamps();
            $table->unique(['asset_id', 'linkable_type', 'linkable_id'], 'asset_link_unique');
        });

        Schema::create('checklist_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('checklist_sections')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('checklist_sections')->cascadeOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->date('due_date')->nullable();
            $table->text('comment')->nullable();
            $table->string('completion_method')->default('Автоматически');
            $table->string('status')->default('Требуются материалы');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('completion_mode')->nullable();
            $table->boolean('has_warning')->default(false);
            $table->text('warning_text')->nullable();
            $table->timestamps();
        });

        Schema::create('checklist_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('asset_type');
            $table->string('label');
            $table->unsignedInteger('minimum_count')->default(1);
            $table->unsignedInteger('recommended_count')->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('approved_only')->default(true);
            $table->json('conditions')->nullable();
            $table->unsignedInteger('current_count')->default(0);
            $table->unsignedInteger('uploaded_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->timestamps();
        });

        Schema::create('checklist_manual_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reason')->nullable();
            $table->text('missing_summary')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('type');
            $table->longText('content')->nullable();
            $table->string('source_path')->nullable();
            $table->string('source_name')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version');
            $table->longText('content')->nullable();
            $table->text('change_note')->nullable();
            $table->timestamps();
            $table->unique(['document_id', 'version']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('subject');
            $table->string('action');
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['activity_logs', 'document_versions', 'documents', 'checklist_manual_overrides', 'checklist_requirements', 'checklist_items', 'checklist_sections', 'asset_links', 'assets', 'asset_categories', 'shots', 'prop_scene', 'character_scene', 'scenes', 'acts', 'props', 'locations', 'characters', 'project_versions', 'projects'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
