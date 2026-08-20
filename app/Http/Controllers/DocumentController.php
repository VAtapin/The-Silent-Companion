<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentController extends Controller
{
    public function index(): View
    {
        return view('documents.index', ['documents' => Document::with('editor')->orderBy('title')->get()]);
    }

    public function show(Document $document): View
    {
        return view('documents.show', ['document' => $document->load(['versions', 'editor'])]);
    }

    public function update(Request $request, Document $document): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'type' => ['required', 'string', 'max:100'], 'content' => ['nullable', 'string'], 'change_note' => ['nullable', 'string', 'max:1000']]);
        $document->version++;
        $document->fill(collect($data)->except('change_note')->all());
        $document->updated_by = $request->user()->id;
        $document->save();
        DocumentVersion::create(['document_id' => $document->id, 'user_id' => $request->user()->id, 'version' => $document->version, 'content' => $document->content, 'change_note' => $data['change_note'] ?? null]);
        ActivityLogger::write('Изменение документа', $document, "Версия {$document->version}");

        return back()->with('success', 'Новая версия документа сохранена.');
    }

    public function source(Document $document): BinaryFileResponse
    {
        abort_unless($document->source_path, 404);
        $root = realpath(base_path('DOC'));
        $path = realpath(base_path($document->source_path));
        abort_unless($root && $path && str_starts_with(strtolower($path), strtolower($root.DIRECTORY_SEPARATOR)), 404);

        return response()->download($path, $document->source_name);
    }
}
