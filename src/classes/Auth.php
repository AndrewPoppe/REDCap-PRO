<?php

namespace YaleREDCap\REDCapPRO;

use Exception;

/**
 * Authorization class
 */
class Auth
{

    public $APPTITLE;
    public $SESSION_NAME = "REDCapPRO_SESSID";

    /**
     * constructor
     * 
     * @param mixed|null $title Title of EM
     * @return void 
     */
    function __construct($title = null)
    {
        $this->APPTITLE = $title;
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
        if ( isset($redcap_session_id) ) {
            \Session::destroy($redcap_session_id);
            \Session::deletecookie("PHPSESSID");
            session_destroy();
        }

        // If we already have a session, use it.
        // Otherwise, create a new session.
        $session_id = $_COOKIE[$this->SESSION_NAME];
        if ( !empty($session_id) ) {
            if ( $session_id !== session_id() ) {
                \Session::destroy(session_id());
                session_destroy();
            }
            session_id($session_id);
        } else {
            \Session::destroy(session_id());
            session_destroy();
            $this->createSession();
        }

        session_name($this->SESSION_NAME);
        session_start();

        $this->set_survey_username($_SESSION["username"]);
        $this->set_survey_record();
    }

    public function createSession()
    {
        \Session::init($this->SESSION_NAME);
    }

    public function destroySession()
    {
        $session_id = $_COOKIE[$this->SESSION_NAME];
        if ( isset($session_id) ) {
            \Session::destroy($session_id);
        }
        \Session::deletecookie($this->SESSION_NAME);
    }

    // --- THESE DEAL WITH SESSION VALUES --- \\

    // TESTS
    public function is_logged_in()
    {
        return isset($_SESSION[$this->APPTITLE . "_loggedin"]) && $_SESSION[$this->APPTITLE . "_loggedin"] === true;
    }

    public function is_survey_url_set()
    {
        return isset($_SESSION[$this->APPTITLE . "_survey_url"]);
    }

    public function is_survey_link_active()
    {
        return $_SESSION[$this->APPTITLE . "_survey_link_active"];
    }

    // GETS

    public function get_survey_url()
    {
        return $_SESSION[$this->APPTITLE . "_survey_url"];
    }

    public function get_participant_id()
    {
        return $_SESSION[$this->APPTITLE . "_participant_id"];
    }

    public function get_username()
    {
        return $_SESSION[$this->APPTITLE . "_username"];
    }

    public function get_redcap_project_id()
    {
        return $_SESSION[$this->APPTITLE . "_redcap_project_id"];
    }

    public function get_data_access_group_id()
    {
        return $_SESSION[$this->APPTITLE . "_data_access_group_id"];
    }

    // SETS

    public function deactivate_survey_link()
    {
        unset($_SESSION[$this->APPTITLE . "_survey_link_active"]);
    }

    public function set_redcap_project_id($project_id)
    {
        $_SESSION[$this->APPTITLE . "_redcap_project_id"] = $project_id;
    }

    public function set_data_access_group_id($dag_id)
    {
        $_SESSION[$this->APPTITLE . "_data_access_group_id"] = $dag_id;
    }

    /**
     * 
     * @param mixed $url 
     * @return void 
     */
    public function set_survey_url($url)
    {
        $_SESSION[$this->APPTITLE . "_survey_url"] = $url;
    }

    public function set_survey_active_state($state)
    {
        $_SESSION[$this->APPTITLE . "_survey_link_active"] = $state;
    }

    public function set_login_values($participant)
    {

        $this->set_survey_username($participant["rcpro_username"]);
        $this->set_survey_record();
        $_SESSION["username"]                          = $participant["rcpro_username"];
        $_SESSION[$this->APPTITLE . "_participant_id"] = $participant["log_id"];
        $_SESSION[$this->APPTITLE . "_username"]       = $participant["rcpro_username"];
        $_SESSION[$this->APPTITLE . "_email"]          = $participant["email"];
        $_SESSION[$this->APPTITLE . "_fname"]          = $participant["fname"];
        $_SESSION[$this->APPTITLE . "_lname"]          = $participant["lname"];
        $_SESSION[$this->APPTITLE . "_loggedin"]       = true;
    }

    public function set_survey_username($username)
    {
        $orig_id           = session_id();
        $survey_session_id = $_COOKIE["survey"];
        if ( isset($survey_session_id) ) {
            session_write_close();
            session_name('survey');
            session_id($survey_session_id);
            session_start();
            $_SESSION['username'] = $username;
            session_write_close();
            session_name($this->SESSION_NAME);
            session_id($orig_id);
            session_start();
        }
    }

    public function set_survey_record()
    {
        $record = $_COOKIE["record"];
        if ( !isset($record) ) return;


        $orig_id           = session_id();
        $survey_session_id = $_COOKIE["survey"];
        if ( isset($survey_session_id) ) {
            session_write_close();
            session_name('survey');
            session_id($survey_session_id);
            session_start();
            $_SESSION['record'] = $record;
            session_write_close();
            session_name($this->SESSION_NAME);
            session_id($orig_id);
            session_start();
        }
    }
}