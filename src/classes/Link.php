<?php

namespace YaleREDCap\REDCapPRO;

class Link
{
    private $module;
    private $project;
    private $participant;
    public $id;

    function __construct(REDCapPRO $module, Project $project, Participant $participant)
    {
        $this->module = $module;
        $this->project = $project;
        $this->participant = $participant;
        $this->id = $this->getId();
    }

    /**
     * Checks whether a link exists at all between participant and project
     * 
     * This returns true if a link exists, regardless of whether that link is 
     * currently active.
     * 
     * @return bool
     */
    function exists()
    {
        $SQL = "message = 'LINK' AND rcpro_participant_id = ? AND rcpro_project_id = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result = $this->module->countLogsValidated($SQL, [
                $this->participant->rcpro_participant_id,
                $this->project->rcpro_project_id
            ]);
            return $result > 0;
        } catch (\Exception $e) {
            $this->module->logError("Error checking if link exists", $e);
        }
    }


    function isActive()
    {
        if (!$this->exists()) {
            return 0;
        }
        $SQL = "SELECT active WHERE log_id = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result = $this->module->selectLogs($SQL, [$this->getId()]);
            return $result->fetch_assoc()["active"];
        } catch (\Exception $e) {
            $this->module->logError("Error fetching link active status", $e);
        }
    }

    /**
     * Creates link between participant and project 
     * 
     * @param int|null $dag Data Access Group for this participant in this 
     * project
     * 
     * @return bool success or failure
     */
    function create(?int $dag)
    {
        try {
            return $this->module->logEvent("LINK", [
                "rcpro_project_id"     => $this->project->rcpro_project_id,
                "rcpro_participant_id" => $this->participant->rcpro_participant_id,
                "active"               => 1,
                "redcap_user"          => constant("USERID"),
                "project_dag"           => $dag
            ]);
        } catch (\Exception $e) {
            $this->module->logError("Error enrolling participant", $e);
            return FALSE;
        }
    }

    /**
     * Activate an existing link and optionally set the DAG
     * 
     * @param int|null $dag
     * 
     * @return [type]
     */
    function activate(?int $dag = NULL)
    {
        $SQL1 = "UPDATE redcap_external_modules_log_parameters SET value = 1 WHERE log_id = ? AND name = 'active'";
        try {
            $result = $this->module->query($SQL1, [$this->id]);
            if ($result && isset($dag)) {
                $SQL2 = "UPDATE redcap_external_modules_log_parameters SET value = ? WHERE log_id = ? AND name = 'project_dag'";
                $result = $this->module->query($SQL2, [$dag, $this->id]);
            }
            return $result;
        } catch (\Exception $e) {
            $this->module->logError("Error activating link", $e);
        }
    }

    /**
     * Deactivate an existing link
     * 
     * @return [type]
     */
    function deactivate()
    {
        $SQL = "UPDATE redcap_external_modules_log_parameters SET value = 0 WHERE log_id = ? AND name = 'active'";
        try {
            return $this->module->query($SQL, [$this->id]);
        } catch (\Exception $e) {
            $this->module->logError("Error deactivating link", $e);
        }
    }

    /**
     * Fetch link id
     * 
     * @return int link id
     */
    public function getId()
    {
        $SQL = "SELECT log_id WHERE message = 'LINK' AND rcpro_participant_id = ? AND rcpro_project_id = ? AND (project_id IS NULL OR project_id IS NOT NULL)";
        try {
            $result = $this->module->selectLogs($SQL, [
                $this->participant->rcpro_participant_id,
                $this->project->rcpro_project_id
            ]);
            return $result->fetch_assoc()["log_id"];
        } catch (\Exception $e) {
            $this->module->logError("Error fetching link id", $e);
        }
    }
}
