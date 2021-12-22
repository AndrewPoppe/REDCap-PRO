<?php

namespace YaleREDCap\REDCapPRO;

// Initialize Authentication
$Auth = new Auth($module);
$Auth->init();

// Helper Objects
$LoginHelper = new LoginHelper($module);
$ProjectSettings = new ProjectSettings($module);
$UI = new UI($module);
$Emailer = new Emailer($module);

// Check if the user is already logged in, if yes then redirect then to the survey
if ($Auth->is_logged_in()) {
    $survey_url = $Auth->get_survey_url();
    $survey_url_active = $Auth->is_survey_link_active();

    if (empty($survey_url) || empty($survey_url_active) || $survey_url_active !== TRUE) {
        return;
    }

    $Auth->deactivate_survey_link();
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
    if (!$Auth->validate_csrf_token($_POST['token'])) {
        $module->logEvent("Invalid CSRF Token");
        echo $module->tt("error_generic1");
        echo "<br>";
        echo $module->tt("error_generic2");
        return;
    }

    // Check if username is empty
    if (empty(trim($_POST["username"]))) {
        $username_err = $module->tt("login_err1");
    } else {
        $username = \REDCap::escapeHtml(trim($_POST["username"]));
        $usernameExists = usernameIsTaken($username);
        $emailExists = checkEmailExists($username);
        $emailLoginsAllowed = $ProjectSettings->emailLoginsAllowed($module->getProjectId());
    }

    // Check if password is empty
    if (empty(trim($_POST["password"]))) {
        $password_err = $module->tt("login_err2");
    } else {
        $password = trim($_POST["password"]);
    }

    try {
        // Validate credentials
        if (empty($username_err) && empty($password_err)) {

            // Check that IP is not locked out
            $ip = $LoginHelper->getIPAddress();
            $lockout_ts_ip = $LoginHelper->checkIpLockedOut($ip);
            if ($lockout_ts_ip !== FALSE) {
                $lockout_duration_remaining = $lockout_ts_ip - time();
                $login_err = $module->tt("login_err3") . "<br>" . $module->tt("login_err4", $lockout_duration_remaining);
                $module->logEvent("Login Attempted - IP Locked Out", [
                    "rcpro_ip"       => $ip,
                    "rcpro_username" => $usernameExists ? $username : "",
                    "rcpro_email" => $emailExists ? $username : ""
                ]);

                // Check if username/email exists, if yes then verify password
                // --> USERNAME DOES NOT EXIST
            } else if (!$usernameExists && !($emailExists && $emailLoginsAllowed)) {

                // Username/email doesn't exist, display a generic error message
                $LoginHelper->incrementFailedIp($ip);
                $attempts = $LoginHelper->checkAttempts(NULL, $ip);
                $remainingAttempts = $ProjectSettings->getLoginAttempts() - $attempts;
                $module->logEvent("Login Attempted - Username/Email does not exist", [
                    "rcpro_ip"       => $ip,
                    "rcpro_username" => $usernameExists ? $username : "",
                    "rcpro_email" => $emailExists ? $username : ""
                ]);
                if ($remainingAttempts <= 0) {
                    $login_err = $module->tt("login_err5") . "<br>" . $module->tt("login_err6", $ProjectSettings->getLockoutDurationSeconds());
                    $module->logEvent("IP LOCKOUT", ["rcpro_ip" => $ip]);
                } else {
                    $login_err = $module->tt("login_err5") . "<br>" . $module->tt("login_err7", $remainingAttempts);
                }

                // --> USERNAME/EMAIL EXISTS
            } else {

                $participant = $usernameExists ? new Participant($module, ["rcpro_username" => $username]) : new Participant($module, ["email" => $username]);
                $stored_hash = $participant->getHash();

                // Check that this username is not locked out
                $lockout_duration_remaining = $LoginHelper->getUsernameLockoutDuration($participant->rcpro_participant_id);
                if ($lockout_duration_remaining !== FALSE && $lockout_duration_remaining !== NULL) {
                    // --> Username is locked out
                    $login_err = $module->tt("login_err3") . "<br>" . $module->tt("login_err4", $lockout_duration_remaining);
                    $module->logEvent("Login Attempted - Username Locked Out", [
                        "rcpro_ip"             => $ip,
                        "rcpro_username"       => $participant->rcpro_username,
                        "rcpro_email"          => $participant->email,
                        "rcpro_participant_id" => $participant->rcpro_participant_id
                    ]);

                    // Check that there is a stored password hash
                } else if (empty($stored_hash)) {
                    // --> No password hash exists
                    // TODO: Give option to resend password email?
                    $module->logEvent("No password hash stored.", [
                        "rcpro_participant_id" => $participant->rcpro_participant_id,
                        "rcpro_username"       => $participant->rcpro_username
                    ]);
                    $login_err = $module->tt("login_err8");

                    // Verify supplied password is correct
                } else if (password_verify($password, $stored_hash)) {


                    ///////////////////////////////
                    // SUCCESSFUL AUTHENTICATION //
                    ///////////////////////////////

                    $module->logEvent("Login Successful", [
                        "rcpro_ip"             => $ip,
                        "rcpro_username"       => $participant->rcpro_username,
                        "rcpro_participant_id" => $participant->rcpro_participant_id
                    ]);

                    // Rehash password if necessary
                    if (password_needs_rehash($stored_hash, PASSWORD_DEFAULT)) {
                        $new_hash = password_hash($password, PASSWORD_DEFAULT);
                        $participant->storeHash($new_hash);
                    }

                    // Reset failed attempts and failed IP
                    $LoginHelper->resetFailedIp($ip);
                    $LoginHelper->resetFailedLogin($participant->rcpro_participant_id);

                    // Store data in session variables
                    $Auth->set_login_values($participant);

                    // Redirect user to appropriate page
                    if ($Auth->is_survey_url_set()) {
                        header("location: " . $Auth->get_survey_url());
                    } else if (isset($qstring["s"])) {
                        header("location: " . constant("APP_PATH_SURVEY_FULL") . $_SERVER['QUERY_STRING']);
                    } else {
                        $study_contact = $Emailer->getContactPerson();
                        echo $module->tt("login_err9");
                        if (isset($study_contact["name"])) {
                            echo ":<br>" . $study_contact["info"];
                        }
                    }
                    return;
                } else {

                    // --> Password is not valid
                    // display a generic error message
                    $module->logEvent("Login Unsuccessful - Incorrect Password", [
                        "rcpro_ip"             => $ip,
                        "rcpro_username"       => $participant->rcpro_username,
                        "rcpro_participant_id" => $participant->rcpro_participant_id
                    ]);
                    $LoginHelper->incrementFailedLogin($participant->rcpro_participant_id, $participant->rcpro_username);
                    $LoginHelper->incrementFailedIp($ip);
                    $attempts = $LoginHelper->checkAttempts($participant->rcpro_participant_id, $ip);
                    $remainingAttempts = $ProjectSettings->getLoginAttempts() - $attempts;
                    if ($remainingAttempts <= 0) {
                        $login_err = $module->tt("login_err5") . "<br>" . $module->tt("login_err6", $ProjectSettings->getLockoutDurationSeconds());
                        $module->logEvent("USERNAME LOCKOUT", [
                            "rcpro_ip"             => $ip,
                            "rcpro_username"       => $participant->rcpro_username,
                            "rcpro_participant_id" => $participant->rcpro_participant_id
                        ]);
                    } else {
                        $login_err = $module->tt("login_err5") . "<br>" . $module->tt("login_err7", $remainingAttempts);
                    }
                }
            }
        }
    } catch (\Exception $e) {
        $module->logError("Error logging in", $e);
        echo $module->tt("error_generic1");
        echo "<br>";
        echo $module->tt("error_generic2");
        exit();
    }
}

// set csrf token
$Auth->set_csrf_token();

// This method starts the html doc
$UI->ShowParticipantHeader($module->tt("login_title"));
?>

<div style="text-align: center;">
    <p><?= $module->tt("login_message1") ?></p>
</div>

<?php
if (!empty($login_err)) {
    echo '<div class="alert alert-danger">' . $login_err . '</div>';
}
?>

<form action="<?= $module->getUrl("src/login.php", true); ?>" method="post">
    <div class="form-group">
        <label><?= $ProjectSettings->emailLoginsAllowed($module->getProjectId()) ? $module->tt("login_username_label2") : $module->tt("login_username_label") ?></label>
        <input type="text" name="username" class="form-control <?= (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?= $username; ?>">
        <span class="invalid-feedback"><?= $username_err; ?></span>
    </div>
    <div class="form-group">
        <label><?= $module->tt("login_password_label") ?></label>
        <input type="password" name="password" class="form-control <?= (!empty($password_err)) ? 'is-invalid' : ''; ?>">
        <span class="invalid-feedback"><?= $password_err; ?></span>
    </div>
    <div class="form-group d-grid">
        <input type="submit" class="btn btn-primary" value="<?= $module->tt("login_button_text") ?>">
    </div>
    <input type="hidden" name="token" value="<?= $Auth->get_csrf_token(); ?>">
</form>
<hr>
<div style="text-align: center;">
    <?= $module->tt("login_forgot") ?>
    <a href="<?= $module->getUrl("src/forgot-username.php", true); ?>"><?= $module->tt("login_username") ?></a>
    <?= $module->tt("login_or") ?>
    <a href="<?= $module->getUrl("src/forgot-password.php", true); ?>"><?= $module->tt("login_password") ?></a>?
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
<?php $UI->EndParticipantPage(); ?>