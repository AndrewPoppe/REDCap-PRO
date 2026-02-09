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
        "deleteLanguage",
        "downloadLanguageFile",
        "exportLogs",
        "getLanguage",
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
        "sendMFAInfo",
        "setLanguage",
        "setLanguageActiveStatus"
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

    private function getLanguage()
    {
        try {
            $role = $this->module->getUserRole($this->module->safeGetUsername()); // 3=admin/manager, 2=user, 1=monitor, 0=not found
            if ( $role < 3 ) {
                throw new REDCapProException("You must be a manager or admin to edit languages");
            }

            $languageCode = $this->params['languageCode'] ?? null;
            if ( empty($languageCode) ) {
                throw new REDCapProException("No language code provided");
            }
            $language = new Language($this->module);

            $languages = $language->getLanguages(false);
            if (!isset($languages[$languageCode])) {
                throw new REDCapProException("Language not found");
            }
            $thisLanguage = $languages[$languageCode];
            $thisLanguage['strings'] = $language->getLanguageStrings($languageCode);
            $thisLanguage['EnglishStrings'] = $this->module->escape($language->getEnglishStrings());
            return $thisLanguage;
        } catch ( \Throwable $e ) {
            $this->module->logError($e->getMessage(), $e);
            return $this->module->escape($e->getMessage() ?? 'Error');
        }
    }

    private function setLanguage()
    {
        try {
            $role = $this->module->getUserRole($this->module->safeGetUsername()); // 3=admin/manager, 2=user, 1=monitor, 0=not found
            if ( $role < 3 ) {
                throw new REDCapProException("You must be a manager or admin to edit languages");
            }
            $languageCode     = $this->params['code'];
            $languageStrings  = $this->params['strings'];
            if ( empty($languageCode) ) {
                throw new REDCapProException("No language code provided");
            }
            if ( empty($languageStrings) || !is_array($languageStrings) ) {
                throw new REDCapProException("No language strings provided or invalid format");
            }
            $language = new Language($this->module);
            $builtInLanguages = $language->getBuiltInLanguages();
            if (isset($builtInLanguages[$languageCode])) {
                throw new REDCapProException("Cannot overwrite built-in language: " . $languageCode);
            }
            $language->setLanguageActiveStatus($languageCode, false);
            $language->setLanguageStrings($languageCode, $languageStrings);
            return [ 'status' => 'ok' ];
        } catch ( \Throwable $e ) {
            $this->module->logError($e->getMessage(), $e);
            return $this->module->escape($e->getMessage() ?? 'Error');
        }
    }

    private function setLanguageActiveStatus()
    {
        try {
            $role = $this->module->getUserRole($this->module->safeGetUsername()); // 3=admin/manager, 2=user, 1=monitor, 0=not found
            if ( $role < 3 ) {
                throw new REDCapProException("You must be a manager or admin to edit languages");
            }
            $languageCode = $this->params['languageCode'] ?? null;
            if ( empty($languageCode) ) {
                throw new REDCapProException("No language code provided");
            }
            $language = new Language($this->module);
            $language->setLanguageActiveStatus($languageCode, $this->params['active']);
            return [ 'status' => 'ok' ];
        } catch ( \Throwable $e ) {
            $this->module->logError($e->getMessage(), $e);
            return $this->module->escape($e->getMessage() ?? 'Error');
        }
    }

    private function deleteLanguage()
    {
        try {
            $role = $this->module->getUserRole($this->module->safeGetUsername()); // 3=admin/manager, 2=user, 1=monitor, 0=not found
            if ( $role < 3 ) {
                throw new REDCapProException("You must be a manager or admin to delete languages");
            }
            $languageCode = $this->params['languageCode'] ?? null;
            if ( empty($languageCode) ) {
                throw new REDCapProException("No language code provided");
            }
            $language = new Language($this->module);
            $builtInLanguages = $language->getBuiltInLanguages();
            if (isset($builtInLanguages[$languageCode])) {
                throw new REDCapProException("Cannot delete built-in language: " . $languageCode);
            }
            $language->deleteLanguage($languageCode);
            return [ 'status' => 'ok' ];
        } catch ( \Throwable $e ) {
            $this->module->logError($e->getMessage(), $e);
            return $this->module->escape($e->getMessage() ?? 'Error');
        }
    }

    private function downloadLanguageFile() 
    {
        try {
            $role = $this->module->getUserRole($this->module->safeGetUsername()); // 3=admin/manager, 2=user, 1=monitor, 0=not found
            if ( $role < 3 ) {
                throw new REDCapProException("You must be a manager or admin to download language files");
            }
            $languageCode = $this->params['languageCode'] ?? null;
            if ( empty($languageCode) ) {
                throw new REDCapProException("No language code provided");
            }
            $format = $this->params['format'] ?? 'json';
            if ( !in_array($format, ['json', 'ini'], true) ) {
                throw new REDCapProException("Invalid format specified");
            }
            $language = new Language($this->module);
            $strings = $language->getLanguageStrings($languageCode);
            if ($format === 'json') {
                return ["fileContents" => json_encode($strings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)];
            } else {
                $iniString = "";
                foreach ($strings as $key => $value) {
                    $iniString .= "$key = $value\n";
                }
                return ["fileContents" => $iniString];  
            }
            
        } catch ( \Throwable $e ) {
            $this->module->logError($e->getMessage(), $e);
             echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage() ?? 'Error'
             ]);
        }
    }

    private function getLogs()
    {
        try {
            if ( $this->params["cc"] === TRUE && !$this->module->framework->isSuperUser() ) {
                throw new REDCapProException("You must be an admin to view Control Center logs");
            }

            $baseColumns = [
                "log_id",
                "timestamp",
                "ui_id",
                "ip",
                "project_id",
                "record",
                "message"
            ];

            $start     = (int) $this->params["start"];
            $length    = (int) $this->params["length"];
            $limitTerm = ($length < 0) ? "" : " limit " . $start . "," . $length;

            $fastQueryParameters = [];
            $queryParameters = [ $this->module->getModuleToken() ];
            $columnSearchParameters = [];

            $generalSearchTerm       = $this->params["search"]["value"] ?? "";
            $generalSearchText       = "module_token = ?" . ($generalSearchTerm === "" ? "" : " and (");
            $columnSearchText  = "";

            // Timestamp filtering
            $timestampFilterText       = "";
            $timestampFilterParameters = [];
            if ( $this->params["minDate"] != "" ) {
                $timestampFilterText .= " and timestamp >= ?";
                $timestampFilterParameters[] = $this->params["minDate"];
            }
            if ( $this->params["maxDate"] != "" ) {
                $timestampFilterText .= " and timestamp <= ?";
                $timestampFilterParameters[] = $this->params["maxDate"];
            }

            // Filtering
            foreach ( $this->params["columns"] as $key => $column ) {

                // Add column to general search if it is searchable
                if ( $generalSearchTerm !== "" && $column["searchable"] == "true" ) {
                    if ( array_key_first($this->params["columns"]) !== $key ) {
                        $generalSearchText .= " or ";
                    }
                    $generalSearchText .= db_escape($column["data"]) . " like ?";
                    array_push($queryParameters, sprintf("%%%s%%", $generalSearchTerm));
                }

                // Add any column-specific filtering
                $searchVal = $column["search"]["value"];
                if ( $searchVal != "" ) {
                    $searchVal        = $column["search"]["regex"] == "true" ? $searchVal : sprintf("%%%s%%", $searchVal);
                    if (in_array($column["data"], $baseColumns, true)) {
                        $columnSearchText .= " and l." . db_escape($column["data"]) . " like ?";
                        array_push($columnSearchParameters, $searchVal);
                    } else {
                        $columnSearchText .= " and l.log_id in (select distinct log_id from redcap_external_modules_log_parameters where name = ? and value like ?) ";
                        array_push($columnSearchParameters, $column["data"], $searchVal);    
                    }
                }
            }
            $generalSearchText .= $generalSearchTerm === "" ? "" : ")";

            $orderTerm = "";
            foreach ( $this->params["order"] as $index => $order ) {
                $column    = db_escape($this->params["columns"][intval($order["column"])]["data"]);
                $direction = $order["dir"] === "asc" ? "asc" : "desc";
                if ( $index === 0 ) {
                    $orderTerm .= " order by ";
                }
                $orderTerm .= $column . " " . $direction;
                if ( $index !== sizeof($this->params["order"]) - 1 ) {
                    $orderTerm .= ", ";
                }
            }

            // BUILD A FAST QUERY OF THE LOGS
            if ($this->params['cc'] === TRUE) {
                $columns = REDCapPRO::$logColumnsCC;
                $projectClause = "";
            } else {
                $columns = REDCapPRO::$logColumns;
                $projectClause = " AND l.project_id = ?";
            }

            $sqlFull = "SELECT ";
            foreach ($columns as $key => $column) {
                if ( in_array( $column, $baseColumns, true) ) {
                    $sqlFull .= " l." . $column;
                } else {
                    $sqlFull .= " MAX(CASE WHEN p.name = '" . $column . "' THEN p.value END) AS $column ";
                }
                if ($key !== array_key_last($columns)) {
                    $sqlFull .= ", ";
                }
            }
            
            $sql = " from redcap_external_modules_log l 
                    left join redcap_external_modules_log_parameters p
                    ON p.log_id = l.log_id 
                    left join redcap_external_modules_log_parameters module_token
                    on module_token.log_id = l.log_id
                    and module_token.name = 'module_token'
                    WHERE l.external_module_id = (SELECT external_module_id FROM redcap_external_modules WHERE directory_prefix = ?)
                    and module_token.value = ? " . $projectClause;
            array_push($fastQueryParameters, $this->module->framework->getModuleInstance()->PREFIX, $this->module->getModuleToken());
            if (!$this->params['cc']) {
                array_push($fastQueryParameters, $this->project_id);
            }

            if ( $generalSearchTerm !== "" ) {
                $sql .= " and (";
                foreach ($baseColumns as $baseColumn) {
                    $sql .= "l.$baseColumn like ? or ";
                    array_push($fastQueryParameters, "%$generalSearchTerm%");
                }
                $sql .= " l.log_id in (select log_id from redcap_external_modules_log_parameters where value like ?)) ";
                array_push($fastQueryParameters, "%$generalSearchTerm%");
            }

            if ( $columnSearchText !== "" ) {
                $sql .= $columnSearchText;
                array_push($fastQueryParameters, ...$columnSearchParameters);
            }

            if ( $timestampFilterText !== "" ) {
                $sql .= $timestampFilterText;
                array_push($fastQueryParameters, ...$timestampFilterParameters);
            }

            $sql .= " group by l.log_id ";

            $queryResult = $this->module->framework->query($sqlFull . $sql . $orderTerm . $limitTerm, $fastQueryParameters);
            $countResult = $this->module->framework->query("SELECT * " . $sql, $fastQueryParameters)->num_rows;

            $totalCountResult = $this->module->countLogs("module_token = ?", [ $this->module->getModuleToken() ]);
            $logs        = array();
            while ( $row = $queryResult->fetch_assoc() ) {
                $logs[] = $row;
            }

            
            return [
                "data"            => $this->module->framework->escape($logs),
                "draw"            => (int) $this->params["draw"],
                "recordsTotal"    => $totalCountResult,
                "recordsFiltered" => $countResult
            ];

        } catch ( \Throwable $e ) {
            $this->module->logError('Error getting logs', $e);
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
            $links             = $projectHelper->getAllLinks();

            foreach ( $participantList as $participant ) {
                $rcpro_participant_id = (int) $participant["log_id"];
                $dag_id = $links[$rcpro_participant_id][$rcpro_project_id]['dag_id'];
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
        $allProjects = $participantHelper->getAllParticipantProjects();
        $results           = [];
        foreach ( $participants as $participant ) {
            $participant_clean    = $this->module->escape($participant);
            $rcpro_participant_id = (int) $participant_clean["log_id"];
            $projects = $allProjects[$rcpro_participant_id] ?? [];
            $info_clean = [
                'User_ID'       => $participant_clean['log_id'] ?? "",
                'Username'      => $participant_clean['rcpro_username'] ?? "",
                'Registered At' => $participant_clean['timestamp'] ?? "",
                'Registered By' => $participant_clean['redcap_user'] ?? ""
            ];
            $active = !isset($participant_clean["active"]) || $participant_clean["active"] == 1;
            
            $results[]            = [
                'log_id'               => $participant_clean["log_id"],
                'rcpro_participant_id' => $rcpro_participant_id,
                'projects_array'       => $projects,
                'info'                 => $info_clean,
                'username'             => $participant_clean["rcpro_username"] ?? "",
                'password_set'         => $participant_clean["pw_set"] === 'True',
                'fname'                => $participant_clean["fname"] ?? "",
                'lname'                => $participant_clean["lname"] ?? "",
                'email'                => $participant_clean["email"] ?? "",
                'isActive'             => $active
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
            $project_rcpro_home         = $this->module->getUrl("src/home.php?pid={$id}");
            $project_rcpro_manage       = $this->module->getUrl("src/manage.php?pid={$id}");
            $project_rcpro_manage_users = $this->module->getUrl("src/manage-users.php?pid={$id}");
            $project_records            = APP_PATH_WEBROOT_FULL . APP_PATH_WEBROOT . "DataEntry/record_status_dashboard.php?pid={$id}";

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

            $this->module->logEvent("Importing CSV register", [ 'contents' => json_encode($this->params, JSON_PRETTY_PRINT) ]);
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

            $this->module->logEvent("Importing CSV enroll", [ 'contents' => json_encode($this->params, JSON_PRETTY_PRINT) ]);
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

            // Check that user has permission to search participants
            $role = $this->module->getUserRole($this->module->safeGetUsername()); // 3=admin/manager, 2=user, 1=monitor, 0=not found
            if ( !$role || $role < 2 ) {
                return;
            }

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
                $hint .= "<div class='searchResult' onclick='RCPRO.populateSelection(\"{$fname}\", \"{$lname}\", \"{$email}\", \"{$id}\", \"{$username}\");'><strong>{$username}</strong> - {$fname} {$lname} - {$email}</div>";
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
            $rcpro_username       = $participantHelper->getUserName($rcpro_participant_id);
            $mfa_secret           = $participantHelper->getMfaSecret($rcpro_participant_id);
            if ( empty($mfa_secret) ) {
                $mfa_secret = $auth->create_totp_mfa_secret();
                $participantHelper->storeMfaSecret($rcpro_participant_id, $mfa_secret);
            }
            $otpauth = $auth->create_totp_mfa_otpauth($rcpro_username, $mfa_secret);
            $url     = $auth->get_totp_mfa_qr_url($otpauth, $this->module);

            return [
                'mfa_secret' => $mfa_secret,
                'url'        => $url,
                'username'   => $rcpro_username
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