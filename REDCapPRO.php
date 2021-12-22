<?php

namespace YaleREDCap\REDCapPRO;

use ExternalModules\AbstractExternalModule;

foreach (glob("src/classes/*.php") as $filename) {
    require_once($filename);
}
/**
 * Main EM Class
 */
class REDCapPRO extends AbstractExternalModule
{

    static $APPTITLE = "REDCapPRO";
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

    static $LOGO_URL           = "https://i.imgur.com/5Xq2Vqt.png";
    static $LOGO_ALTERNATE_URL = "https://i.imgur.com/fu0t8V1.png";

    //////////////\\\\\\\\\\\\\\       
    /////   REDCAP HOOKS   \\\\\ 
    //////////////\\\\\\\\\\\\\\

    function redcap_every_page_top($project_id)
    {
        if (strpos($_SERVER["PHP_SELF"], "surveys") !== false) {
            return;
        }
        $user = new REDCapProUser($this);
        $role = $user->getUserRole($project_id);
        if ($role > 0) {
?>
            <script>
                setTimeout(function() {
                    let link = $("<div>" +
                        "<img src='<?= $this->getUrl('images/fingerprint_2.png'); ?>' style='width:16px; height:16px; position:relative; top:-2px'></img>" +
                        "&nbsp;" +
                        "<a href='<?= $this->getUrl('src/home.php'); ?>'><span id='RCPro-Link'><strong><font style='color:black;'>REDCap</font><em><font style='color:#900000;'>PRO</font></em></strong></span></a>" +
                        "</div>");
                    $('#app_panel').find('div.hang').last().after(link);
                }, 10);
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
        $Auth = new Auth($this);
        $Auth->init();

        // Helpers
        $UI = new UI($this);
        $Dag = new DAG($this);
        $ProjectSettings = new ProjectSettings($this);
        $Emailer = new Emailer($this);

        // Participant is logged in to their account
        if ($Auth->is_logged_in()) {

            // Get RCPRO project
            $project = new Project($this, ["redcap_pid" => $project_id]);

            // Get Participant
            $rcpro_participant_id = $Auth->get_participant_id();
            $participant = new Participant($this, ["rcpro_participant_id" => $rcpro_participant_id]);

            // Determine whether participant is enrolled in the study.
            if (!$project->isParticipantEnrolled($participant)) {
                $this->logEvent("Participant not enrolled", [
                    "rcpro_participant_id" => $participant->rcpro_participant_id,
                    "rcpro_project_id"     => $project->rcpro_project_id,
                    "instrument"           => $instrument,
                    "event"                => $event_id,
                    "group_id"             => $group_id,
                    "survey_hash"          => $survey_hash,
                    "response_id"          => $response_id,
                    "repeat_instance"      => $repeat_instance
                ]);
                $UI->ShowParticipantHeader($this->tt("not_enrolled_title"));
                echo "<p style='text-align:center;'>" . $this->tt("not_enrolled_message1") . "<br>";
                $study_contact = $Emailer->getContactPerson($this->tt("not_enrolled_subject"));
                echo $this->tt("not_enrolled_message2");
                if (isset($study_contact["info"])) {
                    echo "<br>" . $study_contact["info"];
                }
                echo "</p>";
                $UI->EndParticipantPage();
                $this->exitAfterHook();
            }

            // Determine whether participant is in the appropriate DAG
            if (isset($group_id)) {
                $link = new Link($this, $project, $participant);
                $rcpro_dag = $Dag->getParticipantDag($link->id);

                if ($group_id !== $rcpro_dag) {
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
                    $UI->ShowParticipantHeader($this->tt("wrong_dag_title"));
                    echo "<p style='text-align:center;'>" . $this->tt("wrong_dag_message1") . "<br>";
                    $study_contact = $Emailer->getContactPerson($this->tt("wrong_dag_subject"));
                    echo $this->tt("not_enrolled_message2");
                    if (isset($study_contact["info"])) {
                        echo "<br>" . $study_contact["info"];
                    }
                    echo "</p>";
                    $UI->EndParticipantPage();
                    $this->exitAfterHook();
                }
            }

            // Log the event in REDCap's logs and the EM logs
            \REDCap::logEvent(
                "REDCapPRO Survey Accessed",                                        // action description
                "REDCapPRO User: " . $Auth->get_username() . "\n" .
                    "Instrument: ${instrument}\n",                                  // changes made
                NULL,                                                               // sql
                $record,                                                            // record
                $event_id,                                                          // event
                $project_id                                                         // project id
            );
            $this->logEvent("REDCapPRO Survey Accessed", [
                "rcpro_username"  => $Auth->get_username(),
                "rcpro_user_id"   => $Auth->get_participant_id(),
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
                window.rcpro.timeout_minutes = " . $ProjectSettings->getTimeoutMinutes() . ";
                window.rcpro.warning_minutes = " . $ProjectSettings->getTimeoutWarningMinutes() . ";
                window.rcpro.initTimeout();
            </script>";

            // Participant is not logged into their account
            // Store cookie to return to survey
        } else {
            $Auth->set_survey_url(APP_PATH_SURVEY_FULL . "?s=${survey_hash}");
            \Session::savecookie(self::$APPTITLE . "_survey_url", APP_PATH_SURVEY_FULL . "?s=${survey_hash}", 0, TRUE);
            $Auth->set_survey_active_state(TRUE);
            header("location: " . $this->getUrl("src/login.php", true) . "&s=${survey_hash}");
            $this->exitAfterHook();
        }
    }

    function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance = 1)
    {
        $user = new REDCapProUser($this);
        $role = $user->getUserRole($project_id);
        if ($role < 2) {
            return;
        }
        echo '<link href="' . $this->getUrl("lib/select2/select2.min.css") . '" rel="stylesheet" />
        <script src="' . $this->getUrl("lib/select2/select2.min.js") . '"></script>';

        $Dag = new DAG($this);
        $rcpro_dag = $Dag->getCurrentDag(constant("USERID"), $project_id);
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
        $project = new Project($this, [
            "redcap_pid" => $pid,
            "create_project" => true
        ]);
        $user = new REDCapProUser($this);
        $user->changeUserRole(NULL, $project->redcap_pid, NULL, 3);
        $this->logEvent("Module Enabled", [
            "redcap_user" => $user->username,
            "version" => $version
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
    function redcap_module_project_disable($version, $pid)
    {
        $project = new Project($this, ["redcap_pid" => $pid]);
        $project->setActive(0);
        $this->logEvent("Module Disabled", [
            "redcap_user" => constant("USERID"),
            "version" => $version
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
            "redcap_user" => constant("USERID"),
            "version" => $version
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
            "redcap_user" => constant("USERID"),
            "version" => $version
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
    function redcap_module_configure_button_display($project_id)
    {
        return empty($project_id);
    }


    function redcap_module_system_change_version($version, $old_version)
    {
        $this->logEvent("Module Version Changed", [
            "version"     => $version,
            "old_version" => $old_version,
            "redcap_user" => constant("USERID")
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
    /////   MISCELLANEOUS METHODS   \\\\\ 
    //////////////////\\\\\\\\\\\\\\\\\\\

    /**
     * Returns all REDCap users of the module (all staff)
     * 
     * @return [array]
     */
    function getAllUsers()
    {
        $projects = $this->getProjectsWithModuleEnabled();
        $users = array();
        foreach ($projects as $pid) {
            $project = new Project($this, ["redcap_pid" => $pid]);
            $all_staff = $project->getStaff()["allStaff"];
            foreach ($all_staff as $user) {
                if (isset($users[$user])) {
                    array_push($users[$user]['projects'], $pid);
                } else {
                    $newUser = new REDCapProUser($this, $user);
                    $newUserArr = [
                        "username" => $user,
                        "email" => $newUser->user->getEmail(),
                        "name" => $newUser->getUserFullname($user),
                        "projects" => [$pid]
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
            "redcap_user"   => constant("USERID"),
            "module_token"  => $this->getModuleToken()
        ];
        if (isset($e->rcpro)) {
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
        foreach ($parameters as $key => $value) {
            $logParameters[$key] = \REDCap::escapeHtml($value);
        }
        $logParametersString = json_encode($logParameters);
        $this->logEvent($message, [
            "parameters" => $logParametersString,
            "redcap_user" => constant("USERID"),
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
        if (!isset($moduleToken)) {
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
        while ($row = $logs->fetch_assoc()) {
            $SQL = "INSERT INTO redcap_external_modules_log_parameters (log_id, name, value) VALUES (?, ?, ?)";
            $this->query($SQL, [$row["log_id"], "module_token", $this->getModuleToken()]);
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
        $verb = stripos($selectStatement, " where ") === false ? " WHERE" : " AND";
        $selectStatementValidated = $selectStatement . $verb . " module_token = ?";
        array_push($params, $this->getModuleToken());
        if ($use_querylogs) {
            return $this->queryLogs($selectStatementValidated, $params);
        } else {
            return $this->query($selectStatementValidated, $params);
        }
    }

    public function countLogsValidated(string $whereClause, array $params)
    {
        $verb = (empty($whereClause) || trim($whereClause) === "") ? "" : " AND";
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
    function validateSettings(array $settings)
    {

        $message = NULL;

        // System settings
        // Enforce limits on setting values
        if (!$this->getProjectId()) {
            if (isset($settings["warning-time"]) && $settings["warning-time"] <= 0) {
                $message = "The warning time must be a positive number.";
            }
            if (isset($settings["timeout-time"]) && $settings["timeout-time"] <= 0) {
                $message = "The timeout time must be a positive number.";
            }
            if (isset($settings["password-length"]) && $settings["password-length"] < 8) {
                $message = "The minimum password length must be a positive integer greater than or equal to 8.";
            }
            if (isset($settings["login-attempts"]) && $settings["login-attempts"] < 1) {
                $message = "The minimum setting for login attempts is 1.";
            }
            if (isset($settings["lockout-seconds"]) && $settings["lockout-seconds"] < 0) {
                $message = "The minimum lockout duration is 0 seconds.";
            }
        }

        // Log configuration save attempt
        $logParameters = json_encode($settings);
        $this->logEvent("Configuration Saved", [
            "parameters" => $logParameters,
            "redcap_user" => constant("USERID"),
            "message" => $message,
            "success" => is_null($message)
        ]);

        return $message;
    }
}

/**
 * Function to pick the first non-empty string value from the given arguments
 * If you want a default value in case all of the given variables are empty,  
 * pass an extra parameter as the last value.
 *
 * @return  mixed  The first non-empty value from the arguments passed   
 */
function coalesce_string()
{
    $args = func_get_args();

    while (count($args) && !($arg = array_shift($args)));

    return intval($arg) !== 0 ? $arg : null;
}
