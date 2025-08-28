<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckAbsentAppointments
{
    public function handle(Request $request, Closure $next)
    {
        $patient = Auth::user()->patient;

        if ($patient) {
            $absentCount = $patient->appointments()
                ->where('status', 'absent')
                ->count();

            if ($absentCount >= config('app.absent_appointment_threshold', 3)) {
                return response()->json([
                    'error' => 'account_blocked',
                    'message' => 'Your account has been blocked due to multiple missed appointments. Please contact the clinic center.'
                ], 403);
            }
        }

        return $next($request);
    }
}
