<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(): View
    {
        return view('team.index', ['users' => User::orderByDesc('is_active')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', 'string', 'min:10', 'confirmed']]);
        $user = User::create([...$data, 'is_active' => true]);
        ActivityLogger::write('Добавление участника', $user, $user->email);

        return back()->with('success', 'Участник добавлен.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)], 'is_active' => ['nullable', 'boolean'], 'password' => ['nullable', 'string', 'min:10', 'confirmed']]);
        abort_if($user->is($request->user()) && ! $request->boolean('is_active'), 422, 'Нельзя деактивировать текущего пользователя.');
        $data['is_active'] = $request->boolean('is_active');
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        $user->update($data);
        ActivityLogger::write('Изменение участника', $user, $user->email);

        return back()->with('success', 'Данные участника обновлены.');
    }
}
