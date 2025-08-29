<?php
// app/Http/Controllers/Auth/CodeVerificationController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\VerificationCode;
use App\Models\User;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Verified;

class CodeVerificationController extends Controller
{
    public function showVerificationForm()
    {
        return view('auth.verify-code');
    }

    public function verifyCode(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:4'
        ]);

        $user = Auth::user();


        if (!$user) {
            return redirect()->route('login')->withErrors(['error' => 'Please log in to verify your email.']);
        }

        $verificationCode = VerificationCode::where('user_id', $user->id)
            ->where('code', strtoupper($request->code))
            ->first();

        if (!$verificationCode || $verificationCode->isExpired()) {
            return back()->withErrors(['code' => 'Invalid or expired verification code.']);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        $verificationCode->delete();

        return redirect()->route('verification.verified')
            ->with('success', 'Email verified successfully!');
    }

    public function resendCode()
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login')->withErrors(['error' => 'Please log in to resend verification code.']);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('home');
        }

        $user->sendEmailVerificationNotification();

        return back()->with('success', 'Verification code sent successfully!');
    }
}
