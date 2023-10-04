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
        "exportLogs",
        "importCsvEnroll",
        "importCsvRegister",
        "searchParticipantByEmail",
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
        return $this->{$this->method}();
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
            $result               = $this->module->PARTICIPANT->searchParticipants($email);
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
            } elseif ( isset($rcpro_participant_id) && !$this->module->PARTICIPANT->isParticipantActive($rcpro_participant_id) ) {
                $response = "<div class='searchResult'>The user associated with this email is not currently active in REDCapPRO.<br>Contact your REDCap Administrator with questions.</div>";
            } else {
                $response = $hint;
            }
            return $response;

        } catch ( \Throwable $e ) {
            $this->module->logError($e->getMessage(), $e);
        }
    }
}