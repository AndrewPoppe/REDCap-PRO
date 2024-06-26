<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

// Initialize Authentication
$auth = new Auth($module->APPTITLE);
$auth->init();

// Login Helper
$Login = new LoginHelper($module);

// Make sure survey url is set
if ( !$auth->is_survey_url_set() && isset($_GET['s']) ) {
    $url = APP_PATH_SURVEY_FULL . "?s=" . filter_input(INPUT_GET, 's', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $auth->set_survey_url($url);
    $auth->set_survey_active_state(TRUE);
}

// Check if the user is already logged in, if yes then redirect then to the survey
if ( $auth->is_logged_in() ) {
    $survey_url        = $auth->get_survey_url();
    $survey_url_active = $auth->is_survey_link_active();

    if ( empty($survey_url) || empty($survey_url_active) || $survey_url_active !== TRUE ) {
        return;
    }

    $auth->deactivate_survey_link();
    header("location: " . $survey_url);
    return;
}

// Define variables and initialize with empty values
$username     = $password = "";
$username_err = $password_err = $login_err = "";

// Project Settings
$settings = new ProjectSettings($module);

// Participant Helper
$participantHelper = new ParticipantHelper($module);

// Processing form data when form is submitted
if ( $_SERVER["REQUEST_METHOD"] == "POST" ) {

    # Parse query string
    parse_str($_SERVER['QUERY_STRING'], $qstring);

    // Check if username is empty
    if ( empty(trim($_POST["username"])) ) {
        $username_err = $module->tt("login_err1");
    } else {
        $username           = \REDCap::escapeHtml(trim($_POST["username"]));
        $usernameExists     = $participantHelper->usernameIsTaken($username);
        $emailExists        = $participantHelper->checkEmailExists($username);
        $emailLoginsAllowed = $settings->emailLoginsAllowed($module->getProjectId());
    }

    // Check if password is empty
    if ( empty(trim($_POST["password"])) ) {
        $password_err = $module->tt("login_err2");
    } else {
        $password = trim($_POST["password"]);
    }

    try {
        // Validate credentials
        if ( empty($username_err) && empty($password_err) ) {
            // Check that IP is not locked out
            $ip            = $Login->getIPAddress();
            $lockout_ts_ip = $Login->checkIpLockedOut($ip);
            if ( $lockout_ts_ip !== FALSE ) {
                $lockout_duration_remaining = $lockout_ts_ip - time();
                $login_err                  = $module->tt("login_err3") . "<br>" . $module->tt("login_err4", $lockout_duration_remaining);
                $module->logEvent("Login Attempted - IP Locked Out", [
                    "rcpro_ip"       => $ip,
                    "rcpro_username" => $usernameExists ? $username : "",
                    "rcpro_email"    => $emailExists ? $username : ""
                ]);

                // Check if username/email exists, if yes then verify password
                // --> USERNAME DOES NOT EXIST
            } elseif ( !$usernameExists && !($emailExists && $emailLoginsAllowed) ) {

                // Username/email doesn't exist, display a generic error message
                $Login->incrementFailedIp($ip);
                $attempts          = $Login->checkAttempts(NULL, $ip);
                $remainingAttempts = $settings->getLoginAttempts() - $attempts;
                $module->logEvent("Login Attempted - Username/Email does not exist", [
                    "rcpro_ip"       => $ip,
                    "rcpro_username" => $usernameExists ? $username : "",
                    "rcpro_email"    => $emailExists ? $username : ""
                ]);
                if ( $remainingAttempts <= 0 ) {
                    $login_err = $module->tt("login_err5") . "<br>" . $module->tt("login_err6", $settings->getLockoutDurationSeconds());
                    $module->logEvent("IP LOCKOUT", [ "rcpro_ip" => $ip ]);
                } else {
                    $login_err = $module->tt("login_err5") . "<br>" . $module->tt("login_err7", $remainingAttempts);
                }

                // --> USERNAME/EMAIL EXISTS
            } else {

                $participant = $usernameExists ? $participantHelper->getParticipant($username) : $participantHelper->getParticipantFromEmail($username);
                $stored_hash = $Login->getHash($participant["log_id"]);

                // Check that this username is not locked out
                $lockout_duration_remaining = $Login->getUsernameLockoutDuration($participant["log_id"]);
                if ( $lockout_duration_remaining !== FALSE && $lockout_duration_remaining !== NULL ) {
                    // --> Username is locked out
                    $login_err = $module->tt("login_err3") . "<br>" . $module->tt("login_err4", $lockout_duration_remaining);
                    $module->logEvent("Login Attempted - Username Locked Out", [
                        "rcpro_ip"             => $ip,
                        "rcpro_username"       => $participant["rcpro_username"],
                        "rcpro_email"          => $participant["email"],
                        "rcpro_participant_id" => $participant["log_id"]
                    ]);

                    // Check that there is a stored password hash
                } else if ( empty($stored_hash) ) {
                    // --> No password hash exists
                    // TODO: Give option to resend password email?
                    $module->logEvent("No password hash stored.", [
                        "rcpro_participant_id" => $participant["log_id"],
                        "rcpro_username"       => $participant["rcpro_username"]
                    ]);
                    $login_err = $module->tt("login_err8");

                    // Verify supplied password is correct
                } else if ( password_verify($password, $stored_hash) ) {


                    ///////////////////////////////
                    // SUCCESSFUL AUTHENTICATION //
                    ///////////////////////////////

                    $module->logEvent("Login Successful", [
                        "rcpro_ip"             => $ip,
                        "rcpro_username"       => $participant["rcpro_username"],
                        "rcpro_participant_id" => $participant["log_id"]
                    ]);

                    // Rehash password if necessary
                    if ( password_needs_rehash($stored_hash, PASSWORD_DEFAULT) ) {
                        $new_hash = password_hash($password, PASSWORD_DEFAULT);
                        $participantHelper->storeHash($new_hash, $participant["log_id"]);
                    }

                    // Reset failed attempts and failed IP
                    $Login->resetFailedIp($ip);
                    $Login->resetFailedLogin($participant["log_id"]);

                    // Store data in session variables
                    $auth->set_login_values($participant);

                    // Redirect user to appropriate page
                    if ( $auth->is_survey_url_set() ) {
                        header("location: " . $auth->get_survey_url());
                    } elseif ( isset($qstring["s"]) ) {
                        header("location: " . APP_PATH_SURVEY_FULL . $_SERVER['QUERY_STRING']);
                    } else {
                        $study_contact = $module->getContactPerson();
                        echo $module->tt("login_err9");
                        if ( isset($study_contact["name"]) ) {
                            echo ":<br>" . $study_contact["info"];
                        }
                    }
                    return;
                } else {

                    // --> Password is not valid
                    // display a generic error message
                    $module->logEvent("Login Unsuccessful - Incorrect Password", [
                        "rcpro_ip"             => $ip,
                        "rcpro_username"       => $participant["rcpro_username"],
                        "rcpro_participant_id" => $participant["log_id"]
                    ]);
                    $Login->incrementFailedLogin($participant["log_id"], $participant["rcpro_username"]);
                    $Login->incrementFailedIp($ip);
                    $attempts          = $Login->checkAttempts($participant["log_id"], $ip);
                    $remainingAttempts = $settings->getLoginAttempts() - $attempts;
                    if ( $remainingAttempts <= 0 ) {
                        $login_err = $module->tt("login_err5") . "<br>" . $module->tt("login_err6", $settings->getLockoutDurationSeconds());
                        $module->logEvent("USERNAME LOCKOUT", [
                            "rcpro_ip"             => $ip,
                            "rcpro_username"       => $participant["rcpro_username"],
                            "rcpro_participant_id" => $participant["log_id"]
                        ]);
                    } else {
                        $login_err = $module->tt("login_err5") . "<br>" . $module->tt("login_err7", $remainingAttempts);
                    }
                }
            }
        }
    } catch ( \Exception $e ) {
        $module->logError("Error logging in", $e);
        echo $module->tt("error_generic1");
        echo "<br>";
        echo $module->tt("error_generic2");
        exit();
    }
}

// This method starts the html doc
$ui = new UI($module);
$ui->ShowParticipantHeader($module->tt("login_title"));
?>

<div style="text-align: center;">
    <p>
        <?= $module->tt("login_message1") ?>
    </p>
</div>

<?php
if ( !empty($login_err) ) {
    echo '<div class="alert alert-danger">' . $login_err . '</div>';
}
?>

<form action="<?= $module->getUrl("src/login.php", true); ?>" method="post">
    <div class="form-group">
        <label>
            <?= $settings->emailLoginsAllowed($module->getProjectId()) ? $module->tt("login_username_label2") : $module->tt("login_username_label") ?>
        </label>
        <input type="text" name="username" class="form-control <?= (!empty($username_err)) ? 'is-invalid' : ''; ?>"
            value="<?= $username; ?>" autocomplete="username" inputmode="email">
        <span class="invalid-feedback">
            <?= $username_err; ?>
        </span>
    </div>
    <div class="form-group">
        <label>
            <?= $module->tt("login_password_label") ?>
        </label>
        <input type="password" name="password" class="form-control <?= (!empty($password_err)) ? 'is-invalid' : ''; ?>"
            autocomplete="current-password">
        <span class="invalid-feedback">
            <?= $password_err; ?>
        </span>
    </div>
    <div class="form-group d-grid">
        <input type="submit" class="btn btn-primary" value="<?= $module->tt("login_button_text") ?>">
    </div>
    <input type="hidden" name="redcap_csrf_token" value="<?= $module->framework->getCSRFToken() ?>">
</form>
<hr>
<div style="text-align: center;">
    <?= $module->tt("login_forgot") ?>
    <a href="<?= $module->getUrl("src/forgot-username.php", true); ?>">
        <?= $module->tt("login_username") ?>
    </a>
    <?= $module->tt("login_or") ?>
    <a href="<?= $module->getUrl("src/forgot-password.php", true); ?>">
        <?= $module->tt("login_password") ?>
    </a>?
</div>
<?php if ( $settings->shouldAllowSelfRegistration((int) $module->getProjectId()) ) { ?>
    <div style="text-align: center;">
        <?= $module->tt("create_user1") ?>
        <a href="<?= $module->getUrl("src/create-account.php", true); ?>">
            <?= $module->tt("create_user2") ?>
        </a>
    </div>
<?php } ?>
<style>
    a {
        text-decoration: none !important;
        color: #900000 !important;
        font-weight: bold !important;
    }

    a:hover {
        text-shadow: 0px 0px 5px #900000;
    }
</style>
<?php $ui->EndParticipantPage(); ?>