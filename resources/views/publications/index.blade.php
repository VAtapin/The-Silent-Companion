@extends('layouts.app')
@section('title','Публикации')
@section('content')
<div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between"><div><p class="text-sm text-amber-500">Ничего не публикуется автоматически</p><h1>Публичная страница</h1></div><a href="{{ route('public.home') }}" target="_blank" class="btn-secondary">Открыть сайт</a></div>
<div x-data="{tab: @js(request('tab') === 'site' || $errors->has('poster_file') ? 'site' : 'materials')}" class="mt-7"><div class="flex flex-wrap gap-2"><button @click="tab='materials'" :class="tab==='materials'?'bg-ink-900 text-white':'bg-white'" class="rounded-xl px-4 py-2 text-sm">Публикации</button><button @click="tab='site'" :class="tab==='site'?'bg-ink-900 text-white':'bg-white'" class="rounded-xl px-4 py-2 text-sm">Главная страница</button><button @click="tab='donations'" :class="tab==='donations'?'bg-ink-900 text-white':'bg-white'" class="rounded-xl px-4 py-2 text-sm">Пожертвования</button></div>
<section x-show="tab==='materials'" class="mt-5 grid gap-6 xl:grid-cols-[1.2fr_.8fr]"><div class="space-y-4">@forelse($publications as $publication)<article class="card" x-data="{edit:false}"><div class="flex items-start justify-between gap-3"><div><span class="badge {{ $publication->is_published&&$publication->status==='Опубликовано'?'bg-emerald-100 text-emerald-700':'bg-slate-100 text-slate-600' }}">{{ $publication->status }}</span><h2 class="mt-2">{{ $publication->title }}</h2><p class="mt-2 text-sm text-slate-600">{{ $publication->description }}</p><p class="mt-2 text-xs text-slate-400">Материалов: {{ $publication->assets->count() }} · {{ $publication->author?->name }}</p></div><button @click="edit=!edit" class="text-sm text-amber-700">Изменить</button></div><form x-show="edit" method="POST" action="{{ route('publications.update',$publication) }}" class="mt-4 space-y-3 rounded-xl bg-mist-50 p-4">@csrf @method('PUT')<input name="title" value="{{ $publication->title }}" required><textarea name="description">{{ $publication->description }}</textarea><div class="field-grid"><input name="type" value="{{ $publication->type }}" required><select name="status">@foreach(\App\Models\Publication::STATUSES as $status)<option @selected($status===$publication->status)>{{ $status }}</option>@endforeach</select><input type="datetime-local" name="published_at" value="{{ $publication->published_at?->format('Y-m-d\TH:i') }}"><input type="number" min="0" name="sort_order" value="{{ $publication->sort_order }}"></div><div><label>Материалы</label><select multiple name="asset_ids[]" class="min-h-40">@foreach($assets as $asset)<option value="{{ $asset->id }}" @selected($publication->assets->contains($asset))>{{ $asset->title }} · {{ $asset->status }}</option>@endforeach</select></div><label class="flex gap-2"><input class="h-4 w-4" type="checkbox" name="is_published" value="1" @checked($publication->is_published)><span>Разрешить показ на публичной странице</span></label><button class="btn-primary">Сохранить</button></form></article>@empty<div class="card muted">Публикаций пока нет.</div>@endforelse</div>
<aside class="card self-start"><h2>Новая публикация</h2><form method="POST" action="{{ route('publications.store') }}" class="mt-4 space-y-3">@csrf<div><label>Заголовок</label><input name="title" required></div><div><label>Описание</label><textarea name="description"></textarea></div><div class="field-grid"><div><label>Тип</label><select name="type"><option>Фото</option><option>Видео</option><option>Новость</option><option>Афиша</option></select></div><div><label>Статус</label><select name="status">@foreach(\App\Models\Publication::STATUSES as $status)<option>{{ $status }}</option>@endforeach</select></div></div><div><label>Материалы</label><select multiple name="asset_ids[]" class="min-h-52">@foreach($assets as $asset)<option value="{{ $asset->id }}">{{ $asset->title }} · {{ $asset->status }}</option>@endforeach</select><p class="mt-1 text-xs text-slate-400">Рабочий статус материала не публикует его сам по себе.</p></div><input type="hidden" name="sort_order" value="0"><button class="btn-primary w-full">Создать черновик</button></form></aside></section>
<section x-show="tab==='site'" class="mt-5 grid max-w-6xl gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
    <div class="card">
        <h2>Оформление главной</h2>
        <form method="POST" action="{{ route('publications.poster') }}" enctype="multipart/form-data" class="mt-5" x-data="posterUpload({{ $posterUploadMaxBytes }})">
            @csrf
            <input x-ref="file" class="sr-only" type="file" name="poster_file" accept="image/jpeg,image/png,image/webp" @change="select($event.target.files[0])">
            <div class="overflow-hidden rounded-2xl border-2 border-dashed border-amber-400/60 bg-amber-50 transition hover:border-amber-500"
                 @dragover.prevent @drop.prevent="select($event.dataTransfer.files[0])">
                <button type="button" class="block w-full p-5 text-left" @click="$refs.file.click()">
                    <template x-if="!preview">
                        <div class="py-5 text-center">
                            <span class="inline-flex rounded-full bg-white px-4 py-2 text-sm font-semibold text-ink-900 shadow-sm">Выбрать афишу</span>
                            <p class="mt-3 text-sm font-medium text-ink-900">или перетащите изображение сюда</p>
                            <p class="mt-1 text-xs text-slate-500">JPG, PNG или WEBP · фактический лимит сервера {{ number_format($posterUploadMaxBytes / 1048576, 1, ',', ' ') }} МБ</p>
                            <p class="mt-1 text-xs text-slate-500">Большой PNG автоматически преобразуется в качественный JPG.</p>
                        </div>
                    </template>
                    <template x-if="preview">
                        <div class="grid items-center gap-5 sm:grid-cols-[9rem_1fr]">
                            <img :src="preview" alt="Предварительный просмотр афиши" class="aspect-[2/3] w-full rounded-xl object-cover shadow-md">
                            <div>
                                <p class="font-semibold text-ink-900" x-text="fileName"></p>
                                <p class="mt-1 text-sm text-slate-500" x-text="fileSize"></p>
                                <p class="mt-3 text-sm text-amber-700">Нажмите, чтобы выбрать другой файл</p>
                            </div>
                        </div>
                    </template>
                </button>
            </div>
            <p x-show="processing" class="mt-3 text-sm text-amber-700">Подготавливаю изображение…</p>
            <p x-show="error" x-text="error" class="mt-3 rounded-xl bg-red-50 p-3 text-sm text-red-700"></p>
            @error('poster_file')<p class="mt-3 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ $message }}</p>@enderror
            <div class="mt-4 flex flex-wrap gap-2">
                <button class="btn-primary" :disabled="!fileName || processing">Загрузить и установить афишу</button>
                <button x-show="fileName" type="button" class="btn-secondary" @click="clear()">Убрать выбранный файл</button>
            </div>
        </form>

        <div class="my-7 border-t border-mist-200"></div>
        <form method="POST" action="{{ route('publications.site') }}" class="space-y-5">
            @csrf
            @method('PUT')
            <div><label>Публичное описание</label><textarea class="min-h-40" name="public_summary">{{ old('public_summary', $siteSettings->public_summary) }}</textarea></div>
            <div>
                <label>Выбрать ранее загруженное изображение</label>
                <select name="poster_asset_id">
                    <option value="">Без афиши</option>
                    @foreach($assets->filter(fn($asset) => str_starts_with((string) $asset->mime_type, 'image/')) as $asset)
                        <option value="{{ $asset->id }}" @selected(old('poster_asset_id', $siteSettings->poster_asset_id)===$asset->id)>{{ $asset->title }}</option>
                    @endforeach
                </select>
            </div>
            <div><label>Опубликованный контакт</label><input name="contact" value="{{ old('contact', $siteSettings->contact) }}"></div>
            <div><label>Официальные ссылки — по одной в строке</label><textarea name="official_links_text">{{ old('official_links_text', collect($siteSettings->official_links)->pluck('url')->join("\n")) }}</textarea></div>
            <button class="btn-primary">Сохранить остальные настройки</button>
        </form>
    </div>
    <aside class="card self-start lg:sticky lg:top-7">
        <h2>Как она размещается</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">На компьютере афиша показывается полностью справа от названия и описания, а на телефоне — под текстом. Её мягко затемнённая копия создаёт фон первого экрана.</p>
        @if($siteSettings->poster_asset_id)
            <div class="relative mt-5 aspect-[2/3] overflow-hidden rounded-2xl bg-ink-950">
                <img src="{{ route('public.media', $siteSettings->poster_asset_id) }}" alt="Текущая афиша" class="h-full w-full object-cover opacity-70">
                <div class="absolute inset-0 bg-gradient-to-t from-ink-950/85 via-transparent to-transparent"></div>
                <div class="absolute inset-x-0 bottom-0 p-4 text-white"><p class="text-xs uppercase tracking-[.22em] text-amber-400">Текущая афиша</p><p class="mt-1 text-xl font-semibold">Тихий спутник</p></div>
            </div>
        @else
            <div class="mt-5 flex aspect-[2/3] items-center justify-center rounded-2xl bg-mist-100 p-6 text-center text-sm text-slate-500">Афиша ещё не выбрана</div>
        @endif
        <a href="{{ route('public.home') }}" target="_blank" class="btn-secondary mt-4 w-full">Посмотреть публичный сайт</a>
    </aside>
</section>
<section x-show="tab==='donations'" class="card mt-5 max-w-3xl"><h2>Поддержать создание фильма</h2><form method="POST" action="{{ route('publications.donations') }}" class="mt-4 space-y-4">@csrf @method('PUT')<div><label>Заголовок</label><input name="title" value="{{ $donation->title }}" required></div><div><label>Описание цели</label><textarea name="goal_description">{{ $donation->goal_description }}</textarea></div><div><label>Банковские реквизиты</label><textarea name="bank_details">{{ $donation->bank_details }}</textarea></div><div><label>PayPal или другая ссылка</label><input type="url" name="payment_url" value="{{ $donation->payment_url }}"></div><div><label>Дополнительные способы</label><textarea name="additional_methods">{{ $donation->additional_methods }}</textarea></div><div><label>Контакт</label><input name="contact" value="{{ $donation->contact }}"></div><div class="field-grid"><div><label>Изображение</label><select name="image_asset_id"><option value="">—</option>@foreach($assets as $asset)<option value="{{ $asset->id }}" @selected($donation->image_asset_id===$asset->id)>{{ $asset->title }}</option>@endforeach</select></div><div><label>QR-код</label><select name="qr_asset_id"><option value="">—</option>@foreach($assets as $asset)<option value="{{ $asset->id }}" @selected($donation->qr_asset_id===$asset->id)>{{ $asset->title }}</option>@endforeach</select></div></div><label class="flex gap-2"><input class="h-4 w-4" type="checkbox" name="is_visible" value="1" @checked($donation->is_visible)><span>Показывать блок на публичной странице</span></label><button class="btn-primary">Сохранить настройки</button></form></section>
</div>
@endsection
