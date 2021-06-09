<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Models\TreatmentPlan;

class RemoveTreatmentPlan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:treatment-cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove completed treatment plan - 6 year';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        TreatmentPlan::whereDate('end_date', '<=', Carbon::now()->subYears(6))->each(function ($item) {
             $item->forceDelete();
        });

        $this->info('Treatment has been remove successfully');
    }
}
