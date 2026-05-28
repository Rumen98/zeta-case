<?php

use App\Http\Controllers\Api\IncomingEmailController;
use App\Http\Controllers\Api\TaskDraftController;
use Illuminate\Support\Facades\Route;

Route::post('/incoming-emails', [IncomingEmailController::class, 'store']);

Route::get('/task-drafts/{taskDraft}',           [TaskDraftController::class, 'show']);
Route::post('/task-drafts/{taskDraft}/approve',  [TaskDraftController::class, 'approve']);
Route::post('/task-drafts/{taskDraft}/reject',   [TaskDraftController::class, 'reject']);
Route::post('/task-drafts/{taskDraft}/override', [TaskDraftController::class, 'override']);
