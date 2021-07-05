<?php

namespace App\Http\Controllers;

use App\Events\PodcastCalculatorEvent;
use App\Exports\TreatmentPlanExport;
use App\Helpers\TreatmentActivityHelper;
use App\Http\Resources\GoalResource;
use App\Http\Resources\TreatmentPlanResource;
use App\Models\Activity;
use App\Models\Goal;
use App\Models\QuestionnaireAnswer;
use App\Models\TreatmentPlan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class TreatmentPlanController extends Controller
{
    /**
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
     * @param \App\Models\TreatmentPlan $treatmentPlan
     *
     * @return array
     */
    public function update(Request $request, TreatmentPlan $treatmentPlan)
    {
        $patientId = $request->get('patient_id');
        $description = $request->get('description');
        $therapistId = $request->get('therapist_id');
        $startDate = date_create_from_format(config('settings.date_format'), $request->get('start_date'))->format('Y-m-d');
        $endDate = date_create_from_format(config('settings.date_format'), $request->get('end_date'))->format('Y-m-d');
        $disease_id = $request->get('disease_id');

        // Check if there is any overlap schedule.
        $overlapRecords = TreatmentPlan::where('patient_id', $patientId)
            ->where('id', '<>', $treatmentPlan->id)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate]);
            })->get();

        if (count($overlapRecords)) {
            return ['success' => false, 'message' => 'error_message.treatment_plan_assign_to_patient_overlap_schedule'];
        }

        // Check if ongoing treatment over the limit.
        $overLimit = $this->validateOngoingTreatmentOverLimit($startDate, $endDate, $therapistId, $treatmentPlan->id);
        if (!empty($overLimit)) {
            return $overLimit;
        }

        $treatmentPlan->update([
            'name' => $request->get('name'),
            'description' => $description,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_of_weeks' => $request->get('total_of_weeks', 1),
            'disease_id' => $disease_id,
        ]);

        $this->updateOrCreateActivities($treatmentPlan->id, $request->get('activities', []), $therapistId);
        $this->updateOrCreateGoals($treatmentPlan->id, $request->get('goals', []));
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
            ->delete();

        Activity::where('treatment_plan_id', $treatmentPlanId)
            ->where('type', Activity::ACTIVITY_TYPE_GOAL)
            ->whereNotIn('id', $goalIds)
            ->delete();
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
                Http::post(env('ADMIN_SERVICE_URL') . '/api/exercise/mark-as-used/by-ids', [
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
                Http::post(env('ADMIN_SERVICE_URL') . '/api/education-material/mark-as-used/by-ids', [
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
                Http::post(env('ADMIN_SERVICE_URL') . '/api/questionnaire/mark-as-used/by-ids', [
                    'questionnaire_ids' => $questionnaires,
                ]);
            }
        }

        // Remove not selected activities.
        Activity::where('treatment_plan_id', $treatmentPlanId)
            ->where('type', '<>', Activity::ACTIVITY_TYPE_GOAL)
            ->whereNotIn('id', $activityIds)
            ->delete();
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return bool[]
     */
    public function completeActivity(Request $request)
    {
        $activities = json_decode($request[0], true);
        foreach ($activities as $activity) {
            Activity::where('id', $activity['id'])->update([
                'completed' => true,
                'pain_level' => $activity['pain_level'] ?? null,
                'completed_sets' => $activity['sets'] ?? null,
                'completed_reps' => $activity['reps'] ?? null,
            ]);
            // Calculate completed percent and total pain threshold
            event(new PodcastCalculatorEvent($activity));
        }

        return ['success' => true];
    }

    /**
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
            $data = array_merge($treatmentPlan->toArray(), [
                'goals' => GoalResource::collection($treatmentPlan->goals),
                'activities' => TreatmentActivityHelper::getActivities($treatmentPlan, $request, $includedGoals),
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
        $questionnaireAnswers = json_decode($request[0], true);
        foreach ($questionnaireAnswers as $questionnaireAnswer) {
            foreach ($questionnaireAnswer['answers'] as $key => $answer) {
                QuestionnaireAnswer::create([
                    'activity_id' => $questionnaireAnswer['id'],
                    'question_id' => $key,
                    'answer' => serialize($answer),
                ]);
            }

            Activity::where('id',$questionnaireAnswer['id'])->update([
                'completed' => true,
                'submitted_date' => now(),
            ]);
        }
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
        $treatmentPlan = TreatmentPlan::where('patient_id', Auth::id())
            ->whereDate('start_date', '<=', Carbon::now())
            ->whereDate('end_date', '>=', Carbon::now())
            ->firstOrFail();

        $treatmentPlanExport = new TreatmentPlanExport($treatmentPlan, $request);
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
        $ongoingTreatmentLimit = Http::get(env('THERAPIST_SERVICE_URL') . '/api/therapist/get-patient-limit', [
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
}
