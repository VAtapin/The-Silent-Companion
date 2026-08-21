<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('publications')->select(['id', 'title_en', 'title_de', 'description_en', 'description_de'])->orderBy('id')->chunkById(100, function ($publications): void {
            foreach ($publications as $publication) {
                $changes = [];
                foreach (['title_en', 'title_de', 'description_en', 'description_de'] as $field) {
                    if (! is_string($publication->{$field})) {
                        continue;
                    }
                    $normalized = $this->normalize($publication->{$field});
                    if ($normalized !== $publication->{$field}) {
                        $changes[$field] = $normalized;
                    }
                }
                if ($changes !== []) {
                    DB::table('publications')->where('id', $publication->id)->update($changes);
                }
            }
        });
    }

    public function down(): void
    {
        // Исправленные переносы строк намеренно не превращаются обратно в буквальные escape-последовательности.
    }

    private function normalize(string $value): string
    {
        $value = str_replace(['\\\\r\\\\n', '\\\\n', '\\\\r', '\\r\\n', '\\n', '\\r'], "\n", $value);

        return str_replace(["\r\n", "\r"], "\n", $value);
    }
};
