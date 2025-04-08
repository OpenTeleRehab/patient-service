<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateExport;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function export(Request $request)
    {
        GenerateExport::dispatch($request->all());
        return 'ok';
    }

    public function download(Request $request)
    {
        return response()->download($request->get('path'))->deleteFileAfterSend();
    }
}
