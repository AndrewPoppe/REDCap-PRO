<?php
namespace YaleREDCap\REDCapPRO;

class AjaxHandler
{
    private REDCapPRO $module;
    public string $method;
    public array $params;
    private $project_id;
    private $methods = [
        "getLogs",
    ];
    public function __construct(REDCapPRO $module, string $method, array $params, $project_id)
    {
        $this->method     = $method;
        $this->params     = $params ?? [];
        $this->module     = $module;
        $this->project_id = $project_id;
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
}