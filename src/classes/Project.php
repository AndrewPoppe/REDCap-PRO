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

    /**
     * Constructor
     * 
     * @param REDCapPRO $module EM instance
     * @param array $params
     * @return void 
     */
    function __construct(REDCapPRO $module, array $params)
    {
        $this->module = $module;

        if (isset($params["rcpro_project_id"])) {
            $this->rcpro_project_id = $params["rcpro_project_id"];
            $this->redcap_pid = $this->getRedcapPid();
        } else if (isset($params["redcap_pid"])) {
            $this->redcap_pid = $params["redcap_pid"];
            $this->rcpro_project_id = $this->getRcproProjectId();
        } else {
            throw new REDCapProException();
        }

        $this->info = $this->getProjectInfo();
        $this->staff = $this->getStaff();
    }

    function getRedcapPid()
    {
        $SQL = "SELECT project_id WHERE log_id = ?";
        try {
            $result = $this->module->selectLogs($SQL, [$this->rcpro_project_id]);
            return $result->fetch_assoc()["project_id"];
        } catch (\Exception $e) {
            $this->module->logError("Error fetching REDCap PID", $e);
        }
    }

    function getRcproProjectId()
    {
        $SQL = "SELECT log_id WHERE project_id = ?";
        try {
            $result = $this->module->selectLogs($SQL, [$this->redcap_pid]);
            return $result->fetch_assoc()["log_id"];
        } catch (\Exception $e) {
            $this->module->logError("Error fetching RCPRO Project ID", $e);
        }
    }

    /**
     * Pulls all project information from redcap_projects table for the project 
     * ID associated with this Project
     * 
     * @return array Project information 
     */
    public function getProjectInfo()
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
    public function getStatus()
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
     * @return int|NULL Number of participants in project
     */
    public function getParticipantCount()
    {
        $SQL = "message = 'LINK' AND rcpro_project_id = ? AND active = 1";
        try {
            return $this->module->countLogsValidated($SQL, [$this->rcpro_project_id]);
        } catch (\Exception $e) {
            $this->module->logError("Error getting participant count", $e);
        }
    }

    public function getStaff()
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
    public function getRecordCount()
    {
        $SQL = "SELECT COUNT(record) num FROM redcap_record_list WHERE project_id = ?";
        try {
            $result_obj = $this->module->query($SQL, [$this->redcap_pid]);
            return $result_obj->fetch_assoc()["num"];
        } catch (\Exception $e) {
            $this->module->logError("Error getting record count", $e);
        }
    }

    /**
     * Set a project either active or inactive in Project Table
     * 
     * @param int $active 0 to set inactive, 1 to set active
     * 
     * @return boolean success
     */
    public function setActive(int $active)
    {
        $SQL = "UPDATE redcap_external_modules_log_parameters SET value = ? WHERE log_id = ? AND name = 'active'";
        try {
            $result = $this->module->query($SQL, [$active, $this->rcpro_project_id]);
            if ($result) {
                $this->module->logEvent("Project Status Set", [
                    "rcpro_project_id" => $this->rcpro_project_id,
                    "active_status"    => $active,
                    "redcap_user"      => USERID
                ]);
            }
        } catch (\Exception $e) {
            $this->module->logError("Error setting project active status", $e);
        }
    }
}
