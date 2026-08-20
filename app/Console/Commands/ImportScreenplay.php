<?php

namespace App\Console\Commands;

use Database\Seeders\ScreenplayV1Seeder;
use Illuminate\Console\Command;

class ImportScreenplay extends Command
{
    protected $signature = 'film:import-screenplay {--force : Заменить структуру без подтверждения}';

    protected $description = 'Заменить структуру фильма данными сценария версии 1.0';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Удалить прежние акты, сцены, кадры и производные каталоги?', false)) {
            $this->info('Импорт отменён.');

            return self::SUCCESS;
        }

        $this->call('db:seed', ['--class' => ScreenplayV1Seeder::class, '--force' => true]);
        $this->info('Сценарий 1.0 импортирован: 3 акта, 17 сцен и 69 кадров.');

        return self::SUCCESS;
    }
}
