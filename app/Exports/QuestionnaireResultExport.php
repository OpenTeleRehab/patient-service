<?php

namespace App\Exports;

use App\Models\Activity;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Carbon\Carbon;
use App\Models\Forwarder;
use Illuminate\Support\Facades\Http;
use App\Helpers\TranslationHelper;
use App\Models\QuestionnaireAnswer;

class QuestionnaireResultExport
{
    /**
     * @var string $exportDirectoryName
     */
    protected static $exportDirectoryName = 'exports/';

    /**
     * Exports data to an XLSX file.
     *
     * @param $payload.
     * @return string The path to the exported file.
     */
    public static function export($payload)
    {
        $translations = TranslationHelper::getTranslations($payload['lang'], 'therapist_portal');
        $basePath = 'app/' . self::$exportDirectoryName;
        $absolutePath = storage_path($basePath);

        if (!file_exists($absolutePath)) {
            mkdir($absolutePath, 0777, true);
        }
        $access_token = Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE);
        if (isset($payload['clinic_admin_id'])) {
            $response = Http::withToken($access_token)->get(env('ADMIN_SERVICE_URL') . '/get-questionnaire-by-clinic-admin', [
                'clinic_admin_id' => $payload['clinic_admin_id'],
                'lang' => $payload['lang'],
            ]);
        } else if (isset($payload['country_admin_id'])) {
            $response = Http::withToken($access_token)->get(env('ADMIN_SERVICE_URL') . '/get-questionnaire-by-country-admin', [
                'country_admin_id' => $payload['country_admin_id'],
                'lang' => $payload['lang'],
            ]);
        } else {
            $response = Http::withToken($access_token)->get(env('ADMIN_SERVICE_URL') . '/get-questionnaire-by-therapist', [
                'therapist_id' => $payload['therapist_id'],
                'lang' => $payload['lang'],
            ]);
        }
        $questionnaires = $response->json('data');

        // Get countries
        $countriesResponse = Http::get(env('ADMIN_SERVICE_URL') . '/country');
        $json = $countriesResponse->json();
        $countries = $json['data'];

        // Get International classification diseas
        $diseasesResponse =  Http::withToken($access_token)->get(env('ADMIN_SERVICE_URL') . '/disease', [
            'lang' => $payload['lang'],
        ]);
        $json = $diseasesResponse->json();
        $diseases = $json['data'];

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        foreach ($questionnaires as $questionnaire) {
            $questions = [];
            $activities = [];

            if (!empty($questionnaire['questions'])) {
                $questions = $questionnaire['questions'];
                $query = Activity::where('type', Activity::ACTIVITY_TYPE_QUESTIONNAIRE)
                ->where('activity_id', $questionnaire['id'])
                ->where('completed', 1)
                ->whereHas('treatmentPlan', function ($q) use ($payload) {
                    $q->whereHas('user', function ($query) use ($payload) {
                        $query->whereNull('deleted_at');

                        if (isset($payload['clinic_id'])) {
                            $query->where('clinic_id', $payload['clinic_id']);
                        }

                        if (isset($payload['therapist_id'])) {
                            $query->where('therapist_id', $payload['therapist_id']);
                        }
                    });
                });

                $activities = $query->get();
            }

            $sheet = $spreadsheet->createSheet();
            $title = trim(mb_substr($questionnaire['title'] ?? '', 0, 29)) ?: 'Unknown';
            // Remove invalid characters from the title
            $title = preg_replace('/[?]/', '', $title);
            $sheet->setTitle($title);
            $sheet->mergeCells('A1:A2');
            $sheet->mergeCells('B1:B2');
            $sheet->mergeCells('C1:C2');
            $sheet->mergeCells('D1:D2');
            $sheet->mergeCells('E1:E2');
            $sheet->mergeCells('F1:F2');
            $sheet->mergeCells('G1:G2');
            $sheet->mergeCells('H1:H2');
            $sheet->mergeCells('I1:I2');
            $sheet->mergeCells('J1:J2');
            $sheet->mergeCells('K1:K2');
            $sheet->mergeCells('L1:L2');
            $sheet->mergeCells('M1:M2');

            $sheet->setCellValue('A1', $translations['report.questionnaire_result.patient_id']);
            $sheet->setCellValue('B1', $translations['common.country']);
            $sheet->setCellValue('C1', $translations['common.gender']);
            $sheet->setCellValue('D1', $translations['common.date_of_birth']);
            $sheet->setCellValue('E1', $translations['common.age']);
            $sheet->setCellValue('F1', $translations['common.status']);
            $sheet->setCellValue('G1', $translations['common.location']);
            $sheet->setCellValue('H1', $translations['report.questionnaire_result.icd_classification']);
            $sheet->setCellValue('I1', $translations['report.questionnaire_result.diagnostic']);
            $sheet->setCellValue('J1', $translations['common.start_date']);
            $sheet->setCellValue('K1', $translations['common.end_date']);
            $sheet->setCellValue('L1', $translations['common.submitted_date']);
            $sheet->setCellValue('M1', $translations['report.questionnaire_result.questionnaire_name']);
            $sheet->getColumnDimension('A')->setWidth(20);
            $sheet->getColumnDimension('B')->setWidth(20);
            $sheet->getColumnDimension('C')->setWidth(20);
            $sheet->getColumnDimension('D')->setWidth(20);
            $sheet->getColumnDimension('E')->setWidth(20);
            $sheet->getColumnDimension('F')->setWidth(20);
            $sheet->getColumnDimension('G')->setWidth(20);
            $sheet->getColumnDimension('H')->setWidth(20);
            $sheet->getColumnDimension('I')->setWidth(20);
            $sheet->getColumnDimension('J')->setWidth(20);
            $sheet->getColumnDimension('K')->setWidth(20);
            $sheet->getColumnDimension('L')->setWidth(20);
            $sheet->getColumnDimension('M')->setWidth(20);

            $colIndex = 14;
            foreach ($questions as $question) {
                $endColIndex = $colIndex;
                // Each question spans 3 columns for open number and 2 for multiple and checkbox
                if ($question['type'] === QuestionnaireAnswer::QUESTIONNAIRE_TYPE_OPEN_NUMBER) {
                    $endColIndex = $colIndex + 2;
                } else if ($question['type'] !== QuestionnaireAnswer::QUESTIONNAIRE_TYPE_OPEN_TEXT) {
                    $endColIndex = $colIndex + 1;
                }
                // Convert numeric column index to Excel column letters
                $startCol = Coordinate::stringFromColumnIndex($colIndex);
                $endCol = Coordinate::stringFromColumnIndex($endColIndex);

                $sheet->setCellValue($startCol . '1', $question['title']);
                $sheet->mergeCells($startCol . '1:' . $endCol . '1');

                // Answer row title
                $sheet->setCellValue($startCol . '2', $translations['report.questionnaire_result.answer']);
                if ($question['type'] !== QuestionnaireAnswer::QUESTIONNAIRE_TYPE_OPEN_TEXT) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex + 1) . '2', $translations['report.questionnaire_result.value']);
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIndex + 1))->setWidth(20);
                }
                
                if ($question['type'] === QuestionnaireAnswer::QUESTIONNAIRE_TYPE_OPEN_NUMBER) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex + 2) . '2', $translations['report.questionnaire_result.threshold']);
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIndex + 2))->setWidth(20);
                }
                $sheet->getColumnDimension($startCol)->setWidth(20);

                // Move to the next question
                if ($question['type'] === QuestionnaireAnswer::QUESTIONNAIRE_TYPE_OPEN_NUMBER) {
                    $colIndex += 3;
                } else if ($question['type'] !== QuestionnaireAnswer::QUESTIONNAIRE_TYPE_OPEN_TEXT) {
                    $colIndex += 2;
                }
            }

            if (isset($endCol)) {
                $sheet->getStyle('A1:' . $endCol . '2')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle('A1:' . $endCol . '2')->getFont()->setBold(true);
                $sheet->getRowDimension('1')->setRowHeight(25);
                $sheet->getRowDimension('2')->setRowHeight(25);
                $sheet->getStyle('A1:' . $endCol . '2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A1:' . $endCol . '2')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            }

            // Render data
            $row = 3;
            $startRow = 3;
            foreach ($activities as $activity) {
                $treatmentPlan = $activity->treatmentPlan;
                $patient = $treatmentPlan->user;
                $questionnaireAnswers = $activity->answers;
                $age = Carbon::parse($patient->date_of_birth)->age;
                $dob = Carbon::parse($patient->date_of_birth)->toDateString();
                $status = $patient->enabled === 1 ? $translations['common.active'] : $translations['common.inactive'];
                $location = $translations['common.' . $patient->location];
                $gender = $translations['common.' . $patient->gender];
                $country = self::getCountry($patient->country_id, $countries)['name'];
                $disease = $diseases[$treatmentPlan->disease_id] ?? null;
                $data = [
                    $patient?->identity,
                    $country,
                    $gender,
                    $dob,
                    $age,
                    $status,
                    $location,
                    $disease['name'] ?? '',
                    $treatmentPlan?->name,
                    $treatmentPlan?->start_date->format('Y-m-d'),
                    $treatmentPlan?->end_date->format('Y-m-d'),
                    $activity->submitted_date->format('Y-m-d'),
                    $questionnaire['title'],
                ];

                $colIndex = 14;
                $answerStartRow = $row;
                $maxAnswerRow = $row;
                foreach ($questions as $question) {
                    $patientAnswer = null;
                    foreach ($questionnaireAnswers as $questionnaireAnswer) {
                        if ($questionnaireAnswer->question_id === $question['id']) {
                            $patientAnswer = $questionnaireAnswer->answer;
                            break;
                        }
                    }

                    if ($patientAnswer) {
                        $answer = unserialize($patientAnswer);
                        $answerDescriptions = [];
                        $values = [];
                        $thresholds = [];

                        if ($question['type'] === QuestionnaireAnswer::QUESTIONNAIRE_TYPE_CHECKBOX) {
                            $foundAnswers = array_filter($question['answers'], fn($questionAnswer) => in_array($questionAnswer['id'], $answer));
                            $answerDescriptions = array_column($foundAnswers, 'description');
                            $values = array_column($foundAnswers, 'value');
                        } else if ($question['type'] === QuestionnaireAnswer::QUESTIONNAIRE_TYPE_MULTIPLE) {
                            $foundAnswer = current(array_filter($question['answers'], fn($questionAnswer) => $questionAnswer['id'] === $answer));
                            $answerDescriptions[] = $foundAnswer['description'];
                            $values[] = $foundAnswer['value'] ?? '';
                        } else if ($question['type'] === QuestionnaireAnswer::QUESTIONNAIRE_TYPE_OPEN_NUMBER) {
                            $foundAnswer = current(array_filter($question['answers'], fn($questionAnswer) => $questionAnswer['question_id'] === $question['id']));
                            $answerDescriptions[] = $answer;
                            $values[] = $foundAnswer ? $foundAnswer['value'] : '';
                            $thresholds[] = $foundAnswer ? $foundAnswer['threshold'] : '';
                        } else {
                            $answerDescriptions[] = $answer;
                        }

                        // For checkbox questions, we need to create a new row for each selected answer as it can be more than one answer
                        if ($question['type'] === QuestionnaireAnswer::QUESTIONNAIRE_TYPE_CHECKBOX) {
                            $answerRow = $answerStartRow;
                            foreach ($answerDescriptions as $index => $description) {
                                $startCol = Coordinate::stringFromColumnIndex($colIndex);
                                $sheet->setCellValue($startCol . $answerRow, $description ?? '');
                                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex + 1) . $answerRow, $values[$index] ?? '');
                                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex + 2) . $answerRow, $thresholds[$index] ?? '');
                                $sheet->getRowDimension($answerRow)->setRowHeight(20);
                                $answerRow++;
                            }
                            // Set the max answer row for multiple answers.
                            $maxAnswerRow = max($maxAnswerRow, $answerRow - 1);
                        } else {
                            $startCol = Coordinate::stringFromColumnIndex($colIndex);
                            $sheet->setCellValue($startCol . $answerStartRow, $answerDescriptions[0] ?? '');
                            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex + 1) . $answerStartRow, $values[0] ?? '');
                            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex + 2) . $answerStartRow, $thresholds[0] ?? '');
                            $sheet->getRowDimension($answerStartRow)->setRowHeight(20);
                        }
                    }

                    // Move to the next set of columns
                    if ($question['type'] === QuestionnaireAnswer::QUESTIONNAIRE_TYPE_OPEN_NUMBER) {
                        $colIndex += 3;
                    } else if ($question['type'] !== QuestionnaireAnswer::QUESTIONNAIRE_TYPE_OPEN_TEXT) {
                        $colIndex += 2;
                    }
                }
                // Write the patient info to the sheet and merge cells of multiple answer rows.
                foreach ($data as $index => $value) {
                    $col = Coordinate::stringFromColumnIndex($index + 1);

                    if ($answerStartRow !== $maxAnswerRow) {
                        $sheet->mergeCells($col . $answerStartRow . ':' . $col . $maxAnswerRow);
                    }

                    $sheet->setCellValue($col . $answerStartRow, $value);
                }
                $sheet->getRowDimension($row)->setRowHeight(20);
                $row = $maxAnswerRow + 1;
            }

            if (isset($endCol)) {
                // Apply Borders and aling center to All Data Rows
                $sheet->getStyle('A3:' . $endCol . ($row === $startRow ? $row : $row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle('A3:' . $endCol . ($row === $startRow ? $row : $row - 1))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            }
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'Questionnaire-Answers-' . date('Y-m-d_His') . '.xlsx';
        $filePath = $absolutePath . $fileName;

        $writer->save($filePath);
        return $basePath . $fileName;
    }

    /**
     * Get the country by ID.
     *
     * @param int $countryId
     * @param array $countries
     * @return array|null
     */
    private static function getCountry($countryId, $countries)
    {
        return collect($countries)->firstWhere('id', $countryId) ?? null;
    }
}
