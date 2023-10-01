<?php
namespace YaleREDCap\REDCapPRO;

use \ExternalModules\User;

class APIHandler
{
    private $token;
    private REDCapPRO $module;
    public User $user;
    public array $rights = [];
    public Project $project;

    public function __construct(REDCapPRO $module, string $token)
    {
        $this->module  = $module;
        $this->token   = $token;
        $this->rights  = $this->getRights();
        $this->user    = $this->getUser();
        $this->project = $this->getProject();
    }

    public function getUser()
    {
        $username = $this->rights['username'];
        if ( empty($username) ) {
            throw new REDCapProException("Invalid API token");
        }
        return $this->module->framework->getUser($username);
    }

    public function getProject()
    {
        $project_id = $this->rights['project_id'];
        if ( empty($project_id) ) {
            throw new REDCapProException("Invalid API token");
        }
        return $this->module->framework->getProject($project_id);
    }

    public function getRights()
    {
        $rights = $this->getUserRightsFromToken();
        if ( empty($rights) ) {
            throw new REDCapProException("Invalid API token");
        }
        return $rights;
    }

    private function getUserRightsFromToken()
    {
        $sql    = "SELECT * FROM redcap_user_rights WHERE api_token = ?";
        $rights = [];
        try {
            $result = $this->module->query($sql, [ $this->token ]);
            $rights = $result->fetch_assoc();
        } catch ( \Throwable $e ) {
            $this->module->log('Error getting user rights from API token', [ 'error' => $e->getMessage() ]);
        } finally {
            return $this->module->framework->escape($rights);
        }
    }
}