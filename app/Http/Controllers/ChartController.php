<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class ChartController extends Controller
{
    /**
     * @return array
     */
    public function getDataForGlobalAdmin()
    {
        $patientsByGenderGroupedByCountry = DB::table('users')
            ->select(DB::raw('
                country_id,
                SUM(CASE WHEN gender = "male" THEN 1 ELSE 0 END) AS male,
                SUM(CASE WHEN gender = "female" THEN 1 ELSE 0 END) AS female
            '))->groupBy('country_id')
            ->get();

        $onGoingTreatmentsByGenderGroupedByCountry = DB::table('users')
            ->select(DB::raw('
                country_id,
                SUM(CASE WHEN gender = "male" AND start_date <= NOW() AND end_date >= NOW() THEN 1 ELSE 0 END) AS male,
                SUM(CASE WHEN gender = "female" AND start_date <= NOW() AND end_date >= NOW() THEN 1 ELSE 0 END) AS female
            '))
            ->join('treatment_plans', 'users.id', 'treatment_plans.patient_id')
            ->groupBy('country_id')
            ->get();

        return [
            'patientsByGenderGroupedByCountry' => $patientsByGenderGroupedByCountry,
            'onGoingTreatmentsByGenderGroupedByCountry' => $onGoingTreatmentsByGenderGroupedByCountry,
        ];
    }
}
