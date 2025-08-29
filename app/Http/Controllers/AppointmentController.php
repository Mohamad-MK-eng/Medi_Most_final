<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\ClinicWallet;
use App\Models\ClinicWalletTransaction;
use App\Models\Doctor;
use App\Models\Payment;
use App\Models\TimeSlot;
use App\Models\WalletTransaction;
use App\Notifications\AppointmentBooked;
use App\Notifications\AppointmentCancelled;
use App\Models\MedicalCenterWallet;
use App\Models\MedicalCenterWalletTransaction;
use App\Models\Patient;
use App\Notifications\AppointmentConfirmationNotification;
use App\Services\AppointmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Notification;
use App\Notifications\InvoicePaid;
use Hash;

class AppointmentController extends Controller
{
    protected $appointmentService;

    public function __construct(AppointmentService $appointmentService)
    {
        $this->appointmentService = $appointmentService;
    }

    public function bookAppointment(Request $request)
    {
        $patient = Auth::user()->patient;

        if (!$patient) {
            return response()->json(['error' => 'Authenticated user is not a patient'], 403);
        }



        $doctorExists = Doctor::withTrashed()->where('id', $request->doctor_id)->exists();

        if (!$doctorExists) {
            return response()->json([
                'error' => 'doctor_not_found',
                'message' => 'The selected doctor is not available for appointments'
            ], 422);
        }





        $absentCount = Appointment::where('patient_id', $patient->id)
            ->where('status', 'absent')
            ->count();

        if ($absentCount >= 3) {
            return response()->json([
                'error' => 'account_blocked',
                'message' => 'Your account has been blocked due to multiple missed appointments. Please contact the clinic center.'
            ], 403);
        }
        $validated = $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'slot_id' => 'required|exists:time_slots,id',
            'method' => 'required|in:cash,wallet',
            'wallet_pin' => 'required_if:method,wallet|digits:4 ',

        ]);

        return DB::transaction(function () use ($validated, $patient) {
            try {
                \Log::info("Attempting to book slot_id: {$validated['slot_id']} for doctor_id: {$validated['doctor_id']}");

                $slot = TimeSlot::where('id', $validated['slot_id'])
                    ->where('doctor_id', $validated['doctor_id'])
                    ->lockForUpdate()
                    ->first();

                \Log::debug("Slot query result: " . json_encode($slot));

                if (!$slot) {
                    $availableSlots = TimeSlot::where('doctor_id', $validated['doctor_id'])
                        ->where('date', '>=', now()->format('Y-m-d'))
                        ->get();

                    \Log::error("Slot not found. Available slots: " . json_encode($availableSlots));

                    return response()->json([
                        'error' => 'Time slot not found or does not belong to this doctor',
                        'available_slots' => $availableSlots
                    ], 404);
                }

                if ($slot->is_booked) {
                    $existingAppointment = Appointment::where('time_slot_id', $slot->id)
                        ->whereIn('status', ['confirmed', 'completed'])
                        ->first();

                    if ($existingAppointment) {
                        return response()->json(['error' => 'This time slot has already been booked'], 409);
                    } else {
                        $slot->update(['is_booked' => false]);
                    }
                }




                $doctor = Doctor::findOrFail($validated['doctor_id']);

                $status = 'confirmed';
                $paymentStatus = 'pending';


                $appointment_date = Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $slot->date->format('Y-m-d') . ' ' . $slot->start_time,
                    'Asia/Damascus'
                );





                $appointment = Appointment::create([
                    'patient_id' => $patient->id,
                    'doctor_id' => $validated['doctor_id'],
                    'clinic_id' => $doctor->clinic_id,
                    'time_slot_id' => $slot->id,
                    'appointment_date' => $appointment_date,
                    'status' => $status,
                    'payment_status' => $paymentStatus,
                    'document_id' => $validated['document_id'] ?? null,
                    'price' => $doctor->consultation_fee,


                    'notes' => $validated['notes'] ?? null,
                ]);


                if ($validated['method'] === 'wallet') {

                    if (!$patient->wallet_activated_at) {
                        return response()->json([
                            'success' => false,
                            'error_code' => 'wallet_not_activated',
                            'message' => 'Please activate your wallet before making payments',
                        ], 400);
                    }

                    if ($validated['method'] === 'cash') {
                        Payment::create([
                            'appointment_id' => $appointment->id,
                            'patient_id' => $patient->id,
                            'amount' => $doctor->consultation_fee,
                            'method' => 'cash',
                            'status' => 'pending',
                            'paid_at' => null
                        ]);
                    }

                    if (!Hash::check($validated['wallet_pin'], $patient->wallet_pin)) {
                        return response()->json([
                            'success' => false,
                            'error_code' => 'invalid_pin',
                            'message' => 'Incorrect PIN',
                        ], 401);
                    }

                    try {
                        $this->processWalletPayment($patient, $doctor->consultation_fee, $appointment);
                        $paymentStatus = 'paid';
                    } catch (\Exception $e) {
                        return response()->json([
                            'success' => false,
                            'error' => 'Wallet payment failed',
                            'message' => $e->getMessage()
                        ], 400);
                    }
                }





                $slot->update(['is_booked' => true]);

                Notification::sendNow($patient->user, new AppointmentBooked($appointment));
                Notification::sendNow($doctor->user, new \App\Notifications\DoctorAppointmentBooked($appointment));

                return response()->json([
                    'success' => true,
                    'message' => 'Operation Done Successfully',
                    'appointment_details' => [
                        'clinic' => $doctor->clinic->name,
                        'payment_status' => $this->getPaymentStatus($appointment), // Get status from payments

                        'doctor' => $doctor->user->first_name . ' ' . $doctor->user->last_name,
                        'date' => $appointment->appointment_date->format('D d F Y'),
                        'note' => 'Stay tuned for any updates'
                    ]
                ]);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Appointment booking failed: ' . $e->getMessage()], 500);
            }
        });
    }

    protected function getPaymentStatus($appointment)
    {
        $payment = Payment::where('appointment_id', $appointment->id)
            ->where('status', 'completed')
            ->first();

        if ($payment) {
            return 'paid';
        }

        $anyPayment = Payment::where('appointment_id', $appointment->id)->first();

        if ($anyPayment) {
            return $anyPayment->status;
        }

        return 'pending';
    }



    protected function processWalletPayment($patient, $amount, $appointment)
    {
        // Check balance
        if ($patient->wallet_balance < $amount) {
            return response()->json([
                'success' => false,
                'error_code' => 'insufficient_balance',
                'message' => 'Your wallet balance is insufficient.',
                'current_balance' => number_format($patient->wallet_balance, 2),
                'required_amount' => number_format($amount, 2),
                'shortfall' => number_format($amount - $patient->wallet_balance, 2),
            ], 400);
        }

        return DB::transaction(function () use ($patient, $amount, $appointment) {
            // Deduct from patient wallet
            $patient->decrement('wallet_balance', $amount);

            // Add to medical center wallet
            $medicalCenterWallet = MedicalCenterWallet::firstOrCreate([], ['balance' => 0]);
            $medicalCenterWallet->increment('balance', $amount);

            // Create patient wallet transaction
            $patientTransaction = WalletTransaction::create([
                'patient_id' => $patient->id,
                'amount' => $amount,
                'type' => 'payment',
                'reference' => 'APT-' . $appointment->id,
                'balance_before' => $patient->wallet_balance + $amount,
                'balance_after' => $patient->wallet_balance,
                'notes' => 'Payment for appointment #' . $appointment->id
            ]);

            // Create medical center wallet transaction
            MedicalCenterWalletTransaction::create([
                'medical_wallet_id' => $medicalCenterWallet->id,
                'clinic_id' => $appointment->clinic_id,
                'amount' => $amount,
                'type' => 'payment',
                'reference' => 'APT-' . $appointment->id,
                'balance_before' => $medicalCenterWallet->balance - $amount,
                'balance_after' => $medicalCenterWallet->balance,
                'notes' => 'Payment from patient #' . $patient->id . ' for appointment #' . $appointment->id
            ]);

            // Create payment record with 'paid' status for wallet payments
            Payment::create([
                'appointment_id' => $appointment->id,
                'patient_id' => $patient->id,
                'amount' => $amount,
                'method' => 'wallet',
                'status' => 'paid', // Changed from 'completed' to 'paid' to match your enum
                'transaction_id' => 'MCW-' . $patientTransaction->id,
                'paid_at' => now()
            ]);

            return true;
        });
    }




    public function getClinicDoctors($clinicId)
    {
        $doctors = Doctor::where('clinic_id', $clinicId)


            ->with(['user:id,first_name,last_name,profile_picture'])
            ->get()
            ->map(function ($doctor) {
                return [
                    'id' => $doctor->id,
                    'first_name' => $doctor->user->first_name,
                    'last_name' => $doctor->user->last_name,
                    'specialty' => $doctor->specialty,
                    'profile_picture_url' => $doctor->user->getProfilePictureUrl()
                        ? asset('storage/' . $doctor->user->profile_picture)
                        : null,
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

            'method' => [
                'cash' => true,
                'wallet' => true
            ]
        ]);
    }







    public function getClinicDoctorsWithSlots($clinicId, Request $request)
    {
        $request->validate([
            'date' => 'sometimes|date'
        ]);

        $date = $request->input('date')
            ? Carbon::parse($request->date)->format('Y-m-d')
            : now()->addDays(30)->format('Y-m-d');

        $doctors = Doctor::with(['user:id,first_name,last_name', 'timeSlots' => function ($query) use ($date) {
            $query->where('date', $date)
                ->where('is_booked', false)
                ->orderBy('start_time');
        }])
            ->where('clinic_id', $clinicId)
            ->get()
            ->map(function ($doctor) use ($date) {
                return [
                    'id' => $doctor->id,
                    'name' => $doctor->user->first_name . ' ' . $doctor->user->last_name,
                    'specialty' => $doctor->specialty,
                    'available_slots' => $doctor->timeSlots->map(function ($slot) {
                        return [
                            'id' => $slot->id,
                            'start_time' => $slot->formatted_start_time,
                            'end_time' => $slot->formatted_end_time,
                            'date' => $slot->date->format('Y-m-d')
                        ];
                    }),
                    '_debug' => [
                        'doctor_id' => $doctor->id,
                        'date_queried' => $date,
                        'slots_count' => $doctor->timeSlots->count()
                    ]
                ];
            });

        return response()->json($doctors);
    }




    public function getAvailableSlots($doctorId, $date)
    {
        try {
            $dateCarbon = Carbon::createFromFormat('Y-m-d', $date);
            $now = Carbon::now();

            $slots = TimeSlot::where('doctor_id', $doctorId)
                ->where('date', $date)
                ->where('is_booked', false)
                ->where(function ($query) use ($now, $date) {
                    $query->where('date', '>', $now->format('Y-m-d'))
                        ->orWhere(function ($q) use ($now, $date) {
                            $q->where('date', $now->format('Y-m-d'))
                                ->where('start_time', '>', $now->format('H:i:s'));
                        });
                })
                ->orderBy('start_time')
                ->get();

            $formattedSlots = $slots->map(function ($slot) {
                return [
                    'id' => $slot->id,
                    'time' => Carbon::parse($slot->start_time)->format('g:i A'), // heek "10:00 AM"
                    'is_booked' => $slot->is_booked
                ];
            });

            return response()->json([
                'date' => $dateCarbon->format('D j F'), // like "Sun 11 May"
                'available_times' => $formattedSlots,
                'earliest_time' => $slots->isNotEmpty()
                    ? Carbon::parse($slots->first()->start_time)->format('g:i A')
                    : null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Invalid date format or processing error',
                'message' => $e->getMessage()
            ], 400);
        }
    }





    public function getAvailableTimes(Doctor $doctor, $date)
    {
        date_default_timezone_set('Asia/Damascus');
        Carbon::setTestNow(Carbon::now('Asia/Damascus'));

        $date = str_replace('date=', '', $date);

        try {
            $parsedDate = Carbon::parse($date)->timezone('Asia/Damascus')->format('Y-m-d');
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid date format'], 400);
        }

        $now = Carbon::now('Asia/Damascus');

        $slots = TimeSlot::where('doctor_id', $doctor->id)
            ->where('date', $parsedDate)
            ->where('is_booked', false)
            ->where(function ($query) use ($now, $parsedDate) {
                $query->where('date', '>', $now->format('Y-m-d'))
                    ->orWhere(function ($q) use ($now, $parsedDate) {
                        $q->where('date', $parsedDate)
                            ->where('start_time', '>=', $now->format('H:i:s'));
                    });
            })
            ->orderBy('start_time')
            ->get()
            ->map(function ($slot) use ($now) {
                $time = Carbon::parse($slot->start_time)->format('g:i A');

                return [
                    'slot_id' => $slot->id,
                    'time' => $time,
                ];
            })->toArray();

        if (!empty($slots)) {
            $slots[0]['time'] = $slots[0]['time'] . '';
        }

        return response()->json([
            'times' => $slots,

        ]);
    }




    public function getDoctorAvailableDaysWithSlots(Doctor $doctor, Request $request)
    {
        $request->validate([
            'period' => 'sometimes|integer|min:1|max:30',
        ]);

        date_default_timezone_set('Asia/Damascus');
        Carbon::setTestNow(Carbon::now('Asia/Damascus'));

        $period = $request->input('period', 7);
        $now = Carbon::now('Asia/Damascus');

        $workingDays = $doctor->schedules()
            ->pluck('day')
            ->map(fn($day) => strtolower($day))
            ->toArray();

        $startDate = Carbon::today('Asia/Damascus');
        $endDate = $startDate->copy()->addDays($period);
        $days = [];
        $earliestDateInfo = null;

        while ($startDate->lte($endDate)) {
            $dayName = strtolower($startDate->englishDayOfWeek);
            $dateDigital = $startDate->format('Y-m-d');

            if (in_array($dayName, $workingDays)) {
                $availableSlots = TimeSlot::where('doctor_id', $doctor->id)
                    ->where('date', $dateDigital)
                    ->where('is_booked', false)
                    ->where(function ($query) use ($now, $dateDigital) {
                        $query->where('date', '>', $now->format('Y-m-d'))
                            ->orWhere(function ($q) use ($now, $dateDigital) {
                                $q->where('date', $now->format('Y-m-d'))
                                    ->where('start_time', '>=', $now->format('H:i:s'));
                            });
                    })
                    ->orderBy('start_time')
                    ->get();

                if ($availableSlots->isNotEmpty()) {
                    $dayInfo = [
                        'full_date' => $startDate->format('Y-m-d'),
                        'day_name' => $startDate->format('D'),
                        'day_number' => $startDate->format('j'),
                        'month' => $startDate->format('F'),
                    ];

                    if (!$earliestDateInfo || $startDate->lt(Carbon::parse($earliestDateInfo['full_date']))) {
                        $firstSlot = $availableSlots->first();
                        $dayInfo['time'] = Carbon::parse($firstSlot->start_time)->format('g:i A');
                        $dayInfo['slot_id'] = $firstSlot->id;
                        $earliestDateInfo = $dayInfo;
                    }

                    $days[] = $dayInfo;
                }
            }
            $startDate->addDay();
        }

        $formattedDays = array_map(function ($day) {
            return [
                'full_date' => $day['full_date'],
                'day_name' => $day['day_name'],
                'day_number' => $day['day_number'],
                'month' => $day['month']
            ];
        }, $days);

        return response()->json([
            'message' => 'available_days',
            'earliest_date' => $earliestDateInfo,
            'days' => $formattedDays
        ]);
    }







    public function getAppointments(Request $request)
    {
        $patient = Auth::user()->patient;
        $type = $request->query('type', 'upcoming');
        $perPage = $request->query('per_page', 8);

        date_default_timezone_set('Asia/Damascus');
        $nowLocal = Carbon::now('Asia/Damascus');

        $query = $patient->appointments()
            ->with([
                'doctor.user:id,first_name,last_name,profile_picture',
                'clinic:id,name',
                'payments' => function ($query) {
                    $query->whereIn('status', ['completed', 'paid']);
                }
            ])
            ->orderBy('appointment_date', 'desc');

        if ($type === 'upcoming') {
            $appointments = $query->where('status', 'confirmed')
                ->where('appointment_date', '>=', $nowLocal)
                ->get()
                ->map(function ($appointment) {
                    $paymentStatus = $appointment->payments->isNotEmpty()
                        ? 'paid'
                        : 'pending';

                    $doctorUser = $appointment->doctor->user;
                    $profilePictureUrl = $doctorUser ? $doctorUser->getFileUrl('profile_picture') : null;
                    $localTime = Carbon::parse($appointment->appointment_date)
                        ->setTimezone('Asia/Damascus');

                    return [
                        'id' => $appointment->id,
                        'date' => $localTime->format('Y-m-d h:i A'),
                        'doctor_id' => $appointment->doctor->id,
                        'first_name' =>  $appointment->doctor->user->first_name,
                        'last_name' =>   $appointment->doctor->user->last_name,
                        'specialty' =>  $appointment->doctor->specialty,
                        'profile_picture_url' => $profilePictureUrl,
                        'clinic_name' => $appointment->clinic->name,
                        'type' => $paymentStatus,
                        'price' => $appointment->price,
                    ];
                });

            return response()->json(['data' => $appointments->values()]);
        } else if ($type === 'completed') {
            $completedAppointments = $query->where(function ($q) use ($nowLocal) {
                $q->where('status', 'completed')
                    ->orWhere('appointment_date', '<', $nowLocal);
            })
                ->paginate($perPage)
                ->through(function ($appointment) use ($nowLocal) {
                    if (
                        $appointment->status !== 'completed' &&
                        $appointment->appointment_date < $nowLocal
                    ) {
                        $appointment->update(['status' => 'completed']);
                    }

                    $paymentStatus = $appointment->payments->isNotEmpty()
                        ? 'paid'
                        : 'pending';

                    $doctorUser = $appointment->doctor->user;
                    $profilePictureUrl = $doctorUser ? $doctorUser->getFileUrl('profile_picture') : null;
                    $localTime = Carbon::parse($appointment->appointment_date)
                        ->setTimezone('Asia/Damascus');

                    return [
                        'id' => $appointment->id,
                        'date' => $localTime->format('Y-m-d h:i A'),
                        'doctor_id' => $appointment->doctor->id,
                        'first_name' =>  $appointment->doctor->user->first_name,
                        'last_name' =>   $appointment->doctor->user->last_name,
                        'specialty' =>  $appointment->doctor->specialty,
                        'profile_picture_url' => $profilePictureUrl,
                        'clinic_name' => $appointment->clinic->name,
                        'type' => $paymentStatus,
                        'price' => $appointment->price,
                    ];
                });

            return response()->json([
                'data' => $completedAppointments->items(),
                'meta' => [
                    'current_page' => $completedAppointments->currentPage(),
                    'last_page' => $completedAppointments->lastPage(),
                    'per_page' => $completedAppointments->perPage(),
                    'total' => $completedAppointments->total(),
                ],
            ]);
        } else if ($type === 'absent') {
            $absentAppointments = $query->where('status', 'absent')
                ->paginate($perPage)
                ->through(function ($appointment) {
                    $paymentStatus = $appointment->payments->isNotEmpty()
                        ? 'paid'
                        : 'pending';

                    $doctorUser = $appointment->doctor->user;
                    $profilePictureUrl = $doctorUser ? $doctorUser->getFileUrl('profile_picture') : null;
                    $localTime = Carbon::parse($appointment->appointment_date)
                        ->setTimezone('Asia/Damascus');

                    return [
                        'id' => $appointment->id,
                        'date' => $localTime->format('Y-m-d h:i A'),
                        'doctor_id' => $appointment->doctor->id,
                        'first_name' => $appointment->doctor->user->first_name,
                        'last_name' => $appointment->doctor->user->last_name,
                        'specialty' => $appointment->doctor->specialty,
                        'profile_picture_url' => $profilePictureUrl,
                        'clinic_name' => $appointment->clinic->name,
                        'type' => $paymentStatus,
                        'price' => $appointment->price,
                        'status' => $appointment->status,
                        'is_absent' => true
                    ];
                });

            return response()->json([
                'data' => $absentAppointments->items(),
                'meta' => [
                    'current_page' => $absentAppointments->currentPage(),
                    'last_page' => $absentAppointments->lastPage(),
                    'per_page' => $absentAppointments->perPage(),
                    'total' => $absentAppointments->total(),
                ],
            ]);
        }

        return response()->json(['message' => 'Invalid appointment type'], 400);
    }


    public function updateAppointment(Request $request, $id)
    {
        try {
            $patient = Auth::user()->patient;
            if (!$patient) {
                return response()->json(['message' => 'Patient profile not found'], 404);
            }

            $appointment = $patient->appointments()->findOrFail($id);

            $validated = $request->validate([
                'doctor_id' => 'sometimes|exists:doctors,id',
                'time_slot_id' => 'sometimes|exists:time_slots,id',
                'reason' => 'sometimes|string|max:500|nullable',
            ], [
                'doctor_id.exists' => 'The selected doctor does not exist',
                'time_slot_id.exists' => 'The selected time slot does not exist'
            ]);

            if (isset($validated['doctor_id'])) {
                $appointment->doctor_id = $validated['doctor_id'];
            }

            if (isset($validated['time_slot_id'])) {
                $appointment->time_slot_id = $validated['time_slot_id'];
            }

            if (array_key_exists('reason', $validated)) {
                $appointment->reason = $validated['reason'];
            }

            if (!$appointment->save()) {
                return response()->json(['message' => 'Failed to save changes'], 500);
            }

            // تحديث البيانات من القاعدة
            $appointment->refresh();

            // ✅ جلب معلومات الدكتور بعد الحفظ
            $doctor = $appointment->doctor()->with('clinic', 'user')->first();

            return response()->json([
                'success' => true,
                'message' => 'Operation Done Successfully',
                'appointment_details' => [
                    'clinic' => $doctor->clinic->name ?? 'Unknown Clinic',
                    'doctor' => $doctor->user->first_name . ' ' . $doctor->user->last_name ?? 'Unknown Doctor',
                    'date' => $appointment->appointment_date->format('D d F Y'),
                    'note' => 'Stay tuned for any updates'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }




    protected function emptyAppointmentSlot(Appointment $appointment)
    {
        DB::transaction(function () use ($appointment) {
            $timeSlot = TimeSlot::find($appointment->time_slot_id);
            if ($timeSlot) {
                $timeSlot->update(['is_booked' => false]);
            }

            $payment = Payment::where('appointment_id', $appointment->id)
                ->where('method', 'wallet')
                ->where('status', 'completed')
                ->first();

            if ($payment) {
                $refundAmount = $appointment->price;
                $clinicWallet = MedicalCenterWallet::firstOrCreate(['clinic_id' => $appointment->clinic_id]);

                if ($clinicWallet->balance >= $refundAmount) {
                    $appointment->patient->increment('wallet_balance', $refundAmount);

                    $clinicWallet->decrement('balance', $refundAmount);

                    WalletTransaction::create([
                        'patient_id' => $appointment->patient->id,
                        'amount' => $refundAmount,
                        'type' => 'refund',
                        'reference' => 'APT-' . $appointment->id,
                        'balance_before' => $appointment->patient->wallet_balance - $refundAmount,
                        'balance_after' => $appointment->patient->wallet_balance,
                        'notes' => 'Refund for emptied appointment #' . $appointment->id
                    ]);

                    MedicalCenterWalletTransaction::create([
                        'clinic_wallet_id' => $clinicWallet->id,
                        'amount' => $refundAmount,
                        'type' => 'refund',
                        'reference' => 'APT-' . $appointment->id,
                        'balance_before' => $clinicWallet->balance + $refundAmount,
                        'balance_after' => $clinicWallet->balance,
                        'notes' => 'Refund for emptied appointment #' . $appointment->id
                    ]);

                    $payment->update([
                        'status' => 'refunded',
                        'refunded_at' => now(),
                        'refund_amount' => $refundAmount
                    ]);
                }
            }
        });
    }











    public function cancelAppointment(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        return DB::transaction(function () use ($validated, $id) {
            try {
                $patient = Auth::user()->patient;

                $appointment = $patient->appointments()
                    ->with(['patient.user', 'doctor.user', 'clinic', 'payments'])
                    ->where('status', '!=', 'completed')
                    ->findOrFail($id);

                $timeSlot = TimeSlot::where('id', $appointment->time_slot_id)
                    ->lockForUpdate()
                    ->first();

                if ($timeSlot) {
                    $timeSlot->update(['is_booked' => false]);
                }

                $appointment->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => $validated['reason']
                ]);

                $payment = Payment::where('appointment_id', $appointment->id)
                    ->where('method', 'wallet')
                    ->where('status', 'paid')
                    ->first();

                \Log::info("Payment search results", [
                    'appointment_id' => $appointment->id,
                    'payment_found' => $payment ? true : false,
                    'payment_status' => $payment ? $payment->status : 'none',
                    'payment_method' => $payment ? $payment->method : 'none'
                ]);

                        $refundAmount = 0;
            $discountApplied = 0;
            if ($payment) {
                $refundResult = $this->processWalletRefund($appointment, $payment);
                $refundAmount = $refundResult['refund_amount'];
                $discountApplied = $refundResult['discount_applied'];

                $patient->refresh();

                \Log::info("Refund processed for appointment {$appointment->id}", [
                    'patient_id' => $patient->id,
                    'refund_amount' => $refundAmount,
                    'discount_applied' => $discountApplied,
                    'patient_new_balance' => $patient->wallet_balance
                ]);
            }


                return response()->json([
                    'message' => 'Appointment cancelled successfully',
                    'slot_freed' => $timeSlot ? $timeSlot->id : null,
                    'refund_processed' => $payment ? true : false,
                    'refund_amount' => $payment ? $refundAmount : 0,
                    'discount_applied' => $payment ? $discountApplied : 0,
                'original_amount' => $payment ? $appointment->price : 0,
                    'patient_new_balance' => $patient->wallet_balance
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'cancellation_failed',
                    'message' => 'Failed to cancel appointment: ' . $e->getMessage()
                ], 500);
            }
        });
    }



    public function processWalletRefund(Appointment $appointment, Payment $payment)
    {
        $originalAmount = $appointment->price;


          $discountPercentage = 5;
    $discountAmount = ($originalAmount * $discountPercentage) / 100;

        $refundAmount =$originalAmount-$discountAmount;
                $patient = $appointment->patient;

        $medicalCenterWallet = MedicalCenterWallet::lockForUpdate()->firstOrCreate([], ['balance' => 0]);

        if ($medicalCenterWallet->balance < $originalAmount) {
            throw new \Exception('Medical center wallet has insufficient funds for refund');
        }

        $patientBalanceBefore = $patient->wallet_balance;
        $medicalBalanceBefore = $medicalCenterWallet->balance;

        $patient->wallet_balance = $patientBalanceBefore + $refundAmount;
        $patient->save();

        $medicalCenterWallet->balance = $medicalBalanceBefore - $refundAmount;
        $medicalCenterWallet->save();

        WalletTransaction::create([
            'patient_id' => $patient->id,
            'amount' => $refundAmount,
            'type' => 'refund',
            'reference' => 'APT-' . $appointment->id,
            'balance_before' => $patientBalanceBefore,
            'balance_after' => $patient->wallet_balance,
            'notes' => 'Refund for cancelled appointment #' . $appointment->id .
                  ' (5% discount applied: -$' . number_format($discountAmount, 2) . ')'
    ]);

        MedicalCenterWalletTransaction::create([
            'medical_wallet_id' => $medicalCenterWallet->id,
            'clinic_id' => $appointment->clinic_id,
            'amount' => $refundAmount,
            'type' => 'refund',
            'reference' => 'APT-' . $appointment->id,
            'balance_before' => $medicalBalanceBefore,
            'balance_after' => $medicalCenterWallet->balance,
            'notes' => 'Refund for cancelled appointment #' . $appointment->id .
                  ' (5% discount applied: -$' . number_format($discountAmount, 2) . ')'
    ]);

        $payment->update([
            'status' => 'refunded',
            'refunded_at' => now(),
            'refund_amount' => $refundAmount,
            'discount_applied'=>$discountAmount
        ]);

        return [
          'refund_amount' => $refundAmount,
        'discount_applied' => $discountAmount,
        'original_amount' => $originalAmount
        ];
    }
}
