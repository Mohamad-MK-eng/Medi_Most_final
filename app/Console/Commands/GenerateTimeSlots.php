<?php
namespace App\Console\Commands;

use App\Models\Doctor;
use App\Http\Controllers\AdminController;
use Illuminate\Console\Command;

class GenerateTimeSlots extends Command
{
    protected $signature = 'slots:generate';
    protected $description = 'Generate time slots for next 30 days';

   public function handle()
{
    $doctors = Doctor::all();
    $controller = new AdminController;

    foreach ($doctors as $doctor) {
        // Use doctor's slot_duration instead of hardcoded 30
        $controller->generateTimeSlotsForDoctor(
            $doctor,
            30,
            $doctor->slot_duration
        );
    }

    $this->info('Generated slots for '.count($doctors).' doctors');
}
}

