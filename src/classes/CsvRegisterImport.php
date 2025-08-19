<?php

namespace YaleREDCap\REDCapPRO;

class CsvRegisterImport
{
    private string $csvString;
    private REDCapPRO $module;
    private $project_id;
    public $csvContents;
    public array $cleanContents;
    public array $errorMessages = [];
    public array $proposed = [];
    private $header;
    private bool $valid = true;
    private bool $rowValid = true;
    private bool $hasDags = false;
    private array $indices;

    private array $emails = [];
    private array $dags;
    public function __construct(REDCapPRO $module, string $csvString)
    {
        $this->module     = $module;
        $this->csvString  = $csvString;
        $this->project_id = $this->module->framework->getProjectId();
    }

    public function parseCsvString()
    {
        $lineEnding = strpos($this->csvString, "\r\n") !== false ? "\r\n" : "\n";
        $data       = str_getcsv($this->csvString, $lineEnding, "\"", "\\");
        foreach ( $data as &$row ) {
            $row = str_getcsv($row, ',', "\"", "\\");
        }
        $this->csvContents = $data;
    }

    public function contentsValid() : bool
    {
        $this->header = $this->csvContents[0];

        $this->indices['fnameIndex']  = array_search('fname', $this->header, true);
        $this->indices['lnameIndex']  = array_search('lname', $this->header, true);
        $this->indices['emailIndex']  = array_search('email', $this->header, true);
        $this->indices['enrollIndex'] = array_search('enroll', $this->header, true);
        $this->indices['dagIndex']    = array_search('dag', $this->header, true);

        if ( $this->indices['fnameIndex'] === false || $this->indices['lnameIndex'] === false || $this->indices['emailIndex'] === false ) {
            $this->errorMessages[] = 'You must include each of these columns in the import file: fname, lname, email';
            return false;
        }

        foreach ( $this->csvContents as $key => $row ) {
            $this->rowValid = true;

            if ( $key === array_key_first($this->csvContents) ) {
                continue;
            }

            $fname = trim($this->module->framework->escape($row[$this->indices['fnameIndex']]));
            $lname = trim($this->module->framework->escape($row[$this->indices['lnameIndex']]));
            $email = trim($this->module->framework->escape($row[$this->indices['emailIndex']]));
            $this->checkEmail($email);

            if ( $this->indices['enrollIndex'] !== false ) {
                $enroll = trim($this->module->framework->escape($row[$this->indices['enrollIndex']])) === 'Y';
            }

            if ( $enroll && $this->indices['dagIndex'] !== false ) {
                $this->hasDags = true;
                $dag           = (int) trim($this->module->framework->escape($row[$this->indices['dagIndex']]));
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
                    'dag'    => $dag === 0 ? '[No Assignment]' : $dag
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
        $participantHelper = new ParticipantHelper($this->module);
        if ( !filter_var($email, FILTER_VALIDATE_EMAIL) ) {
            $this->errorMessages[] = "Invalid email address: $email";
            $this->rowValid        = false;
        } elseif ( $participantHelper->checkEmailExists($email) ) {
            $this->errorMessages[] = "Email address already exists: $email";
            $this->rowValid        = false;
        } elseif ( in_array($email, $this->emails, true) ) {
            $this->errorMessages[] = "Duplicate email address: $email";
            $this->rowValid        = false;
        }
        $this->emails[] = $email;
    }

    private function checkDag(int $dag) : void
    {
        $dagHelper  = new DAG($this->module);
        $this->dags = $dagHelper->getProjectDags();
        $dagIds     = array_keys($this->dags);
        $userDag    = $dagHelper->getCurrentDag($this->module->safeGetUsername(), $this->project_id);

        if ( !empty($dag) && !in_array($dag, $dagIds) ) {
            $this->errorMessages[] = "Invalid DAG: " . $dag;
            $this->rowValid        = false;
        } elseif ( $userDag !== null && $userDag != $dag ) {
            $dagLabel              = empty($dag) ? "[No Assignment]" : ($dag . " (" . $this->dags[$dag] . ")");
            $this->errorMessages[] = "You cannot enroll a participant in a DAG you are not in: " . $dagLabel;
            $this->rowValid        = false;
        }
    }

    public function import()
    {
        $success = true;
        try {
            foreach ( $this->cleanContents as $row ) {
                $participantHelper = new ParticipantHelper($this->module);
                $rcpro_username    = $participantHelper->createParticipant($row['email'], $row['fname'], $row['lname']);
                $this->module->sendNewParticipantEmail($rcpro_username, $row['email'], $row['fname'], $row['lname']);
                if ( $row['enroll'] ) {
                    $rcpro_participant_id = $participantHelper->getParticipantIdFromUsername($rcpro_username);
                    $dagId                = $row['dag'] === '[No Assignment]' ? null : (int) $row['dag'];
                    $projectHelper        = new ProjectHelper($this->module);
                    $result               = $projectHelper->enrollParticipant($rcpro_participant_id, $this->project_id, $dagId, $rcpro_username);
                    if ( !$result || $result === -1 ) {
                        $this->module->logEvent('Error enrolling participant via CSV', [
                            'rcpro_username'       => $rcpro_username,
                            'rcpro_participant_id' => $rcpro_participant_id,
                            'project_id'           => $this->project_id,
                            'dag_id'               => $dagId
                        ]);
                        $success = false;
                    }
                }
            }
            $this->module->logEvent('Imported Participants from CSV', [ 'data' => json_encode($this->cleanContents) ]);
        } catch ( \Throwable $e ) {
            $success = false;
            $this->module->logError('Error importing Participants', $e);
        } finally {
            return $success;
        }
    }

    public function getUpdateTable()
    {
        $html = '<div class="modal fade">
        <div class="modal-lg modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        Confirm Participants to Import
                    </h5>
                    <button type="button" class="btn-close align-self-center" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                <div class="container mb-4 w-90" style="font-size:larger;">Examine the table of proposed changes below to verify it is correct.</div>
                <table class="table table-bordered">
                    <thead class="thead-dark table-dark">
                        <tr>
                            <th class="text-center">First Name</th>
                            <th class="text-center">Last Name</th>
                            <th class="text-center">Email</th>
                            <th class="text-center">Enroll</th>';
        $html .= $this->hasDags ? '<th class="text-center">DAG ID</th>' : '';
        $html .= '</tr></thead><tbody>';
        foreach ( $this->cleanContents as $row ) {
            $html .= '<tr class="bg-light">';
            $html .= '<td class="text-center">' . $row['fname'] . '</td>';
            $html .= '<td class="text-center">' . $row['lname'] . '</td>';
            $html .= '<td class="text-center">' . $row['email'] . '</td>';
            $html .= '<td class="text-center">' . ($row['enroll'] ? 'Yes' : 'No') . '</td>';
            $dagValue = $row['enroll'] ? $row['dag'] : '-';
            if ( (int) $dagValue !== 0 ) {
                $dagValue = $dagValue . ' (' . $this->dags[$row['dag']] . ')';
            }
            $html .= $this->hasDags ? '<td class="text-center">' . $dagValue . '</td>' : '';
            $html .= '</tr>';
        }

        $html .= '</tbody>
                </table>
            </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="RCPRO.confirmImport()">Confirm</button>
                </div>
            </div>
        </div>
    </div>';
        return $html;
    }

}