<?php

namespace App\Http\Controllers;

use App\Helpers\TranslationHelper;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AppointmentController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/appointment",
     *     tags={"Appointment"},
     *     summary="Appointment list",
     *     operationId="appointmentList",
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         description="Date",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="now",
     *         in="query",
     *         description="Now",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="therapist_id",
     *         in="query",
     *         description="Therapist id",
     *         required=true,
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
    public function index(Request $request)
    {
        $date = date_create_from_format(config('settings.date_format'), $request->get('date'));
        $now = $request->get('now');

        $calendarData = Appointment::where('therapist_id', $request->get('therapist_id'))
            ->whereYear('start_date', $date->format('Y'))
            ->whereMonth('start_date', $date->format('m'))
            ->get();

        $appointments = Appointment::where('therapist_id', $request->get('therapist_id'));

        $newAppointments = Appointment::where('therapist_id', $request->get('therapist_id'))
            ->where('created_by_therapist', 0)
            ->where('therapist_status', Appointment::STATUS_INVITED)
            ->where('start_date', '>', Carbon::now())
            ->orderBy('start_date')
            ->get();

        $upComingAppointments = Appointment::where('therapist_id', $request->get('therapist_id'))
            ->where('end_date', '>=', $now)
            ->count();

        if ($request->get('selected_from_date')) {
            $selectedFromDate = date_create_from_format('Y-m-d H:i:s', $request->get('selected_from_date'));
            $selectedToDate = date_create_from_format('Y-m-d H:i:s', $request->get('selected_to_date'));
            $appointments->where('start_date', '>=', $selectedFromDate)
                ->where('start_date', '<=', $selectedToDate);
        } else {
            $appointments->where('end_date', '>', $now);
        }

        if ($request->get('patient_id')) {
            $appointments->where('patient_id', $request->get('patient_id'));
        }

        $data = [
            'upcomingAppointments' => $upComingAppointments,
            'calendarData' => $calendarData,
            'approves' => AppointmentResource::collection($appointments->orderBy('start_date')->get()),
            'newAppointments' => AppointmentResource::collection($newAppointments),
        ];
        return ['success' => true, 'data' => $data];
    }

    /**
     * @OA\Post(
     *     path="/api/appointment",
     *     tags={"Appointment"},
     *     summary="Create appointment",
     *     operationId="createAppointment",
     *     @OA\Parameter(
     *         name="from",
     *         in="query",
     *         description="From",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="date-time(yyyy-mm-dd hh:mm:ss)"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="to",
     *         in="query",
     *         description="To",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="date-time(yyyy-mm-dd hh:mm:ss)"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="therapist_id",
     *         in="query",
     *         description="Therapist id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="patient_id",
     *         in="query",
     *         description="Patient id",
     *         required=true,
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
    public function store(Request $request)
    {
        $startDate = date_create_from_format('Y-m-d H:i:s', $request->get('from'));
        $endDate = date_create_from_format('Y-m-d H:i:s', $request->get('to'));

        // Check if overlap with any appointment.
        $overlap = $this->validateOverlap($startDate, $endDate, $request->get('therapist_id'), $request->get('patient_id'));
        if ($overlap) {
            return ['success' => false, 'message' => 'error_message.appointment_overlap'];
        }

        Appointment::create([
            'therapist_id' => $request->get('therapist_id'),
            'patient_id' => $request->get('patient_id'),
            'therapist_status' => Appointment::STATUS_ACCEPTED,
            'patient_status' => Appointment::STATUS_INVITED,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'note' => $request->get('note'),
        ]);

        return ['success' => true, 'message' => 'success_message.appointment_add'];
    }

    /**
     * @OA\Put(
     *     path="/api/appointment/{id}",
     *     tags={"Appointment"},
     *     summary="Update appointment",
     *     operationId="updateAppointment",
     *     @OA\Parameter(
     *         name="from",
     *         in="query",
     *         description="From",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="date-time(yyyy-mm-dd hh:mm:ss)"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="to",
     *         in="query",
     *         description="To",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="date-time(yyyy-mm-dd hh:mm:ss)"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Id",
     *         required=true,
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
     * @param \App\Models\Appointment $appointment
     *
     * @return array
     */
    public function update(Request $request, Appointment $appointment)
    {
        $startDate = date_create_from_format('Y-m-d H:i:s', $request->get('from'));
        $endDate = date_create_from_format('Y-m-d H:i:s', $request->get('to'));

        // Check if overlap with any appointment.
        $overlap = $this->validateOverlap($startDate, $endDate, $appointment->therapist_id, $appointment->patient_id, $appointment->id);
        if ($overlap) {
            return ['success' => false, 'message' => 'error_message.appointment_overlap'];
        }

        $updateFile = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'note' => $request->get('note'),
        ];

        // Update patient status if appointment data changed.
        if ($startDate != $appointment->start_date || $endDate != $appointment->end_date) {
            $updateFile['patient_status'] = Appointment::STATUS_INVITED;
        }
        $appointment->update($updateFile);

        try {
            $translations = TranslationHelper::getTranslations($appointment->patient->language_id);
            Appointment::notification($appointment, $translations['appointment.updated_appointment_with'] . ' ' . $appointment->patient->first_name . ' ' . $appointment->patient->last_name);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }

        return ['success' => true, 'message' => 'success_message.appointment_update'];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getPatientAppointments(Request $request)
    {
        $appointments = Appointment::where('patient_id', Auth::id())
            ->where('end_date', '>=', $request->get('now'))
            ->orderBy('start_date')
            ->paginate($request->get('page_size'));

        $info = [
            'current_page' => $appointments->currentPage(),
            'last_page' => $appointments->lastPage(),
            'total_count' => $appointments->total(),
        ];

        $data = AppointmentResource::collection($appointments);
        return ['success' => true, 'data' => $data, 'info' => $info];
    }

    /**
     * @param \Illuminate\Support\Facades\Date $startDate
     * @param \Illuminate\Support\Facades\Date $endDate
     * @param integer $therapistId
     * @param integer $patientId
     * @param integer|null $appointmentId
     *
     * @return boolean
     */
    private function validateOverlap($startDate, $endDate, $therapistId, $patientId, $appointmentId = null)
    {
        $overlap = Appointment::where(function ($query) use ($therapistId, $patientId) {
            $query->where('therapist_id', $therapistId)
                ->orWhere('patient_id', $patientId);
        })->where(function ($query) use ($startDate, $endDate) {
            $query->orWhere(function ($query) use ($startDate, $endDate) {
                $query->where('start_date', '<=', $startDate)
                    ->where('end_date', '>', $startDate);
            })->orWhere(function ($query) use ($startDate, $endDate) {
                $query->where('start_date', '>', $startDate)
                    ->where('end_date', '<', $endDate);
            })->orWhere(function ($query) use ($startDate, $endDate) {
                $query->where('start_date', '>=', $startDate)
                    ->where('end_date', '<', $startDate);
            })->orWhere(function ($query) use ($startDate, $endDate) {
                $query->where('start_date', '<', $endDate)
                    ->where('end_date', '>=', $endDate);
            });
        });

        if ($appointmentId) {
            $overlap->where('id', '!=', $appointmentId);
        }

        return $overlap->count();
    }

    /**
     * @param Request $request
     * @param \App\Models\Appointment $appointment
     * @return array
     */
    public function updateStatus(Request $request, Appointment $appointment)
    {
        $appointment->update([
            'therapist_status' => $request->get('status')
        ]);

        try {
            $translations = TranslationHelper::getTranslations($appointment->patient->language_id);

            $statusTranslation = $translations['appointment.invitation.' . $request->get('status')];
            $patientName = $appointment->patient->first_name . ' ' . $appointment->patient->last_name;

            Appointment::notification($appointment, $translations['appointment.updated_appointment_with'] . ' ' . $patientName . ' ' . $statusTranslation);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }

        return [
            'success' => true,
            'message' => 'success_message.appointment_update',
            'data' => new AppointmentResource($appointment)
        ];
    }

    /**
     * @OA\Delete(
     *     path="/api/appointment/{id}",
     *     tags={"Appointment"},
     *     summary="Delete appointment",
     *     operationId="deleteAppointment",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Id",
     *         required=true,
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
     * @param \App\Models\Appointment $appointment
     *
     * @return array
     * @throws \Exception
     */
    public function destroy(Appointment $appointment)
    {
        if ($appointment->created_by_therapist) {
            $appointment->update(['therapist_status' => Appointment::STATUS_CANCELLED]);
        } else {
            $appointment->update(['patient_status' => Appointment::STATUS_CANCELLED]);
        }

        return ['success' => true, 'message' => 'success_message.appointment_delete'];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function requestAppointment(Request $request)
    {
        $startDate = date_create_from_format('Y-m-d H:i:s', $request->get('start_date'));
        $endDate = date_create_from_format('Y-m-d H:i:s', $request->get('end_date'));

        // Check if overlap with any appointment.
        if ($request->get('id') !== null) {
            $appointment = Appointment::find($request->get('id'));
            $overlap = $this->validateOverlap($startDate, $endDate, $appointment->therapist_id, $appointment->patient_id, $appointment->id);
        } else {
            $overlap = $this->validateOverlap($startDate, $endDate, $request->get('therapist_id'), $request->get('patient_id'));
        }

        if ($overlap) {
             return ['success' => false, 'message' => 'error_message.appointment_overlap'];
        }

        Appointment::updateOrCreate(
            [
                'id' => $request->get('id'),
            ],
            [
                'patient_id' => Auth::id(),
                'therapist_id' => $request->get('therapist_id'),
                'patient_status' => Appointment::STATUS_ACCEPTED,
                'therapist_status' => Appointment::STATUS_INVITED,
                'start_date' => $request->get('start_date'),
                'end_date' => $request->get('end_date'),
                'created_by_therapist' => false,
            ],
        );

        return ['success' => true];
    }

    /**
     * @param Request $request
     * @param \App\Models\Appointment $appointment
     * @return array
     */
    public function updatePatientStatus(Request $request, Appointment $appointment)
    {
        $appointment->update([
            'patient_status' => $request->get('status')
        ]);

        $message = 'success_message.appointment_update';
        return ['success' => true, 'message' => $message, 'data' => new AppointmentResource($appointment)];
    }
}
