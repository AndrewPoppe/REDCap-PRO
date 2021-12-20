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
    /**
     * Constructor
     * 
     * @param REDCapPRO $module
     */
    function __construct(REDCapPRO $module)
    {
        $this->$module = $module;
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
            $result = $this->module->countLogsValidated($SQL, [$email]);
            return $result > 0;
        } catch (\Exception $e) {
            $this->module->logError("Error checking if email exists", $e);
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
            $res = $this->module->countLogsValidated($SQL, [$rcpro_participant_id]);
            return $res > 0;
        } catch (\Exception $e) {
            $this->module->logError("Error checking whether participant exists", $e);
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
            $id = $this->module->logEvent("PARTICIPANT", [
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
            $this->module->logEvent("Participant Created", [
                "rcpro_user_id"  => $id,
                "rcpro_username" => $username,
                "redcap_user"    => USERID
            ]);
            return $username;
        } catch (\Exception $e) {
            $this->module->logError("Participant Creation Failed", $e);
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
     * Grabs all registered participants
     * 
     * @return Participant[] Array of Participants
     */
    public function getAllParticipants(): array
    {
        $SQL = "SELECT log_id WHERE message = 'PARTICIPANT' AND rcpro_username IS NOT NULL AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result = $this->module->selectLogs($SQL, []);
            $participants  = array();

            // grab participant details
            while ($row = $result->fetch_assoc()) {
                $participant = new Participant($this->module, ["rcpro_participant_id" => $row["log_id"]]);
                $participants[$row["log_id"]] = $participant;
            }
            return $participants;
        } catch (\Exception $e) {
            $this->module->logError("Error fetching participants", $e);
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
        $this->module->logEvent("Searched on Enroll Tab", [
            "search"      => \REDCap::escapeHtml($search_term),
            "redcap_user" => USERID
        ]);
        $SQL = "SELECT fname, lname, email, log_id, rcpro_username, active 
                WHERE message = 'PARTICIPANT' 
                AND (project_id IS NULL OR project_id IS NOT NULL) 
                AND (email = ?)";
        try {
            return $this->module->selectLogs($SQL, [$search_term]);
        } catch (\Exception $e) {
            $this->module->logError("Error performing livesearch", $e);
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
            $result = $this->module->countLogsValidated($SQL, [$username]);
            return $result > 0;
        } catch (\Exception $e) {
            $this->module->logError("Error checking if username is taken", $e);
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
            $result = $this->module->selectLogs($SQL, [$token, time()]);
            if ($result->num_rows > 0) {
                $result_array = $result->fetch_assoc();
                $this->module->logEvent("Password Token Verified", [
                    'rcpro_participant_id' => $result_array['log_id'],
                    'rcpro_username'       => $result_array['rcpro_username']
                ]);
                return $result_array;
            }
        } catch (\Exception $e) {
            $this->module->logError("Error verifying password reset token", $e);
        }
    }
}
