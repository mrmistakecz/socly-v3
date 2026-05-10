<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Http\Controllers\WallController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\BlockController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\LiveStreamController;
use Inertia\Inertia;

// Main feed (requires auth)
Route::get('/', [WallController::class, 'index'])->middleware('auth')->name('home');

// Auth routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:5,1');

    // Forgot / reset password
    Route::get('/forgot-password', [ForgotPasswordController::class, 'show'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'show'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->middleware('throttle:5,1')->name('password.update');
});

// Email verification routes + logout (no verified middleware needed)
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [VerifyEmailController::class, 'show'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/resend', [VerifyEmailController::class, 'resend'])->middleware('throttle:3,1')->name('verification.resend');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});

// Protected routes (verified email required)
Route::middleware(['auth', 'verified'])->group(function () {

    // Profile
    Route::get('/profile', [ProfileController::class, 'me'])->name('profile');
    Route::get('/profile/{user}', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Settings
    Route::get('/settings', [ProfileController::class, 'settings'])->name('settings');

    // Posts
    Route::post('/posts', [PostController::class, 'store'])->middleware('throttle:10,1')->name('posts.store');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');

    // Interactions
    Route::post('/posts/{post}/like', [WallController::class, 'like'])->middleware('throttle:30,1')->name('posts.like');
    Route::post('/posts/{post}/bookmark', [WallController::class, 'bookmark'])->middleware('throttle:30,1')->name('posts.bookmark');
    Route::post('/posts/{post}/comment', [WallController::class, 'comment'])->middleware('throttle:10,1')->name('posts.comment');

    // Follow & Subscribe
    Route::post('/users/{user}/follow', [FollowController::class, 'toggle'])->middleware('throttle:30,1')->name('users.follow');
    Route::post('/users/{user}/subscribe', [FollowController::class, 'subscribe'])->middleware('throttle:10,1')->name('users.subscribe');

    // Messages
    Route::get('/messages/{user}', [MessageController::class, 'show'])->middleware('throttle:60,1')->name('messages.show');
    Route::post('/messages', [MessageController::class, 'store'])->middleware('throttle:15,1')->name('messages.store');
    Route::post('/messages/upload', [MessageController::class, 'upload'])->middleware('throttle:10,1')->name('messages.upload');
    Route::post('/messages/voice', [MessageController::class, 'upload'])->middleware('throttle:20,1')->name('messages.voice');
    Route::post('/messages/{user}/read', [MessageController::class, 'markRead'])->middleware('throttle:60,1')->name('messages.read');
    Route::put('/messages/{message}', [MessageController::class, 'update'])->middleware('throttle:30,1')->name('messages.update');
    Route::delete('/messages/{message}', [MessageController::class, 'destroy'])->middleware('throttle:30,1')->name('messages.destroy');
    Route::post('/messages/{message}/react', [MessageController::class, 'addReaction'])->middleware('throttle:30,1')->name('messages.react');
    Route::delete('/messages/{message}/react', [MessageController::class, 'removeReaction'])->middleware('throttle:30,1')->name('messages.unreact');

    // Wallet
    Route::get('/wallet', [WalletController::class, 'index'])->name('wallet');
    Route::post('/wallet/deposit', [WalletController::class, 'createDeposit'])->middleware('throttle:10,1')->name('wallet.deposit');
    Route::post('/wallet/withdraw', [WalletController::class, 'withdraw'])->middleware('throttle:5,1')->name('wallet.withdraw');
    Route::post('/wallet/update', [WalletController::class, 'updateWallet'])->name('wallet.update');
    Route::post('/posts/{post}/unlock', [WalletController::class, 'unlockPost'])->middleware('throttle:20,1')->name('posts.unlock');

    // Bookmarks
    Route::get('/api/bookmarks', [WallController::class, 'bookmarks'])->middleware('throttle:30,1')->name('bookmarks');

    // Feed / Posts API
    Route::get('/api/posts', [WallController::class, 'postsApi'])->middleware('throttle:60,1')->name('posts.api');

    // Discover
    Route::get('/api/discover', [WallController::class, 'discover'])->middleware('throttle:30,1')->name('discover');

    // Search
    Route::get('/api/search', [SearchController::class, 'index'])->middleware('throttle:30,1')->name('search');

    // Stories
    Route::get('/api/stories', [App\Http\Controllers\StoryController::class, 'index'])->middleware('throttle:30,1')->name('stories.index');
    Route::post('/stories', [App\Http\Controllers\StoryController::class, 'store'])->middleware('throttle:10,1')->name('stories.store');
    Route::delete('/stories/{story}', [App\Http\Controllers\StoryController::class, 'destroy'])->middleware('throttle:10,1')->name('stories.destroy');

    // Notifications
    Route::get('/api/notifications', [App\Http\Controllers\NotificationController::class, 'index'])->middleware('throttle:30,1')->name('notifications.index');
    Route::post('/api/notifications/read', [App\Http\Controllers\NotificationController::class, 'markRead'])->middleware('throttle:30,1')->name('notifications.read');
    Route::get('/api/notifications/count', [App\Http\Controllers\NotificationController::class, 'unreadCount'])->middleware('throttle:60,1')->name('notifications.count');

    // Reports
    Route::post('/users/{user}/report', [ReportController::class, 'reportUser'])->middleware('throttle:5,1')->name('users.report');
    Route::post('/posts/{post}/report', [ReportController::class, 'reportPost'])->middleware('throttle:5,1')->name('posts.report');

    // Block
    Route::post('/users/{user}/block', [BlockController::class, 'toggle'])->middleware('throttle:10,1')->name('users.block');

    // Settings — password & email
    Route::put('/settings/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:5,1')->name('settings.password');
    Route::put('/settings/email', [ProfileController::class, 'updateEmail'])->middleware('throttle:5,1')->name('settings.email');
    Route::get('/settings/export', [ProfileController::class, 'exportData'])->middleware('throttle:1,60')->name('settings.export');

    // Tips
    Route::post('/users/{user}/tip', [WalletController::class, 'tip'])->middleware('throttle:20,1')->name('users.tip');

    // Story views
    Route::post('/stories/{story}/view', [App\Http\Controllers\StoryController::class, 'view'])->middleware('throttle:60,1')->name('stories.view');

    // Account deletion
    Route::delete('/account', [AccountController::class, 'destroy'])->middleware('throttle:3,1')->name('account.destroy');

    // LiveKit streaming
    Route::post('/stream/token', [LiveStreamController::class, 'token'])->name('stream.token');
    Route::get('/stream/rooms', [LiveStreamController::class, 'rooms'])->name('stream.rooms');

    // Onboarding
    Route::post('/onboarding/complete', function () {
        auth()->user()->update(['onboarding_completed' => true]);
        return response()->json(['success' => true]);
    })->name('onboarding.complete');
});

// Sitemap (F5.4)
Route::get('/sitemap.xml', function () {
    $users = \App\Models\User::select('username', 'updated_at')->orderByDesc('updated_at')->limit(500)->get();
    $baseUrl = config('app.url');
    $xml = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach (['/terms', '/privacy', '/content-policy', '/login', '/register'] as $path) {
        $xml .= "<url><loc>{$baseUrl}{$path}</loc></url>";
    }
    foreach ($users as $user) {
        $xml .= "<url><loc>{$baseUrl}/@{$user->username}</loc><lastmod>{$user->updated_at->toDateString()}</lastmod></url>";
    }
    $xml .= '</urlset>';
    return response($xml, 200, ['Content-Type' => 'application/xml']);
})->name('sitemap');

// Legal pages — public
Route::get('/terms', fn() => Inertia::render('Legal/Terms'))->name('terms');
Route::get('/privacy', fn() => Inertia::render('Legal/Privacy'))->name('privacy');
Route::get('/content-policy', fn() => Inertia::render('Legal/ContentPolicy'))->name('content-policy');

// NOWPayments webhook — no auth middleware
Route::post('/webhook/nowpayments', [WebhookController::class, 'nowpayments'])->name('webhook.nowpayments');

// Admin routes
Route::middleware(['auth', \App\Http\Middleware\AdminMiddleware::class])->prefix('admin')->group(function () {
    Route::get('/', [App\Http\Controllers\AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::put('/users/{user}', [App\Http\Controllers\AdminController::class, 'updateUser'])->name('admin.users.update');
    Route::post('/users/{user}/ban', [App\Http\Controllers\AdminController::class, 'banUser'])->name('admin.users.ban');
    Route::delete('/users/{user}', [App\Http\Controllers\AdminController::class, 'deleteUser'])->name('admin.users.delete');
    Route::delete('/posts/{post}', [App\Http\Controllers\AdminController::class, 'deletePost'])->name('admin.posts.delete');
    Route::post('/reports/{id}/resolve', [App\Http\Controllers\AdminController::class, 'resolveReport'])->name('admin.reports.resolve');
    Route::post('/reports/{id}/dismiss', [App\Http\Controllers\AdminController::class, 'dismissReport'])->name('admin.reports.dismiss');
});

