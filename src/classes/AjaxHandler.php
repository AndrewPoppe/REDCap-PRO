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
        "exportLogs",
        "getLogs",
        "getParticipants",
        "getParticipantsCC",
        "getProjectsCC",
        "getStaff",
        "getStaffCC",
        "importCsvEnroll",
        "importCsvRegister",
        "searchParticipantByEmail",
        "sendMfaTokenEmail",
        "showMFAInfo",
        "sendMFAInfo"
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
            $this->module->logError($e->getMessage() ?? 'Error', $e);
            return $this->module->escape($e->getMessage() ?? 'Error');
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
        $role = $this->module->getUserRole($this->module->safeGetUsername());
        if ( $role < 1 ) {
            throw new REDCapProException("You must be at least a monitor to view this page.");
        }

        $participants     = [];
        $projectHelper    = new ProjectHelper($this->module);
        $rcpro_project_id = $projectHelper->getProjectIdFromPID($this->project_id);
        $dagHelper        = new DAG($this->module);
        $rcpro_user_dag   = $dagHelper->getCurrentDag($this->module->safeGetUsername(), $this->project_id);
        try {
            $participantHelper = new ParticipantHelper($this->module);
            $participantList   = $participantHelper->getProjectParticipants($rcpro_project_id, $rcpro_user_dag);
            foreach ( $participantList as $participant ) {
                $rcpro_participant_id = (int) $participant["log_id"];
                $link_id              = $projectHelper->getLinkId($rcpro_participant_id, $rcpro_project_id);
                $dag_id               = $dagHelper->getParticipantDag($link_id);
                $dag_name             = $dagHelper->getDagName($dag_id) ?? "Unassigned";
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
        $participantHelper = new ParticipantHelper($this->module);
        $participants      = $participantHelper->getAllParticipants();
        $results           = [];
        foreach ( $participants as $participant ) {
            $participant_clean    = $this->module->escape($participant);
            $rcpro_participant_id = (int) $participant_clean["log_id"];
            $results[]            = [
                'log_id'               => $participant_clean["log_id"],
                'rcpro_participant_id' => $rcpro_participant_id,
                'projects_array'       => $participantHelper->getParticipantProjects($rcpro_participant_id),
                'info'                 => $this->module->framework->escape($participantHelper->getParticipantInfo($rcpro_participant_id)),
                'username'             => $participant_clean["rcpro_username"] ?? "",
                'password_set'         => $participant_clean["pw_set"] === 'True',
                'fname'                => $participant_clean["fname"] ?? "",
                'lname'                => $participant_clean["lname"] ?? "",
                'email'                => $participant_clean["email"] ?? "",
                'isActive'             => $participantHelper->isParticipantActive($rcpro_participant_id)
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
            $projectHelper              = new ProjectHelper($this->module);
            $rcpro_project_id           = $projectHelper->getProjectIdFromPID($thisProject->redcap_pid);
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

    private function getStaff()
    {
        $role = $this->module->getUserRole($this->module->safeGetUsername());
        if ( $role < 3 ) {
            throw new REDCapProException("You must be a manager to view this page.");
        }
        $project  = $this->module->framework->getProject();
        $userList = $project->getUsers();

        $users = [];
        foreach ( $userList as $user ) {
            $username  = $user->getUsername();
            $fullname  = $this->module->getUserFullname($username);
            $email     = $user->getEmail();
            $this_role = $this->module->getUserRole($username);

            $users[] = [
                "username" => $username,
                "fullname" => $fullname,
                "email"    => $email,
                "role"     => $this_role
            ];
        }
        return $this->module->framework->escape($users);

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

    private function importCsvRegister()
    {
        try {

            // Check that user has permission to register participants
            $role = $this->module->getUserRole($this->module->safeGetUsername()); // 3=admin/manager, 2=user, 1=monitor, 0=not found
            if ( !$role || $role < 2 ) {
                return;
            }

            $this->module->log("Importing CSV register", [ 'contents' => json_encode($this->params, JSON_PRETTY_PRINT) ]);
            $csvString         = $this->params['data'];
            $participantImport = new CsvRegisterImport($this->module, $csvString);
            $participantImport->parseCsvString();

            $contentsValid = $participantImport->contentsValid();
            if ( !$contentsValid ) {
                $message = json_encode([
                    'status'  => 'error',
                    'message' => implode('<br>', $participantImport->errorMessages)
                ]);
            } elseif ( filter_var($this->params['confirm'], FILTER_VALIDATE_BOOLEAN) ) {
                $message = json_encode([
                    'status' => 'ok',
                    'result' => $participantImport->import()
                ]);
            } else {
                $message = json_encode([
                    'status' => 'ok',
                    'table'  => $participantImport->getUpdateTable()
                ]);
            }
            return $message;
        } catch ( \Throwable $e ) {
            $this->module->logError($e->getMessage(), $e);
        }
    }

    private function importCsvEnroll()
    {
        try {

            // Check that user has permission to enroll participants
            $role = $this->module->getUserRole($this->module->safeGetUsername()); // 3=admin/manager, 2=user, 1=monitor, 0=not found
            if ( !$role || $role < 2 ) {
                return;
            }

            $this->module->log("Importing CSV enroll", [ 'contents' => json_encode($this->params, JSON_PRETTY_PRINT) ]);
            $csvString         = $this->params['data'];
            $participantImport = new CsvEnrollImport($this->module, $csvString);
            $participantImport->parseCsvString();

            $contentsValid = $participantImport->contentsValid();
            if ( !$contentsValid ) {
                $message = json_encode([
                    'status'  => 'error',
                    'message' => implode('<br>', $participantImport->errorMessages)
                ]);
            } elseif ( filter_var($this->params['confirm'], FILTER_VALIDATE_BOOLEAN) ) {
                $message = json_encode([
                    'status' => 'ok',
                    'result' => $participantImport->import()
                ]);
            } else {
                $message = json_encode([
                    'status' => 'ok',
                    'table'  => $participantImport->getUpdateTable()
                ]);
            }
            return $message;
        } catch ( \Throwable $e ) {
            $this->module->logError($e->getMessage(), $e);
        }
    }

    private function searchParticipantByEmail()
    {
        try {
            $email = filter_var($this->params['searchTerm'], FILTER_VALIDATE_EMAIL);
            if ( empty($email) ) {
                return "<font style='color: red;'>Search term is not a valid email address</font>";
            }
            $this->module->logEvent("Searching for participant by email", [ 'email' => $email ]);
            $participantHelper    = new ParticipantHelper($this->module);
            $result               = $participantHelper->searchParticipants($email);
            $hint                 = "";
            $rcpro_participant_id = null;
            while ( $row = $result->fetch_assoc() ) {
                $fname                = \REDCap::escapeHtml($row["fname"]);
                $lname                = \REDCap::escapeHtml($row["lname"]);
                $email                = \REDCap::escapeHtml($row["email"]);
                $username             = \REDCap::escapeHtml($row["rcpro_username"]);
                $id                   = \REDCap::escapeHtml($row["log_id"]);
                $rcpro_participant_id = $id;
                $hint .= "<div class='searchResult' onclick='RCPRO.populateSelection(\"${fname}\", \"${lname}\", \"${email}\", \"${id}\", \"${username}\");'><strong>${username}</strong> - ${fname} ${lname} - ${email}</div>";
            }

            if ( $hint === "" ) {
                $response = "No Participants Found";
            } elseif ( isset($rcpro_participant_id) && !$participantHelper->isParticipantActive($rcpro_participant_id) ) {
                $response = "<div class='searchResult'>The user associated with this email is not currently active in REDCapPRO.<br>Contact your REDCap Administrator with questions.</div>";
            } else {
                $response = $hint;
            }
            return $response;

        } catch ( \Throwable $e ) {
            $this->module->logError($e->getMessage(), $e);
        }
    }

    private function sendMfaTokenEmail()
    {
        try {
            $auth = new Auth($this->module->APPTITLE);
            $auth->init();
            $participantHelper = new ParticipantHelper($this->module);
            $participantEmail  = $participantHelper->getEmail($auth->get_participant_id());
            $auth->clear_email_mfa_code();
            $code = $auth->get_email_mfa_code();
            return $this->module->sendMfaTokenEmail($participantEmail, $code);
        } catch ( \Throwable $e ) {
            $this->module->logError($e->getMessage(), $e);
        }
    }

    private function showMFAInfo()
    {
        try {
            $auth = new Auth($this->module->APPTITLE);
            $auth->init();
            $participantHelper    = new ParticipantHelper($this->module);
            $rcpro_participant_id = $auth->get_participant_id();
            $participantEmail     = $participantHelper->getEmail($rcpro_participant_id);
            $mfa_secret           = $participantHelper->getMfaSecret($rcpro_participant_id);
            if ( empty($mfa_secret) ) {
                $mfa_secret = $auth->create_totp_mfa_secret();
                $participantHelper->storeMfaSecret($rcpro_participant_id, $mfa_secret);
            }
            $otpauth = $auth->create_totp_mfa_otpauth($participantEmail, $mfa_secret);
            $url     = $auth->get_totp_mfa_qr_url($otpauth, $this->module);

            return [
                'mfa_secret' => $mfa_secret,
                'url'        => $url,
                'email'      => $participantEmail
            ];
        } catch ( \Throwable $e ) {
            $this->module->logError('Error showing Authenticator App Info', $e);
        }
    }

    private function sendMFAInfo()
    {
        try {
            $auth = new Auth($this->module->APPTITLE);
            $auth->init();
            $rcpro_participant_id = $auth->get_participant_id();
            if ( $rcpro_participant_id == 0 ) {
                throw new REDCapProException("No participant ID found");
            }
            return $this->module->sendAuthenticatorAppInfoEmail($rcpro_participant_id);
        } catch ( \Throwable $e ) {
            $this->module->logError('Error sending Authenticator App Info email', $e);
        }
    }
}