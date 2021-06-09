<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RemoveAppointment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:appointment-cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove appointment records - 1 year';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        Appointment::whereDate('end_date', '<=', Carbon::now()->subYears(1))->each(function ($item) {
            $item->forceDelete();
        });

        $this->info('Appointment has been remove successfully');
    }
}
