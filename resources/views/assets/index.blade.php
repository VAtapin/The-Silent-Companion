@extends('layouts.app')
@section('title', 'Материалы')
@section('content')
<div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"><div><p class="text-sm text-amber-500">Фото, видео, звук, документы и ссылки</p><h1>Материалы</h1></div><a href="{{ route('assets.create') }}" class="btn-primary">Загрузить материал</a></div>
<form method="GET" class="card mt-6 grid gap-3 md:grid-cols-4"><input name="q" value="{{ request('q') }}" placeholder="Поиск по названию"><select name="type"><option value="">Все типы</option>@foreach(\App\Models\Asset::TYPES as $type)<option @selected(request('type')===$type)>{{ $type }}</option>@endforeach</select><select name="status"><option value="">Все статусы</option>@foreach(\App\Models\Asset::STATUSES as $status)<option @selected(request('status')===$status)>{{ $status }}</option>@endforeach</select><button class="btn-secondary">Применить фильтры</button></form>
<section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
@forelse($assets as $asset)
    @php($canDelete = ! in_array($asset->status, ['Утверждено', 'Используется в фильме', 'Финальная версия'], true) && ! $protectedAssetIds->contains($asset->id))
    <article class="card overflow-hidden p-0 transition hover:-translate-y-0.5 hover:border-amber-400"><a href="{{ route('assets.show',$asset) }}">@if($asset->thumbnail_path)<img src="{{ route('assets.thumbnail',$asset) }}" alt="" class="h-28 w-full object-cover">@else<div class="grid h-24 place-items-center bg-ink-900 text-sm text-mist-200">{{ $asset->type }}</div>@endif</a><div class="p-4"><div class="flex justify-between gap-3"><a href="{{ route('assets.show',$asset) }}"><h3>{{ $asset->title }}</h3></a><span class="badge bg-mist-100">{{ $asset->status }}</span></div><p class="mt-2 line-clamp-2 text-sm text-slate-500">{{ $asset->description }}</p><p class="mt-3 text-xs text-slate-400">{{ $asset->category?->name }} · {{ $asset->created_at->format('d.m.Y') }}</p><div class="mt-4 flex items-center justify-between border-t border-mist-100 pt-3"><a href="{{ route('assets.show',$asset) }}" class="text-sm font-semibold text-amber-700">Открыть</a>@if($canDelete)<form method="POST" action="{{ route('assets.destroy', $asset) }}" onsubmit="return confirm('Удалить материал «{{ addslashes($asset->title) }}»? Исходный файл останется в защищённом хранилище.')">@csrf @method('DELETE')<button class="text-sm font-semibold text-red-700">Удалить</button></form>@endif</div></div></article>
@empty
    <div class="card sm:col-span-2 xl:col-span-4"><p class="muted">По выбранным фильтрам материалов нет.</p></div>
@endforelse
</section><div class="mt-6">{{ $assets->links() }}</div>
@endsection
