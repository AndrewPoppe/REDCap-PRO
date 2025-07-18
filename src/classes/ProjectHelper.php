<?php

namespace YaleREDCap\REDCapPRO;

class ProjectHelper
{
    public REDCapPRO $module;

    function __construct(REDCapPRO $module)
    {
        $this->module = $module;
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
            return $this->module->logEvent("PROJECT", [
                "pid"         => $pid,
                "active"      => 1,
                "redcap_user" => $this->module->safeGetUsername()
            ]);
        } catch ( \Exception $e ) {
            $this->module->logError("Error creating project entry", $e);
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
            $result = $this->module->selectLogs($SQL, [ $pid ]);
            if ( $result->num_rows == 0 ) {
                return FALSE;
            }
            $row = $result->fetch_assoc();
            return $check_active ? $row["active"] == "1" : TRUE;
        } catch ( \Exception $e ) {
            $this->module->logError("Error checking project", $e);
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
            $this->module->logEvent("LINK", [
                "rcpro_project_id"     => $rcpro_project_id,
                "rcpro_participant_id" => $rcpro_participant_id,
                "active"               => 1,
                "redcap_user"          => $this->module->safeGetUsername(),
                "project_dag"          => $dag
            ]);
            $this->module->logEvent("Enrolled Participant", [
                "rcpro_participant_id" => $rcpro_participant_id,
                "rcpro_username"       => $rcpro_username,
                "rcpro_project_id"     => $rcpro_project_id,
                "redcap_user"          => $this->module->safeGetUsername(),
                "project_dag"          => $dag
            ]);
            return TRUE;
        } catch ( \Exception $e ) {
            $this->module->logError("Error enrolling participant", $e);
            return FALSE;
        }
    }

    /**
     * Removes participant from project.
     * 
     * @param int $rcpro_participant_id
     * @param int $rcpro_project_id
     * 
     * @return BOOL|NULL Success/Failure of action
     */
    public function disenrollParticipant(int $rcpro_participant_id, int $rcpro_project_id, ?string $rcpro_username)
    {
        try {
            $result = $this->setLinkActiveStatus($rcpro_participant_id, $rcpro_project_id, 0);
            if ( $result ) {
                $this->module->logEvent("Disenrolled Participant", [
                    "rcpro_participant_id" => $rcpro_participant_id,
                    "rcpro_username"       => $rcpro_username,
                    "rcpro_project_id"     => $rcpro_project_id,
                    "redcap_user"          => $this->module->safeGetUsername()
                ]);
            }
            return $result;
        } catch ( \Exception $e ) {
            $this->module->logError("Error Disenrolling Participant", $e);
        }
    }

    /**
     * Enrolls a participant in a project
     * 
     * @param int $rcpro_participant_id Participant ID
     * @param int $pid REDCap project ID
     * @param int|null $dag Data Access Group the participant should be in
     * 
     * @return int -1 if already enrolled, bool otherwise
     */
    public function enrollParticipant(int $rcpro_participant_id, int $pid, ?int $dag, ?string $rcpro_username)
    {
        // If project does not exist, create it.
        if ( !$this->checkProject($pid) ) {
            $this->addProject($pid);
        }
        $rcpro_project_id = $this->getProjectIdFromPID($pid);

        // Check that user is not already enrolled in this project
        if ( $this->participantEnrolled($rcpro_participant_id, $rcpro_project_id) ) {
            return -1;
        }

        // If there is already a link between this participant and project,
        // then activate it, otherwise create the link
        if ( $this->linkAlreadyExists($rcpro_participant_id, $rcpro_project_id) ) {
            $result = $this->setLinkActiveStatus($rcpro_participant_id, $rcpro_project_id, 1, $dag);
            if ( $result ) {
                $this->module->logEvent("Enrolled Participant", [
                    "rcpro_participant_id" => $rcpro_participant_id,
                    "rcpro_username"       => $rcpro_username,
                    "rcpro_project_id"     => $rcpro_project_id,
                    "redcap_user"          => $this->module->safeGetUsername(),
                    "project_dag"          => $dag
                ]);
            }
            return $result;
        } else {
            return $this->createLink($rcpro_participant_id, $rcpro_project_id, $dag, $rcpro_username);
        }
    }

    /**
     * Fetch link id given participant and project id's
     * 
     * @param int $rcpro_participant_id
     * @param int $rcpro_project_id
     * 
     * @return int link id
     */
    public function getLinkId(int $rcpro_participant_id, int $rcpro_project_id)
    {
        $SQL = "SELECT log_id WHERE message = 'LINK' AND rcpro_participant_id = ? AND rcpro_project_id = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result = $this->module->selectLogs($SQL, [ $rcpro_participant_id, $rcpro_project_id ]);
            return $result->fetch_assoc()["log_id"];
        } catch ( \Exception $e ) {
            $this->module->logError("Error fetching link id", $e);
        }
    }

    public function getAllLinks() {
        $SQL = "SELECT log_id, rcpro_participant_id, rcpro_project_id, project_dag WHERE message = 'LINK' AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result = $this->module->selectLogs($SQL, []);
            $links  = [];
            while ($row = $result->fetch_assoc()) {
                $links[$row['rcpro_participant_id']][$row['rcpro_project_id']] = [
                    'log_id' => $row['log_id'],
                    'dag_id' => $row['project_dag']
                ];
            }
            return $links;
        } catch ( \Exception $e ) {
            $this->module->logError("Error fetching links", $e);
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
            $result = $this->module->selectLogs($SQL, [ $rcpro_project_id ]);
            return $result->fetch_assoc()["pid"];
        } catch ( \Exception $e ) {
            $this->module->logError("Error fetching pid from project id", $e);
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
            $result = $this->module->selectLogs($SQL, [ $pid ]);
            return $result->fetch_assoc()["log_id"];
        } catch ( \Exception $e ) {
            $this->module->logError("Error fetching project id from pid", $e);
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
            $result = $this->module->countLogsValidated($SQL, [ $rcpro_participant_id, $rcpro_project_id ]);
            return $result > 0;
        } catch ( \Exception $e ) {
            $this->module->logError("Error checking if link exists", $e);
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
            $result = $this->module->countLogsValidated($SQL, [ $rcpro_participant_id, $rcpro_project_id ]);
            return $result > 0;
        } catch ( \Exception $e ) {
            $this->module->logError("Error checking participant enrollment", $e);
        }
    }

    /**
     * Set a link as active or inactive
     * 
     * @param int $rcpro_participant_id
     * @param int $rcpro_project_id
     * @param int $active                   - 0 for inactive, 1 for active
     * @param int|null $dag
     * 
     * @return
     */
    private function setLinkActiveStatus(int $rcpro_participant_id, int $rcpro_project_id, int $active, ?int $dag = null)
    {
        try {
            $link_id = $this->getLinkId($rcpro_participant_id, $rcpro_project_id);
            $SQL1    = "UPDATE redcap_external_modules_log_parameters SET value = ? WHERE log_id = ? AND name = 'active'";
            $result1 = $this->module->query($SQL1, [ $active, $link_id ]);
            if ( $this->module->countLogsValidated("log_id = ? AND project_dag is not null", [ $link_id ]) > 0 ) {
                $SQL2 = "UPDATE redcap_external_modules_log_parameters SET value = ? WHERE log_id = ? AND name = 'project_dag'";
                $result2 = $this->module->query($SQL2, [ $dag ?? "", $link_id ]);
            } else if ($dag !== null) {
                $SQL2 = "INSERT INTO redcap_external_modules_log_parameters (value, name, log_id) VALUES (?, 'project_dag', ?)";
                $result2 = $this->module->query($SQL2, [ $dag ?? "", $link_id ]);
            } else {
                $result2 = TRUE; // No dag to set, so just return true
            }
            return $result2;
        } catch ( \Exception $e ) {
            $this->module->logError("Error setting link activity", $e);
        }
    }

    /**
     * Set a project either active or inactive in Project Table
     * 
     * @param int $pid PID of project
     * @param int $active 0 to set inactive, 1 to set active
     * 
     * @return boolean success
     */
    public function setProjectActive(int $pid, int $active)
    {
        $rcpro_project_id = $this->getProjectIdFromPID($pid);
        $SQL              = "UPDATE redcap_external_modules_log_parameters SET value = ? WHERE log_id = ? AND name = 'active'";
        try {
            $result = $this->module->query($SQL, [ $active, $rcpro_project_id ]);
            if ( $result ) {
                $this->module->logEvent("Project Status Set", [
                    "rcpro_project_id" => $rcpro_project_id,
                    "active_status"    => $active,
                    "redcap_user"      => $this->module->safeGetUsername()
                ]);
            }
        } catch ( \Exception $e ) {
            $this->module->logError("Error setting project active status", $e);
        }
    }
}