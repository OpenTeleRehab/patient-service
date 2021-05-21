<?php

namespace App\Http\Controllers;

use App\Http\Resources\ActivityObjResource;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ActivityController extends Controller
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
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
        foreach ($activities as $key => $activity) {
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
                } else {
                    continue;
                }
            }

            $result[] = array_merge([
                'created_by' => $activity->created_by
            ], $activityObj);

            array_push($activitiesObjIds, $activity->activity_id);
        }

        if ($activityIds) {
            if ($activitiesObjIds) {
                $newActivityIds = array_values(array_diff($activityIds, $activitiesObjIds));
            } else {
                $newActivityIds = $activityIds;
            }

            $response = $this->getActivitiesFromAdminService($type, $newActivityIds, $request);
            if (!empty($response) && $response->successful() && $response->json()['data']) {
                $newActivityObj = $response->json()['data'][0];
                array_push($result, $newActivityObj);
            }
        }

        return ['success' => true, 'data' => $result];
    }

    /**
     * @param string $type
     * @param integer $activityIds
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Client\Response
     */
    private function getActivitiesFromAdminService($type, $activityIds, Request $request)
    {
        $response = null;
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
