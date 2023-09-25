<?php

namespace YaleREDCap\REDCapPRO;

/**
 * Provides methods to get information about a particular project as opposed to
 * the helper functions about projects generally in ProjectHelper class
 * 
 * @package REDCapPRO
 */
class Project
{
    public $module;
    public $redcap_pid;
    public $info;
    public $staff;

    /**
     * Constructor
     * 
     * @param REDCapPRO $module EM instance
     * @param int $redcap_pid REDCap project ID
     * @return void 
     */
    function __construct(REDCapPRO $module, int $redcap_pid)
    {
        $this->module           = $module;
        $this->redcap_pid       = $redcap_pid;
        $this->info             = $this->getProjectInfo();
        $this->staff            = $this->getStaff();
    }

    /**
     * Pulls all project information from redcap_projects table for the project 
     * ID associated with this Project
     * 
     * @return array Project information 
     */
    function getProjectInfo()
    {
        $SQL = "SELECT * FROM redcap_projects WHERE project_id = ?";
        try {
            $result_obj = $this->module->query($SQL, [$this->redcap_pid]);
            return $result_obj->fetch_assoc();
        } catch (\Exception $e) {
            $this->module->logError("Error getting project info", $e);
        }
    }

    /**
     * Returns description of the current status of a REDCap project
     * 
     * @return string current status of project
     */
    function getStatus()
    {
        $status_value = !is_null($this->info["completed_time"]) ? "Completed" : $this->info["status"];
        switch ($status_value) {
            case 0:
                $result = "Development";
                break;
            case 1:
                $result = "Production";
                break;
            case 2:
                $result = "Analysis/Cleanup";
                break;
            case "Completed":
                $result = "Completed";
                break;
            default:
                $result = "Unknown";
                break;
        }
        return $result;
    }

    /**
     * Returns count of participants in a project
     * 
     * @param int $rcpro_project_id RCPRO project ID
     * @return int|NULL Number of participants in project
     */
    function getParticipantCount(?int $rcpro_project_id)
    {
        $SQL = "message = 'LINK' AND rcpro_project_id = ? AND active = 1";
        try {
            return $this->module->countLogsValidated($SQL, [$rcpro_project_id]);
        } catch (\Exception $e) {
            $this->module->logError("Error getting participant count", $e);
        }
    }

    function getStaff()
    {
        try {
            $managers = $this->module->getProjectSetting("managers", $this->redcap_pid);
            $users    = $this->module->getProjectSetting("users", $this->redcap_pid);
            $monitors = $this->module->getProjectSetting("monitors", $this->redcap_pid);
            $managers = is_null($managers) ? [] : $managers;
            $users    = is_null($users) ? [] : $users;
            $monitors = is_null($monitors) ? [] : $monitors;
            $allStaff = array_merge($managers, $users, $monitors);

            return [
                "managers" => $managers,
                "users"    => $users,
                "monitors" => $monitors,
                "allStaff" => $allStaff
            ];
        } catch (\Exception $e) {
            $this->module->logError("Error getting project staff", $e);
        }
    }

    /**
     * This counts records based on redcap_record_list
     * 
     * This is fine for our purposes
     * 
     * @return int Number of records in the Project 
     */
    function getRecordCount()
    {
        $SQL = "SELECT COUNT(record) num FROM redcap_record_list WHERE project_id = ?";
        try {
            $result_obj = $this->module->query($SQL, [$this->redcap_pid]);
            return $result_obj->fetch_assoc()["num"];
        } catch (\Exception $e) {
            $this->module->logError("Error getting record count", $e);
        }
    }
}
