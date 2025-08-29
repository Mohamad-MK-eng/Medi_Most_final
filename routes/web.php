<?php

use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Login route (keep this for auth redirects)
Route::get('/login', function () {
    return response()->json(['message' => 'Please login first'], 401);
})->name('login');

// Email Verification Routes - SIMPLIFIED
Route::get('/email/verify', function () {
    return view('auth.verify-email');
})->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])
    ->name('verification.verify');

Route::post('/email/resend', function (Request $request) {
    if ($request->user()) {
        $request->user()->sendEmailVerificationNotification();
        return back()->with('status', 'Verification link sent!');
    }
    return back()->with('error', 'Please log in to resend verification email.');
})->name('verification.send');

// Verified success page
Route::get('/email/verified', function () {
    return view('auth.verified');
})->name('verification.verified');

// Test route
Route::get('/test', function () {
    return 'Test route is working!';
});







// Password Reset Web Routes
Route::get('/forgot-password', function () {
    return view('auth.forgot-password');
})->name('password.request');

Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkWeb'])->name('password.email');

Route::get('/reset-password/{token}', function ($token) {
    return view('auth.reset-password', ['token' => $token]);
})->name('password.reset');

Route::post('/reset-password', [PasswordResetController::class, 'resetPasswordWeb'])->name('password.update');
