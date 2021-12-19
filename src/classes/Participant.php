<?php

namespace YaleREDCap\REDCapPRO;

class Participant
{

    function __construct(REDCapPRO $module, $params)
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

    /**
     * Updates the participant's email address
     * 
     * It then sends a confirmation email to the new address, cc'ing the old
     * 
     * @param string $new_email - email address that 
     * 
     * @return bool|NULL
     */
    public function changeEmailAddress(string $new_email)
    {
        $current_email = $this->email;
        $SQL = "UPDATE redcap_external_modules_log_parameters SET value = ? WHERE log_id = ? AND name = 'email'";
        try {
            $result = $this->module->query($SQL, [$new_email, $this->rcpro_participant_id]);
            if ($result) {

                // Get current project (or "system" if initiated in the control center)
                $current_pid = $this->module->getProjectId() ?? "system";

                // Get all projects to which participant is currently enrolled
                $project_ids = $this->getEnrolledProjects();
                foreach ($project_ids as $project_id) {
                    $this->module->logEvent("Changed Email Address", [
                        "rcpro_participant_id" => $this->rcpro_participant_id,
                        "rcpro_username"       => $this->rcpro_username,
                        "old_email"            => $current_email,
                        "new_email"            => $new_email,
                        "redcap_user"          => USERID,
                        "project_id"           => $project_id,
                        "initiating_project_id" => $current_pid
                    ]);
                }

                return $this->module->sendEmailUpdateEmail($this->rcpro_username, $new_email, $current_email);
            } else {
                throw new REDCapProException(["rcpro_participant_id" => $this->rcpro_participant_id]);
            }
        } catch (\Exception $e) {
            $this->module->logError("Error changing email address", $e);
        }
    }

    /**
     * Gets participant's current name
     * 
     * @return array fname and lname
     */
    function getName(): array
    {
        $SQL = "SELECT fname, lname WHERE log_id = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result = $this->module->selectLogs($SQL, [$this->rcpro_participant_id]);
            return $result->fetch_assoc();
        } catch (\Exception $e) {
            $this->module->logError("Error fetching participant name", $e);
        }
    }

    public function changeName($fname, $lname)
    {
        $SQL1 = "UPDATE redcap_external_modules_log_parameters SET value = ? WHERE log_id = ? AND name = 'fname'";
        $SQL2 = "UPDATE redcap_external_modules_log_parameters SET value = ? WHERE log_id = ? AND name = 'lname'";
        $current_name = $this->getName();
        try {
            $result1 = $this->module->query($SQL1, [$fname, $this->rcpro_participant_id]);
            if (!$result1) {
                throw new REDCapProException(["rcpro_participant_id" => $this->rcpro_participant_id]);
            }

            $result2 = $this->module->query($SQL2, [$lname, $this->rcpro_participant_id]);
            if (!$result2) {
                throw new REDCapProException(["rcpro_participant_id" => $this->rcpro_participant_id]);
            }

            // Get current project (or "system" if initiated in the control center)
            $current_pid = $this->module->getProjectId() ?? "system";

            // Get all projects to which participant is currently enrolled
            $project_ids = $this->getEnrolledProjects();
            foreach ($project_ids as $project_id) {
                $this->module->logEvent("Updated Participant Name", [
                    "rcpro_participant_id"  => $this->rcpro_participant_id,
                    "rcpro_username"        => $this->rcpro_username,
                    "old_name"              => $current_name["fname"] . " " . $current_name["lname"],
                    "new_name"              => $fname . " " . $lname,
                    "redcap_user"           => USERID,
                    "project_id"            => $project_id,
                    "initiating_project_id" => $current_pid
                ]);
            }
            return $result1 && $result2;
        } catch (\Exception $e) {
            $this->module->logError("Error updating participant's name", $e);
        }
    }
}
