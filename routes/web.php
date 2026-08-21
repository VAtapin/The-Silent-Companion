<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AiAssistantController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FilmStructureController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PublicationController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\TeamController;
use App\Models\Asset;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class, 'home'])->name('public.home');
Route::get('/media/{asset}', [PublicController::class, 'media'])->name('public.media');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'active'])->prefix('workspace')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/project', [ProjectController::class, 'show'])->name('project.show');
    Route::get('/project/edit', [ProjectController::class, 'edit'])->name('project.edit');
    Route::put('/project', [ProjectController::class, 'update'])->name('project.update');

    Route::get('/structure', [FilmStructureController::class, 'index'])->name('structure.index');
    Route::post('/structure/acts', [FilmStructureController::class, 'storeAct'])->name('acts.store');
    Route::post('/structure/scenes', [FilmStructureController::class, 'storeScene'])->name('scenes.store');
    Route::post('/structure/shots', [FilmStructureController::class, 'storeShot'])->name('shots.store');
    Route::put('/structure/{type}/{id}', [FilmStructureController::class, 'update'])->name('structure.update');
    Route::post('/structure/{type}/{id}/unlock', [FilmStructureController::class, 'unlock'])->name('structure.unlock');

    Route::get('/checklist', [ChecklistController::class, 'index'])->name('checklist.index');
    Route::post('/checklist/sections', [ChecklistController::class, 'storeSection'])->name('checklist.sections.store');
    Route::post('/checklist/items', [ChecklistController::class, 'storeItem'])->name('checklist.items.store');
    Route::put('/checklist/items/{item}', [ChecklistController::class, 'updateItem'])->name('checklist.items.update');
    Route::post('/checklist/items/{item}/requirements', [ChecklistController::class, 'storeRequirement'])->name('checklist.requirements.store');
    Route::post('/checklist/items/{item}/manual-complete', [ChecklistController::class, 'manualComplete'])->name('checklist.manual');
    Route::post('/checklist/recalculate', [ChecklistController::class, 'recalculate'])->name('checklist.recalculate');

    Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');
    Route::get('/assets/create', [AssetController::class, 'create'])->name('assets.create');
    Route::post('/assets', [AssetController::class, 'store'])->name('assets.store');
    Route::get('/assets/{asset}', [AssetController::class, 'show'])->name('assets.show');
    Route::put('/assets/{asset}/status', [AssetController::class, 'updateStatus'])->name('assets.status');
    Route::get('/assets/{asset}/download', [AssetController::class, 'download'])->name('assets.download');
    Route::get('/assets/{asset}/preview', [AssetController::class, 'preview'])->name('assets.preview');
    Route::get('/assets/{asset}/thumbnail', fn (Asset $asset, AssetController $controller) => $controller->download($asset, true))->name('assets.thumbnail');

    Route::get('/catalog/{type}', [CatalogController::class, 'index'])->name('catalog.index');
    Route::post('/catalog/{type}', [CatalogController::class, 'store'])->name('catalog.store');
    Route::put('/catalog/{type}/{id}', [CatalogController::class, 'update'])->name('catalog.update');

    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::put('/documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
    Route::post('/documents/{document}/versions/{version}/restore', [DocumentController::class, 'restore'])->name('documents.versions.restore');
    Route::get('/documents/{document}/source', [DocumentController::class, 'source'])->name('documents.source');

    Route::get('/team', [TeamController::class, 'index'])->name('team.index');
    Route::post('/team', [TeamController::class, 'store'])->name('team.store');
    Route::put('/team/{user}', [TeamController::class, 'update'])->name('team.update');

    Route::get('/activity', [ActivityLogController::class, 'index'])->name('activity.index');
    Route::post('/activity/{activityLog}/restore', [ActivityLogController::class, 'restore'])->name('activity.restore');

    Route::get('/publications', [PublicationController::class, 'index'])->name('publications.index');
    Route::post('/publications', [PublicationController::class, 'store'])->name('publications.store');
    Route::put('/publications/site', [PublicationController::class, 'updateSite'])->name('publications.site');
    Route::post('/publications/site/poster', [PublicationController::class, 'uploadPoster'])->name('publications.poster');
    Route::put('/publications/donations', [PublicationController::class, 'updateDonation'])->name('publications.donations');
    Route::put('/publications/{publication}', [PublicationController::class, 'update'])->name('publications.update');
    Route::post('/publications/{publication}/visibility', [PublicationController::class, 'visibility'])->name('publications.visibility');

    Route::middleware('throttle:10,1')->group(function () {
        Route::get('/ai', [AiAssistantController::class, 'index'])->name('ai.index');
        Route::post('/ai/text', [AiAssistantController::class, 'text'])->name('ai.text');
        Route::post('/ai/images', [AiAssistantController::class, 'images'])->name('ai.images');
        Route::get('/ai/requests/{aiRequest}', [AiAssistantController::class, 'show'])->name('ai.show');
        Route::post('/ai/requests/{aiRequest}/decision', [AiAssistantController::class, 'decide'])->name('ai.decision');
    });
});
