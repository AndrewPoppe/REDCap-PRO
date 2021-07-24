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
        $role = SUPER_USER ? 3 : $this->getUserRole(USERID); // 3=admin/manager, 2=monitor, 1=user, 0=not found
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
                echo "<p>You are not currently enrolled in this study.<br>Please contact the study representative.</p>";
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
     * @param int $rcpro_user_id - id key into user table
     * 
     * @return BOOL|NULL
     */
    public function incrementFailedLogin(int $rcpro_user_id) {
        $USER_TABLE = $this->getTable("USER");
        $SQL = "UPDATE ${USER_TABLE} SET failed_attempts=failed_attempts+1 WHERE id=?;";
        try {
            $res = $this->query($SQL, [$rcpro_user_id]);
            
            // Lockout username if necessary
            $this->lockoutLogin($rcpro_user_id);
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
     * @param int $rcpro_user_id - id key into user table
     * 
     * @return BOOL|NULL
     */
    private function lockoutLogin(int $rcpro_user_id) {
        $USER_TABLE = $this->getTable("USER");
        $SQL = "SELECT failed_attempts FROM ${USER_TABLE} WHERE id = ?;";
        try {
            $res = $this->query($SQL, [$rcpro_user_id]);
            $attempts = $res->fetch_assoc()["failed_attempts"];
            if ($attempts >= $this::$LOGIN_ATTEMPTS) {
                $SQL2 = "UPDATE ${USER_TABLE} SET lockout_ts = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id = ?;";
                $res2 = $this->query($SQL2, [$this::$LOCKOUT_DURATION_SECONDS, $rcpro_user_id]);
                $status = $res2 ? "Successful" : "Failed";
                $this->log("Login Lockout ${status}", [
                    "rcpro_user_id" => $rcpro_user_id
                ]);
                return $res2;
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
     * @param int $rcpro_user_id - id key into user table
     * 
     * @return [type]
     */
    public function resetFailedLogin(int $rcpro_user_id) {
        $USER_TABLE = $this->getTable("USER");
        $SQL = "UPDATE ${USER_TABLE} SET failed_attempts=0 WHERE id=?;";
        try {
            return $this->query($SQL, [$rcpro_user_id]);
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
     * @param int $rcpro_user_id - id key into user table
     * 
     * @return int number of failed login attempts
     */
    private function checkUsernameAttempts(int $rcpro_user_id) {
        $USER_TABLE = $this->getTable("USER");
        $SQL = "SELECT failed_attempts FROM ${USER_TABLE} WHERE id=?;";
        try {
            $res = $this->query($SQL, [$rcpro_user_id]);
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
     * @param int $rcpro_user_id - id key into user table
     * 
     * @return bool whether user is locked out
     */
    public function checkUsernameLockedOut(int $rcpro_user_id) {
        $USER_TABLE = $this->getTable("USER");
        $SQL = "SELECT UNIX_TIMESTAMP(lockout_ts) - UNIX_TIMESTAMP(NOW()) as lockout_ts FROM ${USER_TABLE} WHERE id=? AND lockout_ts >= NOW();";
        try {
            $res = $this->query($SQL, [$rcpro_user_id]);
            return $res->fetch_assoc()["lockout_ts"];
        }
        catch (\Exception $e) {
            $this->logError("Failed to check username lockout", $e);
            return FALSE;
        }
    }

    /**
     * Returns number of consecutive failed login attempts
     * 
     * This checks both by username and by ip, and returns the larger
     * 
     * @param int|null $rcpro_user_id
     * @param mixed $ip
     * 
     * @return int number of consecutive attempts
     */
    public function checkAttempts($rcpro_user_id, $ip) {
        if ($rcpro_user_id === null) {
            $usernameAttempts = 0;
        } else {
            $usernameAttempts = $this->checkUsernameAttempts($rcpro_user_id);
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
        $monitors = $this->getProjectSetting("monitors");
        $users    = $this->getProjectSetting("users");

        $result = 0;

        if (in_array($username, $managers)) {
            $result = 3;
        } else if (in_array($username, $monitors)) {
            $result = 2;
        } else if (in_array($username, $users)) {
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
            "2" => $this->getProjectSetting("monitors"),
            "1" => $this->getProjectSetting("users")
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
        $this->setProjectSetting("monitors", $roles["2"]);
        $this->setProjectSetting("users", $roles["1"]);
    }



    /*
        Instead of creating a user table, we'll use the built-in log table (and log parameters table)

        So, there will be a message called "PARTICIPANT" 
        The log_id will be the id of the participant (rcpro_participant_id)
        The log's timestamp will act as the creation time
        and the parameters will be:
            * username              - the coded username for this participant
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
     * Get table name given a type of table
     * 
     * @param string $TYPE USER, PROJECT, or LINK
     * 
     * @return string Table name
     */
    public function getTable(string $TYPE) {
        return $this::$TABLES[$TYPE];
    }


    /**
     * Get hashed password for participant.
     * 
     * @param string $uid ID (not username) of user 
     * 
     * @return string hashed password
     */
    public function getHash(string $rcpro_participant_id) {
        try {
            $res = $this->query("SELECT pw FROM ${USER_TABLE} WHERE id = ?", [$rcpro_participant_id]);
            return $res->fetch_assoc()['pw'];
        }
        catch (\Exception $e) {
            return;
        }
    }

    public function storeHash(string $hash, $uid) {
        $USER_TABLE = $this->getTable("USER");
        try {
            $res = $this->query("UPDATE ${USER_TABLE} SET pw = ? WHERE id = ?", [$hash, $uid]);
            return $res;
        }
        catch (\Exception $e) {
            return;
        }
    }


    /**
     * @param string $hash Password hash to store
     * @param mixed $uid ID (not username) of participant
     * 
     * @return void
     */
    public function updatePassword(string $hash, $uid) {
        $USER_TABLE = $this->getTable("USER");
        $SQL = "UPDATE ${USER_TABLE} SET pw = ? WHERE id = ?;";
        try {
            $res = $this->query($SQL, [$hash, $uid]);
            $this->log("Password Updated", [
                "rcpro_user_id" => $uid
            ]);
            return $res;
        }
        catch (\Exception $e) {
            $this->logError('Password Update Failed', $e);
            return NULL;
        }
    }

    /**
     * Adds user entry into table.
     * 
     * In so doing, it creates a unique username.
     * 
     * @param string $email Email Address
     * @param string $pw_hash Hashed password
     * @param string $fname First Name
     * @param string $lname Last Name
     * 
     * @return string username of newly created user
     */
    public function createUser(string $email, string $fname, string $lname) {
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
            return;
        }
        $SQL = "INSERT INTO ".$this::$TABLES["USER"]." (username, email, fname, lname) VALUES (?, ?, ?, ?)";
        try {
            $result = $this->query($SQL, [$username, $email_clean, $fname_clean, $lname_clean]);
            if (!$result) {
                throw new \Exception("Failed to create new participant.");
            }
            $SQL2 = "SELECT id FROM ".$this::$TABLES['USER']." WHERE username = ?";
            $result2 = $this->query($SQL2, $username);
            $id = $result2->fetch_assoc()["id"];
            $this->log("Participant Created", [
                "rcpro_user_id"  => $id,
                "rcpro_username" => $username
            ]);
            return $username;
        }
        catch (\Exception $e) {
            $this->logError("Participant Creation Failed", $e);
            return NULL;
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
     * @return boolean True if taken, False if free 
     */
    public function usernameIsTaken(string $username) {
        $USER_TABLE = $this->getTable("USER");
        $SQL        = "SELECT id FROM ${USER_TABLE} WHERE username = ?";
        try {
            $result = $this->query($SQL, [$username]);
            return $result->num_rows > 0;
        }
        catch (\Exception $e) {
            $this->log($e->getMessage());
        }
    }


    public function getUser(string $username) {
        if ($username === NULL) {
            return;
        }
        $USER_TABLE = $this->getTable("USER");
        $SQL = "SELECT id, username, email, fname, lname, temp_pw FROM ${USER_TABLE} WHERE username = ?";
        try {
            $result = $this->query($SQL, [$username]);
            return $result->fetch_assoc();
        }
        catch (\Exception $e) {
            $this->log($e->getMessage());
        }
    }

    public function getUserName($user_id) {
        $USER_TABLE = $this->getTable("USER");
        $SQL = "SELECT username FROM ${USER_TABLE} WHERE id = ?";
        try {
            $result = $this->query($SQL, [$user_id]);
            return $result->fetch_assoc()["username"];
        }
        catch (\Exception $e) {
            $this->log($e->getMessage());
        }
    }

    public function getUserIdFromUsername($username) {
        $USER_TABLE = $this->getTable("USER");
        $SQL = "SELECT id FROM ${USER_TABLE} WHERE username = ?";
        try {
            $result = $this->query($SQL, [$username]);
            return $result->fetch_assoc()["id"];
        }
        catch (\Exception $e) {
            $this->log($e->getMessage());
        }
    }

    public function getUserIdFromEmail($email) {
        $USER_TABLE = $this->getTable("USER");
        $SQL = "SELECT id FROM ${USER_TABLE} WHERE email = ?";
        try {
            $result = $this->query($SQL, [$email]);
            return $result->fetch_assoc()["id"];
        }
        catch (\Exception $e) {
            $this->log($e->getMessage());
        }
    }

    public function getEmail($user_id) {
        $USER_TABLE = $this->getTable("USER");
        $SQL = "SELECT email FROM ${USER_TABLE} WHERE id = ?";
        try {
            $result = $this->query($SQL, [$user_id]);
            return $result->fetch_assoc()["email"];
        }
        catch (\Exception $e) {
            $this->log($e->getMessage());
        }
    }


    public function getAllParticipants() {
        $USER_TABLE = $this->getTable("USER");
        
        // Grab all user IDs
        $SQL = "SELECT id,username,email,fname,lname,lockout_ts FROM ${USER_TABLE};";
        try {
            $result = $this->query($SQL, []);
            $participants  = array();

            // grab participant details
            while ($row = $result->fetch_assoc()) {
                $participants[$row["id"]] = $row;               
            }
            return $participants;
        }
        catch (\Exception $e) {
            $this->log($e->getMessage());
            return;
        }
    }


    /**
     * get array of enrolled participants given a project id
     * 
     * @param string $proj_id Project ID (not REDCap PID!)
     * 
     * @return [type]
     */
    public function getProjectParticipants(string $proj_id) {
        $LINK_TABLE = $this->getTable("LINK");
        $USER_TABLE = $this->getTable("USER");

        // Grab all user IDs
        $SQL = "SELECT user FROM ${LINK_TABLE} WHERE project = ?;";
        try {
            $result = $this->query($SQL, [$proj_id]);
            $participants  = array();

            // TODO: rename "USER" table ... it holds participants...
            // grab participant details
            while ($row = $result->fetch_assoc()) {
                $participantSQL = "SELECT id,username,email,fname,lname,temp_pw,lockout_ts FROM ${USER_TABLE} WHERE id = ?;";
                $participantResult = $this->query($participantSQL, [$row["user"]]);
                $participant = $participantResult->fetch_assoc();
                $participants[$row["user"]] = $participant;               
            }
            return $participants;
        }
        catch (\Exception $e) {
            $this->log($e->getMessage());
            return;
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
        $USER_TABLE = $this->getTable("USER");
        $SQL = "SELECT id FROM " .$USER_TABLE. " WHERE email = ?";
        try {
            $result = $this->query($SQL, [$email]);
            return $result->num_rows > 0;
        } 
        catch (\Exception $e) {
            return;
        }
    }


    /**
     * Determines whether the current participant is enrolled in the project
     * 
     * @param mixed $user_id User ID (not username, not record ID)
     * @param mixed $pid PID of REDCap project
     * 
     * @return boolean TRUE if the participant is enrolled
     */
    public function enrolledInProject($user_id, $pid) {
        $LINK_TABLE = $this->getTable("LINK");
        $project_id = $this->getProjectId($pid);
        $SQL = "SELECT id from ${LINK_TABLE} WHERE project = ? AND user = ?;";
        try {
            $result = $this->query($SQL, [$project_id, $user_id]);
            return $result->num_rows > 0;
        }
        catch (\Exception $e) {
            return;
        }
    }


    public function addProject($pid) {
        $PROJECT_TABLE = $this->getTable("PROJECT");
        $SQL = "INSERT INTO ".$PROJECT_TABLE." (pid) VALUES (?)";
        try {
            return $this->query($SQL, [$pid]);
        }
        catch (\Exception $e) {
            $this->log($e->getMessage());
        }
    }

    /**
     * Set a project either active or inactive in Project Table
     * 
     * @param int $pid PID of project
     * @param int $active 0 to set inactive, 1 to set active
     * 
     * @return 
     */
    public function setProjectActive(int $pid, int $active) {
        $PROJECT_TABLE = $this->getTable("PROJECT");
        $SQL = "UPDATE ${PROJECT_TABLE} SET active=? WHERE pid=?;";
        try {
            return $this->query($SQL, [$active, $pid]);
        }
        catch (\Exception $e) {
            $this->log($e->getMessage());
        }
    }

    /**
     * Determine whether project exists in Project Table
     * 
     * Optionally additionally tests whether the project is currently active.
     * 
     * @param mixed $pid
     * @param  $check_active
     * 
     * @return bool
     */
    public function checkProject($pid, $check_active = FALSE) {
        $PROJECT_TABLE = $this->getTable("PROJECT");
        $SQL = "SELECT * FROM ${PROJECT_TABLE} WHERE pid = ?";
        try {
            $result = $this->query($SQL, [$pid]);
            while ($row = $result->fetch_assoc()) {
                if ($check_active) {
                    return $row["active"] == "1";
                } else {
                    return TRUE;
                }
            }
            return FALSE;
        }
        catch (\Exception $e) {
            $this->log($e->getMessage());
            return;
        }
    }


    /**
     * Get the project ID corresonding with a REDCap PID
     * 
     * returns null if REDCap project is not associated with REDCapPRO
     * @param mixed $pid REDCap PID
     * 
     * @return int project ID associated with the PID
     */
    public function getProjectId($pid) {
        $PROJECT_TABLE = $this->getTable("PROJECT");
        $SQL = "SELECT id FROM " .$PROJECT_TABLE. " WHERE pid = ?";
        try {
            $result = $this->query($SQL, [$pid]);
            return $result->fetch_assoc()["id"];
        }
        catch (\Exception $e) {
            $this->log($e->getMessage());
            return;
        }
    }

    public function getLinkId($user_id, $proj_id) {
        $LINK_TABLE = $this->getTable("LINK");
        $SQL = "SELECT id FROM ${LINK_TABLE} WHERE user = ? AND project = ?;";
        try {
            $result = $this->query($SQL, [$user_id, $proj_id]);
            return $result->fetch_assoc()["id"];
        }
        catch (\Exception $e) {
            $this->log($e->getMessage());
            return;
        }
    }

    /**
     * @param mixed $user_id
     * @param mixed $pid
     * 
     * @return [type]
     */
    public function enrollParticipant($user_id, $pid) {
        // If project does not exist, create it.
        if (!$this->checkProject($pid)) {
            $this->addProject($pid);
        }
        $proj_id = $this->getProjectID($pid);

        // Check that user is not already enrolled in this project
        if ($this->userEnrolled($user_id, $proj_id)) {
            return -1;
        }

        return $this->createLink($user_id, $proj_id);
    }

    /**
     * @param mixed $user_id
     * @param mixed $proj_id
     * 
     * @return [type]
     */
    private function createLink($user_id, $proj_id) {
        $LINK_TABLE = $this->getTable("LINK");
        $SQL = "INSERT INTO ".$LINK_TABLE." 
        (user, project) VALUES (?, ?)";
        try {
            $this->query($SQL, [$user_id, $proj_id]);
            $this->log("Participant Enrolled", [
                "rcpro_proj_id" => $proj_id,
                "rcpro_user_id" => $user_id
            ]);
            return TRUE;
        }
        catch (\Exception $e) {
            $this->logError("Participant Enrollment Failed", $e);
            return NULL;
        }
    }

    private function userEnrolled($user_id, $proj_id) {
        $LINK_TABLE = $this->getTable("LINK");
        $SQL = "SELECT * FROM ${LINK_TABLE} WHERE user = ? AND project = ?;";
        try {
            $result = $this->query($SQL, [$user_id, $proj_id]);
            return $result->num_rows > 0;
        }
        catch (\Exception $e) {
            return;
        }
    }

    public function createResetToken($user_id, int $hours_valid = 1) {
        $USER_TABLE = $this->getTable("USER");
        $token = bin2hex(random_bytes(32));
        $SQL = "UPDATE ${USER_TABLE} SET token=?,token_ts=DATE_ADD(NOW(), INTERVAL ${hours_valid} HOUR),token_valid=1 WHERE id=?;";
        try {
            $result = $this->query($SQL, [$token,$user_id]);
            if ($result) {
                return $token;
            } 
        }
        catch (\Exception $e) {
            return;
        }
    }

    /**
     * @param mixed $token
     * 
     * @return [type]
     */
    public function verifyPasswordResetToken($token) {
        $USER_TABLE = $this->getTable("USER");
        $SQL = "SELECT id, username FROM ${USER_TABLE} WHERE token = ? AND token_ts > NOW() AND token_valid = 1;";
        try {
            $result = $this->query($SQL, [$token]);
            $result_array = $result->fetch_assoc();
            $this->log("Password Token Verified", [
                'rcpro_user_id'  => $result_array['id'],
                'rcpro_username' => $result_array['username']
            ]);
            return $result_array;
        }
        catch (\Exception $e) {
            $this->logError("Password Token Verification Failed", $e);
            return NULL;
        }
    }

    /**
     * @param mixed $user_id id of participant
     * 
     * @return bool|null
     */
    public function expirePasswordResetToken($user_id) {
        $USER_TABLE = $this->getTable("USER");
        $SQL = "UPDATE ${USER_TABLE} SET token=NULL,token_ts=DATE_SUB(NOW(), INTERVAL 1 HOUR), token_valid=0 WHERE id=?;";
        try {
            return $this->query($SQL, [$user_id]);
        }
        catch (\Exception $e) {
            $this->log("Error 100 - User id: ${user_id}");
            $this->logError("Problem expiring password reset token.", $e);
            return NULL;
        }
    }

    public function sendPasswordResetEmail($user_id) {
        try {
            // generate token
            $token    = $this->createResetToken($user_id);
            $to       = $this->getEmail($user_id);
            $username = $this->getUserName($user_id);
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
                "rcpro_user_id"  => $user_id,
                "rcpro_username" => $username_clean,
                "rcpro_email"    => $to,
                "redcap_user"    => USERID,
                "redcap_project" => PROJECT_ID
            ]);
        }
        catch (\Exception $e) {
            $this->log("Password Reset Failed", [
                "rcpro_user_id" => $user_id
            ]);
            $this->logError("Password Reset Error", $e);
            return false;
        }
    }

    public function sendNewUserEmail($username, $email, $fname, $lname) {
        // generate token
        try {
            $user_id     = $this->getUserIdFromUsername($username);
            $hours_valid = 24;
            $token       = $this->createResetToken($user_id, $hours_valid);
        }
        catch (\Exception $e) {
            return false;
        }
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

        try {
            $result = \REDCap::email($email, $from, $subject, $body);
            return $result;
        }
        catch (\Exception $e) {
            return false;
        }
    }

    public function sendUsernameEmail($email, $username) {
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
            $result = \REDCap::email($email, $from, $subject, $body);
            return $result;
        }
        catch (\Exception $e) {
            $this->log($e->getMessage());
            return false;
        }

    }
    
    public function disenrollParticipant($user_id, $proj_id) {
        $LINK_TABLE = $this->getTable("LINK");
        $link_id    = $this->getLinkId($user_id, $proj_id);
        $SQL        = "DELETE FROM ${LINK_TABLE} WHERE id = ?;";
        try {
            $result = $this->query($SQL, [$link_id]);
            $status = $result ? "Successful" : "Failed";
            $this->log("Disenrolled Participant - ${status}", [
                "rcpro_user_id" => $user_id,
                "rcpro_project_id" => $proj_id,
                "rcpro_link_id" => $link_id,
                "redcap_user" => USERID,
                "redcap_project" => PROJECT_ID
            ]);
            return $result;
        }
        catch (\Exception $e) {
            $this->logError("Disenrolled Participant - Error", $e);
            return NULL;
        }
    }

    



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
        $role = $this->getUserRole(USERID); // 3=admin/manager, 2=monitor, 1=user, 0=not found
        if (SUPER_USER) {
            $role = 3;
        }
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

    public function getUserFullname($username) {
        $SQL = 'SELECT CONCAT(user_firstname, " ", user_lastname) AS name FROM redcap_user_information WHERE username = ?';
        try {
            $result = $this->query($SQL, [$username]);
            return $result->fetch_assoc()["name"];
        }
        catch (\Exception $e) {
            $this->log($e->getMessage());
            return;
        }
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
        $this->log($message, [
            "error_code"=>$e->getCode(),
            "error_message"=>$e->getMessage(),
            "error_file"=>$e->getFile(),
            "error_line"=>$e->getLine(),
            "error_string"=>$e->__toString()
        ]);
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

class TestHelper {

    private static $TEST_DATA = array(
        "USER" => 1000,
        "PW"   => "TEST_USER_PW_1000",
        "PID"  => -1
    );

    private static $module;

    function __construct($module = null) {
        self::$module = $module;
    }

    /**
     * Create a test record in the USER table
     * 
     * @return void
     */
    private function createTestUser() {
        try {
            $USER_TABLE  = self::$module->getTable("USER");
            $TESTUSERSQL = "INSERT INTO ".$USER_TABLE." (username, pw) VALUES (?, ?)";
            $TEST_USER   = self::$TEST_DATA["USER"];
            $pw_hash     = password_hash(self::$TEST_DATA["PW"], PASSWORD_DEFAULT);
            self::$module->query($TESTUSERSQL, [$TEST_USER, $pw_hash]);
        }
        catch (\Exception $e) {
            self::$module->log($e->getMessage());
        }
    }

    /**
     * Create a test record in the PROJECT table
     * 
     * @return void
     */
    private function createTestProject() {
        $PROJECT_TABLE  = self::$module->getTable("PROJECT");
        $TEST_PID       = self::$TEST_DATA["PID"];
        $TESTPROJECTSQL = "INSERT INTO ".$PROJECT_TABLE." (pid) VALUES (?)";
        try {
            self::$module->query($TESTPROJECTSQL, [$TEST_PID]);
        }
        catch (\Exception $e) {
            self::$module->log($e->getMessage());
        }
    }
    
    /**
     * Create a test record in the LINK table
     * 
     * @return void
     */
    private function createTestLink() {
        $LINK_TABLE    = self::$module->getTable("LINK");
        $USER_TABLE    = self::$module->getTable("USER");
        $PROJECT_TABLE = self::$module->getTable("PROJECT");

        $TEST_PID  = self::$TEST_DATA["PID"];
        $TEST_USER = self::$TEST_DATA["USER"];
        
        $GETPROJECTSQL = "SELECT id FROM ${PROJECT_TABLE} WHERE pid = ?;";
        $GETUSERSQL    = "SELECT id FROM ${USER_TABLE} WHERE username = ?;";

        $TESTLINKSQL = "INSERT INTO ".$LINK_TABLE." 
        (project, user, record_id) VALUES (?, ?, ?)";
        try {
            $projectResult = self::$module->query($GETPROJECTSQL, [$TEST_PID]);
            $proj_id       = $projectResult->fetch_assoc()["id"];
            $userResult    = self::$module->query($GETUSERSQL, [$TEST_USER]);
            $user_id       = $userResult->fetch_assoc()["id"];
            self::$module->query($TESTLINKSQL, [$proj_id, $user_id, "20"]);
        }
        catch (\Exception $e) {
            self::$module->log($e->getMessage());
        }
    }

    /**
     * Create a test record in the given table.
     * 
     * @param string $TYPE USER, PROJECT, or LINK
     * 
     * @return void
     */
    public function createTestData(string $TYPE) {
        if ($TYPE === "USER") {
            self::$module->createTestUser();
        } else if ($TYPE === "PROJECT") {
            self::$module->createTestProject();
        } else if ($TYPE === "LINK") {
            self::$module->createTestLink();
        }
    }
}

?>