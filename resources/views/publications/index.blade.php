@extends('layouts.app')
@section('title','Публикации')
@section('content')
<div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between"><div><p class="text-sm text-amber-500">Ничего не публикуется автоматически</p><h1>Публичная страница</h1></div><a href="{{ route('public.home') }}" target="_blank" class="btn-secondary">Открыть сайт</a></div>
<div x-data="{tab: @js(in_array(request('tab'), ['site', 'donations', 'legal'], true) ? request('tab') : (($errors->has('impressum') || $errors->has('privacy_policy')) ? 'legal' : ($errors->has('poster_file') ? 'site' : 'materials')))}" class="mt-7"><div class="flex flex-wrap gap-2"><button @click="tab='materials'" :class="tab==='materials'?'bg-ink-900 text-white':'bg-white'" class="rounded-xl px-4 py-2 text-sm">Публикации</button><button @click="tab='site'" :class="tab==='site'?'bg-ink-900 text-white':'bg-white'" class="rounded-xl px-4 py-2 text-sm">Главная страница</button><button @click="tab='donations'" :class="tab==='donations'?'bg-ink-900 text-white':'bg-white'" class="rounded-xl px-4 py-2 text-sm">Пожертвования</button><button @click="tab='legal'" :class="tab==='legal'?'bg-ink-900 text-white':'bg-white'" class="rounded-xl px-4 py-2 text-sm">Правовые страницы</button></div>
<section x-show="tab==='materials'" class="mt-5">
    <div class="mb-5 rounded-2xl border border-amber-400/50 bg-amber-50 p-4 text-sm leading-6 text-slate-700">
        <b>Где появляются публикации:</b> на главной странице сразу под полноэкранной афишей, в разделе «Опубликованные материалы».
        <a href="{{ route('public.home') }}#materials" target="_blank" class="ml-1 font-semibold text-amber-700">Открыть этот раздел</a>
    </div>
    <div class="grid gap-6 xl:grid-cols-[1.2fr_.8fr]">
        <div class="space-y-4">
            @forelse($publications as $publication)
                @php($visible = $publication->is_published && $publication->status === 'Опубликовано' && !$publication->unpublished_at && (!$publication->published_at || $publication->published_at->isPast()))
                <article class="card" x-data="{edit:false}">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <span class="badge {{ $visible ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $visible ? 'На сайте' : 'Не показывается' }}</span>
                            <h2 class="mt-2">{{ $publication->title }}</h2>
                            <p class="mt-2 text-sm text-slate-600">{{ $publication->description }}</p>
                            <p class="mt-2 text-xs {{ $publication->assets->isEmpty() ? 'font-semibold text-red-600' : 'text-slate-400' }}">Материалов: {{ $publication->assets->count() }} · {{ $publication->author?->name }}</p>
                            <p class="mt-1 text-xs {{ $publication->title_en && $publication->title_de ? 'text-emerald-700' : 'text-amber-700' }}">{{ $publication->title_en && $publication->title_de ? 'Переводы EN и DE готовы' : 'Переводы EN и DE ещё не созданы' }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('publications.translate', $publication) }}" @if($publication->title_en || $publication->title_de) onsubmit="return confirm('Заменить существующие английский и немецкий переводы?')" @endif>@csrf<button class="btn-secondary">Перевести с ИИ</button></form>
                            <form method="POST" action="{{ route('publications.visibility', $publication) }}">@csrf<input type="hidden" name="visible" value="{{ $visible ? 0 : 1 }}"><button class="{{ $visible ? 'btn-secondary' : 'btn-primary' }}">{{ $visible ? 'Скрыть с сайта' : 'Показать на сайте' }}</button></form>
                            <button type="button" @click="edit=!edit" class="btn-secondary">Изменить</button>
                            <form method="POST" action="{{ route('publications.destroy', $publication) }}" onsubmit="return confirm('Удалить эту публикацию? Прикреплённые файлы останутся в медиатеке.')">@csrf @method('DELETE')<button class="btn-secondary text-red-700">Удалить</button></form>
                        </div>
                    </div>
                    <form x-show="edit" x-cloak method="POST" action="{{ route('publications.update',$publication) }}" enctype="multipart/form-data" class="mt-4 space-y-4 rounded-xl bg-mist-50 p-4">
                        @csrf @method('PUT')
                        <div><label>Заголовок</label><input name="title" value="{{ $publication->title }}" required></div>
                        <div><label>Описание (RU)</label><textarea name="description">{{ $publication->description }}</textarea></div>
                        <details class="rounded-xl border border-mist-200 bg-white p-4"><summary class="cursor-pointer font-semibold">Переводы EN / DE</summary><div class="mt-4 space-y-4"><div class="field-grid"><div><label>Title (EN)</label><input name="title_en" value="{{ $publication->title_en }}"></div><div><label>Titel (DE)</label><input name="title_de" value="{{ $publication->title_de }}"></div></div><div class="field-grid"><div><label>Description (EN)</label><textarea name="description_en">{{ $publication->description_en }}</textarea></div><div><label>Beschreibung (DE)</label><textarea name="description_de">{{ $publication->description_de }}</textarea></div></div></div></details>
                        <div class="field-grid"><div><label>Тип</label><input name="type" value="{{ $publication->type }}" required></div><div><label>Порядок</label><input type="number" min="0" name="sort_order" value="{{ $publication->sort_order }}"></div></div>
                        <input type="hidden" name="status" value="{{ $publication->status }}">
                        <input type="hidden" name="published_at" value="{{ $publication->published_at?->format('Y-m-d\TH:i') }}">
                        <div class="rounded-xl border border-mist-200 bg-white p-4"><h3>Добавить новый материал</h3><div class="mt-3 grid gap-4 md:grid-cols-2"><div><label>Фото или видео с компьютера</label><input type="file" name="media_file" accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm"><p class="mt-1 text-xs text-slate-500">Лимит сервера: {{ number_format($serverUploadMaxBytes / 1048576, 1, ',', ' ') }} МБ</p></div><div><label>Или ссылка YouTube</label><input type="url" name="youtube_url" placeholder="https://www.youtube.com/watch?v=..."></div></div></div>
                        <div><label>Выбрать из медиатеки</label>@include('publications._asset-picker', ['selectedAssets' => $publication->assets])</div>
                        <button class="btn-primary">Сохранить изменения</button>
                    </form>
                </article>
            @empty
                <div class="card muted">Публикаций пока нет.</div>
            @endforelse
        </div>
        <aside class="card self-start xl:sticky xl:top-7">
            <h2>Новая публикация</h2>
            <form method="POST" action="{{ route('publications.store') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <div><label>Заголовок</label><input name="title" required></div>
                <div><label>Описание (RU)</label><textarea name="description"></textarea></div>
                <details class="rounded-xl border border-mist-200 bg-mist-50 p-4"><summary class="cursor-pointer font-semibold">Переводы EN / DE</summary><div class="mt-4 space-y-4"><div><label>Title (EN)</label><input name="title_en"></div><div><label>Description (EN)</label><textarea name="description_en"></textarea></div><div><label>Titel (DE)</label><input name="title_de"></div><div><label>Beschreibung (DE)</label><textarea name="description_de"></textarea></div></div></details>
                <div><label>Тип</label><select name="type"><option>Фото</option><option>Видео</option><option>Новость</option><option>Афиша</option></select></div>
                <div class="rounded-xl border border-amber-400/50 bg-amber-50 p-4"><h3>Добавить новый материал</h3><div class="mt-3 space-y-4"><div><label>Загрузить фото или видео</label><input type="file" name="media_file" accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm"><p class="mt-1 text-xs text-slate-500">JPG, PNG, WEBP, MP4, MOV или WEBM · лимит {{ number_format($serverUploadMaxBytes / 1048576, 1, ',', ' ') }} МБ</p></div><div><label>Или вставить ссылку YouTube</label><input type="url" name="youtube_url" placeholder="https://youtu.be/... или https://www.youtube.com/watch?v=..."></div></div></div>
                <div><label>Или выбрать из медиатеки</label>@include('publications._asset-picker', ['selectedAssets' => $assets->whereIn('id', old('asset_ids', []))])</div>
                <input type="hidden" name="sort_order" value="0">
                <div class="grid gap-2"><button name="publish_action" value="publish" class="btn-primary w-full">Создать и опубликовать</button><button name="publish_action" value="draft" class="btn-secondary w-full">Сохранить как черновик</button></div>
            </form>
        </aside>
    </div>
</section>
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
                            <p class="mt-1 text-xs text-slate-500">Широкое изображение 16:9 · JPG, PNG или WEBP · лимит {{ number_format($posterUploadMaxBytes / 1048576, 1, ',', ' ') }} МБ</p>
                            <p class="mt-1 text-xs text-slate-500">Большой PNG автоматически преобразуется в качественный JPG.</p>
                        </div>
                    </template>
                    <template x-if="preview">
                        <div class="grid items-center gap-5 sm:grid-cols-[16rem_1fr]">
                            <img :src="preview" alt="Предварительный просмотр афиши" class="aspect-video w-full rounded-xl object-cover shadow-md">
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
            <div><label>Публичное описание (RU)</label><textarea class="min-h-40" name="public_summary">{{ old('public_summary', $siteSettings->public_summary) }}</textarea></div>
            <div class="field-grid"><div><label>Public description (EN)</label><textarea class="min-h-32" name="public_summary_en">{{ old('public_summary_en', $siteSettings->public_summary_en) }}</textarea></div><div><label>Öffentliche Beschreibung (DE)</label><textarea class="min-h-32" name="public_summary_de">{{ old('public_summary_de', $siteSettings->public_summary_de) }}</textarea></div></div>
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
        <p class="mt-2 text-sm leading-6 text-slate-600">Широкая афиша 16:9 заполняет весь первый экран как фон. Название, слоган и описание накладываются поверх слева, а градиент сохраняет читаемость текста.</p>
        @if($siteSettings->poster_asset_id)
            <div class="relative mt-5 aspect-video overflow-hidden rounded-2xl bg-ink-950">
                <img src="{{ route('public.media', $siteSettings->poster_asset_id) }}" alt="Текущая афиша" class="h-full w-full object-cover opacity-70">
                <div class="absolute inset-0 bg-gradient-to-r from-ink-950/90 via-ink-950/45 to-transparent"></div>
                <div class="absolute inset-x-0 bottom-0 p-4 text-white"><p class="text-xs uppercase tracking-[.22em] text-amber-400">Текущая афиша</p><p class="mt-1 text-xl font-semibold">Тихий спутник</p></div>
            </div>
        @else
            <div class="mt-5 flex aspect-video items-center justify-center rounded-2xl bg-mist-100 p-6 text-center text-sm text-slate-500">Фоновая афиша ещё не выбрана</div>
        @endif
        <a href="{{ route('public.home') }}" target="_blank" class="btn-secondary mt-4 w-full">Посмотреть публичный сайт</a>
    </aside>
</section>
<section x-show="tab==='donations'" class="card mt-5 max-w-3xl"><h2>Поддержать создание фильма</h2><form method="POST" action="{{ route('publications.donations') }}" class="mt-4 space-y-4">@csrf @method('PUT')<div><label>Заголовок (RU)</label><input name="title" value="{{ $donation->title }}" required></div><div><label>Описание цели (RU)</label><textarea name="goal_description">{{ $donation->goal_description }}</textarea></div><details class="rounded-xl border border-mist-200 p-4"><summary class="cursor-pointer font-semibold">Переводы EN / DE</summary><div class="mt-4 space-y-4"><div class="field-grid"><div><label>Title (EN)</label><input name="title_en" value="{{ $donation->title_en }}"></div><div><label>Titel (DE)</label><input name="title_de" value="{{ $donation->title_de }}"></div></div><div class="field-grid"><div><label>Goal (EN)</label><textarea name="goal_description_en">{{ $donation->goal_description_en }}</textarea></div><div><label>Ziel (DE)</label><textarea name="goal_description_de">{{ $donation->goal_description_de }}</textarea></div></div><div class="field-grid"><div><label>Additional methods (EN)</label><textarea name="additional_methods_en">{{ $donation->additional_methods_en }}</textarea></div><div><label>Weitere Möglichkeiten (DE)</label><textarea name="additional_methods_de">{{ $donation->additional_methods_de }}</textarea></div></div></div></details><div><label>Банковские реквизиты</label><textarea name="bank_details">{{ $donation->bank_details }}</textarea></div><div><label>PayPal или другая ссылка</label><input type="url" name="payment_url" value="{{ $donation->payment_url }}"></div><div><label>Дополнительные способы (RU)</label><textarea name="additional_methods">{{ $donation->additional_methods }}</textarea></div><div><label>Контакт</label><input name="contact" value="{{ $donation->contact }}"></div><div class="field-grid"><div><label>Изображение</label><select name="image_asset_id"><option value="">—</option>@foreach($assets as $asset)<option value="{{ $asset->id }}" @selected($donation->image_asset_id===$asset->id)>{{ $asset->title }}</option>@endforeach</select></div><div><label>QR-код</label><select name="qr_asset_id"><option value="">—</option>@foreach($assets as $asset)<option value="{{ $asset->id }}" @selected($donation->qr_asset_id===$asset->id)>{{ $asset->title }}</option>@endforeach</select></div></div><label class="flex gap-2"><input class="h-4 w-4" type="checkbox" name="is_visible" value="1" @checked($donation->is_visible)><span>Показывать блок на публичной странице</span></label><button class="btn-primary">Сохранить настройки</button></form></section>
<section x-show="tab==='legal'" class="mt-5 grid max-w-6xl gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
    <div class="card">
        <h2>Impressum и Datenschutz</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">Обе страницы публикуются только на немецком языке. Ссылки на них автоматически показываются внизу русской, английской и немецкой версии сайта.</p>
        <div class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-900"><b>Обязательно замените поля в квадратных скобках.</b> Пока не указаны владелец, полный адрес, телефон, хостинг и срок хранения журналов, шаблон нельзя считать окончательно заполненным.</div>
        <form method="POST" action="{{ route('publications.legal') }}" class="mt-5 space-y-5">
            @csrf
            @method('PUT')
            <div><label>Impressum (DE)</label><textarea class="min-h-[32rem] font-mono text-sm leading-6" name="impressum" required>{{ old('impressum', $siteSettings->legalContent('impressum')) }}</textarea></div>
            <div><label>Datenschutzerklärung (DE)</label><textarea class="min-h-[44rem] font-mono text-sm leading-6" name="privacy_policy" required>{{ old('privacy_policy', $siteSettings->legalContent('datenschutz')) }}</textarea></div>
            <button class="btn-primary">Сохранить правовые страницы</button>
        </form>
    </div>
    <aside class="card self-start xl:sticky xl:top-7">
        <h2>Предпросмотр</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">Поддерживается Markdown: <code># Заголовок</code>, <code>## Раздел</code>, списки и ссылки. Произвольный HTML удаляется.</p>
        <div class="mt-4 grid gap-2">
            <a href="{{ route('public.legal', 'impressum') }}" target="_blank" class="btn-secondary w-full">Открыть Impressum</a>
            <a href="{{ route('public.legal', 'datenschutz') }}" target="_blank" class="btn-secondary w-full">Открыть Datenschutz</a>
        </div>
        <p class="mt-4 text-xs leading-5 text-slate-500">Все изменения записываются в историю. Предыдущую версию можно восстановить в разделе «История изменений».</p>
    </aside>
</section>
</div>
@endsection
