<?php

namespace App\Exports;

use App\Helpers\TranslationHelper;
use App\Helpers\TreatmentActivityHelper;
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
     * TreatmentPlanExport constructor.
     *
     * @param \App\Models\TreatmentPlan $treatmentPlan
     * @param \Illuminate\Http\Request $request
     */
    public function __construct(TreatmentPlan $treatmentPlan, Request $request)
    {
        $this->treatmentPlan = $treatmentPlan;
        $this->request = $request;
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function view(): View
    {
        $diseaseName = Http::get(env('ADMIN_SERVICE_URL') . '/disease/get-name/by-id', [
            'disease_id' => $this->treatmentPlan->disease_id,
        ]);
        return view('exports.treatment_plan', [
            'diseaseName' => $diseaseName,
            'treatmentPlan' => $this->treatmentPlan,
            'activities' => TreatmentActivityHelper::getActivities($this->treatmentPlan, $this->request, true),
            'translations' => TranslationHelper::getTranslations(),
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
