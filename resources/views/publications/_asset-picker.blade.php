@php
    $initial = collect($selectedAssets ?? [])->map(function ($asset) {
        $youtubeId = $asset->youtubeId();
        $isImage = str_starts_with((string) $asset->mime_type, 'image/');
        $isVideo = str_starts_with((string) $asset->mime_type, 'video/');

        return [
            'id' => $asset->id,
            'title' => $asset->title,
            'type' => $youtubeId ? 'YouTube' : $asset->type,
            'status' => $asset->status,
            'kind' => $youtubeId ? 'youtube' : ($isImage ? 'image' : ($isVideo ? 'video' : 'other')),
            'thumbnail' => $youtubeId ? "https://i.ytimg.com/vi/{$youtubeId}/hqdefault.jpg" : ($isImage ? route('assets.thumbnail', $asset) : null),
        ];
    })->values();
@endphp
<div x-data="mediaPicker({ endpoint: @js(route('publications.media-options')), initial: @js($initial) })">
    <template x-for="asset in selected" :key="asset.id">
        <input type="hidden" name="asset_ids[]" :value="asset.id">
    </template>

    <div x-show="selected.length" class="mb-3 grid gap-2 sm:grid-cols-2">
        <template x-for="asset in selected" :key="asset.id">
            <div class="flex min-w-0 items-center gap-3 rounded-xl border border-mist-200 bg-white p-2">
                <div class="grid h-14 w-20 shrink-0 place-items-center overflow-hidden rounded-lg bg-ink-950 text-white">
                    <img x-show="asset.thumbnail" :src="asset.thumbnail" alt="" class="h-full w-full object-cover" loading="lazy">
                    <span x-show="!asset.thumbnail" class="text-xl" x-text="asset.kind === 'video' ? '▶' : '•'"></span>
                </div>
                <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold" x-text="asset.title"></p><p class="truncate text-xs text-slate-500" x-text="asset.type + ' · ' + asset.status"></p></div>
                <button type="button" @click="remove(asset.id)" class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-lg text-slate-500 hover:bg-red-50 hover:text-red-700" aria-label="Убрать материал">×</button>
            </div>
        </template>
    </div>
    <p x-show="!selected.length" class="mb-3 rounded-xl bg-mist-50 p-3 text-sm text-slate-500">Материалы ещё не выбраны.</p>
    <button type="button" @click="show()" class="btn-secondary w-full">Открыть медиатеку <span x-show="selected.length" x-text="'· выбрано ' + selected.length"></span></button>

    <template x-teleport="body">
        <div x-show="open" x-cloak @keydown.escape.window="open=false" class="fixed inset-0 z-[100] flex items-center justify-center p-3 sm:p-6">
            <button type="button" class="absolute inset-0 bg-ink-950/75" @click="open=false" aria-label="Закрыть медиатеку"></button>
            <section class="relative flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-mist-50 shadow-2xl">
                <header class="flex items-center justify-between border-b border-mist-200 bg-white px-4 py-3 sm:px-6"><div><h2>Медиатека</h2><p class="mt-1 text-xs text-slate-500">Найдено: <span x-text="total"></span> · выбрано: <span x-text="selected.length"></span></p></div><button type="button" @click="open=false" class="grid h-10 w-10 place-items-center rounded-xl bg-mist-100 text-2xl">×</button></header>
                <div class="grid gap-3 border-b border-mist-200 bg-white p-4 sm:grid-cols-[1fr_12rem_14rem] sm:px-6">
                    <input type="search" x-model="search" @input.debounce.400ms="load(true)" placeholder="Поиск по названию">
                    <select x-model="type" @change="load(true)"><option value="">Все типы</option><option>Фото</option><option>Видео</option><option>Аудио</option><option>Документ</option><option>Текст</option><option>Ссылка</option></select>
                    <select x-model="status" @change="load(true)"><option value="">Все статусы</option><option>Черновик</option><option>Загружено</option><option>Создано ИИ</option><option>На проверке</option><option>Утверждено</option><option>Отклонено</option><option>Требуется переснять</option><option>Используется в фильме</option><option>Финальная версия</option></select>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6">
                    <p x-show="error" x-text="error" class="mb-4 rounded-xl bg-red-50 p-3 text-sm text-red-700"></p>
                    <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                        <template x-for="asset in items" :key="asset.id">
                            <button type="button" @click="toggle(asset)" :class="isSelected(asset.id) ? 'border-amber-500 bg-amber-50 ring-2 ring-amber-300' : 'border-mist-200 bg-white hover:border-amber-300'" class="relative overflow-hidden rounded-xl border-2 text-left transition">
                                <span x-show="isSelected(asset.id)" class="absolute right-2 top-2 z-10 grid h-7 w-7 place-items-center rounded-full bg-amber-500 font-bold text-white shadow">✓</span>
                                <span class="grid aspect-video place-items-center overflow-hidden bg-ink-950 text-white"><img x-show="asset.thumbnail" :src="asset.thumbnail" alt="" class="h-full w-full object-cover" loading="lazy"><span x-show="!asset.thumbnail" class="text-center"><span class="block text-3xl" x-text="asset.kind === 'video' ? '▶' : '•'"></span><span class="mt-2 block text-xs" x-text="asset.type"></span></span></span>
                                <span class="block p-3"><b class="block truncate text-sm" x-text="asset.title"></b><span class="mt-1 block truncate text-xs text-slate-500" x-text="asset.type + ' · ' + asset.status + ' · №' + asset.id"></span></span>
                            </button>
                        </template>
                    </div>
                    <p x-show="loaded && !loading && !items.length" class="rounded-xl bg-white p-5 text-center text-sm text-slate-500">По выбранным фильтрам материалов нет.</p>
                    <div x-show="loading" class="py-8 text-center text-sm text-slate-500">Загрузка материалов…</div>
                    <div x-show="!loading && page < lastPage" class="mt-6 text-center"><button type="button" @click="more()" class="btn-secondary">Показать ещё 24</button></div>
                </div>
                <footer class="flex items-center justify-between gap-3 border-t border-mist-200 bg-white px-4 py-3 sm:px-6"><p class="text-sm text-slate-500">Выбрано: <b x-text="selected.length"></b></p><button type="button" @click="open=false" class="btn-primary">Готово</button></footer>
            </section>
        </div>
    </template>
</div>
