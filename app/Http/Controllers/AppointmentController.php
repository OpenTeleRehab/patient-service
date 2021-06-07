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

        $data = [
            'calendarData' => $calendarData,
            'approves' => AppointmentResource::collection($appointments->orderBy('start_date')->get()),
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
            'therapist_status' => Appointment::STATUS_ACCEPTED,
            'patient_status' => Appointment::STATUS_INVITED,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'note' => $request->get('note'),
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

        $message = 'success_message.appointment_update';
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
        $appointment->delete();

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
        $overlap = $this->validateOverlap($startDate, $endDate, $request->get('therapist_id'), $request->get('patient_id'));

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
