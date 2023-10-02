<?php

namespace YaleREDCap\REDCapPRO;

class CsvRegisterImport
{
    private string $csvString;
    private REDCapPRO $module;
    private $project_id;
    public $csvContents;
    public $cleanContents;
    public array $errorMessages = [];
    public array $proposed = [];
    private $header;
    private bool $valid = true;
    private bool $rowValid = true;
    public function __construct(REDCapPRO $module, string $csvString)
    {
        $this->module     = $module;
        $this->csvString  = $csvString;
        $this->project_id = $this->module->framework->getProjectId();
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

    public function contentsValid() : bool
    {
        $this->header = $this->csvContents[0];

        $fnameIndex  = array_search('fname', $this->header, true);
        $lnameIndex  = array_search('lname', $this->header, true);
        $emailIndex  = array_search('email', $this->header, true);
        $enrollIndex = array_search('enroll', $this->header, true);
        $dagIndex    = array_search('dag', $this->header, true);

        if ( $fnameIndex === false || $lnameIndex === false || $emailIndex === false ) {
            $this->errorMessages[] = 'You must include each of these columns in the import file: fname, lname, email';
            return false;
        }

        foreach ( $this->csvContents as $key => $row ) {
            $this->rowValid = true;

            if ( $key === array_key_first($this->csvContents) ) {
                continue;
            }

            $fname = trim($this->module->framework->escape($row[$fnameIndex]));
            $lname = trim($this->module->framework->escape($row[$lnameIndex]));
            $email = trim($this->module->framework->escape($row[$emailIndex]));
            $this->checkEmail($email);

            if ( $enrollIndex !== false ) {
                $enroll = (bool) trim($this->module->framework->escape($row[$enrollIndex]));
            }

            if ( $enroll && $dagIndex !== false ) {
                $dag = trim($this->module->framework->escape($row[$dagIndex]));
                $this->checkDag($dag);
            }

            if ( !$this->rowValid ) {
                $this->valid = false;
            } else {
                $this->cleanContents[] = [
                    'fname'  => $fname,
                    'lname'  => $lname,
                    'email'  => $email,
                    'enroll' => $enroll ?? false,
                    'dag'    => $dag ?? null
                ];
            }
        }
        if ( empty($this->cleanContents) ) {
            $this->errorMessages[] = 'There were no valid rows in the import file';
            $this->valid           = false;
        }

        $this->errorMessages = array_values(array_unique($this->errorMessages));
        return $this->valid;
    }

    private function checkEmail(string $email) : void
    {
        if ( !filter_var($email, FILTER_VALIDATE_EMAIL) ) {
            $this->errorMessages[] = "Invalid email address: $email";
            $this->rowValid        = false;
        }

        if ( $this->module->PARTICIPANT->checkEmailExists($email) ) {
            $this->errorMessages[] = "Email address already exists: $email";
            $this->rowValid        = false;
        }
    }

    private function checkDag(string $dag) : void
    {
        $dags    = $this->module->DAG->getProjectDags();
        $userDag = $this->module->DAG->getCurrentDag($this->module->safeGetUsername(), $this->project_id);

        if ( $userDag !== null && $userDag !== $dag ) {
            $this->errorMessages[] = "You cannot enroll a participant in a DAG you are not in: $dag";
            $this->rowValid        = false;
        }
        if ( empty($dag) ) {
            return;
        } elseif ( !in_array($dag, $dags, true) ) {
            $this->errorMessages[] = "Invalid DAG: " . $dag;
            $this->rowValid        = false;
        }
    }

    public function import()
    {

    }

    public function getUpdateTable()
    {

    }

}