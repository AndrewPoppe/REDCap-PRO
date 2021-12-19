<?php

namespace YaleREDCap\REDCapPRO;

require_once("src/classes/ParticipantHelper.php");

/**
 * Holds methods related to REDCapPRO Project
 * 
 * @package YaleREDCap\REDCapPRO
 */
class ProjectHelper
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
     * Adds a project entry to table
     * 
     * @param int $pid - REDCap project PID
     * 
     * @return boolean success
     */
    public function addProject(int $pid)
    {
        try {
            return self::$module->logEvent("PROJECT", [
                "pid"         => $pid,
                "active"      => 1,
                "redcap_user" => USERID
            ]);
        } catch (\Exception $e) {
            self::$module->logError("Error creating project entry", $e);
        }
    }

    /**
     * Determine whether project exists in Project Table
     * 
     * Optionally additionally tests whether the project is currently active.
     * 
     * @param int $pid - REDCap Project PID
     * @param bool $check_active - Whether or not to additionally check whether active
     * 
     * @return bool
     */
    public function checkProject(int $pid, bool $check_active = FALSE)
    {
        $SQL = "SELECT active WHERE pid = ? and message = 'PROJECT' and (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result = self::$module->selectLogs($SQL, [$pid]);
            if ($result->num_rows == 0) {
                return FALSE;
            }
            $row = $result->fetch_assoc();
            return $check_active ? $row["active"] == "1" : TRUE;
        } catch (\Exception $e) {
            self::$module->logError("Error checking project", $e);
        }
    }

    /**
     * Creates link between participant and project 
     * 
     * @param int $rcpro_participant_id Participant ID
     * @param int $rcpro_project_id REDCap Project ID
     * @param int|null $dag Data Access Group for this participant in this 
     * project
     * 
     * @return bool success or failure
     */
    private function createLink(int $rcpro_participant_id, int $rcpro_project_id, ?int $dag, ?string $rcpro_username)
    {
        try {
            self::$module->logEvent("LINK", [
                "rcpro_project_id"     => $rcpro_project_id,
                "rcpro_participant_id" => $rcpro_participant_id,
                "active"               => 1,
                "redcap_user"          => USERID,
                "project_dag"           => $dag
            ]);
            self::$module->logEvent("Enrolled Participant", [
                "rcpro_participant_id" => $rcpro_participant_id,
                "rcpro_username"       => $rcpro_username,
                "rcpro_project_id"     => $rcpro_project_id,
                "redcap_user"          => USERID,
                "project_dag"           => $dag
            ]);
            return TRUE;
        } catch (\Exception $e) {
            self::$module->logError("Error enrolling participant", $e);
            return FALSE;
        }
    }




    /**
     * Get the REDCap PID corresponding with a project ID
     * 
     * @param int $rcpro_project_id - rcpro project id
     * 
     * @return int REDCap PID associated with rcpro project id
     */
    public function getPidFromProjectId(int $rcpro_project_id)
    {
        $SQL = "SELECT pid WHERE message = 'PROJECT' AND log_id = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result = self::$module->selectLogs($SQL, [$rcpro_project_id]);
            return $result->fetch_assoc()["pid"];
        } catch (\Exception $e) {
            self::$module->logError("Error fetching pid from project id", $e);
        }
    }

    /**
     * Get the project ID corresonding with a REDCap PID
     * 
     * returns null if REDCap project is not associated with REDCapPRO
     * @param int $pid REDCap PID
     * 
     * @return int rcpro project ID associated with the PID
     */
    public function getProjectIdFromPID(int $pid)
    {
        $SQL = "SELECT log_id WHERE message = 'PROJECT' AND pid = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result = self::$module->selectLogs($SQL, [$pid]);
            return $result->fetch_assoc()["log_id"];
        } catch (\Exception $e) {
            self::$module->logError("Error fetching project id from pid", $e);
        }
    }

    /**
     * Checks whether a link exists at all between participant and project - whether or not it is active
     * 
     * @param int $rcpro_participant_id
     * @param int $rcpro_project_id
     * @param int|null $dag
     * 
     * @return bool
     */
    private function linkAlreadyExists(int $rcpro_participant_id, int $rcpro_project_id)
    {
        $SQL = "message = 'LINK' AND rcpro_participant_id = ? AND rcpro_project_id = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result = self::$module->countLogsValidated($SQL, [$rcpro_participant_id, $rcpro_project_id]);
            return $result > 0;
        } catch (\Exception $e) {
            self::$module->logError("Error checking if link exists", $e);
        }
    }

    /**
     * Checks whether participant is enrolled in given project
     * 
     * @param int $rcpro_participant_id
     * @param int $rcpro_project_id
     * 
     * @return bool
     */
    public function participantEnrolled(int $rcpro_participant_id, int $rcpro_project_id)
    {
        $SQL = "message = 'LINK' AND rcpro_participant_id = ? AND rcpro_project_id = ? AND active = 1 AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result = self::$module->countLogsValidated($SQL, [$rcpro_participant_id, $rcpro_project_id]);
            return $result > 0;
        } catch (\Exception $e) {
            self::$module->logError("Error checking participant enrollment", $e);
        }
    }
}
