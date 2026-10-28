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
        define("API", true);

        $this->module     = $module;
        $this->payload    = $payload;
        $this->data       = $this->parsePayload();
        $this->token      = $this->module->framework->sanitizeAPIToken($this->data['token']) ?? 'X';
        $this->rights     = $this->extractRights();
        $this->user       = $this->extractUser();
        $this->project    = $this->extractProject();
        $this->checkApiRights();
        $this->actionData = $this->extractActionData();

        define('USERID', $this->user->getUsername());
        define('PROJECT_ID', $this->rights['project_id']);
        $_GET['pid']     = $this->rights['project_id'];
        $GLOBALS['Proj'] = new \Project($this->rights['project_id']);
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

    /**
     * Mirrors REDCap's own API checks (API/index.php, RestUtility::processRequest): the project must not be
     * deleted, completed or offline, rights must not have expired, and both actions import data, so API
     * Import rights are required. Admins are exempt from the rights checks, as in REDCap.
     */
    private function checkApiRights()
    {
        $project_id = $this->rights['project_id'];
        $project    = (new \Project($project_id))->project;
        if ( !empty($project['date_deleted']) ) {
            throw new \Error("This project has been deleted");
        }
        if ( !empty($project['completed_time']) ) {
            throw new \Error("This project has been marked as completed");
        }
        if ( ($project['online_offline'] ?? '1') == '0' ) {
            throw new \Error("This project is currently offline");
        }

        $username = $this->user->getUsername();
        if ( $this->user->isSuperUser() || $this->isProjectAdmin($username, $project_id) ) {
            return;
        }
        // REDCap keys privileges by lower-cased username
        $privileges = \UserRights::getPrivileges($project_id, $username);
        $rights     = $privileges[$project_id][strtolower($username)] ?? [];
        $expiration = $rights['expiration'] ?? '';
        if ( $expiration != '' && str_replace('-', '', $expiration) <= date('Ymd') ) {
            throw new \Error("Your privileges have expired for this project");
        }
        if ( (int) ($rights['api_import'] ?? 0) !== 1 ) {
            throw new \Error("You do not have API Import privileges in this project");
        }
    }

    /**
     * Project Admin Groups (REDCap 17.1.0+): REDCap's API treats these admins like super users
     */
    private function isProjectAdmin(string $username, $project_id) : bool
    {
        if ( !method_exists('ProjectAdminGroups', 'userIsAdminForProject') ) {
            return false;
        }
        $ui_id = \User::getUIIDByUsername($username);
        return !empty($ui_id) && \ProjectAdminGroups::userIsAdminForProject($ui_id, $project_id);
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
        // Like REDCap's API, ignore tokens belonging to suspended users
        $sql    = "SELECT r.* FROM redcap_user_rights r
                   JOIN redcap_user_information i ON i.username = r.username
                   WHERE r.api_token = ? AND i.user_suspended_time IS NULL";
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