<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Forwarder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ActivityController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/activities/list/by-filters",
     *     tags={"Activity"},
     *     summary="Activities list by filters",
     *     operationId="activitiesListByFilters",
     *     @OA\Parameter(
     *         name="activity_id",
     *         in="query",
     *         description="Activity id",
     *         required=false,
     *          @OA\Schema(
     *              type="integer"
     *          ),
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="type",
     *         required=false,
     *          @OA\Schema(
     *              type="string",
     *              enum={"exercise", "material", "questionnaire"}
     *          ),
     *     ),
     *     @OA\Parameter(
     *         name="completed",
     *         in="query",
     *         description="completed",
     *         required=false,
     *          @OA\Schema(
     *              type="boolean"
     *          ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getActivities(Request $request)
    {
        $query = Activity::query();

        if ($request->has('type')) {
            $query->where('type', $request->get('type'));
        }
        if ($request->has('activity_id')) {
            $query->where('activity_id', $request->get('activity_id'));
        }
        if ($request->has('completed')) {
            $query->where('completed', $request->boolean('completed'));
        }
        $activities = $query->with(['treatmentPlan', 'treatmentPlan.user'])->with('answers')->get();

        return ['success' => true, 'data' => $activities];
    }

    /**
     * @OA\Get(
     *     path="/api/activities/list/by-ids",
     *     tags={"Activity"},
     *     summary="Activities list by ids",
     *     operationId="activitiesListByIds",
     *     @OA\Parameter(
     *         name="activity_ids[]",
     *         in="query",
     *         description="Activity ids",
     *         required=true,
     *          @OA\Schema(
     *              type="array",
     *              @OA\Items( type="integer"),
     *          ),
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="type",
     *         required=true,
     *          @OA\Schema(
     *              type="string",
     *              enum={"exercise", "material", "questionnaire"}
     *          ),
     *     ),
     *     @OA\Parameter(
     *         name="day",
     *         in="query",
     *         description="day",
     *         required=true,
     *          @OA\Schema(
     *              type="integer"
     *          ),
     *     ),
     *     @OA\Parameter(
     *         name="week",
     *         in="query",
     *         description="week",
     *         required=true,
     *          @OA\Schema(
     *              type="integer"
     *          ),
     *     ),
     *     @OA\Parameter(
     *         name="treatment_plan_id",
     *         in="query",
     *         description="Treatment plan id",
     *         required=true,
     *          @OA\Schema(
     *              type="integer"
     *          ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
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
            $response = $this->getActivitiesFromAdminService($activity->type, $activity->activity_id, $request);
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
     * @OA\Post(
     *     path="/api/activities/delete/by-ids",
     *     tags={"Activity"},
     *     summary="Activities delete by ids",
     *     operationId="activitiesDeleteByIds",
     *     @OA\Parameter(
     *         name="activity_ids[]",
     *         in="query",
     *         description="Activity ids",
     *         required=true,
     *          @OA\Schema(
     *              type="array",
     *              @OA\Items( type="integer"),
     *          ),
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="type",
     *         required=true,
     *          @OA\Schema(
     *              type="string",
     *              enum={"exercise", "material", "questionnaire"}
     *          ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @param Request $request
     * @return array
     */
    public static function deleteByIds(Request $request)
    {
        $activitiesIds = $request->get('activity_ids', []);

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
        $access_token = Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE);

        if ($type === Activity::ACTIVITY_TYPE_EXERCISE) {
            $response = Http::withToken($access_token)->get(env('ADMIN_SERVICE_URL') . '/exercise/list/by-ids', [
                'exercise_ids' => [$activityIds],
                'lang' => $request->get('lang'),
            ]);
        } elseif ($type === Activity::ACTIVITY_TYPE_MATERIAL) {
            $response = Http::withToken($access_token)->get(env('ADMIN_SERVICE_URL') . '/education-material/list/by-ids', [
                'material_ids' => [$activityIds],
                'lang' => $request->get('lang'),
            ]);
        } else {
            $response = Http::withToken($access_token)->get(env('ADMIN_SERVICE_URL') . '/questionnaire/list/by-ids', [
                'questionnaire_ids' => [$activityIds],
                'lang' => $request->get('lang'),
            ]);
        }

        return $response;
    }
}
