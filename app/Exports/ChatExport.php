<?php

namespace App\Exports;

use App\Helpers\TranslationHelper;
use Illuminate\Contracts\View\View;
use Mpdf\Mpdf;

class ChatExport
{
    /**
     * @var array $messages
     */
    private $messages;

    /**
     * @var array $patient
     */
    private $patient;

    /**
     * @var array $therapist
     */
    private $therapist;

    /**
     * ProfileExport constructor.
     *
     * @param array $messages
     * @param array $patient
     * @param array $therapist
     *
     */
    public function __construct($messages, $patient, $therapist)
    {
        $this->messages = $messages;
        $this->patient = $patient;
        $this->therapist = $therapist;
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function view(): View
    {
        return view('exports.chat', [
            'messages' => $this->messages,
            'patient' => $this->patient,
            'therapist' => $this->therapist,
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
