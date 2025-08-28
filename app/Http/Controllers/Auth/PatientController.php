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
        $user = Auth::user();
        $patient = $user->patient;

        if (!$patient) {
            return response()->json(['message' => 'Patient profile not found'], 404);
        }

        $profile = [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone_number' => $patient->phone_number,
            'address' => $patient->address,
            'date_of_birth' => $patient->date_of_birth,
            'gender' => $patient->gender,
            'blood_type' => $patient->blood_type,
            'chronic_conditions' => $patient->chronic_conditions,
            'profile_picture_url' => $user->getProfilePictureUrl(),
            'wallet_balance' => $patient->wallet_balance,
            'wallet_pin' => $patient->wallet_pin
        ];

        return response()->json([
            'message' => ' Patient Profile Fetched successfully',
            'patient' => $profile
        ]);
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
            'gender' => 'sometimes|string|in:Male,Female,other',
            'blood_type' => 'sometimes|in:A +,A -,B +,B -,AB +,AB -,O +,O -',
            'chronic_conditions' => 'nullable|String',
            'profile_picture' => 'sometimes|image|mimes:jpg,jpeg,png|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'invalid information'
                ,'errors' => $validator->errors()], 422);
        }

        // Handle profile picture update if present
        if ($request->hasFile('profile_picture')) {
            try {
                $patient->user->uploadFile($request->file('profile_picture'), 'profile_picture');
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
            'patient' => [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone_number' => $patient->phone_number,
                'address' => $patient->address,
                'date_of_birth' => $patient->date_of_birth,
                'gender' => $patient->gender,
                'blood_type' => $patient->blood_type,
                'chronic_conditions' => $patient->chronic_conditions,
                'profile_picture_url' => $user->getProfilePictureUrl(),
                'wallet_balance' => $patient->wallet_balance,
                'wallet_pin' => $patient->wallet_pin
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
            $path = $patient->user->profile_picture;

            // Remove any 'storage/' prefix if present
            $path = str_replace('storage/', '', $path);

            // Check if file exists
            if (!Storage::disk('public')->exists($path)) {
                return response()->json(['message' => 'Profile picture file not found'], 404);
            }-

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




            if ($transactions->isEmpty()) {
        return response()->json([
            'message' => 'No transactions found',
            'data' => [],
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'last_page' => $transactions->lastPage(),
            ]
        ]);
    }
        return response()->json($transactions);
    }


public function getPatientHistory(Request $request)
{
    $patient = Auth::user()->patient;

    if (!$patient) {
        return response()->json(['message' => 'Patient profile not found'], 404);
    }

    // Custom validation for date (accepts both YYYY-M and YYYY-M-D)
    $validator = Validator::make($request->all(), [
        'date' => 'sometimes|regex:/^\d{4}-\d{1,2}(-\d{1,2})?$/',
        'clinic_id' => 'sometimes|exists:clinics,id',
        'per_page' => 'sometimes|integer|min:1|max:100',
        'page' => 'sometimes|integer|min:1'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }

    $validated = $validator->validated();

    $query = Appointment::with([
            'clinic:id,name',
            'doctor.user:id,first_name,last_name,profile_picture',
            'doctor:id,user_id,specialty' // Ensure doctor is loaded
        ])->where('status','completed')
        ->where('patient_id', $patient->id)
        ->orderBy('appointment_date', 'desc');

    // Date filtering
    if ($request->has('date')) {
        $dateInput = $validated['date'];
        $dateParts = explode('-', $dateInput);

        if (count($dateParts) === 2) {
            // Format: YYYY-M (month filter)
            $year = $dateParts[0];
            $month = $dateParts[1];

            // Validate month
            if ($month < 1 || $month > 12) {
                return response()->json([
                    'message' => 'Invalid month value (1-12)',
                    'errors' => ['date' => ['Month must be between 1 and 12']]
                ], 422);
            }

            $query->whereYear('appointment_date', $year)
                  ->whereMonth('appointment_date', $month);
        } else {
            // Format: YYYY-M-D (specific date filter)
            try {
                $date = Carbon::createFromFormat('Y-n-j', $dateInput);
                if (!$date || $date->format('Y-n-j') !== $dateInput) {
                    throw new \Exception('Invalid date');
                }
                $query->whereDate('appointment_date', $date->format('Y-m-d'));
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Invalid date format',
                    'errors' => ['date' => ['Date must be in format YYYY-M or YYYY-M-D']]
                ], 422);
            }
        }
    }

    // Apply clinic filter if provided
    if ($request->has('clinic_id')) {
        $query->where('clinic_id', $validated['clinic_id']);
    }

    // Paginate results
    $perPage = $validated['per_page'] ?? 10;
    $appointments = $query->paginate($perPage);

    if ($appointments->isEmpty()) {
        return response()->json([
            'message' => 'No appointments found for the given criteria',
            'data' => [],
            'meta' => [
                'current_page' => $appointments->currentPage(),
                'per_page' => $appointments->perPage(),
                'total' => $appointments->total(),
                'last_page' => $appointments->lastPage(),
            ]
        ], 404);
    }

    // Format response with proper null checks
    $formattedAppointments = $appointments->map(function ($appointment) {
        $doctor = $appointment->doctor;
        $doctorUser = $doctor->user ?? null;
        $clinic = $appointment->clinic ?? null;

        return [
            'id' => $appointment->id,
            'date' => $appointment->appointment_date->format('Y-n-j h:i A'),
            'clinic_name' => $clinic ? $clinic->name : null,
            'doctor_id' => $doctor ? $doctor->id : null,
            'first_name' => $doctorUser ? $doctorUser->first_name : null,
            'last_name' => $doctorUser ? $doctorUser->last_name : null,
            'specialty' => $doctor ? $doctor->specialty : null,
            'profile_picture_url' => $doctorUser ? $doctorUser->getFileUrl('profile_picture') : null
        ];
    });

    return response()->json([
        'data' => $formattedAppointments,
        'meta' => [
            'current_page' => $appointments->currentPage(),
            'per_page' => $appointments->perPage(),
            'total' => $appointments->total(),
            'last_page' => $appointments->lastPage(),
        ]
    ]);
}

    // tested successfully

    public function getMedicalHistory(Request $request)
    {
        $patient = Auth::user()->patient;
        if (!$patient) {
            return response()->json(['message' => 'Patient profile not found'], 404);
        }


         $history = $request->user()->patient->appointments()
        ->with(['prescription', 'medicalNotes', 'doctor.user', 'clinic'])
        ->where('appointment_date', '<=', now())
        ->orderBy('appointment_date', 'desc')
        ->get();

    if ($history->isEmpty()) {
        return response()->json([
            'message' => 'No medical history found',
            'data' => []
        ]);
    }


      return $history->map(function ($appointment) {
        return [
            'id' => $appointment->id,
            'date' => $appointment->appointment_date,
            'clinic' => $appointment->clinic->name,
            'doctor' => $appointment->doctor->user->name,
            'diagnosis' => $appointment->diagnosis,
            'notes' => $appointment->notes,
            'prescription' => $appointment->prescription,
        ];
    });
}

















    // not tested  yet
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
            ->with(['prescription'])
            ->where('status', '!=', 'absent') // Ensure 'prescription' is a defined relationship in Appointment model
            ->whereHas('prescription') // Only appointments with prescriptions
            ->get()
            ->pluck('prescription') // Extract prescriptions
            ->filter(); // Remove null entries (if any)

         if ($prescriptions->isEmpty()) {
        return response()->json([
            'message' => 'No prescriptions found'        ]);
    }
        return response()->json($prescriptions);
    }






public function getAppointmentReports(Appointment $appointment)
{
    $patient = Auth::user()->patient;



      if ($appointment->status === 'absent') {
        return response()->json([
            'success' => false,
            'message' => 'No report available for absent appointments'
        ], 404);
    }

    if ($appointment->patient_id !== $patient->id) {
        return response()->json(['message' => 'Unauthorized access to appointment reports'], 403);
    }






    if ($appointment->patient_id !== $patient->id) {
        return response()->json(['message' => 'Unauthorized access to appointment reports'], 403);
    }

    // Load the report with prescriptions and related data
    $report = $appointment->report()
                ->with(['prescriptions', 'appointment.doctor.user', 'appointment.clinic'])
                ->first();

    if (!$report) {
        return response()->json(['message' => 'No report found for this appointment'], 404);
    }

    // Format the response to match your interface
    $formattedReport = [
        'date' => $appointment->appointment_date->format('Y-n-j h:i A'), // "2025-7-20 10:00 AM"
        'clinic' => $appointment->clinic->name, // "Oncology"
        'doctor' => $appointment->doctor->user->first_name . ' ' . $appointment->doctor->user->last_name, // "John White"
        'specialty' => $appointment->doctor->specialty, // "special"
        'title' => $report->title ?? 'Medical Report', // "Report Title"
        'content' => $report->content, // The content of the report
        'prescriptions' => $report->prescriptions->map(function($prescription) {
            return [
                'medication' => $prescription->medication, // "Paracetamol"
                'dosage' => $prescription->dosage, // "500mg"
                'frequency' => $prescription->frequency, // "3x/day"
                'instructions' => $prescription->instructions, // "After meal"
                'is_completed' => (bool)$prescription->is_completed // checkbox status
            ];
        })->toArray()
    ];

    return response()->json([
        'success' => true,
        'report' => $formattedReport
    ]);
}










    //  until another time
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
