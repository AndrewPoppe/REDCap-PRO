<?php

namespace YaleREDCap\REDCapPRO;

class DAG
{

    public $module;
    function __construct($module)
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
     * @return array DAG IDs available to user for this project
     */
    public function getPossibleDags(string $redcap_username, int $redcap_pid)
    {
        $allDags = array(); // FIX: PHP 8 issue array_keys will no longer gracefully work with a null

        // Get all dags in project
        $dagData = $this->getProjectDags();
        $allDags = array_keys($dagData);

        // If there are none, then the user can't have any
        if ( count($allDags) === 0 ) {
            return NULL;
        }

        // If user is admin, all dags are available to them
        $user = $this->module->getUser($redcap_username);
        if ( $user->isSuperUser() ) {
            $result = [ NULL ];
            return array_merge($result, $allDags);
        }

        try {

            $SQL = "SELECT group_id 
                    FROM redcap_data_access_groups_users 
                    WHERE project_id = ? 
                    AND username = ?";

            // Get all DAGs the user can switch to with switcher
            $query = $this->module->createQuery();
            $query->add($SQL, [ $redcap_pid, $redcap_username ]);
            $result = $query->execute();

            // At least one exists here
            if ( $query->affected_rows > 0 ) {
                $dags = array();
                while ( $row = $result->fetch_assoc() ) {
                    array_push($dags, $row["group_id"]);
                }
                return $dags;
            }

            // User might only be in one DAG and not in switcher
            $current = $this->getCurrentDag($redcap_username, $redcap_pid);
            return isset($current) ? [ $current ] : $allDags;
        } catch ( \Exception $e ) {
            $this->module->logError("Error getting possible DAGs", $e);
        }
    }

    /**
     * Get currently chosen DAG for REDCap user in the provided project.
     * 
     * @param string $redcap_username Username of REDCap User (staff)
     * @param int $redcap_pid REDCap's PID for this project
     * @return int|NULL DAG ID currently selected for this user. Returns null if 
     * the user is not in a DAG. 
     */
    public function getCurrentDag(string $redcap_username, int $redcap_pid)
    {

        $user        = $this->module->getUser($redcap_username);
        $isSuperUser = $user->isSuperUser();
        if ( $isSuperUser ) {
            return null;
        }

        $SQL = "SELECT group_id 
                FROM redcap_user_rights
                WHERE project_id = ?
                AND username = ?";
        try {
            $result = $this->module->query($SQL, [ $redcap_pid, $redcap_username ]);
            $dag    = null;
            while ( $row = $result->fetch_assoc() ) {
                $dag = $row["group_id"];
            }
            return $dag;
        } catch ( \Exception $e ) {
            $this->module->logError("Error getting current DAG", $e);
        }
    }

    /**
     * Get all DAGs in the current project
     * 
     * @return array array of group names with their corresponding group_id's as
     * array keys. Returns empty array if no data access groups exist for the current 
     * project.
     */
    public function getProjectDags()
    {
        $dags = \REDCap::getGroupNames();
        if ( $dags === false ) {
            $dags = array();
        }
        return $dags;
    }

    public function getDagName($dag_id)
    {
        if ( empty((int) $dag_id) ) {
            return null;
        }
        $sql = "SELECT group_name FROM redcap_data_access_groups WHERE group_id = ?";
        $q   = $this->module->framework->query($sql, [ $dag_id ]);
        if ( $q->num_rows === 0 ) {
            return null;
        }
        return $q->fetch_assoc()['group_name'];
    }

    /**
     * Get the DAG for a participant given a rcpro link
     * 
     * @param int $rcpro_link_id 
     * @return mixed 
     */
    public function getParticipantDag(int $rcpro_link_id)
    {
        $SQL = "SELECT project_dag WHERE log_id = ?";
        try {
            $result = $this->module->selectLogs($SQL, [ $rcpro_link_id ]);
            if ( $row = $result->fetch_assoc() ) {
                return $row["project_dag"];
            }
        } catch ( \Exception $e ) {
            $this->module->logError("Error getting participant DAG", $e);
        }
    }

    /**
     * Switch a participant's DAG in the given project
     * 
     * @param int $rcpro_participant_id 
     * @param int $rcpro_project_id 
     * @param int $dag_id 
     * @return mixed 
     */
    public function updateDag(int $rcpro_link_id, ?int $dag_id)
    {
        if ( $this->module->countLogsValidated("log_id = ? AND project_dag is not null", [ $rcpro_link_id ]) > 0 ) {
            $SQL = "UPDATE redcap_external_modules_log_parameters SET value = ? WHERE log_id = ? AND name = 'project_dag'";
        } else {
            $SQL = "INSERT INTO redcap_external_modules_log_parameters (value, name, log_id) VALUES (?, 'project_dag', ?)";
        }
        try {
            return $this->module->query($SQL, [ $dag_id, $rcpro_link_id ]);
        } catch ( \Exception $e ) {
            $this->module->logError("Error updating participant's DAG", $e);
        }
    }
}