<?php

namespace App\Models\Concerns;

trait HasLocalizedContent
{
    public function localized(string $field, ?string $locale = null): mixed
    {
        $locale ??= app()->getLocale();
        if (str_ends_with($field, '_ru')) {
            $base = substr($field, 0, -3);

            return $locale === 'ru'
                ? $this->getAttribute($field)
                : (filled($this->getAttribute($base.'_'.$locale)) ? $this->getAttribute($base.'_'.$locale) : $this->getAttribute($field));
        }
        if ($locale === 'ru') {
            return $this->getAttribute($field);
        }

        $translation = $this->getAttribute($field.'_'.$locale);

        return filled($translation) ? $translation : $this->getAttribute($field);
    }
}
