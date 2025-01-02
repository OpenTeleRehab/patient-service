<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\CallHistory;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Twilio\Rest\Client;

class CallHistoryController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return array
     */
    public function store(Request $request)
    {
        // Define the start and end of "yesterday" as we sync on 12:00 midnight
        $startOfYesterday = Carbon::yesterday()->startOfDay()->toRfc3339String(); // Start of yesterday
        $endOfYesterday = Carbon::yesterday()->endOfDay()->toRfc3339String(); 
        $twilioAccountSid = env('TWILIO_ACCOUNT_SID');
        $twilioAuthToken = env('TWILIO_AUTH_TOKEN');

        if ($request->has('all')) {
            $twilio = new Client($twilioAccountSid, $twilioAuthToken);
            $rooms = $twilio->video->rooms->read([
                'status' => CallHistory::CALL_COMPLETED,
            ]);

            self::storeData($rooms, $request, $twilio);
        } else {
            $twilio = new Client($twilioAccountSid, $twilioAuthToken);
            $rooms = $twilio->video->rooms->read([
                'status' => CallHistory::CALL_COMPLETED,
                'dateCreatedAfter' => $startOfYesterday,
                'dateCreatedBefore' => $endOfYesterday,
            ]);
            self::storeData($rooms, $request, $twilio);
            
        }

        return ['success' => true, 'message' => 'success_message.call_history_add'];
    }

    public static function storeData($rooms, $request, $twilio)
    {
        foreach ($rooms as $room) {
            $participants = $twilio->video->rooms($room->sid)->participants->read();
            foreach ($participants as $participant) {
                $identity = explode('_', $participant->identity);
                if (count($identity) > 1) {
                    // For other patient service host
                    if ($request->get('country_id') && $identity[1] === $request->get('country_id')) {
                        $patient = User::where('identity', $identity[0])->first();
                        CallHistory::updateOrCreate([
                            'patient_id' => $patient?->id,
                            'date' => $room->dateCreated,
                        ],
                        [
                            'patient_id' => $patient?->id,
                            'date' => $room->dateCreated,
                        ]);
                        
                    } else if ($request->get('host_country_ids') && !in_array($identity[1], $request->get('host_country_ids'))) { // This for golbal paitent host
                        $patient = User::where('identity', $identity[0])->first();
                        CallHistory::updateOrCreate([
                            'patient_id' => $patient?->id,
                            'date' => $room->dateCreated,
                        ],
                        [
                            'patient_id' => $patient?->id,
                            'date' => $room->dateCreated,
                        ]);
                    }
                }
            }
        }
    }
}
