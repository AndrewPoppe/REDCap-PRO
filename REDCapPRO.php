<?php

namespace YaleREDCap\REDCapPRO;

use ExternalModules\AbstractExternalModule;
use ExternalModules\Framework;
use IU\AutoNotifyModule\Settings;

require_once("src/classes/Auth.php");
require_once("src/classes/DAG.php");
require_once("src/classes/Instrument.php");
require_once("src/classes/ParticipantHelper.php");
require_once("src/classes/Project.php");
require_once("src/classes/ProjectHelper.php");
require_once("src/classes/ProjectSettings.php");
require_once("src/classes/REDCapProException.php");
require_once("src/classes/UI.php");

/**
 * @property Framework $framework
 * @see Framework
 */
class REDCapPRO extends AbstractExternalModule
{

    public $APPTITLE = "REDCapPRO";
    public $AUTH;
    public $UI;
    public $PARTICIPANT;
    public $PROJECT;
    public $DAG;
    static $COLORS = [
        "primary"          => "#900000",
        "secondary"        => "#17a2b8",
        "primaryHighlight" => "#c91616",
        "primaryDark"      => "#7a0000",
        "primaryLight"     => "#ffadad",
        "lightGrey"        => "#f9f9f9",
        "mediumGrey"       => "#dddddd",
        "darkGrey"         => "#6c757d",
        "blue"             => "#000090",
        "green"            => "#009000",
        "ban"              => "tomato"
    ];

    static $LOGO_URL = "https://i.imgur.com/5Xq2Vqt.png";
    static $LOGO_ALTERNATE_URL = "https://i.imgur.com/fu0t8V1.png";

    function __construct()
    {
        parent::__construct();
        $this->AUTH        = new Auth($this->APPTITLE);
        $this->UI          = new UI($this);
        $this->PARTICIPANT = new ParticipantHelper($this);
        $this->PROJECT     = new ProjectHelper($this);
        $this->DAG         = new DAG($this);
    }


    //////////////\\\\\\\\\\\\\\       
    /////   REDCAP HOOKS   \\\\\ 
    //////////////\\\\\\\\\\\\\\

    function redcap_every_page_top($project_id)
    {
        if ( strpos($_SERVER["PHP_SELF"], "surveys") !== false ) {
            return;
        }
        $this->AUTH->destroySession();
        $role = SUPER_USER ? 3 : $this->getUserRole(USERID); // 3=admin/manager, 2=user, 1=monitor, 0=not found
        if ( $role > 0 ) {
            ?>
            <script>
                (function ($, window, document) {
                    $(document).ready(function () {
                        let link = $("<div>" +
                            "<img src='<?= $this->getUrl('images/fingerprint_2.png'); ?>' style='width:16px; height:16px; position:relative; top:-2px'></img>" +
                            "&nbsp;" +
                            "<a href='<?= $this->getUrl('src/home.php'); ?>'><span id='RCPro-Link'><strong><font style='color:black;'>REDCap</font><em><font style='color:#900000;'>PRO</font></em></strong></span></a>" +
                            "</div>");
                        $('#app_panel').find('div.hang').last().after(link);
                    });
                }(window.jQuery, window, document));
            </script>
            <?php
        }
    }

    function redcap_survey_page_top(
        $project_id,
        $record,
        $instrument,
        $event_id,
        $group_id,
        $survey_hash,
        $response_id,
        $repeat_instance
    ) {

        // Initialize Authentication
        if ( isset($record) ) \Session::savecookie("record", $record, 0, TRUE);
        $this->AUTH->init();

        // Participant is logged in to their account
        if ( $this->AUTH->is_logged_in() ) {

            // Get RCPRO project ID
            $rcpro_project_id = $this->PARTICIPANT->getProjectIdFromPID($project_id);

            // Settings
            $settings = new ProjectSettings($this);

            // Determine whether participant is enrolled in the study.
            $rcpro_participant_id = $this->AUTH->get_participant_id();
            if ( !$this->PARTICIPANT->enrolledInProject($rcpro_participant_id, $rcpro_project_id) ) {
                $this->logEvent("Participant not enrolled", [
                    "rcpro_participant_id" => $rcpro_participant_id,
                    "rcpro_project_id"     => $rcpro_project_id,
                    "instrument"           => $instrument,
                    "event"                => $event_id,
                    "group_id"             => $group_id,
                    "survey_hash"          => $survey_hash,
                    "response_id"          => $response_id,
                    "repeat_instance"      => $repeat_instance
                ]);
                $this->UI->ShowParticipantHeader($this->tt("not_enrolled_title"));
                echo "<p style='text-align:center;'>" . $this->tt("not_enrolled_message1") . "<br>";
                $study_contact = $this->getContactPerson($this->tt("not_enrolled_subject"));
                echo $this->tt("not_enrolled_message2");
                if ( isset($study_contact["info"]) ) {
                    echo "<br>" . $study_contact["info"];
                }
                echo "</p>";
                $this->UI->EndParticipantPage();
                $this->exitAfterHook();
            }

            // Determine whether participant is in the appropriate DAG
            if ( isset($group_id) ) {
                $rcpro_link_id = $this->PARTICIPANT->getLinkId($rcpro_participant_id, $rcpro_project_id);
                $rcpro_dag     = $this->DAG->getParticipantDag($rcpro_link_id);

                if ( $group_id !== $rcpro_dag ) {
                    $this->logEvent("Participant wrong DAG", [
                        "rcpro_participant_id" => $rcpro_participant_id,
                        "instrument"           => $instrument,
                        "event"                => $event_id,
                        "group_id"             => $group_id,
                        "project_dag"          => $rcpro_dag,
                        "survey_hash"          => $survey_hash,
                        "response_id"          => $response_id,
                        "repeat_instance"      => $repeat_instance
                    ]);
                    $this->UI->ShowParticipantHeader($this->tt("wrong_dag_title"));
                    echo "<p style='text-align:center;'>" . $this->tt("wrong_dag_message1") . "<br>";
                    $study_contact = $this->getContactPerson($this->tt("wrong_dag_subject"));
                    echo $this->tt("not_enrolled_message2");
                    if ( isset($study_contact["info"]) ) {
                        echo "<br>" . $study_contact["info"];
                    }
                    echo "</p>";
                    $this->UI->EndParticipantPage();
                    $this->exitAfterHook();
                }
            }

            // Log the event in REDCap's logs and the EM logs
            \REDCap::logEvent(
                "REDCapPRO Survey Accessed",
                // action description
                "REDCapPRO User: " . $this->AUTH->get_username() . "\n" .
                "Instrument: ${instrument}\n",
                // changes made
                NULL,
                // sql
                $record,
                // record
                $event_id,
                // event
                $project_id // project id
            );
            $this->logEvent("REDCapPRO Survey Accessed", [
                "rcpro_username"  => $this->AUTH->get_username(),
                "rcpro_user_id"   => $this->AUTH->get_participant_id(),
                "record"          => $record,
                "event"           => $event_id,
                "instrument"      => $instrument,
                "survey_hash"     => $survey_hash,
                "response_id"     => $response_id,
                "repeat_instance" => $repeat_instance
            ]);

            // Add inline style
            echo "<style>
                .swal2-timer-progress-bar {
                    background: #900000 !important;
                }
                button.swal2-confirm:focus {
                    box-shadow: 0 0 0 3px rgb(144 0 0 / 50%) !important;
                }
                body.swal2-shown > [aria-hidden='true'] {
                    filter: blur(10px);
                }
                body > * {
                    transition: 0.1s filter linear;
                }
            </style>";

            // Initialize Javascript module object
            $this->initializeJavascriptModuleObject();

            // Transfer language translation keys to Javascript object
            $this->tt_transferToJavascriptModuleObject([
                "timeout_message1",
                "timeout_message2",
                "timeout_button_text"
            ]);

            // Add script to control logout of form
            echo "<script src='" . $this->getUrl("src/rcpro_base.js", true) . "'></script>";
            echo "<script>
                window.rcpro.module = " . $this->getJavascriptModuleObjectName() . ";
                window.rcpro.logo = '" . $this->getUrl("images/RCPro_Favicon.svg") . "';
                window.rcpro.logoutPage = '" . $this->getUrl("src/logout.php", true) . "';
                window.rcpro.sessionCheckPage = '" . $this->getUrl("src/session_check.php", true) . "';
                window.rcpro.timeout_minutes = " . $settings->getTimeoutMinutes() . ";
                window.rcpro.warning_minutes = " . $settings->getTimeoutWarningMinutes() . ";
                window.rcpro.initTimeout();
                window.rcpro.initSessionCheck();
            </script>";

            // Participant is not logged into their account
            // Store cookie to return to survey
        } else {
            $this->AUTH->set_survey_url(APP_PATH_SURVEY_FULL . "?s=${survey_hash}");
            \Session::savecookie($this->APPTITLE . "_survey_url", APP_PATH_SURVEY_FULL . "?s=${survey_hash}", 0, TRUE);
            $this->AUTH->set_survey_active_state(TRUE);
            header("location: " . $this->getUrl("src/login.php", true) . "&s=${survey_hash}");
            $this->exitAfterHook();
        }
    }

    function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance = 1)
    {
        $role = SUPER_USER ? 3 : $this->getUserRole(USERID); // 3=admin/manager, 2=user, 1=monitor, 0=not found
        if ( $role < 2 ) {
            return;
        }
        echo '<link href="' . $this->getUrl("lib/select2/select2.min.css") . '" rel="stylesheet" />
        <script src="' . $this->getUrl("lib/select2/select2.min.js") . '"></script>';

        $rcpro_dag  = $this->DAG->getCurrentDag(USERID, $project_id);
        $instrument = new Instrument($this, $instrument, $rcpro_dag);
        $instrument->update_form();
    }

    /**
     * Hook that is triggered when a module is enabled in a Project
     * 
     * It creates/updates a LINK entry in the module log. It also makes the user
     * that enabled the EM a manager in the project.
     * 
     * @param mixed $version
     * @param mixed $pid
     * 
     * @return void
     */
    function redcap_module_project_enable($version, $pid)
    {
        if ( !$this->PARTICIPANT->checkProject($pid) ) {
            $this->PARTICIPANT->addProject($pid);
        } else {
            $this->PARTICIPANT->setProjectActive($pid, 1);
        }
        $this->changeUserRole(USERID, NULL, 3);
        $this->logEvent("Module Enabled", [
            "redcap_user" => USERID,
            "version"     => $version
        ]);
    }

    /**
     * Hook that is triggered when a module is disabled in a Project
     * 
     * @param mixed $version
     * @param mixed $pid
     * 
     * @return void
     */
    function redcap_module_project_disable($version, $project_id)
    {
        $this->PARTICIPANT->setProjectActive($project_id, 0);
        $this->logEvent("Module Disabled", [
            "redcap_user" => USERID,
            "version"     => $version
        ]);
    }

    /**
     * Hook that is triggered when the module is enabled system-wide
     * 
     * @param mixed $version
     * 
     * @return void
     */
    function redcap_module_system_enable($version)
    {
        $this->logEvent("Module Enabled - System", [
            "redcap_user" => USERID,
            "version"     => $version
        ]);
    }

    /**
     * Hook that is triggered when the module is disabled system-wide
     * 
     * @param mixed $version
     * 
     * @return void
     */
    function redcap_module_system_disable($version)
    {
        $this->logEvent("Module Disabled - System", [
            "redcap_user" => USERID,
            "version"     => $version
        ]);
    }

    /**
     * This hook controls whether the configure button is displayed.
     * 
     * We want it shown in the control center, but not in projects.
     * 
     * @param mixed $project_id
     * 
     * @return bool
     */

    function redcap_module_configure_button_display()
    {
        // Hide module configuration button in project context.
        return $this->getProjectId() === null;
    }


    function redcap_module_system_change_version($version, $old_version)
    {
        $this->logEvent("Module Version Changed", [
            "version"     => $version,
            "old_version" => $old_version,
            "redcap_user" => USERID
        ]);

        $new_version_number = explode('v', $version)[1];
        $old_version_number = explode('v', $old_version)[1];

        // If upgrading from a previous version to version 0.4.5,
        // assume all existing logs are genuine, create module token,
        // and add the token to all existing logs.
        $critical_version = "0.4.6";
        if (
            version_compare($new_version_number, $critical_version, ">=") &&
            version_compare($old_version_number, $critical_version, "<")
        ) {
            $this->updateLogsWithToken();
        }
    }


    //////////////////\\\\\\\\\\\\\\\\\\\       
    /////   EMAIL-RELATED METHODS   \\\\\ 
    //////////////////\\\\\\\\\\\\\\\\\\\


    /**
     * @param string $email
     * @param string|null $subject
     * 
     * @return [type]
     */
    public function createEmailLink(string $email, ?string $subject)
    {
        if ( !isset($subject) ) {
            $subject = $this->tt("email_inquiry_subject");
        }
        $body = "";
        if ( $this->AUTH->is_logged_in() ) {
            $username = $this->AUTH->get_username();
            $body .= $this->tt("email_inquiry_username", $username) . "\n";
        }
        if ( PROJECT_ID ) {
            $body .= $this->tt("email_inquiry_project_id", PROJECT_ID) . "\n";
            $body .= $this->tt("email_inquiry_project_title", \REDCap::getProjectTitle());
        }
        $link = "mailto:${email}?subject=" . rawurlencode($subject) . "&body=" . rawurlencode($body);
        return "<br><strong>Email:</strong> <a href='${link}'>$email</a>";
    }

    /**
     * Gets project contact person's details
     * 
     * @return array of contact details
     */
    public function getContactPerson(string $subject = NULL)
    {
        $name  = $this->getProjectSetting("pc-name");
        $email = $this->getProjectSetting("pc-email");
        $phone = $this->getProjectSetting("pc-phone");

        $name_string  = isset($name) && $name !== "" ? "<strong>" . $this->tt("email_contact_name_string") . "</strong>" . $name : "";
        $email_string = isset($email) && $email !== "" ? $this->createEmailLink($email, $subject) : "";
        $phone_string = isset($phone) && $phone !== "" ? "<br><strong>" . $this->tt("email_contact_phone_string") . "</strong>" . $phone : "";
        $info         = "${name_string} ${email_string} ${phone_string}";

        return [
            "name"         => $name,
            "email"        => $email,
            "phone"        => $phone,
            "info"         => $info,
            "name_string"  => $name_string,
            "email_string" => $email_string,
            "phone_string" => $phone_string
        ];
    }

    public function sendEmailUpdateEmail(string $username, string $new_email, string $old_email)
    {
        $settings = new ProjectSettings($this);
        $subject         = $this->tt("email_update_subject");
        $from            = $settings->getEmailFromAddress();
        $old_email_clean = \REDCap::escapeHtml($old_email);
        $new_email_clean = \REDCap::escapeHtml($new_email);
        $body            = "<html><body><div>
        <img src='" . $this->LOGO_ALTERNATE_URL . "' alt='img' width='500px'><br>
        <p>" . $this->tt("email_update_greeting") . "</p>
        <p>" . $this->tt("email_update_message1") . "<strong> ${username}</strong><br>
            <ul>
                <li><strong>" . $this->tt("email_update_old_email") . "</strong> ${old_email_clean}</li>
                <li><strong>" . $this->tt("email_update_new_email") . "</strong> ${new_email_clean}</li>
            </ul>
        </p>";
        $body .= "<p><strong>" . $this->tt("email_update_message2") . "</strong>";
        if ( defined("PROJECT_ID") ) {
            $study_contact = $this->getContactPerson($this->tt("email_update_subject"));
            if ( isset($study_contact["info"]) ) {
                $body .= "<br>" . $study_contact["info"];
            }
        }
        $body .= "</p></div></body></html>";

        try {
            return \REDCap::email($new_email, $from, $subject, $body, $old_email);
        } catch ( \Exception $e ) {
            $this->logError("Error sending email reset email", $e);
        }
    }

    /**
     * Send new participant email to set password
     * 
     * This acts as an email verification process as well
     * 
     * @param string $username
     * @param string $email
     * @param string $fname
     * @param string $lname
     * 
     * @return bool|NULL
     */
    public function sendNewParticipantEmail(string $username, string $email, string $fname, string $lname)
    {
        // generate token
        try {

            $settings = new ProjectSettings($this);

            $rcpro_participant_id = $this->PARTICIPANT->getParticipantIdFromUsername($username);
            $hours_valid          = 24;
            $token                = $this->PARTICIPANT->createResetToken($rcpro_participant_id, $hours_valid);

            // create email
            $subject = $this->tt("email_new_participant_subject");
            $from    = $settings->getEmailFromAddress();
            $body    = "<html><body><div>
            <img src='" . $this->LOGO_ALTERNATE_URL . "' alt='img' width='500px'><br>
            <p>" . $this->tt("email_new_participant_greeting", [ $fname, $lname ]) . "
            <br>" . $this->tt("email_new_participant_message1") . "
            <br>" . $this->tt("email_new_participant_message2") . " <strong>${username}</strong>
            <br>" . $this->tt("email_new_participant_message3") . "</p>

            <p>" . $this->tt("email_new_participant_message4") . "
            <br>" . $this->tt("email_new_participant_message5") . " 
            <a href='" . $this->getUrl("src/create-password.php", true) . "&t=${token}'>" . $this->tt("email_new_participant_link_text") . "</a>
            <br>" . $this->tt("email_new_participant_message6", $hours_valid) . "</p>
            <br>";
            $body .= "<p>" . $this->tt("email_new_participant_message7");
            if ( defined("PROJECT_ID") ) {
                $study_contact = $this->getContactPerson($subject);
                if ( isset($study_contact["info"]) ) {
                    $body .= "<br>" . $study_contact["info"];
                }
            }
            $body .= "</p></div></body></html>";

            return \REDCap::email($email, $from, $subject, $body);
        } catch ( \Exception $e ) {
            $this->logError("Error sending new participant email", $e);
        }
    }

    /**
     * Send an email with a link for the participant to reset their email
     * 
     * @param mixed $rcpro_participant_id
     * 
     * @return void
     */
    public function sendPasswordResetEmail($rcpro_participant_id)
    {
        try {

            $settings = new ProjectSettings($this);

            // generate token
            $token          = $this->PARTICIPANT->createResetToken($rcpro_participant_id);
            $to             = $this->PARTICIPANT->getEmail($rcpro_participant_id);
            $username       = $this->PARTICIPANT->getUserName($rcpro_participant_id);
            $username_clean = \REDCap::escapeHtml($username);

            // create email
            $subject = $this->tt("email_password_reset_subject");
            $from    = $settings->getEmailFromAddress();
            $body    = "<html><body><div>
            <img src='" . $this->LOGO_ALTERNATE_URL . "' alt='img' width='500px'><br>
            <p>" . $this->tt("email_password_reset_greeting") . "
            <br>" . $this->tt("email_password_reset_message1") . "<br>
            <br>" . $this->tt("email_password_reset_message2") . "
            <br>" . $this->tt("email_password_reset_message3") . "<strong>${username_clean}</strong>
            <br>
            <br>" . $this->tt("email_password_reset_message4") . "<a href='" . $this->getUrl("src/reset-password.php", true) . "&t=${token}'>" . $this->tt("email_password_reset_link_text") . "</a>
            <br><em>" . $this->tt("email_password_reset_message5") . "<a href='" . $this->getUrl("src/forgot-password.php", true) . "'>" . $this->tt("email_password_reset_link_text") . "</a>
            </em></p><br>";
            $body .= "<p>" . $this->tt("email_password_reset_message6");
            if ( defined("PROJECT_ID") ) {
                $study_contact = $this->getContactPerson($subject);
                if ( isset($study_contact["info"]) ) {
                    $body .= "<br>" . $study_contact["info"];
                }
            }
            $body .= "</p></div></body></html>";

            $result = \REDCap::email($to, $from, $subject, $body);
            $status = $result ? "Sent" : "Failed to send";

            // Get current project (or "system" if initiated in the control center)
            $current_pid = $this->getProjectId() ?? "system";

            // Get all projects to which participant is currently enrolled
            $project_ids = $this->PARTICIPANT->getEnrolledProjects($rcpro_participant_id);
            foreach ( $project_ids as $project_id ) {
                $this->logEvent("Password Reset Email - ${status}", [
                    "rcpro_participant_id"  => $rcpro_participant_id,
                    "rcpro_username"        => $username_clean,
                    "rcpro_email"           => $to,
                    "redcap_user"           => USERID,
                    "project_id"            => $project_id,
                    "initiating_project_id" => $current_pid
                ]);
            }
            return $result;
        } catch ( \Exception $e ) {
            $this->logEvent("Password Reset Failed", [
                "rcpro_participant_id" => $rcpro_participant_id,
                "redcap_user"          => USERID
            ]);
            $this->logError("Error sending password reset email", $e);
        }
    }

    /**
     * Sends an email that just contains the participant's username.
     * 
     * @param string $email
     * @param string $username
     * 
     * @return bool|NULL success or failure
     */
    public function sendUsernameEmail(string $email, string $username)
    {

        $settings = new ProjectSettings($this);

        $subject = $this->tt("email_username_subject");
        $from    = $settings->getEmailFromAddress();
        $body    = "<html><body><div>
        <img src='" . $this->LOGO_ALTERNATE_URL . "' alt='img' width='500px'><br>
        <p>" . $this->tt("email_username_greeting") . "</p>
        <p>" . $this->tt("email_username_message1") . "<strong> ${username}</strong><br>
        " . $this->tt("email_username_message2") . "</p>

        <p>" . $this->tt("email_username_message3") . "<br><br>";

        $body .= $this->tt("email_username_message4");
        if ( defined("PROJECT_ID") ) {
            $study_contact = $this->getContactPerson($subject);
            if ( isset($study_contact["info"]) ) {
                $body .= "<br>" . $study_contact["info"];
            }
        }
        $body .= "</p></div></body></html>";

        try {
            return \REDCap::email($email, $from, $subject, $body);
        } catch ( \Exception $e ) {
            $this->logError("Error sending username email", $e);
        }
    }


    /////////////////////\\\\\\\\\\\\\\\\\\\\\\       
    /////   REDCAP USER-RELATED METHODS   \\\\\ 
    /////////////////////\\\\\\\\\\\\\\\\\\\\\\

    /**
     * Updates the role of the given REDCap user 
     * 
     * @param string $username
     * @param string|NULL $oldRole This is just for logging purposes
     * @param string $newRole
     * 
     * @return void
     */
    public function changeUserRole(string $username, ?string $oldRole, string $newRole)
    {
        // FIX: PHP 8 fix
        try {
            $roles = array(
                "3" => $this->getProjectSetting("managers") ?? array( '' ),
                "2" => $this->getProjectSetting("users") ?? array( '' ),
                "1" => $this->getProjectSetting("monitors") ?? array( '' )
            );

            $oldRole = strval($oldRole);
            $newRole = strval($newRole);

            foreach ( $roles as $role => $users ) {
                if ( ($key = array_search($username, $users)) !== false ) {
                    unset($users[$key]);
                    $roles[$role] = array_values($users);
                }
            }
            if ( $newRole !== "0" ) {
                $roles[$newRole][] = $username;
            }

            $this->setProjectSetting("managers", $roles["3"]);
            $this->setProjectSetting("users", $roles["2"]);
            $this->setProjectSetting("monitors", $roles["1"]);

            $this->logEvent("Changed user role", [
                "redcap_user"            => USERID,
                "redcap_user_acted_upon" => $username,
                "old_role"               => $oldRole,
                "new_role"               => $newRole
            ]);
        } catch ( \Exception $e ) {
            $this->logError("Error changing user role", $e);
        }
    }

    /**
     * Gets the full name of REDCap user with given username
     * 
     * @param string $username
     * 
     * @return string|null Full Name
     */
    public function getUserFullname(string $username)
    {
        $SQL = 'SELECT CONCAT(user_firstname, " ", user_lastname) AS name FROM redcap_user_information WHERE username = ?';
        try {
            $result = $this->query($SQL, [ $username ]);
            return $result->fetch_assoc()["name"];
        } catch ( \Exception $e ) {
            $this->logError("Error getting user full name", $e);
        }
    }

    /**
     * Gets the REDCapPRO role for the given REDCap user
     * @param string $username REDCap username
     * 
     * @return int role
     */
    public function getUserRole(string $username)
    {
        // FIX: PHP 8 fix
        $managers = $this->getProjectSetting("managers") ?? array( '' );
        $users    = $this->getProjectSetting("users") ?? array( '' );
        $monitors = $this->getProjectSetting("monitors") ?? array( '' );

        $result = 0;

        if ( in_array($username, $managers) ) {
            $result = 3;
        } else if ( in_array($username, $users) ) {
            $result = 2;
        } else if ( in_array($username, $monitors) ) {
            $result = 1;
        }

        return $result;
    }


    //////////////////\\\\\\\\\\\\\\\\\\\       
    /////   MISCELLANEOUS METHODS   \\\\\ 
    //////////////////\\\\\\\\\\\\\\\\\\\

    /**
     * Returns all REDCap users of the module (all staff)
     * 
     * @return [array]
     */
    function getAllUsers()
    {
        global $module;
        $projects = $module->getProjectsWithModuleEnabled();
        $users    = array();
        foreach ( $projects as $pid ) {
            $project   = new Project($this, $pid);
            $staff_arr = $project->getStaff();
            $all_staff = $staff_arr["allStaff"];
            foreach ( $all_staff as $user ) {
                if ( isset($users[$user]) ) {
                    array_push($users[$user]['projects'], $pid);
                } else {
                    $newUser      = $module->getUser($user);
                    $newUserArr   = [
                        "username" => $user,
                        "email"    => $newUser->getEmail(),
                        "name"     => $module->getUserFullname($user),
                        "projects" => [ $pid ]
                    ];
                    $users[$user] = $newUserArr;
                }
            }
        }
        return $users;
    }

    /**
     * Logs errors thrown during operation
     * 
     * @param string $message
     * @param \Exception $e
     * 
     * @return void
     */
    public function logError(string $message, \Exception $e)
    {
        $params = [
            "error_code"    => $e->getCode(),
            "error_message" => $e->getMessage(),
            "error_file"    => $e->getFile(),
            "error_line"    => $e->getLine(),
            "error_string"  => $e->__toString(),
            "redcap_user"   => USERID,
            "module_token"  => $this->getModuleToken()
        ];
        if ( isset($e->rcpro) ) {
            $params = array_merge($params, $e->rcpro);
        }
        $this->logEvent($message, $params);
    }

    /**
     * Logs form submission attempts by the user
     * 
     * @param string $message
     * @param array $parameters
     * 
     * @return void
     */
    public function logForm(string $message, $parameters)
    {
        $logParameters = array();
        foreach ( $parameters as $key => $value ) {
            $logParameters[$key] = \REDCap::escapeHtml($value);
        }
        $logParametersString = json_encode($logParameters);
        $this->logEvent($message, [
            "parameters"   => $logParametersString,
            "redcap_user"  => USERID,
            "module_token" => $this->getModuleToken()
        ]);
    }

    /**
     * Logs an event 
     * 
     * @param string $message
     * @param array $parameters
     * 
     * @return mixed
     */
    public function logEvent(string $message, $parameters)
    {
        $parameters["module_token"] = $this->getModuleToken();
        return $this->log($message, $parameters);
    }

    /**
     * Retrieves token used to validate logs created by this module
     * 
     * @return string
     */
    private function getModuleToken()
    {
        $moduleToken = $this->getSystemSetting("module_token");
        if ( !isset($moduleToken) ) {
            $moduleToken = $this->createModuleToken();
            $this->saveModuleToken($moduleToken);
        }
        return $moduleToken;
    }

    private function saveModuleToken(string $moduleToken)
    {
        $this->setSystemSetting("module_token", $moduleToken);
    }

    /**
     * Creates token used by module to validate logs
     * 
     * @return string
     */
    private function createModuleToken()
    {
        return bin2hex(random_bytes(64));
    }

    /**
     * If moving from an early version of the module, need to update existing
     * logs with the module token
     * 
     * @return null
     */
    private function updateLogsWithToken()
    {
        $logs = $this->queryLogs("SELECT log_id WHERE module_token IS NULL");
        while ( $row = $logs->fetch_assoc() ) {
            $SQL = "INSERT INTO redcap_external_modules_log_parameters (log_id, name, value) VALUES (?, ?, ?)";
            $this->query($SQL, [ $row["log_id"], "module_token", $this->getModuleToken() ]);
        }
    }

    /**
     * Automatically include check for module token in any select query
     * 
     * @param string $select_statement
     * @param array $params
     * @param bool $use_querylogs - whether to use queryLogs() or query()
     * 
     * @return mixed
     */
    public function selectLogs(string $selectStatement, array $params, bool $use_querylogs = true)
    {
        $verb                     = stripos($selectStatement, " where ") === false ? " WHERE" : " AND";
        $selectStatementValidated = $selectStatement . $verb . " module_token = ?";
        array_push($params, $this->getModuleToken());
        if ( $use_querylogs ) {
            return $this->queryLogs($selectStatementValidated, $params);
        } else {
            return $this->query($selectStatementValidated, $params);
        }
    }

    public function countLogsValidated(string $whereClause, array $params)
    {
        $verb                 = (empty($whereClause) || trim($whereClause) === "") ? "" : " AND";
        $whereClauseValidated = $whereClause . $verb . " module_token = ?";
        array_push($params, $this->getModuleToken());
        return $this->countLogs($whereClauseValidated, $params);
    }

    /**
     * Make sure settings meet certain conditions.
     * 
     * This is called when a user clicks "Save" in either system or project
     * configuration.
     * 
     * @param array $settings Array of settings user is trying to set
     * 
     * @return string|null if not null, the error message to show to user
     */
    function validateSettings($settings)
    {

        $message = NULL;

        // System settings
        // Enforce limits on setting values
        if ( !$this->getProjectId() ) {
            if ( isset($settings["warning-time"]) && $settings["warning-time"] <= 0 ) {
                $message = "The warning time must be a positive number.";
            }
            if ( isset($settings["timeout-time"]) && $settings["timeout-time"] <= 0 ) {
                $message = "The timeout time must be a positive number.";
            }
            if ( isset($settings["password-length"]) && $settings["password-length"] < 8 ) {
                $message = "The minimum password length must be a positive integer greater than or equal to 8.";
            }
            if ( isset($settings["login-attempts"]) && $settings["login-attempts"] < 1 ) {
                $message = "The minimum setting for login attempts is 1.";
            }
            if ( isset($settings["lockout-seconds"]) && $settings["lockout-seconds"] < 0 ) {
                $message = "The minimum lockout duration is 0 seconds.";
            }
        }

        // Log configuration save attempt
        $logParameters = json_encode($settings);
        $this->logEvent("Configuration Saved", [
            "parameters"  => $logParameters,
            "redcap_user" => USERID,
            "message"     => $message,
            "success"     => is_null($message)
        ]);

        return $message;
    }
}