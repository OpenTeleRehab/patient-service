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
    .indent {
        margin-left: 50px;
    }
    .mb-1 {
        margin: 10px;
    }
    .text-warning {
        color:rgb(255, 145, 0);
    }
    .text-danger {
        color: #ff0000;
    }
</style>

<img width="190" src="http://localhost/images/logo-horizontal.svg">

<h1>{{ $treatmentPlan->name }}</h1>
<h2>{{ $diseaseName }}</h2>
<pre>{!! $treatmentPlan->description !!}</pre>

@php
    $startDate = $treatmentPlan->start_date;
@endphp

@for($w = 0; $w < $treatmentPlan->total_of_weeks; $w++)
    {{--  Add new page for each week --}}
    @if($w > 0)
        <pagebreak>
    @endif
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
                $dayActivities[$d] = array_values(array_filter($activities, function ($a) use ($w, $d) {
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
                                <img width="190" src="{{ 'http://admin_service/api/file/' . $file['id'] . '?thumbnail=1' }}">
                            @else
                                <img width="190" src="{{ 'http://admin_service/api/file/' . $file['id'] }}">
                            @endif

                            <h4>{{ $activity['title'] }}</h4>
                            @if($activity['sets'] > 0)
                                <span>{{ str_replace(['${sets}', '${reps}'], [$activity['sets'], $activity['reps']], $translations['activity.number_of_sets_and_reps'] ?? '') }}</span>
                            @endif
                        @endif

                        @if($activity && $activity['type'] === \App\Models\Activity::ACTIVITY_TYPE_MATERIAL)
                            @if($activity['file'] && ($activity['file']['hasThumbnail'] || $activity['file']['fileGroupType'] === 'common.type.image'))
                                <img width="190" src="{{ 'http://admin_service/api/file/' . $activity['file']['id'] . '?thumbnail=' . $activity['file']['hasThumbnail'] }}">
                            @else
                                <img width="190" src="http://localhost/images/material.png">
                            @endif

                            <h4>{{ $activity['title'] }}</h4>

                            @if($activity['file'])
                                <span>{{ $translations[$activity['file']['fileGroupType']] }}</span>
                            @endif
                        @endif

                        @if($activity && $activity['type'] === \App\Models\Activity::ACTIVITY_TYPE_QUESTIONNAIRE)
                            <img width="190" src="http://localhost/images/questionnaire.png">
                            <h4>{{ $activity['title'] }}</h4>
                            <b>{{ count($activity['questions'])  }}</b> {{ $translations['activity.questions'] ?? 'questions' }}
                        @endif

                        @if($activity && $activity['type'] === \App\Models\Activity::ACTIVITY_TYPE_GOAL)
                            <img width="190" src="http://localhost/images/satisfaction.png">
                            <h4>{{ $activity['title'] }}</h4>
                            <span>{{ $translations['activity.goal.' . $activity['frequency']] ?? '' }}</span>
                        @endif
                    </td>
                @endfor
            </tr>
        @endfor
        </tbody>
    </table>
@endfor


@for($w = 0; $w < $treatmentPlan->total_of_weeks; $w++)
    {{--  Add new page for each week --}}
    <pagebreak>
    <h3>{{ $translations['common.week'] ?? 'Week' }} {{ $w + 1 }} </h3>

    @for($d = 0; $d < 7; $d++)
        @php
            $date = clone $startDate;
            $date->modify('+' . ($w * 7  + $d) .'day');

            $dayActivities = array_filter($activities, function ($a) use ($w, $d) {
                return $a['week'] === $w + 1 && $a['day'] === $d + 1;
            });
        @endphp
        <div class="indent">
            <h4>
                {{ $translations['common.day'] ?? 'Day' }} {{ $d + 1 }}<small>({{ $date->format(config('settings.date_format')) }})</small>
            </h4>

            @foreach($dayActivities as $activity)
                <div class="indent mb-1">
                    <h4>{{ $activity['title'] }}</h4>

                    @if($activity && $activity['type'] === \App\Models\Activity::ACTIVITY_TYPE_EXERCISE)
                        @php
                            $file = $activity['files'][0];
                        @endphp
                        @if($file['fileType'] === 'audio/mpeg')
                            <img width="390" src="http://localhost/images/music.png">
                        @elseif($file['fileType'] === 'video/mp4')
                            <img width="390" src="{{ 'http://admin_service/api/file/' . $file['id'] . '?thumbnail=1' }}">
                        @else
                            <img width="390" src="{{ 'http://admin_service/api/file/' . $file['id'] }}">
                        @endif

                        @if($activity['sets'] > 0)
                            <div>{{ str_replace(['${sets}', '${reps}'], [$activity['sets'], $activity['reps']], $translations['activity.number_of_sets_and_reps'] ?? '') }}</div>
                        @endif

                        @foreach($activity['additional_fields'] as $additionalField)
                            <h5>{{ $additionalField['field'] }}</h5>
                            <p>{{ $additionalField['value'] }}</p>
                        @endforeach
                    @endif

                    @if($activity && $activity['type'] === \App\Models\Activity::ACTIVITY_TYPE_MATERIAL)
                        @if($activity['file'] && $activity['file']['fileGroupType'] == 'common.type.image')
                            <img width="390" src="{{ 'http://admin_service/api/file/' . $file['id'] . $activity['file']['id'] }}">
                        @elseif($activity['file'])
                            <span>{{ $translations['activity.file_attachment'] ?? 'File attachment' }}: <i>{{ $activity['title'] }}_{{ $activity['file']['fileName'] }}</i></span>
                        @endif
                    @endif

                    @if($activity && $activity['type'] === \App\Models\Activity::ACTIVITY_TYPE_QUESTIONNAIRE)
                        <p>{{ $activity['description'] }}</p>
                        @foreach($activity['questions'] as $question)
                            @php
                                $questionAnswer = current(array_filter($activity['answers']->resolve(), function($a) use ($question) { return $a['question_id'] === $question['id']; }));
                            @endphp
                            <div class="mb-1">
                                <h5>{{ $translations['activity.question'] ?? 'Question' }} {{ $loop->iteration }}</h5>
                                @if($question['file'])
                                    <img width="390" src="{{ 'http://admin_service/api/file/' . $question['file']['id'] }}">
                                @endif
                                <p>{{ $question['title'] }}</p>
                                @if($question['type'] === 'open-text' || $question['type'] === 'open-number')
                                    @if($question['answers'])
                                        @foreach($question['answers'] as $answer)
                                            @if($question['type'] === 'open-number' && $answer['value'] && !$isPatient)
                                                <div class="text-warning">{{$translations['question.answer_value']}}: {{$answer['value']}}</div>
                                            @endif
                                            @if($question['type'] === 'open-number' && $answer['threshold'] && !$isPatient)
                                                <div class="text-danger">{{$translations['question.answer_threshold']}}: {{$answer['threshold']}}</div>
                                            @endif
                                        @endforeach
                                    @endif
                                    <input size="500" value="{{ $questionAnswer ? $questionAnswer['answer'] : '' }}">
                                @elseif($question['answers'])
                                    @foreach($question['answers'] as $answer)
                                        <div class="indent">
                                            @if($question['type'] === 'checkbox')
                                                <input type="checkbox" {{ $questionAnswer && in_array($answer['id'], $questionAnswer['answer']) ? 'checked=checked' : '' }}>
                                            @else
                                                <input type="radio" {{ $questionAnswer && $questionAnswer['answer'] === $answer['id'] ? 'checked=checked' : '' }}>
                                            @endif

                                            <label>
                                                {{ $answer['description'] }}
                                            </label>
                                            @if($answer['value'] && !$isPatient)
                                                <span class="text-warning">{{$translations['question.answer_value']}}: {{$answer['value']}}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        @endforeach
                        @if (!$isPatient)
                            <h4>{{ $translations['questionnaire.total_score'] }}: {{ $activity['score'] }}</h4>
                        @endif
                    @endif

                    @if($activity && $activity['type'] === \App\Models\Activity::ACTIVITY_TYPE_GOAL)
                        <div>{{ $translations['activity.goal.' . $activity['frequency']] ?? '' }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endfor
@endfor
