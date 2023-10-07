<?php
namespace YaleREDCap\REDCapPRO;

class AjaxHandler
{
    private REDCapPRO $module;
    public string $method;
    public array $params;
    private $project_id;
    public $args;
    private $methods = [
        "getLogs",
        "getParticipants",
        "getParticipantsCC",
        "getProjectsCC",
        "getStaffCC",
        "exportLogs"
    ];
    public function __construct(REDCapPRO $module, string $method, array $params, $project_id, $args = null)
    {
        $this->method     = $method;
        $this->params     = $params ?? [];
        $this->module     = $module;
        $this->project_id = $project_id;
        $this->args       = $args;
    }

    public function handleAjax()
    {
        if ( !in_array($this->method, $this->methods, true) ) {
            throw new REDCapProException("Invalid ajax method");
        }
        try {
            return $this->{$this->method}();
        } catch ( \Throwable $e ) {
            $this->module->logError($e->getMessage(), $e);
            return $this->module->escape($e->getMessage());
        }
    }

    private function getLogs() : array
    {
        $logs = [];
        try {
            if ( $this->params["cc"] ) {
                if ( !$this->module->framework->isSuperUser() ) {
                    throw new REDCapProException("You must be an admin to view Control Center logs");
                }
                $result = $this->module->selectLogs("SELECT " . implode(', ', REDCapPRO::$logColumnsCC), []);

                while ( $row = $result->fetch_assoc() ) {
                    $logs[] = $row;
                }
            } else {
                $result = $this->module->selectLogs("SELECT " . implode(', ', REDCapPRO::$logColumns) . " WHERE project_id = ?", [ $this->project_id ]);

                while ( $row = $result->fetch_assoc() ) {
                    $logs[] = $row;
                }
            }
        } catch ( \Throwable $e ) {
            $this->module->logError($e->getMessage(), $e);
        } finally {
            return $this->module->escape($logs);
        }
    }

    private function getParticipants()
    {
        $participants     = [];
        $rcpro_project_id = $this->module->PROJECT->getProjectIdFromPID($this->project_id);
        $rcpro_user_dag   = $this->module->DAG->getCurrentDag($this->module->safeGetUsername(), $this->project_id);
        $role             = $this->module->getUserRole($this->module->safeGetUsername());
        try {
            $participantList = $this->module->PARTICIPANT->getProjectParticipants($rcpro_project_id, $rcpro_user_dag);
            foreach ( $participantList as $participant ) {
                $rcpro_participant_id = (int) $participant["log_id"];
                $link_id              = $this->module->PROJECT->getLinkId($rcpro_participant_id, $rcpro_project_id);
                $dag_id               = $this->module->DAG->getParticipantDag($link_id);
                $dag_name             = $this->module->DAG->getDagName($dag_id) ?? "Unassigned";
                $thisParticipant      = [
                    'username'             => $participant["rcpro_username"] ?? "",
                    'password_set'         => $participant["pw_set"] === 'True',
                    'rcpro_participant_id' => $rcpro_participant_id,
                    'dag_name'             => $dag_name,
                    'dag_id'               => $dag_id
                ];
                if ( $role > 1 ) {
                    $thisParticipant['fname'] = $participant["fname"] ?? "";
                    $thisParticipant['lname'] = $participant["lname"] ?? "";
                    $thisParticipant['email'] = $participant["email"] ?? "";
                }
                $participants[] = $thisParticipant;
            }
        } catch ( \Throwable $e ) {
            $this->module->logError('Error fetching participant list', $e);
        } finally {
            return $this->module->escape($participants);
        }
    }

    private function getParticipantsCC()
    {
        if ( !$this->module->framework->isSuperUser() ) {
            throw new REDCapProException("You must be an admin to view this page");
        }

        $participants = $this->module->PARTICIPANT->getAllParticipants();
        $results      = [];
        foreach ( $participants as $participant ) {
            $participant_clean    = $this->module->escape($participant);
            $rcpro_participant_id = (int) $participant_clean["log_id"];
            $results[]            = [
                'log_id'               => $participant_clean["log_id"],
                'rcpro_participant_id' => $rcpro_participant_id,
                'projects_array'       => $this->module->PARTICIPANT->getParticipantProjects($rcpro_participant_id),
                'info'                 => $this->module->framework->escape($this->module->PARTICIPANT->getParticipantInfo($rcpro_participant_id)),
                'username'             => $participant_clean["rcpro_username"] ?? "",
                'password_set'         => $participant_clean["pw_set"] === 'True',
                'fname'                => $participant_clean["fname"] ?? "",
                'lname'                => $participant_clean["lname"] ?? "",
                'email'                => $participant_clean["email"] ?? "",
                'isActive'             => $this->module->PARTICIPANT->isParticipantActive($rcpro_participant_id)
            ];
        }
        return $results;
    }

    private function getProjectsCC()
    {
        if ( !$this->module->framework->isSuperUser() ) {
            throw new REDCapProException("You must be an admin to view this page");
        }

        $redcap_project_ids = $this->module->framework->getProjectsWithModuleEnabled();
        $results            = [];
        foreach ( $redcap_project_ids as $id ) {
            $thisProject                = new Project($this->module, $id);
            $rcpro_project_id           = $this->module->PROJECT->getProjectIdFromPID($thisProject->redcap_pid);
            $project_rcpro_home         = $this->module->getUrl("src/home.php?pid=${id}");
            $project_rcpro_manage       = $this->module->getUrl("src/manage.php?pid=${id}");
            $project_rcpro_manage_users = $this->module->getUrl("src/manage-users.php?pid=${id}");
            $project_records            = APP_PATH_WEBROOT_FULL . APP_PATH_WEBROOT . "DataEntry/record_status_dashboard.php?pid=${id}";

            $results[] = [
                "project_id"                 => $id,
                "rcpro_project_id"           => $rcpro_project_id,
                "project_rcpro_home"         => $project_rcpro_home,
                "project_rcpro_manage"       => $project_rcpro_manage,
                "project_rcpro_manage_users" => $project_rcpro_manage_users,
                "project_records"            => $project_records,
                "title"                      => $thisProject->info["app_title"] ?? "",
                "status"                     => $thisProject->getStatus(),
                "participant_count"          => $thisProject->getParticipantCount($rcpro_project_id),
                "staff_count"                => count($thisProject->staff["allStaff"]),
                "record_count"               => $thisProject->getRecordCount()
            ];
        }
        return $results;
    }

    private function getStaffCC()
    {
        if ( !$this->module->framework->isSuperUser() ) {
            throw new REDCapProException("You must be an admin to view this page");
        }

        global $redcap_version;
        $baseUrlPath = APP_PATH_WEBROOT_FULL . 'redcap_v' . $redcap_version . '/';

        $users   = $this->module->getAllUsers();
        $results = [];
        foreach ( $users as $user ) {
            if ( $user["username"] == '' ) {
                continue; // skip blank users
            }
            $user      = $this->module->framework->escape($user);
            $results[] = [
                "username" => $user["username"],
                "name"     => $user["name"],
                "email"    => $user["email"],
                "projects" => $user["projects"],
                "userLink" => $baseUrlPath . "ControlCenter/view_users.php?username=" . $user["username"]
            ];
        }
        return $results;
    }

    private function exportLogs()
    {
        try {
            if ( $this->params["cc"] && !$this->module->framework->isSuperUser() ) {
                throw new REDCapProException("You must be an admin to view Control Center logs");
            }
            $role = $this->module->getUserRole($this->module->safeGetUsername()); // 3=admin/manager, 2=user, 1=monitor, 0=not found
            if ( $role < 3 ) {
                return;
            }
            $this->module->logEvent("Exported logs", [
                "export_type" => $this->params["export_type"],
                "redcap_user" => $this->module->safeGetUsername(),
                "export_page" => $this->params["cc"] ? "src/cc_logs" : "src/logs"
            ]);
        } catch ( \Throwable $e ) {
            $this->module->logError($e->getMessage(), $e);
        }
    }
}