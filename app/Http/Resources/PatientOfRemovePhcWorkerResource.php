<?php

namespace App\Http\Resources;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;


class PatientOfRemovePhcWorkerResource extends JsonResource
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

        $responseData = [
            'id' => $this->id,
            'phc_service_id' => $this->phc_service_id,
            'country_id' => $this->country_id,
            'enabled' => $this->enabled,
            'upcomingTreatmentPlan' => $upcomingTreatmentPlan,
            'ongoingTreatmentPlan' => $ongoingTreatmentPlan,
            'phc_worker_id' => $this->phc_worker_id,
            'is_supplementary_phc_worker' => $this->isSupplementaryPhcWorker($this->supplementary_phc_workers, $request)
        ];

        return $responseData;
    }

    /**
     * @param $secondary_therapists
     * @param $request
     * @return bool
     */
    private function isSupplementaryPhcWorker($supplementary_phc_workers, $request)
    {
        $isSupplementaryPhcWorker = false;
        if (!empty($supplementary_phc_workers) && in_array($request->get('phc_worker_id'), $supplementary_phc_workers)) {
            $isSupplementaryPhcWorker = true;
        }

        return $isSupplementaryPhcWorker;
    }
}
