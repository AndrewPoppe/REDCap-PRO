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
    public static $module;
    public static $rcpro_project_id;
    public static $redcap_pid;
    public static $info;
    public static $staff;

    /**
     * Constructor
     * 
     * @param REDCapPRO $module EM instance
     * @param int $redcap_pid REDCap project ID
     * @return void 
     */
    function __construct(REDCapPRO $module, int $redcap_pid)
    {
        self::$module           = $module;
        self::$redcap_pid       = $redcap_pid;
        self::$info             = $this->getProjectInfo();
        self::$staff            = $this->getStaff();
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
            $result_obj = self::$module->query($SQL, [self::$redcap_pid]);
            return $result_obj->fetch_assoc();
        } catch (\Exception $e) {
            self::$module->logError("Error getting project info", $e);
        }
    }

    /**
     * Returns description of the current status of a REDCap project
     * 
     * @return string current status of project
     */
    function getStatus()
    {
        $status_value = !is_null(self::$info["completed_time"]) ? "Completed" : self::$info["status"];
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
    function getParticipantCount(int $rcpro_project_id)
    {
        $SQL = "SELECT log_id WHERE message = 'LINK' AND rcpro_project_id = ? AND active = 1";
        try {
            return self::$module->countLogs($SQL, [$rcpro_project_id]);
        } catch (\Exception $e) {
            self::$module->logError("Error getting participant count", $e);
        }
    }

    function getStaff()
    {
        try {
            $managers = self::$module->getProjectSetting("managers", self::$redcap_pid);
            $users    = self::$module->getProjectSetting("users", self::$redcap_pid);
            $monitors = self::$module->getProjectSetting("monitors", self::$redcap_pid);
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
            self::$module->logError("Error getting project staff", $e);
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
            $result_obj = self::$module->query($SQL, [self::$redcap_pid]);
            return $result_obj->fetch_assoc()["num"];
        } catch (\Exception $e) {
            self::$module->logError("Error getting record count", $e);
        }
    }
}
