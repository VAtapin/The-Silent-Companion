@extends('layouts.app')
@section('title', 'История изменений')
@section('content')
<div>
    <p class="text-sm text-amber-500">Защита от случайной перезаписи</p>
    <h1>История изменений</h1>
    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Здесь видно, кто и когда менял данные. Кнопка восстановления возвращает только поля конкретного изменения и сама создаёт новую запись — действие обратимо.</p>
</div>

<section class="card mt-7 border-amber-400/50">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-amber-600">Полная копия MySQL и файлов</p>
            @if($backup)
                <h2 class="mt-1">Последняя копия: {{ \Illuminate\Support\Carbon::parse($backup['created_at'])->format('d.m.Y H:i') }}</h2>
                <p class="mt-1 text-sm text-slate-500">База: {{ number_format(($backup['database']['bytes'] ?? 0) / 1048576, 1, ',', ' ') }} МБ · Storage: {{ number_format(($backup['storage']['bytes'] ?? 0) / 1048576, 1, ',', ' ') }} МБ · файлов: {{ $backup['storage']['files'] ?? 0 }}</p>
            @else
                <h2 class="mt-1">Полная копия ещё не обнаружена</h2>
                <p class="mt-1 text-sm text-slate-500">После настройки ежедневной задачи здесь появится дата последнего успешного архива.</p>
            @endif
        </div>
        <p class="max-w-sm text-sm leading-6 text-slate-500">Архив создаётся сервером один раз в сутки и недоступен из браузера.</p>
    </div>
</section>

<section class="mt-5 space-y-4">
    @forelse($logs as $log)
        @php
            $old = $log->old_values ?? [];
            $new = $log->new_values ?? [];
            $restorable = in_array($log->subject_type, $restorableTypes, true) && $log->subject_id && $old !== [];
        @endphp
        <article class="card">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs text-slate-400">{{ $log->created_at->format('d.m.Y H:i:s') }} · {{ $log->user?->name ?? 'Система' }}</p>
                    <h2 class="mt-1 text-lg">{{ $log->action }}</h2>
                    @if($log->description)<p class="mt-1 text-sm text-slate-600">{{ $log->description }}</p>@endif
                </div>
                @if($restorable)
                    <form method="POST" action="{{ route('activity.restore', $log) }}" onsubmit="return confirm('Вернуть значения, которые были до этого изменения? Текущее состояние сохранится в журнале.');">
                        @csrf
                        <button class="btn-secondary whitespace-nowrap">Восстановить состояние до правки</button>
                    </form>
                @endif
            </div>
            @if($old !== [] || $new !== [])
                <details class="mt-4 rounded-xl bg-mist-50 p-3">
                    <summary class="cursor-pointer text-sm font-medium">Показать изменённые поля</summary>
                    <div class="mt-3 grid gap-3 lg:grid-cols-2">
                        <div><p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Было</p><pre class="max-h-72 overflow-auto whitespace-pre-wrap break-words rounded-lg bg-white p-3 text-xs">{{ json_encode($old, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></div>
                        <div><p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Стало</p><pre class="max-h-72 overflow-auto whitespace-pre-wrap break-words rounded-lg bg-white p-3 text-xs">{{ json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></div>
                    </div>
                </details>
            @endif
        </article>
    @empty
        <div class="card muted">Изменений пока нет.</div>
    @endforelse
</section>
<div class="mt-6">{{ $logs->links() }}</div>
@endsection
