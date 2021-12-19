<?php

namespace YaleREDCap\REDCapPRO;

require_once("src/classes/ProjectHelper.php");

/**
 * Holds methods related to REDCapPRO Participants
 * 
 * @package YaleREDCap\REDCapPRO
 */
class ParticipantHelper
{
    public static $module;
    /**
     * Constructor
     * 
     * @param REDCapPRO $module
     */
    function __construct(REDCapPRO $module)
    {
        self::$module = $module;
    }





    /**
     * Determine whether email address already exists in database
     * 
     * @param string $email
     * 
     * @return boolean True if email already exists, False if not
     */
    public function checkEmailExists(string $email)
    {
        $SQL = "message = 'PARTICIPANT' AND email = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result = self::$module->countLogsValidated($SQL, [$email]);
            return $result > 0;
        } catch (\Exception $e) {
            self::$module->logError("Error checking if email exists", $e);
        }
    }

    /**
     * Returns whether a participant with the given ID exists in the system
     * This is regardless of whether the participant is active or has set a
     * password.
     * 
     * @param mixed $rcpro_participant_id
     * 
     * @return boolean true = exists
     */
    public function checkParticipantExists($rcpro_participant_id)
    {
        $SQL = "log_id = ? AND message = 'PARTICIPANT' AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $res = self::$module->countLogsValidated($SQL, [$rcpro_participant_id]);
            return $res > 0;
        } catch (\Exception $e) {
            self::$module->logError("Error checking whether participant exists", $e);
        }
    }

    /**
     * Adds participant entry into log table.
     * 
     * In so doing, it creates a unique username.
     * 
     * @param string $email Email Address
     * @param string $fname First Name
     * @param string $lname Last Name
     * 
     * @return string username of newly created participant
     */
    public function createParticipant(string $email, string $fname, string $lname)
    {
        $username     = $this->createUsername();
        $email_clean  = \REDCap::escapeHtml($email);
        $fname_clean  = \REDCap::escapeHtml($fname);
        $lname_clean  = \REDCap::escapeHtml($lname);
        $counter      = 0;
        $counterLimit = 90000000;
        while ($this->usernameIsTaken($username) && $counter < $counterLimit) {
            $username = $this->createUsername();
            $counter++;
        }
        if ($counter >= $counterLimit) {
            echo "Please contact your REDCap administrator.";
            return NULL;
        }
        try {
            $id = self::$module->logEvent("PARTICIPANT", [
                "rcpro_username"   => $username,
                "email"            => $email_clean,
                "fname"            => $fname_clean,
                "lname"            => $lname_clean,
                "pw"               => "",
                "last_modified_ts" => time(),
                "lockout_ts"       => time(),
                "failed_attempts"  => 0,
                "token"            => "",
                "token_ts"         => time(),
                "token_valid"      => 0,
                "redcap_user"      => USERID,
                "active"           => 1
            ]);
            if (!$id) {
                throw new REDCapProException(["rcpro_username" => $username]);
            }
            self::$module->logEvent("Participant Created", [
                "rcpro_user_id"  => $id,
                "rcpro_username" => $username,
                "redcap_user"    => USERID
            ]);
            return $username;
        } catch (\Exception $e) {
            self::$module->logError("Participant Creation Failed", $e);
        }
    }

    /**
     * Create and store token for resetting participant's password
     * 
     * @param mixed $rcpro_participant_id
     * @param int $hours_valid - how long should the token be valid for
     * 
     * @return string token
     */
    public function createResetToken($rcpro_participant_id, int $hours_valid = 1)
    {
        $token = bin2hex(random_bytes(32));
        $token_ts = time() + ($hours_valid * 60 * 60);
        $SQL1 = "UPDATE redcap_external_modules_log_parameters SET value = ? WHERE log_id = ? AND name = 'token'";
        $SQL2 = "UPDATE redcap_external_modules_log_parameters SET value = ? WHERE log_id = ? AND name = 'token_ts'";
        $SQL3 = "UPDATE redcap_external_modules_log_parameters SET value = 1 WHERE log_id = ? AND name = 'token_valid'";
        try {
            $result1 = self::$module->query($SQL1, [$token, $rcpro_participant_id]);
            $result2 = self::$module->query($SQL2, [$token_ts, $rcpro_participant_id]);
            $result3 = self::$module->query($SQL3, [$rcpro_participant_id]);
            if (!$result1 || !$result2 || !$result3) {
                throw new REDCapProException(["rcpro_participant_id" => $rcpro_participant_id]);
            }
            return $token;
        } catch (\Exception $e) {
            self::$module->logError("Error creating reset token", $e);
        }
    }

    /**
     * Creates a "random" username
     * It creates an 8-digit username (between 10000000 and 99999999)
     * Of the form: XXX-XX-XXX
     * 
     * @return string username
     */
    private function createUsername()
    {
        return sprintf("%03d", random_int(100, 999)) . '-' .
            sprintf("%02d", random_int(0, 99)) . '-' .
            sprintf("%03d", random_int(0, 999));
    }

    /**
     * Sets active status of participant to 0
     * 
     * @param mixed $rcpro_participant_id
     * 
     * @return [type]
     */
    public function deactivateParticipant($rcpro_participant_id)
    {
        return $this->setActiveStatus($rcpro_participant_id, 0);
    }

    /**
     * Sets active status of participant to 1
     * 
     * @param mixed $rcpro_participant_id
     * 
     * @return [type]
     */
    public function reactivateParticipant($rcpro_participant_id)
    {
        return $this->setActiveStatus($rcpro_participant_id, 1);
    }

    /**
     * Determines whether the current participant is enrolled in the project
     * 
     * @param int $rcpro_participant_id
     * @param int $rcpro_project_id RCPRO Project ID
     * 
     * @return boolean TRUE if the participant is enrolled
     */
    public function enrolledInProject(int $rcpro_participant_id, int $rcpro_project_id)
    {
        $SQL = "message = 'LINK' AND rcpro_project_id = ? AND rcpro_participant_id = ? AND active = 1 AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result = self::$module->countLogsValidated($SQL, [$rcpro_project_id, $rcpro_participant_id]);
            return $result > 0;
        } catch (\Exception $e) {
            self::$module->logError("Error checking that participant is enrolled", $e);
        }
    }

    /**
     * Sets the password reset token as invalid/expired
     * 
     * @param mixed $rcpro_participant_id id of participant
     * 
     * @return bool|null success or failure
     */
    public function expirePasswordResetToken($rcpro_participant_id)
    {
        $SQL = "UPDATE redcap_external_modules_log_parameters SET value = 0 WHERE log_id = ? AND name = 'token_valid'";
        try {
            return self::$module->query($SQL, [$rcpro_participant_id]);
        } catch (\Exception $e) {
            self::$module->logError("Error expiring password reset token.", $e);
        }
    }

    /**
     * Grabs all registered participants
     * 
     * @return array|NULL of user arrays or null if error
     */
    public function getAllParticipants()
    {
        $SQL = "SELECT log_id, rcpro_username, email, fname, lname, lockout_ts, pw, active WHERE message = 'PARTICIPANT' AND rcpro_username IS NOT NULL AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result = self::$module->selectLogs($SQL, []);
            $participants  = array();

            // grab participant details
            while ($row = $result->fetch_assoc()) {
                $row["pw_set"] = (!isset($row["pw"]) || $row["pw"] === "") ? "False" : "True";
                unset($row["pw"]);
                $participants[$row["log_id"]] = $row;
            }
            return $participants;
        } catch (\Exception $e) {
            self::$module->logError("Error fetching participants", $e);
        }
    }




    /**
     * Fetch array of REDCap PIDs for all projects this participant is enrolled
     * in.
     * 
     * @param int $rcpro_participant_id
     * 
     * @return array
     */
    public function getEnrolledProjects(int $rcpro_participant_id)
    {
        $SQL = "SELECT project_id WHERE message = 'LINK' AND rcpro_participant_id = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result = self::$module->selectLogs($SQL, [$rcpro_participant_id]);
            $project_ids = array();
            while ($row = $result->fetch_assoc()) {
                array_push($project_ids, $row["project_id"]);
            }
            if (count($project_ids) === 0) {
                array_push($project_ids, NULL);
            }
            return $project_ids;
        } catch (\Exception $e) {
            self::$module->logError("Error fetching enrolled projects", $e);
        }
    }

    /**
     * Returns an array with participant information given a username
     * 
     * @param string $username
     * 
     * @return array|NULL user information
     */
    public function getParticipant(string $username)
    {
        if ($username === NULL) {
            return NULL;
        }
        $SQL = "SELECT log_id, rcpro_username, email, fname, lname, active WHERE message = 'PARTICIPANT' AND rcpro_username = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result = self::$module->selectLogs($SQL, [$username]);
            return $result->fetch_assoc();
        } catch (\Exception $e) {
            self::$module->logError("Error fetching participant information", $e);
        }
    }

    /**
     * Returns an array with participant information given an email address
     * 
     * @param string $email
     * 
     * @return array|NULL user information
     */
    public function getParticipantFromEmail(string $email)
    {
        if ($email === NULL) {
            return NULL;
        }
        $SQL = "SELECT log_id, rcpro_username, email, fname, lname, active WHERE message = 'PARTICIPANT' AND email = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result = self::$module->selectLogs($SQL, [$email]);
            return $result->fetch_assoc();
        } catch (\Exception $e) {
            self::$module->logError("Error fetching participant information", $e);
        }
    }



    /**
     * Fetch participant id corresponding with given username
     * 
     * @param string $username
     * 
     * @return int|NULL RCPRO participant id
     */
    public function getParticipantIdFromUsername(string $username)
    {
        $SQL = "SELECT log_id WHERE message = 'PARTICIPANT' AND rcpro_username = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result = self::$module->selectLogs($SQL, [$username]);
            return $result->fetch_assoc()["log_id"];
        } catch (\Exception $e) {
            self::$module->logError("Error fetching id from username", $e);
        }
    }

    /**
     * Gets various info about a participant from participant ID
     * 
     * @param int $rcpro_participant_id RCPRO participant ID
     * @return array info about participant:
     * User_ID, Username, Registered At, Registered By 
     */
    public function getParticipantInfo(int $rcpro_participant_id)
    {
        $SQL = "SELECT log_id AS 'User_ID', rcpro_username AS Username, timestamp AS 'Registered At', redcap_user AS 'Registered By' WHERE log_id = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result_obj = self::$module->selectLogs($SQL, [$rcpro_participant_id]);
            return $result_obj->fetch_assoc();
        } catch (\Exception $e) {
            self::$module->logError("Error getting participant info", $e);
        }
    }

    /**
     * Fetches all the projects that the provided participant is enrolled in
     * 
     * This includes active and inactive projects
     * 
     * @param int $rcpro_participant_id
     * 
     * @return array array of arrays, each corresponding with a project
     */
    public function getParticipantProjects(int $rcpro_participant_id)
    {
        $SQL1 = "SELECT rcpro_project_id, active WHERE rcpro_participant_id = ? AND message = 'LINK' AND (project_id IS NULL OR project_id IS NOT NULL)";
        $SQL2 = "SELECT pid WHERE log_id = ? AND message = 'PROJECT' AND (project_id IS NULL OR project_id IS NOT NULL)";
        $projects = array();
        try {
            $result1 = self::$module->selectLogs($SQL1, [$rcpro_participant_id]);
            if (!$result1) {
                throw new REDCapProException(["rcpro_participant_id" => $rcpro_participant_id]);
            }
            while ($row = $result1->fetch_assoc()) {
                $rcpro_project_id = $row["rcpro_project_id"];
                $result2 = self::$module->selectLogs($SQL2, [$rcpro_project_id]);
                $redcap_pid = $result2->fetch_assoc()["pid"];
                array_push($projects, [
                    "rcpro_project_id" => $rcpro_project_id,
                    "active"           => $row["active"],
                    "redcap_pid"       => $redcap_pid
                ]);
            }
            return $projects;
        } catch (\Exception $e) {
            self::$module->logError("Error fetching participant's projects", $e);
        }
    }

    /**
     * get array of active enrolled participants given a rcpro project id
     * 
     * @param string $rcpro_project_id Project ID (not REDCap PID!)
     * @param int|NULL $dag Data Access Group to filter search by
     * 
     * @return array|NULL participants enrolled in given study
     */
    public function getProjectParticipants(string $rcpro_project_id, ?int $dag = NULL)
    {
        $SQL = "SELECT rcpro_participant_id WHERE message = 'LINK' AND rcpro_project_id = ? AND active = 1 AND (project_id IS NULL OR project_id IS NOT NULL)";
        $PARAMS = [$rcpro_project_id];
        if (isset($dag)) {
            $SQL .= " AND project_dag IS NOT NULL AND project_dag = ?";
            array_push($PARAMS, strval($dag));
        }
        try {
            $result = self::$module->selectLogs($SQL, $PARAMS);
            $participants  = array();

            while ($row = $result->fetch_assoc()) {
                $participantSQL = "SELECT log_id, rcpro_username, email, fname, lname, lockout_ts, pw WHERE message = 'PARTICIPANT' AND log_id = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
                $participantResult = self::$module->selectLogs($participantSQL, [$row["rcpro_participant_id"]]);
                $participant = $participantResult->fetch_assoc();
                $participant["pw_set"] = (!isset($participant["pw"]) || $participant["pw"] === "") ? "False" : "True";
                unset($participant["pw"]);
                $participants[$row["rcpro_participant_id"]] = $participant;
            }
            return $participants;
        } catch (\Exception $e) {
            self::$module->logError("Error fetching project participants", $e);
        }
    }



    /**
     * Returns whether participant is active or not
     * 
     * @param mixed $rcpro_participant_id
     * 
     * @return bool true is active, false is deactivated
     */
    public function isParticipantActive($rcpro_participant_id)
    {
        $SQL = "SELECT active WHERE log_id = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $res = self::$module->selectLogs($SQL, [$rcpro_participant_id]);
            $status_arr = $res->fetch_assoc();
            return !isset($status_arr["active"]) || $status_arr["active"] == 1;
        } catch (\Exception $e) {
            self::$module->logError("Error getting active status", $e);
        }
    }

    /**
     * Use provided search string to find registered participants
     * 
     * @param string $search_term - text to search for
     * 
     * @return 
     */
    public function searchParticipants(string $search_term)
    {
        self::$module->logEvent("Searched on Enroll Tab", [
            "search"      => \REDCap::escapeHtml($search_term),
            "redcap_user" => USERID
        ]);
        $SQL = "SELECT fname, lname, email, log_id, rcpro_username, active 
                WHERE message = 'PARTICIPANT' 
                AND (project_id IS NULL OR project_id IS NOT NULL) 
                AND (email = ?)";
        try {
            return self::$module->selectLogs($SQL, [$search_term]);
        } catch (\Exception $e) {
            self::$module->logError("Error performing livesearch", $e);
        }
    }

    /**
     * Sets active status of participant
     * 
     * @param mixed $rcpro_participant_id
     * @param int $value
     * 
     * @return [type]
     */
    private function setActiveStatus($rcpro_participant_id, int $value)
    {
        if (self::$module->countLogsValidated("log_id = ? AND active is not null", [$rcpro_participant_id]) > 0) {
            $SQL = "UPDATE redcap_external_modules_log_parameters SET value = ? WHERE log_id = ? AND name = 'active'";
        } else {
            $SQL = "INSERT INTO redcap_external_modules_log_parameters (value, name, log_id) VALUES (?, 'active', ?)";
        }
        try {
            $res = self::$module->query($SQL, [$value, $rcpro_participant_id]);
            if ($res) {
                self::$module->logEvent("Set participant active status", [
                    "rcpro_participant_id" => $rcpro_participant_id,
                    "rcpro_username" => $this->getUserName($rcpro_participant_id),
                    "active" => $value,
                    "redcap_user" => USERID
                ]);
            } else {
                self::$module->logEvent("Failed to set participant active status", [
                    "rcpro_participant_id" => $rcpro_participant_id,
                    "rcpro_username" => $this->getUserName($rcpro_participant_id),
                    "active" => $value,
                    "redcap_user" => USERID
                ]);
            }
            return $res;
        } catch (\Exception $e) {
            self::$module->logError("Error setting participant active status", $e);
        }
    }

    /**
     * Stores hashed password for the given participant id
     * 
     * @param string $hash - hashed password
     * @param int $rcpro_participant_id - id key for participant
     * 
     * @return bool|NULL success/failure/null
     */
    public function storeHash(string $hash, int $rcpro_participant_id)
    {
        try {
            $SQL = "UPDATE redcap_external_modules_log_parameters SET value = ? WHERE log_id = ? AND name = 'pw';";
            $res = self::$module->query($SQL, [$hash, $rcpro_participant_id]);
            self::$module->logEvent("Password Hash Stored", [
                "rcpro_participant_id" => $rcpro_participant_id,
                "rcpro_username"       => $this->getUserName($rcpro_participant_id)
            ]);
            return $res;
        } catch (\Exception $e) {
            self::$module->logError("Error storing password hash", $e);
        }
    }

    /**
     * Checks whether username already exists in database.
     * 
     * @param string $username
     * 
     * @return boolean|NULL True if taken, False if free, NULL if error 
     */
    public function usernameIsTaken(string $username)
    {
        $SQL = "message = 'PARTICIPANT' AND rcpro_username = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result = self::$module->countLogsValidated($SQL, [$username]);
            return $result > 0;
        } catch (\Exception $e) {
            self::$module->logError("Error checking if username is taken", $e);
        }
    }

    /**
     * Verify that the supplied password reset token is valid.
     * 
     * @param string $token
     * 
     * @return array with participant id and username
     */
    public function verifyPasswordResetToken(string $token)
    {
        $SQL = "SELECT log_id, rcpro_username WHERE message = 'PARTICIPANT' AND token = ? AND token_ts > ? AND token_valid = 1 AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result = self::$module->selectLogs($SQL, [$token, time()]);
            if ($result->num_rows > 0) {
                $result_array = $result->fetch_assoc();
                self::$module->logEvent("Password Token Verified", [
                    'rcpro_participant_id' => $result_array['log_id'],
                    'rcpro_username'       => $result_array['rcpro_username']
                ]);
                return $result_array;
            }
        } catch (\Exception $e) {
            self::$module->logError("Error verifying password reset token", $e);
        }
    }
}
