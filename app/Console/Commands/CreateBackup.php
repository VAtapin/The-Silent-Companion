<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class CreateBackup extends Command
{
    protected $signature = 'backup:create {--keep= : Количество последних архивов}';

    protected $description = 'Создать проверяемую резервную копию MySQL и файлов проекта';

    public function handle(): int
    {
        $lock = Cache::lock('silent-companion-backup', 3600);
        if (! $lock->get()) {
            $this->error('Другая резервная копия уже создаётся.');

            return self::FAILURE;
        }

        try {
            return $this->create();
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Резервная копия не создана: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }

    private function create(): int
    {
        $root = rtrim((string) config('backup.path'), '/\\');
        File::ensureDirectoryExists($root, 0750);
        $name = now()->format('Y-m-d_H-i-s');
        $temporary = $root.DIRECTORY_SEPARATOR.'.'.$name.'.partial';
        $destination = $root.DIRECTORY_SEPARATOR.$name;
        File::ensureDirectoryExists($temporary, 0750);

        try {
            $database = $this->dumpDatabase($temporary);
            [$files, $fileCount] = $this->archiveStorage($temporary);
            $manifest = [
                'application' => config('app.name'),
                'created_at' => now()->toIso8601String(),
                'database' => ['file' => basename($database), 'sha256' => hash_file('sha256', $database), 'bytes' => filesize($database)],
                'storage' => ['file' => basename($files), 'sha256' => hash_file('sha256', $files), 'bytes' => filesize($files), 'files' => $fileCount],
            ];
            File::put($temporary.DIRECTORY_SEPARATOR.'manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            File::moveDirectory($temporary, $destination);
        } catch (Throwable $exception) {
            File::deleteDirectory($temporary);
            throw $exception;
        }

        $this->rotate($root, max(1, (int) ($this->option('keep') ?: config('backup.keep'))));
        $this->info("Резервная копия создана: {$destination}");
        $this->line("Файлов в Storage: {$fileCount}");

        return self::SUCCESS;
    }

    private function dumpDatabase(string $destination): string
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}");
        if (($database['driver'] ?? null) !== 'mysql') {
            throw new RuntimeException('Команда полного бэкапа поддерживает MySQL.');
        }

        $path = $destination.DIRECTORY_SEPARATOR.'database.sql.gz';
        $stream = gzopen($path, 'wb9');
        if (! $stream) {
            throw new RuntimeException('Не удалось создать сжатый дамп базы.');
        }

        $command = [
            (string) config('backup.mysqldump'), '--single-transaction', '--quick', '--triggers', '--no-tablespaces',
            '--default-character-set=utf8mb4', '--host='.(string) ($database['host'] ?? '127.0.0.1'), '--port='.(string) ($database['port'] ?? 3306),
            '--user='.(string) ($database['username'] ?? ''), (string) ($database['database'] ?? ''),
        ];
        $process = new Process($command, base_path(), ['MYSQL_PWD' => (string) ($database['password'] ?? '')]);
        $process->setTimeout(1800);
        $process->run(function (string $type, string $buffer) use ($stream): void {
            if ($type === Process::OUT) {
                gzwrite($stream, $buffer);
            }
        });
        gzclose($stream);

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'mysqldump завершился с ошибкой.');
        }

        return $path;
    }

    private function archiveStorage(string $destination): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Для архива файлов требуется PHP-расширение zip.');
        }

        $disk = Storage::disk(config('production.asset_disk'));
        $path = $destination.DIRECTORY_SEPARATOR.'storage.zip';
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Не удалось создать ZIP-архив Storage.');
        }

        $count = 0;
        foreach ($disk->allFiles() as $file) {
            $realPath = $disk->path($file);
            if (is_file($realPath) && $zip->addFile($realPath, $file)) {
                $count++;
            }
        }
        $zip->close();

        return [$path, $count];
    }

    private function rotate(string $root, int $keep): void
    {
        $directories = collect(File::directories($root))
            ->reject(fn (string $directory) => str_ends_with($directory, '.partial'))
            ->sortByDesc(fn (string $directory) => basename($directory))
            ->values();

        foreach ($directories->slice($keep) as $directory) {
            File::deleteDirectory($directory);
        }
    }
}
