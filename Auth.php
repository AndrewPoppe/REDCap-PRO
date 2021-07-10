<?php
namespace YaleREDCap\REDCapPRO;

/**
 * Authorization class
 */
class Auth {

    private $APPTITLE;

    public function yell() {
        echo 'LOADED';
    }
    
    function __construct($title = null) {
        $this->APPTITLE = $title;
    }


    public function init() {
        $session_id = $_COOKIE["survey"] ?? $_COOKIE["PHPSESSID"];
        if (!empty($session_id)) {
            session_id($session_id);
        } else {
            $this->createSession();
        }
        session_start();
    }

    private function createSession() {
        \Session::init();
        $this->set_csrf_token();
    }

    private function set_csrf_token() {
        $_SESSION[$this::$APPTITLE."_token"] = bin2hex(random_bytes(24));
    }

    private function get_csrf_token() {
        return $_SESSION[$this::$APPTITLE."_token"];
    }

    private function validate_csrf_token(string $token) {
        return hash_equals($this->get_csrf_token(), $token);
    }

}

?>