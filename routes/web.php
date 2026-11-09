<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\WorkspaceController;
use App\Livewire\Pipeline;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:10,1');
});
Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/', Pipeline::class)->name('pipeline');
    Route::get('/jobs', [WorkspaceController::class, 'jobs'])->name('jobs.index');
    Route::get('/jobs/new', [WorkspaceController::class, 'createJob'])->name('jobs.create');
    Route::post('/jobs', [WorkspaceController::class, 'storeJob'])->name('jobs.store');
    Route::get('/applications/new', [WorkspaceController::class, 'createApplication'])->name('applications.create');
    Route::post('/applications', [WorkspaceController::class, 'storeApplication'])->name('applications.store')->block(10, 10);
    Route::get('/applications/{id}', [WorkspaceController::class, 'application'])->whereNumber('id')->name('applications.show');
    Route::post('/applications/{id}/stage', [WorkflowController::class, 'move'])->whereNumber('id')->name('applications.move')->block(10, 10);
    Route::post('/applications/{id}/interviews', [WorkflowController::class, 'schedule'])->whereNumber('id')->name('interviews.store')->block(10, 10);
    Route::post('/interviews/{id}/cancel', [WorkflowController::class, 'cancel'])->whereNumber('id')->name('interviews.cancel')->block(10, 10);
    Route::get('/activity', [WorkspaceController::class, 'activity'])->name('activity');
});
