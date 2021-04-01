<?php

namespace App\Http\Controllers;

use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AppointmentController extends Controller
{
    /**
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

        $appointments = Appointment::where('therapist_id', $request->get('therapist_id'))
            ->where('therapist_id', $request->get('therapist_id'));

        if ($request->get('selected_from_date')) {
            $selectedFromDate = date_create_from_format('Y-m-d H:i:s', $request->get('selected_from_date'));
            $selectedToDate = date_create_from_format('Y-m-d H:i:s', $request->get('selected_to_date'));
            $appointments->where('start_date', '>=', $selectedFromDate)
                ->where('start_date', '<=', $selectedToDate);
        } else {
            $appointments->where('end_date', '>', $now);
        }

        $requests = Appointment::where('therapist_id', $request->get('therapist_id'))
            ->where('status', Appointment::STATUS_PENDING)
            ->orderBy('created_at')
            ->get();

        $cancelRequests = Appointment::where('therapist_id', $request->get('therapist_id'))
            ->where('status', Appointment::STATUS_REQUEST_CANCELLATION)
            ->where('end_date', '>', $now)
            ->orderBy('start_date')
            ->get();

        $data = [
            'calendarData' => $calendarData,
            'approves' => AppointmentResource::collection($appointments->orderBy('start_date')->get()),
            'requests' => AppointmentResource::collection($requests),
            'cancelRequests' => AppointmentResource::collection($cancelRequests),
        ];
        return ['success' => true, 'data' => $data];
    }

    /**
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
            'status' => Appointment::STATUS_APPROVED,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return ['success' => true, 'message' => 'success_message.appointment_add'];
    }

    /**
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

        $appointment->update([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => Appointment::STATUS_APPROVED,
        ]);

        return ['success' => true, 'message' => 'success_message.appointment_update'];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getPatientAppointments(Request $request)
    {
        $appointments = Appointment::where('status', '!=', Appointment::STATUS_PENDING)
            ->where('patient_id', Auth::id())
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
            'status' => $request->get('status')
        ]);

        $message = 'success_message.appointment_update';

        // Check if deleting a cancellation request.
        if ($request->get('status') === Appointment::STATUS_APPROVED) {
            $message = 'success_message.appointment_cancellation_request_delete';
        }

        return ['success' => true, 'message' => $message, 'data' => new AppointmentResource($appointment)];
    }

    /**
     * @param \App\Models\Appointment $appointment
     *
     * @return array
     * @throws \Exception
     */
    public function destroy(Appointment $appointment)
    {
        if ($appointment->status === Appointment::STATUS_REQUEST_CANCELLATION) {
            $appointment->delete();
            return ['success' => true, 'message' => 'success_message.appointment_delete'];
        }

        return ['success' => false, 'message' => 'error_message.appointment_delete'];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function requestAppointment(Request $request)
    {
        Appointment::updateOrCreate(
            [
                'patient_id' => Auth::id(),
                'therapist_id' => $request->get('therapist_id'),
                'status' => Appointment::STATUS_PENDING,
            ],
        );
        return ['success' => true];
    }
}
