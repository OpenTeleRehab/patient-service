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
     * ProfileExport constructor.
     *
     * @param array $messages
     */
    public function __construct($messages)
    {
        $this->messages = $messages;
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function view(): View
    {
        return view('exports.chat', [
            'messages' => $this->messages,
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
