<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BibleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HymnController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationMemberController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TodoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LanguageController::class, 'landing'])->name('landing');
Route::get('/language', [LanguageController::class, 'show'])->name('language.select');
Route::post('/language', [LanguageController::class, 'update'])
    ->middleware('throttle:10,1')
    ->name('language.update');

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

    // Password Reset Routes
    Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])
        ->middleware('throttle:6,1')
        ->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Email Verification Routes
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [AuthController::class, 'verifyNotice'])
        ->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('/email/verification-notification', [AuthController::class, 'verifyResend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    
    Route::get('/notes', [NoteController::class, 'index'])->name('notes.index');
    Route::post('/notes', [NoteController::class, 'store'])->name('notes.store');
    Route::put('/notes/{note}', [NoteController::class, 'update'])->name('notes.update');
    Route::post('/notes/{note}/unlock', [NoteController::class, 'unlock'])->name('notes.unlock');
    Route::post('/notes/{note}/lock', [NoteController::class, 'lock'])->name('notes.lock');
    Route::delete('/notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');
    
    // Note Passcode Recovery
    Route::get('/notes/{note}/recover-passcode', [NoteController::class, 'showRecoveryForm'])->name('notes.recover-passcode');
    Route::post('/notes/{note}/submit-recovery', [NoteController::class, 'submitRecoveryAnswer'])
        ->middleware('throttle:6,1')
        ->name('notes.submit-recovery');
    Route::get('/notes/{note}/reset-passcode', [NoteController::class, 'showResetPasscodeForm'])->name('notes.reset-passcode');
    Route::post('/notes/{note}/reset-passcode', [NoteController::class, 'resetPasscode'])
        ->middleware('throttle:6,1')
        ->name('notes.reset-passcode.update');

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
    Route::get('/bible/search', [BibleController::class, 'search'])->name('bible.search');
    Route::get('/bible/bookmarks', [BibleController::class, 'bookmarks'])->name('bible.bookmarks');
    Route::get('/bible/reading-history', [BibleController::class, 'readingHistory'])->name('bible.reading-history');
    Route::post('/bible/bookmark', [BibleController::class, 'bookmark'])->name('bible.bookmark');
    Route::post('/bible/bookmark/remove', [BibleController::class, 'removeBookmark'])->name('bible.removeBookmark');
    Route::post('/bible/highlight', [BibleController::class, 'highlight'])->name('bible.highlight');
    Route::post('/bible/highlight/remove', [BibleController::class, 'removeHighlight'])->name('bible.removeHighlight');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::get('/organizations/create', [OrganizationController::class, 'create'])->name('organizations.create');
    Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');
    Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])->name('organizations.show');
    Route::get('/organizations/{organization}/edit', [OrganizationController::class, 'edit'])->name('organizations.edit');
    Route::put('/organizations/{organization}', [OrganizationController::class, 'update'])->name('organizations.update');
    Route::delete('/organizations/{organization}', [OrganizationController::class, 'destroy'])->name('organizations.destroy');

    Route::get('/organizations/{organization}/members', [OrganizationMemberController::class, 'index'])->name('organizations.members.index');
    Route::get('/organizations/{organization}/members/add', [OrganizationMemberController::class, 'create'])
        ->middleware('throttle:30,1')
        ->name('organizations.members.create');
    Route::post('/organizations/{organization}/members', [OrganizationMemberController::class, 'store'])->name('organizations.members.store');
    Route::put('/organizations/{organization}/members/{member}', [OrganizationMemberController::class, 'update'])->name('organizations.members.update');
    Route::delete('/organizations/{organization}/members/{member}', [OrganizationMemberController::class, 'destroy'])->name('organizations.members.destroy');

    Route::get('/organizations/{organization}/departments', [DepartmentController::class, 'index'])->name('organizations.departments.index');
    Route::post('/organizations/{organization}/departments', [DepartmentController::class, 'store'])->name('organizations.departments.store');
    Route::put('/organizations/{organization}/departments/{department}', [DepartmentController::class, 'update'])->name('organizations.departments.update');
    Route::delete('/organizations/{organization}/departments/{department}', [DepartmentController::class, 'destroy'])->name('organizations.departments.destroy');
});
