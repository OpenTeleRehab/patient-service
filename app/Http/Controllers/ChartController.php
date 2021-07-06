<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class ChartController extends Controller
{
    const MIN_AGE = 0;
    const MAX_AGE = 80;
    const AGE_GAP = 10;
    /**
     * @return array
     */
    public function getDataForGlobalAdmin()
    {
        $patientsByGenderGroupedByCountry = DB::table('users')
            ->select(DB::raw('
                country_id,
                SUM(CASE WHEN gender = "male" THEN 1 ELSE 0 END) AS male,
                SUM(CASE WHEN gender = "female" THEN 1 ELSE 0 END) AS female,
                SUM(CASE WHEN gender = "other" THEN 1 ELSE 0 END) AS other
            '))
            ->where('users.enabled', '=', 1)
            ->where('deleted_at', '=', null)->groupBy('country_id')
            ->get();

        $onGoingTreatmentsByGenderGroupedByCountry = DB::table('users')
            ->select(DB::raw('
                country_id,
                SUM(CASE WHEN gender = "male" AND start_date <= CURDATE() AND end_date >= CURDATE() THEN 1 ELSE 0 END) AS male,
                SUM(CASE WHEN gender = "female" AND start_date <= CURDATE() AND end_date >= CURDATE() THEN 1 ELSE 0 END) AS female,
                SUM(CASE WHEN gender = "other" AND start_date <= CURDATE() AND end_date >= CURDATE() THEN 1 ELSE 0 END) AS other
            '))
            ->join('treatment_plans', 'users.id', 'treatment_plans.patient_id')
            ->where('users.enabled', '=', 1)
            ->where('deleted_at', '=', null)
            ->groupBy('country_id')
            ->get();


        $treatmentsByGender = DB::table('treatment_plans')
            ->select(DB::raw('
                country_id,
                SUM(CASE WHEN gender = "male" THEN 1 ELSE 0 END) AS male,
                SUM(CASE WHEN gender = "female" THEN 1 ELSE 0 END) AS female,
                SUM(CASE WHEN gender = "other" THEN 1 ELSE 0 END) AS other
            '))
            ->leftJoin('users', 'treatment_plans.patient_id', 'users.id')
            ->where('users.enabled', '=', 1)
            ->where('deleted_at', '=', null)
            ->groupBy('country_id')
            ->get();

        $patientsByAgeGapGroupedByCountryColumns = '';
        $onGoingTreatmentsByAgeGapGroupedByCountryColumns = '';

        for ($i = self::MIN_AGE; $i <= self::MAX_AGE; ($i += self::AGE_GAP)) {
            if ($i === self::MIN_AGE) {
                $patientsByAgeGapGroupedByCountryColumns .= '
                    SUM(CASE WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ' . $i .
                    ' AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < ' . ($i + 10) .
                    ' THEN 1 ELSE 0 END) AS `< ' . ($i + self::AGE_GAP) . '`,';

                $onGoingTreatmentsByAgeGapGroupedByCountryColumns .= '
                    SUM(CASE WHEN start_date <= CURDATE() AND end_date >= CURDATE()
                    AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ' . $i .
                    ' AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < ' . ($i + 10) .
                    ' THEN 1 ELSE 0 END) AS `< ' . ($i + self::AGE_GAP) . '`,';
            } elseif ($i < self::MAX_AGE) {
                $patientsByAgeGapGroupedByCountryColumns .= '
                    SUM(CASE WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ' . $i .
                    ' AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < ' . ($i + 10) .
                    ' THEN 1 ELSE 0 END) AS `' . $i . ' - ' . ($i + self::AGE_GAP) . '`,';

                $onGoingTreatmentsByAgeGapGroupedByCountryColumns .= '
                    SUM(CASE WHEN start_date <= CURDATE() AND end_date >= CURDATE()
                    AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ' . $i .
                    ' AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < ' . ($i + 10) .
                    ' THEN 1 ELSE 0 END) AS `' . $i . ' - ' . ($i + self::AGE_GAP) . '`,';
            } else {
                $patientsByAgeGapGroupedByCountryColumns .= '
                    SUM(CASE WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ' . $i .
                    ' THEN 1 ELSE 0 END) AS `>=' . $i . '`';

                $onGoingTreatmentsByAgeGapGroupedByCountryColumns .= '
                    SUM(CASE WHEN start_date <= CURDATE() AND end_date >= CURDATE()
                    AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ' . $i .
                    ' THEN 1 ELSE 0 END) AS `>=' . $i . '`';
            }
        }

        $patientsByAgeGapGroupedByCountry = DB::table('users')
            ->select(DB::raw('
                country_id, ' . $patientsByAgeGapGroupedByCountryColumns))
            ->where('users.enabled', '=', 1)
            ->where('deleted_at', '=', null)
            ->groupBy('country_id')
            ->get();

        $onGoingTreatmentsByAgeGapGroupedByCountry = DB::table('users')
            ->select(DB::raw('
                country_id, ' . $onGoingTreatmentsByAgeGapGroupedByCountryColumns))
            ->where('users.enabled', '=', 1)
            ->where('deleted_at', '=', null)
            ->groupBy('country_id')
            ->join('treatment_plans', 'users.id', 'treatment_plans.patient_id')
            ->get();

        $treatmentsByAgeGapGroupedByCountry = DB::table('treatment_plans')
            ->select(DB::raw('
                country_id, ' . $patientsByAgeGapGroupedByCountryColumns))
            ->where('users.enabled', '=', 1)
            ->where('deleted_at', '=', null)
            ->groupBy('country_id')
            ->join('users', 'treatment_plans.patient_id', 'users.id')
            ->get();

        return [
            'patientsByGenderGroupedByCountry' => $patientsByGenderGroupedByCountry,
            'onGoingTreatmentsByGenderGroupedByCountry' => $onGoingTreatmentsByGenderGroupedByCountry,
            'treatmentsByGender' => $treatmentsByGender,
            'patientsByAgeGapGroupedByCountry' => $patientsByAgeGapGroupedByCountry,
            'onGoingTreatmentsByAgeGapGroupedByCountry' => $onGoingTreatmentsByAgeGapGroupedByCountry,
            'treatmentsByAgeGapGroupedByCountry' => $treatmentsByAgeGapGroupedByCountry,
        ];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getDataForCountryAdmin(Request $request)
    {
        $country_id = $request->get('country_id');

        $patientsByGenderGroupedByClinic = DB::table('users')
            ->select(DB::raw('
                clinic_id,
                SUM(CASE WHEN gender = "male" THEN 1 ELSE 0 END) AS male,
                SUM(CASE WHEN gender = "female" THEN 1 ELSE 0 END) AS female,
                SUM(CASE WHEN gender = "other" THEN 1 ELSE 0 END) AS other
            '))
            ->where('country_id', $country_id)
            ->where('users.enabled', '=', 1)
            ->where('deleted_at', '=', null)
            ->groupBy('clinic_id')
            ->get();

        $onGoingTreatmentsByGenderGroupedByClinic = DB::table('users')
            ->select(DB::raw('
                clinic_id,
                SUM(CASE WHEN gender = "male" AND start_date <= CURDATE() AND end_date >= CURDATE() THEN 1 ELSE 0 END) AS male,
                SUM(CASE WHEN gender = "female" AND start_date <= CURDATE() AND end_date >= CURDATE() THEN 1 ELSE 0 END) AS female,
                SUM(CASE WHEN gender = "other" AND start_date <= CURDATE() AND end_date >= CURDATE() THEN 1 ELSE 0 END) AS other
            '))
            ->join('treatment_plans', 'users.id', 'treatment_plans.patient_id')
            ->where('country_id', $country_id)
            ->where('users.enabled', '=', 1)
            ->where('deleted_at', '=', null)
            ->groupBy('clinic_id')
            ->get();

        $patientsByAgeGapGroupedByClinicColumns = '';
        $onGoingTreatmentsByAgeGapGroupedByClinicColumns = '';

        for ($i = self::MIN_AGE; $i <= self::MAX_AGE; ($i += self::AGE_GAP)) {
            if ($i === self::MIN_AGE) {
                $patientsByAgeGapGroupedByClinicColumns .= '
                    SUM(CASE WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ' . $i .
                    ' AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) <= ' . ($i + 10) .
                    ' THEN 1 ELSE 0 END) AS `< ' . ($i + self::AGE_GAP) . '`,';

                $onGoingTreatmentsByAgeGapGroupedByClinicColumns .= '
                    SUM(CASE WHEN start_date <= CURDATE() AND end_date >= CURDATE()
                    AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ' . $i .
                    ' AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) <= ' . ($i + 10) .
                    ' THEN 1 ELSE 0 END) AS `< ' . ($i + self::AGE_GAP) . '`,';
            } elseif ($i < self::MAX_AGE) {
                $patientsByAgeGapGroupedByClinicColumns .= '
                    SUM(CASE WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ' . $i .
                    ' AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) <= ' . ($i + 10) .
                    ' THEN 1 ELSE 0 END) AS `' . $i . ' - ' . ($i + self::AGE_GAP) . '`,';

                $onGoingTreatmentsByAgeGapGroupedByClinicColumns .= '
                    SUM(CASE WHEN start_date <= CURDATE() AND end_date >= CURDATE()
                    AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ' . $i .
                    ' AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) <= ' . ($i + 10) .
                    ' THEN 1 ELSE 0 END) AS `' . $i . ' - ' . ($i + self::AGE_GAP) . '`,';
            } else {
                $patientsByAgeGapGroupedByClinicColumns .= '
                    SUM(CASE WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ' . $i .
                    ' THEN 1 ELSE 0 END) AS `>=' . $i . '`';

                $onGoingTreatmentsByAgeGapGroupedByClinicColumns .= '
                    SUM(CASE WHEN start_date <= CURDATE() AND end_date >= CURDATE()
                    AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ' . $i .
                    ' THEN 1 ELSE 0 END) AS `>=' . $i . '`';
            }
        }

        $patientsByAgeGapGroupedByClinic = DB::table('users')
            ->select(DB::raw('
                clinic_id, ' . $patientsByAgeGapGroupedByClinicColumns))
            ->where('country_id', $country_id)
            ->where('users.enabled', '=', 1)
            ->where('deleted_at', '=', null)
            ->groupBy('clinic_id')
            ->get();

        $onGoingTreatmentsByAgeGapGroupedByClinic = DB::table('users')
            ->select(DB::raw('
                clinic_id, ' . $onGoingTreatmentsByAgeGapGroupedByClinicColumns))
            ->groupBy('clinic_id')
            ->join('treatment_plans', 'users.id', 'treatment_plans.patient_id')
            ->where('country_id', $country_id)
            ->where('users.enabled', '=', 1)
            ->where('deleted_at', '=', null)
            ->get();

        return [
            'patientsByGenderGroupedByClinic' => $patientsByGenderGroupedByClinic,
            'onGoingTreatmentsByGenderGroupedByClinic' => $onGoingTreatmentsByGenderGroupedByClinic,
            'patientsByAgeGapGroupedByClinic' => $patientsByAgeGapGroupedByClinic,
            'onGoingTreatmentsByAgeGapGroupedByClinic' => $onGoingTreatmentsByAgeGapGroupedByClinic
        ];
    }

    /**
     * @param Request $request
     * @return array
     */
    public function getDataForClinicAdmin(Request $request)
    {
        $clinicId = $request->get('clinic_id');
        $patientTotal = User::where('clinic_id', $clinicId)
            ->where('users.enabled', '=', 1)
            ->where('deleted_at', '=', null)
            ->count();

        $onGoingTreatmentsByClinic = DB::table('users')
            ->select(DB::raw('
                COUNT(*) AS total
            '))
            ->whereDate('start_date', '<=', Carbon::now())
            ->whereDate('end_date', '>=', Carbon::now())
            ->where('users.enabled', '=', 1)
            ->where('deleted_at', '=', null)
            ->groupBy('clinic_id')
            ->join('treatment_plans', 'users.id', 'treatment_plans.patient_id')
            ->where('clinic_id', $clinicId)
            ->count();
        return [
          'patientTotal' => $patientTotal,
          'onGoingTreatments' => $onGoingTreatmentsByClinic
        ];
    }
}
