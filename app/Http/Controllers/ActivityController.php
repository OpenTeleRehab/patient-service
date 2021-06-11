<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\TreatmentPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ActivityController extends Controller
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getByIds(Request $request)
    {
        $activityIds = $request->get('activity_ids', []);
        $treatmentPlanId = $request->get('treatment_plan_id');
        $type = $request->get('type');
        $day = $request->get('day');
        $week = $request->get('week');
        $activities = Activity::whereIn('activity_id', $activityIds)
            ->where('type', $type)
            ->where('treatment_plan_id', $treatmentPlanId)
            ->where('day', $day)
            ->where('week', $week)
            ->get();

        $activitiesObjIds = [];
        $result = [];
        foreach ($activities as $activity) {
            if ($activity->type === Activity::ACTIVITY_TYPE_EXERCISE) {
                $type = Activity::ACTIVITY_TYPE_EXERCISE;
            } elseif ($activity->type === Activity::ACTIVITY_TYPE_MATERIAL) {
                $type = Activity::ACTIVITY_TYPE_MATERIAL;
            } else {
                $type = Activity::ACTIVITY_TYPE_QUESTIONNAIRE;
            }

            $response = $this->getActivitiesFromAdminService($type, $activity->activity_id, $request);
            if (!empty($response) && $response->successful()) {
                if ($response->json()['data']) {
                    $activityObj = $response->json()['data'][0];
                    $activityObj['id'] = $activity->activity_id;

                    // Custom Sets/Reps in Treatment.
                    if ($activity->sets !== null) {
                        $activityObj['sets'] = $activity->sets;
                    }
                    if ($activity->reps !== null) {
                        $activityObj['reps'] = $activity->reps;
                    }
                } else {
                    continue;
                }
            }

            $result[] = array_merge([
                'created_by' => $activity->created_by,
                'additional_information' => $activity->additional_information,
            ], $activityObj);

            array_push($activitiesObjIds, $activity->activity_id);
        }

        if ($activityIds) {
            if ($activitiesObjIds) {
                $newActivityIds = array_values(array_diff($activityIds, $activitiesObjIds));
            } else {
                $newActivityIds = $activityIds;
            }

            $newActivityObj = [];
            foreach ($newActivityIds as $id) {
                $response = $this->getActivitiesFromAdminService($type, $id, $request);
                if (!empty($response) && $response->successful()) {
                    if ($response->json()['data']) {
                        $newActivityObj = $response->json()['data'][0];
                    } else {
                        continue;
                    }
                }

                array_push($result, $newActivityObj);
            }
        }

        return ['success' => true, 'data' => $result];
    }

    /**
     * @param Request $request
     * @return array
     */
    public static function deleteByIds(Request $request)
    {
        $activitiesIds = $request->get('activity_ids', []);
        Log::debug($activitiesIds);

        $type = $request->get('type');
        Activity::where('type', $type)->whereIn('activity_id', $activitiesIds)->delete();

        return ['success' => true, 'message' => 'message.activity_delete'];
    }

    /**
     * @param string $type
     * @param integer $activityIds
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Client\Response
     */
    private function getActivitiesFromAdminService($type, $activityIds, Request $request)
    {
        if ($type === Activity::ACTIVITY_TYPE_EXERCISE) {
            $response = Http::get(env('ADMIN_SERVICE_URL') . '/api/exercise/list/by-ids', [
                'exercise_ids' => [$activityIds],
                'lang' => $request->get('lang'),
                'therapist_id' => $request->get('therapist_id')
            ]);
        } elseif ($type === Activity::ACTIVITY_TYPE_MATERIAL) {
            $response = Http::get(env('ADMIN_SERVICE_URL') . '/api/education-material/list/by-ids', [
                'material_ids' => [$activityIds],
                'lang' => $request->get('lang'),
                'therapist_id' => $request->get('therapist_id')
            ]);
        } else {
            $response = Http::get(env('ADMIN_SERVICE_URL') . '/api/questionnaire/list/by-ids', [
                'questionnaire_ids' => [$activityIds],
                'lang' => $request->get('lang'),
                'therapist_id' => $request->get('therapist_id')
            ]);
        }

        return $response;
    }
}
