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
     * Updates the email address for the given participant
     * 
     * It then sends a confirmation email to the new address, cc'ing the old
     * 
     * @param int $rcpro_participant_id
     * @param string $new_email - email address that 
     * 
     * @return bool|NULL
     */
    public function changeEmailAddress(int $rcpro_participant_id, string $new_email)
    {
        $current_email = $this->getEmail($rcpro_participant_id);
        $SQL = "UPDATE redcap_external_modules_log_parameters SET value = ? WHERE log_id = ? AND name = 'email'";
        try {
            $result = self::$module->query($SQL, [$new_email, $rcpro_participant_id]);
            if ($result) {
                self::$module->log("Changed Email Address", [
                    "rcpro_participant_id" => $rcpro_participant_id,
                    "old_email"            => $current_email,
                    "new_email"            => $new_email,
                    "redcap_user"          => USERID
                ]);
                $username = $this->getUserName($rcpro_participant_id);
                return self::$module->sendEmailUpdateEmail($username, $new_email, $current_email);
            } else {
                throw new REDCapProException(["rcpro_participant_id" => $rcpro_participant_id]);
            }
        } catch (\Exception $e) {
            self::$module->logError("Error changing email address", $e);
        }
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
            $result = self::$module->countLogs($SQL, [$email]);
            return $result > 0;
        } catch (\Exception $e) {
            self::$module->logError("Error checking if email exists", $e);
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
            $id = self::$module->log("PARTICIPANT", [
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
                "redcap_user"      => USERID
            ]);
            if (!$id) {
                throw new REDCapProException(["rcpro_username" => $username]);
            }
            self::$module->log("Participant Created", [
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
            $result = self::$module->countLogs($SQL, [$rcpro_project_id, $rcpro_participant_id]);
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
        $SQL = "SELECT log_id, rcpro_username, email, fname, lname, lockout_ts, (CASE WHEN (pw IS NULL OR pw = '') THEN 'False' ELSE 'True' END) AS pw_set WHERE message = 'PARTICIPANT' AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result = self::$module->queryLogs($SQL, []);
            $participants  = array();

            // grab participant details
            while ($row = $result->fetch_assoc()) {
                $participants[$row["log_id"]] = $row;
            }
            return $participants;
        } catch (\Exception $e) {
            self::$module->logError("Error fetching participants", $e);
        }
    }

    /**
     * Fetch email corresponding with given participant id
     * 
     * @param int $rcpro_participant_id
     * 
     * @return string|NULL email address
     */
    public function getEmail(int $rcpro_participant_id)
    {
        $SQL = "SELECT email WHERE message = 'PARTICIPANT' AND log_id = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result = self::$module->queryLogs($SQL, [$rcpro_participant_id]);
            return $result->fetch_assoc()["email"];
        } catch (\Exception $e) {
            self::$module->logError("Error fetching email address", $e);
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
        $SQL = "SELECT log_id, rcpro_username, email, fname, lname WHERE message = 'PARTICIPANT' AND rcpro_username = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result = self::$module->queryLogs($SQL, [$username]);
            return $result->fetch_assoc();
        } catch (\Exception $e) {
            self::$module->logError("Error fetching participant information", $e);
        }
    }

    /**
     * Fetch participant id corresponding with given email
     * 
     * @param string $email
     * 
     * @return int|NULL RCPRO participant id
     */
    public function getParticipantIdFromEmail(string $email)
    {
        $SQL = "SELECT log_id WHERE message = 'PARTICIPANT' AND email = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result = self::$module->queryLogs($SQL, [$email]);
            return $result->fetch_assoc()["log_id"];
        } catch (\Exception $e) {
            self::$module->logError("Error fetching id from email", $e);
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
            $result = self::$module->queryLogs($SQL, [$username]);
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
            $result_obj = self::$module->queryLogs($SQL, [$rcpro_participant_id]);
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
            $result1 = self::$module->queryLogs($SQL1, [$rcpro_participant_id]);
            if (!$result1) {
                throw new REDCapProException(["rcpro_participant_id" => $rcpro_participant_id]);
            }
            while ($row = $result1->fetch_assoc()) {
                $rcpro_project_id = $row["rcpro_project_id"];
                $result2 = self::$module->queryLogs($SQL2, [$rcpro_project_id]);
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
            $result = self::$module->queryLogs($SQL, $PARAMS);
            $participants  = array();

            while ($row = $result->fetch_assoc()) {
                $participantSQL = "SELECT log_id, rcpro_username, email, fname, lname, lockout_ts, (CASE WHEN (pw IS NULL OR pw = '') THEN 'False' ELSE 'True' END) AS pw_set WHERE message = 'PARTICIPANT' AND log_id = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
                $participantResult = self::$module->queryLogs($participantSQL, [$row["rcpro_participant_id"]]);
                $participant = $participantResult->fetch_assoc();
                $participants[$row["rcpro_participant_id"]] = $participant;
            }
            return $participants;
        } catch (\Exception $e) {
            self::$module->logError("Error fetching project participants", $e);
        }
    }

    /**
     * Fetch username for given participant id
     * 
     * @param int $rcpro_participant_id - participant id
     * 
     * @return string|NULL username
     */
    public function getUserName(int $rcpro_participant_id)
    {
        $SQL = "SELECT rcpro_username WHERE message = 'PARTICIPANT' AND log_id = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result = self::$module->queryLogs($SQL, [$rcpro_participant_id]);
            return $result->fetch_assoc()["rcpro_username"];
        } catch (\Exception $e) {
            self::$module->logError("Error fetching username", $e);
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
        $SQL = "SELECT fname, lname, email, log_id, rcpro_username 
                WHERE message = 'PARTICIPANT' 
                AND (project_id IS NULL OR project_id IS NOT NULL) 
                AND (fname LIKE ? OR lname LIKE ? OR email LIKE ? OR rcpro_username LIKE ?)
                LIMIT 5";
        try {
            return self::$module->queryLogs($SQL, [$search_term, $search_term, $search_term, $search_term]);
        } catch (\Exception $e) {
            self::$module->logError("Error performing livesearch", $e);
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
            self::$module->log("Password Hash Stored", [
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
            $result = self::$module->countLogs($SQL, [$username]);
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
            $result = self::$module->queryLogs($SQL, [$token, time()]);
            if ($result->num_rows > 0) {
                $result_array = $result->fetch_assoc();
                self::$module->log("Password Token Verified", [
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
