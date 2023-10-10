<?php
namespace YaleREDCap\REDCapPRO;

class APIParticipantRegister extends APIHandler
{
    public array $users = [];
    private bool $userValid = true;
    public bool $valid = true;
    public array $errorMessages = [];
    private array $emails = [];
    private array $dags = [];
    public function __construct(REDCapPRO $module, array $payload)
    {
        parent::__construct($module, $payload);
        $this->contentsValid();
    }

    public function contentsValid() : bool
    {

        foreach ( $this->actionData as $key => $user ) {
            $this->userValid = true;

            $fname = trim($user['fname']);
            $lname = trim($user['lname']);
            $email = trim($user['email']);
            $this->checkEmail($email);

            if ( empty($fname) || empty($lname) || empty($email) ) {
                $this->errorMessages[] = "All users must have first name, last name, and email address";
                $this->userValid       = false;
            }

            $dag = (int) trim($user['dag']);

            $enroll = trim(strtoupper($user['enroll'])) === 'Y';
            if ( $enroll ) {
                $this->checkDag($dag);
                $this->checkEnrollment($email);
                $this->checkActiveStatus($email);
            }

            if ( !$this->userValid ) {
                $this->valid = false;
            } else {
                $this->users[] = [
                    'fname'  => $fname,
                    'lname'  => $lname,
                    'email'  => $email,
                    'enroll' => $enroll ?? false,
                    'dag'    => $dag === 0 ? '[No Assignment]' : $dag
                ];
            }
        }
        if ( empty($this->users) ) {
            $this->errorMessages[] = 'There were no valid users in the json payload';
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
            $this->userValid       = false;
        } elseif ( $participantHelper->checkEmailExists($email) ) {
            $this->errorMessages[] = "Email address already exists: $email";
            $this->userValid       = false;
        } elseif ( in_array($email, $this->emails, true) ) {
            $this->errorMessages[] = "Duplicate email address: $email";
            $this->userValid       = false;
        }
        $this->emails[] = $email;
    }

    private function checkDag(int $dag) : void
    {
        $dagHelper  = new DAG($this->module);
        $dags       = $dagHelper->getProjectDags();
        $this->dags = $dags === false ? [] : $dags;
        $dagIds     = array_keys($this->dags);
        $userDag    = $dagHelper->getCurrentDag($this->user->getUsername(), $this->project->getProjectId());

        if ( !empty($dag) && !in_array($dag, $dagIds) ) {
            $this->errorMessages[] = "Invalid DAG: " . $dag;
            $this->userValid       = false;
        } elseif ( $userDag !== null && $userDag != $dag ) {
            $dagLabel              = empty($dag) ? "[No Assignment]" : ($dag . " (" . $this->dags[$dag] . ")");
            $this->errorMessages[] = "You cannot enroll a participant in a DAG you are not in: " . $dagLabel;
            $this->userValid       = false;
        }
    }

    private function checkActiveStatus(string $email)
    {
        $participantHelper  = new ParticipantHelper($this->module);
        $participant_exists = $participantHelper->checkEmailExists($email);
        if ( !$participant_exists ) {
            return;
        }
        $rcpro_participant_id = $participantHelper->getParticipantIdFromEmail($email);
        $active               = $participantHelper->isParticipantActive($rcpro_participant_id);
        if ( !$active ) {
            $this->errorMessages[] = "Participant is not active: " . $email;
            $this->userValid       = false;
        }
    }

    private function checkEnrollment(string $email)
    {
        $participantHelper  = new ParticipantHelper($this->module);
        $participant_exists = $participantHelper->checkEmailExists($email);
        if ( !$participant_exists ) {
            return;
        }
        $rcpro_participant_id = $participantHelper->getParticipantIdFromEmail($email);
        $projectHelper        = new ProjectHelper($this->module);
        $rcpro_project_id     = $projectHelper->getProjectIdFromPID($this->project->getProjectId());
        $enrolled             = $participantHelper->enrolledInProject($rcpro_participant_id, $rcpro_project_id);
        if ( $enrolled ) {
            $this->errorMessages[] = "Participant already enrolled in project: " . $email;
            $this->userValid       = false;
        }
    }

    public function takeAction() : bool
    {
        $success           = true;
        $participantHelper = new ParticipantHelper($this->module);
        try {
            foreach ( $this->users as $user ) {
                if ( !$participantHelper->checkEmailExists($user['email']) ) {
                    $rcpro_username = $participantHelper->createParticipant($user['email'], $user['fname'], $user['lname']);
                    $this->module->sendNewParticipantEmail($rcpro_username, $user['email'], $user['fname'], $user['lname']);
                }
                if ( $user['enroll'] ) {
                    $rcpro_participant_id = $participantHelper->getParticipantIdFromEmail($user['email']);
                    $rcpro_username       = $rcpro_username ?? $participantHelper->getUsername($rcpro_participant_id);
                    $dagId                = $user['dag'] === '[No Assignment]' ? null : (int) $user['dag'];
                    $projectHelper        = new ProjectHelper($this->module);
                    $result               = $projectHelper->enrollParticipant($rcpro_participant_id, $this->project->getProjectId(), $dagId, $rcpro_username);
                    if ( !$result || $result === -1 ) {
                        $this->module->logEvent("Error enrolling participant", [
                            'rcpro_username'       => $rcpro_username,
                            'rcpro_participant_id' => $rcpro_participant_id,
                            'project_id'           => $this->project->getProjectId(),
                            'dag_id'               => $dagId,
                            'redcap_user'          => $this->rights['username'],
                        ]);
                        $success = false;
                    }
                }
                $this->module->logEvent("Registered participant via API", [ 'redcap_user' => $this->module->safeGetUsername(), 'data' => json_encode($this->users, JSON_PRETTY_PRINT) ]);
            }
        } catch ( \Throwable $e ) {
            $success = false;
            $this->module->logError("Error registering users via API", $e);
        } finally {
            return $success;
        }
    }

}