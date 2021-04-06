<?php

namespace App\Exports;

use App\Helpers\TranslationHelper;
use Illuminate\Contracts\View\View;

class TreatmentPlanExport
{
    /**
     * @var array
     */
    private $treatmentPlan;

    /**
     * TreatmentPlanExport constructor.
     *
     * @param array $treatmentPlan
     *
     */
    public function __construct($treatmentPlan)
    {
        $this->treatmentPlan = $treatmentPlan;
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function view(): View
    {
        return view('exports.treatment_plan', [
            'treatmentPlan' => $this->treatmentPlan,
            'translations' => TranslationHelper::getTranslations(),
        ]);
    }
}
