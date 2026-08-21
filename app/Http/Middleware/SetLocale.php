<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const SUPPORTED = ['ru', 'en', 'de'];

    public function handle(Request $request, Closure $next, ?string $locale = null): Response
    {
        $locale ??= $request->session()->get('locale', 'ru');
        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = 'ru';
        }

        App::setLocale($locale);
        if ($request->hasSession() && func_num_args() >= 3) {
            $request->session()->put('locale', $locale);
        }

        return $next($request);
    }
}
