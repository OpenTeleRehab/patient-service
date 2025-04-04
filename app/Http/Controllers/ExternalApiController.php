<?php

namespace App\Http\Controllers;

use App\Http\Resources\ExternalPatientResource;
use App\Models\InternationalClassificationDisease;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ExternalApiController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/external/patients",
     *     tags={"External"},
     *     summary="Patient list",
     *     operationId="patientList",
     *     @OA\Parameter(
     *          name="page",
     *          in="query",
     *          description="Current page",
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *     @OA\Parameter(
     *         name="page_size",
     *         in="query",
     *         description="Limit",
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="Identifier to search for",
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *    @OA\Parameter(
     *        name="name",
     *        in="query",
     *        description="Name to search for",
     *        @OA\Schema(
     *            type="string"
     *        )
     *    ),
     *     @OA\Parameter(
     *         name="age",
     *         in="query",
     *         description="Age to search for",
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getPatients(Request $request)
    {
        $data = $request->all();
        $pageSize = $request->get('pageSize', 10);

        $response = Http::get(env('ADMIN_SERVICE_URL') . '/country');
        $json = $response->json();
        $countries = $json['data'];
        $query = User::where('email', '!=', env('KEYCLOAK_BACKEND_USERNAME'))->orWhereNull('email');
        if (isset($data['id'])) {
            $query->where('id', $data['id']);
        }

        if (isset($data['enabled'])) {
            $query->where('enabled', boolval($data['enabled']));
        }

        if (isset($data['name'])) {
            $query->whereAny(['first_name', 'last_name'], 'like', '%' . $data['name'] . '%');
        }

        if (isset($data['age'])) {
            $query->whereRaw('YEAR(NOW()) - YEAR(date_of_birth) = ?', $data['age']);
        }

        $users = $query->paginate($pageSize);

        $patients = ExternalPatientResource::collection($users->map(function ($user) use ($countries) {
            return new ExternalPatientResource($user, $countries);
        }));

        $baseUrl = url(env('APP_URL') . '/api/patient/external/patients');

        $queryParams = http_build_query(array_merge($request->except('page'), [
            'pageSize' => $pageSize,
        ]));

        return [
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => $users->total(),
            'entry' => $patients,
            'link' => [
                [
                    'relation' => 'self',
                    'url' => $baseUrl . '?' . $queryParams . '&page=' . $users->currentPage(),
                ],
                $users->currentPage() < $users->lastPage() ?
                [
                    'relation' => 'next',
                    'url' => $baseUrl . '?' . $queryParams . '&page=' . ($users->currentPage() + 1),
                ]
                : [
                    'relation' => 'previous',
                    'url' => $baseUrl . '?' . $queryParams . '&page=' . ($users->currentPage() - 1),
                ],
            ],
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/external/patients/{id}",
     *     tags={"External"},
     *     summary="Patient",
     *     operationId="patient",
     *     @OA\Parameter(
     *          name="page",
     *          in="query",
     *          description="Current page",
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @param int $id
     *
     * @return ExternalPatientResource | JsonResponse
     */
    public function getPatient($id)
    {
        $patient = User::find($id);
        if (!$patient) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    'severity' => 'error',
                    'code' => 'not-found',
                    'details' => [
                        'text' => 'Patient not found',
                    ],
                ],
            ], 404);
        }

        return new ExternalPatientResource($patient);
    }
}
