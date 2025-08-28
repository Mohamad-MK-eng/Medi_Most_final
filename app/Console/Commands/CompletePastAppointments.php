<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Appointment;
use Carbon\Carbon;

class CompletePastAppointments extends Command
{
    protected $signature = 'appointments:complete';
    protected $description = 'Mark past appointments as completed';

   public function handle()
{
    $now = Carbon::now('Asia/Damascus');
    $cutoffTime = $now->copy()->subMinutes(15);

    // Update appointments that are confirmed and past their time + 15 minutes
    $updated = Appointment::where('status', 'confirmed')
        ->where('appointment_date', '<=', $cutoffTime)
        ->update(['status' => 'completed']);

    $this->info("Marked $updated appointments as completed.");
}
}
