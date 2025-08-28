<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\Review;
use App\Models\Secretary;
use App\Models\TimeSlot;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;


use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use DB;
use Storage;
use App\Traits\HandlesFiles;

class PatientController extends Controller
{
    use HandlesFiles;



    public function getProfile()
    {
        $patient = Auth::user()->patient;
        if (!$patient) {
            return response()->json(['message' => 'Patient profile not found'], 404);
        }

        $profile = $patient->only([
            'first_name',
            'last_name',
            'phone_number',
            'address',
            'date_of_birth',
            'gender',
            'blood_type',
            'chronic_conditions',
            'emergency_contact',
        ]);

        $patient = Auth::user();
        $patient->getProfilePictureUrl();

        // Add user fields
        $profile['first_name'] = $patient->user->first_name;
        $profile['last_name'] = $patient->user->last_name;

        return response()->json($profile);
    }




    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        $patient = $user->patient;
        if (!$patient) {
            return response()->json(['message' => 'Patient profile not found'], 404);
        }


        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone_number' => 'sometimes|string|max:20',
            'address' => 'sometimes|string|nullable',
            'date_of_birth' => 'sometimes|date',
            'gender' => 'sometimes|string|in:male,female,other',
            'blood_type' => 'sometimes|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'chronic_conditions' => 'nullable|array',
            'emergency_contact' => 'sometimes|string|max:255',
            'profile_picture' => 'sometimes|image|mimes:jpg,jpeg,png|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Handle profile picture update if present
        if ($request->hasFile('profile_picture')) {
            try {
                $patient->uploadFile($request->file('profile_picture'), 'profile_picture');
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Failed to upload picture',
                    'message' => $e->getMessage()
                ], 500);
            }
        }



        $validated = $validator->validated();




        if (isset($validated['first_name']) || isset($validated['last_name'])) {
            $user->update([
                'first_name' => $validated['first_name'] ?? $user->first_name,
                'last_name' => $validated['last_name'] ?? $user->last_name
            ]);
        }

        // Remove user-related fields from patient data
        $patientData = collect($validated)->except(['first_name', 'last_name', 'profile_picture'])->all();

        // Update patient data
        $patient->update($patientData);

        return response()->json([
            'patient' => $patient->fresh()->load('user'),
            'profile_picture' => [
                'url' => $patient->user->getProfilePictureUrl(),
                'exists' => !empty($patient->user->profile_picture)
            ],
            'message' => 'Profile updated successfully'
        ]);
    }








    public function getProfilePicture()
    {
        $patient = Auth::user()->patient;

        if (!$patient) {
            return response()->json(['message' => 'Patient profile not found'], 404);
        }

        if (!$patient->user->profile_picture) {
            return response()->json(['message' => 'No profile picture set'], 404);
        }

        try {
            // Get the stored path
            $path = $patient->getFileUrl('profile_picture');

            // Remove any 'storage/' prefix if present
            $path = str_replace('storage/', '', $path);

            // Check if file exists
            if (!Storage::disk('public')->exists($path)) {
                return response()->json(['message' => 'Profile picture file not found'], 404);
            }

            // Get the full filesystem path
            $fullPath = Storage::disk('public')->path($path);

            return response()->file($fullPath);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving profile picture',
                'error' => $e->getMessage()
            ], 500);
        }
    }





    public function uploadProfilePicture(Request $request)
    {
        $request->validate([
            'profile_picture' => 'required|image|mimes:jpg,jpeg,png|max:3072' // 3MB max
        ]);

        $patient = Auth::user()->patient;

        if (!$patient) {
            return response()->json(['message' => 'Patient profile not found'], 404);
        }

        try {
            // Upload the file using the HandlesFiles trait
            $uploaded = $patient->user->uploadFile($request->file('profile_picture'), 'profile_picture');

            if (!$uploaded) {
                throw new \Exception('Failed to upload profile picture');
            }

            return response()->json([
                'success' => true,
                'profile_picture_url' => $patient->user->getProfilePictureUrl(),
                'message' => 'Profile picture updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload profile picture',
                'error' => $e->getMessage()
            ], 500);
        }
    }




    // 2. Get doctors for a clinic with profile pictures



    public function getWalletTransactions()
    {
        $patient = Auth::user()->patient;
        if (!$patient) {
            return response()->json(['message' => 'Patient profile not found'], 404);
        }

        $transactions = $patient->walletTransactions()
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($transactions);
    }







    public function getWalletBalance()
    {
        $patient = Auth::user()->patient;
        if (!$patient) {
            return response()->json(['message' => 'Patient profile not found'], 404);
        }

        return response()->json([
            'balance' => $patient->wallet_balance,
            'wallet_activated' => !is_null($patient->wallet_activated_at)
        ]);
    }



    public function getPrescriptions()
    {
        $patient = Auth::user()->patient;
        if (!$patient) {
            return response()->json(['message' => 'Patient profile not found'], 404);
        }

        // Alternative 1: If prescriptions are linked via appointments
        $prescriptions = $patient->appointments()
            ->with(['prescription']) // Ensure 'prescription' is a defined relationship in Appointment model
            ->whereHas('prescription') // Only appointments with prescriptions
            ->get()
            ->pluck('prescription') // Extract prescriptions
            ->filter(); // Remove null entries (if any)


        return response()->json($prescriptions);
    }


    public function submitRating(Request $request)
    {
        $validated = $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'appointment_id' => 'required|exists:appointments,id',
            'rating' => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:500'
        ]);

        $review = Review::create([
            'patient_id' => $request->user()->patient->id,
            ...$validated
        ]);

        return response()->json($review, 201);
    }






}
