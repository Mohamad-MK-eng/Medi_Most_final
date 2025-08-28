<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\MedicalCenterWallet;
use App\Models\MedicalCenterWalletTransaction;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Salary;
use App\Models\SalarySetting;
use App\Models\Secretary;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\WalletTransaction;
use Auth;
use Carbon\Carbon;
use DB;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Storage;
use Str;
use Validator;

class AdminController extends Controller
{
    public function authUser(){
        return Auth::user();
    }

 protected $profilePictureConfig = [
        'directory' => 'admin_profile_pictures',
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif'],
        'max_size' => 3072, // 3MB
    ];

    // not tested yet until another time
    public function getClinicIncomeReport(Request $request)
    {
        $validated = $request->validate([
            'from' => 'sometimes|date',
            'to' => 'sometimes|date|after_or_equal:from'
        ]);

        $query = Payment::query();

        if ($request->has('from')) {
            $query->where('created_at', '>=', $validated['from']);
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $validated['to']);
        }

        $report = $query->selectRaw('
            method,
            SUM(amount) as total_amount,
            COUNT(*) as transaction_no
        ')
            ->groupBy('method')
            ->get();

        return response()->json($report);
    }

    // optional and not tested yet :


    // في كل شي بخص الصور عملتو image_path



    public function getWalletTransactions(Request $request)
    {
        $transactions = WalletTransaction::with(['patient.user', 'admin'])
            ->when($request->has('type'), function ($q) use ($request) {
                return $q->where('type', $request->type);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($transactions);
    }




    public function createClinic(Request $request)
    {
        // هون ما بعرف اذا بدك تحط انو يتأكد انه ادمن


        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'image_path' => 'nullable|image|mimes:jpg,jpeg,png|max:2048'
        ]);

        $clinic = Clinic::create($validated);

        if ($request->hasFile('image_path')) {
            $clinic->uploadIcon($request->file('image_path'));
        }

        return response()->json($clinic, 201);
    }










    public function updateclinic(Request $request, $id)
    {
        $clinic = Clinic::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'location' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|nullable',
            'opening_time' => 'sometimes|date_format:H:i',
            'closing_time' => 'sometimes|date_format:H:i|after:opening_time',
            'icon' => 'sometimes|image|mimes:jpg,jpeg,png|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Handle icon update if present
        if ($request->hasFile('icon')) {
            try {
                $clinic->uploadIcon($request->file('icon'));
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Failed to upload clinic icon',
                    'message' => $e->getMessage()
                ], 500);
            }
        }

        $clinic->update($validator->validated());

        return response()->json([
            'clinic' => $clinic,
            'icon_url' => $clinic->getIconUrl(),
            'message' => 'Clinic updated successfully'
        ]);
    }














    public function uploadClinicIcon(Request $request, $id)
    {


        $clinic = Clinic::find($id);
        if (!$clinic) {
            return response()->json(['error' => 'Clinic not found'], 404);
        }


        $validator = Validator::make($request->all(), [
            'icon' => 'required|image|mimes:jpg,jpeg,png|max:2048' // Changed from image_path to icon
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ]);
        }

        $clinic = Clinic::findOrFail($id);

        try {
            if ($clinic->uploadIcon($request->file('icon'))) {
                return response()->json([
                    'success' => true,
                    'image_url' => $clinic->getIconUrl(),
                    'message' => 'Clinic image updated successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to save clinic image'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Image upload failed: ' . $e->getMessage()
            ]);
        }
    }






//     public function createDoctor(Request $request)
//     {
//         // Validate the request
//         $validator = Validator::make($request->all(), [
//             // User data
//             'first_name' => 'required|string|max:255',
//             'last_name' => 'required|string|max:255',
//             'email' => 'required|email|unique:users,email',
//             'phone_number' => 'required|string|max:20',

//             // Doctor data
//             'specialty' => 'required|string|max:255',
//             'bio' => 'nullable|string',
//             'consultation_fee' => 'required|numeric|min:0',
//             'experience_years' => 'required|integer|min:0',
//             'clinic_id' => 'required|exists:clinics,id',

//             // Schedule data
//             'schedules' => 'required|array|min:1',
//             'schedules.*.day' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
//             'schedules.*.start_time' => 'required|date_format:H:i',
//             'schedules.*.end_time' => 'required|date_format:H:i|after:schedules.*.start_time',

//             // Time slot configuration
//             'slot_duration' => 'required|integer|in:30,60',
//             'generate_slots_for_days' => 'required|integer|min:1|max:365',
//         ]);

//         if ($validator->fails()) {
//             return response()->json(['errors' => $validator->errors()], 422);
//         }

//         try {
//             // Start transaction
//             return DB::transaction(function () use ($request) {
//                 // Get the doctor role
//                 $doctorRole = Role::where('name', 'doctor')->first();
//                 if (!$doctorRole) {
//                     throw new \Exception('Doctor role not found in database');
//                 }

//                 // Get a secretary for salary assignment
//                 $secretary = Secretary::first();
//                 if (!$secretary) {
//                     throw new \Exception('No secretary found in database');
//                 }

//                 // Verify clinic exists
//                 $clinic = Clinic::find($request->clinic_id);
//                 if (!$clinic) {
//                     throw new \Exception('Specified clinic not found');
//                 }

//                 // Create salary settings and salary record
//                 $salarySettings = SalarySetting::firstOrCreate([]);
//                 $salary = Salary::create([
//                     'secretary_id' => $secretary->id,
//                     'base_amount' => 100,
//                     'bonus_amount' => 0.5,
//                     'total_amount' => 100.5,
//                     'salary_setting_id' => $salarySettings->id,
//                     'status' => 'pending'
//                 ]);

//                 // Create user account
//                 $user = User::create([
//                     'first_name' => $request->first_name,
//                     'last_name' => $request->last_name,
//                     'email' => $request->email,
//                     'password' => Hash::make('temporary_password'),
//                     'role_id' => $doctorRole->id,
//                     'gender'=>$request->gender,
//                 ]);

//                 // Create doctor profile
//                 $doctor = Doctor::create([
//                     'user_id' => $user->id,
//                     'clinic_id' => $request->clinic_id,
//                     'specialty' => $request->specialty,
//                     'bio' => $request->bio ?? "Lorem ipsum is simply dummy text of the printing and typesetting industry...",
//                     'consultation_fee' => $request->consultation_fee,
//                     'experience_years' => $request->experience_years,
//                     'salary_id' => $salary->id,
//                     'workdays' => collect($request->schedules)->pluck('day')->toArray()
//                 ]);

//                 // Create schedules
//                 foreach ($request->schedules as $scheduleData) {
//                     DoctorSchedule::create([
//                         'doctor_id' => $doctor->id,
//                         'day' => $scheduleData['day'],
//                         'start_time' => $scheduleData['start_time'],
//                         'end_time' => $scheduleData['end_time']
//                     ]);
//                 }
//  $this->generateTimeSlotsForDoctor(
//                 $doctor,
//                 $request->generate_slots_for_days,
//                 $request->slot_duration
//             );
//                 // Generate time slots
//               /*   $timeSlots = [];
//                 $slotDuration = $request->slot_duration;

//                 for ($i = 1; $i <= $request->generate_slots_for_days; $i++) {
//                     $date = now()->addDays($i)->format('Y-m-d');
//                     $dayOfWeek = strtolower(Carbon::parse($date)->englishDayOfWeek);

//                     $schedule = $doctor->schedules()->where('day', $dayOfWeek)->first();
//                     if (!$schedule) continue;

//                     $start = Carbon::parse($schedule->start_time);
//                     $end = Carbon::parse($schedule->end_time);

//                     $current = $start->copy();
//                     while ($current->addMinutes($slotDuration)->lte($end)) {
//                         $slotStart = $current->copy()->subMinutes($slotDuration);
//                         $slotEnd = $current->copy();

//                         $timeSlots[] = [
//                             'doctor_id' => $doctor->id,
//                             'date' => $date,
//                             'start_time' => $slotStart->format('H:i:s'),
//                             'end_time' => $slotEnd->format('H:i:s'),
//                             'is_booked' => false,
//                             'created_at' => now(),
//                             'updated_at' => now()
//                         ];
//                     }
//                 } */

//                 // Insert time slots if any were generated
//                 if (!empty($timeSlots)) {
//                     TimeSlot::insert($timeSlots);
//                 }

//                 // Refresh the doctor model with relationships
//                 $doctor = $doctor->fresh(['user', 'clinic', 'schedules']);

//                 return response()->json([
//                     'message' => 'Doctor created successfully',
//                     'doctor' => $doctor,
//                     'login_credentials' => [
//                         'email' => $user->email,
//                     ]
//                 ], 201);
//             });
//         } catch (\Exception $e) {
//             return response()->json([
//                 'message' => 'Failed to create doctor',
//                 'error' => $e->getMessage()
//             ], 500);
//         }
//     }


public function createDoctor(Request $request)
{
    // Validate the request
    $validator = Validator::make($request->all(), [
        // User data
        'first_name' => 'required|string|max:255',
        'last_name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'phone_number' => 'required|string|max:20',
        'gender' => 'required|in:male,female',

        // Doctor data
        'specialty' => 'required|string|max:255',
        'bio' => 'nullable|string',
        'consultation_fee' => 'required|numeric|min:0',
        'experience_years' => 'required|integer|min:0',
        'clinic_id' => 'required|exists:clinics,id',

        // Schedule data
        'schedules' => 'required|array|min:1',
        'schedules.*.day' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
        'schedules.*.start_time' => 'required|date_format:H:i',
        'schedules.*.end_time' => 'required|date_format:H:i|after:schedules.*.start_time',

        // Time slot configuration
        'slot_duration' => 'required|integer|in:30,60',
        'generate_slots_for_days' => 'required|integer|min:1|max:365',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    try {
        // Start transaction
        return DB::transaction(function () use ($request) {
            // Get the doctor role
            $doctorRole = Role::where('name', 'doctor')->first();
            if (!$doctorRole) {
                throw new \Exception('Doctor role not found in database');
            }

            // Get a secretary for salary assignment
            $secretary = Secretary::first();
            if (!$secretary) {
                throw new \Exception('No secretary found in database');
            }

            // Verify clinic exists
            $clinic = Clinic::find($request->clinic_id);
            if (!$clinic) {
                throw new \Exception('Specified clinic not found');
            }

            // Create salary settings and salary record
            $salarySettings = SalarySetting::firstOrCreate([]);
            $salary = Salary::create([
                'secretary_id' => $secretary->id,
                'base_amount' => 100,
                'bonus_amount' => 0.5,
                'total_amount' => 100.5,
                'salary_setting_id' => $salarySettings->id,
                'status' => 'pending'
            ]);

            // Create user account - تحويل phone_number إلى phone
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password' => Hash::make('temporary_password'),
                'role_id' => $doctorRole->id,
                'phone' => $request->phone_number, // تحويل phone_number إلى phone
                'gender' => $request->gender,
            ]);

            // Create doctor profile
            $doctor = Doctor::create([
                'user_id' => $user->id,
                'clinic_id' => $request->clinic_id,
                'specialty' => $request->specialty,
                'bio' => $request->bio ?? "Lorem ipsum is simply dummy text of the printing and typesetting industry...",
                'consultation_fee' => $request->consultation_fee,
                'experience_years' => $request->experience_years,
                'salary_id' => $salary->id,
                'workdays' => collect($request->schedules)->pluck('day')->toArray()
            ]);

            // Create schedules
            foreach ($request->schedules as $scheduleData) {
                DoctorSchedule::create([
                    'doctor_id' => $doctor->id,
                    'day' => $scheduleData['day'],
                    'start_time' => $scheduleData['start_time'],
                    'end_time' => $scheduleData['end_time']
                ]);
            }

            // Generate time slots
            $this->generateTimeSlotsForDoctor(
                $doctor,
                $request->generate_slots_for_days,
                $request->slot_duration
            );

            // Refresh the doctor model with relationships
            $doctor = $doctor->fresh(['user', 'clinic', 'schedules']);

            return response()->json([
                'message' => 'Doctor created successfully',
                'doctor' => $doctor,
                'login_credentials' => [
                    'email' => $user->email,
                ]
            ], 201);
        });
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Failed to create doctor',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function editDoctor(Request $request, $doctorId){
    // البحث عن الطبيب أولاً
    $doctor = Doctor::with('user')->find($doctorId);
    if (!$doctor) {
        return response()->json(['message' => 'Doctor not found'], 404);
    }

    // طباعة البيانات المستلمة للتشخيص
    \Log::info('Edit Doctor Request Data:', $request->all());

    $validator = Validator::make($request->all(), [
        // User data
        'first_name' => 'sometimes|string|max:255',
        'last_name' => 'sometimes|string|max:255',
        'email' => [
            'sometimes',
            'email',
            'unique:users,email,' . $doctor->user_id
        ],
        'phone_number' => 'sometimes|string|max:20',
        'gender' => 'sometimes|in:male,female',

        // Doctor data
        'specialty' => 'sometimes|string|max:255',
        'bio' => 'nullable|string',
        'consultation_fee' => 'sometimes|numeric|min:0',
        'experience_years' => 'sometimes|integer|min:0',
        'clinic_id' => 'sometimes|exists:clinics,id',

        // جدول المواعيد
        'schedules' => 'sometimes|array|min:1',
        'schedules.*.day' => 'required_with:schedules|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
        'schedules.*.start_time' => 'required_with:schedules|date_format:H:i',
        'schedules.*.end_time' => 'required_with:schedules|date_format:H:i',

        // إعدادات المواعيد
        'slot_duration' => 'sometimes|integer|in:30,60',
        'generate_slots_for_days' => 'sometimes|integer|min:1|max:365',

        // Status control
        'is_active' => 'sometimes|boolean'
    ]);

    if ($validator->fails()) {
        \Log::error('Validation failed for edit doctor:', $validator->errors()->toArray());
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }

    $validated = $validator->validated();

    try {
        return DB::transaction(function () use ($request, $doctor, $validated) {
            // تحديث بيانات المستخدم
            if (isset($validated['first_name']) ||
                isset($validated['last_name']) ||
                isset($validated['email']) ||
                isset($validated['phone_number']) ||
                isset($validated['gender'])) {

                $userData = [];
                if (isset($validated['first_name'])) $userData['first_name'] = $validated['first_name'];
                if (isset($validated['last_name'])) $userData['last_name'] = $validated['last_name'];
                if (isset($validated['email'])) $userData['email'] = $validated['email'];
                if (isset($validated['phone_number'])) $userData['phone'] = $validated['phone_number']; // تحويل إلى phone
                if (isset($validated['gender'])) $userData['gender'] = $validated['gender'];

                \Log::info('Updating user data:', $userData);
                $doctor->user->update($userData);
            }

            // تحديث بيانات الطبيب
            $doctorData = collect($validated)
                ->except(['first_name', 'last_name', 'email', 'phone_number', 'gender', 'schedules'])
                ->toArray();

            // تحديث workdays إذا تم تحديث schedules
            if (isset($validated['schedules'])) {
                $doctorData['workdays'] = collect($validated['schedules'])->pluck('day')->toArray();
            }

            if (!empty($doctorData)) {
                \Log::info('Updating doctor data:', $doctorData);
                $doctor->update($doctorData);
            }

            // تحديث جدول المواعيد
            if (isset($validated['schedules'])) {
                \Log::info('Updating schedules:', $validated['schedules']);

                // حذف الجداول القديمة
                $doctor->schedules()->delete();

                // إدخال الجداول الجديدة
                foreach ($validated['schedules'] as $schedule) {
                    $doctor->schedules()->create([
                        'day' => $schedule['day'],
                        'start_time' => $schedule['start_time'],
                        'end_time' => $schedule['end_time'],
                    ]);
                }

                // حذف المواعيد الزمنية المستقبلية القديمة
                $doctor->timeSlots()->where('date', '>=', now()->format('Y-m-d'))->delete();

                // إعادة توليد المواعيد الزمنية
                $slotDuration = $validated['slot_duration'] ?? 60;
                $generateDays = $validated['generate_slots_for_days'] ?? 30;

                $this->generateTimeSlotsForDoctor($doctor, $generateDays, $slotDuration);
            }

            return response()->json([
                'message' => 'Doctor updated successfully',
                'doctor' => $doctor->fresh()->load(['user', 'clinic', 'schedules'])
            ]);
        });
    } catch (\Exception $e) {
        \Log::error('Error updating doctor: ' . $e->getMessage());
        return response()->json([
            'message' => 'Failed to update doctor',
            'error' => $e->getMessage()
        ], 500);
    }
}

// تحديث دالة generateTimeSlotsForDoctor
public function generateTimeSlotsForDoctor(Doctor $doctor, $daysToGenerate, $slotDuration){
    $timeSlots = [];
    $now = Carbon::now();

    \Log::info("Generating slots for doctor {$doctor->id} for {$daysToGenerate} days with {$slotDuration} minute slots");

    for ($i = 0; $i < $daysToGenerate; $i++) {
        $date = $now->copy()->addDays($i)->startOfDay();
        $dayName = strtolower($date->englishDayOfWeek);

        $schedule = $doctor->schedules()->where('day', $dayName)->first();
        if (!$schedule) {
            \Log::info("No schedule for {$dayName} on {$date->format('Y-m-d')}");
            continue;
        }

        $dateStr = $date->format('Y-m-d');
        $start = Carbon::parse($date->format('Y-m-d') . ' ' . $schedule->start_time);
        $end = Carbon::parse($date->format('Y-m-d') . ' ' . $schedule->end_time);

        // للأيام المستقبلية، ابدأ من وقت البداية المحدد
        // لليوم الحالي، ابدأ من الوقت الحالي إذا كان ضمن ساعات العمل
        if ($date->isToday() && $now->between($start, $end)) {
            // قرب الوقت الحالي إلى أقرب slot
            $minutesToAdd = $slotDuration - ($now->minute % $slotDuration);
            $start = $now->copy()->addMinutes($minutesToAdd)->startOfMinute();
        }

        // Generate slots
        $current = $start->copy();
        $slotsCount = 0;

        while ($current->copy()->addMinutes($slotDuration)->lte($end)) {
            $slotStart = $current->copy();
            $slotEnd = $current->copy()->addMinutes($slotDuration);

            // تأكد من أن الموعد في المستقبل
            if ($slotEnd->lt($now)) {
                $current->addMinutes($slotDuration);
                continue;
            }

            $timeSlots[] = [
                'doctor_id' => $doctor->id,
                'date' => $dateStr,
                'start_time' => $slotStart->format('H:i:s'),
                'end_time' => $slotEnd->format('H:i:s'),
                'is_booked' => false,
                'created_at' => now(),
                'updated_at' => now()
            ];

            $slotsCount++;
            $current->addMinutes($slotDuration);
        }

        \Log::info("Generated {$slotsCount} slots for {$dateStr} ({$dayName})");
    }

    if (!empty($timeSlots)) {
        try {
            \Log::info("Inserting ".count($timeSlots)." slots for doctor {$doctor->id}");
            TimeSlot::insert($timeSlots);
            return count($timeSlots);
        } catch (\Exception $e) {
            \Log::error("Failed to insert slots: ".$e->getMessage());
            return 0;
        }
    }

    \Log::warning("No slots generated for doctor {$doctor->id}");
    return 0;
}


//     public function generateTimeSlotsForDoctor(Doctor $doctor, $daysToGenerate, $slotDuration)
// {
//     $timeSlots = [];
//     $now = Carbon::now();
//     $startDate = $now->copy()->startOfDay();

//     \Log::info("Generating slots for doctor {$doctor->id} for {$daysToGenerate} days");

//     for ($i = 0; $i < $daysToGenerate; $i++) {
//         $date = $startDate->copy()->addDays($i);
//         $dayName = strtolower($date->englishDayOfWeek);

//         $schedule = $doctor->schedules()->where('day', $dayName)->first();
//         if (!$schedule) {
//             \Log::info("No schedule for {$dayName} on {$date->format('Y-m-d')}");
//             continue;
//         }

//         $dateStr = $date->format('Y-m-d');
//         $start = Carbon::parse($schedule->start_time);
//         $end = Carbon::parse($schedule->end_time);

//         // Adjust for today: only generate future slots
//         if ($date->isToday()) {
//             $currentTime = $now->copy();
//             // Only adjust if current time is within working hours
//             if ($currentTime->between($start, $end)) {
//                 $start = $currentTime;
//             }
//         }

//         // Generate slots
//         $current = $start->copy();
//         $slotsCount = 0;

//         while ($current->addMinutes($slotDuration)->lte($end)) {
//     $slotStart = $current->copy()->subMinutes($slotDuration);
//     $slotEnd = $current->copy();

//             // Skip past slots for today
//             if ($date->isToday() && $slotEnd->lte($now)) {
//                 continue;
//             }

//             $timeSlots[] = [
//                 'doctor_id' => $doctor->id,
//                 'date' => $dateStr,
//                 'start_time' => $slotStart->format('H:i:s'),
//                 'end_time' => $slotEnd->format('H:i:s'),
//                 'is_booked' => false,
//                 'created_at' => now(),
//                 'updated_at' => now()
//             ];
//             $slotsCount++;
//         }

//         \Log::info("Generated {$slotsCount} slots for {$dateStr} ({$dayName})");
//     }

//     if (!empty($timeSlots)) {
//         try {
//             \Log::info("Inserting ".count($timeSlots)." slots for doctor {$doctor->id}");
//             TimeSlot::insert($timeSlots);
//             return count($timeSlots);
//         } catch (\Exception $e) {
//             \Log::error("Failed to insert slots: ".$e->getMessage());
//             return 0;
//         }
//     }

//     \Log::warning("No slots generated for doctor {$doctor->id}");
//     return 0;
// }



//     public function updateDoctor(Request $request, Doctor $doctor)
//     {
//         $validator = Validator::make($request->all(), [

//             'email' => [
//                 'sometimes',
//                 'email',
//                 'unique:users,email,' . $doctor->user_id
//             ],
//             'phone_number' => 'sometimes|string|max:20',

//             // Doctor data
//             'specialty' => 'sometimes|string|max:255',
//             'bio' => 'nullable|string',
//             'consultation_fee' => 'sometimes|numeric|min:120',
//             'experience_years' => 'sometimes|integer|min:1',
//             'clinic_id' => 'sometimes|exists:clinics,id',
//             'workdays' => 'sometimes|array',
//             'workdays.*' => 'string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',

//             // Status control
//             'is_active' => 'sometimes|boolean'
//         ]);

//         if ($validator->fails()) {
//             return response()->json(['errors' => $validator->errors()], 422);
//         }

//         $validated = $validator->validated();

//         return DB::transaction(function () use ($request, $doctor, $validated) {
//             // Update user data if present
//             if (
//                 isset($validated['email']) || isset($validated['phone_number'])
//             ) {

//                 $userData = [
//                   'email' => $validated['email'] ?? $doctor->user->email,
//                     'phone_number' => $validated['phone_number'] ?? $doctor->user->phone_number,
//                 ];

//                 $doctor->user->update($userData);
//             }

//             // Update doctor data
//             $doctorData = collect($validated)
//                 ->except(['first_name', 'last_name', 'email', 'phone_number'])
//                 ->toArray();

//             $doctor->update($doctorData);

//             return response()->json([
//                 'message' => 'Doctor updated successfully',
//                 'doctor' => $doctor->fresh()->load(['user', 'clinic', 'schedules'])
//             ]);
//         });
//     }
    /**
     * Delete a doctor (admin only)
     */
    public function deleteDoctor(Doctor $doctor)
    {
        // Check for upcoming appointments
        $hasUpcomingAppointments = $doctor->appointments()
            ->where('appointment_date', '>=', now())
            ->whereIn('status', ['confirmed', 'pending'])
            ->exists();

        if ($hasUpcomingAppointments) {
            return response()->json([
                'message' => 'Cannot delete doctor with upcoming appointments',
                'upcoming_appointments' => $doctor->appointments()
                    ->where('appointment_date', '>=', now())
                    ->count()
            ], 422);
        }

        return DB::transaction(function () use ($doctor) {
            // Archive or soft delete if implemented
            if (method_exists($doctor, 'trashed')) {
                $doctor->delete();
                $doctor->user()->delete();
            } else {
                // Permanent deletion
                $doctor->user()->delete();
                $doctor->delete();
            }

            return response()->json([
                'message' => 'Doctor deleted successfully',
                'deleted_at' => now()->toDateTimeString()
            ]);
        });
    }








/**
 * Change admin password
 */
public function changePassword(Request $request)
{
    $request->validate([
        'current_password' => 'required|string',
        'new_password' => 'required|string|min:8|confirmed'
    ]);

    $admin = Auth::user();

    if (!Hash::check($request->current_password, $admin->password)) {
        return response()->json(['error' => 'Current password is incorrect'], 401);
    }

    $admin->password = Hash::make($request->new_password);
    $admin->save();

    return response()->json(['message' => 'Password changed successfully']);
}








/**
 * Update admin information (excluding profile picture)
 */
// public function updateAdminInfo(Request $request){
//     $user = Auth::user();

//     // Check if user is authenticated
//     if (!$user) {
//         return response()->json([
//             'message' => 'Unauthenticated'
//         ], 401);
//     }

//     // Check if user has admin role
//     if (!$user->hasRole('admin')) {
//         return response()->json([
//             'message' => 'User is not an admin'
//         ], 403);
//     }

//     $validator = Validator::make($request->all(), [
//         'first_name' => 'sometimes|string|max:255',
//         'last_name' => 'sometimes|string|max:255',
//         'email' => [
//             'sometimes',
//             'email',
//             'unique:users,email,' . $user->id
//         ],
//         'phone_number' => 'sometimes|string|max:20',
//         'date_of_birth' => 'sometimes|date',
//         'address' => 'sometimes|string|max:500',
//         'gender' => 'sometimes|in:male,female,other',
//         'additional_notes' => 'nullable|string'
//     ]);

//     if ($validator->fails()) {
//         return response()->json(['errors' => $validator->errors()], 422);
//     }

//     $validated = $validator->validated();

//     try {
//         $user->update([
//             'first_name' => $validated['first_name'] ?? $user->first_name,
//             'last_name' => $validated['last_name'] ?? $user->last_name,
//             'email' => $validated['email'] ?? $user->email,
//             'phone_number' => $validated['phone_number'] ?? $user->phone_number,
//             'date_of_birth' => $validated['date_of_birth'] ?? $user->date_of_birth,
//             'address' => $validated['address'] ?? $user->address,
//             'gender' => $validated['gender'] ?? $user->gender,
//             'additional_notes' => $validated['additional_notes'] ?? $user->additional_notes,
//         ]);

//         return response()->json([
//             'message' => 'Admin information updated successfully',
//             'admin' => $user->fresh()
//         ]);
//     } catch (\Exception $e) {
//         return response()->json([
//             'message' => 'Failed to update admin information',
//             'error' => $e->getMessage()
//         ], 500);
//     }
// }











 public function uploadProfilePicture(Request $request)
    {
        $config = $this->profilePictureConfig;

        $validator = Validator::make($request->all(), [
            'profile_picture' => 'required|image|mimes:' . implode(',', $config['allowed_types']) .
                                 '|max:' . $config['max_size']
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $file = $request->file('profile_picture');

        try {
            // Delete old file if exists
            if ($user->profile_picture) {
                $this->deleteProfilePicture();
            }

            // Generate unique filename
            $extension = $file->getClientOriginalExtension();
            $filename = Str::uuid() . '.' . $extension;
            $directory = trim($config['directory'], '/');

            // Store file
            $path = $file->storeAs($directory, $filename, 'public');

            // Update user record
            $user->profile_picture = $path;
            $user->save();

            return response()->json([
                'success' => true,
                'profile_picture_url' => $this->getProfilePictureUrl($user),
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

    /**
     * Get admin profile picture URL
     */
    public function getProfilePicture()
    {
        $user = Auth::user();

        if (!$user->profile_picture) {
            return response()->json(['message' => 'No profile picture set'], 404);
        }

        try {
            $url = $this->getProfilePictureUrl($user);

            if (!$url) {
                return response()->json(['message' => 'Profile picture file not found'], 404);
            }

            return response()->json([
                'profile_picture_url' => $url,
                'message' => 'Profile picture retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving profile picture',
                'error' => $e->getMessage()
            ], 500);
        }
    }



        public function deleteProfilePicture(){
        $user = Auth::user();

        try {
            if ($this->deleteProfilePictureFile($user)) {  // Changed to helper method
                return response()->json([
                    'success' => true,
                    'message' => 'Profile picture deleted successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No profile picture to delete'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete profile picture',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getProfilePictureFile(){
    $user = Auth::user();

    if (!$user->profile_picture) {
        return response()->json(['message' => 'No profile picture set'], 404);
    }

    $path = storage_path('app/public/' . $user->profile_picture);

    if (!file_exists($path)) {
        return response()->json(['message' => 'File not found'], 404);
    }

    return response()->file($path);
}



  private function getProfilePictureUrl(User $user){
    if (!$user->profile_picture) {
        return null;
    }

    // Get the stored path directly
    $path = $user->profile_picture;

    // Verify file exists in storage
    if (Storage::disk('public')->exists($path)) {
        return asset('storage/' . $path);
    }

    return null;
}

private function deleteProfilePictureFile(User $user)
{
    if (!$user->profile_picture) {
        return false;
    }

    $path = $user->profile_picture;

    if (Storage::disk('public')->exists($path)) {
        Storage::disk('public')->delete($path);
    }

    $user->profile_picture = null;
    $user->save();

    return true;
}
























///////////////////////////////////////////////////////////routs by me ////////////////////////////////////////////////////////////////
public function addClinic(Request $request)
{
    // التحقق من المدخلات
    $attr = $request->validate([
        'name' => 'required|string',
        'specialty' => 'nullable|string',
        'location' => 'nullable|string',
        'description' => 'nullable|string',
        'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,bmp|max:4096',
    ]);

    // إنشاء العيادة أولاً
    $clinic = Clinic::create([
        'name' => $attr['name'],
        'specialty' => $attr['specialty'] ?? 'Specail at '.$attr['name'],
        'location' => $attr['location'] ?? null,
        'description' => $attr['description'] ?? null,
        'image_path' => null, // سيتم تعبئتها لاحقاً إذا كانت هناك صورة
    ]);

    // رفع الصورة إذا كانت موجودة باستخدام التابع في المودل
    if ($request->hasFile('image')) {
        $success = $clinic->uploadIcon($request->file('image'));
        if (!$success) {
            // إذا فشل رفع الصورة، نحذف العيادة التي تم إنشاؤها
            $clinic->delete();
            return response()->json([
                'message' => 'Failed to upload image'
            ], 500);
        }
    }

    return response()->json([
        'message' => 'The clinic and image were created successfully',
        'clinic' => $clinic,
        'icon_url' => $clinic->getIconUrl() // إضافة رابط الأيقونة في الرد
    ], 200);
}
public function editClinic(Request $request, $clinic_id)
{
    $clinic = Clinic::findOrFail($clinic_id);

    $attr = $request->validate([
        'name' => 'required|string',
        'specialty' => 'nullable|string',
        'location' => 'nullable|string',
        'description' => 'nullable|string',
        'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,bmp|max:4096',
    ]);

    // استخدام التابع uploadIcon من المودل لمعالجة الصورة
    if ($request->hasFile('image')) {
        $clinic->uploadIcon($request->file('image'));
    } 
        
    

    $clinic->update([
        'name' => $attr['name'],
        'specialty' => $attr['specialty'] ?? $clinic->specialty,
        'location' => $attr['location'] ?? $clinic->location,
        'description' => $attr['description'] ?? $clinic->description,
        'image_path' => $clinic->getIconUrl(),
    ]);

    return response()->json([
        'message' => 'The clinic was updated successfully',
        'clinic' => $clinic,
    ], 200);
}

    public function deleteClinic($clinic_id)
    {
        $Clinic = Clinic::find($clinic_id);
        if (!$Clinic) {
            return response()->json(['message' => 'Clinic is not found'], 404);
        }
        $Clinic->delete();

        return response()->json(['message' => ' deleted successfully'], 200);
    }


public function allClinics(Request $request){
    $limit = $request->get('limit', 5);
    $clinics = Clinic::paginate($limit);

    if ($clinics->isEmpty()) {
        return response()->json(['message' => 'No clinics found'], 404);
    }

    // إضافة رابط الأيقونة لكل عيادة
    $clinicsData = $clinics->map(function ($clinic) {
        return [
            'id' => $clinic->id,
            'name' => $clinic->name,
            'specialty' => $clinic->specialty,
            'location' => $clinic->location,
            'image_path' => $clinic->getIconUrl()
        ];
    });

    return response()->json([
        'clinics' => $clinicsData, // البيانات مع الأيقونات
        'total' => $clinics->total(),
        'current_page' => $clinics->currentPage(),
        'last_page' => $clinics->lastPage(),
        'per_page' => $clinics->perPage(),
    ], 200);
}


public function gitClinicById($clinic_id)
{
    $clinic = Clinic::find($clinic_id);
    
    if (!$clinic) {
        return response()->json(['message' => 'Clinic is not found'], 404);
    }

    // إضافة بيانات إضافية بما فيها رابط الأيقونة
    $clinicData = [
        'id' => $clinic->id,
        'name' => $clinic->name,
        'location' => $clinic->location,
        'icon_url' => $clinic->getIconUrl(),
        'specialty' => $clinic->specialty,
        // يمكنك إضافة أي حقول أخرى تحتاجها هنا
        'created_at' => $clinic->created_at,
        'updated_at' => $clinic->updated_at,
    ];

    return response()->json(["clinic" => $clinicData], 200);
}



public function searchClinicsA(Request $request)
{
    $limit = $request->get('limit', 5);
    $query = Clinic::withCount('doctors');

    if ($request->filled('keyword')) {
        $keyword = $request->keyword;

        $query->where(function ($q) use ($keyword) {
            $q->where('name', 'like', "%$keyword%")
              ->orWhere('description', 'like', "%$keyword%");
        });
    } else {
        return response()->json([
            'success' => true,
            'clinics' => [],
            'total' => 0,
            'current_page' => 1,
            'last_page' => 1
        ], 200);
    }

    $clinics = $query->paginate($limit);

    if ($clinics->isEmpty()) {
        return response()->json([
            'success' => true,
            'clinics' => [],
            'total' => 0,
            'current_page' => $clinics->currentPage(),
            'last_page' => $clinics->lastPage()
        ], 200);
    }

    // إضافة icon_url بدلاً من image_path للحفاظ على التناسق
    return response()->json([
        'success' => true,
        'clinics' => $clinics->map(function ($clinic) {
            return [
                'id' => $clinic->id,
                'name' => $clinic->name,
                'location' => $clinic->location, // أضفت location للحفاظ على التناسق
                'icon_url' => $clinic->getIconUrl(), // غيرت من image_path إلى icon_url
                'doctors_count' => $clinic->doctors_count
            ];
        }),
        'total' => $clinics->total(),
        'current_page' => $clinics->currentPage(),
        'last_page' => $clinics->lastPage(),
        'per_page' => $clinics->perPage(), // أضفت per_page لإكمال المعلومات
    ], 200);
}


/////////////////////////////////////////////////////////    patients          ///////////////////////////////
public function allPatients(Request $request)
{
    $limit = $request->get('limit', 5);

    // جلب المرضى مع بيانات المستخدم المرتبطة بهم
    $patients = Patient::with('user')->paginate($limit);

    if ($patients->isEmpty()) {
        return response()->json(['message' => 'No patients found'], 404);
    }

    // تحويل profile_picture إلى رابط كامل
    $patients->getCollection()->transform(function ($patient) {
        if ($patient->user && method_exists($patient->user, 'getProfilePictureUrl')) {
            // استبدال profile_picture بالرابط الكامل
            $patient->user->profile_picture = $patient->user->getProfilePictureUrl();
        }
        return $patient;
    });

    return response()->json([
        'patients' => $patients->items(),
        'total' => $patients->total(),
        'current_page' => $patients->currentPage(),
        'last_page' => $patients->lastPage(),
    ], 200);
}
public function addPatient(Request $request)
{
    // التحقق من صحة البيانات المدخلة
    $validator = Validator::make($request->all(), [
        'first_name' => 'required|string|max:255',
        'last_name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
        'password' => 'required|string|min:8',
        'phone_number' => 'nullable|string',
        'date_of_birth' => 'nullable|date',
        'address' => 'nullable|string',
        'gender' => 'required|in:male,female',
        'blood_type' => 'nullable|string',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    try {
        DB::beginTransaction();

        // 1. إنشاء المستخدم أولاً
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone_number, 
            'password' => Hash::make($request->password),
            'address' => $request->address,
            'gender' => $request->gender,
            'role_id' => 4, 
        ]);

        
        $patient = Patient::create([
            'user_id' => $user->id,
            'phone_number' => $request->phone_number,
            'date_of_birth' => $request->date_of_birth,
            'address' => $request->address,
            'gender' => $request->gender,
            'blood_type' => $request->blood_type,
            // يمكنك إضافة أي حقول أخرى هنا
        ]);

        DB::commit();

        return response()->json([
            'message' => 'Patient created successfully',
            'patient' => $patient->load('user') // إرجاع المريض مع بيانات المستخدم
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        // يمكنك تسجيل الخطأ هنا للمراجعة لاحقاً
        // Log::error($e->getMessage());
        return response()->json(['message' => 'An error occurred while creating the patient.'], 500);
    }
}

public function searchPatients(Request $request)
{
    $query = Patient::with(['user']);

    if ($request->filled('keyword')) {
        $keyword = $request->keyword;

        $query->where(function ($q) use ($keyword) {
            $q->whereHas('user', function ($userQuery) use ($keyword) {
                $userQuery->where('first_name', 'like', "%$keyword%")
                    ->orWhere('last_name', 'like', "%$keyword%")
                    ->orWhere('email', 'like', "%$keyword%");
            })
            ->orWhere('phone_number', 'like', "%$keyword%")
            ->orWhere('blood_type', 'like', "%$keyword%");
        });
    } else {
        return response()->json([], 200);
    }

    $results = $query->get();

    if ($results->isEmpty()) {
        return response()->json([], 404);
    }

    // تحويل profile_picture إلى رابط كامل
    $results->transform(function ($patient) {
        if ($patient->user && method_exists($patient->user, 'getProfilePictureUrl')) {
            // استبدال profile_picture بالرابط الكامل
            $patient->user->profile_picture = $patient->user->getProfilePictureUrl();
        }
        return $patient;
    });

    return response()->json($results, 200);
}
public function getPatientById($id)
{
    $patient = Patient::with('user')->find($id);

    if (!$patient) {
        return response()->json(['message' => 'Patient not found'], 404);
    }

    return response()->json([
        'patient' => $patient
    ], 200);
}
public function updatePatient(Request $request, $id)
{
    $patient = Patient::with('user')->find($id);

    if (!$patient) {
        return response()->json(['message' => 'Patient not found'], 404);
    }

    $validator = Validator::make($request->all(), [
        'first_name'   => 'required|string|max:255',
        'last_name'    => 'required|string|max:255',
        'email'        => 'required|string|email|max:255|unique:users,email,' . $patient->user_id,
        'password'     => 'nullable|string|min:8',
        'phone_number' => 'nullable|string',
        'date_of_birth'=> 'nullable|date',
        'address'      => 'nullable|string',
        'gender'       => 'required|in:male,female',
        'blood_type'   => 'nullable|string',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    try {
        DB::beginTransaction();

        // تحديث بيانات الـ User
        $patient->user->update([
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'email'      => $request->email,
            'phone'      => $request->phone_number,
            'address'    => $request->address,
            'gender'     => $request->gender,
            'password'   => $request->password ? Hash::make($request->password) : $patient->user->password,
        ]);

        // تحديث بيانات Patient
        $patient->update([
            'phone_number' => $request->phone_number,
            'date_of_birth'=> $request->date_of_birth,
            'address'      => $request->address,
            'gender'       => $request->gender,
            'blood_type'   => $request->blood_type,
        ]);

        DB::commit();

        return response()->json([
            'message' => 'Patient updated successfully',
            'patient' => $patient->load('user')
        ], 200);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['message' => 'An error occurred while updating the patient.'], 500);
    }
}



/////////////////////////////////////////////////////////    doctors          ///////////////////////////////
    public function allDoctors(Request $request){
    $limit = $request->get('limit', 5);

    $doctors = Doctor::with([
        'user',     // معلومات المستخدم
        'clinic',   // معلومات العيادة
        'salary'    // معلومات الراتب (إذا عندك علاقة salary)
    ])->paginate($limit);

    if ($doctors->isEmpty()) {
        return response()->json(['message' => 'No doctors found'], 404);
    }
     $doctors->getCollection()->transform(function ($doctors) {
        if ($doctors->user && method_exists($doctors->user, 'getProfilePictureUrl')) {
            // استبدال profile_picture بالرابط الكامل
            $doctors->user->profile_picture = $doctors->user->getProfilePictureUrl();
        }
        return $doctors;
    });

    return response()->json([
        'doctors' => $doctors->items(),  // البيانات مع العلاقات
        'total' => $doctors->total(),
        'current_page' => $doctors->currentPage(),
        'last_page' => $doctors->lastPage(),
    ], 200);
}

public function DoctorInfo($doctor_id){
    $doctor = Doctor::with([
        'user',       // معلومات المستخدم
        'clinic',     // معلومات العيادة
        'salary',     // معلومات الراتب
        'schedules',  // جدول مواعيد الطبيب
        'timeSlots'   // المواعيد الزمنية (إذا عندك علاقة باسم timeSlots)
    ])->where('id', $doctor_id)->first();

    if (!$doctor) {
        return response()->json(['message' => 'No doctor found'], 404);
    }

    return response()->json([
        'doctor' => $doctor
    ], 200);
}

public function deleteDoctorA($doctor_id)
{


    $doctor = Doctor::findOrFail($doctor_id);

    // Check for upcoming appointments
    $hasUpcomingAppointments = $doctor->appointments()
        ->where('appointment_date', '>=', now())
        ->whereIn('status', ['confirmed', 'pending'])
        ->exists();

    if ($hasUpcomingAppointments) {
        return response()->json([
            'message' => 'Cannot delete doctor with upcoming appointments',
            'upcoming_appointments' => $doctor->appointments()
                ->where('appointment_date', '>=', now())
                ->count()
        ], 422);
    }

    return DB::transaction(function () use ($doctor) {
        // Archive or soft delete if implemented
        if (method_exists($doctor, 'trashed')) {
            $doctor->delete();
            $doctor->user()->delete();
        } else {
            // Permanent deletion
            $doctor->user()->delete();
            $doctor->delete();
        }

        return response()->json([
            'message' => 'Doctor deleted successfully',
            'deleted_at' => now()->toDateTimeString()
        ]);
    });
}
public function searchDoctorsA(Request $request)
    {
        $query = Doctor::with(['user', 'clinic', 'reviews', 'salary']); // تأكد من جلب كل العلاقات المطلوبة

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;

            $query->where(function ($q) use ($keyword) {
                $q->whereHas('user', function ($uq) use ($keyword) {
                    $uq->where('first_name', 'like', "%$keyword%")
                        ->orWhere('last_name', 'like', "%$keyword%");
                })
                    ->orWhere('specialty', 'like', "%$keyword%")
                    ->orWhereHas('clinic', function ($cq) use ($keyword) {
                        $cq->where('name', 'like', "%$keyword%");
                    });
            });
        } else {
            // من الأفضل إرجاع مصفوفة فارغة بدلاً من خطأ إذا لم يتم توفير كلمة بحث
            return response()->json([], 200);
        }

        $results = $query->get();

        if ($results->isEmpty()) {
            // أرجع مصفوفة فارغة إذا لم يتم العثور على نتائج
            return response()->json([], 404);
        }

        // أرجع النتائج مباشرة بنفس هيكل allDoctors
        return response()->json($results, 200);
    }







// public function editDoctor1(Request $request, Doctor $doctor)
// {
//     $validator = Validator::make($request->all(), [
//         // User data
//         'first_name' => 'sometimes|string|max:255',
//         'last_name' => 'sometimes|string|max:255',
//         'email' => [
//             'sometimes',
//             'email',
//             'unique:users,email,' . $doctor->user_id
//         ],
//         'phone_number' => 'sometimes|string|max:20',

//         // Doctor data
//         'specialty' => 'sometimes|string|max:255',
//         'bio' => 'nullable|string',
//         'consultation_fee' => 'sometimes|numeric|min:0',
//         'experience_years' => 'sometimes|integer|min:0',
//         'clinic_id' => 'sometimes|exists:clinics,id',

//         // جدول المواعيد
//         'schedules' => 'sometimes|array',
//         'schedules.*.day' => 'required_with:schedules|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
//         'schedules.*.start_time' => 'required_with:schedules|date_format:H:i',
//         'schedules.*.end_time' => 'required_with:schedules|date_format:H:i',

//         // إعدادات المواعيد
//         'slot_duration' => 'sometimes|integer|min:5',
//         'generate_slots_for_days' => 'sometimes|integer|min:1',

//         // Status control
//         'is_active' => 'sometimes|boolean'
//     ]);

//     if ($validator->fails()) {
//         return response()->json(['errors' => $validator->errors()], 422);
//     }

//     $validated = $validator->validated();

//     return DB::transaction(function () use ($request, $doctor, $validated) {
//         // تحديث بيانات المستخدم
//         if (isset($validated['first_name']) || isset($validated['last_name']) || isset($validated['email']) || isset($validated['phone_number'])) {
//             $userData = [
//                 'first_name' => $validated['first_name'] ?? $doctor->user->first_name,
//                 'last_name' => $validated['last_name'] ?? $doctor->user->last_name,
//                 'email' => $validated['email'] ?? $doctor->user->email,
//                 'phone' => $validated['phone_number'] ?? $doctor->user->phone, // إذا العمود اسمه phone أو phone_number حسب الجدول
//             ];
//             $doctor->user->update($userData);
//         }

//         // تحديث بيانات الطبيب
//         $doctorData = collect($validated)
//             ->except(['first_name', 'last_name', 'email', 'phone_number', 'schedules'])
//             ->toArray();
//         $doctor->update($doctorData);

//         // تحديث جدول المواعيد
//         if (isset($validated['schedules'])) {
//             // حذف الجداول القديمة
//             $doctor->schedules()->delete();

//             // إدخال الجداول الجديدة
//             foreach ($validated['schedules'] as $schedule) {
//                 $doctor->schedules()->create([
//                     'day' => $schedule['day'],
//                     'start_time' => $schedule['start_time'],
//                     'end_time' => $schedule['end_time'],
//                 ]);
//             }
//         }

//         return response()->json([
//             'message' => 'Doctor updated successfully',
//             'doctor' => $doctor->fresh()->load(['user', 'clinic', 'schedules'])
//         ]);
//     });
// }


public function createSecretary(Request $request)
{
    // Validate input
    $validator = Validator::make($request->all(), [
        'first_name'   => 'required|string|max:255',
        'last_name'    => 'required|string|max:255',
        'email'        => 'required|email|unique:users,email',
        'phone_number' => 'required|string|max:20',
        'gender'       => 'required|in:male,female',
        'workdays'     => 'required|array',
        'workdays.*'   => 'string',
        'salary'       => 'nullable|numeric|min:0',
        'password' => 'required|string|min:8',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Validation failed',
            'errors'  => $validator->errors()
        ], 422);
    }

    try {
        // Create User record
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'email'      => $request->email,
            'phone'      => $request->phone_number,
            'gender'     => $request->gender,
            'role_id'    => 3,
            'password' => Hash::make($request->password),

        ]);

        // Create Secretary record
        $secretary = Secretary::create([
            'user_id'             => $user->id,
            'salary'              => $request->salary,
            'workdays'            => json_encode($request->workdays),
            'emergency_absences'  => json_encode([]),
            'performance_metrics' => json_encode([]),
        ]);

        return response()->json([
            'message'           => 'Secretary created successfully',
            'secretary'         => $secretary,
            'login_credentials' => [
                'email'    => $user->email,
                'password' => $user->password
            ]
        ], 201);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Failed to create secretary',
            'error'   => $e->getMessage()
        ], 500);
    }
}
public function updateSecretary(Request $request, $id)
{
    $secretary = Secretary::with('user')->find($id);

    if (!$secretary) {
        return response()->json(['success' => false, 'message' => 'Secretary Not Found!'], 404);
    }

    $user = $secretary->user;

    $validated = $request->validate([
        'first_name'       => 'nullable|string|max:255',
        'last_name'        => 'nullable|string|max:255',
        'email'            => 'nullable|email|unique:users,email,' . $user->id,
        'workdays'         => 'nullable|string',
        
        'profile_picture'  => 'nullable|image|mimes:jpeg,png,jpg,gif|max:4096'
    ]);

    // تحديث بيانات المستخدم
    if (isset($validated['first_name'])) {
        $user->first_name = $validated['first_name'];
    }
    
    if (isset($validated['last_name'])) {
        $user->last_name = $validated['last_name'];
    }
    
    if (isset($validated['email'])) {
        $user->email = $validated['email'];
    }

    // رفع صورة الملف الشخصي إذا كانت موجودة
    if ($request->hasFile('profile_picture')) {
        $uploaded = $user->uploadFile($request->file('profile_picture'), 'profile_picture');
        if (!$uploaded) {
            return response()->json([
                'success' => false, 
                'message' => 'Failed to upload profile picture'
            ], 500);
        }
    }

    // حفظ بيانات المستخدم
    $user->save();

    // تحديث بيانات السكرتارية
    if (isset($validated['workdays'])) {
        $secretary->workdays = $validated['workdays'];
    }
    
    $secretary->save();

    return response()->json([
        'success' => true, 
        'message' => 'Information Updated Successfully',
        'secretary' => $secretary->load('user') // إرجاع البيانات مع العلاقة
    ]);
}
public function deleteSecretary($secretary)
{
    try {
        // البحث عن السكرتارية باستخدام ID الأساسي الخاص بها
        $secretary = Secretary::find($secretary);

        if (!$secretary) {
            return response()->json([
                'success' => false,
                'message' => 'السكرتارية غير موجودة'
            ], 404);
        }

        // حذف السكرتارية (سوف يحذف User تلقائياً بسبب onDelete('cascade'))
       
        return DB::transaction(function () use ($secretary) {
            // Archive or soft delete if implemented
            if (method_exists($secretary, 'trashed')) {
                $secretary->delete();
                $secretary->user()->delete();
            } else {
                // Permanent deletion
                $secretary->user()->delete();
                $secretary->delete();
            }

            return response()->json([
                'message' => 'Secretary deleted successfully',
                'deleted_at' => now()->toDateTimeString()
            ]);
        });
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'فشل في حذف السكرتارية',
            'error' => $e->getMessage()
        ], 500);
    }
}


// داخل SecretaryController
public function getSecretaryById($id)
{
    $secretary = Secretary::with('user')->where('id',$id)->first();

    if (!$secretary) {
        return response()->json([
            'success' => false,
            'message' => 'السكرتيرة غير موجودة'
        ], 404);
    }

    return response()->json([
        'success' => true,
        'data' => $secretary
    ]);
}

///////////////////////////////profile/////
public function updateAdminInfo(Request $request)
{
    $user = Auth::user();

    if (!$user) {
        return response()->json([
            'message' => 'Unauthenticated'
        ], 401);
    }

    // if (!$user->hasRole('admin')) {
    //     return response()->json([
    //         'message' => 'User is not an admin'
    //     ], 403);
    // }

    $validator = Validator::make($request->all(), [
        'first_name' => 'sometimes|string|max:255',
        'last_name' => 'sometimes|string|max:255',
        'email' => [
            'sometimes',
            'email',
            'unique:users,email,' . $user->id
        ],
        'phone' => 'sometimes|string|max:20',
        'date_of_birth' => 'sometimes|date',
        'address' => 'sometimes|string|max:500',
        'gender' => 'sometimes|in:male,female,other',
        'additional_notes' => 'nullable|string',
        'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:4096'
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $validated = $validator->validated();

    // Handle image upload
    if ($request->hasFile('profile_picture'))
    {  
            // Upload the file using the HandlesFiles trait
            $uploaded = $user->uploadFile($request->file('profile_picture'), 'profile_picture');
            if (!$uploaded) {
                throw new \Exception('Failed to upload profile picture');
            }
    }
    
    try {
        $user->update([
            'first_name'       => $validated['first_name'] ?? $user->first_name,
            'last_name'        => $validated['last_name'] ?? $user->last_name,
            'email'            => $validated['email'] ?? $user->email,
            'phone'            => $validated['phone'] ?? $user->phone,
            'date_of_birth'    => $validated['date_of_birth'] ?? $user->date_of_birth,
            'address'          => $validated['address'] ?? $user->address,
            'gender'           => $validated['gender'] ?? $user->gender,
            'additional_notes' => $validated['additional_notes'] ?? $user->additional_notes,
            'profile_picture'  => $user->getProfilePictureUrl(),
        ]);

        return response()->json([
            'message' => 'Admin information updated successfully',
            'admin'   => $user->fresh()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Failed to update admin information',
            'error'   => $e->getMessage()
        ], 500);
    }
}

///////////////////////////////////////////////////////////////////////statistics
public function statistics(){
    $now = Carbon::now();

        return response()->json([
            'total_patients' => Patient::count(),
            'total_doctors' => Doctor::count(),
            'total_appointments' => Appointment::count(),
            'current_month_appointments' => Appointment::whereMonth('created_at', $now->month)
                ->whereYear('created_at', $now->year)
                ->count(),
            'wallet_balance' => MedicalCenterWallet::first()->balance ?? 0,
            'current_month_doctors' => Doctor::whereMonth('created_at', $now->month)
                ->whereYear('created_at', $now->year)
                ->count()
        ]);
    }
///////////////////////////////////////////////////////////////////////


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
            'medical_center_wallet' => true // Mark as going to medical center
        ];

        $payment = Payment::create($paymentData);

        // Add to medical center wallet for cash payments
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

public function addToPatientWallet(Request $request)
{
    $validated = $request->validate([
        'patient_id' => 'required|exists:patients,id',
        'amount' => 'required|numeric|min:0.01',
        'notes' => 'nullable|string|max:255'
    ]);

    // Authorization check
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

    // Only process deposit if wallet is activated
    $result = $patient->deposit(
        $validated['amount'],
        $validated['notes'] ?? 'Added by secretary',
        Auth::user()->id
    );

    // Simplify the success response since we've already validated
    return response()->json([
        'status' => 'success',
        'message' => 'Funds added successfully',
        'transaction' => $result['transaction'],
        'new_balance' => $result['new_balance']
    ]);
}




public function getPatientWalletInfo($patientId)
    {
        $patient = Patient::with(['user', 'walletTransactions' => function ($q) {
                $q->orderBy('created_at', 'desc')->limit(10);
            }])->findOrFail($patientId);

        return response()->json([
            'patient' => $patient,
            'wallet_balance' => $patient->wallet_balance,
            'wallet_activated' => !is_null($patient->wallet_activated_at),
            'recent_transactions' => $patient->walletTransactions
        ]);
}




public function bookAppointment(Request $request)
{
    if (!Auth::user()->secretary) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $validated = $request->validate([
        'patient_id' => 'required|exists:patients,id',
        'doctor_id' => 'required|exists:doctors,id',
        'clinic_id' => 'required|exists:clinics,id',
        'time_slot_id' => 'required|exists:time_slots,id',
        'appointment_date' => 'required|date|after:now',
        'reason' => 'nullable|string',
        'price' => 'required|numeric|min:0',
        'payment_method' => 'required|in:cash,card,insurance,wallet',
    ]);

    try {
        DB::beginTransaction();

        // Get the time slot
        $slot = TimeSlot::where('id', $validated['time_slot_id'])
            ->where('doctor_id', $validated['doctor_id'])
            ->lockForUpdate()
            ->firstOrFail();

        // Check if slot is already booked
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


 $doctorExists = Doctor::withTrashed()->where('id', $request->doctor_id)->exists();

    if (!$doctorExists) {
        return response()->json([
            'error' => 'doctor_not_found',
            'message' => 'The selected doctor is not available for appointments'
        ], 422);
    }


        // Create appointment
        $appointment = Appointment::create([
            'patient_id' => $validated['patient_id'],
            'doctor_id' => $validated['doctor_id'],
            'clinic_id' => $validated['clinic_id'],
            'time_slot_id' => $validated['time_slot_id'],
            'appointment_date' => $validated['appointment_date'],
            'reason' => $validated['reason'],
            'price' => $validated['price'],
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        // Mark slot as booked
        $slot->update(['is_booked' => true]);

        // Get the single medical center wallet (since we're not using clinic-specific wallets)
        $medicalCenterWallet = MedicalCenterWallet::firstOrCreate([], ['balance' => 0]);

        // Handle payment based on method
        if ($validated['payment_method'] === 'wallet') {
            $patient = Patient::findOrFail($validated['patient_id']);

            if ($patient->wallet_balance < $validated['price']) {
                throw new \Exception('Insufficient wallet balance');
            }

            // Deduct from patient wallet
            $patient->decrement('wallet_balance', $validated['price']);

            // Create patient wallet transaction
            WalletTransaction::create([
                'patient_id' => $patient->id,
                'amount' => $validated['price'],
                'type' => 'payment',
                'reference' => 'APT-' . $appointment->id,
                'balance_before' => $patient->wallet_balance + $validated['price'],
                'balance_after' => $patient->wallet_balance,
                'notes' => 'Payment for appointment #' . $appointment->id
            ]);
        }

        // For ALL payment methods, add to medical center wallet
        $medicalCenterWallet->increment('balance', $validated['price']);

        // Create medical center wallet transaction
        MedicalCenterWalletTransaction::create([
            'medical_wallet_id' => $medicalCenterWallet->id,
            'amount' => $validated['price'],
            'type' => 'payment',
            'reference' => 'APT-' . $appointment->id,
            'balance_before' => $medicalCenterWallet->balance - $validated['price'],
            'balance_after' => $medicalCenterWallet->balance,
            'notes' => 'Payment ('.$validated['payment_method'].') from patient #' . $validated['patient_id'] . ' for appointment #' . $appointment->id,
            'clinic_id' => $validated['clinic_id'] // This will be stored but not used for wallet balance
        ]);

        // Create payment record
        $payment = Payment::create([
            'appointment_id' => $appointment->id,
            'patient_id' => $validated['patient_id'],
            'amount' => $validated['price'],
            'method' => $validated['payment_method'],
            'status' => 'paid',
            'secretary_id' => Auth::user()->secretary->id,
            'medical_center_wallet' => true,
            'transaction_id' => $validated['payment_method'] === 'wallet'
                ? 'WALLET-' . $appointment->id
                : strtoupper($validated['payment_method']) . '-' . $appointment->id,
            'paid_at' => now()
        ]);

        DB::commit();

        return response()->json([
            'message' => 'Appointment booked and payment processed',
            'appointment' => $appointment,
            'payment' => $payment
        ], 201);
        } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Failed to book appointment',
            'error' => $e->getMessage()
        ], 500);
    }
}






public function getAppointments(Request $request){
    // Verify secretary
    // if (!Auth::user()->secretary) {
    //     return response()->json(['message' => 'Unauthorized'], 403);
    // }

    $type = $request->query('type', 'upcoming');
    $perPage = $request->query('per_page', 10);
    $clinicId = $request->query('clinic_id');
    $doctorId = $request->query('doctor_id');

    $query = Appointment::with([
        'patient.user:id,first_name,last_name',
        'doctor' => function($query) {
            $query->withTrashed()->with(['user' => function($q) {
                $q->withTrashed()->select('id', 'first_name', 'last_name');
            }]);
        },
        'clinic:id,name',
        'payments'
    ]);

    // Apply filters
    if ($clinicId) {
        $query->where('clinic_id', $clinicId);
    }
    if ($doctorId) {
        $query->where('doctor_id', $doctorId);
    }

    // Filter by appointment type
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

public function secretaryBookAppointment(Request $request)
    {
        if (!Auth::user()->secretary) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'patient_id' => 'required_without:new_patient|exists:patients,id',
            'new_patient' => 'required_without:patient_id|array',
            'new_patient.first_name' => 'required_with:new_patient|string|max:255',
            'new_patient.last_name' => 'required_with:new_patient|string|max:255',
            'new_patient.email' => 'required_with:new_patient|email|unique:users,email',
            'new_patient.phone' => 'required_with:new_patient|string|max:20',
            'new_patient.date_of_birth' => 'required_with:new_patient|date',
            'new_patient.gender' => 'required_with:new_patient|in:male,female,other',
            'new_patient.address' => 'nullable|string',
            'doctor_id' => 'required|exists:doctors,id',
            'clinic_id' => 'required|exists:clinics,id',
            'time_slot_id' => 'required|exists:time_slots,id',
            'reason' => 'required|string|max:500',
            'payment_method' => 'required|in:cash,card,insurance,wallet',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $secretary = Auth::user()->secretary;

            if ($request->has('new_patient')) {
                $newPatientData = $request->new_patient;

                $patientRole = Role::firstOrCreate(
                    ['name' => 'patient'],
                    ['description' => 'Patient user']
                );

                $user = User::create([
                    'first_name' => $newPatientData['first_name'],
                    'last_name' => $newPatientData['last_name'],
                    'email' => $newPatientData['email'],
                    'phone' => $newPatientData['phone'],
                    'password' => bcrypt('temporary_password'),
                    'role_id' => $patientRole->id,
                ]);

                $patient = Patient::create([
                    'user_id' => $user->id,
                    'date_of_birth' => $newPatientData['date_of_birth'],
                    'gender' => $newPatientData['gender'],
                    'address' => $newPatientData['address'] ?? null,
                    'phone_number' => $newPatientData['phone'],
                ]);

                $patientId = $patient->id;
            } else {
                $patientId = $request->patient_id;
                $patient = Patient::findOrFail($patientId);
            }

            $slot = TimeSlot::where('id', $request->time_slot_id)
                ->where('doctor_id', $request->doctor_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($slot->is_booked) {
                return response()->json([
                    'message' => 'This time slot is no longer available'
                ], 409);
            }

            $doctor = Doctor::findOrFail($request->doctor_id);
            $appointment = Appointment::create([
                'patient_id' => $patientId,
                'doctor_id' => $request->doctor_id,
                'clinic_id' => $request->clinic_id,
                'time_slot_id' => $request->time_slot_id,
                'appointment_date' => $slot->date->format('Y-m-d') . ' ' . $slot->start_time,
                'reason' => $request->reason,
                'notes' => $request->notes,
                'price' => $doctor->consultation_fee,
                'status' => 'confirmed',
                'booked_by_secretary' => true,
                'secretary_id' => $secretary->id
            ]);

            $slot->update(['is_booked' => true]);

            $paymentResult = $this->processAppointmentPayment(
                $appointment,
                $patient,
                $request->payment_method,
                $secretary->id
            );

            DB::commit();

            return response()->json([
                'message' => 'Appointment booked successfully',
                'appointment' => $appointment->load(['patient.user', 'doctor.user', 'clinic']),
                'payment' => $paymentResult,
                'new_patient_created' => $request->has('new_patient')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to book appointment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

protected function processAppointmentPayment($appointment, $patient, $paymentMethod, $secretaryId)
    {
        $medicalCenterWallet = MedicalCenterWallet::firstOrCreate([], ['balance' => 0]);

        if ($paymentMethod === 'wallet') {
            if (!$patient->wallet_activated_at) {
                throw new \Exception('Patient wallet is not activated');
            }

            if ($patient->wallet_balance < $appointment->price) {
                throw new \Exception('Insufficient wallet balance');
            }

            $patient->decrement('wallet_balance', $appointment->price);

            WalletTransaction::create([
                'patient_id' => $patient->id,
                'amount' => $appointment->price,
                'type' => 'payment',
                'reference' => 'APT-' . $appointment->id,
                'balance_before' => $patient->wallet_balance + $appointment->price,
                'balance_after' => $patient->wallet_balance,
                'notes' => 'Payment for appointment #' . $appointment->id
            ]);
        }

        $medicalCenterWallet->increment('balance', $appointment->price);

        MedicalCenterWalletTransaction::create([
            'medical_wallet_id' => $medicalCenterWallet->id,
            'clinic_id' => $appointment->clinic_id,
            'amount' => $appointment->price,
            'type' => 'payment',
            'reference' => 'APT-' . $appointment->id,
            'balance_before' => $medicalCenterWallet->balance - $appointment->price,
            'balance_after' => $medicalCenterWallet->balance,
            'notes' => 'Payment (' . $paymentMethod . ') for appointment #' . $appointment->id
        ]);

        $payment = Payment::create([
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'amount' => $appointment->price,
            'method' => $paymentMethod,
            'status' => 'paid',
            'secretary_id' => $secretaryId,
            'medical_center_wallet' => true,
            'transaction_id' => $paymentMethod === 'wallet'
                ? 'WALLET-' . $appointment->id
                : strtoupper($paymentMethod) . '-' . $appointment->id,
            'paid_at' => now()
        ]);

        return $payment;
    }







public function getClinicDoctors(Clinic $clinic)
    {
        $doctors = $clinic->doctors()
            ->with(['user', 'reviews', 'schedules'])
            ->get();



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
                'is_active' => $user->is_active,
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






public function unblockPatient(Request $request)
{
    // Verify the authenticated user is a secretary
if (Auth::user()->role_id !== 3) {
    return response()->json(['message' => 'Unauthorized'], 403);
}


    $validated = $request->validate([
        'patient_id' => 'required|exists:patients,id',
    ]);

    $patient = Patient::findOrFail($validated['patient_id']);

    // Get all absent appointments
    $absentAppointments = $patient->appointments()
        ->where('status', 'absent')
        ->get();

    // Option 1: Change status to 'cancelled' (soft approach)
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
    // Get the threshold from config
    $threshold = config('app.absent_appointment_threshold', 3);

    // Get patients who meet or exceed the threshold
    $blockedPatients = Patient::withCount(['appointments as absent_count' => function($query) {
            $query->where('status', 'absent');
        }])
        ->having('absent_count', '>=', $threshold)
        ->with('user:id,first_name,last_name,email,phone')
        ->get();

    return response()->json([
        'blocked_patients' => $blockedPatients->map(function($patient) {
            return [
                'id' => $patient->id,
                'name' => $patient->user->first_name.' '.$patient->user->last_name,
                'email' => $patient->user->email,
                'phone' => $patient->user->phone,
                'absent_count' => $patient->absent_count
            ];
        })
    ]);
}

}
