<?php

namespace YaleREDCap\REDCapPRO;

use Exception;

/**
 * Authorization class
 */
class Auth
{

    public static $APPTITLE;
    public static $SESSION_NAME = "REDCapPRO_SESSID";

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
        // To ensure any REDCap user is logged out 
        $redcap_session_id = $_COOKIE["PHPSESSID"];
        if (isset($redcap_session_id)) {
            \Session::destroy($redcap_session_id);
            \Session::deletecookie("PHPSESSID");
            session_destroy($redcap_session_id);
        }

        // If we already have a session, use it.
        // Otherwise, create a new session.
        $session_id = $_COOKIE[self::$SESSION_NAME];
        if (!empty($session_id)) {
            if ($session_id !== session_id()) {
                \Session::destroy(session_id());
                session_destroy();
            }
            session_id($session_id);
        } else {
            \Session::destroy(session_id());
            session_destroy();
            $this->createSession();
        }

        session_name(self::$SESSION_NAME);
        session_start();

        $this->set_survey_username($_SESSION["username"]);
    }

    public function createSession()
    {
        \Session::init(self::$SESSION_NAME);
        $this->set_csrf_token();
    }

    public function destroySession()
    {
        $session_id = $_COOKIE[self::$SESSION_NAME];
        if (isset($session_id)) {
            \Session::destroy($session_id);
        }
        \Session::deletecookie(self::$SESSION_NAME);
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

    public function set_login_values($participant)
    {

        $this->set_survey_username($participant["rcpro_username"]);

        $_SESSION["username"] = $participant["rcpro_username"];
        $_SESSION[self::$APPTITLE . "_participant_id"] = $participant["log_id"];
        $_SESSION[self::$APPTITLE . "_username"] = $participant["rcpro_username"];
        $_SESSION[self::$APPTITLE . "_email"] = $participant["email"];
        $_SESSION[self::$APPTITLE . "_fname"] = $participant["fname"];
        $_SESSION[self::$APPTITLE . "_lname"] = $participant["lname"];
        $_SESSION[self::$APPTITLE . "_loggedin"] = true;
    }

    public function set_survey_username($username)
    {
        $orig_id = session_id();
        $survey_session_id = $_COOKIE["survey"];
        if (isset($survey_session_id)) {
            session_write_close();
            session_name('survey');
            session_id($survey_session_id);
            session_start();
            $_SESSION['username'] = $username;
            session_write_close();
            session_name(self::$SESSION_NAME);
            session_id($orig_id);
            session_start();
        }
    }
}
