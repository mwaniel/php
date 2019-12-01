<?php


class SurveyAnswer extends Model
{
    // ina identify records
    protected static $primaryKey = 'survey_answer_id';

    // list ziko kwa table
    protected static $fields = array(
        'survey_answer_id',
        'survey_response_id',
        'question_id',
        'answer_value',
    );
}

?>
