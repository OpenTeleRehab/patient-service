<?php

namespace App\Http\Resources;
use App\Helpers\TreatmentActivityHelper;
use App\Models\TreatmentPlan;
use App\Helpers\RocketChatHelper;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Http;


class PatientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $upcomingTreatmentPlan = $this->treatmentPlans()
            ->whereDate('end_date', '>', Carbon::now())
            ->orderBy('start_date')
            ->first();

        $ongoingTreatmentPlan = $this->treatmentPlans()
            ->whereDate('start_date', '<=', Carbon::now())
            ->whereDate('end_date', '>=', Carbon::now())
            ->get();

        // Get last treatment if there is no upcoming
        if (!$upcomingTreatmentPlan) {
            $upcomingTreatmentPlan = $this->treatmentPlans()
                ->orderBy('end_date', 'desc')
                ->first();
        }

        if ($request->get('type') !== User::ADMIN_GROUP_GLOBAL_ADMIN) {
            $total = 0;
            $completed = 0;
            $currentDate = Carbon::now()->format('Y-m-d');
            $completedPercent = 0;
            $totalPainThreshold = 0;
            $exercises = [];
            $tmp = [];

            $painThresholdLimit = Http::get(env('ADMIN_SERVICE_URL') . '/api/setting/library-limit', [
                'type' => TreatmentPlan::PAIN_THRESHOLD_LIMIT,
            ]);

            if (count($ongoingTreatmentPlan) > 0) {
                $treatmentPlan = TreatmentPlan::where('id', $ongoingTreatmentPlan[0]->id)->firstOrFail();
                $activities = TreatmentActivityHelper::getActivities($treatmentPlan, $request);
                usort($activities, function ($a, $b) {
                    return $a['date'] < $b['date'];
                });

                if (count($activities) > 0) {
                    foreach ($activities as $activity) {
                        if ($activity['date'] < $currentDate) {
                            if ($activity['completed'] == 1) {
                                $tmp[$activity['date']]['completed'][] = $activity['completed'];
                            } else {
                                $tmp[$activity['date']]['incompleted'][] = $activity['completed'];
                            }
                            //get activity type exercise group by date
                            if ($activity['type'] == 'exercise') {
                                $exercises[$activity['date']]['exercise'][] = $activity['pain_level'];
                            }
                        }
                    }

                    //get last three day percent of activities
                    $i = 0;
                    foreach ($tmp as $key => $d) {
                        if ($i < 3) {
                            if (isset($d['completed'])) {
                                if (isset($d['incompleted'])) {
                                    $total += count($d['incompleted']) + count($d['completed']);
                                    $completed += count($d['completed']);
                                } else {
                                    $total += count($d['completed']);
                                    $completed += count($d['completed']);
                                }
                            } else {
                                $total += count($d['incompleted']);
                            }
                        }
                        $i++;
                    }

                    $completedPercent = $total > 0 ? ($completed / $total) * 100 : 0;

                    //get last three day exercises and the pain threshold
                    $j = 0;
                    foreach ($exercises as $key => $exercise) {
                        if ($j < 3) {
                            foreach ($exercise as $values) {
                                foreach ($values as $value) {
                                    if ($value != null) {
                                        if ($value > (int)$painThresholdLimit->body()) {
                                            $totalPainThreshold += 1;
                                        }
                                    }
                                }
                            }
                        }
                        $j++;
                    }
                }
            }

            $unreads = 0;
            if ($request->get('chat_rooms')) {
                $rooms = array_intersect($request->get('chat_rooms'), $this->chat_rooms);
                $unreads = RocketChatHelper::getUnreadMessages($request->get('auth_token'),
                    $request->get('auth_userId'), reset($rooms));
            }
        }

        $responseData = [
            'id' => $this->id,
            'identity' => $this->identity,
            'clinic_id' => $this->clinic_id,
            'country_id' => $this->country_id,
            'date_of_birth' => $this->date_of_birth,
            'enabled' => $this->enabled,
            'upcomingTreatmentPlan' => $upcomingTreatmentPlan,
            'ongoingTreatmentPlan' => $ongoingTreatmentPlan,
        ];
        if ($request->get('type') !== User::ADMIN_GROUP_GLOBAL_ADMIN) {
            $responseData = array_merge($responseData, [
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'phone' => $this->phone,
                'dial_code' => $this->dial_code,
                'gender' => $this->gender,
                'chat_user_id' => $this->chat_user_id,
                'chat_rooms' => $this->chat_rooms ?: [],
                'therapist_id' => $this->therapist_id,
                'secondary_therapists' => $this->secondary_therapists ? : [],
                'note' => $this->note,
                'is_secondary_therapist' => $this->isSecondaryTherapist($this->secondary_therapists, $request),
                'completed_percent' => $completedPercent,
                'totalPainThreshold' => $totalPainThreshold,
                'unreads' => $unreads
            ]);
        }

        return $responseData;
    }

    /**
     * @param $secondary_therapists
     * @param $request
     * @return bool
     */
    private function isSecondaryTherapist($secondary_therapists, $request)
    {
        $isSecondaryTherapist = false;
        if (!empty($secondary_therapists) && in_array($request->get('therapist_id'), $secondary_therapists)) {
            $isSecondaryTherapist = true;
        }

        return $isSecondaryTherapist;
    }
}
