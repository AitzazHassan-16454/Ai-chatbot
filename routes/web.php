<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\JobAlertController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SttController;
use App\Http\Controllers\TtsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Chat');
})->name('chat');

Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => Inertia::render('Login'))->name('login');
    Route::get('/register', fn () => Inertia::render('Register'))->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.post');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/api/user', [AuthController::class, 'user']);

Route::middleware('throttle:chat')->group(function () {
    Route::post('/chat', [ChatController::class, 'ask'])->name('chat.ask');
    Route::post('/chat/stream', [ChatController::class, 'askStreaming'])->name('chat.ask.stream');
});

Route::middleware('auth')->group(function () {
    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::delete('/conversations/{conversation}', [ConversationController::class, 'destroy'])->name('conversations.destroy');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'updateProfile'])->name('profile.update');
    Route::get('/profile/avatar', [ProfileController::class, 'avatar'])->name('profile.avatar');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.upload');
    Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar'])->name('profile.avatar.remove');
    Route::post('/profile/cv', [ProfileController::class, 'uploadCv'])->name('profile.cv.upload');
    Route::delete('/profile/cv', [ProfileController::class, 'removeCv'])->name('profile.cv.remove');
    Route::put('/profile/job-preferences', [ProfileController::class, 'updatePreferences'])->name('profile.preferences');

    Route::get('/job-alerts', [JobAlertController::class, 'index'])->name('job-alerts.index');
    Route::post('/job-alerts/search', [JobAlertController::class, 'search'])->name('job-alerts.search');
});

Route::middleware('throttle:feedback')->group(function () {
    Route::post('/feedback', [FeedbackController::class, 'store'])->name('feedback.store');
});

Route::middleware('throttle:tts')->group(function () {
    Route::post('/tts', [TtsController::class, 'synthesize'])->name('tts.synthesize');
});

Route::middleware('throttle:stt')->group(function () {
    Route::post('/stt', [SttController::class, 'transcribe'])->name('stt.transcribe');
});
