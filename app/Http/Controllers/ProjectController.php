<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectVersion;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function show(): View
    {
        return view('project.show', ['project' => Project::with(['editor', 'versions'])->firstOrFail()]);
    }

    public function edit(): View
    {
        return view('project.edit', ['project' => Project::firstOrFail()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $project = Project::firstOrFail();
        $data = $request->validate([
            'title_ru' => ['required', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'tagline' => ['nullable', 'string'], 'logline' => ['nullable', 'string'], 'synopsis' => ['nullable', 'string'],
            'genre' => ['nullable', 'string', 'max:255'], 'duration' => ['nullable', 'string', 'max:255'],
            'aspect_ratio' => ['nullable', 'string', 'max:30'], 'frame_rate' => ['nullable', 'integer', 'min:1', 'max:240'],
            'resolution' => ['nullable', 'string', 'max:50'], 'language' => ['required', 'string', 'max:100'],
            'visual_principle' => ['nullable', 'string'], 'sound_principle' => ['nullable', 'string'],
            'color_palette' => ['nullable', 'string'], 'camera_rules' => ['nullable', 'string'],
            'production_stage' => ['required', 'string', 'max:100'],
            'firefly_board_url' => ['nullable', 'url', 'max:2048'],
        ]);
        $old = $project->only(array_keys($data));
        $project->fill($data);
        $project->version++;
        $project->updated_by = $request->user()->id;
        $project->save();
        ProjectVersion::create(['project_id' => $project->id, 'version' => $project->version, 'snapshot' => $project->only(array_keys($data)), 'user_id' => $request->user()->id]);
        ActivityLogger::write('Изменение карточки проекта', $project, "Создана версия {$project->version}", $old, $data);

        return redirect()->route('project.show')->with('success', 'Карточка проекта обновлена, версия сохранена.');
    }
}
