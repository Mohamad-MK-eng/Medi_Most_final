<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\MedicalCenterWallet;
use App\Models\MedicalCenterWalletTransaction;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Secretary;
use App\Models\WalletTransaction;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SecretaryController extends Controller
{






    public function makePayment(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (!$user->patient) {
            return response()->json(['message' => 'Patient profile not found'], 404);
        }

        $validated = $request->validate([
            'appointment_id' => 'required|exists:appointments,id',
            'amount' => 'required|numeric|min:0',
            'method' => 'required|in:cash,card,insurance,transfer',
            'transaction_reference' => 'sometimes|string|max:255'
        ]);

        try {
            $appointment = $user->patient->appointments()
                ->findOrFail($validated['appointment_id']);

            $secretary = Secretary::first();

            $paymentData = [
                'appointment_id' => $appointment->id,
                'amount' => $validated['amount'],
                'method' => $validated['method'],
                'status' => 'paid',
                'patient_id' => $user->patient->id,
                'secretary_id' => $secretary->id ?? null,
                'transaction_reference' => $validated['transaction_reference'] ?? null,
                'medical_center_wallet' => true
            ];

            $payment = Payment::create($paymentData);

            if (in_array($validated['method'], ['cash', 'card', 'transfer'])) {
                $medicalCenterWallet = MedicalCenterWallet::firstOrCreate([], ['balance' => 0]);
                $medicalCenterWallet->increment('balance', $validated['amount']);

                MedicalCenterWalletTransaction::create([
                    'medical_center_wallet_id' => $medicalCenterWallet->id,
                    'clinic_id' => $appointment->clinic_id,
                    'amount' => $validated['amount'],
                    'type' => 'payment',
                    'reference' => 'CASH-' . $payment->id,
                    'balance_before' => $medicalCenterWallet->balance - $validated['amount'],
                    'balance_after' => $medicalCenterWallet->balance,
                    'notes' => 'Cash payment for appointment #' . $appointment->id
                ]);
            }

            $totalPaid = $appointment->payments()->sum('amount');
            if ($appointment->price && $totalPaid >= $appointment->price) {
                $appointment->update(['payment_status' => 'paid']);
            }

            return response()->json([
                'payment' => $payment->load(['appointment']),
                'message' => 'Payment processed successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Payment failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /*
    public function addToPatientWallet(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'sometimes|string|max:255'
        ]);

        if (!Auth::user()->secretary) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return DB
            ::transaction(function () use ($validated) {
                $patient = Patient::find($validated['patient_id']);
                $secretary = Auth::user();

                $transaction = $patient->deposit(
                    $validated['amount'],
                    $validated['notes'] ?? 'Added by secretary',
                    $secretary->id
                );

                return response()->json([
                    'message' => 'Funds added successfully',
                    'new_balance' => $patient->fresh()->wallet_balance,
                    'transaction' => $transaction
                ]);
            });
    }
*/

    public function addToPatientWallet(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:255'
        ]);

        if (!Auth::user()->secretary) {
            return response()->json([
                'status' => 'unauthorized',
                'message' => 'Unauthorized access'
            ], 403);
        }

        $patient = Patient::findOrFail($validated['patient_id']);

        // Check wallet activation first
        if (!$patient->wallet_pin || !$patient->wallet_activated_at) {
            return response()->json([
                'status' => 'wallet_not_activated',
                'message' => 'Cannot add funds - wallet is not activated',
                'current_balance' => $patient->wallet_balance
            ], 200);
        }

        $result = $patient->deposit(
            $validated['amount'],
            $validated['notes'] ?? 'Added by secretary',
            Auth::user()->id
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Funds added successfully',
            'transaction' => $result['transaction'],
            'new_balance' => $result['new_balance']
        ]);
    }






    public function unblockPatient(Request $request)
    {
        if (!Auth::user()->hasRole('secretary')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
        ]);

        $patient = Patient::findOrFail($validated['patient_id']);

        $absentAppointments = $patient->appointments()
            ->where('status', 'absent')
            ->get();

        foreach ($absentAppointments as $appointment) {
            $appointment->update([
                'status' => 'cancelled',
                'cancelled_by' => Auth::id(),
            ]);
        }
        return response()->json('message:block removed');
    }


    public function listBlockedPatients()
    {
        $threshold = config('app.absent_appointment_threshold', 3);

        $blockedPatients = Patient::withCount(['appointments as absent_count' => function ($query) {
            $query->where('status', 'absent');
        }])
            ->having('absent_count', '>=', $threshold)
            ->with('user:id,first_name,last_name,email,phone')
            ->get();

        return response()->json([
            'blocked_patients' => $blockedPatients->map(function ($patient) {
                return [
                    'id' => $patient->id,
                    'name' => $patient->user->first_name . ' ' . $patient->user->last_name,
                    'email' => $patient->user->email,
                    'phone' => $patient->user->phone,
                    'absent_count' => $patient->absent_count
                ];
            })
        ]);
    }



    public function getPatientWalletInfo($patientId)
    {
        $patient = Patient
            ::with(['user', 'walletTransactions' => function ($q) {
                $q->orderBy('created_at', 'desc')->limit(10);
            }])->findOrFail($patientId);

        return response()->json([
            'patient' => $patient,
            'wallet_balance' => $patient->wallet_balance,
            'wallet_activated' => !is_null($patient->wallet_activated_at),
            'recent_transactions' => $patient->walletTransactions
        ]);
    }








    public function getAppointments(Request $request)
    {
        if (!Auth::user()->secretary) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $type = $request->query('type', 'upcoming');
        $perPage = $request->query('per_page', 10);
        $clinicId = $request->query('clinic_id');
        $doctorId = $request->query('doctor_id');

        $query = Appointment::with([
            'patient.user:id,first_name,last_name',
            'doctor' => function ($query) {
                $query->withTrashed()->with(['user' => function ($q) {
                    $q->withTrashed()->select('id', 'first_name', 'last_name');
                }]);
            },
            'clinic:id,name',
            'payments'
        ]);

        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        if ($doctorId) {
            $query->where('doctor_id', $doctorId);
        }

        switch ($type) {
            case 'upcoming':
                $query->where('status', 'confirmed')
                    ->where('appointment_date', '>=', now());
                break;
            case 'completed':
                $query->where('status', 'completed');
                break;
            case 'cancelled':
                $query->where('status', 'cancelled');
                break;
            case 'absent':
                $query->where('status', 'absent');
                break;
            default:
                return response()->json(['message' => 'Invalid appointment type'], 400);
        }

        $appointments = $query->orderBy('appointment_date', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => $appointments->items(),
            'meta' => [
                'current_page' => $appointments->currentPage(),
                'per_page' => $appointments->perPage(),
                'total' => $appointments->total(),
            ]
        ]);
    }



    /*





public function createPatient(Request $request)
{
    if (!Auth::user()->secretary) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $validated = $request->validate([
        'first_name' => 'required|string|max:255',
        'last_name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
        'phone' => 'required|string|max:20',
        'gender' => 'required|in:male,female,other',
        'date_of_birth' => 'required|date',
        'address' => 'nullable|string',
        '_type' => 'nullable|string|max:3',
    ]);

    try {
        DB::beginTransaction();

        // Get or create patient role (same as register method)
        $patientRole = Role::firstOrCreate(
            ['name' => 'patient'],
            ['description' => 'Patient user']
        );

        // Create user with role_id
        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'gender' => $validated['gender'],
            'password' => bcrypt('temporary_password'),
            'role_id' => $patientRole->id, // Add this line
        ]);

        // Create patient
        $patient = Patient::create([
            'user_id' => $user->id,
            'date_of_birth' => $validated['date_of_birth'],
            'address' => $validated['address'],
            'blood_type' => $validated['blood_type'],
        ]);

        DB::commit();

        return response()->json([
            'message' => 'Patient created successfully',
            'patient' => $patient->load('user')
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Failed to create patient',
            'error' => $e->getMessage()
        ], 500);
    }
}


*/
}
