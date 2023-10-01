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
        "importCsvRegister"
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
        $this->module->log('ok', []);
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
        $this->module->log("Importing CSV register", [ 'contents' => json_encode($this->params, JSON_PRETTY_PRINT) ]);
        $csvString = $this->params['data'];
        $sagImport = new CsvRegisterImport($this->module, $csvString);
        $sagImport->parseCsvString();

        $contentsValid = $sagImport->contentsValid();
        if ( $contentsValid !== true ) {
            return json_encode([
                'status'  => 'error',
                'message' => $sagImport->errorMessages
            ]);
        }

        if ( filter_var($this->params['confirm'], FILTER_VALIDATE_BOOLEAN) ) {
            return json_encode([
                'status' => 'ok',
                'result' => $sagImport->import()
            ]);
        } else {
            return json_encode([
                'status' => 'ok',
                'table'  => $sagImport->getUpdateTable()
            ]);
        }
    }
}