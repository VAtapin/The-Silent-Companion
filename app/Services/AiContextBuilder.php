<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Document;
use App\Models\Project;
use App\Models\Scene;
use App\Models\Shot;

class AiContextBuilder
{
    public function build(array $selection): string
    {
        $parts = [];
        if (! empty($selection['include_project'])) {
            $p = Project::first();
            if ($p) {
                $parts[] = "КАРТОЧКА ПРОЕКТА\n{$p->title_ru}\n{$p->logline}\n{$p->visual_principle}\n{$p->sound_principle}";
            }
        }
        foreach (Document::whereIn('id', $selection['document_ids'] ?? [])->get() as $d) {
            $parts[] = "ДОКУМЕНТ: {$d->title}\n{$d->content}";
        }
        foreach (Scene::with(['act', 'location', 'characters', 'props'])->whereIn('id', $selection['scene_ids'] ?? [])->get() as $s) {
            $parts[] = "СЦЕНА {$s->code}: {$s->title}\n{$s->action_description}\nМесто: {$s->location?->name}\nПерсонажи: {$s->characters->pluck('name')->join(', ')}\nРеквизит: {$s->props->pluck('name')->join(', ')}\nЗвук: {$s->sounds}";
        }
        foreach (Shot::whereIn('id', $selection['shot_ids'] ?? [])->get() as $s) {
            $parts[] = "КАДР {$s->code}\n{$s->description}\nКамера: {$s->camera_position}; {$s->camera_movement}\nПромпт: {$s->prompt}";
        }
        foreach (Asset::whereIn('id', $selection['asset_ids'] ?? [])->get() as $a) {
            if ($a->text_content) {
                $parts[] = "ТЕКСТОВЫЙ МАТЕРИАЛ: {$a->title}\n{$a->text_content}";
            } elseif (str_starts_with((string) $a->mime_type, 'image/')) {
                $parts[] = "ИЗОБРАЖЕНИЕ: {$a->title}\n{$a->description}";
            }
        }

        return mb_substr(implode("\n\n---\n\n", $parts), 0, 80000);
    }
}
