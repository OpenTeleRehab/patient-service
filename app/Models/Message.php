<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    const JITSI_CALL_AUDIO_STARTED = 'jitsi_call_audio_started';
    const JITSI_CALL_AUDIO_ENDED = 'jitsi_call_audio_ended';
    const JITSI_CALL_AUDIO_MISSED = 'jitsi_call_audio_missed';
    const JITSI_CALL_VIDEO_STARTED = 'jitsi_call_video_started';
    const JITSI_CALL_VIDEO_ENDED = 'jitsi_call_video_ended';
    const JITSI_CALL_VIDEO_MISSED = 'jitsi_call_video_missed';
    const JITSI_CALL_ACCEPTED = 'jitsi_call_accepted';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];

    /**
     * @param string $start
     * @param string $end
     *
     * @return int
     */
    public static function getCallDuration($start, $end)
    {
        $start = Carbon::parse($start);
        $end = Carbon::parse($end);

        return gmdate('H:i:s', $end->diffInSeconds($start));
    }
}
