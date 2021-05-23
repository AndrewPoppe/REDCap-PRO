<?php
namespace YaleREDCap\REDCapPRO;


use ExternalModules\AbstractExternalModule;

/**
 * 
 */
class REDCapPRO extends AbstractExternalModule {

    //public static $USER_TABLE       = "REDCAP_PRO_USER";
    //public static $PROJECT_TABLE    = "REDCAP_PRO_PROJECT";
    //public static $LINK_TABLE       = "REDCAP_PRO_LINK";
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

    private function createUserTable() {
        $USER_TABLE = $this->getTable("USER");
        $USERSQL = "CREATE TABLE ".$USER_TABLE." (
            id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
            username INT NOT NULL UNIQUE,
            email VARCHAR(50) NOT NULL UNIQUE, 
            fname VARCHAR(50) NOT NULL, 
            lname VARCHAR(50) NOT NULL, 
            pw VARCHAR(512) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_modified_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
            temp_pw INT(1) DEFAULT 1
        )";
        try {
            $this->query($USERSQL, []);
        }
        catch (\Exception $e) {
            echo $e->getMessage();
        }
    } 

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

    public function createTable(string $TYPE) {
        if ($TYPE === "USER") {
            $this->createUserTable();
        } else if ($TYPE === "PROJECT") {
            $this->createProjectTable();
        } else if ($TYPE === "LINK") {
            $this->createLinkTable();
        }
    }

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

    public function createTestData(string $TYPE) {
        if ($TYPE === "USER") {
            $this->createTestUser();
        } else if ($TYPE === "PROJECT") {
            $this->createTestProject();
        } else if ($TYPE === "LINK") {
            $this->createTestLink();
        }
    }


    public function tableExists(string $TYPE) {
        $TABLE = $this->getTable($TYPE);
        $res = $this->query('SHOW TABLES LIKE "'.$TABLE.'"', []);
        return $res->num_rows > 0;
    }

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
    private function getTable(string $TYPE) {
        return $this::$TABLES[$TYPE];
    }

    /**
     * Get hashed password from USER database.
     * 
     * @param string $uid ID (not username) of user 
     * 
     * @return string hashed password
     */
    public function getHash(string $uid) {
        $res = $this->query('SELECT pw FROM '.$this::$TABLES["USER"].' WHERE id = ?', [$uid]);
        return $res->fetch_assoc()['pw'];
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
        while ($this->usernameIsTaken($username) && $counter < 90000000) {
            $username = $this->createUsername();
            $counter++;
        }
        $SQL = "INSERT INTO ".$this::$TABLES["USER"]." (username, email, pw, fname, lname) VALUES (?, ?, ?, ?, ?)";
        $this->query($SQL, [$username, $email, $pw_hash, $fname, $lname]);
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
    private function usernameIsTaken(string $username) {
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

    /**
     * Determine whether email address already exists in database
     * 
     * @param string $email
     * 
     * @return boolean True if email already exists, False if not
     */
    public function checkEmailExists(string $email) {
        $USER_TABLE = $this->getTable("USER");
        $sql = "SELECT id FROM " .$USER_TABLE. " WHERE email = ?";
        try {
            $result = $this->query($sql, [$email]);
            return $result->num_rows > 0;
        } 
        catch (\Exception $e) {
            return;
        }
    }

}
