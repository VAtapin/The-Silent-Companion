@php
    [$helpTitle, $helpItems] = match (true) {
        request()->routeIs('structure.*', 'acts.*', 'scenes.*', 'shots.*') => ['Акты, сцены и кадры', ['Слева находятся уже созданные элементы фильма. Кнопки «Изменить сцену» и «Изменить» открывают их редактирование.', 'Справа создаются только новые элементы. Для нового кадра сначала выберите сцену: следующий код и номер рассчитаются автоматически.', '«Проанализировать с ИИ» открывает помощник с выбранной сценой или кадром в качестве основного объекта.']],
        request()->routeIs('ai.*') => ['ИИ-помощник', ['Основной объект определяет исходный текст и место, куда можно применить результат.', 'Дополнительный контекст помогает ИИ, но не заменяет исходный текст.', 'ИИ ничего не изменяет автоматически: результат нужно отдельно принять на странице запроса.']],
        request()->routeIs('project.*') => ['Карточка проекта', ['Здесь хранится утверждаемая концепция фильма: логлайн, синопсис, визуальные и звуковые правила.', 'Изменения карточки версионируются и записываются в историю.']],
        request()->routeIs('checklist.*') => ['Чек-лист', ['Автоматические пункты считаются по прикреплённым и утверждённым материалам.', 'Ручные пункты закрываются участником команды после фактической проверки.']],
        request()->routeIs('assets.*') => ['Материалы', ['Медиатека хранит фотографии, видео, аудио и документы.', 'Статус «Утверждено» означает, что материал можно использовать в производстве или публикации.']],
        request()->routeIs('catalog.*') => ['Подготовка съёмок', ['Персонажи, места и реквизит являются производственными справочниками.', 'Фиксируйте здесь внешний вид, непрерывность, разрешения и требования к съёмке.']],
        request()->routeIs('documents.*') => ['Документы', ['Документы редактируются с версиями.', 'Предыдущую версию можно восстановить, не удаляя последующие записи истории.']],
        request()->routeIs('publications.*') => ['Публичный сайт', ['Ничего не публикуется автоматически.', 'Публикация появляется на сайте только после команды «Показать на сайте».', 'Impressum и Datenschutz редактируются во вкладке «Правовые страницы».']],
        request()->routeIs('team.*') => ['Команда', ['Все активные пользователи имеют одинаковые права.', 'Деактивированный пользователь не сможет войти, но его история действий сохранится.']],
        request()->routeIs('activity.*') => ['История изменений', ['Здесь видно, кто и что изменил.', 'Для поддерживаемых записей можно восстановить предыдущие значения.']],
        default => ['Рабочая зона', ['Используйте разделы слева для перехода между подготовкой фильма, материалами, публикациями и командой.', 'Полное руководство доступно в разделе «Помощь».']],
    };
@endphp

<button type="button" @click="help=true" class="fixed bottom-5 right-5 z-30 grid h-10 w-10 place-items-center rounded-full bg-amber-400 text-lg font-semibold italic text-ink-950 shadow-lg ring-1 ring-black/10" aria-label="Открыть помощь">i</button>
<div x-show="help" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="Справка по странице">
    <button type="button" @click="help=false" class="absolute inset-0 h-full w-full bg-black/40" aria-label="Закрыть помощь"></button>
    <aside class="absolute inset-y-0 right-0 w-full max-w-md overflow-y-auto bg-white p-6 shadow-2xl">
        <div class="flex items-start justify-between gap-4"><div><p class="text-xs uppercase tracking-[.2em] text-amber-600">Справка</p><h2 class="mt-2 text-2xl">{{ $helpTitle }}</h2></div><button type="button" @click="help=false" class="grid h-10 w-10 place-items-center rounded-full bg-mist-100 text-2xl">×</button></div>
        <ol class="mt-7 space-y-4">@foreach($helpItems as $item)<li class="flex gap-3 text-sm leading-6 text-slate-700"><span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-amber-100 text-xs font-semibold text-amber-800">{{ $loop->iteration }}</span><span>{{ $item }}</span></li>@endforeach</ol>
        <a href="{{ route('help.index') }}" class="btn-primary mt-8 w-full">Открыть полное руководство</a>
    </aside>
</div>
