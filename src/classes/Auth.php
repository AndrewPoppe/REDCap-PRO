<?php

namespace YaleREDCap\REDCapPRO;

/**
 * Authorization class
 */
class Auth
{
    private $module;
    private $APPTITLE;
    private $TOKEN_COOKIE;
    private $LOGGED_IN_COOKIE;
    private $SURVEY_URL_COOKIE;
    private $SURVEY_ACTIVE_COOKIE;
    private $PARTICIPANT_ID_COOKIE;
    private $USERNAME_COOKIE;
    private $EMAIL_COOKIE;
    private $FNAME_COOKIE;
    private $LNAME_COOKIE;

    /**
     * constructor
     * 
     * @param mixed|null $title Title of EM
     * @return void 
     */
    function __construct(REDCapPRO $module)
    {
        $this->module                = $module;
        $this->APPTITLE              = $this->module::$APPTITLE;
        $this->TOKEN_COOKIE          = $this->APPTITLE . "_token";
        $this->LOGGED_IN_COOKIE      = $this->APPTITLE . "_loggedin";
        $this->SURVEY_URL_COOKIE     = $this->APPTITLE . "_survey_url";
        $this->SURVEY_ACTIVE_COOKIE  = $this->APPTITLE . "_survey_link_active";
        $this->PARTICIPANT_ID_COOKIE = $this->APPTITLE . "_participant_id";
        $this->USERNAME_COOKIE       = $this->APPTITLE . "_username";
        $this->EMAIL_COOKIE          = $this->APPTITLE . "_email";
        $this->FNAME_COOKIE          = $this->APPTITLE . "_fname";
        $this->LNAME_COOKIE          = $this->APPTITLE . "_lname";
    }

    /**
     * Initiate a session, create one if one doesn't exist
     */
    public function init(): void
    {
        $session_id = $_COOKIE["survey"] ?? $_COOKIE["PHPSESSID"];
        if (!empty($session_id)) {
            session_id($session_id);
        } else {
            $this->create_session();
        }
        session_start();
    }

    public function create_session(): void
    {
        \Session::init();
        $this->set_csrf_token();
    }

    public function set_csrf_token(): void
    {
        $_SESSION[$this->TOKEN_COOKIE] = bin2hex(random_bytes(24));
    }

    public function validate_csrf_token(string $token): bool
    {
        return hash_equals($this->get_csrf_token(), $token);
    }

    public function get_csrf_token(): string
    {
        return $_SESSION[$this->TOKEN_COOKIE];
    }

    public function is_logged_in(): bool
    {
        return isset($_SESSION[$this->LOGGED_IN_COOKIE]) && $_SESSION[$this->LOGGED_IN_COOKIE] === true;
    }

    public function is_survey_url_set(): bool
    {
        return isset($_SESSION[$this->SURVEY_URL_COOKIE]);
    }

    public function is_survey_link_active(): bool
    {
        return $_SESSION[$this->SURVEY_ACTIVE_COOKIE];
    }

    public function get_survey_url(): string
    {
        return $_SESSION[$this->SURVEY_URL_COOKIE];
    }

    public function get_participant_id(): string
    {
        return $_SESSION[$this->PARTICIPANT_ID_COOKIE];
    }

    public function get_username(): string
    {
        return $_SESSION[$this->USERNAME_COOKIE];
    }

    public function set_survey_link(string $url): void
    {
        $_SESSION[$this->SURVEY_URL_COOKIE] = $url;
    }

    public function deactivate_survey_link(): void
    {
        unset($_SESSION[$this->SURVEY_ACTIVE_COOKIE]);
    }

    public function activate_survey_link(): void
    {
        $_SESSION[$this->SURVEY_ACTIVE_COOKIE] = true;
    }

    public function set_login_values(Participant $participant): void
    {
        $name = $participant->getName();

        $_SESSION["username"]                   = $participant->rcpro_username;
        $_SESSION[$this->PARTICIPANT_ID_COOKIE] = $participant->rcpro_participant_id;
        $_SESSION[$this->USERNAME_COOKIE]       = $participant->rcpro_username;
        $_SESSION[$this->EMAIL_COOKIE]          = $participant->email;
        $_SESSION[$this->FNAME_COOKIE]          = $name["fname"];
        $_SESSION[$this->LNAME_COOKIE]          = $name["lname"];
        $_SESSION[$this->LOGGED_IN_COOKIE]      = true;
    }
}
