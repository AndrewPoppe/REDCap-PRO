<?php
namespace YaleREDCap\REDCapPRO;

use ExternalModules\AbstractExternalModule;

/**
 * Main EM Class
 */
class REDCapPRO extends AbstractExternalModule {
 
    static $APPTITLE                 = "REDCapPRO";
    static $LOGIN_ATTEMPTS           = 3;
    static $LOCKOUT_DURATION_SECONDS = 60;

    static $AUTH;
    
    function __construct() {
        parent::__construct();
        $this::$AUTH = new Auth($this::$APPTITLE);
    }

    function redcap_every_page_top($project_id) {
        if (strpos($_SERVER["PHP_SELF"], "surveys") !== false) {
            return;
        }
        $role = SUPER_USER ? 3 : $this->getUserRole(USERID); // 3=admin/manager, 2=user, 1=monitor, 0=not found
        if ($role > 0) {
            ?>
            <script>
                setTimeout(function() {
                    let link = $("<div>"+
                        "<img src='<?=$this->getUrl('images/fingerprint_2.png');?>' style='width:16px; height:16px; position:relative; top:-2px'></img>"+
                        "&nbsp;"+
                        "<a href='<?=$this->getUrl('home.php');?>'><span id='RCPro-Link'><strong><font style='color:black;'>REDCap</font><em><font style='color:#900000;'>PRO</font></em></strong></span></a>"+
                    "</div>");
                    $('#app_panel').find('div.hang').last().after(link);
                }, 10);
            </script>
            <?php
        }
    }

    function redcap_survey_page_top($project_id, $record, $instrument, 
    $event_id, $group_id, $survey_hash, $response_id, $repeat_instance) {
        
        // Initialize Authentication
        $this::$AUTH::init();

        // Participant is logged in to their account
        if (isset($_SESSION[$this::$APPTITLE."_loggedin"]) && $_SESSION[$this::$APPTITLE."_loggedin"] === true) {

            // Determine whether participant is enrolled in the study.
            $user_id = $_SESSION[$this::$APPTITLE."_user_id"];
            if (!$this->enrolledInProject($user_id, $project_id)) {
                $this->UiShowParticipantHeader("Not Enrolled");
                echo "<p style='text-align:center;'>You are not currently enrolled in this study.<br>Please contact the study representative.</p>";
                $this->exitAfterHook();
            }

            \REDCap::logEvent(
                "REDCapPro Survey Accessed",                                        // action description
                "REDCapPRO User: ".$_SESSION[$this::$APPTITLE."_username"]."\n".
                "Instrument: ${instrument}\n",                                      // changes made
                NULL,                                                               // sql
                $record,                                                            // record
                $event_id,                                                          // event
                $project_id                                                         // project id
            );
            $this->log("REDCapPro Survey Accessed", [
                "rcpro_username" => $_SESSION[$this::$APPTITLE."_username"],
                "rcpro_user_id"  => $_SESSION[$this::$APPTITLE."_user_id"],
                "record" => $record,
                "event" => $event_id,
                "project" => $project_id,
                "instrument" => $instrument,
                "survey_hash" => $survey_hash,
                "response_id" => $response_id,
                "repeat_instance" => $repeat_instance
            ]);
            echo "<style>
                .swal2-timer-progress-bar {
                    background: #900000 !important;
                }
                button.swal2-confirm:focus {
                    box-shadow: 0 0 0 3px rgb(144 0 0 / 50%) !important;
                }
            </style>";
            echo "<script src='".$this->getUrl("rcpro_base.js",true)."'></script>";
            echo "<script>
                window.rcpro.logo = '".$this->getUrl("images/RCPro_Favicon.svg")."';
                window.rcpro.logoutPage = '".$this->getUrl("logout.php", true)."';
                window.rcpro.initTimeout();
            </script>";

        // Participant is not logged into their account
        // Store cookie to return to survey
        } else {
            $_SESSION[$this::$APPTITLE."_survey_url"] = APP_PATH_SURVEY_FULL."?s=${survey_hash}";
            \Session::savecookie($this::$APPTITLE."_survey_url", APP_PATH_SURVEY_FULL."?s=${survey_hash}", 0, TRUE);
            $_SESSION[$this::$APPTITLE."_survey_link_active"] = TRUE;
            header("location: ".$this->getUrl("login.php", true)."&s=${survey_hash}"); // TODO: Does hash need to be in querystring? Consider removing if not necessary
            $this->exitAfterHook();
        }

    }

    /**
     * Hook that is triggered when a module is enabled in Control Center
     * 
     * @param mixed $version
     * 
     * @return void
     */
    function redcap_module_system_enable($version) {
        // TODO: Do what?
    }

    /**
     * Hook that is triggered when a module is disabled in Control Center
     * 
     * @param mixed $version
     * 
     * @return void
     */
    function redcap_module_system_disable($version) {
        // TODO: Do what?
    }

    /**
     * Hook that is triggered when a module is enabled in a Project
     * 
     * @param mixed $version
     * @param mixed $pid
     * 
     * @return void
     */
    function redcap_module_project_enable($version, $pid) {
        if (!$this->checkProject($pid)) {
            $this->addProject($pid);
        } else {
            $this->setProjectActive($pid, 1);
        }
    }

    /**
     * Hook that is triggered when a module is disabled in a Project
     * 
     * @param mixed $version
     * @param mixed $pid
     * 
     * @return void
     */
    function redcap_module_project_disable($version, $project_id) {
        $this->setProjectActive($project_id, 0);
    }

    

    /**
     * Increments the number of failed attempts at login for the provided id
     * 
     * @param int $rcpro_participant_id - id key for participant
     * 
     * @return BOOL|NULL
     */
    public function incrementFailedLogin(int $rcpro_participant_id) {
        $SQL = "UPDATE redcap_external_modules_log_parameters SET value = value+1 WHERE log_id = ? AND name = 'failed_attempts'";
        try {
            $res = $this->query($SQL, [$rcpro_participant_id]);
            
            // Lockout username if necessary
            $this->lockoutLogin($rcpro_participant_id);
            return $res;
        }
        catch (\Exception $e) {
            $this->logError("Error incrementing failed login", $e);
            return NULL;
        }
    }

    /**
     * This both tests whether a user should be locked out based on the number
     * of failed login attempts and does the locking out.
     * 
     * @param int $rcpro_participant_id - id key for participant
     * 
     * @return BOOL|NULL
     */
    private function lockoutLogin(int $rcpro_participant_id) {
        try {
            $attempts = $this->checkUsernameAttempts($rcpro_participant_id);
            if ($attempts >= $this::$LOGIN_ATTEMPTS) {
                $lockout_ts = time() + $this::$LOCKOUT_DURATION_SECONDS;
                $SQL = "UPDATE redcap_external_modules_log_parameters SET lockout_ts = ? WHERE log_id = ?;";
                $res = $this->query($SQL, [$lockout_ts, $rcpro_participant_id]);
                $status = $res ? "Successful" : "Failed";
                $this->log("Login Lockout ${status}", [
                    "rcpro_participant_id" => $rcpro_participant_id
                ]);
                return $res;
            } else {
                return TRUE;
            }
        }
        catch (\Exception $e) {
            $this->logError("Error doing login lockout", $e);
            return FALSE;
        }
    }

    /**
     * Resets the count of failed login attempts for the given id
     * 
     * @param int $rcpro_participant_id - id key for participant
     * 
     * @return BOOL|NULL
     */
    public function resetFailedLogin(int $rcpro_participant_id) {
        $SQL = "UPDATE redcap_external_modules_log_parameters SET value=0 WHERE log_id=? AND name='failed_attempts';";
        try {
            return $this->query($SQL, [$rcpro_participant_id]);
        }
        catch (\Exception $e) {
            $this->logError("Error resetting failed login count", $e);
            return NULL;
        }
    }

    /**
     * Gets the IP address in an easy way
     * 
     * @return string - the ip address
     */
    public function getIPAddress() {
        return $_SERVER['REMOTE_ADDR'];
    }  

    /**
     * Increments the number of failed login attempts for the given ip address
     * 
     * It also detects whether the ip should be locked out based on the number
     * of failed attempts and then does the locking.
     * 
     * @param string $ip Client IP address
     * 
     * @return int number of attempts INCLUDING current attempt
     */
    public function incrementFailedIp(string $ip) {
        if ($this->getSystemSetting('ip_lockouts') === null) {
            $this->setSystemSetting('ip_lockouts', json_encode(array()));
        }
        $ipLockouts = json_decode($this->getSystemSetting('ip_lockouts'), true);
        if (isset($ipLockouts[$ip])) {
            $ipStat = $ipLockouts[$ip];
        } else {
            $ipStat = array();
        }
        if (isset($ipStat["attempts"])) {
            $ipStat["attempts"]++;
            if ($ipStat["attempts"] >= $this::$LOGIN_ATTEMPTS) {
                $ipStat["lockout_ts"] = time() + $this::$LOCKOUT_DURATION_SECONDS;
                $this->log("Locked out IP address", [
                    "rcpro_ip"   => $ip,
                    "lockout_ts" => $ipStat["lockout_ts"]
                ]);
            }
        } else {
            $ipStat["attempts"] = 1;
        }
        $ipLockouts[$ip] = $ipStat;
        $this->setSystemSetting('ip_lockouts', json_encode($ipLockouts));
        return $ipStat["attempts"];
    }

    /**
     * Resets the failed login attempt count for the given ip address
     * 
     * @param string $ip Client IP address
     * 
     * @return bool Whether or not the reset succeeded
     */
    public function resetFailedIp(string $ip) {
        try {
            if ($this->getSystemSetting('ip_lockouts') === null) {
                $this->setSystemSetting('ip_lockouts', json_encode(array()));
            }
            $ipLockouts = json_decode($this->getSystemSetting('ip_lockouts'), true);
            if (isset($ipLockouts[$ip])) {
                $ipStat = $ipLockouts[$ip];
            } else {
                $ipStat = array();
            }
            $ipStat["attempts"] = 0;
            $ipStat["lockout_ts"] = NULL;
            $ipLockouts[$ip] = $ipStat;
            $this->setSystemSetting('ip_lockouts', json_encode($ipLockouts));
            return TRUE;
        }
        catch (\Exception $e) {
            $this->logError("IP Login Attempt Reset Failed", $e);
            return FALSE;
        }
    }

    /**
     * Checks the number of failed login attempts for the given ip address
     * 
     * @param string $ip Client IP address
     * 
     * @return int number of failed login attempts for the given ip
     */
    private function checkIpAttempts(string $ip) { 
        if ($this->getSystemSetting('ip_lockouts') === null) {
            $this->setSystemSetting('ip_lockouts', json_encode(array()));
        }
        $ipLockouts = json_decode($this->getSystemSetting('ip_lockouts'), true);
        if (isset($ipLockouts[$ip])) {
            $ipStat = $ipLockouts[$ip];
            if (isset($ipStat["attempts"])) {
                return $ipStat["attempts"];
            }
        }
        return 0;
    }

    /**
     * Determines whether given ip is currently locked out
     * 
     * @param string $ip Client IP address
     * 
     * @return bool whether ip is locked out
     */
    public function checkIpLockedOut(string $ip) {
        if ($this->getSystemSetting('ip_lockouts') === null) {
            $this->setSystemSetting('ip_lockouts', json_encode(array()));
        }
        $ipLockouts = json_decode($this->getSystemSetting('ip_lockouts'), true);
        $ipStat = $ipLockouts[$ip];
        
        if (isset($ipStat) && $ipStat["lockout_ts"] !== null && $ipStat["lockout_ts"] >= time()) {
            return $ipStat["lockout_ts"];
        }
        return FALSE;
    }

    /**
     * Gets number of failed login attempts for the given user by id
     * 
     * @param int $rcpro_participant_id - id key for participant
     * 
     * @return int number of failed login attempts
     */
    private function checkUsernameAttempts(int $rcpro_participant_id) {
        $SQL = "SELECT failed_attempts WHERE message = 'PARTICIPANT' AND log_id = ? AND project_id <> FALSE;";
        try {
            $res = $this->queryLogs($SQL, [$rcpro_participant_id]);
            return $res->fetch_assoc()["failed_attempts"];
        }
        catch (\Exception $e) {
            $this->logError("Failed to check username attempts", $e);
            return 0;
        }
    }

    /**
     * Checks whether given user (by id) is locked out
     * 
     * Returns the remaining lockout time in seconds for this participant
     * 
     * @param int $rcpro_participant_id - id key for participant
     * 
     * @return int number of seconds of lockout left
     */
    public function getUsernameLockoutDuration(int $rcpro_participant_id) {
        $SQL = "SELECT lockout_ts WHERE message = 'PARTICIPANT' AND log_id = ? AND project_id <> FALSE;";
        try {
            $res = $this->queryLogs($SQL, [$rcpro_participant_id]);
            $lockout_ts = intval($res->fetch_assoc()["lockout_ts"]);
            $time_remaining = $lockout_ts - time();
            if ($time_remaining > 0) {
                return $time_remaining;
            }
        }
        catch (\Exception $e) {
            $this->logError("Failed to check username lockout", $e);
        }
    }

    /**
     * Returns number of consecutive failed login attempts
     * 
     * This checks both by username and by ip, and returns the larger
     * 
     * @param int|null $rcpro_participant_id
     * @param mixed $ip
     * 
     * @return int number of consecutive attempts
     */
    public function checkAttempts($rcpro_participant_id, $ip) {
        if ($rcpro_participant_id === null) {
            $usernameAttempts = 0;
        } else {
            $usernameAttempts = $this->checkUsernameAttempts($rcpro_participant_id);
        }
        $ipAttempts = $this->checkIpAttempts($ip);
        return max($usernameAttempts, $ipAttempts);
    }

    ////////////////////
    ///// SETTINGS /////
    ////////////////////

    /**
     * Gets the REDCapPRO role for the given REDCap user
     * @param string $username REDCap username
     * 
     * @return int role
     */
    public function getUserRole(string $username) {
        $managers = $this->getProjectSetting("managers");
        $users    = $this->getProjectSetting("users");
        $monitors = $this->getProjectSetting("monitors");
        
        $result = 0;

        if (in_array($username, $managers)) {
            $result = 3;
        } else if (in_array($username, $users)) {
            $result = 2;
        } else if (in_array($username, $monitors)) {
            $result = 1;
        }

        return $result;
    }

    /**
     * Updates the role of the given REDCap user 
     * 
     * @param string $username
     * @param string $oldRole
     * @param string $newRole
     * 
     * @return void
     */
    public function changeUserRole(string $username, string $oldRole, string $newRole) {
        $roles = array(
            "3" => $this->getProjectSetting("managers"),
            "2" => $this->getProjectSetting("users"),
            "1" => $this->getProjectSetting("monitors")
        );

        $oldRole = strval($oldRole);
        $newRole = strval($newRole);

        if (($key = array_search($username, $roles[$oldRole])) !== false) {
            unset($roles[$oldRole][$key]);
            $roles[$oldRole] = array_values($roles[$oldRole]);
        }
        if ($newRole !== "0") {
            $roles[$newRole][] = $username;
        }
            
        $this->setProjectSetting("managers", $roles["3"]);
        $this->setProjectSetting("users", $roles["2"]);
        $this->setProjectSetting("monitors", $roles["1"]);
    }



    /*
        Instead of creating a user table, we'll use the built-in log table (and log parameters table)

        So, there will be a message called "PARTICIPANT" 
        The log_id will be the id of the participant (rcpro_participant_id)
        The log's timestamp will act as the creation time
        and the parameters will be:
            * rcpro_username              - the coded username for this participant
            * email                 - email address
            * fname                 - first name
            * lname                 - last name
            * pw (hashed)           - hashed password
            * last_modified_ts      - timstamp of any updates to this log (php DateTime converted to unix timestamp)
            * failed_attempts       - number of failed login attempts for this username (not ip)
            * lockout_ts            - timestamp that a lockout will end (php DateTime converted to unix timestamp)
            * token                 - password set/reset token
            * token_ts              - timestamp the token is valid until (php DateTime converted to unix timestamp)
            * token_valid           - bool? 0/1? what is best here?
    */

    /*
        Insteam of a Project table:
        There will be a message called PROJECT
        The log_id will serve as the rcpro_project_id
        The timestamp will be the creation timestamp
        The parameters will be:
            * pid               - REDCap project id for this project
            * active            - whether the project is active or not. bool? 0/1?
    */

    /*
        Instead of a Link table:
        There will be a message called LINK
        The log_id will serve as the link id
        The timestamp will be the creation timestamp
        The parameters will be:
            * project           - rcpro_project_id (int)
            * participant       - rcpro_participant_id (int)
            * active            - bool? 0/1? This is whether the participant is enrolled 
                                  (i.e., if the link is active)
    */

    /**
     * Get hashed password for participant.
     * 
     * @param int $rcpro_participant_id - id key for participant
     * 
     * @return string|NULL hashed password or null
     */
    public function getHash(int $rcpro_participant_id) {
        try {
            $SQL = "SELECT pw WHERE message = 'PARTICIPANT' AND log_id = ? AND project_id <> FALSE;";
            $res = $this->queryLogs($SQL, [$rcpro_participant_id]);
            return $res->fetch_assoc()['pw'];
        }
        catch (\Exception $e) {
            $this->logError("Error fetching password hash", $e);
        }
    }

    /**
     * Stores hashed password for the given participant id
     * 
     * @param string $hash - hashed password
     * @param int $rcpro_participant_id - id key for participant
     * 
     * @return bool|NULL success/failure/null
     */
    public function storeHash(string $hash, int $rcpro_participant_id) {
        try {
            $SQL = "UPDATE redcap_external_modules_log_parameters SET value = ? WHERE log_id = ? AND name = 'pw';";
            $res = $this->query($SQL, [$hash, $rcpro_participant_id]);
            $this->log("Password Hash Stored", ["rcpro_participant_id" => $rcpro_participant_id]);
            return $res;
        }
        catch (\Exception $e) {
            $this->logError("Error storing password hash", $e);
        }
    }

    /**
     * Adds participant entry into log table.
     * 
     * In so doing, it creates a unique username.
     * 
     * @param string $email Email Address
     * @param string $fname First Name
     * @param string $lname Last Name
     * 
     * @return string username of newly created participant
     */
    public function createParticipant(string $email, string $fname, string $lname) {
        $username     = $this->createUsername();
        $email_clean  = \REDCap::escapeHtml($email);
        $fname_clean  = \REDCap::escapeHtml($fname);
        $lname_clean  = \REDCap::escapeHtml($lname);
        $counter      = 0;
        $counterLimit = 90000000;
        while ($this->usernameIsTaken($username) && $counter < $counterLimit) {
            $username = $this->createUsername();
            $counter++;
        }
        if ($counter >= $counterLimit) {
            echo "Please contact your REDCap administrator.";
            return NULL;
        }
        try {
            $id = $this->log("PARTICIPANT", [
                "rcpro_username"   => $username,
                "email"            => $email_clean,
                "fname"            => $fname_clean,
                "lname"            => $lname_clean,
                "pw"               => "",
                "last_modified_ts" => time(),
                "lockout_ts"       => time(),
                "failed_attempts"  => 0,
                "token"            => "",
                "token_ts"         => time(),
                "token_valid"      => 0
            ]);
            if (!$id) {
                throw new REDCapProException(["rcpro_username" => $username]);
            } 
            $this->log("Participant Created", [
                "rcpro_user_id"  => $id,
                "rcpro_username" => $username
            ]);
            return $username;
        }
        catch (\Exception $e) {
            $this->logError("Participant Creation Failed", $e);
        }
    }


    /**
     * Creates a "random" username
     * It creates an 8-digit username (between 10000000 and 99999999)
     * Of the form: XXX-XX-XXX
     * 
     * @return string username
     */
    private function createUsername() {
        return sprintf("%03d", random_int(100, 999)) . '-' . 
               sprintf("%02d", random_int(0,99)) . '-' . 
               sprintf("%03d", random_int(0,999));
    }

    /**
     * Checks whether username already exists in database.
     * 
     * @param string $username
     * 
     * @return boolean|NULL True if taken, False if free, NULL if error 
     */
    public function usernameIsTaken(string $username) {
        $SQL = "message = 'PARTICIPANT' AND rcpro_username = ? AND project_id <> FALSE";
        try {
            $result = $this->countLogs($SQL, [$username]);
            return $result > 0;
        }
        catch (\Exception $e) {
            $this->logError("Error checking if username is taken", $e);
        }
    }


    /**
     * Returns an array with participant information given a username
     * 
     * @param string $username
     * 
     * @return array|NULL user information
     */
    public function getParticipant(string $username) {
        if ($username === NULL) {
            return NULL;
        }
        $SQL = "SELECT log_id, rcpro_username, email, fname, lname WHERE message = 'PARTICIPANT' AND rcpro_username = ? AND project_id <> FALSE";
        try {
            $result = $this->queryLogs($SQL, [$username]);
            return $result->fetch_assoc();
        }
        catch (\Exception $e) {
            $this->logError("Error fetching participant information", $e);
        }
    }

    /**
     * Fetch username for given participant id
     * 
     * @param int $rcpro_participant_id - participant id
     * 
     * @return string|NULL username
     */
    public function getUserName(int $rcpro_participant_id) {
        $SQL = "SELECT rcpro_username WHERE message = 'PARTICIPANT' AND log_id = ? AND project_id <> FALSE";
        try {
            $result = $this->queryLogs($SQL, [$rcpro_participant_id]);
            return $result->fetch_assoc()["rcpro_username"];
        }
        catch (\Exception $e) {
            $this->logError("Error fetching username", $e);
        }
    }

    /**
     * Fetch participant id corresponding with given username
     * 
     * @param string $username
     * 
     * @return int|NULL RCPRO participant id
     */
    public function getParticipantIdFromUsername(string $username) {
        $SQL = "SELECT log_id WHERE message = 'PARTICIPANT' AND rcpro_username = ? AND project_id <> FALSE";
        try {
            $result = $this->queryLogs($SQL, [$username]);
            return $result->fetch_assoc()["log_id"];
        }
        catch (\Exception $e) {
            $this->logError("Error fetching id from username", $e);
        }
    }

    /**
     * Fetch participant id corresponding with given email
     * 
     * @param string $email
     * 
     * @return int|NULL RCPRO participant id
     */
    public function getParticipantIdFromEmail(string $email) {
        $SQL = "SELECT log_id WHERE message = 'PARTICIPANT' AND email = ? AND project_id <> FALSE";
        try {
            $result = $this->queryLogs($SQL, [$email]);
            return $result->fetch_assoc()["log_id"];
        }
        catch (\Exception $e) {
            $this->logError("Error fetching id from email", $e);
        }
    }

    /**
     * Fetch email corresponding with given participant id
     * 
     * @param int $rcpro_participant_id
     * 
     * @return string|NULL email address
     */
    public function getEmail(int $rcpro_participant_id) {
        $SQL = "SELECT email WHERE message = 'PARTICIPANT' AND log_id = ? AND project_id <> FALSE";
        try {
            $result = $this->queryLogs($SQL, [$rcpro_participant_id]);
            return $result->fetch_assoc()["email"];
        }
        catch (\Exception $e) {
            $this->logError("Error fetching email address", $e);
        }
    }

    /**
     * Use provided search string to find registered participants
     * 
     * @param string $search_term - text to search for
     * 
     * @return 
     */
    public function searchParticipants(string $search_term) {
        $SQL = "SELECT fname, lname, email, log_id 
                WHERE message = 'PARTICIPANT' 
                AND project_id <> FALSE 
                AND (fname LIKE ? OR lname LIKE ? OR email LIKE ?)";
        try {
            return $this->queryLogs($SQL, [$search_term, $search_term, $search_term]);
        }
        catch(\Exception $e) {
            $this->logError("Error performing livesearch", $e);
        }
    }

    /**
     * Grabs all registered participants
     * 
     * @return array|NULL of user arrays or null if error
     */
    public function getAllParticipants() {
        $SQL = "SELECT log_id, rcpro_username, email, fname, lname, lockout_ts WHERE message = 'PARTICIPANT' AND project_id <> FALSE";
        try {
            $result = $this->queryLogs($SQL, []);
            $participants  = array();

            // grab participant details
            while ($row = $result->fetch_assoc()) {
                $participants[$row["log_id"]] = $row;               
            }
            return $participants;
        }
        catch (\Exception $e) {
            $this->logError("Error fetching participants", $e);
        }
    }


    /**
     * get array of active enrolled participants given a rcpro project id
     * 
     * @param string $rcpro_project_id Project ID (not REDCap PID!)
     * 
     * @return array|NULL participants enrolled in given study
     */
    public function getProjectParticipants(string $rcpro_project_id) {
        $SQL = "SELECT rcpro_participant_id WHERE message = 'LINK' AND rcpro_project_id = ? AND active = 1 AND project_id <> FALSE";
        try {
            $result = $this->queryLogs($SQL, [$rcpro_project_id]);
            $participants  = array();

            while ($row = $result->fetch_assoc()) {
                $participantSQL = "SELECT log_id, rcpro_username, email, fname, lname, lockout_ts WHERE message = 'PARTICIPANT' AND log_id = ? AND project_id <> FALSE";
                $participantResult = $this->queryLogs($participantSQL, [$row["rcpro_participant_id"]]);
                $participant = $participantResult->fetch_assoc();
                $participants[$row["rcpro_participant_id"]] = $participant;               
            }
            return $participants;
        }
        catch (\Exception $e) {
            $this->logError("Error fetching project participants", $e);
        }
    }

    /**
     * Determine whether email address already exists in database
     * 
     * @param string $email
     * 
     * @return boolean True if email already exists, False if not
     */
    public function checkEmailExists(string $email) {
        $SQL = "message = 'PARTICIPANT' AND email = ? AND project_id <> FALSE";
        try {
            $result = $this->countLogs($SQL, [$email]);
            return $result > 0;
        } 
        catch (\Exception $e) {
            $this->logError("Error checking if email exists", $e);
        }
    }


    /**
     * Determines whether the current participant is enrolled in the project
     * 
     * @param int $rcpro_participant_id
     * @param int $pid PID of REDCap project
     * 
     * @return boolean TRUE if the participant is enrolled
     */
    public function enrolledInProject(int $rcpro_participant_id, int $pid) {
        $rcpro_project_id = $this->getProjectIdFromPID($pid);
        $SQL = "message = 'LINK' AND rcpro_project_id = ? AND rcpro_participant_id = ? AND project_id <> FALSE";
        try {
            $result = $this->countLogs($SQL, [$rcpro_project_id, $rcpro_participant_id]);
            return $result > 0;
        }
        catch (\Exception $e) {
            $this->logError("Error checking that participant is enrolled", $e);
        }
    }


    /**
     * Adds a project entry to table
     * 
     * @param int $pid - REDCap project PID
     * 
     * @return boolean success
     */
    public function addProject(int $pid) {
        try {
            return $this->log("PROJECT", [
                "pid"    => $pid,
                "active" => 1
            ]);
        }
        catch (\Exception $e) {
            $this->logError("Error creating project entry", $e);
        }
    }

    /**
     * Set a project either active or inactive in Project Table
     * 
     * @param int $pid PID of project
     * @param int $active 0 to set inactive, 1 to set active
     * 
     * @return boolean success
     */
    public function setProjectActive(int $pid, int $active) {
        $rcpro_project_id = $this->getProjectIdFromPID($pid);
        $SQL = "UPDATE redcap_external_modules_log_parameters SET value = ? WHERE log_id = ? AND name = 'active'";
        try {
            return $this->query($SQL, [$active, $rcpro_project_id]);
        }
        catch (\Exception $e) {
            $this->logError("Error setting project active status", $e);
        }
    }

    /**
     * Determine whether project exists in Project Table
     * 
     * Optionally additionally tests whether the project is currently active.
     * 
     * @param int $pid - REDCap Project PID
     * @param bool $check_active - Whether or not to additionally check whether active
     * 
     * @return bool
     */
    public function checkProject(int $pid, bool $check_active = FALSE) {
        $SQL = "SELECT active WHERE pid = ? and message = 'PROJECT' and project_id <> FALSE";
        try {
            $result = $this->queryLogs($SQL, [$pid]);
            if ($result->num_rows == 0) {
                return FALSE;
            }
            $row = $result->fetch_assoc();
            return $check_active ? $row["active"] == "1" : TRUE;
        }
        catch (\Exception $e) {
            $this->logError("Error checking project", $e);
        }
    }


    /**
     * Get the project ID corresonding with a REDCap PID
     * 
     * returns null if REDCap project is not associated with REDCapPRO
     * @param int $pid REDCap PID
     * 
     * @return int rcpro project ID associated with the PID
     */
    public function getProjectIdFromPID(int $pid) {
        $SQL = "SELECT log_id WHERE message = 'PROJECT' AND pid = ? AND project_id <> FALSE";
        try {
            $result = $this->queryLogs($SQL, [$pid]);
            return $result->fetch_assoc()["log_id"];
        }
        catch (\Exception $e) {
            $this->logError("Error fetching project id from pid", $e);
        }
    }

    /**
     * Fetch link id given participant and project id's
     * 
     * @param int $rcpro_participant_id
     * @param int $rcpro_project_id
     * 
     * @return int link id
     */
    public function getLinkId(int $rcpro_participant_id, int $rcpro_project_id) {
        $SQL = "SELECT log_id WHERE message = 'LINK' AND rcpro_participant_id = ? AND rcpro_project_id = ? AND project_id <> FALSE";
        try {
            $result = $this->queryLogs($SQL, [$rcpro_participant_id, $rcpro_project_id]);
            return $result->fetch_assoc()["log_id"];
        }
        catch (\Exception $e) {
            $this->logError("Error fetching link id", $e);
        }
    }

    /**
     * Enrolls a participant in a project
     * 
     * @param int $rcpro_participant_id
     * @param int $pid
     * 
     * @return int -1 if already enrolled, bool otherwise
     */
    public function enrollParticipant(int $rcpro_participant_id, int $pid) {
        // If project does not exist, create it.
        if (!$this->checkProject($pid)) {
            $this->addProject($pid);
        }
        $rcpro_project_id = $this->getProjectIdFromPID($pid);

        // Check that user is not already enrolled in this project
        if ($this->participantEnrolled($rcpro_participant_id, $rcpro_project_id)) {
            return -1;
        }

        // If there is already a link between this participant and project,
        // then activate it, otherwise create the link
        if ($this->linkAlreadyExists($rcpro_participant_id, $rcpro_project_id)) {
            $result = $this->setLinkActiveStatus($rcpro_participant_id, $rcpro_project_id, 1);
            if ($result) {
                $this->log("Enrolled Participant", [
                    "rcpro_participant_id" => $rcpro_participant_id,
                    "rcpro_project_id" => $rcpro_project_id
                ]);
            }
            return $result;
        } else {
            return $this->createLink($rcpro_participant_id, $rcpro_project_id);
        }
    }

    /**
     * Creates link between participant and project 
     * 
     * @param int $rcpro_participant_id
     * @param int $rcpro_project_id
     * 
     * @return bool success or failure
     */
    private function createLink(int $rcpro_participant_id, int $rcpro_project_id) {
        try {
            $this->log("LINK", [
                "rcpro_project_id"     => $rcpro_project_id,
                "rcpro_participant_id" => $rcpro_participant_id,
                "active"               => 1
            ]);
            return TRUE;
        }
        catch (\Exception $e) {
            $this->logError("Error enrolling participant", $e);
            return FALSE;
        }
    }

    /**
     * Set a link as active or inactive
     * 
     * @param int $rcpro_participant_id
     * @param int $rcpro_project_id
     * @param int $active                   - 0 for inactive, 1 for active
     * 
     * @return
     */
    private function setLinkActiveStatus(int $rcpro_participant_id, int $rcpro_project_id, int $active) {
        $link_id = $this->getLinkId($rcpro_participant_id, $rcpro_project_id);
        $SQL = "UPDATE redcap_external_modules_log_parameters SET value = ? WHERE log_id = ? AND name = 'active'";
        try {
            return $this->query($SQL, [$active, $link_id]);
        }
        catch (\Exception $e) {
            $this->logError("Error setting link activity", $e);
        }
    }

    /**
     * Checks whether participant is enrolled in given project
     * 
     * @param int $rcpro_participant_id
     * @param int $rcpro_project_id
     * 
     * @return bool
     */
    private function participantEnrolled(int $rcpro_participant_id, int $rcpro_project_id) {
        $SQL = "message = 'LINK' AND rcpro_participant_id = ? AND rcpro_project_id = ? AND active = 1 AND project_id <> FALSE";
        try {
            $result = $this->countLogs($SQL, [$rcpro_participant_id, $rcpro_project_id]);
            return $result > 0;
        }
        catch (\Exception $e) {
            $this->logError("Error checking participant enrollment", $e);
        }
    }

    /**
     * Checks whether a link exists at all between participant and project - whether or not it is active
     * 
     * @param int $rcpro_participant_id
     * @param int $rcpro_project_id
     * 
     * @return bool
     */
    private function linkAlreadyExists(int $rcpro_participant_id, int $rcpro_project_id) {
        $SQL = "message = 'LINK' AND rcpro_participant_id = ? AND rcpro_project_id = ? AND project_id <> FALSE";
        try {
            $result = $this->countLogs($SQL, [$rcpro_participant_id, $rcpro_project_id]);
            return $result > 0;
        }
        catch (\Exception $e) {
            $this->logError("Error checking if link exists", $e);
        }
    }

    /**
     * Removes participant from project.
     * 
     * @param mixed $rcpro_participant_id
     * @param mixed $rcpro_project_id
     * 
     * @return [type]
     */
    public function disenrollParticipant($rcpro_participant_id, $rcpro_project_id) {
        try {
            $result = $this->setLinkActiveStatus($rcpro_participant_id, $rcpro_project_id, 0);
            if ($result) {
                $this->log("Disenrolled Participant", [
                    "rcpro_participant_id" => $rcpro_participant_id,
                    "rcpro_project_id" => $rcpro_project_id
                ]);
            }
            return $result;
        }
        catch (\Exception $e) {
            $this->logError("Error Disenrolling Participant", $e);
        }
    }

    

    /**
     * Create and store token for resetting participant's password
     * 
     * @param mixed $rcpro_participant_id
     * @param int $hours_valid - how long should the token be valid for
     * 
     * @return string token
     */
    public function createResetToken($rcpro_participant_id, int $hours_valid = 1) {
        $token = bin2hex(random_bytes(32));
        $token_ts = time() + ($hours_valid*60*60);
        $SQL1 = "UPDATE redcap_external_modules_log_parameters SET value = ? WHERE log_id = ? AND name = 'token'";
        $SQL2 = "UPDATE redcap_external_modules_log_parameters SET value = ? WHERE log_id = ? AND name = 'token_ts'";
        $SQL3 = "UPDATE redcap_external_modules_log_parameters SET value = 1 WHERE log_id = ? AND name = 'token_valid'";
        try {
            $result1 = $this->query($SQL1, [$token, $rcpro_participant_id]);
            $result2 = $this->query($SQL2, [$token_ts, $rcpro_participant_id]);
            $result3 = $this->query($SQL3, [$rcpro_participant_id]);
            if (!$result1 || !$result2 || !$result3) {
                throw new REDCapProException(["rcpro_participant_id"=>$rcpro_participant_id]);
            }
            return $token;
        }
        catch (\Exception $e) {
            $this->logError("Error creating reset token", $e);
        }
    }

    /**
     * Verify that the supplied password reset token is valid.
     * 
     * @param string $token
     * 
     * @return array with participant id and username
     */
    public function verifyPasswordResetToken(string $token) {
        $SQL = "SELECT log_id, rcpro_username WHERE message = 'PARTICIPANT' AND token = ? AND token_ts > ? AND token_valid = 1 AND project_id <> FALSE";
        try {
            $result = $this->queryLogs($SQL, [$token, time()]);
            if ($result->num_rows > 0) {
                $result_array = $result->fetch_assoc();
                $this->log("Password Token Verified", [
                    'rcpro_participant_id'  => $result_array['log_id'],
                    'rcpro_username' => $result_array['rcpro_username']
                ]);
                return $result_array;
            } 
        }
        catch (\Exception $e) {
            $this->logError("Error verifying password reset token", $e);
        }
    }

    /**
     * Sets the password reset token as invalid/expired
     * 
     * @param mixed $rcpro_participant_id id of participant
     * 
     * @return bool|null success or failure
     */
    public function expirePasswordResetToken($rcpro_participant_id) {
        $SQL = "UPDATE redcap_external_modules_log_parameters SET value = 0 WHERE log_id = ? AND name = 'token_valid'";
        try {
            return $this->query($SQL, [$rcpro_participant_id]);
        }
        catch (\Exception $e) {
            $this->logError("Error expiring password reset token.", $e);
        }
    }

    /**
     * Send an email with a link for the participant to reset their email
     * 
     * @param mixed $rcpro_participant_id
     * 
     * @return void
     */
    public function sendPasswordResetEmail($rcpro_participant_id) {
        try {
            // generate token
            $token    = $this->createResetToken($rcpro_participant_id);
            $to       = $this->getEmail($rcpro_participant_id);
            $username = $this->getUserName($rcpro_participant_id);
            $username_clean = \REDCap::escapeHtml($username);
        
            // create email
            $subject = "REDCapPRO - Password Reset";
            $from = "noreply@REDCapPro.com";
            $body = "<html><body><div>
            <img src='".$this->getUrl("images/RCPro_Logo.svg")."' alt='img' width='500px'><br>
            <p>Hello,
            <br>We have received a request to reset your account password. If you did not make this request, you can ignore this email.<br>
            <br>To reset your password, click the link below.
            <br>This is your username: <strong>${username_clean}</strong><br>
            <br>Click <a href='".$this->getUrl("reset-password.php",true)."&t=${token}'>here</a> to reset your password.
            <br><em>That link is only valid for the next hour. If you need a new link, click <a href='".$this->getUrl("forgot-password.php",true)."'>here</a>.</em>
            </p>
            <br>
            <p>If you have any questions, contact a member of the study team.</p>
            </body></html></div>";

            $result = \REDCap::email($to, $from, $subject, $body);
            $status = $result ? "Sent" : "Failed to send";
            $this->log("Password Reset Email - ${status}", [
                "rcpro_participant_id"  => $rcpro_participant_id,
                "rcpro_username" => $username_clean,
                "rcpro_email"    => $to,
                "redcap_user"    => USERID,
                "redcap_project" => PROJECT_ID
            ]);
        }
        catch (\Exception $e) {
            $this->log("Password Reset Failed", [
                "rcpro_participant_id" => $rcpro_participant_id
            ]);
            $this->logError("Error sending password reset email", $e);
        }
    }

    /**
     * Send new participant email to set password
     * 
     * This acts as an email verification process as well
     * 
     * @param string $username
     * @param string $email
     * @param string $fname
     * @param string $lname
     * 
     * @return bool|NULL
     */
    public function sendNewParticipantEmail(string $username, string $email, string $fname, string $lname) {
        // generate token
        try {
            $rcpro_participant_id = $this->getParticipantIdFromUsername($username);
            $hours_valid          = 24;
            $token                = $this->createResetToken($rcpro_participant_id, $hours_valid);
        
            // create email
            $subject = "REDCapPRO - Account Created";
            $from    = "noreply@REDCapPro.com";
            $body    = "<html><body><div>
            <img src='".$this->getUrl("images/RCPro_Logo.svg")."' alt='img' width='500px'><br>
            <p>Hello ${fname} ${lname},
            <br>An account has been created for you in order to take part in a research study.<br>
            This is your username: <strong>${username}</strong><br>
            Write it down someplace safe, because you will need to know your username to take part in the study.</p>

            <p>To use your account, first you will need to create a password. 
            <br>Click <a href='".$this->getUrl("create-password.php",true)."&t=${token}'>this link</a> to create your password.
            <br>That link will only work for the next $hours_valid hours.
            </p>
            <br>
            <p>If you have any questions, contact a member of the study team.</p>
            </body></html></div>";

            return \REDCap::email($email, $from, $subject, $body);
        }
        catch (\Exception $e) {
            $this->logError("Error sending new user email", $e);
        }
    }

    /**
     * Sends an email that just contains the participant's username.
     * 
     * @param string $email
     * @param string $username
     * 
     * @return bool|NULL success or failure
     */
    public function sendUsernameEmail(string $email, string $username) {
        $subject = "REDCapPRO - Username";
        $from    = "noreply@REDCapPro.com";
        $body    = "<html><body><div>
        <img src='".$this->getUrl("images/RCPro_Logo.svg")."' alt='img' width='500px'><br>
        <p>Hello,</p>
        <p>This is your username: <strong>${username}</strong><br>
        Write it down someplace safe.</p>

        <p>If you did not request this email, please disregard.<br>If you have any questions, contact a member of the study team.</p>
        </body></html></div>";
 
        try {
            return \REDCap::email($email, $from, $subject, $body);
        }
        catch (\Exception $e) {
            $this->log("Error sending username email", $e);
        }

    }
    

    /*-------------------------------------*\
    |             UI FORMATTING             |
    \*-------------------------------------*/

    public function UiShowParticipantHeader(string $title) {
        echo '<!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <title>REDCapPRO '.$title.'</title>
                    <link rel="shortcut icon" href="'.$this->getUrl("images/favicon.ico").'"/>
                    <link rel="icon" type="image/png" sizes="32x32" href="'.$this->getUrl("images/favicon-32x32.png").'">
                    <link rel="icon" type="image/png" sizes="16x16" href="'.$this->getUrl("images/favicon-16x16.png").'">
                    <link rel="stylesheet" href="'.$this->getUrl("lib/bootstrap/css/bootstrap.min.css").'">
                    <script async src="'.$this->getUrl("lib/bootstrap/js/bootstrap.bundle.min.js").'"></script>
                    <style>
                        body { font: 14px sans-serif; }
                        .wrapper { width: 360px; padding: 20px; }
                        .form-group { margin-top: 20px; }
                        .center { display: flex; justify-content: center; align-items: center; }
                        img#rcpro-logo { position: relative; left: -125px; }
                    </style>
                </head>
                <body>
                    <div class="center">
                        <div class="wrapper">
                            <img id="rcpro-logo" src="'.$this->getUrl("images/RCPro_Logo.svg").'" width="500px"></img>
                            <hr>
                            <div style="text-align: center;"><h2>'.$title.'</h2></div>';
    }

    public function UiEndParticipantPage() {
        echo '</div></div></body></html>';
    }

    public function UiShowHeader(string $page) {
        $role = SUPER_USER ? 3 : $this->getUserRole(USERID); // 3=admin/manager, 2=user, 1=monitor, 0=not found
        $header = "
        <style>
            .rcpro-nav a {
                color: #900000 !important;
                font-weight: bold !important;
            }
            .rcpro-nav a.active:hover {
                color: #900000 !important;
                font-weight: bold !important;
                outline: none !important;
            }
            .rcpro-nav a:hover:not(.active), a:focus {
                color: #900000 !important;
                font-weight: bold !important;
                border: 1px solid #c0c0c0 !important;
                background-color: #e1e1e1 !important;
                outline: none !important;
            }
            .rcpro-nav a:not(.active) {
                background-color: #f7f6f6 !important;
                border: 1px solid #e1e1e1 !important;
                outline: none !important;
            }
        </style>
        <link rel='shortcut icon' href='".$this->getUrl('images/favicon.ico')."'/>
        <link rel='icon' type='image/png' sizes='32x32' href='".$this->getUrl('images/favicon-32x32.png')."'>
        <link rel='icon' type='image/png' sizes='16x16' href='".$this->getUrl('images/favicon-16x16.png')."'>
        <div>
            <img src='".$this->getUrl("images/RCPro_Logo.svg")."' width='500px'></img>
            <br>
            <nav style='margin-top:20px;'><ul class='nav nav-tabs rcpro-nav'>
                <li class='nav-item'>
                    <a class='nav-link ".($page==="Home" ? "active" : "")."' aria-current='page' href='".$this->getUrl("home.php")."'>
                    <i class='fas fa-home'></i>
                    Home</a>
                </li>";
        if ($role >= 1) {
            $header .= "<li class='nav-item'>
                            <a class='nav-link ".($page==="Manage" ? "active" : "")."' href='".$this->getUrl("manage.php")."'>
                            <i class='fas fa-users-cog'></i>
                            Manage Participants</a>
                        </li>";
        }
        if ($role >= 2) {
            $header .= "<li class='nav-item'>
                            <a class='nav-link ".($page==="Enroll" ? "active" : "")."' href='".$this->getUrl("enroll.php")."'>
                            <i class='fas fa-user-check'></i>
                            Enroll</a>
                        </li>
                        <li class='nav-item'>
                            <a class='nav-link ".($page==="Register" ? "active" : "")."' href='".$this->getUrl("register.php")."'>
                            <i class='fas fa-id-card'></i>
                            Register</a>
                        </li>";
        }
        if ($role > 2) {
            $header .= "<li class='nav-item'>
                            <a class='nav-link ".($page==="Users" ? "active" : "")."' href='".$this->getUrl("manage-users.php")."'>
                            <i class='fas fa-users'></i>
                            Study Staff</a>
                        </li>";
        }
        $header .= "</ul></nav>
            </div>";
        echo $header;
    }

    public function UiShowControlCenterHeader(string $page) {
        $header = "
        <style>
            .rcpro-nav a {
                color: #900000 !important;
                font-weight: bold !important;
            }
            .rcpro-nav a.active:hover {
                color: #900000 !important;
                font-weight: bold !important;
                outline: none !important;
            }
            .rcpro-nav a:hover:not(.active), a:focus {
                color: #900000 !important;
                font-weight: bold !important;
                border: 1px solid #c0c0c0 !important;
                background-color: #e1e1e1 !important;
                outline: none !important;
            }
            .rcpro-nav a:not(.active) {
                background-color: #f7f6f6 !important;
                border: 1px solid #e1e1e1 !important;
                outline: none !important;
            }
        </style>
        <link rel='shortcut icon' href='".$this->getUrl('images/favicon.ico')."'/>
        <link rel='icon' type='image/png' sizes='32x32' href='".$this->getUrl('images/favicon-32x32.png')."'>
        <link rel='icon' type='image/png' sizes='16x16' href='".$this->getUrl('images/favicon-16x16.png')."'>
        <div>
            <img src='".$this->getUrl("images/RCPro_Logo.svg")."' width='500px'></img>
            <br>
            <nav style='margin-top:20px;'><ul class='nav nav-tabs rcpro-nav'>
                <li class='nav-item'>
                    <a class='nav-link ".($page==="Home" ? "active" : "")."' aria-current='page' href='".$this->getUrl("cc_home.php")."'>
                    <i class='fas fa-home'></i>
                    Home</a>
                </li>
                <li class='nav-item'>
                    <a class='nav-link ".($page==="Manage" ? "active" : "")."' href='".$this->getUrl("cc_manage.php")."'>
                    <i class='fas fa-users-cog'></i>
                    Manage Participants</a>
                </li>
        ";
        
        $header .= "</ul></nav>
        </div><hr style='margin-top:0px;'>";
        echo $header;
    }



    /**
     * Logs errors thrown during operation
     * 
     * @param string $message
     * @param \Exception $e
     * 
     * @return void
     */
    public function logError(string $message, \Exception $e) {
        $params = [
            "error_code"=>$e->getCode(),
            "error_message"=>$e->getMessage(),
            "error_file"=>$e->getFile(),
            "error_line"=>$e->getLine(),
            "error_string"=>$e->__toString()
        ];
        if (isset($e->rcpro)) {
            $params = array_merge($params, $e->rcpro);
        }
        $this->log($message, $params);
    }

    /**
     * Gets the full name of REDCap user with given username
     * 
     * @param string $username
     * 
     * @return string|null Full Name
     */
    public function getUserFullname(string $username) {
        $SQL = 'SELECT CONCAT(user_firstname, " ", user_lastname) AS name FROM redcap_user_information WHERE username = ?';
        try {
            $result = $this->query($SQL, [$username]);
            return $result->fetch_assoc()["name"];
        }
        catch (\Exception $e) {
            $this->logError("Error getting user full name", $e);
        }
    }

    /**
     * Make sure settings meet certain conditions.
     * 
     * This is called when a user clicks "Save" in either system or project
     * configuration.
     * 
     * @param array $settings Array of settings user is trying to set
     * 
     * @return string|null if not null, the error message to show to user
     */
    function validateSettings(array $settings) {

        $managers = $users = $monitors = array();
        $message = NULL;

        // project-level settings
        if ($this->getProjectId()) {
            if (count($settings["managers"]) > 0) {
                foreach ($settings["managers"] as $i=>$manager) {
                    if (in_array($manager, $managers)) {
                        $message = "This user ($manager) is already a manager";
                    }
                    array_push($managers, $manager);
                }
            }
            if (count($settings["users"]) > 0) {
                foreach ($settings["users"] as $i=>$user) {
                    if (in_array($user, $users)) {
                        $message = "This user ($user) is already a user";
                    }
                    array_push($users, $user);
                    if (in_array($user, $managers)) {
                        $message = "This user ($user) cannot have multiple roles";
                    }
                }
            }
            if (count($settings["monitors"]) > 0) {
                foreach ($settings["monitors"] as $i=>$monitor) {
                    if (in_array($monitor, $monitors)) {
                        $message = "This user ($monitor) is already a monitor";
                    }
                    array_push($monitors, $monitor);
                    if (in_array($monitor, $managers) || in_array($monitor, $users)) {
                        $message = "This user ($monitor) cannot have multiple roles";
                    }
                    
                }
            }
        }
        return $message;
    }
}

/**
 * Authorization class
 */
class Auth {

    public static $APPTITLE;

    public function yell() {
        echo "Loaded Auth for: ".self::$APPTITLE;
    }
    
    function __construct($title = null) {
        self::$APPTITLE = $title;
    }


    public function init() {
        $session_id = $_COOKIE["survey"] ?? $_COOKIE["PHPSESSID"];
        if (!empty($session_id)) {
            session_id($session_id);
        } else {
            self::createSession();
        }
        session_start();
    }

    function createSession() {
        \Session::init();
        self::set_csrf_token();
    }

    function set_csrf_token() {
        $_SESSION[self::$APPTITLE."_token"] = bin2hex(random_bytes(24));
    }

    function get_csrf_token() {
        return $_SESSION[self::$APPTITLE."_token"];
    }

    function validate_csrf_token(string $token) {
        return hash_equals(self::get_csrf_token(), $token);
    }

}

class REDCapProException extends \Exception {
    public $rcpro = NULL; 
    public function __construct($rcpro = NULL) {
        $this->rcpro = $rcpro;
    }
}

?>