<?php

namespace App\Http\Controllers;

use App\Events\AddLogToAdminServiceEvent;
use App\Http\Resources\AssistiveTechnologyResource;
use App\Models\Appointment;
use App\Models\AssistiveTechnology;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

class AssistiveTechnologyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function index(Request $request)
    {
        $query = AssistiveTechnology::query();

        if ($request->has('patient_id')) {
            $query->where('patient_id', $request->get('patient_id'));
        }

        if ($request->has('filters')) {
            $filters = $request->get('filters');
            $query->where(function ($query) use ($filters) {
                foreach ($filters as $filter) {
                    $filterObj = json_decode($filter);
                    if ($filterObj->columnName === 'assistive_name') {
                        $query->where('assistive_technology_id', $filterObj->value);
                    } elseif ($filterObj->columnName === 'provision_date') {
                        $provisionDate = date_create_from_format('d/m/Y', $filterObj->value);
                        $query->whereDate($filterObj->columnName, date_format($provisionDate, config('settings.defaultTimestampFormat')));
                    } elseif ($filterObj->columnName === 'follow_up_date') {
                        $followUpDate = date_create_from_format('d/m/Y', $filterObj->value);
                        $query->whereDate($filterObj->columnName, date_format($followUpDate, config('settings.defaultTimestampFormat')));
                    } else {
                        $query->where($filterObj->columnName, 'like', '%' .  $filterObj->value . '%');
                    }
                }
            });
        }

        if ($request->has('search_value')) {
            $query->whereIn('assistive_technology_id', $request->get('search_value'));
        }

        $assistiveTechnologies = $query->paginate($request->get('page_size'));

        return [
            'success' => true,
            'data' => AssistiveTechnologyResource::collection($assistiveTechnologies),
            'info' => [
                'current_page' => $assistiveTechnologies->currentPage(),
                'total_count' => $assistiveTechnologies->total(),
            ],
        ];
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return array
     */
    public function store(Request $request)
    {
        $provisionDate = str_replace('/', '-', $request->get('provisionDate'));
        $followUpDate = str_replace('/', '-', $request->get('followUpDate'));
        $appointmentFrom = $request->get('appointmentFrom');
        $appointmentTo = $request->get('appointmentTo');
        $appointment = null;
        $user = Auth::user();

        if ($followUpDate) {
            // Check if overlap with any appointment.
            $overlap = $this->validateOverlap($appointmentFrom, $appointmentTo, $request->get('therapistId'), $request->get('patientId'));

            if ($overlap) {
                return ['success' => false, 'message' => 'error_message.appointment_overlap'];
            }

            $appointment = Appointment::create([
                'patient_id' => $request->get('patientId'),
                'therapist_id' => $request->get('therapistId'),
                'therapist_status' => Appointment::STATUS_ACCEPTED,
                'patient_status' => Appointment::STATUS_INVITED,
                'start_date' => $appointmentFrom,
                'end_date' => $appointmentTo,
            ]);
            // Activity log
            $lastLoggedActivity = Activity::all()->last();
            event(new AddLogToAdminServiceEvent($lastLoggedActivity, $user));
        }

        AssistiveTechnology::create([
            'assistive_technology_id' => $request->get('assistiveTechnologyId'),
            'patient_id' => $request->get('patientId'),
            'therapist_id' => $request->get('therapistId'),
            'appointment_id' => $appointment ? $appointment->id : null,
            'provision_date' => date('Y-m-d', strtotime($provisionDate)),
            'follow_up_date' => $followUpDate ? date('Y-m-d', strtotime($followUpDate)) : null,
        ]);
        // Activity log
        $lastLoggedActivity = Activity::all()->last();
        event(new AddLogToAdminServiceEvent($lastLoggedActivity, $user));

        return ['success' => true, 'message' => 'success_message.assistive_technology_add'];
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param integer $id
     *
     * @return array
     */
    public function update(Request $request, $id)
    {
        $provisionDate = str_replace('/', '-', $request->get('provisionDate'));
        $followUpDate = str_replace('/', '-', $request->get('followUpDate'));
        $appointmentFrom = $request->get('appointmentFrom');
        $appointmentTo = $request->get('appointmentTo');
        $user = Auth::user();

        $assistive = AssistiveTechnology::find($id);

        if ($assistive->appointment_id) {
            // Check if overlap with any appointment.
            $overlap = $this->validateOverlap($appointmentFrom, $appointmentTo, $request->get('therapistId'), $request->get('patientId'), $assistive->appointment_id);

            if ($overlap) {
                return ['success' => false, 'message' => 'error_message.appointment_overlap'];
            }

            $appointment = Appointment::find($assistive->appointment_id);

            $updateField = [
                'start_date' => $appointmentFrom,
                'end_date' => $appointmentTo,
            ];

            // Update patient status if appointment date changed.
            if ($appointmentFrom != $appointment->start_date || $appointmentTo != $appointment->end_date) {
                $updateField['patient_status'] = Appointment::STATUS_INVITED;
            }

            $appointment->update($updateField);
            // Activity log
            $lastLoggedActivity = Activity::all()->last();
            event(new AddLogToAdminServiceEvent($lastLoggedActivity, $user));
        }

        $assistive->update([
            'assistive_technology_id' => $request->get('assistiveTechnologyId'),
            'provision_date' => date('Y-m-d', strtotime($provisionDate)),
            'follow_up_date' => $followUpDate ? date('Y-m-d', strtotime($followUpDate)) : null,
        ]);
        // Activity log
        $lastLoggedActivity = Activity::all()->last();
        event(new AddLogToAdminServiceEvent($lastLoggedActivity, $user));

        return ['success' => true, 'message' => 'success_message.assistive_technology_update'];
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param integer $id
     * @return array
     */
    public function destroy($id)
    {
        AssistiveTechnology::find($id)->delete();
        // Activity log
        $lastLoggedActivity = Activity::all()->last();
        event(new AddLogToAdminServiceEvent($lastLoggedActivity, Auth::user());

        return ['success' => true, 'message' => 'success_message.assistive_technology_delete'];
    }

    /**
     * @return mixed
     */
    public function getAssistiveTechnologyProvidedPatients()
    {
        return AssistiveTechnology::join('users', 'assistive_technologies.patient_id', 'users.id')
            ->withTrashed()
            ->get([
                'assistive_technologies.*',
                'users.identity',
                'users.clinic_id',
                'users.country_id',
                'users.therapist_id',
                'users.date_of_birth',
                'users.enabled',
                'users.gender',
                'users.id as patient_id'
            ]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return bool
     */
    public function getUsedAssistiveTechnology(Request $request)
    {
        $assistiveTechnologyId = $request->get('assistive_technology_id');
        $assistiveTechnology = AssistiveTechnology::where('assistive_technology_id', $assistiveTechnologyId)->count();

        return $assistiveTechnology > 0;
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
}
