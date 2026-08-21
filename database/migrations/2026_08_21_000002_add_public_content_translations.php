<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('title_de')->nullable()->after('title_en');
            $table->text('tagline_en')->nullable()->after('tagline');
            $table->text('tagline_de')->nullable()->after('tagline_en');
            $table->text('logline_en')->nullable()->after('logline');
            $table->text('logline_de')->nullable()->after('logline_en');
        });
        Schema::table('public_site_settings', function (Blueprint $table) {
            $table->text('public_summary_en')->nullable()->after('public_summary');
            $table->text('public_summary_de')->nullable()->after('public_summary_en');
        });
        Schema::table('publications', function (Blueprint $table) {
            $table->string('title_en')->nullable()->after('title');
            $table->string('title_de')->nullable()->after('title_en');
            $table->text('description_en')->nullable()->after('description');
            $table->text('description_de')->nullable()->after('description_en');
        });
        Schema::table('donation_settings', function (Blueprint $table) {
            $table->string('title_en')->nullable()->after('title');
            $table->string('title_de')->nullable()->after('title_en');
            $table->text('goal_description_en')->nullable()->after('goal_description');
            $table->text('goal_description_de')->nullable()->after('goal_description_en');
            $table->text('additional_methods_en')->nullable()->after('additional_methods');
            $table->text('additional_methods_de')->nullable()->after('additional_methods_en');
        });

        DB::table('projects')->whereNull('title_en')->update(['title_en' => 'The Silent Companion']);
        DB::table('projects')->whereNull('title_de')->update(['title_de' => 'Der stille Begleiter']);
        DB::table('projects')->where('title_ru', 'Тихий спутник')->update([
            'tagline_en' => 'A story about how those who cannot speak can save us.',
            'tagline_de' => 'Eine Geschichte darüber, wie uns jene retten, die nicht sprechen können.',
            'logline_en' => 'After a devastating loss, a man falls silent and withdraws from the world. Only his old black Labrador draws him out of the empty house each day and, before leaving, helps him reconnect with people and entrusts him to a new little companion.',
            'logline_de' => 'Nach einem schweren Verlust verstummt ein Mann und zieht sich von der Welt zurück. Nur sein alter schwarzer Labrador holt ihn jeden Tag aus dem leeren Haus und hilft ihm vor seinem Abschied, wieder zu den Menschen zu finden und einen neuen kleinen Begleiter anzunehmen.',
        ]);
        DB::table('public_site_settings')->whereNotNull('public_summary')->update([
            'public_summary_en' => 'A quiet psychological drama about a man and an old Labrador who leads his owner back to people and gives him a new ritual for living.',
            'public_summary_de' => 'Ein stilles psychologisches Drama über einen Mann und einen alten Labrador, der seinen Menschen zu den Menschen zurückführt und ihm ein neues Lebensritual schenkt.',
        ]);
    }

    public function down(): void
    {
        Schema::table('donation_settings', fn (Blueprint $table) => $table->dropColumn(['title_en', 'title_de', 'goal_description_en', 'goal_description_de', 'additional_methods_en', 'additional_methods_de']));
        Schema::table('publications', fn (Blueprint $table) => $table->dropColumn(['title_en', 'title_de', 'description_en', 'description_de']));
        Schema::table('public_site_settings', fn (Blueprint $table) => $table->dropColumn(['public_summary_en', 'public_summary_de']));
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn(['title_de', 'tagline_en', 'tagline_de', 'logline_en', 'logline_de']));
    }
};
