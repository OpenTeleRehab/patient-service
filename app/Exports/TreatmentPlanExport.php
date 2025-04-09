<?php

namespace App\Exports;

use App\Helpers\TranslationHelper;
use App\Helpers\TreatmentActivityHelper;
use App\Models\Forwarder;
use App\Models\TreatmentPlan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Mpdf\Mpdf;

class TreatmentPlanExport
{
    /**
     * @var \App\Models\TreatmentPlan
     */
    private $treatmentPlan;

    /**
     * @var \Illuminate\Http\Request
     */
    private $request;

    /**
     * @var bool
     */
    private $isPatient;

    /**
     * TreatmentPlanExport constructor.
     *
     * @param \App\Models\TreatmentPlan $treatmentPlan
     * @param \Illuminate\Http\Request $request
     * @param bool $isPatient
     */
    public function __construct(TreatmentPlan $treatmentPlan, Request $request, bool $isPatient = false)
    {
        $this->treatmentPlan = $treatmentPlan;
        $this->request = $request;
        $this->isPatient = $isPatient;
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function view(): View
    {
        $access_token = Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE);

        $diseaseName = Http::withToken($access_token)->get(env('ADMIN_SERVICE_URL') . '/disease/get-name/by-id', [
            'disease_id' => $this->treatmentPlan->disease_id,
        ]);

        $activityData = TreatmentActivityHelper::getActivities($this->treatmentPlan, $this->request, true);
        return view('exports.treatment_plan', [
            'diseaseName' => $diseaseName,
            'treatmentPlan' => $this->treatmentPlan,
            'activities' => $activityData['activities'],
            'translations' => TranslationHelper::getTranslations(),
            'isPatient' => $this->isPatient,
        ]);
    }

    /**
     * @param string $name
     * @param string $dest
     *
     * @return string
     * @throws \Mpdf\MpdfException
     */
    public function outPut($name = '', $dest = '')
    {
        $mpdf = new Mpdf(['orientation' => 'L']);
        $mpdf->setHeader('{PAGENO}');
        $mpdf->useSubstitutions = true;
        $mpdf->WriteHTML($this->view());
        return $mpdf->Output($name, $dest);
    }
}
