<?php

namespace YaleREDCap\REDCapPRO;

class Participant
{

    function __construct($module, $params)
    {
        $this->module = $module;

        if (isset($params["rcpro_participant_id"])) {
            $this->rcpro_participant_id = $params["rcpro_participant_id"];
            $this->rcpro_username       = $this->getUsername();
            $this->email                = $this->getEmail();
        } else if (isset($params["rcpro_username"])) {
            $this->rcpro_username       = $params["rcpro_username"];
            $this->rcpro_participant_id = $this->getParticipantId();
            $this->email                = $this->getEmail();
        } else if (isset($params["email"])) {
            $this->email                = $params["email"];
            $this->rcpro_participant_id = $this->getParticipantId();
            $this->rcpro_username       = $this->getUsername();
        } else {
            throw new REDCapProException();
        }
    }

    /**
     * Fetch email corresponding with participant
     * 
     * @return string|NULL email address
     */
    public function getEmail()
    {
        $SQL = "SELECT email WHERE message = 'PARTICIPANT' AND log_id = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result = $this->module->selectLogs($SQL, [$this->rcpro_participant_id]);
            return $result->fetch_assoc()["email"];
        } catch (\Exception $e) {
            $this->module->logError("Error fetching participant email address", $e);
        }
    }

    /**
     * Fetch participant id - either email or username must be known
     * 
     * @return int|NULL RCPRO participant id
     */
    public function getParticipantId()
    {
        if (isset($this->rcpro_username)) {
            $column = "rcpro_username";
            $value = $this->rcpro_username;
        } else if (isset($this->email)) {
            $column = "email";
            $value = $this->email;
        } else {
            throw new REDCapProException();
        }
        $SQL = "SELECT log_id WHERE message = 'PARTICIPANT' AND ${column} = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result = $this->module->selectLogs($SQL, [$value]);
            return $result->fetch_assoc()["log_id"];
        } catch (\Exception $e) {
            $this->module->logError("Error fetching participant id", $e);
        }
    }

    /**
     * Fetch username 
     * 
     * @return string|NULL username
     */
    public function getUserName()
    {
        try {

            if (!isset($this->rcpro_participant_id)) {
                $this->rcpro_participant_id = $this->getParticipantId();
            }

            $SQL = "SELECT rcpro_username WHERE message = 'PARTICIPANT' AND log_id = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
            $result = $this->module->selectLogs($SQL, [$this->rcpro_participant_id]);
            return $result->fetch_assoc()["rcpro_username"];
        } catch (\Exception $e) {
            $this->module->logError("Error fetching username", $e);
        }
    }
}
