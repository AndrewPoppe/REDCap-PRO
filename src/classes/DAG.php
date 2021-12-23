<?php

namespace YaleREDCap\REDCapPRO;

class DAG
{

    private $module;

    function __construct(REDCapPRO $module)
    {
        $this->module = $module;
    }

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
     * 
     * @return array DAG IDs available to user for this project
     */
    public function get_possible_dags(string $redcap_username, int $redcap_pid): array
    {

        // Get all dags in project
        $allDags = array_keys($this->get_project_dags());

        // If there are none, then the user can't have any
        if (count($allDags) === 0) {
            return NULL;
        }

        // If user is admin, all dags are available to them
        $user = $this->module->getUser($redcap_username);
        if ($user->isSuperUser()) {
            $result = [NULL];
            return array_merge($result, $allDags);
        }

        try {

            $SQL = "SELECT group_id 
                    FROM redcap_data_access_groups_users 
                    WHERE project_id = ? 
                    AND username = ?";

            // Get all DAGs the user can switch to with switcher
            $query = $this->module->createQuery();
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
            $current = $this->get_current_dags($redcap_username, $redcap_pid);
            return isset($current) ? [$current] : $allDags;
        } catch (\Exception $e) {
            $this->module->logError("Error getting possible DAGs", $e);
        }
    }

    /**
     * Get currently chosen DAG for REDCap user in the provided project.
     * 
     * @param string $redcap_username Username of REDCap User (staff)
     * @param int $redcap_pid REDCap's PID for this project
     * @return int|NULL DAG ID currently selected for this user. Returns -1 if 
     * the user is not in a DAG. 
     */
    public function get_current_dags(string $redcap_username, int $redcap_pid)
    {
        $SQL = "SELECT group_id 
                FROM redcap_user_rights
                WHERE project_id = ?
                AND username = ?";
        try {
            $result = $this->module->query($SQL, [$redcap_pid, $redcap_username]);
            $dag = -1;
            while ($row = $result->fetch_assoc()) {
                $dag = $row["group_id"];
            }
            return $dag;
        } catch (\Exception $e) {
            $this->module->logError("Error getting current DAG", $e);
        }
    }

    /**
     * Get all DAGs in the current project
     * 
     * @return string[] array of group names with their corresponding group_id's as
     * array keys. Returns FALSE if no data access groups exist for the current 
     * project. 
     */
    public function get_project_dags(): array
    {
        return \REDCap::getGroupNames();
    }

    /**
     * Get the DAG for a participant given a rcpro link
     * 
     * @param int $rcpro_link_id 
     * @return mixed 
     */
    public function get_participant_dag(int $rcpro_link_id)
    {
        $SQL = "SELECT project_dag WHERE log_id = ?";
        try {
            $result = $this->module->selectLogs($SQL, [$rcpro_link_id]);
            if ($row = $result->fetch_assoc()) {
                return $row["project_dag"];
            }
        } catch (\Exception $e) {
            $this->module->logError("Error getting participant DAG", $e);
        }
    }

    /**
     * Switch a participant's DAG in the given project
     * 
     * @param int $rcpro_link_id 
     * @param int $dag_id 
     * @return mixed 
     */
    public function update_dag(int $rcpro_link_id, ?int $dag_id)
    {
        if ($this->module->countLogsValidated("log_id = ? AND project_dag is not null", [$rcpro_link_id]) > 0) {
            $SQL = "UPDATE redcap_external_modules_log_parameters SET value = ? WHERE log_id = ? AND name = 'project_dag'";
        } else {
            $SQL = "INSERT INTO redcap_external_modules_log_parameters (value, name, log_id) VALUES (?, 'project_dag', ?)";
        }
        try {
            return $this->module->query($SQL, [$dag_id, $rcpro_link_id]);
        } catch (\Exception $e) {
            $this->module->logError("Error updating participant's DAG", $e);
        }
    }
}
