<?php

namespace YaleREDCap\REDCapPRO;

class CsvRegisterImport
{
    private string $csvString;
    private REDCapPRO $module;
    private array $permissions;
    public $csvContents;
    public $cleanContents;
    public array $errorMessages = [];
    public array $proposed = [];
    private $header;
    private bool $valid = true;
    private bool $rowValid = true;
    public function __construct(REDCapPRO $module, string $csvString)
    {
        $this->module      = $module;
        $this->csvString   = $csvString;
        $this->permissions = $this->module->getPermissions();
    }

    public function parseCsvString()
    {
        $lineEnding = strpos($this->csvString, "\r\n") !== false ? "\r\n" : "\n";
        $data       = str_getcsv($this->csvString, $lineEnding);
        foreach ( $data as &$row ) {
            $row = str_getcsv($row, ',');
        }
        $this->csvContents = $data;
    }


}