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
        "ID" => 1000,
        "PW" => "TEST_USER_PW_1000",
        "PID" => -1
    );

    private function createUserTable() {
        $USER_TABLE = $this->getTable("USER");
        $USERSQL = "CREATE TABLE ".$USER_TABLE." (
            id int NOT NULL PRIMARY KEY AUTO_INCREMENT,
            email VARCHAR(50) NOT NULL UNIQUE, 
            fname VARCHAR(50) NOT NULL, 
            lname VARCHAR(50) NOT NULL, 
            pw VARCHAR(512) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_modified_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
            temp_pw INT(1) DEFAULT 1
        ) AUTO_INCREMENT = 1000;";
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
            id INT NOT NULL,
            project INT NOT NULL REFERENCES ".$PROJECT_TABLE."(id),
            user INT NOT NULL REFERENCES ".$USER_TABLE."(id),
            record_id VARCHAR(50),
            PRIMARY KEY (user, project)
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
            $TESTUSERSQL = "INSERT INTO ".$USER_TABLE." (id, pw, email) VALUES (?, ?)";
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
        $PROJECT_TABLE = $this->getTable("PROJECT");

        $TEST_PID = $this::$TEST_DATA["PID"];
        $TEST_USER = $this::$TEST_DATA["ID"];
        
        $GETPROJECTSQL = "SELECT id FROM ${PROJECT_TABLE} WHERE pid = ?;";
        $TESTLINKSQL = "INSERT INTO ".$LINK_TABLE." 
        (project, user, record_id) VALUES (?, ?, ?)";
        try {
            $projectResult = $this->query($GETPROJECTSQL, [$TEST_PID]);
            $proj_id = $projectResult->fetch_assoc()["id"];
            $this->query($TESTLINKSQL, [$proj_id, $TEST_USER, "20"]);
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

    private function getTable(string $TYPE) {
        return $this::$TABLES[$TYPE];
    }


    public function getHash(string $uid) {
        $res = $this->query('SELECT pw FROM '.$this::$TABLES["USER"].' WHERE id = ?', [$uid]);
        return $res->fetch_assoc()['pw'];
    }

    public function createUser(string $email, string $pw_hash) {
        $SQL = "INSERT INTO ".$this::$TABLES["USER"]." (email, pw) VALUES (?, ?)";
        $this->query($SQL, [$email, $pw_hash]);
    }

    public function checkEmail(string $email) {
        $USER_TABLE = $this->getTable("USER");
        $sql = "SELECT id FROM " .$USER_TABLE. " WHERE email = ?";
        try {
            $result = $this->query($sql, [$email]);
            return $result->num_rows;
        } 
        catch (\Exception $e) {
            return;
        }
    }

}
