<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Patient;
use App\Models\User;
use App\Models\Appointment;

class BlockedPatientsSeeder extends Seeder
{
    public function run(): void
    {
        // إنشاء 3 مرضى
        for ($p = 1; $p <= 3; $p++) {

            // إنشاء يوزر للمريض
            $user = User::create([
                'first_name' => "Test$p",
                'last_name'  => "Patient$p",
                'email'      => "testpatient$p@example.com",
                'phone'      => "12345678$p",
                'role_id'    => 4, // Patient role
                'password'   => bcrypt('password'),
            ]);

            // ربطه بموديل Patient
            $patient = Patient::create([
                'user_id' => $user->id,
            ]);

            // إنشاء 5 مواعيد Absent لكل مريض
            for ($i = 0; $i < 5; $i++) {
                Appointment::create([
                    'patient_id'       => $patient->id,
                    'doctor_id'        => 1, // اختر دكتور موجود أو أنشئ دكتور dummy
                    'clinic_id'        => 1, // اختر عيادة موجودة أو أنشئ dummy
                    'time_slot_id'     => 1, // اختر time_slot موجود
                    'status'           => 'absent',
                    'appointment_date' => now()->addDays($i),
                ]);
            }
        }
    }
}
