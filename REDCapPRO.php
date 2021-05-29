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

        //$this->dropTable("USER");
        //$this->createTable("USER");
        //$this->createUser("andrew.poppe@yale.edu", password_hash("Password1!", PASSWORD_DEFAULT), "Andrew", "Poppe");

        //$TT = password_hash("Password1!", PASSWORD_DEFAULT);
        //$this->updatePassword($TT, 3, TRUE);

        if (isset($_SESSION[$this::$APPTITLE."_loggedin"]) && $_SESSION[$this::$APPTITLE."_loggedin"] === true) {

            if (isset($_SESSION[$this::$APPTITLE."_temp_pw"]) && $_SESSION[$this::$APPTITLE."_temp_pw"] === 1) {
                // Need to set password
                header("location: ".$this->getUrl("reset-password.php", true));
                $this->exitAfterHook();
            }

            \REDCap::logEvent("REDCapPro Survey User Login", "user: ".$_SESSION[$this::$APPTITLE."_username"], NULL, $record, $event_id);
            $this->log("REDCapPro Survey User Login", ["user"=>$_SESSION[$this::$APPTITLE."_username"], "id"=>$_SESSION[$this::$APPTITLE."_user_id"]]);
            return;
        } else {
            $_SESSION[$this::$APPTITLE."_survey_url"] = APP_PATH_SURVEY_FULL."?s=${survey_hash}";
            \Session::savecookie($this::$APPTITLE."_survey_url", APP_PATH_SURVEY_FULL."?s=${survey_hash}", 0, TRUE);
            header("location: ".$this->getUrl("login.php", true)."&s=${survey_hash}");
            $this->exitAfterHook();
        }

    }

    public function createSession() {
        //session_destroy();
        //session_start();
        \Session::init();
        \Session::savecookie($this::$APPTITLE."_sessid", session_id(), 0, TRUE);
        $_SESSION[$this::$APPTITLE."_token"] = bin2hex(random_bytes(24));
    }

    public function validateToken(string $token) {
        return hash_equals($_SESSION[$this::$APPTITLE."_token"], $token);
    }

    public function incrementFailedLogin(int $uid) {
        $USER_TABLE = $this->getTable("USER");
        $SQL = "UPDATE ${USER_TABLE} SET failed_attempts=failed_attempts+1 WHERE id=?;";
        try {
            $res = $this->query($SQL, [$uid]);
            return $res;
        }
        catch (\Exception $e) {
            return;
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
        } else {
            $ipStat["attempts"] = 1;
        }
        $ipLockouts[$ip] = $ipStat;
        $this->setSystemSetting('ip_lockouts', json_encode($ipLockouts));
        return $ipStat["attempts"];
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

    private function checkUsernameAttempts(int $uid) {
        $USER_TABLE = $this->getTable("USER");
        $SQL = "SELECT failed_attempts FROM ${USER_TABLE} WHERE id=?;";
        try {
            $res = $this->query($SQL, [$uid]);
            return $res->fetch_assoc["failed_attempts"];
        }
        catch (\Exception $e) {
            return 0;
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
            lockout_ts TIMESTAMP
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
     * @param bool $oneTime Whether or not this is a one-time use temporary password
     * 
     * @return void
     */
    public function updatePassword(string $hash, $uid, bool $oneTime = FALSE) {
        $USER_TABLE = $this->getTable("USER");
        $temp_pw = ($oneTime === TRUE) ? 1 : 0;
        $SQL = "UPDATE ${USER_TABLE} SET pw = ?, temp_pw = ? WHERE id = ?;";
        var_dump($SQL);

        try {
            $res = $this->query($SQL, [$hash, $temp_pw, $uid]);
            var_dump($res);
            $ok = $this->query("SELECT * FROM ${USER_TABLE}", []);
            while($row = $ok->fetch_assoc()) {
                var_dump($row);
            }
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
     * @return void
     */
    public function createUser(string $email, string $pw_hash, string $fname, string $lname) {
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
        $SQL = "INSERT INTO ".$this::$TABLES["USER"]." (username, email, pw, fname, lname) VALUES (?, ?, ?, ?, ?)";
        try {
            $result = $this->query($SQL, [$username, $email, $pw_hash, $fname, $lname]);
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


    public function addProject($pid) {
        $PROJECT_TABLE = $this->getTable("PROJECT");
        $TESTPROJECTSQL = "INSERT INTO ".$PROJECT_TABLE." (pid) VALUES (?)";
        try {
            $this->query($TESTPROJECTSQL, [$pid]);
        }
        catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    public function checkProject($pid, $check_active = FALSE) {
        $PROJECT_TABLE = $this->getTable("PROJECT");
        $TESTPROJECTSQL = "SELECT * FROM ".$PROJECT_TABLE." WHERE pid = ?";
        try {
            $result = $this->query($TESTPROJECTSQL, [$pid]);
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

    public function sendPasswordResetEmail($user_id) {
        return true;
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
        <div>
            <img src='".$this->getUrl("images/REDCapPROLOGO_4.png")."' width='500px'></img>
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
                        </li>";
        }
        $header .= "</ul></nav>
            </div>";
        echo $header;
    }




}
