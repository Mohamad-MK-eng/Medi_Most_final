<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\MedicalCenterWallet;
use App\Models\MedicalCenterWalletTransaction;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\Report;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\EmergencyAppointmentCancellationNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Traits\HandlesFiles;
use Carbon\Carbon;
use DB;

class DoctorController extends Controller
{
    use HandlesFiles;

    public function getProfile()
    {
        try {
            $user = Auth::user(); // Get authenticated user

            // Check if user has a doctor profile
            if (!$user->doctor) {
                return response()->json([
                    'error' => 'Doctor profile not found',
                    'message' => 'User is not associated with a doctor profile'
                ], 404);
            }

            $doctor = $user->doctor->load(['clinic', 'schedules', 'reviews']);

            // Format working days
            $schedule = $doctor->schedules->map(function ($schedule) {
                return [
                    'day' => ucfirst($schedule->day),

                ];
            });

            return response()->json([
                'personal_information' => [
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'phone_number' => $user->phone,
                    'email' => $user->email,
                    'gender' => $user->gender ?? 'Not specified',
                    'address' => $user->address ?? 'Not specified',
                    'specialty' => $doctor->specialty,
                    'consultation_fee' => number_format($doctor->consultation_fee, 0),
                    'start_working_date' => $doctor->experience_start_date
                        ? Carbon::parse($doctor->experience_start_date)->format('Y-m-d')
                        : 'Not specified',
                    'experience_years' => $doctor->getExperienceYearsAttribute(),
                    'rating' => round($doctor->rating, 1),
                    'rating_count' => $doctor->reviews->count(),
                    'bio' => $doctor->bio ?? 'No bio available',
                    'profile_picture_url' => $user->getProfilePictureUrl(),
                    'clinic' => $doctor->clinic->name,
                    'working_days' => $schedule,
                    'is_active' => $doctor->is_active ? true : false
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load profile',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getProfilePicture()
    {
        $user = Auth::user();

        try {
            $path = $user->getFileUrl('profile_picture');
            $path = str_replace(asset('storage/'), '', $path);

            if (!Storage::disk('public')->exists($path)) {
                return response()->json(['message' => 'Profile picture file not found'], 404);
            }

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
            'profile_picture' => 'nullable|image|mimes:jpg,jpeg,png|max:3072'
        ]);

        $user = Auth::user();

        try {
            $uploaded = $user->uploadFile($request->file('profile_picture'), 'profile_picture');

            if (!$uploaded) {
                throw new \Exception('Failed to upload profile picture');
            }

            return response()->json([
                'success' => true,
                'profile_picture_url' => $user->getProfilePictureUrl(),
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
    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        $doctor = $user->doctor;

        if (!$doctor) {
            return response()->json([
                'error' => 'Doctor profile not found',
                'message' => 'User is not associated with a doctor profile'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'phone_number' => 'sometimes|string|max:20',
            'address' => 'sometimes|string',
            'bio' => 'sometimes|string|max:1000',
            'profile_picture' => 'sometimes|image|mimes:jpg,jpeg,png|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::transaction(function () use ($user, $doctor, $request, $validator) {
                // Update phone number if provided
                if ($request->has('phone_number')) {
                    $user->phone = $request->phone_number;
                    $user->save();
                }

                // Update address if provided (assuming address is stored in clinic)
                if ($request->has('address') && $doctor->clinic) {
                    $user->address = $request->address;
                    $user->save();
                }

                // Update bio if provided
                if ($request->has('bio')) {
                    $doctor->bio = $request->bio;
                    $doctor->save();
                }

                // Handle profile picture upload if provided
                if ($request->hasFile('profile_picture')) {
                    $uploaded = $user->uploadFile($request->file('profile_picture'), 'profile_picture');
                    if (!$uploaded) {
                        throw new \Exception('Failed to upload profile picture');
                    }
                }
            });

            // Refresh the models to get updated data
            $user->refresh();
            $doctor->refresh();

            // Format working days
            $schedule = $doctor->schedules->map(function ($schedule) {
                return [
                    'day' => ucfirst($schedule->day),

                ];
            });

            return response()->json([
                'message' => 'Profile updated successfully',
                'personal_information' => [
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'phone_number' => $user->phone,
                    'email' => $user->email,
                    'gender' => $user->gender ?? 'Not specified',
                    'address' => $user->address ?? 'Not specified',
                    'specialty' => $doctor->specialty,
                    'consultation_fee' => number_format($doctor->consultation_fee, 0),
                    'start_working_date' => $doctor->experience_start_date
                        ? Carbon::parse($doctor->experience_start_date)->format('Y-m-d')
                        : 'Not specified',
                    'experience_years' => $doctor->getExperienceYearsAttribute(),
                    'rating' => round($doctor->rating, 1),
                    'rating_count' => $doctor->reviews->count(),
                    'bio' => $doctor->bio ?? 'No bio available',
                    'profile_picture_url' => $user->getProfilePictureUrl(),
                    'clinic' => $doctor->clinic->name,
                    'working_days' => $schedule
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update profile',
                'message' => $e->getMessage()
            ], 500);
        }
    }




    public function getSchedule()
    {
        $doctor = Auth::user()->doctor;

        if (!$doctor) {
            return response()->json(['message' => 'Doctor profile not found'], 404);
        }

        $schedules = DoctorSchedule::where('doctor_id', $doctor->id)
            ->get(['day', 'start_time', 'end_time']);

        return response()->json($schedules);
    }


    /* show
    public function getAppointments(Request $request)
    {
        $doctor = Auth::user()->doctor;

        if (!$doctor) {
            return response()->json(['message' => 'Doctor profile not found'], 404);
        }

        $status = $request->query('status', 'upcoming');

        $query = Appointment::with(['patient.user', 'clinic'])
            ->where('doctor_id', $doctor->id);

        if ($status === 'upcoming') {
            $query->where('appointment_date', '>=', now())
                ->where('status', 'confirmed');
        } elseif ($status === 'past') {
            $query->where('appointment_date', '<', now());
        } elseif ($status === 'cancelled') {
            $query->where('status', 'cancelled');
        }

        $appointments = $query->orderBy('appointment_date', 'asc')
            ->paginate(10);

        return response()->json($appointments);
    }
*/




    public function getAppointments(Request $request)
    {
        $doctor = Auth::user()->doctor;

        if (!$doctor) {
            return response()->json(['message' => 'Doctor profile not found'], 404);
        }

        $type = $request->query('type', 'upcoming');
        $perPage = $request->query('per_page', 8);

        date_default_timezone_set('Asia/Damascus');
        $nowLocal = Carbon::now('Asia/Damascus');

        $validator = Validator::make($request->all(), [
            'date' => ['sometimes', 'regex:/^\d{4}-\d{1,2}-\d{1,2}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        $query = $doctor->appointments()
            ->with([
                'patient.user:id,first_name,last_name,profile_picture',
                'patient:id,user_id,phone_number',
                'clinic:id,name',
                'payments' => function ($query) {
                    $query->whereIn('status', ['completed', 'paid']);
                }
            ])
            ->orderBy('appointment_date', 'asc');

        $hasDateFilter = false;
        $selectedDate = null;

        if ($request->has('date')) {
            $dateInput = $validated['date'];
            $hasDateFilter = true;

            // التحقق من صحة التاريخ باستخدام Carbon
            try {
                $date = Carbon::parse($dateInput);
                $selectedDate = $date->format('Y-m-d');

                // الفلترة بناء على التاريخ الكامل (اليوم+الشهر+السنة)
                $query->whereDate('appointment_date', $selectedDate);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Invalid date format',
                    'errors' => ['date' => ['Date must be in a valid format (e.g., 2025-08-05 or 2025-8-5)']]
                ], 422);
            }
        }

        if ($type === 'upcoming') {
            $upcomingAppointments = $query->where('status', 'confirmed')
                ->where('appointment_date', '>=', $nowLocal)
                ->paginate($perPage);

            if ($hasDateFilter && $upcomingAppointments->isEmpty()) {
                return response()->json([
                    'message' => 'No upcoming appointments found for ' . Carbon::parse($selectedDate)->format('F j, Y'),
                    'data' => []
                ], 404);
            }

            $transformedAppointments = $upcomingAppointments->through(function ($appointment) {
                $localTime = Carbon::parse($appointment->appointment_date)
                    ->setTimezone('Asia/Damascus');

                $profilePictureUrl = $appointment->patient->user->getFileUrl('profile_picture');

                return [
                    'appointment_id' => $appointment->id,
                    'patient_id' => $appointment->patient_id,
                    'patient_user_id' => $appointment->patient->user_id,
                    'first_name' => $appointment->patient->user->first_name,
                    'last_name' => $appointment->patient->user->last_name,
                    'phone_number' => $appointment->patient->phone_number,
                    'time' => $localTime->format('h:i A'),
                    'date' => $localTime->format('Y-m-d'),
                    'image' => $profilePictureUrl,
                    'clinic_name' => $appointment->clinic->name,
                    'status' => 'upcoming',
                    'price' => $appointment->price,
                    'reason' => $appointment->reason
                ];
            });

            return response()->json([
                'data' => $transformedAppointments->items(),
                'meta' => [
                    'current_page' => $upcomingAppointments->currentPage(),
                    'last_page' => $upcomingAppointments->lastPage(),
                    'per_page' => $upcomingAppointments->perPage(),
                    'total' => $upcomingAppointments->total(),
                ],
            ]);
        } else if ($type === 'completed') {
            $completedAppointments = $query->where(function ($q) use ($nowLocal) {
                $q->where('status', 'completed')
                    ->orWhere(function ($query) use ($nowLocal) {
                        $query->where('status', 'confirmed')
                            ->where('appointment_date', '<', $nowLocal);
                    });
            })
                ->paginate($perPage);

            if ($hasDateFilter && $completedAppointments->isEmpty()) {
                return response()->json([
                    'message' => 'No completed appointments found for ' . Carbon::parse($selectedDate)->format('F j, Y'),
                    'data' => []
                ], 404);
            }

            $transformedAppointments = $completedAppointments->through(function ($appointment) use ($nowLocal) {
                if (
                    $appointment->status === 'confirmed' &&
                    $appointment->appointment_date < $nowLocal
                ) {
                    $appointment->update(['status' => 'completed']);
                }

                $localTime = Carbon::parse($appointment->appointment_date)
                    ->setTimezone('Asia/Damascus');

                $profilePictureUrl = $appointment->patient->user->getFileUrl('profile_picture');

                return [
                    'appointment_id' => $appointment->id,
                    'patient_id' => $appointment->patient_id,
                    'first_name' => $appointment->patient->user->first_name,
                    'last_name' => $appointment->patient->user->last_name,
                    'phone_number' => $appointment->patient->phone_number,
                    'time' => $localTime->format('h:i A'),
                    'date' => $localTime->format('Y-m-d'),
                    'image' => $profilePictureUrl,
                    'clinic_name' => $appointment->clinic->name,
                    'status' => 'completed',
                    'price' => $appointment->price,
                    'reason' => $appointment->reason,
                    'completed_at' => $appointment->completed_at?->format('Y-m-d H:i:s')
                ];
            });

            return response()->json([
                'data' => $transformedAppointments->items(),
                'meta' => [
                    'current_page' => $completedAppointments->currentPage(),
                    'last_page' => $completedAppointments->lastPage(),
                    'per_page' => $completedAppointments->perPage(),
                    'total' => $completedAppointments->total(),
                ],
            ]);
        } else if ($type === 'absent') {
            $absentAppointments = $query->where('status', 'absent')
                ->paginate($perPage);

            if ($hasDateFilter && $absentAppointments->isEmpty()) {
                return response()->json([
                    'message' => 'No absent appointments found for ' . Carbon::parse($selectedDate)->format('F j, Y'),
                    'data' => []
                ], 404);
            }

            $transformedAppointments = $absentAppointments->through(function ($appointment) {
                $localTime = Carbon::parse($appointment->appointment_date)
                    ->setTimezone('Asia/Damascus');

                $profilePictureUrl = $appointment->patient->user->getFileUrl('profile_picture');

                return [
                    'appointment_id' => $appointment->id,
                    'patient_id' => $appointment->patient_id,
                    'first_name' => $appointment->patient->user->first_name,
                    'last_name' => $appointment->patient->user->last_name,
                    'phone_number' => $appointment->patient->phone_number,
                    'date' => $localTime->format('Y-m-d h:i A'),
                    'image' => $profilePictureUrl,
                    'clinic_name' => $appointment->clinic->name,
                    'status' => 'absent',
                    'price' => $appointment->price,
                    'reason' => $appointment->reason,
                    'cancelled_at' => $appointment->cancelled_at?->format('Y-m-d H:i:s')
                ];
            });

            return response()->json([
                'data' => $transformedAppointments->items(),
                'meta' => [
                    'current_page' => $absentAppointments->currentPage(),
                    'last_page' => $absentAppointments->lastPage(),
                    'per_page' => $absentAppointments->perPage(),
                    'total' => $absentAppointments->total(),
                ],
            ]);
        } else if ($type === 'cancelled') {
            $cancelledAppointments = $query->where('status', 'cancelled')
                ->paginate($perPage);

            if ($hasDateFilter && $cancelledAppointments->isEmpty()) {
                return response()->json([
                    'message' => 'No cancelled appointments found for ' . Carbon::parse($selectedDate)->format('F j, Y'),
                    'data' => []
                ], 404);
            }

            $transformedAppointments = $cancelledAppointments->through(function ($appointment) {
                $localTime = Carbon::parse($appointment->appointment_date)
                    ->setTimezone('Asia/Damascus');

                $profilePictureUrl = $appointment->patient->user->getFileUrl('profile_picture');

                return [
                    'appointment_id' => $appointment->id,
                    'patient_id' => $appointment->patient_id,
                    'first_name' => $appointment->patient->user->first_name,
                    'last_name' => $appointment->patient->user->last_name,
                    'phone_number' => $appointment->patient->phone_number,
                    'date' => $localTime->format('Y-m-d h:i A'),
                    'image' => $profilePictureUrl,
                    'clinic_name' => $appointment->clinic->name,
                    'status' => 'cancelled',
                    'price' => $appointment->price,
                    'reason' => $appointment->reason,
                    'cancellation_reason' => $appointment->cancellation_reason,
                    'cancelled_at' => $appointment->cancelled_at?->format('Y-m-d H:i:s'),
                    'is_emergency_cancellation' => $appointment->is_emergency_cancellation
                ];
            });

            return response()->json([
                'data' => $transformedAppointments->items(),
                'meta' => [
                    'current_page' => $cancelledAppointments->currentPage(),
                    'last_page' => $cancelledAppointments->lastPage(),
                    'per_page' => $cancelledAppointments->perPage(),
                    'total' => $cancelledAppointments->total(),
                ],
            ]);
        }

        return response()->json(['message' => 'Invalid appointment type'], 400);
    }




    public function getTimeSlots(Request $request)
    {
        $doctor = Auth::user()->doctor;

        if (!$doctor) {
            return response()->json(['message' => 'Doctor profile not found'], 404);
        }

        $date = $request->query('date', now()->format('Y-m-d'));

        $timeSlots = TimeSlot::where('doctor_id', $doctor->id)
            ->where('date', $date)
            ->orderBy('start_time')
            ->get();

        return response()->json($timeSlots);
    }


    public function getDoctorScheduleInfo()
    {
        $doctor = Auth::user()->doctor;

        if (!$doctor) {
            return response()->json(['message' => 'Doctor profile not found'], 404);
        }

        // Get doctor's basic information
        $doctorInfo = [
            'name' => $doctor->user->first_name . ' ' . $doctor->user->last_name,
            'specialty' => $doctor->specialty,
            'experience_years' => $doctor->experience_years ?? 2,
            'rating' => $doctor->rating ?? 0,
            'consultation_fee' => number_format($doctor->consultation_fee ?? 100.00, 2),
            'bio' => $doctor->bio ?? "Lorem ipsum is simply dummy text of the printing and typesetting industry...",
        ];

        // Get all schedules ordered by day of week
        $schedules = $doctor->schedules()
            ->orderByRaw("FIELD(day, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')")
            ->get();

        // Format working days and hours
        $workingDays = $schedules->map(function ($schedule) {
            return [
                'day' => ucfirst($schedule->day),
                'start_time' => Carbon::parse($schedule->start_time)->format('g:i A'),
                'end_time' => Carbon::parse($schedule->end_time)->format('g:i A')
            ];
        });

        // Create the formatted working hours string
        $daysAbbreviated = $workingDays->map(function ($day) {
            return substr($day['day'], 0, 3);
        })->implode('_');

        $firstSchedule = $workingDays->first();
        $workingHours = $daysAbbreviated . ' (' . $firstSchedule['start_time'] . ' _ ' . $firstSchedule['end_time'] . ')';

        return response()->json([
            'doctor' => $doctorInfo,
            'working_days' => $workingDays,
            'working_hours' => $workingHours,
            'consultation_fee' => $doctorInfo['consultation_fee']
        ]);
    }


    private function getFormattedAvailability(Doctor $doctor)
    {
        $schedules = $doctor->schedules()
            ->orderByRaw("FIELD(day, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')")
            ->get();

        return $schedules->map(function ($schedule) {
            return [
                'day' => ucfirst($schedule->day),
                'start_time' => Carbon::parse($schedule->start_time)->format('g:i A'),
                'end_time' => Carbon::parse($schedule->end_time)->format('g:i A')
            ];
        });
    }

    private function getAppointmentStats(Doctor $doctor)
    {
        return [
            'total' => $doctor->appointments()->count(),
            'upcoming' => $doctor->appointments()
                ->where('appointment_date', '>=', now())
                ->whereIn('status', ['confirmed', 'pending'])
                ->count(),
            'completed' => $doctor->appointments()
                ->where('appointment_date', '<', now())
                ->where('status', 'completed')
                ->count()
        ];
    }


    public function getTopDoctors()
    {
        $topDoctors = Doctor::topRated()->get()->map(function ($doctor) {
            return [
                'id' => $doctor->id,
                'first_name' => $doctor->user->first_name, // Assuming name is in User model
                'last_name' => $doctor->user->last_name,
                'specialty' => $doctor->specialty,

                'experience_years' => $doctor->experience_years,
                'rate' => (float)$doctor->rating,
                'profile_picture_url' => $doctor->user->getProfilePictureUrl(), // Assuming user has profile picture
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $topDoctors
        ]);
    }



    public function addPrescriptions(Request $request, $reportId)
    {
        $doctor = Auth::user()->doctor;
        if (!$doctor) {
            return response()->json(['message' => 'Doctor profile not found'], 404);
        }

        $validated = $request->validate([
            'prescriptions' => 'nullable|array',
            'prescriptions.*.medication' => 'nullable|string|max:255',
            'prescriptions.*.dosage' => 'nullable|string|max:100',
            'prescriptions.*.frequency' => 'nullable|string|max:100',
            'prescriptions.*.instructions' => 'nullable|string',
            'prescriptions.*.is_completed' => 'sometimes|boolean',
        ]);

        $report = Report::whereHas('appointment', function ($query) use ($doctor) {
            $query->where('doctor_id', $doctor->id);
        })
            ->where('id', $reportId)
            ->firstOrFail();

        return DB::transaction(function () use ($report, $validated) {
            $prescriptions = [];

            foreach ($validated['prescriptions'] as $prescriptionData) {
                $prescription = new Prescription([
                    'report_id' => $report->id,
                    'appointment_id' => $report->appointment_id,
                    'medication' => $prescriptionData['medication'],
                    'dosage' => $prescriptionData['dosage'],
                    'frequency' => $prescriptionData['frequency'] ?? null,
                    'instructions' => $prescriptionData['instructions'] ?? null,
                    'is_completed' => $prescriptionData['is_completed'] ?? false,
                    'issue_date' => now(),
                ]);
                $prescription->save();
                $prescriptions[] = $prescription;
            }

            return response()->json([
                'message' => 'Prescriptions added successfully',
                'prescriptions' => $prescriptions,
            ], 201);
        });
    }

    public function submitMedicalReport(Request $request, $appointmentId)
    {
        $doctor = Auth::user()->doctor;
        if (!$doctor) {
            return response()->json(['message' => 'Doctor profile not found'], 404);
        }

        $appointment = Appointment::with(['clinic', 'doctor.user'])->where('doctor_id', $doctor->id)
            ->where('id', $appointmentId)
            ->firstOrFail();

        // Validate the request
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string|min:20',
            'prescriptions' => 'nullable|array',
            'prescriptions.*.medication' => 'nullable|string|max:255',
            'prescriptions.*.dosage' => 'nullable|string|max:100',
            'prescriptions.*.frequency' => 'nullable|string|max:100',
            'prescriptions.*.instructions' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($appointment, $validated) {
            // Create the report
            $report = Report::create([
                'appointment_id' => $appointment->id,
                'title' => $validated['title'],
                'content' => $validated['content']
            ]);

            // Add prescriptions
            foreach ($validated['prescriptions'] as $prescriptionData) {
                Prescription::create([
                    'report_id' => $report->id,
                    'appointment_id' => $appointment->id,
                    'medication' => $prescriptionData['medication'],
                    'dosage' => $prescriptionData['dosage'],
                    'frequency' => $prescriptionData['frequency'],
                    'instructions' => $prescriptionData['instructions'],
                    'issue_date' => now()
                ]);
            }

            // Mark appointment as completed
            $appointment->update(['status' => 'completed']);

            // Format the response to exactly match your interface
            return response()->json([
                'success' => true,
                'message' => 'Medical report submitted successfully',
                'report' => [
                    'date' => $appointment->appointment_date->format('Y-m-d h:i A'), // "2025-08-01 09:00 AM" format
                    'clinic' => $appointment->clinic->name, // "Oncology"
                    'doctor' => $appointment->doctor->user->name, // "John White" or null if not set
                    'specialty' => $appointment->doctor->specialty, // "Cardiology"
                    'title' => $report->title, // "Annual Checkup Report"
                    'content' => $report->content, // Patient health details
                    'prescriptions' => $report->prescriptions->map(function ($prescription) {
                        return [
                            'medication' => $prescription->medication, // "Paracetamol"
                            'dosage' => $prescription->dosage, // "500mg"
                            'frequency' => $prescription->frequency, // "3x/day"
                            'instructions' => $prescription->instructions, // "After meal"
                        ];
                    })->toArray()
                ]
            ]);
        });
    }










    // In your AppointmentController or similar

    public function markAsAbsent(Appointment $appointment)
    {
        // Verify the authenticated user is the doctor for this appointment
        if (Auth::user()->doctor->id !== $appointment->doctor_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if appointment can be marked as absent
        if (!in_array($appointment->status, ['pending', 'confirmed'])) {
            return response()->json(['message' => 'Appointment cannot be marked as absent in its current state'], 400);
        }
        try {
            DB::transaction(function () use ($appointment) {
                $appointment->update([
                    'status' => 'absent',
                    'cancelled_at' => now()
                ]);

                $patient = $appointment->patient;
                $absentCount = $patient->appointments()->where('status', 'absent')->count();

                // Notify patient if they're approaching the limit
                if ($absentCount >= 2) {
                    $remaining = 3 - $absentCount;
                    $message = $remaining > 0
                        ? "You have missed $absentCount appointments. After $remaining more absences, your account will be blocked."
                        : "Your account has been blocked due to multiple missed appointments. Please contact the clinic center.";
                }
            });

            return response()->json(['message' => 'Appointment marked as absent successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to update appointment status'], 500);
        }
    }







    public function markAsCompleted(Appointment $appointment)
    {
        // Verify the authenticated user is the doctor for this appointment
        if (Auth::user()->doctor->id !== $appointment->doctor_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if appointment can be marked as completed
        if (!in_array($appointment->status, ['confirmed'])) {
            return response()->json([
                'message' => 'Only confirmed appointments can be marked as completed'
            ], 400);
        }

        try {
            DB::transaction(function () use ($appointment) {
                $appointment->update([
                    'status' => 'completed',
                    'completed_at' => now()
                ]);
            });

            return response()->json([
                'message' => 'Appointment marked as completed successfully',
                'appointment' => $appointment->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to complete appointment',
                'error' => $e->getMessage()
            ], 500);
        }
    }













    public function getDoctorSpecificPatients(Request $request)
    {
        try {
            $doctor = Auth::user()->doctor;

            if (!$doctor) {
                return response()->json(['message' => 'Doctor profile not found'], 404);
            }

            $patientIds = DB::table('appointments')
                ->select('patient_id')
                ->where('doctor_id', $doctor->id)
                ->groupBy('patient_id')
                ->pluck('patient_id');

            $patients = Patient::with(['user'])
                ->whereIn('id', $patientIds)
                ->paginate($request->per_page ?? 10);

            $latestAppointments = DB::table('appointments')
                ->select('patient_id', DB::raw('MAX(appointment_date) as last_visit_at'))
                ->where('doctor_id', $doctor->id)
                ->whereIn('patient_id', $patientIds)
                ->groupBy('patient_id')
                ->get()
                ->keyBy('patient_id');

            $formattedPatients = $patients->map(function ($patient) use ($latestAppointments) {
                $lastVisit = $latestAppointments->get($patient->id);

                return [
                    'patient_id' => $patient->id,
                    'patient_user_id' => $patient->user_id,
                    'patient_name' => $patient->user->first_name . ' ' . $patient->user->last_name,
                    'phone' => $patient->phone_number,
                    'last_visit_at' => $lastVisit && $lastVisit->last_visit_at ?
                        Carbon::parse($lastVisit->last_visit_at)->format('Y/m/d') : null,
                    'profile_picture_url' => $patient->user->getProfilePictureUrl()
                ];
            });

            return response()->json([
                'data' => $formattedPatients,
                'meta' => [
                    'current_page' => $patients->currentPage(),
                    'per_page' => $patients->perPage(),
                    'total' => $patients->total(),
                    'last_page' => $patients->lastPage()
                ]
            ]);
        } catch (\Exception $e) {
            logger('Error in getDoctorSpecificPatients: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to retrieve patients',
                'message' => $e->getMessage()
            ], 500);
        }
    }









    public function getPatientDocuments($patientId)
    {
        try {
            $doctor = Auth::user()->doctor;

            if (!$doctor) {
                return response()->json(['message' => 'Doctor profile not found'], 404);
            }

            // Verify the patient has had appointments with this doctor
            $hasAppointments = Appointment::where('doctor_id', $doctor->id)
                ->where('patient_id', $patientId)
                ->exists();

            if (!$hasAppointments) {
                return response()->json([
                    'message' => 'Patient not found or no appointments with this doctor'
                ], 404);
            }

            // Get the patient details
            $patient = Patient::with('user')->findOrFail($patientId);

            // Get year and month from request parameters or use current
            $year = request()->has('year') ? request('year') : Carbon::now()->year;
            $month = request()->has('month') ? request('month') : Carbon::now()->month;

            // Get all reports for this patient with this doctor filtered by year and month
            $reports = Report::select('reports.id', 'reports.title', 'appointments.appointment_date')
                ->join('appointments', 'appointments.id', '=', 'reports.appointment_id')
                ->where('appointments.doctor_id', $doctor->id)
                ->where('appointments.patient_id', $patientId)
                ->whereYear('appointments.appointment_date', $year)
                ->whereMonth('appointments.appointment_date', $month)
                ->orderBy('appointments.appointment_date', 'desc')
                ->get();

            // Check if reports are empty
            if ($reports->isEmpty()) {
                return response()->json([
                    'message' => 'No records found for the selected year and month',
                    'patient_name' => $patient->user->first_name . ' ' . $patient->user->last_name,
                    'selected_year' => (string)$year,
                    'selected_month' => (string)$month,
                    'data' => []
                ], 404);
            }

            // Format the reports data
            $formattedReports = $reports->map(function ($report) {
                $appointmentDate = Carbon::parse($report->appointment_date);

                return [
                    'id' => $report->id,
                    'date' => $appointmentDate->format('Y/m/d h:i A'),
                    'title' => $report->title,
                    'year' => (string)$appointmentDate->year,
                    'month' => (string)$appointmentDate->month
                ];
            });

            return response()->json([
                'patient_name' => $patient->user->first_name . ' ' . $patient->user->last_name,
                'selected_year' => (string)$year,
                'selected_month' => (string)$month,
                'data' => $formattedReports
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve patient documents',
                'message' => $e->getMessage()
            ], 500);
        }
    }
















    public function getPatientDetails($patientId)
    {
        $doctor = Auth::user()->doctor;

        if (!$doctor) {
            return response()->json(['message' => 'Doctor profile not found'], 404);
        }

        $patient = Patient::where('id', $patientId)
            ->whereHas('appointments', function ($query) use ($doctor) {
                $query->where('doctor_id', $doctor->id);
            })
            ->with(['user', 'appointments' => function ($query) use ($doctor) {
                $query->where('doctor_id', $doctor->id)
                    ->orderBy('appointment_date', 'desc')
                    ->limit(1);
            }])
            ->first();

        if (!$patient) {
            return response()->json([
                'message' => 'Patient not found or not associated with this doctor',
                'success' => false
            ], 404);
        }

        $age = null;
        if ($patient->date_of_birth) {
            try {
                $dob = date_create($patient->date_of_birth);
                $now = date_create();

                if ($dob && $now && $dob <= $now) {
                    $diff = date_diff($dob, $now);
                    $age = $diff->y;
                }
            } catch (\Exception $e) {
                $age = null;
            }
        }

        $lastVisit = $patient->appointments->first();

        return response()->json([
            'success' => true,
            'data' => [
                'first_name' => $patient->user->first_name,
                'last_name' => $patient->user->last_name,
                'phone' => $patient->phone_number,
                'profile_picture_url' => $patient->user->getProfilePictureUrl(),
                'address' => $patient->address ?? 'Not specified',
                'age' => $age ?? 'Not specified',
                'gender' => $patient->gender ?? 'Not specified',
                'blood_type' => $patient->blood_type ?? 'Not specified',
                'chronic_conditions' => $patient->chronic_conditions ?? 'not specified',
                'last_visit' => $lastVisit ? $lastVisit->appointment_date->format('Y/m/d') : 'Never',
            ]
        ]);
    }






















    public function getPatientReport($id)
    {
        try {
            $doctor = Auth::user()->doctor;

            if (!$doctor) {
                return response()->json(['message' => 'Doctor profile not found'], 404);
            }

            $type = request()->query('type', 'report');

            if ($type === 'appointment') {
                $appointment = Appointment::with(['report.prescriptions', 'patient.user', 'clinic'])
                    ->where('doctor_id', $doctor->id)
                    ->findOrFail($id);

                if (!$appointment->report) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No report found for this appointment'
                    ], 404);
                }

                $report = $appointment->report;
            } else if ($type === 'report') {
                $report = Report::with(['appointment.patient.user', 'appointment.clinic', 'prescriptions'])
                    ->whereHas('appointment', function ($query) use ($doctor) {
                        $query->where('doctor_id', $doctor->id);
                    })
                    ->findOrFail($id);

                $appointment = $report->appointment;
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid type parameter. Use "appointment" or "report"'
                ], 400);
            }

            // تنسيق الرد مع report map - التصحيح هنا
            return response()->json([
                'success' => true,
                'requested_type' => $type,
                'report_id' => $report->id,
                'appointment_id' => $appointment->id,
                'report' => [ // استخدام => بدل من :
                    'date' => Carbon::parse($appointment->appointment_date)->format('Y-n-j h:i A'),
                    'clinic' => $appointment->clinic->name,
                    'doctor' => $doctor->user->first_name . ' ' . $doctor->user->last_name,
                    'specialty' => $doctor->specialty,
                    'title' => $report->title ?? 'Medical Report',
                    'content' => $report->content,
                    'prescriptions' => $report->prescriptions->map(function ($prescription) {
                        return [
                            'medication' => $prescription->medication,
                            'dosage' => $prescription->dosage,
                            'frequency' => $prescription->frequency,
                            'instructions' => $prescription->instructions,
                            'is_completed' => (bool)$prescription->is_completed
                        ];
                    })->toArray(),
                    'patient_name' => $appointment->patient->user->first_name . ' ' .
                        $appointment->patient->user->last_name,
                    'patient_age' => $appointment->patient->age ?? null,
                    'patient_gender' => $appointment->patient->gender ?? null
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Report or appointment not found or you do not have access to it'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve report',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function updateActivityStatus()
    {
        try {
            $doctor = Auth::user()->doctor;
            if (!$doctor) {
                return response()->json(['message' => 'Doctor profile not found'], 404);
            }
            $doctor->is_active = !$doctor->is_active;
            $doctor->save();

            return response()->json([
                'succuss' => true,
                'message' => $doctor->is_active ? 'You are active now' : 'You are inactive now',
                'is_active' => $doctor->is_active ? true : false,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to update Status',
                'message' => $e->getMessage()
            ], 500);
        }
    }





public function emergencyCancelAppointments(Request $request)
{
    $doctor = Auth::user()->doctor;

    if (!$doctor) {
        return response()->json(['message' => 'Doctor profile not found'], 404);
    }

    $validated = $request->validate([
        'appointment_ids' => 'required|array',
        'appointment_ids.*' => 'exists:appointments,id',
        'reason' => 'required|string|max:500',
        'is_emergency' => 'required|boolean'
    ]);

    date_default_timezone_set('Asia/Damascus');
    $nowLocal = Carbon::now('Asia/Damascus');

    return DB::transaction(function () use ($doctor, $validated, $nowLocal) {
        $results = [
            'cancelled' => [],
            'already_cancelled' => [],
            'not_eligible' => []
        ];

        foreach ($validated['appointment_ids'] as $appointmentId) {
            try {
                // ADD timeSlot to the eager loading
                $appointment = Appointment::with(['patient.user', 'clinic', 'payments', 'timeSlot'])->find($appointmentId);

                if (!$appointment) {
                    $results['not_eligible'][] = [
                        'id' => $appointmentId,
                        'reason' => 'Appointment not found'
                    ];
                    continue;
                }

                if ($appointment->doctor_id != $doctor->id) {
                    $results['not_eligible'][] = [
                        'id' => $appointmentId,
                        'reason' => 'Does not belong to this doctor'
                    ];
                    continue;
                }

                if ($appointment->status === 'cancelled') {
                    $results['already_cancelled'][] = [
                        'id' => $appointment->id,
                        'patient_name' => $appointment->patient->user->name,
                        'original_date' => $appointment->appointment_date->format('Y-m-d h:i A'),
                        'cancelled_at' => $appointment->cancelled_at->format('Y-m-d h:i A')
                    ];
                    continue;
                }

                if ($appointment->status !== 'confirmed') {
                    $results['not_eligible'][] = [
                        'id' => $appointment->id,
                        'reason' => 'Cannot cancel ' . $appointment->status . ' appointment'
                    ];
                    continue;
                }

                if ($appointment->appointment_date < $nowLocal) {
                    $results['not_eligible'][] = [
                        'id' => $appointment->id,
                        'reason' => 'Cannot cancel past appointment'
                    ];
                    continue;
                }

                // Check for time slot and free it
                $timeSlot = TimeSlot::where('id', $appointment->time_slot_id)
                    ->lockForUpdate()
                    ->first();

                if ($timeSlot) {
                    $timeSlot->update(['is_booked' => false]);
                }

                // Proceed with cancellation
                $appointment->update([
                    'status' => 'cancelled',
                    'cancelled_at' => $nowLocal,
                    'cancellation_reason' => $validated['reason'],
                    'is_emergency_cancellation' => $validated['is_emergency']
                ]);

                // Process refund if payment exists
                $payment = Payment::where('appointment_id', $appointment->id)
                    ->where('method', 'wallet')
                    ->where('status', 'paid')
                    ->first();

                $refundAmount = 0;
                $discountApplied = 0;
                $originalAmount = 0;

                if ($payment) {
                    $refundResult = $this->processWalletRefund($appointment, $payment);
                    $refundAmount = $refundResult['refund_amount'];
                    $discountApplied = $refundResult['discount_applied'];
                    $originalAmount = $refundResult['original_amount'];

                    $appointment->patient->refresh();

                    \Log::info("Refund processed for emergency cancelled appointment {$appointment->id}", [
                        'patient_id' => $appointment->patient->id,
                        'refund_amount' => $refundAmount,
                        'discount_applied' => $discountApplied,
                        'patient_new_balance' => $appointment->patient->wallet_balance
                    ]);
                }

                $appointment->patient->user->notify(new EmergencyAppointmentCancellationNotification(
                    $appointment,
                    $doctor->user->name,
                    $validated['reason'],
                    $validated['is_emergency']
                ));

                $results['cancelled'][] = [
                    'id' => $appointment->id,
                    'patient_name' => $appointment->patient->user->name,
                    'original_date' => $appointment->appointment_date->format('Y-m-d h:i A'),
                    'new_status' => 'cancelled',
                    'refund_processed' => $payment ? true : false,
                    'refund_amount' => $refundAmount,
                    'discount_applied' => $discountApplied
                ];

            } catch (\Exception $e) {
                $results['not_eligible'][] = [
                    'id' => $appointmentId,
                    'reason' => 'Error: ' . $e->getMessage()
                ];
            }
        }

        return response()->json([
            'message' => 'Cancellation processed',
            'results' => $results,
            'summary' => [
                'successfully_cancelled' => count($results['cancelled']),
                'previously_cancelled' => count($results['already_cancelled']),
                'not_eligible' => count($results['not_eligible'])
            ]
        ]);
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
