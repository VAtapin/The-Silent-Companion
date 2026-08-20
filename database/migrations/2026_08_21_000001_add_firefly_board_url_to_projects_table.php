<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->text('firefly_board_url')->nullable()->after('production_stage');
        });

        DB::table('projects')->update([
            'firefly_board_url' => 'https://firefly.adobe.com/boards/id/urn:aaid:sc:DEU1:5b89e678-b6f6-43fb-b67d-05d7064796a5',
        ]);
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('firefly_board_url');
        });
    }
};
