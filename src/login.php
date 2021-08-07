<?php

use YaleREDCap\REDCapPRO\LoginHelper;

// Initialize Authentication
$module::$AUTH->init();

// Login Helper
require_once("classes/LoginHelper.php");
$Login = new LoginHelper($module);

// Check if the user is already logged in, if yes then redirect then to the survey
if ($module::$AUTH->is_logged_in()) {
    $survey_url = $module::$AUTH->get_survey_url();
    $survey_url_active = $module::$AUTH->is_survey_link_active();

    if (empty($survey_url) || empty($survey_url_active) || $survey_url_active !== TRUE) {
        return;
    }

    $module::$AUTH->deactivate_survey_link();
    header("location: ${survey_url}");
    return;
}

// Define variables and initialize with empty values
$username = $password = "";
$username_err = $password_err = $login_err = "";


// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    # Parse query string
    parse_str($_SERVER['QUERY_STRING'], $qstring);

    // Validate token
    if (!$module::$AUTH->validate_csrf_token($_POST['token'])) {
        $module->log("Invalid CSRF Token");
        echo "Oops! Something went wrong. Please try again later.";
        return;
    }

    // Check if username is empty
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter username.";
    } else {
        $username = \REDCap::escapeHtml(trim($_POST["username"]));
    }

    // Check if password is empty
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }

    try {
        // Validate credentials
        if (empty($username_err) && empty($password_err)) {

            // Check that IP is not locked out
            $ip = $Login->getIPAddress();
            $lockout_ts_ip = $Login->checkIpLockedOut($ip);
            if ($lockout_ts_ip !== FALSE) {
                $lockout_duration_remaining = $lockout_ts_ip - time();
                $login_err = "You have been temporarily locked out.<br>You have ${lockout_duration_remaining} seconds left.";
                $module->log("Login Attempted - IP Locked Out", [
                    "rcpro_ip"       => $ip,
                    "rcpro_username" => $username
                ]);

                // Check if username exists, if yes then verify password
                // --> USERNAME DOES NOT EXIST
            } else if (!$module::$PARTICIPANT->usernameIsTaken($username)) {

                // Username doesn't exist, display a generic error message
                $Login->incrementFailedIp($ip);
                $attempts = $Login->checkAttempts(NULL, $ip);
                $remainingAttempts = $module::$SETTINGS->getLoginAttempts() - $attempts;
                $module->log("Login Attempted - Username does not exist", [
                    "rcpro_ip"       => $ip,
                    "rcpro_username" => $username
                ]);
                if ($remainingAttempts <= 0) {
                    $login_err = "Invalid username or password.<br>You have been locked out for " . $module::$SETTINGS->getLockoutDurationSeconds() . " seconds.";
                    $module->log("IP LOCKOUT", ["rcpro_ip" => $ip]);
                } else {
                    $login_err = "Invalid username or password.<br>You have ${remainingAttempts} attempts remaining before being locked out.";
                }

                // --> USERNAME EXISTS
            } else {

                $participant = $module::$PARTICIPANT->getParticipant($username);
                $stored_hash = $Login->getHash($participant["log_id"]);

                // Check that this username is not locked out
                $lockout_duration_remaining = $Login->getUsernameLockoutDuration($participant["log_id"]);
                if ($lockout_duration_remaining !== FALSE && $lockout_duration_remaining !== NULL) {
                    // --> Username is locked out
                    $login_err = "You have been temporarily locked out.<br>You have ${lockout_duration_remaining} seconds left.";
                    $module->log("Login Attempted - Username Locked Out", [
                        "rcpro_ip"             => $ip,
                        "rcpro_username"       => $username,
                        "rcpro_participant_id" => $participant["log_id"]
                    ]);

                    // Check that there is a stored password hash
                } else if (empty($stored_hash)) {
                    // --> No password hash exists
                    // TODO: Give option to resend password email?
                    $module->log("No password hash stored.", [
                        "rcpro_participant_id" => $participant["log_id"],
                        "rcpro_username"       => $username
                    ]);
                    $login_err = "Error: you have not set up your password. Please speak with your study coordinator.";

                    // Verify supplied password is correct
                } else if (password_verify($password, $stored_hash)) {


                    ///////////////////////////////
                    // SUCCESSFUL AUTHENTICATION //
                    ///////////////////////////////

                    $module->log("Login Successful", [
                        "rcpro_ip"             => $ip,
                        "rcpro_username"       => $username,
                        "rcpro_participant_id" => $participant["log_id"]
                    ]);

                    // Rehash password if necessary
                    if (password_needs_rehash($stored_hash, PASSWORD_DEFAULT)) {
                        $new_hash = password_hash($password, PASSWORD_DEFAULT);
                        $module::$PARTICIPANT->storeHash($new_hash, $participant["log_id"]);
                    }

                    // Reset failed attempts and failed IP
                    $Login->resetFailedIp($ip);
                    $Login->resetFailedLogin($participant["log_id"]);

                    // Store data in session variables
                    $module::$AUTH->set_login_values($participant);

                    // Redirect user to appropriate page
                    if ($module::$AUTH->is_survey_url_set()) {
                        header("location: " . $module::$AUTH->get_survey_url());
                    } else if (isset($qstring["s"])) {
                        header("location: " . APP_PATH_SURVEY_FULL . $_SERVER['QUERY_STRING']);
                    } else {
                        $study_contact = $module->getContactPerson();
                        if (!isset($study_contact["name"])) {
                            echo "Please contact your study coordinator.";
                        } else {
                            echo "Please contact your study coordinator:<br>" . $study_contact["info"];
                        }
                    }
                    return;
                } else {

                    // --> Password is not valid
                    // display a generic error message
                    $module->log("Login Unsuccessful - Incorrect Password", [
                        "rcpro_ip"             => $ip,
                        "rcpro_username"       => $username,
                        "rcpro_participant_id" => $participant["log_id"]
                    ]);
                    $Login->incrementFailedLogin($participant["log_id"]);
                    $Login->incrementFailedIp($ip);
                    $attempts = $Login->checkAttempts($participant["log_id"], $ip);
                    $remainingAttempts = $module::$SETTINGS->getLoginAttempts() - $attempts;
                    if ($remainingAttempts <= 0) {
                        $login_err = "Invalid username or password.<br>You have been locked out for " . $module::$SETTINGS->getLockoutDurationSeconds() . " seconds.";
                        $module->log("USERNAME LOCKOUT", [
                            "rcpro_ip"             => $ip,
                            "rcpro_username"       => $username,
                            "rcpro_participant_id" => $participant["log_id"]
                        ]);
                    } else {
                        $login_err = "Invalid username or password.<br>You have ${remainingAttempts} attempts remaining before being locked out.";
                    }
                }
            }
        }
    } catch (\Exception $e) {
        $module->logError("Error logging in", $e);
        echo "Oops! Something went wrong. Please try again later.";
        exit();
    }
}

// set csrf token
$module::$AUTH->set_csrf_token();

// This method starts the html doc
$module::$UI->ShowParticipantHeader("Login");
?>

<div style="text-align: center;">
    <p>Please fill in your credentials to login.</p>
</div>

<?php
if (!empty($login_err)) {
    echo '<div class="alert alert-danger">' . $login_err . '</div>';
}
?>

<form action="<?= $module->getUrl("src/login.php", true); ?>" method="post">
    <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" class="form-control <?= (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?= $username; ?>">
        <span class="invalid-feedback"><?= $username_err; ?></span>
    </div>
    <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" class="form-control <?= (!empty($password_err)) ? 'is-invalid' : ''; ?>">
        <span class="invalid-feedback"><?= $password_err; ?></span>
    </div>
    <div class="form-group d-grid">
        <input type="submit" class="btn btn-primary" value="Login">
    </div>
    <input type="hidden" name="token" value="<?= $module::$AUTH->get_csrf_token(); ?>">
</form>
<hr>
<div style="text-align: center;">
    Forgot
    <a href="<?= $module->getUrl("src/forgot-username.php", true); ?>">Username</a>
    or
    <a href="<?= $module->getUrl("src/forgot-password.php", true); ?>">Password</a>?
</div>
</div>
</div>
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
</body>

</html>