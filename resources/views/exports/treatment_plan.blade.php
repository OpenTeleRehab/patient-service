<style>
    body {
        font-family: 'Khmer OS', serif;
    }
    h1, h2, h3, h4 {
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

<h1>{{ $treatmentPlan['name'] }}</h1>
<pre>{!! $treatmentPlan['description'] !!}</pre>

@php
    $startDate = date_create_from_format('d/m/Y', $treatmentPlan['start_date']);
@endphp

@for($w = 0; $w < $treatmentPlan['total_of_weeks']; $w++)
    <h3>{{ $translations['common.week'] ?? 'Week' }} {{ $w + 1 }} </h3>
    <table>
        <thead>
        <tr>
            @for($d = 0; $d < 7; $d++)
                @php
                    $date = clone $startDate;
                    $date->modify('+' . ($w * 7  + $d) .'day');
                @endphp
                <th>
                    {{ $translations['common.day'] ?? 'Day' }} {{ $d + 1 }}<small>({{ $date->format(config('settings.date_format')) }})</small>
                </th>
            @endfor
        </tr>
        </thead>
        <tbody>

        @php
            $dayActivities = [];
            for ($d = 0; $d < 7; $d++) {
                $dayActivities[$d] = array_values(array_filter($treatmentPlan['activities'], function ($a) use ($w, $d) {
                    return $a['week'] === $w + 1 && $a['day'] === $d + 1;
                }));
            }
            $numberOfLongestItems = count(max($dayActivities));
        @endphp
        @for($i = 0; $i < $numberOfLongestItems; $i++)
            <tr>
                @for($d = 0; $d < 7; $d++)
                    <td>
                        @php
                            $activity = $dayActivities[$d][$i] ?? null;
                        @endphp

                        @if($activity && $activity['type'] === \App\Models\Activity::ACTIVITY_TYPE_EXERCISE)
                            @php
                                $file = $activity['files'][0];
                            @endphp
                            @if($file['fileType'] === 'audio/mpeg')
                                <img width="190" src="http://localhost/images/music.png">
                            @elseif($file['fileType'] === 'video/mp4')
                                <img width="190" src="{{ env("ADMIN_SERVICE_URL") . '/api/file/' . $file['id'] . '?thumbnail=1'}}">
                            @else
                                <img width="190" src="{{ env("ADMIN_SERVICE_URL") . '/api/file/' . $file['id']}}">
                            @endif

                            <h4>{{ $activity['title'] }}</h4>
                            @if($activity['sets'] > 0)
                                <span>{{ str_replace(['${sets}', '${reps}'], [$activity['sets'], $activity['reps']], $translations['activity.number_of_sets_and_reps'] ?? '') }}</span>
                            @endif
                        @endif

                        @if($activity && $activity['type'] === \App\Models\Activity::ACTIVITY_TYPE_MATERIAL)
                            <img width="190" src="http://localhost/images/material.png">
                            <h4>{{ $activity['title'] }}</h4>
                            <span>{{ $translations[$activity['file']['fileGroupType']] ?? '' }}</span>
                        @endif

                        @if($activity && $activity['type'] === \App\Models\Activity::ACTIVITY_TYPE_QUESTIONNAIRE)
                            <img width="190" src="http://localhost/images/questionnaire.png">
                            <h4>{{ $activity['title'] }}</h4>
                            <b>{{ count($activity['questions'])  }}</b> {{ $translations['activity.questions'] ?? 'questions' }}
                        @endif
                    </td>
                @endfor
            </tr>
        @endfor
        </tbody>
    </table>
@endfor
