<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BibleController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventRsvpController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\GivingCampaignController;
use App\Http\Controllers\OrganizationEventController;
use App\Http\Controllers\HymnController;
use App\Http\Controllers\JobApplicationController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationDirectoryController;
use App\Http\Controllers\AudioCollectionController;
use App\Http\Controllers\AudioResourceController;
use App\Http\Controllers\OrganizationMemberController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TodoController;
use App\Http\Controllers\UserConnectionController;
use App\Http\Controllers\UserProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LanguageController::class, 'landing'])->name('landing');
Route::get('/language', [LanguageController::class, 'show'])->name('language.select');
Route::post('/language', [LanguageController::class, 'update'])
    ->middleware('throttle:10,1')
    ->name('language.update');

Route::get('/files/{media}', [FileController::class, 'show'])->name('files.show');

// Public library browsing — guests may view published, publicly-visible
// resources, matching how public organizations/logos are already browsable.
// The specific /resources/saved and /resources/create paths are registered
// (further below, inside the auth group) before the /resources/{resource}
// wildcard here would otherwise swallow them — Laravel matches routes in
// registration order regardless of which middleware group they sit in.
Route::get('/resources', [ResourceController::class, 'index'])->name('resources.index');

// Same guest-browsable-but-filtered pattern as the resource library.
Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');

// Unified search — guest-accessible for public content across every
// provider; people-search itself returns nothing for guests (see
// UserSearchProvider), so no separate auth branch is needed here.
Route::get('/search', [SearchController::class, 'index'])->name('search.index');

// Public organization directory — deliberately a separate prefix from
// /organizations (that index is each user's own membership list; this one is
// the public discovery surface over the same Organization model/policies).
// Public user profiles — username-based, reusing the existing Phase 9
// identity (UserProfile.username), gated by the Phase 21 `discoverable`
// privacy preference (see PreferenceService::canViewPublicProfile()).
Route::get('/users/{username}', [UserProfileController::class, 'show'])->name('users.show');
Route::get('/users/{username}/followers', [UserConnectionController::class, 'followers'])->name('users.followers');
Route::get('/users/{username}/following', [UserConnectionController::class, 'following'])->name('users.following');

Route::get('/directory', [OrganizationDirectoryController::class, 'index'])->name('directory.index');
Route::get('/directory/{organization}', [OrganizationDirectoryController::class, 'show'])->name('directory.show');

// Giving — public browsing/checkout surfaces (a guest may view a published
// public campaign and give without an account), management stays in the auth
// group further below.
Route::get('/organizations/{organization}/giving', [GivingCampaignController::class, 'index'])->name('giving.index');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/organizations/{organization}/giving/create', [GivingCampaignController::class, 'create'])->name('giving.create');
});

Route::get('/organizations/{organization}/giving/{campaign}', [GivingCampaignController::class, 'show'])->name('giving.show');
Route::post('/organizations/{organization}/giving/{campaign}/donate', [DonationController::class, 'store'])->middleware('throttle:10,1')->name('giving.donate');
Route::get('/giving/checkout/{reference}', [DonationController::class, 'checkout'])->name('giving.checkout.show');
Route::post('/giving/checkout/{reference}/simulate', [DonationController::class, 'simulate'])->name('giving.checkout.simulate');
Route::get('/giving/result/{reference}', [DonationController::class, 'result'])->name('giving.result');

// Provider webhook — publicly reachable by design (the provider's servers
// call it directly), authenticated by HMAC signature inside the adapter, not
// by a Laravel session/CSRF token (excluded in bootstrap/app.php).
Route::post('/webhooks/payments/{provider}', [PaymentWebhookController::class, 'handle'])->name('payments.webhook');

// Organization-owned events — entirely separate from the existing personal
// Events feature at /calendar (route name 'events.index'), hence the
// 'org-events' route-name prefix even though the URL itself is /events.
Route::get('/events', [OrganizationEventController::class, 'discover'])->name('org-events.discover');
Route::get('/events/{event}', [OrganizationEventController::class, 'show'])->name('org-events.show');
Route::get('/organizations/{organization}/events', [OrganizationEventController::class, 'index'])->name('org-events.index');

// Audio/Media library — same guest-browsable pattern as the resource library.
// The "My Audio" / "Organization Audio" tabs are a `scope` query param on this
// same index rather than separate routes, since they share identical
// search/filter UI and only differ in the base query.
Route::get('/audio', [AudioResourceController::class, 'index'])->name('audio.index');
Route::get('/audio-collections', [AudioCollectionController::class, 'index'])->name('audio.collections.index');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/resources/saved', [ResourceController::class, 'saved'])->name('resources.saved');
    Route::get('/resources/create', [ResourceController::class, 'create'])->name('resources.create');
    Route::get('/organizations/{organization}/resources/create', [ResourceController::class, 'create'])->name('organizations.resources.create');

    Route::get('/jobs/mine', [JobController::class, 'mine'])->name('jobs.mine');
    Route::get('/jobs/create', [JobController::class, 'create'])->name('jobs.create');
    Route::get('/organizations/{organization}/jobs/create', [JobController::class, 'create'])->name('organizations.jobs.create');
    Route::get('/job-applications/mine', [JobApplicationController::class, 'mine'])->name('jobs.applications.mine');

    Route::get('/audio/create', [AudioResourceController::class, 'create'])->name('audio.create');
    Route::get('/organizations/{organization}/audio/create', [AudioResourceController::class, 'create'])->name('organizations.audio.create');
    Route::get('/audio-collections/create', [AudioCollectionController::class, 'create'])->name('audio.collections.create');
    Route::get('/organizations/{organization}/audio-collections/create', [AudioCollectionController::class, 'create'])->name('organizations.audio-collections.create');
});

Route::get('/jobs/{job}', [JobController::class, 'show'])->name('jobs.show');

Route::get('/resources/{resource}', [ResourceController::class, 'show'])->name('resources.show');

Route::get('/audio/{audio}', [AudioResourceController::class, 'show'])->name('audio.show');
Route::get('/audio-collections/{collection}', [AudioCollectionController::class, 'show'])->name('audio.collections.show');

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
    Route::post('/profile/photo', [ProfileController::class, 'storePhoto'])->name('profile.photo.store');
    Route::delete('/profile/photo', [ProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');

    Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::get('/organizations/create', [OrganizationController::class, 'create'])->name('organizations.create');
    Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');
    Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])->name('organizations.show');
    Route::get('/organizations/{organization}/edit', [OrganizationController::class, 'edit'])->name('organizations.edit');
    Route::post('/organizations/{organization}/logo', [OrganizationController::class, 'storeLogo'])->name('organizations.logo.store');
    Route::delete('/organizations/{organization}/logo', [OrganizationController::class, 'destroyLogo'])->name('organizations.logo.destroy');
    Route::put('/organizations/{organization}', [OrganizationController::class, 'update'])->name('organizations.update');
    Route::delete('/organizations/{organization}', [OrganizationController::class, 'destroy'])->name('organizations.destroy');

    Route::get('/organizations/{organization}/members', [OrganizationMemberController::class, 'index'])->name('organizations.members.index');
    Route::get('/organizations/{organization}/members/add', [OrganizationMemberController::class, 'create'])
        ->middleware('throttle:30,1')
        ->name('organizations.members.create');
    Route::post('/organizations/{organization}/members', [OrganizationMemberController::class, 'store'])->name('organizations.members.store');
    Route::put('/organizations/{organization}/members/{member}', [OrganizationMemberController::class, 'update'])->name('organizations.members.update');
    Route::delete('/organizations/{organization}/members/{member}', [OrganizationMemberController::class, 'destroy'])->name('organizations.members.destroy');

    Route::delete('/files/{media}', [FileController::class, 'destroy'])->name('files.destroy');

    Route::get('/organizations/{organization}/departments', [DepartmentController::class, 'index'])->name('organizations.departments.index');
    Route::post('/organizations/{organization}/departments', [DepartmentController::class, 'store'])->name('organizations.departments.store');
    Route::put('/organizations/{organization}/departments/{department}', [DepartmentController::class, 'update'])->name('organizations.departments.update');
    Route::delete('/organizations/{organization}/departments/{department}', [DepartmentController::class, 'destroy'])->name('organizations.departments.destroy');

    Route::post('/resources', [ResourceController::class, 'store'])->name('resources.store');
    Route::post('/organizations/{organization}/resources', [ResourceController::class, 'store'])->name('organizations.resources.store');
    Route::get('/resources/{resource}/edit', [ResourceController::class, 'edit'])->name('resources.edit');
    Route::put('/resources/{resource}', [ResourceController::class, 'update'])->name('resources.update');
    Route::delete('/resources/{resource}', [ResourceController::class, 'destroy'])->name('resources.destroy');
    Route::post('/resources/{resource}/publish', [ResourceController::class, 'publish'])->name('resources.publish');
    Route::post('/resources/{resource}/archive', [ResourceController::class, 'archive'])->name('resources.archive');
    Route::post('/resources/{resource}/cover', [ResourceController::class, 'storeCover'])->middleware('throttle:20,1')->name('resources.cover.store');
    Route::post('/resources/{resource}/file', [ResourceController::class, 'storeFile'])->middleware('throttle:20,1')->name('resources.file.store');
    Route::post('/resources/{resource}/save', [ResourceController::class, 'save'])->name('resources.save');
    Route::delete('/resources/{resource}/save', [ResourceController::class, 'unsave'])->name('resources.unsave');

    // Messaging is entirely authenticated — unlike the resource library there is
    // no guest-visible browsing surface here at all.
    Route::get('/messages', [ConversationController::class, 'index'])->name('messages.index');
    Route::get('/messages/search', [ConversationController::class, 'search'])->name('messages.search');
    Route::post('/messages/start', [ConversationController::class, 'start'])->name('messages.start');
    Route::get('/messages/{conversation}', [ConversationController::class, 'show'])->name('messages.show');
    Route::post('/messages/{conversation}/read', [ConversationController::class, 'markRead'])->name('messages.read');
    Route::post('/messages/{conversation}/messages', [MessageController::class, 'store'])->name('messages.messages.store');
    Route::put('/messages/{conversation}/messages/{message}', [MessageController::class, 'update'])->name('messages.messages.update');
    Route::delete('/messages/{conversation}/messages/{message}', [MessageController::class, 'destroy'])->name('messages.messages.destroy');

    Route::post('/jobs', [JobController::class, 'store'])->name('jobs.store');
    Route::post('/organizations/{organization}/jobs', [JobController::class, 'store'])->name('organizations.jobs.store');
    Route::get('/jobs/{job}/edit', [JobController::class, 'edit'])->name('jobs.edit');
    Route::put('/jobs/{job}', [JobController::class, 'update'])->name('jobs.update');
    Route::delete('/jobs/{job}', [JobController::class, 'destroy'])->name('jobs.destroy');
    Route::post('/jobs/{job}/publish', [JobController::class, 'publish'])->name('jobs.publish');
    Route::post('/jobs/{job}/close', [JobController::class, 'close'])->name('jobs.close');
    Route::post('/jobs/{job}/cancel', [JobController::class, 'cancel'])->name('jobs.cancel');
    Route::post('/jobs/{job}/attachment', [JobController::class, 'storeAttachment'])->middleware('throttle:20,1')->name('jobs.attachment.store');

    Route::get('/jobs/{job}/applications', [JobApplicationController::class, 'index'])->name('jobs.applications.index');
    Route::post('/jobs/{job}/applications', [JobApplicationController::class, 'store'])->middleware('throttle:20,1')->name('jobs.applications.store');
    Route::get('/jobs/{job}/applications/{application}', [JobApplicationController::class, 'show'])->name('jobs.applications.show');
    Route::post('/jobs/{job}/applications/{application}/withdraw', [JobApplicationController::class, 'withdraw'])->name('jobs.applications.withdraw');
    Route::post('/jobs/{job}/applications/{application}/accept', [JobApplicationController::class, 'accept'])->name('jobs.applications.accept');
    Route::post('/jobs/{job}/applications/{application}/reject', [JobApplicationController::class, 'reject'])->name('jobs.applications.reject');

    Route::post('/organizations/{organization}/giving', [GivingCampaignController::class, 'store'])->name('giving.store');
    Route::get('/organizations/{organization}/giving/{campaign}/edit', [GivingCampaignController::class, 'edit'])->name('giving.edit');
    Route::put('/organizations/{organization}/giving/{campaign}', [GivingCampaignController::class, 'update'])->name('giving.update');
    Route::post('/organizations/{organization}/giving/{campaign}/publish', [GivingCampaignController::class, 'publish'])->name('giving.publish');
    Route::post('/organizations/{organization}/giving/{campaign}/close', [GivingCampaignController::class, 'close'])->name('giving.close');
    Route::post('/organizations/{organization}/giving/{campaign}/cancel', [GivingCampaignController::class, 'cancel'])->name('giving.cancel');
    Route::get('/organizations/{organization}/giving/{campaign}/history', [GivingCampaignController::class, 'history'])->name('giving.history');

    Route::get('/giving/mine', [DonationController::class, 'mine'])->name('giving.mine');
    Route::post('/donations/{donation}/retry', [DonationController::class, 'retry'])->name('giving.donations.retry');

    Route::get('/organizations/{organization}/events/create', [OrganizationEventController::class, 'create'])->name('org-events.create');
    Route::post('/organizations/{organization}/events', [OrganizationEventController::class, 'store'])->name('org-events.store');
    Route::get('/events/{event}/edit', [OrganizationEventController::class, 'edit'])->name('org-events.edit');
    Route::put('/events/{event}', [OrganizationEventController::class, 'update'])->name('org-events.update');
    Route::delete('/events/{event}', [OrganizationEventController::class, 'destroy'])->name('org-events.destroy');
    Route::post('/events/{event}/publish', [OrganizationEventController::class, 'publish'])->name('org-events.publish');
    Route::post('/events/{event}/cancel', [OrganizationEventController::class, 'cancel'])->name('org-events.cancel');
    Route::post('/events/{event}/complete', [OrganizationEventController::class, 'complete'])->name('org-events.complete');
    Route::post('/events/{event}/cover', [OrganizationEventController::class, 'storeCover'])->middleware('throttle:20,1')->name('org-events.cover.store');
    Route::delete('/events/{event}/cover', [OrganizationEventController::class, 'destroyCover'])->name('org-events.cover.destroy');
    Route::get('/events/{event}/attendance', [OrganizationEventController::class, 'attendance'])->name('org-events.attendance');
    Route::post('/events/{event}/rsvp', [EventRsvpController::class, 'store'])->name('org-events.rsvp.store');
    Route::delete('/events/{event}/rsvp', [EventRsvpController::class, 'destroy'])->name('org-events.rsvp.destroy');

    Route::post('/audio', [AudioResourceController::class, 'store'])->name('audio.store');
    Route::post('/organizations/{organization}/audio', [AudioResourceController::class, 'store'])->name('organizations.audio.store');
    Route::get('/audio/{audio}/edit', [AudioResourceController::class, 'edit'])->name('audio.edit');
    Route::put('/audio/{audio}', [AudioResourceController::class, 'update'])->name('audio.update');
    Route::delete('/audio/{audio}', [AudioResourceController::class, 'destroy'])->name('audio.destroy');
    Route::post('/audio/{audio}/publish', [AudioResourceController::class, 'publish'])->name('audio.publish');
    Route::post('/audio/{audio}/archive', [AudioResourceController::class, 'archive'])->name('audio.archive');
    Route::post('/audio/{audio}/file', [AudioResourceController::class, 'storeAudioFile'])->middleware('throttle:20,1')->name('audio.file.store');
    Route::post('/audio/{audio}/cover', [AudioResourceController::class, 'storeCover'])->middleware('throttle:20,1')->name('audio.cover.store');
    Route::delete('/audio/{audio}/cover', [AudioResourceController::class, 'destroyCover'])->name('audio.cover.destroy');

    Route::post('/audio-collections', [AudioCollectionController::class, 'store'])->name('audio.collections.store');
    Route::post('/organizations/{organization}/audio-collections', [AudioCollectionController::class, 'store'])->name('organizations.audio-collections.store');
    Route::get('/audio-collections/{collection}/edit', [AudioCollectionController::class, 'edit'])->name('audio.collections.edit');
    Route::put('/audio-collections/{collection}', [AudioCollectionController::class, 'update'])->name('audio.collections.update');
    Route::delete('/audio-collections/{collection}', [AudioCollectionController::class, 'destroy'])->name('audio.collections.destroy');
    Route::post('/audio-collections/{collection}/publish', [AudioCollectionController::class, 'publish'])->name('audio.collections.publish');
    Route::post('/audio-collections/{collection}/archive', [AudioCollectionController::class, 'archive'])->name('audio.collections.archive');
    Route::post('/audio-collections/{collection}/items', [AudioCollectionController::class, 'addItem'])->name('audio.collections.items.store');
    Route::delete('/audio-collections/{collection}/items/{audio}', [AudioCollectionController::class, 'removeItem'])->name('audio.collections.items.destroy');
    Route::put('/audio-collections/{collection}/reorder', [AudioCollectionController::class, 'reorder'])->name('audio.collections.reorder');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings/account', [SettingsController::class, 'updateAccount'])->name('settings.account.update');
    Route::put('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password.update');
    Route::put('/settings/security-question', [SettingsController::class, 'updateSecurityQuestion'])->name('settings.security-question.update');
    Route::put('/settings/notifications', [SettingsController::class, 'updateNotifications'])->name('settings.notifications.update');
    Route::put('/settings/privacy', [SettingsController::class, 'updatePrivacy'])->name('settings.privacy.update');
    Route::put('/settings/appearance', [SettingsController::class, 'updateAppearance'])->name('settings.appearance.update');
    Route::put('/settings/bible', [SettingsController::class, 'updateBible'])->name('settings.bible.update');
    Route::put('/settings/audio', [SettingsController::class, 'updateAudio'])->name('settings.audio.update');
    Route::put('/settings/dashboard', [SettingsController::class, 'updateDashboard'])->name('settings.dashboard.update');

    // Relationship-changing endpoints — throttled against follow/block spam,
    // same mechanism (Laravel's built-in rate limiter) already used for
    // auth and member-search endpoints elsewhere in this app.
    Route::middleware('throttle:30,1')->group(function () {
        Route::post('/users/{username}/follow', [UserConnectionController::class, 'follow'])->name('users.follow');
        Route::delete('/users/{username}/follow', [UserConnectionController::class, 'unfollow'])->name('users.unfollow');
        Route::post('/users/{username}/block', [UserConnectionController::class, 'block'])->name('users.block');
        Route::delete('/users/{username}/block', [UserConnectionController::class, 'unblock'])->name('users.unblock');
    });

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // Report submission — throttled against flooding, same mechanism as the
    // relationship-changing endpoints above.
    Route::middleware('throttle:10,1')->post('/report', [ReportController::class, 'store'])->name('report.store');
});

// Platform moderation — a distinct authority from organization administration
// (see EnsureIsModerator / users.is_moderator). No organization role, no
// route parameter, grants access here.
Route::middleware(['auth', 'verified', 'moderator'])->prefix('admin/moderation')->name('admin.moderation.')->group(function () {
    Route::get('/', [ModerationController::class, 'index'])->name('index');
    Route::get('/reports/{report}', [ModerationController::class, 'show'])->name('reports.show');
    Route::post('/reports/{report}/assign', [ModerationController::class, 'assign'])->name('reports.assign');
    Route::post('/reports/{report}/status', [ModerationController::class, 'updateStatus'])->name('reports.status');
    Route::post('/reports/{report}/notes', [ModerationController::class, 'addNote'])->name('reports.notes.store');
    Route::post('/users/{user}/warn', [ModerationController::class, 'warnUser'])->name('users.warn');
    Route::post('/users/{user}/status', [ModerationController::class, 'updateUserStatus'])->name('users.status');
    Route::post('/content/hide', [ModerationController::class, 'hideContent'])->name('content.hide');
});
