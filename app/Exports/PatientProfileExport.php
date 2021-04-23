<?php

namespace App\Exports;

use App\Helpers\TranslationHelper;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Mpdf\Mpdf;

class PatientProfileExport
{
    /**
     * @var \App\Models\User
     */
    private $user;

    /**
     * ProfileExport constructor.
     *
     * @param \App\Models\User $user
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function view(): View
    {
        return view('exports.patient_profile', [
            'user' => $this->user,
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
        $mpdf = new Mpdf();
        $mpdf->WriteHTML($this->view());
        return $mpdf->Output($name, $dest);
    }
}
