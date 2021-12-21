<?php

namespace YaleREDCap\REDCapPRO;

class REDCapProUser
{
    private $module;
    public $username;
    public $user;

    function __construct(REDCapPRO $module, string $username = NULL)
    {
        $this->module = $module;
        $this->username = $this->getUsername($username);
        $this->user = $this->getUser();
    }

    function getUsername(string $username = NULL)
    {
        if (!isset($username) && defined(USERID)) {
            $username = USERID;
        }
        return $username;
    }

    function getUser()
    {
        if (isset($this->username)) {
            $userObject = $this->module->getUser($this->username);
        }
        return $userObject;
    }

    function exists(): bool
    {
        return isset($this->user);
    }

    /**
     * Gets the REDCapPRO role for the given REDCap user
     * @param int $redcap_pid PID of project to check role against
     * @param bool $checkForSuperuser If true, returns 3 if the user is a superuser
     * 
     * @return int role
     */
    public function getUserRole(int $redcap_pid, bool $checkForSuperuser = true)
    {
        $managers = $this->module->getProjectSetting("managers", $redcap_pid);
        $users    = $this->module->getProjectSetting("users", $redcap_pid);
        $monitors = $this->module->getProjectSetting("monitors", $redcap_pid);

        $result = 0;

        $isSuperuser = $checkForSuperuser && defined(SUPER_USER) && SUPER_USER;

        if (in_array($this->username, $managers) || $isSuperuser) {
            $result = 3;
        } else if (in_array($this->username, $users)) {
            $result = 2;
        } else if (in_array($this->username, $monitors)) {
            $result = 1;
        }

        return $result;
    }

    /**
     * Updates the role of the given REDCap user 
     * 
     * @param string $username
     * @param string|NULL $oldRole This is just for logging purposes
     * @param string $newRole
     * 
     * @return void
     */
    public function changeUserRole(string $username, int $redcap_pid, ?string $oldRole, string $newRole)
    {
        try {
            $roles = array(
                "3" => $this->module->getProjectSetting("managers", $redcap_pid),
                "2" => $this->module->getProjectSetting("users", $redcap_pid),
                "1" => $this->module->getProjectSetting("monitors", $redcap_pid)
            );

            $oldRole = strval($oldRole);
            $newRole = strval($newRole);

            foreach ($roles as $role => $users) {
                if (($key = array_search($username, $users)) !== false) {
                    unset($users[$key]);
                    $roles[$role] = array_values($users);
                }
            }
            if ($newRole !== "0") {
                $roles[$newRole][] = $username;
            }

            $this->module->setProjectSetting("managers", $roles["3"], $redcap_pid);
            $this->module->setProjectSetting("users", $roles["2"], $redcap_pid);
            $this->module->setProjectSetting("monitors", $roles["1"], $redcap_pid);

            $this->module->logEvent("Changed user role", [
                "redcap_user" => $this->username,
                "redcap_user_acted_upon" => $username,
                "old_role" => $oldRole,
                "new_role" => $newRole,
                "project_id" => $redcap_pid
            ]);
        } catch (\Exception $e) {
            $this->module->logError("Error changing user role", $e);
        }
    }

    /**
     * Gets the full name of REDCap user
     * 
     * @return string|null Full Name
     */
    public function getUserFullname()
    {
        $SQL = 'SELECT CONCAT(user_firstname, " ", user_lastname) AS name FROM redcap_user_information WHERE username = ?';
        try {
            $result = $this->module->query($SQL, [$this->username]);
            return $result->fetch_assoc()["name"];
        } catch (\Exception $e) {
            $this->module->logError("Error getting user full name", $e);
        }
    }
}
