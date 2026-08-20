<?php

namespace App\Http\Controllers;

use App\Models\Act;
use App\Models\Character;
use App\Models\Location;
use App\Models\Project;
use App\Models\Prop;
use App\Models\Scene;
use App\Models\Shot;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FilmStructureController extends Controller
{
    public function index(): View
    {
        return view('structure.index', [
            'project' => Project::with(['acts.scenes.location', 'acts.scenes.shots'])->firstOrFail(),
            'locations' => Location::orderBy('name')->get(),
            'characters' => Character::orderBy('name')->get(),
            'props' => Prop::orderBy('name')->get(),
            'users' => User::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function storeAct(Request $request): RedirectResponse
    {
        $project = Project::firstOrFail();
        $data = $request->validate([
            'number' => ['required', 'integer', 'min:1', Rule::unique('acts')->where('project_id', $project->id)],
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'],
            'planned_duration_seconds' => ['nullable', 'integer', 'min:0'], 'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'string', 'max:80'],
        ]);
        $act = $project->acts()->create($data);
        ActivityLogger::write('Создание акта', $act, $act->title);

        return back()->with('success', 'Акт создан.');
    }

    public function storeScene(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'act_id' => ['required', 'exists:acts,id'], 'code' => ['required', 'string', 'max:50', 'unique:scenes,code'],
            'number' => ['required', 'integer', 'min:1'], 'title' => ['required', 'string', 'max:255'],
            'action_description' => ['nullable', 'string'], 'location_id' => ['nullable', 'exists:locations,id'],
            'time_of_day' => ['nullable', 'string', 'max:100'], 'weather' => ['nullable', 'string', 'max:150'],
            'dialogue' => ['nullable', 'string'], 'sounds' => ['nullable', 'string'],
            'planned_duration_seconds' => ['nullable', 'integer', 'min:0'], 'status' => ['required', 'string', 'max:80'],
            'assignee_id' => ['nullable', 'exists:users,id'], 'character_ids' => ['array'], 'character_ids.*' => ['exists:characters,id'],
            'prop_ids' => ['array'], 'prop_ids.*' => ['exists:props,id'],
        ]);
        $scene = Scene::create(collect($data)->except(['character_ids', 'prop_ids'])->all());
        $scene->characters()->sync($data['character_ids'] ?? []);
        $scene->props()->sync($data['prop_ids'] ?? []);
        ActivityLogger::write('Создание сцены', $scene, "{$scene->code} — {$scene->title}");

        return back()->with('success', 'Сцена создана.');
    }

    public function storeShot(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'scene_id' => ['required', 'exists:scenes,id'], 'code' => ['required', 'string', 'max:50', 'unique:shots,code'],
            'number' => ['required', 'integer', 'min:1'], 'description' => ['required', 'string'],
            'hero_action' => ['nullable', 'string'], 'dog_action' => ['nullable', 'string'], 'shot_size' => ['nullable', 'string', 'max:100'],
            'camera_position' => ['nullable', 'string'], 'camera_movement' => ['nullable', 'string'], 'lens' => ['nullable', 'string'],
            'planned_duration_seconds' => ['nullable', 'integer', 'min:0'], 'sound' => ['nullable', 'string'], 'dialogue' => ['nullable', 'string'],
            'creation_method' => ['nullable', 'string', 'max:120'], 'ai_model' => ['nullable', 'string', 'max:120'],
            'prompt' => ['nullable', 'string'], 'negative_prompt' => ['nullable', 'string'], 'credits_spent' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'string', 'max:80'], 'assignee_id' => ['nullable', 'exists:users,id'],
        ]);
        $shot = Shot::create($data);
        ActivityLogger::write('Создание кадра', $shot, "{$shot->code}");

        return back()->with('success', 'Кадр создан.');
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        $model = $this->model($type, $id);
        abort_if($model->status === 'Финал', 423, 'Объект в статусе «Финал». Сначала снимите защиту.');
        $data = $request->validate(['title' => ['sometimes', 'required', 'string', 'max:255'], 'description' => ['sometimes', 'required', 'string'], 'status' => ['required', 'string', 'max:80']]);
        $old = $model->only(array_keys($data));
        $model->update($data);
        ActivityLogger::write('Изменение производственного объекта', $model, null, $old, $data);

        return back()->with('success', 'Изменения сохранены.');
    }

    public function unlock(string $type, int $id): RedirectResponse
    {
        $model = $this->model($type, $id);
        abort_unless($model->status === 'Финал', 422);
        $model->update(['status' => 'На проверке']);
        ActivityLogger::write('Снятие статуса «Финал»', $model);

        return back()->with('success', 'Защита снята, объект возвращён на проверку.');
    }

    private function model(string $type, int $id): Model
    {
        $class = match ($type) {
            'act' => Act::class, 'scene' => Scene::class, 'shot' => Shot::class, default => abort(404)
        };

        return $class::findOrFail($id);
    }
}
