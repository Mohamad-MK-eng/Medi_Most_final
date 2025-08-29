<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\ClinicWallet;
use App\Models\ClinicWalletTransaction;
use App\Models\Doctor;
use App\Models\MedicalCenterWallet;
use App\Models\MedicalCenterWalletTransaction;
use App\Models\Specialty;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;


class ClinicController extends Controller
{
    public function index()
    {
        $clinics = Clinic::all()->map(function ($clinic) {
            return [
                'id' => $clinic->id,
                'name' => $clinic->name,
                'location' => $clinic->location,
                'icon_url' => $clinic->getIconUrl()
            ];
        });

        return response()->json($clinics);
    }






    public function show($id)
    {
        $clinic = Clinic::findOrFail($id);

        return response()->json([
            'id' => $clinic->id,
            'name' => $clinic->name,
            'location' => $clinic->location,
            'description' => $clinic->description,
            'icon_url' => $clinic->getIconUrl(),
            'doctors_count' => $clinic->doctors->count()
        ]);
    }



    public function getIconUrl($id)
    {
        $clinic = Clinic::findOrFail($id);

        if (!$clinic->image_path) {
            return response()->json(['message' => 'No image set for this clinic'], 404);
        }

        try {
            $filename = basename($clinic->description_picture);
            $relativePath = 'clinic_images/' . $filename;

            if (!Storage::disk('public')->exists($relativePath)) {
                return response()->json(['message' => 'Image file not found'], 404);
            }

            $fullPath = storage_path('app/public/' . $relativePath);
            return response()->file($fullPath);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving clinic image',
                'error' => $e->getMessage()
            ], 500);
        }
    }
















    public function getClinicDoctors(Clinic $clinic)
    {
        $doctors = $clinic->doctors()
            ->with(['user', 'reviews', 'schedules'])
            ->get();


        // مبدئيا هيك تمام بس انت حاطط شرط حلو تبع is active هاد في حال بدو يستقيل ويصفي معايناته القديمة وما بده حدا جديد
        // بتعمل فلترة على اللي is active ? true
        $formattedDoctors = $doctors->map(function ($doctor) {
            $user = $doctor->user;

            return [
                'id' => $doctor->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'specialty' => $doctor->specialty,
                'experience_years' => $doctor->experience_years,
                'profile_picture_url' => $user->getProfilePictureUrl(),
                'rate' => (float)$doctor->rating,
                // حلوة الفكرة
                'is_active' => $doctor->is_active
                /* 'schedules' => $doctor->schedules->map(function ($schedule) {
                    return [
                        'day' => $schedule->day,
                    ];
                }) */
            ];
        });

        return response()->json([
            'doctors' => $formattedDoctors
        ]);
    }


    public function getClinicDoctorsWithSlots(Clinic $clinic, Request $request)
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d'
        ]);

        $doctors = $clinic->doctors()
            ->with(['user', 'schedules', 'timeSlots' => function ($query) use ($request) {
                $query->where('date', $request->date)
                    ->where('is_booked', false);
            }])
            ->withAvg('reviews', 'rating')
            ->get()
            ->map(function ($doctor) {
                return [
                    'id' => $doctor->id,
                    'first_name' => $doctor->user->first_name,
                    'last_name' => $doctor->user->last_name,
                    'specialty' => $doctor->specialty,
                    'profile_picture_url' => $doctor->user->getProfilePictureUrl(),
                    'experience_years' => $doctor->experience_years,
                    'rating' => $doctor->reviews_avg_rating ? (float) $doctor->reviews_avg_rating : 0,
                    'available_slots' => $doctor->timeSlots->map(function ($slot) {
                        return [
                            'id' => $slot->id,
                            'start_time' => $slot->start_time,
                            'end_time' => $slot->end_time
                        ];
                    })
                ];
            });

        return response()->json($doctors);
    }





    public function getDoctorDetails(Doctor $doctor)
    {
        $doctor->load(['reviews', 'schedules', 'user']);
        $averageRating = $doctor->reviews->avg('rating');
        $schedule = $doctor->schedules->map(function ($schedule) {
            return [
                'day' => ucfirst($schedule->day),
                'start_time' => Carbon::parse($schedule->start_time)->format('g:i A'),
                'end_time' => Carbon::parse($schedule->end_time)->format('g:i A')
            ];
        });

        return response()->json([
            'name' => $doctor->user->first_name . ' ' . $doctor->user->last_name,
            'specialty' => $doctor->specialty,
            'rate' => $doctor->rating,
            'consultation_fee' => $doctor->consultation_fee,
            2,
            'bio' => $doctor->bio,
            'schedule' => $schedule,
            'review_count' => $doctor->reviews->count(),
            'is_active' => $doctor->is_active? true : false,

            'method' => [
                'cash' => true,
                'wallet' => true
            ]
        ]);
    }















    public function getWalletBalance($clinicId)
    {
        $clinic = Clinic::findOrFail($clinicId);
        $wallet = $clinic->wallet()->firstOrCreate(['clinic_id' => $clinicId]);

        return response()->json([
            'clinic_id' => $clinic->id,
            'clinic_name' => $clinic->name,
            'balance' => $wallet->balance,
            'last_updated' => $wallet->updated_at
        ]);
    }

    public function getWalletTransactions($clinicId)
    {
        $wallet = MedicalCenterWallet::with('transactions')
            ->where('clinic_id', $clinicId)
            ->firstOrFail();

        return response()->json([
            'clinic_id' => $wallet->clinic_id,
            'balance' => $wallet->balance,
            'transactions' => $wallet->transactions()->orderBy('created_at', 'desc')->paginate(10)
        ]);
    }

    public function withdrawFromWallet(Request $request, $clinicId)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'sometimes|string|max:255'
        ]);

        $wallet = MedicalCenterWallet::where('clinic_id', $clinicId)->firstOrFail();

        if ($wallet->balance < $validated['amount']) {
            return response()->json([
                'message' => 'Insufficient balance',
                'current_balance' => $wallet->balance
            ], 400);
        }

        return DB::transaction(function () use ($wallet, $validated) {
            $wallet->decrement('balance', $validated['amount']);

            MedicalCenterWalletTransaction::create([
                'clinic_wallet_id' => $wallet->id,
                'amount' => $validated['amount'],
                'type' => 'withdrawal',
                'reference' => 'WDR-' . now()->format('YmdHis'),
                'balance_before' => $wallet->balance + $validated['amount'],
                'balance_after' => $wallet->balance,
                'notes' => $validated['notes'] ?? 'Cash withdrawal'
            ]);

            return response()->json([
                'message' => 'Withdrawal successful',
                'new_balance' => $wallet->balance,
                'withdrawal_amount' => $validated['amount']
            ]);
        });
    }
}
