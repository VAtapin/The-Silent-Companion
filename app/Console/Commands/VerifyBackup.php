<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class VerifyBackup extends Command
{
    protected $signature = 'backup:verify {name? : Имя каталога; по умолчанию последний}';

    protected $description = 'Проверить наличие и контрольные суммы резервной копии';

    public function handle(): int
    {
        $root = rtrim((string) config('backup.path'), '/\\');
        if (! File::isDirectory($root)) {
            $this->error('Каталог резервных копий не найден.');

            return self::FAILURE;
        }
        $directory = $this->argument('name')
            ? $root.DIRECTORY_SEPARATOR.basename((string) $this->argument('name'))
            : collect(File::directories($root))->sortByDesc(fn (string $path) => basename($path))->first();

        if (! $directory || ! File::isDirectory($directory)) {
            $this->error('Резервная копия не найдена.');

            return self::FAILURE;
        }

        $manifestPath = $directory.DIRECTORY_SEPARATOR.'manifest.json';
        $manifest = json_decode(File::get($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        foreach (['database', 'storage'] as $part) {
            $path = $directory.DIRECTORY_SEPARATOR.$manifest[$part]['file'];
            if (! File::exists($path) || ! hash_equals($manifest[$part]['sha256'], hash_file('sha256', $path))) {
                $this->error("Проверка не пройдена: {$part} повреждён или отсутствует.");

                return self::FAILURE;
            }
        }

        $this->info('Резервная копия исправна: '.basename($directory));

        return self::SUCCESS;
    }
}
