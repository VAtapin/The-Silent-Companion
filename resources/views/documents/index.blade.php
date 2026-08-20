@extends('layouts.app')
@section('title', 'Документы')
@section('content')
<div><p class="text-sm text-amber-500">Исходники из DOC и редактируемые версии</p><h1>Документы проекта</h1></div><section class="mt-7 grid gap-4 md:grid-cols-2 xl:grid-cols-3">@foreach($documents as $document)<a href="{{ route('documents.show',$document) }}" class="card transition hover:border-amber-400"><span class="badge bg-mist-100">{{ $document->type }}</span><h2 class="mt-3">{{ $document->title }}</h2><p class="mt-2 text-sm text-slate-500">Версия {{ $document->version }} · {{ $document->updated_at->format('d.m.Y H:i') }}</p><p class="mt-1 text-xs text-slate-400">{{ $document->source_name }}</p></a>@endforeach</section>
@endsection
