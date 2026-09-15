<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RagController;

Route::get('/', [RagController::class, 'index'])->name('rag.index');
Route::post('/api/documents/upload', [RagController::class, 'uploadDocument'])->name('rag.upload');
Route::post('/api/rag/ask', [RagController::class, 'askQuestion'])->name('rag.ask');
Route::delete('/api/documents/{id}', [RagController::class, 'deleteDocument'])->name('rag.delete');
