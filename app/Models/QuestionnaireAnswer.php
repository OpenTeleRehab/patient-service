<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionnaireAnswer extends Model
{
    use HasFactory;

    const QUESTIONNAIRE_TYPE_CHECKBOX = 'checkbox';
    const QUESTIONNAIRE_TYPE_MULTIPLE = 'multiple';
    const QUESTIONNAIRE_TYPE_OPEN_NUMBER = 'open-number';
    const QUESTIONNAIRE_TYPE_OPEN_TEXT = 'open-text';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'activity_id',
        'question_id',
        'answer',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var boolean
     */
    public $timestamps = false;
}
