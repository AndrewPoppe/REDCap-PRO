<?php

namespace YaleREDCap\REDCapPRO;

use Exception;

/**
 * Authorization class
 */
class Auth
{

    public static $APPTITLE;

    /**
     * constructor
     * 
     * @param mixed|null $title Title of EM
     * @return void 
     */
    function __construct($title = null)
    {
        self::$APPTITLE = $title;
    }

    /**
     * 
     * @return void 
     * @throws Exception 
     */
    public function init()
    {
        $session_id = $_COOKIE["survey"] ?? $_COOKIE["PHPSESSID"];
        if (!empty($session_id)) {
            session_id($session_id);
        } else {
            $this->createSession();
        }
        session_start();
    }

    public function createSession()
    {
        \Session::init();
        $this->set_csrf_token();
    }

    public function set_csrf_token()
    {
        $_SESSION[self::$APPTITLE . "_token"] = bin2hex(random_bytes(24));
    }

    public function get_csrf_token()
    {
        return $_SESSION[self::$APPTITLE . "_token"];
    }

    public function validate_csrf_token(string $token)
    {
        return hash_equals($this->get_csrf_token(), $token);
    }

    // --- THESE DEAL WITH SESSION VALUES --- \\

    // TESTS
    public function is_logged_in()
    {
        return isset($_SESSION[self::$APPTITLE . "_loggedin"]) && $_SESSION[self::$APPTITLE . "_loggedin"] === true;
    }

    public function is_survey_url_set()
    {
        return isset($_SESSION[self::$APPTITLE . "_survey_url"]);
    }

    public function is_survey_link_active()
    {
        return $_SESSION[self::$APPTITLE . "_survey_link_active"];
    }

    // GETS

    public function get_survey_url()
    {
        return $_SESSION[self::$APPTITLE . "_survey_url"];
    }

    public function get_participant_id()
    {
        return $_SESSION[self::$APPTITLE . "_participant_id"];
    }

    public function get_username()
    {
        return $_SESSION[self::$APPTITLE . "_username"];
    }

    // SETS

    public function deactivate_survey_link()
    {
        unset($_SESSION[self::$APPTITLE . "_survey_link_active"]);
    }

    /**
     * 
     * @param mixed $url 
     * @return void 
     */
    public function set_survey_url($url)
    {
        $_SESSION[self::$APPTITLE . "_survey_url"] = $url;
    }

    public function set_survey_active_state($state)
    {
        $_SESSION[self::$APPTITLE . "_survey_link_active"] = $state;
    }

    public function set_login_values(Participant $participant)
    {
        $_SESSION["username"] = $participant->rcpro_username;
        $_SESSION[self::$APPTITLE . "_participant_id"] = $participant->rcpro_participant_id;
        $_SESSION[self::$APPTITLE . "_username"] = $participant->rcpro_username;
        $_SESSION[self::$APPTITLE . "_email"] = $participant->email;
        $name = $participant->getName();
        $_SESSION[self::$APPTITLE . "_fname"] = $name["fname"];
        $_SESSION[self::$APPTITLE . "_lname"] = $name["lname"];
        $_SESSION[self::$APPTITLE . "_loggedin"] = true;
    }
}
