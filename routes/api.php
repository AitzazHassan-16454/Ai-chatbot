<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JobAlertController;

Route::post('/job-alerts/daily-run', [JobAlertController::class, 'dailyRun']);
Route::post('/job-alerts/search-users', [JobAlertController::class, 'searchUsers']);
Route::post('/job-alerts/send-email', [JobAlertController::class, 'sendEmail']);
