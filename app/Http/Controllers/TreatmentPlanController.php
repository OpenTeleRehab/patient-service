<?php

namespace App\Http\Controllers;

use App\Events\PodcastCalculatorEvent;
use App\Exports\TreatmentPlanExport;
use App\Helpers\TreatmentActivityHelper;
use App\Http\Resources\GoalResource;
use App\Http\Resources\TreatmentPlanResource;
use App\Models\Activity;
use App\Models\Forwarder;
use App\Models\Goal;
use App\Models\QuestionnaireAnswer;
use App\Models\TreatmentPlan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class TreatmentPlanController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/treatment-plan",
     *     tags={"Treatment plan"},
     *     summary="Treatment plan list",
     *     operationId="treatmentPlanList",
     *     @OA\Parameter(
     *         name="page_size",
     *         in="query",
     *         description="Limit",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
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
    public function index(Request $request)
    {
        $data = $request->all();
        $info = [];
        if ($request->has('id')) {
            $treatmentPlans = TreatmentPlan::where('id', $request->get('id'))->get();
        } else {
            $query = TreatmentPlan::query();

            if (isset($data['patient_id'])) {
                $query = TreatmentPlan::where('patient_id', $data['patient_id']);
            }

            if (isset($data['search_value'])) {
                $query->where(function ($query) use ($data) {
                    $query->where('name', 'like', '%' . $data['search_value'] . '%');
                });
            }

            if (isset($data['filters'])) {
                $filters = $request->get('filters');
                $query->where(function ($query) use ($filters) {
                    foreach ($filters as $filter) {
                        $filterObj = json_decode($filter);
                        if ($filterObj->columnName === 'treatment_status') {
                            $status = $filterObj->value;
                            switch ($status) {
                                case TreatmentPlan::FILTER_STATUS_PLANNED:
                                    $query->whereDate('start_date', '>', Carbon::now());
                                    break;
                                case TreatmentPlan::FILTER_STATUS_FINISHED:
                                    $query->whereDate('end_date', '<', Carbon::now());
                                    break;
                                case TreatmentPlan::FILTER_STATUS_ON_GOING:
                                    $query->whereDate('start_date', '<=', Carbon::now());
                                    $query->whereDate('end_date', '>=', Carbon::now());
                            }
                        } elseif ($filterObj->columnName === 'start_date' || $filterObj->columnName === 'end_date') {
                            $dates = explode(' - ', $filterObj->value);
                            $startDate = date_create_from_format('d/m/Y', $dates[0]);
                            $endDate = date_create_from_format('d/m/Y', $dates[1]);
                            $startDate->format('Y-m-d');
                            $endDate->format('Y-m-d');
                            $query->whereDate($filterObj->columnName, '>=', $startDate)
                                ->whereDate($filterObj->columnName, '<=', $endDate);
                        } else {
                            $query->where($filterObj->columnName, 'like', '%' .  $filterObj->value . '%');
                        }
                    }
                });
            }

            $treatmentPlans = $query->paginate($data['page_size']);
            $info = [
                'current_page' => $treatmentPlans->currentPage(),
                'total_count' => $treatmentPlans->total(),
            ];
        }

        return ['success' => true, 'data' => TreatmentPlanResource::collection($treatmentPlans), 'info' => $info];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array|void
     */
    public function store(Request $request)
    {
        $name = $request->get('name');
        $description = $request->get('description');
        $patientId = $request->get('patient_id');
        $therapistId = $request->get('therapist_id');
        $disease_id = $request->get('disease_id');

        $startDate = date_create_from_format(config('settings.date_format'), $request->get('start_date'))->format('Y-m-d');
        $endDate = date_create_from_format(config('settings.date_format'), $request->get('end_date'))->format('Y-m-d');

        // Check if there is any overlap schedule.
        $overlapRecords = TreatmentPlan::where('patient_id', $patientId)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere('start_date', '<' , $startDate)->where('end_date', '>', $startDate);
            })->get();

        if (count($overlapRecords)) {
            return ['success' => false, 'message' => 'error_message.treatment_plan_assign_to_patient_overlap_schedule'];
        }

        // Check if ongoing treatment over the limit.
        $overLimit = $this->validateOngoingTreatmentOverLimit($startDate, $endDate, $therapistId);
        if (!empty($overLimit)) {
            return $overLimit;
        }

        $treatmentPlan = TreatmentPlan::create([
            'name' => $name,
            'description' => $description,
            'patient_id' => $patientId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => TreatmentPlan::STATUS_PLANNED,
            'total_of_weeks' => $request->get('total_of_weeks', 1),
            'created_by' => $therapistId,
            'disease_id' => $disease_id,
        ]);

        if (!$treatmentPlan) {
            return ['success' => false, 'message' => 'error_message.treatment_plan_assign_to_patient'];
        }

        $this->updateOrCreateGoals($treatmentPlan->id, $request->get('goals', []));
        $this->updateOrCreateActivities($treatmentPlan->id, $request->get('activities', []), $therapistId);
        return ['success' => true, 'message' => 'success_message.treatment_plan_assign_to_patient', 'data' => $treatmentPlan];
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param int $id
     *
     * @return array
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $patientId = $request->get('patient_id');
        $description = $request->get('description');
        $therapistId = $request->get('therapist_id');
        $startDate = date_create_from_format(config('settings.date_format'), $request->get('start_date'))->format('Y-m-d');
        $endDate = date_create_from_format(config('settings.date_format'), $request->get('end_date'))->format('Y-m-d');
        $disease_id = $request->get('disease_id');

        // Check if there is any overlap schedule.
        $overlapRecords = TreatmentPlan::where('patient_id', $patientId)
            ->where('id', '<>', $id)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate]);
            })->get();

        if (count($overlapRecords)) {
            return ['success' => false, 'message' => 'error_message.treatment_plan_assign_to_patient_overlap_schedule'];
        }

        // Check if ongoing treatment over the limit.
        $overLimit = $this->validateOngoingTreatmentOverLimit($startDate, $endDate, $therapistId, $id);

        if (!empty($overLimit)) {
            return $overLimit;
        }

        $treatmentPlan = TreatmentPlan::find($id);
        $treatmentPlan->update([
            'name' => $request->get('name'),
            'description' => $description,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_of_weeks' => $request->get('total_of_weeks', 1),
            'disease_id' => $disease_id,
        ]);

        $this->updateOrCreateActivities($id, $request->get('activities', []), $therapistId);
        $this->updateOrCreateGoals($id, $request->get('goals', []));

        return ['success' => true, 'message' => 'success_message.treatment_plan_update'];
    }

    /**
     * @param int $treatmentPlanId
     * @param array $goals
     *
     * @return void
     */
    private function updateOrCreateGoals(int $treatmentPlanId, array $goals = [])
    {
        $goalIds = [];
        $user = Auth::user();
        foreach ($goals as $goal) {
            $goalObj = Goal::updateOrCreate(
                [
                    'id' => isset($goal['id']) ? $goal['id'] : null,
                ],
                [
                    'treatment_plan_id' => $treatmentPlanId,
                    'title' => $goal['title'],
                    'frequency' => $goal['frequency'],
                ]
            );
            $goalIds[] = $goalObj->id;
        }

        // Remove deleted goals.
        Goal::where('treatment_plan_id', $treatmentPlanId)
            ->whereNotIn('id', $goalIds)
            ->get()
            ->each(function ($goal) {
                $goal->delete();
            });;

        Activity::where('treatment_plan_id', $treatmentPlanId)
            ->where('type', Activity::ACTIVITY_TYPE_GOAL)
            ->whereNotIn('id', $goalIds)
            ->get()
            ->each(function ($activity) {
                $activity->delete();
            });
    }

    /**
     * @param int $treatmentPlanId
     * @param array $activities
     * @param int $createdBy
     *
     * @return void
     */
    private function updateOrCreateActivities(int $treatmentPlanId, array $activities, $createdBy)
    {
        $activityIds = [];
        $user = Auth::user();
        foreach ($activities as $activity) {
            $customExercises = isset($activity['customExercises']) ? $activity['customExercises'] : [];
            $exercises = isset($activity['exercises']) ? $activity['exercises'] : [];
            $materials = isset($activity['materials']) ? $activity['materials'] : [];
            $questionnaires = isset($activity['questionnaires']) ? $activity['questionnaires'] : [];

            if (count($exercises) > 0) {
                foreach ($exercises as $exercise) {
                    $existedExercise = Activity::where('activity_id', $exercise)
                        ->where('type', Activity::ACTIVITY_TYPE_EXERCISE)
                        ->where('treatment_plan_id', $treatmentPlanId)
                        ->where('day', $activity['day'])
                        ->where('week', $activity['week'])
                        ->first();

                    $updateFields = [
                        'treatment_plan_id' => $treatmentPlanId,
                        'week' => $activity['week'],
                        'day' => $activity['day'],
                        'activity_id' => $exercise,
                        'type' => Activity::ACTIVITY_TYPE_EXERCISE,
                        'created_by' => isset($existedExercise) ? $existedExercise['created_by'] : $createdBy,
                    ];

                    $customExercise = current(array_filter($customExercises, function ($c) use ($exercise) {
                        return $c['id'] === $exercise;
                    }));

                    if ($customExercise) {
                        $updateFields['sets'] = $customExercise['sets'];
                        $updateFields['reps'] = $customExercise['reps'];
                        $updateFields['additional_information'] = $customExercise['additional_information'] ?? null;
                    }

                    $activityObj = Activity::updateOrCreate(
                        ['id' => isset($existedExercise) ? $existedExercise['id'] : null],
                        $updateFields,
                    );
                    $activityIds[] = $activityObj->id;
                }
                // TODO: move to Queued Event Listeners.
                Http::post(env('ADMIN_SERVICE_URL') . '/exercise/mark-as-used/by-ids', [
                    'exercise_ids' => $exercises,
                ]);
            }

            if (count($materials) > 0) {
                foreach ($materials as $material) {
                    $existedMaterial = Activity::where('activity_id', $material)
                        ->where('type', Activity::ACTIVITY_TYPE_MATERIAL)
                        ->where('treatment_plan_id', $treatmentPlanId)
                        ->where('day', $activity['day'])
                        ->where('week', $activity['week'])
                        ->first();

                    $activityObj = Activity::updateOrCreate(
                        [
                            'id' => isset($existedMaterial) ? $existedMaterial['id'] : null,
                        ],
                        [
                            'treatment_plan_id' => $treatmentPlanId,
                            'week' => $activity['week'],
                            'day' => $activity['day'],
                            'activity_id' => $material,
                            'type' => Activity::ACTIVITY_TYPE_MATERIAL,
                            'created_by' => isset($existedMaterial) ? $existedMaterial['created_by'] : $createdBy,
                        ],
                    );
                    $activityIds[] = $activityObj->id;
                }
                // TODO: move to Queued Event Listeners.
                Http::post(env('ADMIN_SERVICE_URL') . '/education-material/mark-as-used/by-ids', [
                    'material_ids' => $materials,
                ]);
            }

            if (count($questionnaires) > 0) {
                foreach ($questionnaires as $questionnaire) {
                    $existedQuestionnaire = Activity::where('activity_id', $questionnaire)
                        ->where('type', Activity::ACTIVITY_TYPE_QUESTIONNAIRE)
                        ->where('treatment_plan_id', $treatmentPlanId)
                        ->where('day', $activity['day'])
                        ->where('week', $activity['week'])
                        ->first();

                    $activityObj = Activity::firstOrCreate(
                        [
                            'id' => isset($existedQuestionnaire) ? $existedQuestionnaire['id'] : null,
                        ],
                        [
                            'treatment_plan_id' => $treatmentPlanId,
                            'week' => $activity['week'],
                            'day' => $activity['day'],
                            'activity_id' => $questionnaire,
                            'type' => Activity::ACTIVITY_TYPE_QUESTIONNAIRE,
                            'created_by' => isset($existedMaterial) ? $existedMaterial['created_by'] : $createdBy,
                        ],
                    );
                    $activityIds[] = $activityObj->id;
                }
                // TODO: move to Queued Event Listeners.
                $access_token = Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE);
                Http::withToken($access_token)->post(env('ADMIN_SERVICE_URL') . '/questionnaire/mark-as-used/by-ids', [
                    'questionnaire_ids' => $questionnaires,
                    'is_used' => true
                ]);
            }
        }

        // Unmark as used for unselect questionnaires.
        $unSelectedQuestionnaireIds = Activity::where('treatment_plan_id', $treatmentPlanId)
            ->where('type', Activity::ACTIVITY_TYPE_QUESTIONNAIRE)
            ->whereNotIn('id', $activityIds)
            ->pluck('activity_id');

        $ongoingTreatmentPlanIds = TreatmentPlan::whereDate('start_date', '<=', Carbon::now())
            ->where('id', '<>', $treatmentPlanId)
            ->whereDate('end_date', '>=', Carbon::now())
            ->pluck('id');

        $questionnaireInUsed = Activity::where('type', Activity::ACTIVITY_TYPE_QUESTIONNAIRE)->whereIn('activity_id', $unSelectedQuestionnaireIds)->whereIn('treatment_plan_id', $ongoingTreatmentPlanIds)->get();

        if (count($questionnaireInUsed) == 0 && count($unSelectedQuestionnaireIds) > 0) {
            $access_token = Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE);
            Http::withToken($access_token)->post(env('ADMIN_SERVICE_URL') . '/questionnaire/mark-as-used/by-ids', [
                'questionnaire_ids' => $unSelectedQuestionnaireIds,
                'is_used' => false
            ]);
        }

        // Remove not selected activities.
        Activity::where('treatment_plan_id', $treatmentPlanId)
            ->where('type', '<>', Activity::ACTIVITY_TYPE_GOAL)
            ->whereNotIn('id', $activityIds)
            ->get()
            ->each(function ($activity) {
                $activity->delete();
            });
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return bool[]
     */
    public function completeActivity(Request $request)
    {
        $user = Auth::user();
        $activities = json_decode($request[0], true);
        foreach ($activities as $activity) {
            $activityObj = Activity::find($activity['id']);
            $activityObj->update([
                'completed' => true,
                'pain_level' => $activity['pain_level'] ?? null,
                'completed_sets' => $activity['sets'] ?? null,
                'completed_reps' => $activity['reps'] ?? null,
                'submitted_date' => now(),
            ]);
            $timezone = $activity['timezone'];

            // Calculate completed percent and total pain threshold.
            event(new PodcastCalculatorEvent($activity));
        }

        $nowLocal = Carbon::now($timezone);
        $ongoingTreatmentPlan = $user->treatmentPlans()
            ->whereDate('start_date', '<=', $nowLocal)
            ->whereDate('end_date', '>=', $nowLocal)
            ->first();
        // Update user daily task.
        $submittedActivity = Activity::where('treatment_plan_id', $ongoingTreatmentPlan->id)
            ->where('type', '<>', Activity::ACTIVITY_TYPE_QUESTIONNAIRE)
            ->where('completed', 1)
            ->whereDate('submitted_date', '>=', $nowLocal->startOfDay())
            ->whereDate('submitted_date', '<=', $nowLocal->endOfDay())
            ->get();
        $init_daily_tasks = $user->init_daily_tasks;
        if ($submittedActivity->count() <= 1) {
            $init_daily_tasks = $user->init_daily_tasks + 1;
        }
        $user->update([
            'init_daily_tasks' => $init_daily_tasks,
            'daily_tasks' => $init_daily_tasks > $user->daily_tasks ? $init_daily_tasks : $user->daily_tasks,
        ]);

        return ['success' => true];
    }

    /**
     * @OA\Get(
     *     path="/api/treatment-plan/get-treatment-plan-detail",
     *     tags={"Treatment plan"},
     *     summary="Get treatment plan detail",
     *     operationId="getTreatmentPlanDetail",
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="Treatment plan id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
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
        if ($request->has('id')) {
            $includedGoals = false;
            $treatmentPlan = TreatmentPlan::where('id', $request->get('id'))->firstOrFail();
        } else {
            $includedGoals = true;
            // Get on-going treatment plan.
            $treatmentPlan = TreatmentPlan::where('patient_id', Auth::id())
                ->whereDate('start_date', '<=', Carbon::now())
                ->whereDate('end_date', '>=', Carbon::now())
                ->first();
        }

        $data = [];
        if ($treatmentPlan) {
            $activityData = TreatmentActivityHelper::getActivities($treatmentPlan, $request, $includedGoals);
            $data = array_merge($treatmentPlan->toArray(), [
                'goals' => GoalResource::collection($treatmentPlan->goals),
                'activities' => $activityData['activities'],
                'previewData' => $activityData['previewData'],
            ]);
        }
        return ['success' => true, 'data' => $data];
    }

    /**
     * @deprecated It is wrong with goal activity count, use getActivities() instead of, then count in FE
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getSummary(Request $request)
    {
        $date = date_create_from_format(config('settings.date_format'), $request->get('today'));
        $treatmentPlan = TreatmentPlan::where('patient_id', Auth::id())
            ->where('start_date', '<=', $date->format(config('settings.defaultTimestampFormat')))
            ->where('end_date', '>=', $date->format(config('settings.defaultTimestampFormat')))
            ->firstOrFail();

        $startDate = $treatmentPlan->start_date;
        $diff = $date->diff($startDate);
        $days = $diff->days;
        $day = $days % 7 + 1;
        $week = floor($days / 7) + 1 ;
        $activities = Activity::where('week', $week)
            ->where('day', $day)
            ->where('treatment_plan_id', $treatmentPlan->id)
            ->get();
        $data = [
            'all' => $activities->count(),
            'completed' => $activities->sum('completed'),
        ];

        return ['success' => true, 'data' => $data];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return bool[]
     */
    public function completeQuestionnaire(Request $request)
    {
        $user = Auth::user();
        $questionnaireAnswers = json_decode($request[0], true);
        foreach ($questionnaireAnswers as $questionnaireAnswer) {
            $activityObj = Activity::find($questionnaireAnswer['id']);
            $access_token = Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE);
            $response = Http::withToken($access_token)->get(env('ADMIN_SERVICE_URL') . '/get-questionnaire-by-id', [
                'id' => $activityObj->activity_id,
            ]);
            $questionnaire = $response->json('data');

            if ($questionnaire) {
                $questions = $questionnaire['questions'];
                foreach ($questionnaireAnswer['answers'] as $key => $answer) {
                    $question = Arr::first($questions, function ($item) use ($key) {
                        return $item['id'] === $key;
                    });

                    if ($question) {
                        $score = null;
                        if ($question['mark_as_countable']) {
                            $questionAnswers = $question['answers'];
                            if ($questionAnswers) {
                                if ($question['type'] === QuestionnaireAnswer::QUESTIONNAIRE_TYPE_CHECKBOX) {
                                    $selectedAnswers = array_filter($questionAnswers, function($questionAnswer) use ($answer) { return in_array($questionAnswer['id'], $answer); });
                                    $values = array_column($selectedAnswers, 'value');
                                    $score = array_sum($values);
                                } else if ($question['type'] === QuestionnaireAnswer::QUESTIONNAIRE_TYPE_MULTIPLE) {
                                    $selectedAnswer = Arr::first($questionAnswers, function ($item) use ($answer) {
                                        return $item['id'] === $answer;
                                    });
                                    if ($selectedAnswer) {
                                        $score = $selectedAnswer['value'];
                                    }
                                } else if ($question['type'] === QuestionnaireAnswer::QUESTIONNAIRE_TYPE_OPEN_NUMBER) {
                                    $score = $questionAnswers[0]['value'];
                                }
                            }
                        }
                        QuestionnaireAnswer::create([
                            'activity_id' => $questionnaireAnswer['id'],
                            'question_id' => $key,
                            'answer' => serialize($answer),
                            'score' => $score,
                        ]);
                    }
                }
                $timezone = $questionnaireAnswer['timezone'];

                $activityObj->update([
                    'completed' => true,
                    'submitted_date' => now(),
                ]);
            }
        }

        $nowLocal = Carbon::now($timezone);
        $ongoingTreatmentPlan = $user->treatmentPlans()
            ->whereDate('start_date', '<=', $nowLocal)
            ->whereDate('end_date', '>=', $nowLocal)
            ->first();
        // Update user daily answers completed.
        $submittedQuestionnaire = Activity::where('treatment_plan_id', $ongoingTreatmentPlan->id)
            ->where('type', Activity::ACTIVITY_TYPE_QUESTIONNAIRE)
            ->where('completed', 1)
            ->whereDate('submitted_date', '>=', $nowLocal->startOfDay())
            ->whereDate('submitted_date', '<=', $nowLocal->endOfDay())
            ->get();
        $init_daily_answers = $user->init_daily_answers;
        if ($submittedQuestionnaire->count() <= 1) {
            $init_daily_answers = $user->init_daily_answers + 1;
        }
        $user->update([
            'init_daily_answers' => $init_daily_answers,
            'daily_answers' => $init_daily_answers > $user->daily_answers ? $init_daily_answers : $user->daily_answers,
        ]);

        return ['success' => true];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function completeGoal(Request $request)
    {
        $goals = json_decode($request[0], true);
        foreach ($goals as $goal) {
            Activity::firstOrCreate([
                'satisfaction' => $goal['satisfaction'],
                'week' => $goal['week'],
                'day' => $goal['day'],
                'type' => Activity::ACTIVITY_TYPE_GOAL,
                'completed' => true,
                'activity_id' => $goal['goal_id'],
                'treatment_plan_id' => $goal['treatment_plan_id'],
            ]);
        }
        return ['success' => true];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return string
     * @throws \Mpdf\MpdfException
     */
    public function exportOnGoing(Request $request)
    {
        $user = Auth::user();
        $request['lang'] = $user->language_id;

        $treatmentPlan = TreatmentPlan::where('patient_id', Auth::id())
            ->whereDate('start_date', '<=', Carbon::now())
            ->whereDate('end_date', '>=', Carbon::now())
            ->firstOrFail();

        $treatmentPlanExport = new TreatmentPlanExport($treatmentPlan, $request, true);
        return $treatmentPlanExport->outPut();
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\TreatmentPlan $treatmentPlan
     *
     * @return string
     * @throws \Mpdf\MpdfException
     */
    public function export(Request $request, TreatmentPlan $treatmentPlan)
    {
        if (Auth::check()) {
            $user = Auth::user();
            $request['lang'] = $user->language_id;
        }

        $treatmentPlanExport = new TreatmentPlanExport($treatmentPlan, $request);
        return $treatmentPlanExport->outPut();
    }

    /**
     * @param string $startDate
     * @param string $endDate
     * @param integer $therapistId
     * @param integer|null $treatmentId
     *
     * @return array
     */
    private function validateOngoingTreatmentOverLimit($startDate, $endDate, $therapistId, $treatmentId = null)
    {
        $ongoingTreatmentLimit = Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))
            ->get(env('THERAPIST_SERVICE_URL') . '/therapist/get-patient-limit', [
                'therapist_id' => $therapistId,
            ]);

        $therapistOngoingTreatment = DB::table('treatment_plans')
            ->where('created_by', $therapistId)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere('start_date', '<', $startDate)->where('end_date', '>', $startDate);
            });

        if ($treatmentId) {
            $therapistOngoingTreatment->where('treatment_plans.id', '!=', $treatmentId);
        }

        if ($therapistOngoingTreatment->count() >= (int)$ongoingTreatmentLimit->body()) {
            return ['success' => false, 'message' => 'error_message.ongoing_treatment_create.full_limit', 'limit' => (int)$ongoingTreatmentLimit->body()];
        } else {
            return [];
        }
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getUsedDisease(Request $request)
    {
        $diseaseId = $request->get('disease_id');
        $treatmentPlans = TreatmentPlan::where('disease_id', $diseaseId)->count();

        return $treatmentPlans > 0 ? true : false;
    }

    /**
     * @param Request $request
     * @return \Illuminate\Support\Collection
     */
    public function getTreatmentPlanForGlobalData(Request $request)
    {
        $yesterday = Carbon::yesterday();

        if ($request->has('all')) {
            $treatmentPlans = TreatmentPlan::all();
        } else {
            $treatmentPlans = TreatmentPlan::whereDate('updated_at', '>=', $yesterday->startOfDay())
                ->whereDate('updated_at', '<=', $yesterday->endOfDay())
                ->get();
        }

        return $treatmentPlans;
    }
}
