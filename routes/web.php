<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BibleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HymnController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\TodoController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/notes', [NoteController::class, 'index'])->name('notes.index');
    Route::post('/notes', [NoteController::class, 'store'])->name('notes.store');
    Route::put('/notes/{note}', [NoteController::class, 'update'])->name('notes.update');
    Route::post('/notes/{note}/unlock', [NoteController::class, 'unlock'])->name('notes.unlock');
    Route::post('/notes/{note}/lock', [NoteController::class, 'lock'])->name('notes.lock');
    Route::delete('/notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');

    Route::get('/todos', [TodoController::class, 'index'])->name('todos.index');
    Route::post('/todos', [TodoController::class, 'store'])->name('todos.store');
    Route::patch('/todos/{todo}/toggle', [TodoController::class, 'toggle'])->name('todos.toggle');
    Route::delete('/todos/{todo}', [TodoController::class, 'destroy'])->name('todos.destroy');

    Route::get('/calendar', [EventController::class, 'index'])->name('events.index');
    Route::post('/calendar', [EventController::class, 'store'])->name('events.store');
    Route::delete('/calendar/{event}', [EventController::class, 'destroy'])->name('events.destroy');

    Route::get('/hymns', [HymnController::class, 'index'])->name('hymns.index');
    Route::post('/hymns', [HymnController::class, 'store'])->name('hymns.store');
    Route::delete('/hymns/{hymn}', [HymnController::class, 'destroy'])->name('hymns.destroy');

    Route::get('/bible', [BibleController::class, 'index'])->name('bible.index');
});
