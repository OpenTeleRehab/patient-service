<?php

namespace App\Helpers;

use Carbon\Carbon;
use App\Models\TreatmentPlan;

class TreatmentPlanHelper
{
    public static function determineStatus(string $startDateStr, string $endDateStr): ?string
    {
        $today = Carbon::today();
        $startDate = Carbon::parse($startDateStr);
        $endDate = Carbon::parse($endDateStr);

        if ($startDate->lte($today) && $endDate->gte($today)) {
            return TreatmentPlan::STATUS_ON_GOING;
        } elseif ($startDate->gt($today) && $endDate->gt($today)) {
            return TreatmentPlan::STATUS_PLANNED;
        } elseif ($startDate->lt($today) && $endDate->lt($today)) {
            return TreatmentPlan::STATUS_FINISHED;
        }

        return null;
    }
}