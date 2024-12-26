<?php

namespace App\Http\Controllers;

use App\Exports\QuestionnaireResultExport;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function exportQuestionnaireResult(Request $request)
    {
        $filePath = QuestionnaireResultExport::export($request);
        $absolutePath = storage_path($filePath);
        return response()->download($absolutePath)->deleteFileAfterSend(true);
    }
}
