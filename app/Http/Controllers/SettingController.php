<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/app/settings",
     *     tags={"Settings"},
     *     summary="Get a setting of application",
     *     operationId="getAppVersion",
     *     @OA\Parameter(
     *         name="name",
     *         in="query",
     *         description="Required name",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     * )
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     *
     * @return \Illuminate\Http\Response
     */
    public function getSetting(Request $request)
    {
        $setting = Setting::where('name', $request->get('name'))
                    ->first();
        if (!$setting) {
            return ['success' => false];
        }
        return ['success' => true, 'data' => $setting->value];
    }
}
