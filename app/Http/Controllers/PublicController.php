<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\DonationSetting;
use App\Models\Project;
use App\Models\Publication;
use App\Models\PublicSiteSetting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicController extends Controller
{
    public function home(): View
    {
        $project = Project::first();

        return view('public.home', ['project' => $project, 'settings' => PublicSiteSetting::with('poster')->first(), 'donations' => DonationSetting::where('is_visible', true)->first(), 'publications' => Publication::visible()->with('assets')->orderBy('sort_order')->orderByDesc('published_at')->get()]);
    }

    public function sitemap(): Response
    {
        return response()->view('public.sitemap')->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function media(Asset $asset): StreamedResponse
    {
        $publicationVisible = Publication::visible()->whereHas('assets', fn ($q) => $q->whereKey($asset->id))->exists();
        $settingVisible = PublicSiteSetting::where('poster_asset_id', $asset->id)->exists();
        $donationVisible = DonationSetting::where('is_visible', true)->where(fn ($q) => $q->where('image_asset_id', $asset->id)->orWhere('qr_asset_id', $asset->id))->exists();
        abort_unless($publicationVisible || $settingVisible || $donationVisible, 404);
        abort_unless($asset->file_path && Storage::disk($asset->disk)->exists($asset->file_path), 404);

        return Storage::disk($asset->disk)->response($asset->file_path, $asset->original_name, ['Cache-Control' => 'public, max-age=3600']);
    }
}
