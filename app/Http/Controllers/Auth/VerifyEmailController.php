<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    public function showNotice()
    {
        return view('auth.verify-email');
    }

    public function verify(Request $request, $id, $hash)
    {
        $user = User::find($id);

        if (!$user) {
            return redirect('/email/verify')->with('error', 'User not found.');
        }

        if (!hash_equals(sha1($user->email), $hash)) {
            return redirect('/email/verify')->with('error', 'Invalid verification link.');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect('/email/verified')->with('info', 'Email already verified.');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect('/email/verified')->with('success', 'Email verified successfully!');
    }

    public function resend(Request $request)
    {
        if ($request->user()) {
            $request->user()->sendEmailVerificationNotification();
            return back()->with('status', 'Verification link sent!');
        }

        return back()->with('error', 'Please log in to resend verification email.');
    }
}
