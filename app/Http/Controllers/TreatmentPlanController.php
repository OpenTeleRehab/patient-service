<?php

namespace App\Http\Controllers;

use App\Http\Resources\GoalResource;
use App\Http\Resources\QuestionnaireAnswerResource;
use App\Http\Resources\TreatmentPlanResource;
use App\Models\Activity;
use App\Models\Goal;
use App\Models\QuestionnaireAnswer;
use App\Models\TreatmentPlan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
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
                            $status = trim($filterObj->value);
                            switch ($status) {
                                case TreatmentPlan::STATUS_PLANNED:
                                    $query->whereDate('start_date', '>', Carbon::now());
                                    break;
                                case TreatmentPlan::STATUS_FINISHED:
                                    $query->where('end_date', '<', Carbon::now());
                                    break;
                                case TreatmentPlan::STATUS_ON_GOING:
                                    $query->where('start_date', '<=', Carbon::now());
                                    $query->where('end_date', '>=', Carbon::now());
                            }
                        } elseif ($filterObj->columnName === 'start_date' || $filterObj->columnName === 'end_date') {
                            $dates = explode(' - ', $filterObj->value);
                            $startDate = date_create_from_format('d/m/Y', $dates[0]);
                            $endDate = date_create_from_format('d/m/Y', $dates[1]);
                            $startDate->format('Y-m-d');
                            $endDate->format('Y-m-d');
                            $query->where($filterObj->columnName, '>=', $startDate)
                                ->where($filterObj->columnName, '<=', $endDate);
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

        $startDate = date_create_from_format(config('settings.date_format'), $request->get('start_date'))->format('Y-m-d');
        $endDate = date_create_from_format(config('settings.date_format'), $request->get('end_date'))->format('Y-m-d');

        // Check if there is any overlap schedule.
        $overlapRecords = TreatmentPlan::where('patient_id', $patientId)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate]);
            })->get();

        if (count($overlapRecords)) {
            return ['success' => false, 'message' => 'error_message.treatment_plan_assign_to_patient_overlap_schedule'];
        }

        $treatmentPlan = TreatmentPlan::create([
            'name' => $name,
            'description' => $description,
            'patient_id' => $patientId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => TreatmentPlan::STATUS_PLANNED,
            'total_of_weeks' => $request->get('total_of_weeks', 1),
        ]);

        if (!$treatmentPlan) {
            return ['success' => false, 'message' => 'error_message.treatment_plan_assign_to_patient'];
        }

        $this->updateOrCreateGoals($treatmentPlan->id, $request->get('goals', []));
        $this->updateOrCreateActivities($treatmentPlan->id, $request->get('activities', []));
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
        $startDate = date_create_from_format(config('settings.date_format'), $request->get('start_date'))->format('Y-m-d');
        $endDate = date_create_from_format(config('settings.date_format'), $request->get('end_date'))->format('Y-m-d');

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

        $treatmentPlan->update([
            'name' => $request->get('name'),
            'description' => $description,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_of_weeks' => $request->get('total_of_weeks', 1),
        ]);

        $this->updateOrCreateActivities($treatmentPlan->id, $request->get('activities', []));
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
    }

    /**
     * @param int $treatmentPlanId
     * @param array $activities
     *
     * @return void
     */
    private function updateOrCreateActivities(int $treatmentPlanId, array $activities = [])
    {
        $activityIds = [];
        foreach ($activities as $activity) {
            $exercises = $activity['exercises'];
            $materials = $activity['materials'];
            $questionnaires = $activity['questionnaires'];
            if (count($exercises) > 0) {
                foreach ($exercises as $exercise) {
                    $activityObj = Activity::firstOrCreate(
                        [
                            'treatment_plan_id' => $treatmentPlanId,
                            'week' => $activity['week'],
                            'day' => $activity['day'],
                            'activity_id' => $exercise,
                            'type' => Activity::ACTIVITY_TYPE_EXERCISE,
                        ],
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
                    $activityObj = Activity::firstOrCreate(
                        [
                            'treatment_plan_id' => $treatmentPlanId,
                            'week' => $activity['week'],
                            'day' => $activity['day'],
                            'activity_id' => $material,
                            'type' => Activity::ACTIVITY_TYPE_MATERIAL,
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
                    $activityObj = Activity::firstOrCreate(
                        [
                            'treatment_plan_id' => $treatmentPlanId,
                            'week' => $activity['week'],
                            'day' => $activity['day'],
                            'activity_id' => $questionnaire,
                            'type' => Activity::ACTIVITY_TYPE_QUESTIONNAIRE,
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
     * @param \App\Models\Activity $activity
     *
     * @return bool[]
     */
    public function completeActivity(Request $request, Activity $activity)
    {
        $activity->update([
            'completed' => true,
            'pain_level' => $request->get('pain_level'),
            'sets' => $request->get('sets'),
            'reps' => $request->get('reps'),
        ]);

        return ['success' => true];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getActivities(Request $request)
    {
        $result = [];
        $treatmentPlan = null;
        if ($request->has('today')) {
            $date = date_create_from_format(config('settings.date_format'), $request->get('today'))->format(config('settings.defaultTimestampFormat'));
            $treatmentPlan = TreatmentPlan::where('patient_id', Auth::id())
                ->where('start_date', '<=', $date)
                ->where('end_date', '>=', $date)
                ->firstOrFail();
            $dailyGoals = $treatmentPlan->goals->where('frequency', 'daily')->all();
            $weeklyGoals = $treatmentPlan->goals->where('frequency', 'weekly')->all();
        } else {
            $treatmentPlan = TreatmentPlan::where('id', $request->get('id'))->firstOrFail();
        }
        $activities = $treatmentPlan->activities->sortBy(function ($activity) {
            return [$activity->week, $activity->day];
        });

        $previousActivity = $activities ? $activities->first() : null;
        foreach ($activities as $key => $activity) {
            $date = $treatmentPlan->start_date->modify('+' . ($activity->week - 1) . ' week')
                ->modify('+' . ($activity->day - 1) . ' day')
                ->format(config('settings.defaultTimestampFormat'));
            $activityObj = [];
            $response = null;

            if ($activity->type === Activity::ACTIVITY_TYPE_EXERCISE) {
                $response = Http::get(env('ADMIN_SERVICE_URL') . '/api/exercise/list/by-ids', [
                    'exercise_ids' => [$activity->activity_id],
                    'lang' => $request->get('lang'),
                    'therapist_id' => $request->get('therapist_id')
                ]);
            } elseif ($activity->type === Activity::ACTIVITY_TYPE_MATERIAL) {
                $response = Http::get(env('ADMIN_SERVICE_URL') . '/api/education-material/list/by-ids', [
                    'material_ids' => [$activity->activity_id],
                    'lang' => $request->get('lang'),
                    'therapist_id' => $request->get('therapist_id')
                ]);
            } elseif ($activity->type === Activity::ACTIVITY_TYPE_QUESTIONNAIRE) {
                $response = Http::get(env('ADMIN_SERVICE_URL') . '/api/questionnaire/list/by-ids', [
                    'questionnaire_ids' => [$activity->activity_id],
                    'lang' => $request->get('lang'),
                    'therapist_id' => $request->get('therapist_id')
                ]);
            } else {
                $goal = Goal::find($activity->activity_id);
                if ($goal) {
                    $activityObj = [
                        'id' => $activity->id,
                        'title' => $goal->title,
                        'frequency' => $goal->frequency,
                    ];
                }
            }

            if (!empty($response) && $response->successful()) {
                if ($response->json()['data']) {
                    $activityObj = $response->json()['data'][0];
                    $activityObj['id'] = $activity->id;
                } else {
                    continue;
                }
            }

            $result[] = array_merge([
                'date' => $date,
                'activity_id' => $activity->activity_id,
                'completed' => $activity->completed,
                'pain_level' => $activity->pain_level,
                'completed_sets' => $activity->sets,
                'completed_reps' => $activity->reps,
                'satisfaction' => $activity->satisfaction,
                'type' => $activity->type,
                'submitted_date' => $activity->submitted_date,
                'answers' => QuestionnaireAnswerResource::collection($activity->answers),
                'week' => $activity->week,
                'day' => $activity->day,
            ], $activityObj);

            // Add daily goals.
            if (!empty($dailyGoals)) {
                if ($previousActivity->day !== $activity->day || $previousActivity->week !== $activity->week) {
                    $previousDate = $treatmentPlan->start_date->modify('+' . ($previousActivity->week - 1) . ' week')
                        ->modify('+' . ($previousActivity->day - 1) . ' day')
                        ->format(config('settings.defaultTimestampFormat'));
                    $this->addGoals($dailyGoals, $activities, $previousActivity, $previousDate, 'daily', $result);
                }

                if ($key === array_key_last($activities->toArray())) {
                    $previousDate = $treatmentPlan->start_date->modify('+' . ($activity->week - 1) . ' week')
                        ->modify('+' . ($activity->day - 1) . ' day')
                        ->format(config('settings.defaultTimestampFormat'));
                    $this->addGoals($dailyGoals, $activities, $activity, $previousDate, 'daily', $result);
                }
            }

            // Add weekly goals.
            if (!empty($weeklyGoals)) {
                if ($previousActivity->week !== $activity->week) {
                    $previousDate = $treatmentPlan->start_date->modify('+' . ($previousActivity->week - 1) . ' week')
                        ->modify('+' . ($previousActivity->day - 1) . ' day')
                        ->format(config('settings.defaultTimestampFormat'));
                    $this->addGoals($weeklyGoals, $activities, $previousActivity, $previousDate, 'weekly', $result);
                }

                if ($key === array_key_last($activities->toArray())) {
                    $previousDate = $treatmentPlan->start_date->modify('+' . ($activity->week - 1) . ' week')
                        ->modify('+' . ($activity->day - 1) . ' day')
                        ->format(config('settings.defaultTimestampFormat'));
                    $this->addGoals($weeklyGoals, $activities, $activity, $previousDate, 'weekly', $result);
                }
            }
            $previousActivity = clone $activity;
        }

        $data = array_merge($treatmentPlan->toArray(), [
            'goals' => GoalResource::collection($treatmentPlan->goals),
            'activities' => $result
        ]);

        return ['success' => true, 'data' => $data];
    }

    /**
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
     * @param \App\Models\Activity $activity
     *
     * @return bool[]
     */
    public function completeQuestionnaire(Request $request, Activity $activity)
    {
        foreach ($request->get('answers', []) as $key => $answer) {
            QuestionnaireAnswer::create([
                'activity_id' => $activity->id,
                'question_id' => $key,
                'answer' => serialize($answer),
            ]);
        }

        $activity->update([
            'completed' => true,
            'submitted_date' => now(),
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
        Activity::create([
            'satisfaction' => $request->get('satisfaction'),
            'week' => $request->get('week'),
            'day' => $request->get('day'),
            'type' => Activity::ACTIVITY_TYPE_GOAL,
            'completed' => true,
            'activity_id' => $request->get('goal_id'),
            'treatment_plan_id' => $request->get('treatment_plan_id'),
        ]);
        return ['success' => true];
    }

    /**
     * @param array $goals
     * @param array $activities
     * @param array $previousActivity
     * @param Date $previousDate
     * @param string  $frequency
     * @param array $result
     *
     * @return void
     */
    private function addGoals($goals, $activities, $previousActivity, $previousDate, $frequency, &$result)
    {
        foreach ($goals as $goal) {
            $completed = $activities->where('type', Activity::ACTIVITY_TYPE_GOAL)
                ->where('activity_id', $goal->id)
                ->where('week', $previousActivity->week)
                ->where('day', $previousActivity->day)
                ->count();
            if (!$completed) {
                $result[] = [
                    'date' => $previousDate,
                    'activity_id' => $goal->id,
                    'title' => $goal->title,
                    'completed' => false,
                    'type' => Activity::ACTIVITY_TYPE_GOAL,
                    'frequency' => $frequency,
                    'week' => $previousActivity->week,
                    'day' => $previousActivity->day,
                    'treatment_plan_id' => $previousActivity->treatment_plan_id,
                ];
            }
        }
    }
}
