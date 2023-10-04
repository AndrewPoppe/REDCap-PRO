<?php
namespace YaleREDCap\REDCapPRO;

use \ExternalModules\User;

class APIHandler
{
    public $token;
    public REDCapPRO $module;
    private array $payload;
    public User $user;
    public array $rights = [];
    public \ExternalModules\Project $project;
    private array $data = [];
    public array $actionData = [];

    public function __construct(REDCapPRO $module, array $payload)
    {
        $this->module  = $module;
        $this->payload = $payload;
        $this->data    = $this->parsePayload();
        $this->token   = $this->data['token'] ?? 'X';
        $this->rights  = $this->extractRights();
        $this->user    = $this->extractUser();
        define('USERID', $this->user->getUsername());
        $this->project    = $this->extractProject();
        $_GET['pid']      = $this->project->getProjectId();
        $this->actionData = $this->extractActionData();
    }

    public function extractUser()
    {
        $username = $this->rights['username'];
        if ( empty($username) ) {
            throw new \Error("Invalid API token");
        }
        return $this->module->framework->getUser($username);
    }

    public function extractProject()
    {
        $project_id = $this->rights['project_id'];
        if ( empty($project_id) ) {
            throw new \Error("Invalid API token");
        }
        return $this->module->framework->getProject($project_id);
    }

    public function extractRights()
    {
        $rights = $this->getUserRightsFromToken();
        if ( empty($rights) ) {
            throw new \Error('Invalid API token');
        }
        return $rights;
    }

    public function extractActionData()
    {
        $actionDataString = $this->data['data'];
        try {
            $actionData = json_decode($actionDataString, true);
        } catch ( \Throwable $e ) {
            $this->module->logError('Error decoding action data', $e);
        }
        if ( empty($actionData) ) {
            throw new \Error('No import data provided');
        }
        return $this->module->framework->escape($actionData);
    }

    private function getUserRightsFromToken() : array
    {
        $sql    = "SELECT * FROM redcap_user_rights WHERE api_token = ?";
        $rights = [];
        try {
            $result = $this->module->framework->query($sql, [ $this->token ]);
            $rights = $result->fetch_assoc() ?? [];
        } catch ( \Throwable $e ) {
            $this->module->logError('Error getting user rights from API token', $e);
        } finally {
            return $this->module->framework->escape($rights);
        }
    }

    private function parsePayload()
    {
        try {
            $actionDataString   = $this->payload['data'] ?? '{}'; // This will be sanitized later
            $this->data         = $this->module->framework->escape($this->payload);
            $this->data['data'] = $actionDataString;
        } catch ( \Throwable $e ) {
            $this->module->logError('Error parsing payload', $e);
        } finally {
            return $this->data;
        }
    }

    public function getRole()
    {
        return $this->module->getUserRole($this->user->getUsername());
    }

    public function getApiData()
    {
        return [
            'token'      => $this->token,
            'rights'     => $this->rights,
            'user'       => $this->user->getUsername(),
            'project'    => $this->project->getProjectId(),
            'role'       => $this->module->getUserRole($this->user->getUsername()),
            'actionData' => $this->actionData
        ];
    }
}