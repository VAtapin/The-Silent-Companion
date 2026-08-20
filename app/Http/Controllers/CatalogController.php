<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Location;
use App\Models\Prop;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(string $type): View
    {
        [$class, $title] = $this->meta($type);

        return view('catalog.index', ['type' => $type, 'title' => $title, 'records' => $class::orderBy('name')->get()]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        [$class, $title] = $this->meta($type);
        $data = $this->validated($request, $type);
        $record = $class::create($data);
        ActivityLogger::write('Создание: '.$title, $record, $record->name);

        return back()->with('success', 'Запись создана.');
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        [$class, $title] = $this->meta($type);
        $record = $class::findOrFail($id);
        $data = $this->validated($request, $type);
        $record->update($data);
        ActivityLogger::write('Изменение: '.$title, $record, $record->name);

        return back()->with('success', 'Запись обновлена.');
    }

    private function meta(string $type): array
    {
        return match ($type) {
            'characters' => [Character::class, 'Персонажи'], 'locations' => [Location::class, 'Места'], 'props' => [Prop::class, 'Реквизит и одежда'], default => abort(404),
        };
    }

    private function validated(Request $request, string $type): array
    {
        $common = ['name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'status' => ['required', 'string', 'max:100']];
        $extra = match ($type) {
            'characters' => ['role' => ['nullable', 'string'], 'age' => ['nullable', 'string'], 'appearance' => ['nullable', 'string'], 'build' => ['nullable', 'string'], 'clothing' => ['nullable', 'string'], 'voice' => ['nullable', 'string'], 'movement' => ['nullable', 'string'], 'continuity_details' => ['nullable', 'string'], 'forbidden_changes' => ['nullable', 'string']],
            'locations' => ['time_of_day' => ['nullable', 'string'], 'weather' => ['nullable', 'string'], 'lighting' => ['nullable', 'string'], 'color_palette' => ['nullable', 'string'], 'continuity_elements' => ['nullable', 'string'], 'sounds' => ['nullable', 'string']],
            'props' => ['type' => ['required', 'string'], 'color' => ['nullable', 'string'], 'dimensions' => ['nullable', 'string'], 'condition' => ['nullable', 'string'], 'continuity_requirements' => ['nullable', 'string']],
        };

        return $request->validate([...$common, ...$extra]);
    }
}
