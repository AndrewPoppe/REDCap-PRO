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
     * 
     * @return void 
     */
    public function init(): void
    {
        $session_id = $_COOKIE["survey"] ?? $_COOKIE["PHPSESSID"];
        if (!empty($session_id)) {
            session_id($session_id);
        } else {
            $this->createSession();
        }
        session_start();
    }

    /**
     * Create a session
     * 
     * @return void
     */
    public function createSession(): void
    {
        \Session::init();
        $this->set_csrf_token();
    }

    /**
     * Set a session variable with an anti-CSRF token
     * 
     * @return void
     */
    public function set_csrf_token(): void
    {
        $_SESSION[$this->TOKEN_COOKIE] = bin2hex(random_bytes(24));
    }

    /**
     * Retrieves a stored anti-CSRF token
     * 
     * @return string Anti-CSRF token
     */
    public function get_csrf_token(): string
    {
        return $_SESSION[$this->TOKEN_COOKIE];
    }

    /**
     * Compare the provided token with the stored anti-CSRF token
     * 
     * @param string $token The token to validate
     * 
     * @return bool
     */
    public function validate_csrf_token(string $token): bool
    {
        return hash_equals($this->get_csrf_token(), $token);
    }

    // --- THESE DEAL WITH SESSION VALUES --- \\

    // TESTS

    /**
     * @return bool Whether anyone is currently logged in
     */
    public function is_logged_in(): bool
    {
        return isset($_SESSION[$this->LOGGED_IN_COOKIE]) && $_SESSION[$this->LOGGED_IN_COOKIE] === true;
    }

    /**
     * @return bool Whether survey url is currently set
     */
    public function is_survey_url_set(): bool
    {
        return isset($_SESSION[$this->SURVEY_URL_COOKIE]);
    }

    /**
     * @return bool Whether survey link is currently active
     */
    public function is_survey_link_active(): bool
    {
        return $_SESSION[$this->SURVEY_ACTIVE_COOKIE];
    }

    // GETS

    /**
     * @return string Currently stored survey url
     */
    public function get_survey_url(): string
    {
        return $_SESSION[$this->SURVEY_URL_COOKIE];
    }

    /**
     * @return string RCPRO Participant ID of currently logged-in participant
     */
    public function get_participant_id(): string
    {
        return $_SESSION[$this->PARTICIPANT_ID_COOKIE];
    }

    /**
     * @return string RCPRO Username of currently logged-in participant
     */
    public function get_username(): string
    {
        return $_SESSION[$this->USERNAME_COOKIE];
    }

    // SETS

    /**
     * Deactivates currently stored survey url
     * 
     * @return void
     */
    public function deactivate_survey_link(): void
    {
        unset($_SESSION[$this->SURVEY_ACTIVE_COOKIE]);
    }

    /**
     * Sets survey url
     * 
     * @param string $url Survey url
     * 
     * @return void
     */
    public function set_survey_url(string $url): void
    {
        $_SESSION[$this->SURVEY_URL_COOKIE] = $url;
    }

    /**
     * Sets currently stored survey link as active or inactive
     * 
     * @param bool $state True is active
     * 
     * @return void
     */
    public function set_survey_active_state(bool $state): void
    {
        $_SESSION[$this->SURVEY_ACTIVE_COOKIE] = $state;
    }

    /**
     * Stores information about logged-in participant in session variable
     * 
     * @param Participant $participant The logged-in participant
     * 
     * @return void
     */
    public function set_login_values(Participant $participant): void
    {
        $name                                   = $participant->getName();
        $_SESSION["username"]                   = $participant->rcpro_username;
        $_SESSION[$this->PARTICIPANT_ID_COOKIE] = $participant->rcpro_participant_id;
        $_SESSION[$this->USERNAME_COOKIE]       = $participant->rcpro_username;
        $_SESSION[$this->EMAIL_COOKIE]          = $participant->email;
        $_SESSION[$this->FNAME_COOKIE]          = $name["fname"];
        $_SESSION[$this->LNAME_COOKIE]          = $name["lname"];
        $_SESSION[$this->LOGGED_IN_COOKIE]      = true;
    }
}
