<?php

namespace App\Support;

use App\Models\Publication;

class PublicUrl
{
    public static function home(?string $locale = null): string
    {
        return route(match ($locale ?? app()->getLocale()) {
            'en' => 'public.home.en',
            'de' => 'public.home.de',
            default => 'public.home',
        });
    }

    public static function publication(Publication $publication, ?string $locale = null): string
    {
        return route(match ($locale ?? app()->getLocale()) {
            'en' => 'public.publications.show.en',
            'de' => 'public.publications.show.de',
            default => 'public.publications.show',
        }, $publication);
    }
}
