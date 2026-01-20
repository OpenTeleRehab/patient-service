<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessUserDeletion;
use App\Models\User;
use Illuminate\Http\Request;

class DataCleanUpController extends Controller
{
    public function deleteUsersByEntity(Request $request)
    {
        $validatedData = $request->validate([
            'entity_name' => 'required|in:country,region,province,rehab_service,phc_service',
            'entity_id' => 'required|integer',
        ]);

        $entityName = $validatedData['entity_name'];
        $entityId = $validatedData['entity_id'];

        ProcessUserDeletion::dispatch($entityName, $entityId);

        return response()->json([
            'message' => "Deletion job for patients in {$entityName} {$entityId} has been queued."
        ]);
    }

    /**
     * Count all patients belonging to a specific group.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function countUsersByEntity(Request $request)
    {
        $validatedData = $request->validate([
            'entity_name' => 'required|in:country,region,province,rehab_service,phc_service',
            'entity_id' => 'required|integer',
        ]);

        $entityName = $validatedData['entity_name'];
        $entityId = $validatedData['entity_id'];

        $entityColumnMap = [
            'country' => 'country_id',
            'region' => 'region_id',
            'province' => 'province_id',
            'rehab_service' => 'clinic_id',
            'phc_service' => 'phc_service_id',
        ];

        $column = $entityColumnMap[$entityName];

        $userCount = User::where($column, $entityId)->count();

        return response()->json(['data' => $userCount]);
    }
}
