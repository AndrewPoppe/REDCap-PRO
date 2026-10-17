<?php

namespace YaleREDCap\REDCapPRO;

/**
 * The @RCPRO-SURVEY-USERNAME action tag fills a text field on a survey with the
 * username of the participant who is logged in to REDCapPRO
 */
class SurveyUsernameTag
{
    const TAG = "@RCPRO-SURVEY-USERNAME";

    public REDCapPRO $module;
    public $project_id;

    function __construct(REDCapPRO $module, $project_id)
    {
        $this->module     = $module;
        $this->project_id = (int) $project_id;
    }

    /**
     * Gets the fields that use the action tag
     *
     * @param array $instruments Only look in these instruments (default: all)
     *
     * @return string[] field names
     */
    function getFields(array $instruments = [])
    {
        $json            = \REDCap::getDataDictionary($this->project_id, "json", false, null, $instruments);
        $dd              = json_decode($json, true) ?? [];
        $record_id_field = $this->module->framework->getRecordIdField($this->project_id);
        $fields          = [];
        foreach ( $dd as $field ) {
            if (
                strpos($field["field_annotation"], self::TAG) !== FALSE
                && $field["field_type"] === "text"
                && $field["text_validation_type_or_show_slider_number"] === ""
                && $field["field_name"] !== $record_id_field
            ) {
                $fields[] = $field["field_name"];
            }
        }
        return $fields;
    }

    /**
     * Fills the tagged fields on a survey page with the participant's username
     * and makes them read-only
     *
     * @param string $instrument The survey's instrument
     * @param string|null $username REDCapPRO username of the logged-in participant
     *
     * @return void
     */
    function populateSurveyFields(string $instrument, ?string $username)
    {
        if ( empty($username) ) {
            return;
        }
        $fields = $this->getFields([ $instrument ]);
        if ( empty($fields) ) {
            return;
        }
        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        ?>
        <script>
            $(function () {
                const username = <?= json_encode($username, $flags) ?>;
                const fields = <?= json_encode($fields, $flags) ?>;
                // Filling in these fields shouldn't count as the participant changing the form
                const formValuesChanged = window.dataEntryFormValuesChanged;
                fields.forEach(function (field) {
                    const input = $('input[name="' + field + '"]');
                    input.prop('readonly', true).css('background-color', '#eee');
                    if (input.val() !== username) {
                        // Update any piping, calculations, and branching logic
                        input.val(username).trigger('change').trigger('blur');
                    }
                });
                if (typeof formValuesChanged !== 'undefined') {
                    window.dataEntryFormValuesChanged = formValuesChanged;
                }
            });
        </script>
        <?php
    }

    /**
     * Makes sure that submitted values of tagged fields are the username of the
     * logged-in participant, whatever the browser actually sent
     *
     * Call this on a survey page before REDCap processes the submission, while
     * REDCap's survey session is still the active session
     *
     * @return void
     */
    function enforceSubmittedValues()
    {
        $fields = array_intersect($this->getFields(), array_keys($_POST));
        if ( empty($fields) ) {
            return;
        }
        $username = $this->getSurveyParticipantUsername();
        if ( empty($username) ) {
            return;
        }
        foreach ( $fields as $field ) {
            $_POST[$field] = $username;
        }
    }

    /**
     * Gets the username that the module wrote to REDCap's survey session when the
     * participant logged in or loaded a survey page (see Auth::set_survey_username)
     *
     * @return string|null username, if it belongs to a participant enrolled in this project
     */
    function getSurveyParticipantUsername()
    {
        if ( session_status() !== PHP_SESSION_ACTIVE || !Auth::is_redcap_survey_session_name(session_name()) ) {
            return null;
        }
        $username = $_SESSION["username"] ?? null;
        if ( empty($username) ) {
            return null;
        }

        $participantHelper    = new ParticipantHelper($this->module);
        $rcpro_participant_id = $participantHelper->getParticipantIdFromUsername($username);
        $projectHelper        = new ProjectHelper($this->module);
        $rcpro_project_id     = $projectHelper->getProjectIdFromPID($this->project_id);
        if (
            empty($rcpro_participant_id)
            || empty($rcpro_project_id)
            || !$participantHelper->enrolledInProject((int) $rcpro_participant_id, (int) $rcpro_project_id)
        ) {
            return null;
        }
        return $username;
    }
}
