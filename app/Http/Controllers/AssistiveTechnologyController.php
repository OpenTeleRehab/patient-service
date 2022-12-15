<?php

namespace App\Http\Controllers;

use App\Http\Resources\AssistiveTechnologyResource;
use App\Models\Appointment;
use App\Models\AssistiveTechnology;
use Illuminate\Http\Request;

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

        // TODO: Search assistive technology by name.
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

        $appointment = Appointment::create([
            'patient_id' => $request->get('patientId'),
            'therapist_id' => $request->get('therapistId'),
            'therapist_status' => Appointment::STATUS_ACCEPTED,
            'patient_status' => Appointment::STATUS_INVITED,
            'start_date' => date_create_from_format('Y-m-d H:i:s', $followUpDate . ' ' . '01:00:00'),
            'end_date' => date_create_from_format('Y-m-d H:i:s', $followUpDate . ' ' . '10:00:00'),
            'note' => AssistiveTechnology::ASSISTIVE_TECHNOLOGY_FOLLOW_UP,
        ]);

        AssistiveTechnology::create([
            'assistive_technology_id' => $request->get('assistiveTechnologyId'),
            'patient_id' => $request->get('patientId'),
            'therapist_id' => $request->get('therapistId'),
            'appointment_id' => $appointment->id,
            'provision_date' => date('Y-m-d', strtotime($provisionDate)),
            'follow_up_date' => date('Y-m-d', strtotime($followUpDate)),
        ]);

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

        AssistiveTechnology::find($id)->update([
            'assistive_technology_id' => $request->get('assistiveTechnologyId'),
            'provision_date' => date('Y-m-d', strtotime($provisionDate)),
            'follow_up_date' => date('Y-m-d', strtotime($followUpDate)),
        ]);

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
}
