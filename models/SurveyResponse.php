<?php

/**
 * The SurveyResponse class is a Model representing the survey_response table, used to 
 * store an instance of a survey being taken
 *
 * @author Daniel mwaniki
 * @copyright Copyright (c) 2018, Daniel mwaniki
 */
class SurveyResponse extends Model
{
    // uniquely identify a record
    protected static $primaryKey = 'survey_response_id';

    //  fields in the table
    protected static $fields = array(
        'survey_response_id',
        'survey_id',
        'time_taken',
    );
}

?>
