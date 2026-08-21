<div class="grid gap-3 sm:grid-cols-2">
    @forelse($assets as $asset)
        @php($youtubeId = $asset->youtubeId())
        <label class="group cursor-pointer overflow-hidden rounded-xl border-2 border-mist-200 bg-white transition has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50">
            <div class="relative aspect-video bg-ink-950">
                @if($youtubeId)
                    <img src="https://i.ytimg.com/vi/{{ $youtubeId }}/hqdefault.jpg" alt="" class="h-full w-full object-cover">
                    <span class="absolute inset-0 grid place-items-center"><span class="grid h-12 w-12 place-items-center rounded-full bg-red-600 text-lg text-white shadow">▶</span></span>
                @elseif($asset->mime_type && str_starts_with($asset->mime_type, 'image/'))
                    <img src="{{ route('assets.preview', $asset) }}" alt="" class="h-full w-full object-cover">
                @elseif($asset->mime_type && str_starts_with($asset->mime_type, 'video/'))
                    <div class="grid h-full place-items-center text-white"><span class="text-center"><span class="block text-3xl">▶</span><span class="mt-2 block text-xs">Загруженное видео</span></span></div>
                @else
                    <div class="grid h-full place-items-center text-sm text-mist-200">{{ $asset->type }}</div>
                @endif
                <input class="absolute left-3 top-3 h-5 w-5" type="checkbox" name="asset_ids[]" value="{{ $asset->id }}" @checked(in_array($asset->id, $selectedIds ?? [], true))>
            </div>
            <span class="block p-3"><b class="block text-sm">{{ $asset->title }}</b><span class="mt-1 block text-xs text-slate-500">{{ $youtubeId ? 'YouTube' : $asset->type }} · {{ $asset->status }} · №{{ $asset->id }}</span></span>
        </label>
    @empty
        <p class="rounded-xl bg-mist-50 p-3 text-sm text-slate-500 sm:col-span-2">В медиатеке пока нет материалов.</p>
    @endforelse
</div>
