@extends('layouts.app')
@section('title', $document->title)
@section('content')
<div x-data="{ editing: {{ $errors->any() ? 'true' : 'false' }} }">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <p class="text-sm text-amber-500">{{ $document->type }} · версия {{ $document->version }}</p>
            <h1>{{ $document->title }}</h1>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" class="btn-secondary" @click="editing = !editing" x-text="editing ? 'Отменить редактирование' : 'Редактировать сценарий'"></button>
            <a href="{{ route('ai.index',['document'=>$document->id]) }}" class="btn-primary">Работа с текстом через ИИ</a>
            @if($document->source_path)
                <a href="{{ route('documents.source',$document) }}" class="btn-secondary">Скачать исходный {{ strtoupper(pathinfo($document->source_name,PATHINFO_EXTENSION)) }}</a>
            @endif
        </div>
    </div>

    <section x-show="editing" x-cloak class="card mt-7">
        <div class="max-w-5xl">
            <h2>Новая версия сценария</h2>
            <p class="mt-2 muted">Используйте Markdown: <code>#</code> для заголовка, <code>##</code> для раздела, <code>**текст**</code> для жирного текста. Исходный файл в DOC не изменяется.</p>
            <form method="POST" action="{{ route('documents.update',$document) }}" class="mt-5 space-y-4">
                @csrf
                @method('PUT')
                <div class="field-grid">
                    <div><label>Название</label><input name="title" value="{{ old('title', $document->title) }}" required></div>
                    <div><label>Тип документа</label><input name="type" value="{{ old('type', $document->type) }}" required></div>
                </div>
                <div>
                    <label>Текст сценария</label>
                    <textarea class="min-h-[65vh] font-mono text-sm leading-6" name="content" spellcheck="true">{{ old('content', $document->content) }}</textarea>
                </div>
                <div><label>Комментарий к версии</label><textarea class="min-h-24" name="change_note" placeholder="Например: исправлены описания персонажей и сцена финала">{{ old('change_note') }}</textarea></div>
                <div class="flex flex-wrap gap-2">
                    <button class="btn-primary">Сохранить новую версию</button>
                    <button type="button" class="btn-secondary" @click="editing = false">Отмена</button>
                </div>
            </form>
        </div>
    </section>

    <div x-show="!editing" class="mt-7 grid gap-6 xl:grid-cols-[minmax(0,1fr)_18rem]">
        <article class="card screenplay-paper">
            <div class="prose-film">{!! \Illuminate\Support\Str::markdown($document->content ?? '', ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
        </article>
        <aside class="space-y-5">
            <div class="card">
                <div class="flex items-center justify-between gap-3">
                    <h2>История версий</h2>
                    <button type="button" @click="editing = true; window.scrollTo({top: 0, behavior: 'smooth'})" class="text-sm text-amber-700">Новая версия</button>
                </div>
                <div class="mt-3 space-y-3">
                    @forelse($document->versions->sortByDesc('version') as $version)
                        <div class="rounded-xl bg-mist-50 p-3 text-sm">
                            <b>Версия {{ $version->version }}</b>
                            <p class="mt-1 text-xs text-slate-500">{{ $version->change_note ?: 'Первоначальный импорт' }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ $version->created_at->format('d.m.Y H:i') }}</p>
                        </div>
                    @empty
                        <p class="muted">Предыдущих версий пока нет.</p>
                    @endforelse
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection
