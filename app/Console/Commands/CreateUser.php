<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateUser extends Command
{
    protected $signature = 'user:create';

    protected $description = 'Создать пользователя или обновить его пароль';

    public function handle(): int
    {
        $name = trim((string) $this->ask('Имя', 'Продюсер проекта'));
        $email = mb_strtolower(trim((string) $this->ask('Электронная почта')));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Укажите корректный адрес электронной почты.');

            return self::FAILURE;
        }

        $existingUser = User::query()->where('email', $email)->first();

        if ($existingUser !== null && ! $this->confirm('Пользователь уже существует. Обновить имя и пароль?', true)) {
            $this->info('Изменения отменены.');

            return self::SUCCESS;
        }

        $password = (string) $this->secret('Пароль (не менее 10 символов)');
        $passwordConfirmation = (string) $this->secret('Повторите пароль');

        if (mb_strlen($password) < 10) {
            $this->error('Пароль должен содержать не менее 10 символов.');

            return self::FAILURE;
        }

        if (! hash_equals($password, $passwordConfirmation)) {
            $this->error('Пароли не совпадают.');

            return self::FAILURE;
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'is_active' => true,
            ],
        );

        $this->info($user->wasRecentlyCreated
            ? "Пользователь {$email} создан."
            : "Данные пользователя {$email} обновлены.");

        return self::SUCCESS;
    }
}
