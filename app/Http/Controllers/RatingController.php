<?php
// app/Http/Controllers/RatingController.php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Appointment;
use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RatingController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'appointment_id' => 'required|exists:appointments,id',
            'rating' => 'required|numeric|between:1,5',
            'comment' => 'nullable|string|max:500'
        ]);

        $appointment = Appointment::findOrFail($request->appointment_id);

        $user = Auth::user();
        $patient = $user->patient;
        if (!$patient) {
            return response()->json(['message' => 'Patient profile not found'], 404);
        }

        if ($appointment->status !== 'completed') {
            return response()->json(['message' => 'You can only rate completed appointments'], 400);
        }

        if (Review::where('appointment_id', $request->appointment_id)->exists()) {
            return response()->json(['message' => 'You have already rated this appointment'], 400);
        }

        $review = Review::create([
            'patient_id' => Auth::id(),
            'doctor_id' => $appointment->doctor_id,
            'appointment_id' => $request->appointment_id,
            'rating' => $request->rating,
            'comment' => $request->comment
        ]);

        $appointment->doctor->updateRating();

        return response()->json([
            'success' => true,
            'message' => 'Rating submitted successfully',
            'data' => $review
        ],200);
    }

    public function getTopDoctors()
    {
        $topDoctors = Doctor::with(['user', 'reviews'])
            ->withAvg('reviews as average_rating', 'rating')
            ->has('reviews')
            ->orderByDesc('average_rating')
            ->orderByDesc('experience_years')
            ->limit(5)
            ->get()
            ->map(function ($doctor) {
                return [
                    'id' => $doctor->id,
                    'name' => $doctor->user->name,
                    'specialty' => $doctor->specialty,
                    'experience_years' => $doctor->experience_years,
                    'rating' => number_format($doctor->average_rating, 1),
                    'profile_picture' => $doctor->user->getProfilePictureUrl(),
                    'review_count' => $doctor->reviews->count()
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $topDoctors
        ]);
    }
}
