<?php

namespace Database\Seeders;

use App\Models\{Appointment, Clinic, DoctorSchedule, User, Doctor, MedicalCenterWallet, Patient, Prescription, Role, Salary, SalarySetting, Secretary, Specialty, TimeSlot};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles
        $roles = [
            'admin' => Role::firstOrCreate(['name' => 'admin']),
            'doctor' => Role::firstOrCreate(['name' => 'doctor']),
            'secretary' => Role::firstOrCreate(['name' => 'secretary']),
            'patient' => Role::firstOrCreate(['name' => 'patient'])
        ];

        // Create clinics
        $clinic1 = Clinic::firstOrCreate(['name' => 'Oncology']);
        Clinic::firstOrCreate(['name' => 'ENT']);
        Clinic::firstOrCreate(['name' => 'Neurology']);
        Clinic::firstOrCreate(['name' => 'Ophthalmology']);

        // Create secretary
        $secretaryUser = User::firstOrCreate(
            ['email' => 'secretary4@example.com'],
            [
                'first_name' => 'Sara',
                'last_name' => 'Secretary',
                'password' => Hash::make('password'),
                'role_id' => $roles['secretary']->id,
            ]
        );

        $secretary = Secretary::firstOrCreate(
            ['user_id' => $secretaryUser->id],
            [
                'salary' => 4000,
                'workdays' => json_encode(['Sunday', 'Monday', 'Tuesday']),
            ]
        );

        // Create salary settings
        $salarySettings = SalarySetting::firstOrCreate([]);

        // Create salary
        $doctorSalary = Salary::firstOrCreate(
            ['secretary_id' => $secretary->id],
            [
                'base_amount' => 100,
                'bonus_amount' => 0.50,
                'total_amount' => 100.5,
                'salary_setting_id' => $salarySettings->id,
                'status' => 'pending',
            ]
        );

        // Create patient
        $patientUser = User::firstOrCreate(
            ['email' => 'patient4@example.com'],
            [
                'first_name' => 'Test',
                'last_name' => 'Patient',
                'password' => Hash::make('password'),
                'role_id' => $roles['patient']->id,
            ]
        );

        $patient = Patient::firstOrCreate(
            ['user_id' => $patientUser->id],
            [
                'phone_number' => '1234567890',
                'date_of_birth' => '1990-01-01',
                'address' => '123 Test St',
                'gender' => 'male',
                'blood_type' => 'O +',
                'emergency_contact' => '9876543210'
            ]
        );

        // Create admin
        User::firstOrCreate(
            ['email' => 'admin4@example.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'password' => Hash::make('password'),
                'role_id' => $roles['admin']->id,
            ]
        );

        // Create doctor
        $doctorUser = User::firstOrCreate(
            ['email' => 'doctor4@example.com'],
            [
                'first_name' => 'John',
                'last_name' => 'Smith',
                'password' => Hash::make('password'),
                'role_id' => $roles['doctor']->id,
            ]
        );

        $doctor = Doctor::firstOrCreate(
            ['user_id' => $doctorUser->id],
            [
                'salary_id' => $doctorSalary->id,
                'clinic_id' => $clinic1->id,
                'specialty' => 'Cardiology',
                'workdays' => json_encode(['Monday', 'Wednesday', 'Friday']),
            ]
        );

        // Create doctor schedules
        $scheduleData = [
            ['day' => 'monday', 'start_time' => '09:00:00', 'end_time' => '17:00:00'],
            ['day' => 'wednesday', 'start_time' => '09:00:00', 'end_time' => '17:00:00'],
            ['day' => 'friday', 'start_time' => '09:00:00', 'end_time' => '17:00:00']
        ];

        foreach ($scheduleData as $schedule) {
            DoctorSchedule::firstOrCreate(
                ['doctor_id' => $doctor->id, 'day' => $schedule['day']],
                $schedule
            );
        }

        // Create time slots for the next 7 days
        $timeSlots = [];
        $appointmentDate = now()->addDays(7)->format('Y-m-d');

        $morningSlots = [
            ['09:00:00', '10:00:00'],
            ['10:00:00', '11:00:00'],
            ['11:00:00', '12:00:00']
        ];

        $afternoonSlots = [
            ['13:00:00', '14:00:00'],
            ['14:00:00', '15:00:00'],
            ['15:00:00', '16:00:00']
        ];

        for ($i = 1; $i <= 7; $i++) {
            $date = now()->addDays($i)->format('Y-m-d');

            foreach ($morningSlots as $slot) {
                $timeSlots[] = [
                    'doctor_id' => $doctor->id,
                    'date' => $date,
                    'start_time' => $slot[0],
                    'end_time' => $slot[1],
                    'is_booked' => false,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            foreach ($afternoonSlots as $slot) {
                $timeSlots[] = [
                    'doctor_id' => $doctor->id,
                    'date' => $date,
                    'start_time' => $slot[0],
                    'end_time' => $slot[1],
                    'is_booked' => false,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        }

        // Insert time slots only if they don't exist
        foreach ($timeSlots as $slot) {
            TimeSlot::firstOrCreate(
                [
                    'doctor_id' => $slot['doctor_id'],
                    'date' => $slot['date'],
                    'start_time' => $slot['start_time']
                ],
                $slot
            );
        }

        MedicalCenterWallet::firstOrCreate([], ['balance' => 0]);

        // Get a specific time slot for the appointment
        $appointmentSlot = TimeSlot::where('doctor_id', $doctor->id)
            ->where('date', $appointmentDate)
            ->where('start_time', '09:00:00')
            ->first();

        // Create appointment if slot exists
        if ($appointmentSlot) {
            $appointmentSlot->update(['is_booked' => true]);

            Appointment::firstOrCreate(
                [
                    'patient_id' => $patient->id,
                    'doctor_id' => $doctor->id,
                    'appointment_date' => $appointmentDate . ' 09:00:00'
                ],
                [
                    'clinic_id' => $clinic1->id,
                    'time_slot_id' => $appointmentSlot->id,
                    'reason' => 'Initial consultation',
                    'status' => 'confirmed',
                    'price' => 100.00,
                    'fee' => 80.00,
                ]
            );
        }
    }
}
