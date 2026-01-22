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

    /**
     * Bulk update users belonging to a specific entity.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateUsersByEntity(Request $request)
    {
        $validatedData = $request->validate([
            'entity_name' => 'required|in:rehab_service,phc_service',
            'entity_id' => 'required|integer',
            'region_id' => 'nullable|integer',
            'province_id' => 'nullable|integer',
        ]);

        $entityColumnMap = [
            'rehab_service' => 'clinic_id',
            'phc_service' => 'phc_service_id',
        ];

        $column = $entityColumnMap[$validatedData['entity_name']];

        $updateData = [];

        if (isset($validatedData['region_id'])) {
            $updateData['region_id'] = $validatedData['region_id'];
        }

        if (isset($validatedData['province_id'])) {
            $updateData['province_id'] = $validatedData['province_id'];
        }

        if (empty($updateData)) {
            return response()->json(['message' => 'No data to update.'], 422);
        }

        User::where($column, $validatedData['entity_id'])->update($updateData);

        return response()->json(['message' => 'Users updated successfully.']);
    }
}
