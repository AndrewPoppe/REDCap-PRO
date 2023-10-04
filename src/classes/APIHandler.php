<?php
namespace YaleREDCap\REDCapPRO;

use \ExternalModules\User;

class APIHandler
{
    public $token;
    private REDCapPRO $module;
    private array $payload;
    public User $user;
    public array $rights = [];
    public \ExternalModules\Project $project;
    public array $data = [];
    public string $action;
    public array $actionData = [];
    private array $allowedActions = [
        "enroll",
        "register"
    ];

    public function __construct(REDCapPRO $module, array $payload)
    {
        $this->module     = $module;
        $this->payload    = $payload;
        $this->data       = $this->parsePayload();
        $this->token      = $this->data['token'] ?? 'X';
        $this->rights     = $this->extractRights();
        $this->user       = $this->extractUser();
        $this->project    = $this->extractProject();
        $this->action     = $this->extractAction();
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

    public function extractAction()
    {
        $action = $this->data['action'];
        if ( empty($action) ) {
            throw new \Error('No action provided');
        }
        if ( !in_array($action, $this->allowedActions, true) ) {
            throw new \Error('Invalid action provided');
        }
        return $action;
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
        return $actionData;
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
        //$data_clean = [];
        try {
            $this->data = $this->module->framework->escape($this->payload);
        } catch ( \Throwable $e ) {
            $this->module->logError('Error parsing payload', $e);
        } finally {
            return $this->data;
        }
    }

    public function getApiData()
    {
        return [
            'token'      => $this->token,
            'rights'     => $this->rights,
            'user'       => $this->user->getUsername(),
            'project'    => $this->project->getProjectId(),
            'data'       => $this->data,
            'role'       => $this->module->getUserRole($this->user->getUsername()),
            'action'     => $this->action,
            'actionData' => $this->actionData
        ];
    }
}