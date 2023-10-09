<?php
namespace YaleREDCap\REDCapPRO;

class APIParticipantEnroll extends APIHandler
{
    public array $users = [];
    private bool $userValid = true;
    public bool $valid = true;
    public array $errorMessages = [];
    private array $emails = [];
    private array $usernames = [];
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

            $username = trim($user['username']);
            $this->checkUsername($username);
            $email = trim($user['email']);
            $this->checkEmail($email);

            if ( empty($username) && empty($email) ) {
                $this->errorMessages[] = "All users must have either a username or email address";
                $this->userValid       = false;
            }

            if ( !empty($username) && !empty($email) ) {
                $this->errorMessages[] = "Users must not have both a username or email address defined";
                $this->userValid       = false;
            }

            if ( !empty($username) ) {
                $rcpro_participant_id = $this->module->PARTICIPANT->getParticipantIdFromUsername($username);
                if ( $rcpro_participant_id === null ) {
                    $this->errorMessages[] = "Username is not associated with a REDCapPRO participant: $username";
                    $this->userValid       = false;
                    continue;
                }
                $email = $this->module->PARTICIPANT->getEmail($rcpro_participant_id);
            } else {
                $rcpro_participant_id = $this->module->PARTICIPANT->getParticipantIdFromEmail($email);
                if ( $rcpro_participant_id === null ) {
                    $this->errorMessages[] = "Email is not associated with a REDCapPRO participant: $email";
                    $this->userValid       = false;
                    continue;
                }
                $username = $this->module->PARTICIPANT->getUsername($rcpro_participant_id);
            }

            $dag = (int) trim($user['dag']);
            $this->checkDag($dag);
            $this->checkEnrollment($email);
            $this->checkActiveStatus($email);


            if ( !$this->userValid ) {
                $this->valid = false;
            } else {
                $this->users[] = [
                    'username'             => $username,
                    'email'                => $email,
                    'rcpro_participant_id' => $rcpro_participant_id,
                    'dag'                  => $dag === 0 ? '[No Assignment]' : $dag
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

    private function checkUsername(string $username) : void
    {
        if ( empty($username) ) {
            return;
        }
        $participant = $this->module->PARTICIPANT->getParticipant($username);
        if ( $participant === null ) {
            $this->errorMessages[] = "Username is not associated with a REDCapPRO participant: $username";
            $this->userValid       = false;
        } elseif ( !$this->module->PARTICIPANT->isParticipantActive($participant['log_id']) ) {
            $this->errorMessages[] = "Participant is not currently active in REDCapPRO: $username";
            $this->userValid       = false;
        } elseif ( in_array($username, $this->usernames, true) ) {
            $this->errorMessages[] = "Duplicate username: $username";
            $this->userValid       = false;
        }
        $this->usernames[] = $username;
    }

    private function checkEmail(string $email) : void
    {
        if ( empty($email) ) {
            return;
        }
        if ( !filter_var($email, FILTER_VALIDATE_EMAIL) ) {
            $this->errorMessages[] = "Invalid email address: $email";
            $this->userValid       = false;
        } elseif ( in_array($email, $this->emails, true) ) {
            $this->errorMessages[] = "Duplicate email address: $email";
            $this->userValid       = false;
        }
        $this->emails[] = $email;
    }

    private function checkDag(int $dag) : void
    {
        $dags       = $this->module->DAG->getProjectDags();
        $this->dags = $dags === false ? [] : $dags;
        $dagIds     = array_keys($this->dags);
        $userDag    = $this->module->DAG->getCurrentDag($this->user->getUsername(), $this->project->getProjectId());

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
        $rcpro_participant_id = $this->module->PARTICIPANT->getParticipantIdFromEmail($email);
        $active               = $this->module->PARTICIPANT->isParticipantActive($rcpro_participant_id);
        if ( !$active ) {
            $this->errorMessages[] = "Participant is not active: " . $email;
            $this->userValid       = false;
        }
    }

    private function checkEnrollment(string $email)
    {
        $rcpro_participant_id = $this->module->PARTICIPANT->getParticipantIdFromEmail($email);
        $rcpro_project_id     = $this->module->PROJECT->getProjectIdFromPID($this->project->getProjectId());
        $enrolled             = $this->module->PARTICIPANT->enrolledInProject($rcpro_participant_id, $rcpro_project_id);
        if ( $enrolled ) {
            $this->errorMessages[] = "Participant already enrolled in project: " . $email;
            $this->userValid       = false;
        }
    }

    public function takeAction() : bool
    {
        $success = true;
        try {
            foreach ( $this->users as $user ) {
                $rcpro_participant_id = $user['rcpro_participant_id'];
                ;
                $rcpro_username = $rcpro_username ?? $this->module->PARTICIPANT->getUsername($rcpro_participant_id);
                $dagId          = $user['dag'] === '[No Assignment]' ? null : (int) $user['dag'];
                $result         = $this->module->PROJECT->enrollParticipant($rcpro_participant_id, $this->project->getProjectId(), $dagId, $rcpro_username);
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