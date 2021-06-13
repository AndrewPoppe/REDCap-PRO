<?php
namespace YaleREDCap\REDCapPRO;


use ExternalModules\AbstractExternalModule;

/**
 * Main EM Class
 */
class REDCapPRO extends AbstractExternalModule {

    private static $TABLES = array(
        "USER" => "REDCAP_PRO_USER",
        "PROJECT" => "REDCAP_PRO_PROJECT",
        "LINK" => "REDCAP_PRO_LINK"
    );

    private static $TEST_DATA = array(
        "USER" => 1000,
        "PW" => "TEST_USER_PW_1000",
        "PID" => -1
    );

    static $APPTITLE = "REDCapPRO";
    static $LOGIN_ATTEMPTS = 3;
    static $LOCKOUT_DURATION_SECONDS = 60;

    function redcap_every_page_top($project_id) {
        $role = $this->getUserRole(USERID); // 3=admin/manager, 2=monitor, 1=user, 0=not found
        if (SUPER_USER) {
            $role = 3;
        }
        if ($role > 0) {
            ?>
            <script>
                setTimeout(function() {
                    let link = $(`<div>
                        <img src="<?=$this->getUrl('images/fingerprint_2.png');?>" style="width:16px; height:16px; position:relative; top:-2px"></img>
                        <a href="<?=$this->getUrl('home.php');?>" target="" data-link-key="redcap_pro-redcappro"><span id="RCPro-Link"><strong><font style="color:black;">REDCap</font><em><font style="color:#900000;">PRO</font></em></strong></span></a>
                    </div>`);
                    $('#app_panel').find('div.hang').last().after(link);
                }, 100);
            </script>
            <?php
        }
    }

    function redcap_survey_page_top($project_id, $record, $instrument, 
    $event_id, $group_id, $survey_hash, $response_id, $repeat_instance) {
        $session_id = $_COOKIE[$this::$APPTITLE."_sessid"] ?? $_COOKIE["survey"] ?? $_COOKIE["PHPSESSID"];
        if (!empty($session_id)) {
            session_id($session_id);
        }
        session_start();
        if (!isset($_SESSION[$this::$APPTITLE."_loggedin"]) || $_SESSION[$this::$APPTITLE."_loggedin"] !== TRUE) {
            $this->createSession();
        }

        
        
        /*$this->dropTable("USER");
        $this->createTable("USER");
        $this->createUser("andrew.poppe@yale.edu", "Andrew", "Poppe");

        $this->dropTable("LINK");
        $this->createTable("LINK");*/
        


        // Participant is logged in to their account
        if (isset($_SESSION[$this::$APPTITLE."_loggedin"]) && $_SESSION[$this::$APPTITLE."_loggedin"] === true) {

            /*if (isset($_SESSION[$this::$APPTITLE."_temp_pw"]) && $_SESSION[$this::$APPTITLE."_temp_pw"] === 1) {
                // Need to set password
                header("location: ".$this->getUrl("reset-password.php", true));
                $this->exitAfterHook();
            }*/

            // Determine whether participant is enrolled in the study.
            $user_id = $_SESSION[$this::$APPTITLE."_user_id"];
            if (!$this->enrolledInProject($user_id, $project_id)) {
                $this->UiShowParticipantHeader("Not Enrolled");
                echo "<p>You are not currently enrolled in this study.<br>Please contact the study representative.</p>";
                $this->exitAfterHook();
            }

            \REDCap::logEvent("REDCapPro Survey User Login", "user: ".$_SESSION[$this::$APPTITLE."_username"], NULL, $record, $event_id);
            $this->log("REDCapPro Survey User Login", ["user"=>$_SESSION[$this::$APPTITLE."_username"], "id"=>$_SESSION[$this::$APPTITLE."_user_id"]]);
            return;

        // Participant is not logged into their account
        // Store cookie to return to survey
        } else {
            $_SESSION[$this::$APPTITLE."_survey_url"] = APP_PATH_SURVEY_FULL."?s=${survey_hash}";
            \Session::savecookie($this::$APPTITLE."_survey_url", APP_PATH_SURVEY_FULL."?s=${survey_hash}", 0, TRUE);
            $_SESSION[$this::$APPTITLE."_survey_link_active"] = TRUE;
            header("location: ".$this->getUrl("login.php", true)."&s=${survey_hash}");
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
        $this->initTables();
    }

    /**
     * Hook that is triggered when a module is disabled in Control Center
     * 
     * @param mixed $version
     * 
     * @return void
     */
    function redcap_module_system_disable($version) {
        // Do what?
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

    public function createSession() {
        \Session::init();
        $this->set_csrf_token();
    }

    public function set_csrf_token() {
        $_SESSION[$this::$APPTITLE."_token"] = bin2hex(random_bytes(24));
    }

    public function get_csrf_token() {
        return $_SESSION[$this::$APPTITLE."_token"];
    }

    public function validate_csrf_token(string $token) {
        return hash_equals($this->get_csrf_token(), $token);
    }

    public function incrementFailedLogin(int $uid) {
        $USER_TABLE = $this->getTable("USER");
        $SQL = "UPDATE ${USER_TABLE} SET failed_attempts=failed_attempts+1 WHERE id=?;";
        try {
            $res = $this->query($SQL, [$uid]);
            
            // Lockout username if necessary
            $this->lockoutLogin($uid);

            return $res;
        }
        catch (\Exception $e) {
            return;
        }
    }

    private function lockoutLogin(int $uid) {
        $USER_TABLE = $this->getTable("USER");
        $SQL = "SELECT failed_attempts FROM ${USER_TABLE} WHERE id = ?;";
        try {
            $res = $this->query($SQL, [$uid]);
            $attempts = $res->fetch_assoc()["failed_attempts"];
            if ($attempts >= $this::$LOGIN_ATTEMPTS) {
                $SQL2 = "UPDATE ${USER_TABLE} SET lockout_ts = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id = ?;";
                $res2 = $this->query($SQL2, [$this::$LOCKOUT_DURATION_SECONDS, $uid]);
                return $res2;
            } else {
                return TRUE;
            }
        }
        catch (\Exception $e) {
            return FALSE;
        }
    }

    public function resetFailedLogin(int $uid) {
        $USER_TABLE = $this->getTable("USER");
        $SQL = "UPDATE ${USER_TABLE} SET failed_attempts=0 WHERE id=?;";
        try {
            $res = $this->query($SQL, [$uid]);
            return $res;
        }
        catch (\Exception $e) {
            return;
        }
    }

    public function getIPAddress() {
        $ip = $_SERVER['REMOTE_ADDR'];   
        /*if(!empty($_SERVER['HTTP_CLIENT_IP'])) {  
            $ip = $_SERVER['HTTP_CLIENT_IP'];  
        } 
        
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {  
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];  
        } else {  
             
        }*/  
        return $ip;  
    }  

    /**
     * @param mixed $ip Client IP address
     * 
     * @return int number of attempts INCLUDING current attempt
     */
    public function incrementFailedIp($ip) {
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
            }
        } else {
            $ipStat["attempts"] = 1;
        }
        $ipLockouts[$ip] = $ipStat;
        $this->setSystemSetting('ip_lockouts', json_encode($ipLockouts));
        return $ipStat["attempts"];
    }

    public function resetFailedIp($ip) {
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
            $this->log("ERROR: ".$e->getMessage());
            return FALSE;
        }
    }

    private function checkIpAttempts($ip) { 
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

    public function checkIpLockedOut($ip) {
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

    private function checkUsernameAttempts(int $uid) {
        $USER_TABLE = $this->getTable("USER");
        $SQL = "SELECT failed_attempts FROM ${USER_TABLE} WHERE id=?;";
        try {
            $res = $this->query($SQL, [$uid]);
            return $res->fetch_assoc()["failed_attempts"];
        }
        catch (\Exception $e) {
            return 0;
        }
    }

    public function checkUsernameLockedOut(int $uid) {
        $USER_TABLE = $this->getTable("USER");
        $SQL = "SELECT UNIX_TIMESTAMP(lockout_ts) - UNIX_TIMESTAMP(NOW()) as lockout_ts FROM ${USER_TABLE} WHERE id=? AND lockout_ts >= NOW();";
        try {
            $res = $this->query($SQL, [$uid]);
            return $res->fetch_assoc()["lockout_ts"];
        }
        catch (\Exception $e) {
            return FALSE;
        }
    }

    /**
     * Returns number of consecutive failed login attempts
     * 
     * This checks both by username and by ip, and returns the larger
     * 
     * @param int|null $uid
     * @param mixed $ip
     * 
     * @return int number of consecutive attempts
     */
    public function checkAttempts($uid, $ip) {
        if ($uid === null) {
            $usernameAttempts = 0;
        } else {
            $usernameAttempts = $this->checkUsernameAttempts($uid);
        }
        $ipAttempts = $this->checkIpAttempts($ip);
        return max($usernameAttempts, $ipAttempts);
    }

    // SETTINGS

    public function getUserRole($username) {
        $managers = $this->getProjectSetting("managers");
        $monitors = $this->getProjectSetting("monitors");
        $users = $this->getProjectSetting("users");

        if (in_array($username, $managers)) {
            return 3;
        } else if (in_array($username, $monitors)) {
            return 2;
        } else if (in_array($username, $users)) {
            return 1;
        } else {
            return 0;
        }
    }



    /**
     * Create a table of the USER Type
     * 
     * @return void
     */
    private function createUserTable() {
        $USER_TABLE = $this->getTable("USER");
        $USERSQL = "CREATE TABLE ".$USER_TABLE." (
            id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(20) NOT NULL UNIQUE,
            email VARCHAR(50) NOT NULL UNIQUE, 
            fname VARCHAR(50) NOT NULL, 
            lname VARCHAR(50) NOT NULL, 
            pw VARCHAR(512) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_modified_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
            temp_pw INT(1) DEFAULT 1,
            failed_attempts int DEFAULT 0,
            lockout_ts TIMESTAMP,
            token VARCHAR(128),
            token_ts TIMESTAMP,
            token_valid INT(1) DEFAULT 0
        )";
        try {
            $this->query($USERSQL, []);
        }
        catch (\Exception $e) {
            echo $e->getMessage();
        }
    } 

    /**
     * Create a table of the PROJECT Type
     * 
     * @return void
     */
    private function createProjectTable() {
        $PROJECT_TABLE = $this->getTable("PROJECT");
        $PROJECTSQL = "CREATE TABLE ".$PROJECT_TABLE." (
            id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
            pid INT NOT NULL,
            active INT(1) DEFAULT 1
        )";
        try {
            $this->query($PROJECTSQL, []);
        }
        catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    /**
     * Create a table of the LINK Type
     * 
     * @return void
     */
    private function createLinkTable() {
        $LINK_TABLE = $this->getTable("LINK");
        $PROJECT_TABLE = $this->getTable("PROJECT");
        $USER_TABLE = $this->getTable("USER");
        $LINKSQL = "CREATE TABLE ".$LINK_TABLE." (
            id INT PRIMARY KEY AUTO_INCREMENT,
            project INT NOT NULL REFERENCES ".$PROJECT_TABLE."(id),
            user INT NOT NULL REFERENCES ".$USER_TABLE."(id),
            record_id VARCHAR(50)
        )";
        try {
            $this->query($LINKSQL, []);
        }
        catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    /**
     * Create a table of the given type
     * 
     * @param string $TYPE USER, PROJECT, or LINK
     * 
     * @return void
     */
    public function createTable(string $TYPE) {
        if ($TYPE === "USER") {
            $this->createUserTable();
        } else if ($TYPE === "PROJECT") {
            $this->createProjectTable();
        } else if ($TYPE === "LINK") {
            $this->createLinkTable();
        }
    }

    /**
     * Create a test record in the USER table
     * 
     * @return void
     */
    private function createTestUser() {
        try {
            $USER_TABLE = $this->getTable("USER");
            $TESTUSERSQL = "INSERT INTO ".$USER_TABLE." (username, pw) VALUES (?, ?)";
            $TEST_USER = $this::$TEST_DATA["USER"];
            $pw_hash = password_hash($this::$TEST_DATA["PW"], PASSWORD_DEFAULT);
            $this->query($TESTUSERSQL, [$TEST_USER, $pw_hash]);
        }
        catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    /**
     * Create a test record in the PROJECT table
     * 
     * @return void
     */
    private function createTestProject() {
        $PROJECT_TABLE = $this->getTable("PROJECT");
        $TEST_PID = $this::$TEST_DATA["PID"];
        $TESTPROJECTSQL = "INSERT INTO ".$PROJECT_TABLE." (pid) VALUES (?)";
        try {
            $this->query($TESTPROJECTSQL, [$TEST_PID]);
        }
        catch (\Exception $e) {
            echo $e->getMessage();
        }
    }
    
    /**
     * Create a test record in the LINK table
     * 
     * @return void
     */
    private function createTestLink() {
        $LINK_TABLE = $this->getTable("LINK");
        $USER_TABLE = $this->getTable("USER");
        $PROJECT_TABLE = $this->getTable("PROJECT");

        $TEST_PID = $this::$TEST_DATA["PID"];
        $TEST_USER = $this::$TEST_DATA["USER"];
        
        $GETPROJECTSQL = "SELECT id FROM ${PROJECT_TABLE} WHERE pid = ?;";
        $GETUSERSQL = "SELECT id FROM ${USER_TABLE} WHERE username = ?;"; 

        $TESTLINKSQL = "INSERT INTO ".$LINK_TABLE." 
        (project, user, record_id) VALUES (?, ?, ?)";
        try {
            $projectResult = $this->query($GETPROJECTSQL, [$TEST_PID]);
            $proj_id = $projectResult->fetch_assoc()["id"];
            $userResult = $this->query($GETUSERSQL, [$TEST_USER]);
            $user_id = $userResult->fetch_assoc()["id"];
            $this->query($TESTLINKSQL, [$proj_id, $user_id, "20"]);
        }
        catch (\Exception $e) {
            echo $e->getMessage();
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
            $this->createTestUser();
        } else if ($TYPE === "PROJECT") {
            $this->createTestProject();
        } else if ($TYPE === "LINK") {
            $this->createTestLink();
        }
    }


    /**
     * Determine whether table of given type already exists.
     * 
     * @param string $TYPE USER, PROJECT, or LINK
     * 
     * @return boolean True if it exists, False if not
     */
    public function tableExists(string $TYPE) {
        $TABLE = $this->getTable($TYPE);
        $res = $this->query('SHOW TABLES LIKE "'.$TABLE.'"', []);
        return $res->num_rows > 0;
    }

    /**
     * Remove a table.
     * 
     * This shouldn't be needed much.
     * 
     * @param string $TYPE USER, PROJECT, or LINK
     * 
     * @return void
     */
    public function dropTable(string $TYPE) {
        $TABLE = $this->getTable($TYPE);
        $SQL = "DROP TABLE ".$TABLE.";";
        $this->query($SQL, []);
    }

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

    public function initTables() {
        $types = ["USER", "PROJECT", "LINK"];
        foreach ($types as $type) {
            if (!$this->tableExists($type)) {
                $this->createTable($type);
            }
        }
    }




    /**
     * Get hashed password from USER database.
     * 
     * @param string $uid ID (not username) of user 
     * 
     * @return string hashed password
     */
    public function getHash(string $uid) {
        $USER_TABLE = $this->getTable("USER");
        try {
            $res = $this->query("SELECT pw FROM ${USER_TABLE} WHERE id = ?", [$uid]);
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
     * @param mixed $uid ID (not username) of user
     * 
     * @return void
     */
    public function updatePassword(string $hash, $uid) {
        $USER_TABLE = $this->getTable("USER");
        $SQL = "UPDATE ${USER_TABLE} SET pw = ? WHERE id = ?;";
        try {
            $res = $this->query($SQL, [$hash, $uid]);
            return $res;
        }
        catch (\Exception $e) {
            return;
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
        $username = $this->createUsername();
        $counter = 0;
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
            $result = $this->query($SQL, [$username, $email, $fname, $lname]);
            return $username;
        }
        catch (\Exception $e) {
            return;
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
        $SQL = "SELECT id FROM ${USER_TABLE} WHERE username = ?";
        try {
            $result = $this->query($SQL, [$username]);
            return $result->num_rows > 0;
        }
        catch (\Exception $e) {
            echo $e->getMessage();
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
            echo $e->getMessage();
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
            echo $e->getMessage();
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
            echo $e->getMessage();
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
            echo $e->getMessage();
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
            echo $e->getMessage();
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
            echo $e->getMessage();
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
            echo $e->getMessage();
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
            echo $e->getMessage();
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
            echo $e->getMessage();
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
            echo $e->getMessage();
            return;
        }
    }

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

    private function createLink($user_id, $proj_id) {
        $LINK_TABLE = $this->getTable("LINK");
        $SQL = "INSERT INTO ".$LINK_TABLE." 
        (user, project) VALUES (?, ?)";
        try {
            $this->query($SQL, [$user_id, $proj_id]);
            return TRUE;
        }
        catch (\Exception $e) {
            echo $e->getMessage();
            return;
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

    public function verifyPasswordResetToken($token) {
        $USER_TABLE = $this->getTable("USER");
        $SQL = "SELECT id, username FROM ${USER_TABLE} WHERE token = ? AND token_ts > NOW() AND token_valid = 1;";
        try {
            $result = $this->query($SQL, [$token]);
            $user = $result->fetch_assoc();
            return $user;
        }
        catch (\Exception $e) {
            echo "Oops, there was a problem. Try again later.<br>";
            echo $e->getMessage();
            return;
        }
    }

    public function expirePasswordResetToken($user_id) {
        $USER_TABLE = $this->getTable("USER");
        $SQL = "UPDATE ${USER_TABLE} SET token=NULL,token_ts=DATE_SUB(NOW(), INTERVAL 1 HOUR), token_valid=0 WHERE id=?;";
        try {
            return $this->query($SQL, [$user_id]);
        }
        catch (\Exception $e) {
            $this->log("Error 100 - User id: ${user_id}");
            echo "Problem updating user.";
            return;
        }
    }

    public function sendPasswordResetEmail($user_id) {
        // generate token
        try {
            $token = $this->createResetToken($user_id);
            $to = $this->getEmail($user_id);
            $username = $this->getUserName($user_id);
        }
        catch (\Exception $e) {
            return false;
        }
        // create email
        $subject = "REDCapPRO - Password Reset";
        $from = "noreply@REDCapPro.com";
        $body = "<html><body><div>
        <img src='".$this->getUrl("images/RCPro_Logo.svg")."' alt='img' width='500px'><br>
        This is your username: ${username}<br>
        Click <a href='".$this->getUrl("reset-password.php",true)."&t=${token}'>here</a> to set/reset your password.
        </body></html></div>";

        try {
            $result = \REDCap::email($to, $from, $subject, $body);
            return $result;
        }
        catch (\Exception $e) {
            return false;
        }
    }

    public function sendNewUserEmail($username, $email, $fname, $lname) {
        // generate token
        try {
            $user_id = $this->getUserIdFromUsername($username);
            $hours_valid = 24;
            $token = $this->createResetToken($user_id, $hours_valid);
        }
        catch (\Exception $e) {
            return false;
        }
        // create email
        $subject = "REDCapPRO - Account Created";
        $from = "noreply@REDCapPro.com";
        $body = "<html><body><div>
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
    
    public function disenrollParticipant($user_id, $proj_id) {
        $LINK_TABLE = $this->getTable("LINK");
        $link_id = $this->getLinkId($user_id, $proj_id);
        $SQL = "DELETE FROM ${LINK_TABLE} WHERE id = ?;";
        try {
            $result = $this->query($SQL, [$link_id]);
            return $result;
        }
        catch (\Exception $e) {
            return;
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
                    <script src="'.$this->getUrl("lib/bootstrap/js/bootstrap.bundle.min.js").'"></script>
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
                            <h2>'.$title.'</h2>';
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
            <hr>
            <nav><ul class='nav nav-tabs rcpro-nav'>
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
                        </li>
                        <li class='nav-item'>
                            <a class='nav-link ".($page==="Users" ? "active" : "")."' href='".$this->getUrl("manage-users.php")."'>
                            <i class='fas fa-users'></i>
                            Study Staff</a>
                        </li>";
        }
        $header .= "</ul></nav>
            </div>";
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


}
