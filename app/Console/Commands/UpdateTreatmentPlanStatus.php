<?php

namespace App\Console\Commands;

use App\Helpers\TreatmentPlanHelper;
use Illuminate\Console\Command;
use App\Models\TreatmentPlan;
use Illuminate\Support\Facades\DB;

class UpdateTreatmentPlanStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:update-treatment-plan-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update treatment plan status daily';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        TreatmentPlan::chunk(1000, function ($treatmentPlans) {
            foreach ($treatmentPlans as $treatmentPlan) {
                $status = TreatmentPlanHelper::determineStatus($treatmentPlan->start_date, $treatmentPlan->end_date);
                DB::table('treatment_plans')
                    ->where('id', $treatmentPlan->id)
                    ->update([
                        'status' => $status,
                        'updated_at' => now(),
                    ]);
            }
        });

        $this->info('Treatment has been update successfully');
    }
}
