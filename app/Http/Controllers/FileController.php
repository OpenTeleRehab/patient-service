<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FileController extends Controller
{
    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|null
     */
    public function download(Request $request)
    {
        $filePath = storage_path($request->get('path'));
        if (file_exists($filePath) && is_file($filePath)) {
            return response()->download($filePath);
        }
        return null;
    }
}
