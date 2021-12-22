<?php

namespace YaleREDCap\REDCapPRO;

class Participant
{

    private $module;
    public $rcpro_participant_id;
    public $rcpro_username;
    public $email;
    public $exists;

    function __construct(REDCapPRO $module, array $params)
    {
        $this->module = $module;
        $this->exists = false;

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
        }

        if (isset($this->rcpro_participant_id) && isset($this->rcpro_username)) {
            $this->exists = true;
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
        $Emailer = new Emailer($this->module);
        $SQL = "UPDATE redcap_external_modules_log_parameters SET value = ? WHERE log_id = ? AND name = 'email'";
        try {
            $result = $this->module->query($SQL, [$new_email, $this->rcpro_participant_id]);
            if ($result) {

                // Get current project (or "system" if initiated in the control center)
                $current_pid = $this->module->getProjectId() ?? "system";

                // Get all projects to which participant is currently enrolled
                $projects = $this->getEnrolledProjects();
                foreach ($projects as $project) {
                    $this->module->logEvent("Changed Email Address", [
                        "rcpro_participant_id" => $this->rcpro_participant_id,
                        "rcpro_username"       => $this->rcpro_username,
                        "old_email"            => $current_email,
                        "new_email"            => $new_email,
                        "redcap_user"          => constant("USERID"),
                        "project_id"           => $project->redcap_pid,
                        "initiating_project_id" => $current_pid
                    ]);
                }

                return $Emailer->sendEmailUpdateEmail($this->rcpro_username, $new_email, $current_email);
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
            $projects = $this->getEnrolledProjects();
            foreach ($projects as $project) {
                $this->module->logEvent("Updated Participant Name", [
                    "rcpro_participant_id"  => $this->rcpro_participant_id,
                    "rcpro_username"        => $this->rcpro_username,
                    "old_name"              => $current_name["fname"] . " " . $current_name["lname"],
                    "new_name"              => $fname . " " . $lname,
                    "redcap_user"           => constant("USERID"),
                    "project_id"            => $project->redcap_pid,
                    "initiating_project_id" => $current_pid
                ]);
            }
            return $result1 && $result2;
        } catch (\Exception $e) {
            $this->module->logError("Error updating participant's name", $e);
        }
    }

    /**
     * Create and store token for resetting participant's password
     * 
     * @param int $hours_valid - how long should the token be valid for
     * 
     * @return string token
     */
    public function createResetToken(int $hours_valid = 1)
    {
        $token = bin2hex(random_bytes(32));
        $token_ts = time() + ($hours_valid * 60 * 60);
        $SQL1 = "UPDATE redcap_external_modules_log_parameters SET value = ? WHERE log_id = ? AND name = 'token'";
        $SQL2 = "UPDATE redcap_external_modules_log_parameters SET value = ? WHERE log_id = ? AND name = 'token_ts'";
        $SQL3 = "UPDATE redcap_external_modules_log_parameters SET value = 1 WHERE log_id = ? AND name = 'token_valid'";
        try {
            $result1 = $this->module->query($SQL1, [$token, $this->rcpro_participant_id]);
            $result2 = $this->module->query($SQL2, [$token_ts, $this->rcpro_participant_id]);
            $result3 = $this->module->query($SQL3, [$this->rcpro_participant_id]);
            if (!$result1 || !$result2 || !$result3) {
                throw new REDCapProException(["rcpro_participant_id" => $this->rcpro_participant_id]);
            }
            return $token;
        } catch (\Exception $e) {
            $this->module->logError("Error creating reset token", $e);
        }
    }

    /**
     * Sets active status of participant to 0
     * 
     * @return [type]
     */
    public function deactivate()
    {
        return $this->setActiveStatus(0);
    }


    /**
     * Sets active status of participant to 1
     * 
     * @return [type]
     */
    public function reactivate()
    {
        return $this->setActiveStatus(1);
    }

    /**
     * Sets active status of participant
     * 
     * @param int $value
     * 
     * @return [type]
     */
    private function setActiveStatus(int $value)
    {
        if ($this->module->countLogsValidated("log_id = ? AND active is not null", [$this->rcpro_participant_id]) > 0) {
            $SQL = "UPDATE redcap_external_modules_log_parameters SET value = ? WHERE log_id = ? AND name = 'active'";
        } else {
            $SQL = "INSERT INTO redcap_external_modules_log_parameters (value, name, log_id) VALUES (?, 'active', ?)";
        }
        try {
            $res = $this->module->query($SQL, [$value, $this->rcpro_participant_id]);
            if ($res) {
                $this->module->logEvent("Set participant active status", [
                    "rcpro_participant_id" => $this->rcpro_participant_id,
                    "rcpro_username" => $this->rcpro_username,
                    "active" => $value,
                    "redcap_user" => constant("USERID")
                ]);
            } else {
                $this->module->logEvent("Failed to set participant active status", [
                    "rcpro_participant_id" => $this->rcpro_participant_id,
                    "rcpro_username" => $this->rcpro_username,
                    "active" => $value,
                    "redcap_user" => constant("USERID")
                ]);
            }
            return $res;
        } catch (\Exception $e) {
            $this->module->logError("Error setting participant active status", $e);
        }
    }


    /**
     * Whether the participant is active or has been deactivated
     * 
     * @return bool True is active, false is deactivated.
     */
    function isActive(): bool
    {
        $SQL = "SELECT active WHERE log_id = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $res = $this->module->selectLogs($SQL, [$this->rcpro_participant_id]);
            $status_arr = $res->fetch_assoc();
            return !isset($status_arr["active"]) || $status_arr["active"] == 1;
        } catch (\Exception $e) {
            $this->module->logError("Error getting active status", $e);
        }
    }

    function isEnrolled(Project $project)
    {
        $link = new Link($this->module, $project, $this);
        return $link->exists() && $link->isActive();
    }

    /**
     * Sets the password reset token as invalid/expired
     * 
     * @return bool|null success or failure
     */
    public function expirePasswordResetToken()
    {
        $SQL = "UPDATE redcap_external_modules_log_parameters SET value = 0 WHERE log_id = ? AND name = 'token_valid'";
        try {
            return $this->module->query($SQL, [$this->rcpro_participant_id]);
        } catch (\Exception $e) {
            $this->module->logError("Error expiring password reset token.", $e);
        }
    }

    /**
     * Fetch array of REDCap PIDs for all projects this participant is enrolled
     * in.
     * 
     * @return Project[] a redcap_pid for each enrolled project
     */
    public function getEnrolledProjects(): array
    {
        $SQL = "SELECT project_id WHERE message = 'LINK' AND rcpro_participant_id = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result = $this->module->selectLogs($SQL, [$this->rcpro_participant_id]);
            $projects = array();
            while ($row = $result->fetch_assoc()) {
                $redcap_pid = $row["project_id"];
                $project = new Project($this->module, [
                    "redcap_pid" => $redcap_pid
                ]);
                $projects[$redcap_pid] = $project;
            }
            if (empty($projects)) {
                array_push($projects, NULL);
            }
            return $projects;
        } catch (\Exception $e) {
            $this->module->logError("Error fetching enrolled projects", $e);
        }
    }

    /**
     * Gets various info about a participant
     * 
     * @return array info about participant:
     * User_ID, Username, Registered At, Registered By 
     */
    public function getInfo(): array
    {
        $SQL = "SELECT log_id AS 'User_ID', rcpro_username AS Username, timestamp AS 'Registered At', redcap_user AS 'Registered By' WHERE log_id = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result_obj = $this->module->selectLogs($SQL, [$this->rcpro_participant_id]);
            return $result_obj->fetch_assoc();
        } catch (\Exception $e) {
            $this->module->logError("Error getting participant info", $e);
        }
    }

    /**
     * Stores hashed password for the given participant
     * 
     * @param string $hash - hashed password
     * 
     * @return bool|NULL success/failure/null
     */
    public function storeHash(string $hash): bool
    {
        try {
            $SQL = "UPDATE redcap_external_modules_log_parameters SET value = ? WHERE log_id = ? AND name = 'pw'";
            $res = $this->module->query($SQL, [$hash, $this->rcpro_participant_id]);
            $this->module->logEvent("Password Hash Stored", [
                "rcpro_participant_id" => $this->rcpro_participant_id,
                "rcpro_username"       => $this->rcpro_username
            ]);
            return $res;
        } catch (\Exception $e) {
            $this->module->logError("Error storing password hash", $e);
        }
    }

    public function isPasswordSet(): bool
    {
        try {
            $SQL = "SELECT pw WHERE log_id = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
            $result = $this->module->selectLogs($SQL, [$this->rcpro_participant_id]);
            $pw = $result->fetch_assoc()["pw"];
            return isset($pw) && $pw !== "";
        } catch (\Exception $e) {
            $this->module->logError("Error checking if password is set", $e);
        }
    }

    /**
     * Get hashed password for participant.
     * 
     * @return string hashed password
     */
    public function getHash(): string
    {
        try {
            $SQL = "SELECT pw WHERE message = 'PARTICIPANT' AND log_id = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
            $res = $this->module->selectLogs($SQL, [$this->rcpro_participant_id]);
            return $res->fetch_assoc()['pw'];
        } catch (\Exception $e) {
            $this->module->logError("Error fetching password hash", $e);
        }
    }
}
