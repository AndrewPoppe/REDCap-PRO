<?php

namespace YaleREDCap\REDCapPRO;

# Initialize authentication session on page
$module::$AUTH->init();

# Parse query string to grab token.
parse_str($_SERVER['QUERY_STRING'], $qstring);

// Redirect to login page if we shouldn't be here
if (!isset($qstring["t"]) && $_SERVER["REQUEST_METHOD"] !== "POST") {
    header("location: " . $module->getUrl("src/login.php", true));
    return;
}

// Define variables and initialize with empty values
$new_password = $confirm_password = "";
$new_password_err = $confirm_password_err = "";


// Verify password reset token
$verified_participant = $module->PARTICIPANT_HELPER->verifyPasswordResetToken($qstring["t"]);
$participant = new Participant($module, ["rcpro_participant_id" => $verified_participant["log_id"]]);

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Validate token
    if (!$module::$AUTH->validate_csrf_token($_POST['token'])) {
        echo $module->tt("error_generic1");
        echo "<br>";
        echo $module->tt("error_generic2");
        return;
    }

    // Validate password
    if (empty(trim($_POST["new_password"]))) {
        $new_password_err = $module->tt("reset_password_err1");
        $any_error = TRUE;
    } else {
        $new_password = trim($_POST["new_password"]);
        // Validate password strength
        $pw_len_req   = $module::$SETTINGS->getPasswordLength();
        $uppercase    = preg_match('@[A-Z]@', $new_password);
        $lowercase    = preg_match('@[a-z]@', $new_password);
        $number       = preg_match('@[0-9]@', $new_password);
        $specialChars = preg_match('@[^\w]@', $new_password);
        $goodLength   = strlen($new_password) >= $pw_len_req;

        if (!$uppercase || !$lowercase || !$number || !$specialChars || !$goodLength) {
            $new_password_err = implode("<br>- ", [
                $module->tt("reset_password_err2", $pw_len_req),
                $module->tt("create_password_upper"),
                $module->tt("create_password_lower"),
                $module->tt("create_password_number"),
                $module->tt("create_password_special")
            ]);
            $any_error = TRUE;
        }
    }

    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = $module->tt("reset_password_err3");
        $any_error = TRUE;
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($new_password_err) && ($new_password !== $confirm_password)) {
            $confirm_password_err = $module->tt("reset_password_err4");
            $any_error = TRUE;
        }
    }

    // Check input errors before updating the database
    if (!$any_error) {

        // Update password
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $result = $participant->storeHash($password_hash);
        if (empty($result) || $result === FALSE) {
            echo $module->tt("error_generic1");
            echo "<br>";
            echo $module->tt("error_generic2");
            return;
        }

        // Password was successfully set. Expire the token.
        $participant->expirePasswordResetToken();

        // Store data in session variables
        $module::$AUTH->set_login_values($participant);

        if ($module::$AUTH->is_survey_url_set()) {
            header("location: " . $module::$AUTH->get_survey_url());
        } else {
            $module::$UI->ShowParticipantHeader($module->tt("reset_password_title2"));
            echo "<div style='text-align:center;'><p>" . $module->tt("ui_close_tab") . "</p></div>";
            $module::$UI->EndParticipantPage();
        }
        return;
    }
}

// set csrf token
$module::$AUTH->set_csrf_token();

$module::$UI->ShowParticipantHeader($module->tt("reset_password_title"));

if ($participant->exists) {
?>
    <div style="text-align: center;">
        <p><?= $module->tt("reset_password_message1") ?></p>
    </div>
    <form action="<?= $module->getUrl("src/reset-password.php", true) . "&t=" . $qstring["t"]; ?>" method="post">
        <div class="form-group">
            <span><?= $module->tt("reset_password_username_label") ?><span style="color: #900000; font-weight: bold;"><?= $participant->rcpro_username ?></span></span>
        </div>
        <div class="form-group">
            <label><?= $module->tt("reset_password_password_label") ?></label>
            <input type="password" name="new_password" class="form-control <?php echo (!empty($new_password_err)) ? 'is-invalid' : ''; ?>">
            <span class="invalid-feedback"><?php echo $new_password_err; ?></span>
        </div>
        <div class="form-group">
            <label><?= $module->tt("reset_password_confirm_password_label") ?></label>
            <input type="password" name="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>">
            <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
        </div>
        <div class="form-group d-grid">
            <input type="submit" class="btn btn-primary" value="<?= $module->tt("ui_button_submit") ?>">
        </div>
        <input type="hidden" name="token" value="<?= $module::$AUTH->get_csrf_token(); ?>">
        <input type="hidden" name="username" value="<?= $participant->rcpro_username ?>">
    </form>
<?php } else { ?>
    <div class='red' style="text-align: center;">
        <?= $module->tt("reset_password_err5") ?>
    </div>
<?php } ?>
<?php $module::$UI->EndParticipantPage(); ?>