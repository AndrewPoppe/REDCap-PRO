<?php

namespace YaleREDCap\REDCapPRO;

class DAG
{

    public static $module;
    function __construct($module)
    {
        self::$module = $module;
    }



    // TODO: THINK MORE ABOUT HOW THIS SHOULD WORK. MAYBE IF THEY ARE IN A DAG WE SHOULD ONLY SHOW THEM THAT DAG AND REQUIRE THAT THEY SWITCH THEMSELVES TO ASSIGN TO/SEE OTHER DAGS 
    /**
     * Get DAGs the provided user has the ability to switch to or NULL if none
     * 
     * Notes: If user is assigned to a single DAG, this will return an array
     * containing just that DAG.
     * 
     * If the user is a REDCap admin, this will still only return the DAGs 
     * that have been explicitly assigned in the DAG switcher.
     * 
     * @param string $redcap_username Username of REDCap User (staff)
     * @param int $redcap_pid REDCap's PID for this project
     * @return array DAG IDs available to user for this project
     */
    public function getPossibleDags(string $redcap_username, int $redcap_pid)
    {

        // Get all dags in project
        $allDags = array_keys($this->getProjectDags());

        // If there are none, then the user can't have any
        if (count($allDags) === 0) {
            return NULL;
        }

        // If user is admin, all dags are available to them
        $user = self::$module->getUser($redcap_username);
        if ($user->isSuperUser()) {
            return $allDags;
        }


        try {

            $SQL = "SELECT group_id 
                    FROM redcap_data_access_groups_users 
                    WHERE project_id = ? 
                    AND username = ?";

            // Get all DAGs the user can switch to with switcher
            $query = self::$module->createQuery();
            $query->add($SQL, [$redcap_pid, $redcap_username]);
            $result = $query->execute();

            // At least one exists here
            if ($query->affected_rows > 0) {
                $dags = array();
                while ($row = $result->fetch_assoc()) {
                    array_push($dags, $row["group_id"]);
                }
                return $dags;
            }

            // User might only be in one DAG and not in switcher
            $current = $this->getCurrentDag($redcap_username, $redcap_pid);
            return isset($current) ? [$current] : $allDags;
        } catch (\Exception $e) {
            self::$module->logError("Error getting possible DAGs", $e);
        }
    }

    /**
     * Get currently chosen DAG for REDCap user in the provided project.
     * 
     * @param string $redcap_username Username of REDCap User (staff)
     * @param int $redcap_pid REDCap's PID for this project
     * @return int|NULL DAG ID currently selected for this user 
     */
    public function getCurrentDag(string $redcap_username, int $redcap_pid)
    {
        $SQL = "SELECT group_id 
                FROM redcap_user_rights
                WHERE project_id = ?
                AND username = ?";
        try {
            $result = self::$module->query($SQL, [$redcap_pid, $redcap_username]);
            $dag = NULL;
            while ($row = $result->fetch_assoc()) {
                $dag = $row["group_id"];
            }
            return $dag;
        } catch (\Exception $e) {
            self::$module->logError("Error getting current DAG", $e);
        }
    }

    /**
     * Get all DAGs in the current project
     * 
     * This is clearly just a wrapper around the REDCap module's method.
     * It is included here for consistency with other DAG methods.
     * 
     * @return mixed array of group names with their corresponding group_id's as
     * array keys. Returns FALSE if no data access groups exist for the current 
     * project. 
     */
    public function getProjectDags($unique = false)
    {
        return \REDCap::getGroupNames($unique);
    }
}
