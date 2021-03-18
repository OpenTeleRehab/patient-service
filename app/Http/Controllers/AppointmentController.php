<?php

namespace App\Http\Controllers;

use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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

        $calendarData = DB::table('appointments')
            ->select(DB::raw('ANY_VALUE(DATE(start_date)) AS date'), DB::raw('COUNT(*) AS total'))
            ->where('therapist_id', $request->get('therapist_id'))
            ->whereYear('start_date', $date->format('Y'))
            ->whereMonth('start_date', $date->format('m'))
            ->groupBy(DB::raw('DATE(start_date)'))
            ->get();

        $appointments = Appointment::where('therapist_id', $request->get('therapist_id'))
            ->where('therapist_id', $request->get('therapist_id'))
            ->whereYear('start_date', $date->format('Y'))
            ->whereMonth('start_date', $date->format('m'));

        if ($request->get('selected_date')) {
            $selectedDate = date_create_from_format(config('settings.date_format'), $request->get('selected_date'));
            $appointments->whereDay('start_date', $selectedDate->format('d'));
        }

        $requests = Appointment::where('therapist_id', $request->get('therapist_id'))
            ->where('status', Appointment::STATUS_PENDING)
            ->get();

        $cancelRequests = Appointment::where('therapist_id', $request->get('therapist_id'))
            ->where('status', Appointment::STATUS_REQUEST_CANCELLATION)
            ->get();

        $data = [
            'calendarData' => $calendarData,
            'approves' => AppointmentResource::collection($appointments->get()),
            'requests' => AppointmentResource::collection($requests),
            'cancelRequests' => AppointmentResource::collection($cancelRequests),
        ];
        return ['success' => true, 'data' => $data];
    }
}
