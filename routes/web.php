<?php

declare(strict_types=1);

use App\Http\Controllers\SourceController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/sources');

Route::get('/sources', [SourceController::class, 'index'])->name('sources.index');
Route::get('/sources/create', [SourceController::class, 'create'])->name('sources.create');
Route::post('/sources', [SourceController::class, 'store'])->name('sources.store');
Route::patch('/sources/{source}/toggle', [SourceController::class, 'toggle'])->name('sources.toggle');
Route::delete('/sources/{source}', [SourceController::class, 'destroy'])->name('sources.destroy');
