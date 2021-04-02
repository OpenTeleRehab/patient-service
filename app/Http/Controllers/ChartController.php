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
        $minAge = 0;
        $maxAge = 100;
        $ageGap = 10;

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

        $patientsByAgeGapGroupedByCountryColumns = '';
        $onGoingTreatmentsByAgeGapGroupedByCountryColumns = '';

        for ($i = $minAge; $i <= $maxAge; ($i += $ageGap)) {
            if ($i < $maxAge) {
                $patientsByAgeGapGroupedByCountryColumns .= '
                    SUM(CASE WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ' . $i .
                    ' AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) <= ' . ($i + 10) .
                    ' THEN 1 ELSE 0 END) AS `' . $i . ' - ' . ($i + $ageGap) . '`,';

                $onGoingTreatmentsByAgeGapGroupedByCountryColumns .= '
                    SUM(CASE WHEN start_date <= NOW() AND end_date >= NOW()
                    AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ' . $i .
                    ' AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) <= ' . ($i + 10) .
                    ' THEN 1 ELSE 0 END) AS `' . $i . ' - ' . ($i + $ageGap) . '`,';
            } else {
                $patientsByAgeGapGroupedByCountryColumns .= '
                    SUM(CASE WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ' . $i .
                    ' THEN 1 ELSE 0 END) AS `>=' . $i . '`';

                $onGoingTreatmentsByAgeGapGroupedByCountryColumns .= '
                    SUM(CASE WHEN start_date <= NOW() AND end_date >= NOW()
                    AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ' . $i .
                    ' THEN 1 ELSE 0 END) AS `>=' . $i . '`';
            }
        }

        $patientsByAgeGapGroupedByCountry = DB::table('users')
            ->select(DB::raw('
                country_id, ' . $patientsByAgeGapGroupedByCountryColumns))
            ->groupBy('country_id')
            ->get();

        $onGoingTreatmentsByAgeGapGroupedByCountry = DB::table('users')
            ->select(DB::raw('
                country_id, ' . $onGoingTreatmentsByAgeGapGroupedByCountryColumns))
            ->groupBy('country_id')
            ->join('treatment_plans', 'users.id', 'treatment_plans.patient_id')
            ->get();

        return [
            'patientsByGenderGroupedByCountry' => $patientsByGenderGroupedByCountry,
            'onGoingTreatmentsByGenderGroupedByCountry' => $onGoingTreatmentsByGenderGroupedByCountry,
            'patientsByAgeGapGroupedByCountry' => $patientsByAgeGapGroupedByCountry,
            'onGoingTreatmentsByAgeGapGroupedByCountry' => $onGoingTreatmentsByAgeGapGroupedByCountry,
        ];
    }
}
