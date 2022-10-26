<?php

namespace App\Http\Controllers;

use App\Models\Forwarder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ForwarderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return mixed
     */
    public function index(Request $request)
    {
        $service_name = $request->route()->getName();
        $endpoint = str_replace('api/', '/', $request->path());

        if ($service_name !== null && str_contains($service_name, Forwarder::GADMIN_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::GADMIN_SERVICE);
            return Http::withToken($access_token)->get(env('GADMIN_SERVICE_URL') . $endpoint, $request->all());
        }

        if ($service_name !== null && str_contains($service_name, Forwarder::ADMIN_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE);
            return Http::withToken($access_token)->get(env('ADMIN_SERVICE_URL') . $endpoint, $request->all());
        }

        if ($service_name !== null && str_contains($service_name, Forwarder::THERAPIST_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE);
            return Http::withToken($access_token)->get(env('THERAPIST_SERVICE_URL') . $endpoint, $request->all());
        }
    }
}
