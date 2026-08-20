<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->boolean('is_private')->default(true)->after('status');
        });

        Schema::create('public_site_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('poster_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->text('public_summary')->nullable();
            $table->string('contact')->nullable();
            $table->json('official_links')->nullable();
            $table->timestamps();
        });

        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type');
            $table->dateTime('published_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default('Черновик');
            $table->boolean('is_published')->default(false);
            $table->dateTime('unpublished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('publication_assets', function (Blueprint $table) {
            $table->foreignId('publication_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->primary(['publication_id', 'asset_id']);
        });

        Schema::create('donation_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('image_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->foreignId('qr_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->string('title')->default('Поддержать создание фильма');
            $table->text('goal_description')->nullable();
            $table->text('bank_details')->nullable();
            $table->text('payment_url')->nullable();
            $table->text('additional_methods')->nullable();
            $table->string('contact')->nullable();
            $table->boolean('is_visible')->default(false);
            $table->timestamps();
        });

        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('ai_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('subject');
            $table->string('target_field')->nullable();
            $table->string('request_type');
            $table->string('action')->nullable();
            $table->string('model');
            $table->longText('source_text')->nullable();
            $table->longText('prompt');
            $table->longText('context_snapshot')->nullable();
            $table->longText('result_text')->nullable();
            $table->string('status')->default('Ожидает');
            $table->string('decision')->nullable();
            $table->string('api_request_id')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('image_count')->default(0);
            $table->decimal('cost', 12, 6)->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->foreignId('request_id')->nullable()->constrained('ai_requests')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role');
            $table->longText('content');
            $table->timestamps();
        });

        Schema::create('ai_prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('action')->unique();
            $table->text('instructions');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ai_generated_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('ai_requests')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('ai_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('model');
            $table->string('usage_type');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('images')->default(0);
            $table->decimal('cost', 12, 6)->default(0);
            $table->date('usage_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['ai_usage_records', 'ai_generated_assets', 'ai_prompt_templates', 'ai_messages', 'ai_requests', 'ai_conversations', 'donation_settings', 'publication_assets', 'publications', 'public_site_settings'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('assets', fn (Blueprint $table) => $table->dropColumn('is_private'));
    }
};
