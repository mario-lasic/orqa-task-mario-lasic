<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    if ($request->user()->hasRole('super_admin')) {
        return redirect()->route('admin.documents.index');
    } else {
        return redirect()->route('documents.index');
    }
})->middleware('auth')->name('home');
Route::get('/login', [AuthController::class, 'showLoginForm'])
    ->middleware('guest')
    ->name('login');
Route::post('/login', [AuthController::class, 'login'])
    ->middleware(['guest', 'throttle:5,1']);
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::get('/admin/documents', function () {
    return view('admin.documents.index');
})->middleware(['auth', 'role:super_admin'])->name('admin.documents.index');

Route::get('/documents', [DocumentController::class, 'index'])
    ->middleware(['auth', 'role:user'])
    ->name('documents.index');

Route::post('/documents', [DocumentController::class, 'store'])
    ->middleware(['auth', 'role:user'])
    ->name('documents.store');

Route::get('/documents/{document}/download', [DocumentController::class, 'download'])
    ->middleware('auth')
    ->name('documents.download');

Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])
    ->middleware('auth')
    ->name('documents.destroy');

Route::get('/profile', [ProfileController::class, 'edit'])
    ->middleware(['auth', 'role:user'])
    ->name('profile.edit');

Route::patch('/profile', [ProfileController::class, 'update'])
    ->middleware(['auth', 'role:user'])
    ->name('profile.update');
