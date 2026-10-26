<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;

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
Route::get('/documents', function () {
    return view('documents.index');
})->middleware(['auth', 'role:user'])->name('documents.index');
Route::get('/admin/documents', function () {
    return view('admin.documents.index');
})->middleware(['auth', 'role:super_admin'])->name('admin.documents.index');
