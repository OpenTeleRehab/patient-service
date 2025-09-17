<style>
    body {
        font-family: 'Khmer OS', serif;
    }
    h1, h2, h3, h4, h5, h6,
    p {
        margin: 0;
    }
    table {
        border-collapse: collapse;
        margin-bottom: 30px;
        width: 100%;
    }
    table, th, td {
        border: 1px solid black;
    }
    th, td {
        padding: 5px;
        vertical-align: top;
        width: 200px;
    }
</style>

<img width="190" src="http://localhost/images/logo-horizontal.svg">

<h1>{{ $translations['chat_message.history'] ?? 'User Chat/Video Call History' }}</h1>

<table>
    <thead>
        <tr>
            <th align="left">{{ $translations['common.name'] ?? 'Name' }}</th>
            <th align="left">{{ $translations['common.message'] ?? 'Message' }}</th>
            <th align="left">{{ $translations['common.datetime'] ?? 'Date Time' }}</th>
        </tr>
    </thead>

    <tbody>
        @foreach ($messages as $message)
            <tr>
                <th align="left">{{ strpos($message['u']['username'], 'P') !== false ? $patient['name'] : $therapist['name'] }}</th>
                <td align="left">
                    @switch($message)
                        @case($message['msg'] === \App\Models\Message::JITSI_CALL_AUDIO_ENDED || $message['msg'] === \App\Models\Message::JITSI_CALL_VIDEO_ENDED)
                            {{ $translations[$message['msg']] ?? $message['msg'] }}
                            @if (isset($message['ts']) && isset($message['editedAt']))
                                {{ \App\Models\Message::getCallDuration($message['ts'], $message['editedAt']) }}
                            @endif
                            @break

                        @case($message['msg'] === \App\Models\Message::JITSI_CALL_AUDIO_MISSED || $message['msg'] === \App\Models\Message::JITSI_CALL_VIDEO_MISSED)
                            {{ $translations[$message['msg']] ?? $message['msg'] }}
                            @break

                        @case($message['msg'] === \App\Models\Message::JITSI_CALL_ACCEPTED)
                            {{ $translations['video_call_accepted'] ?? 'Video Call Accepted' }}
                            @break

                        @case(@isset($message['file']))
                            {{ $message['file']['name'] }}
                            @break

                        @default
                            {{ $message['msg'] }}
                    @endswitch
                </td>
                <td align="left">{{ $message['_updatedAt'] ? Carbon\Carbon::parse($message['_updatedAt'])->timezone($timezone)->format(config('settings.datetime_format')) : '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
