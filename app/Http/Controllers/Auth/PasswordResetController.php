<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VerificationCode;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class PasswordResetController extends Controller
{
    public function sendResetCode(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found with this email address'], 404);
        }

        $code = VerificationCode::generateCode(4);

        VerificationCode::where('user_id', $user->id)
            ->delete();

        VerificationCode::create([
            'user_id' => $user->id,
            'code' => $code,
            'expires_at' => Carbon::now()->addMinutes(15),
        ]);

        $user->notify(new ResetPasswordNotification($code));

        return response()->json([
            'message' => 'Verification code sent to your email',
            'expires_in' => 15
        ]);
    }



    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|digits:4',
            'password' => 'required|min:8',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $verificationCode = VerificationCode::where('user_id', $user->id)
            ->where('code', $request->code)
            ->first();

        if (!$verificationCode) {
            return response()->json(['error' => 'Invalid verification code'], 400);
        }

        if ($verificationCode->isExpired()) {
            $verificationCode->delete();
            return response()->json(['error' => 'Verification code has expired'], 400);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        $verificationCode->delete();

        return response()->json(['message' => 'Password reset successfully']);
    }
}
