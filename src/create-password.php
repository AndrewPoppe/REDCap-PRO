<?php

namespace YaleREDCap\REDCapPRO;

// Helpers
$Auth = new Auth($module);
$UI = new UI($module);
$ParticipantHelper = new ParticipantHelper($module);

// Initialize Authentication
$Auth->init();

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
$verified_participant = $ParticipantHelper->verifyPasswordResetToken($qstring["t"]);

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Validate token
    if (!$Auth->validate_csrf_token($_POST['token'])) {
        $UI->ShowParticipantHeader($module->tt("error_title"));
        echo "<div style='text-align: center;'>" . $module->tt("error_generic1") . "<br>" . $module->tt("error_csrf1") . "</div>";
        $UI->EndParticipantPage();
        return;
    }

    // Validate password
    $new_password = trim($_POST["new_password"]);
    if (empty($new_password)) {
        $new_password_err = $module->tt("create_password_message1");
        $any_error = TRUE;
    } else {
        // Validate password strength
        $settings     = new ProjectSettings($module);
        $pw_len_req   = $settings->getPasswordLength();
        $uppercase    = preg_match('@[A-Z]@', $new_password);
        $lowercase    = preg_match('@[a-z]@', $new_password);
        $number       = preg_match('@[0-9]@', $new_password);
        $specialChars = preg_match('@[^\w]@', $new_password);
        $goodLength   = strlen($new_password) >= $pw_len_req;

        if (!$uppercase || !$lowercase || !$number || !$specialChars || !$goodLength) {
            $new_password_err = implode("<br>- ", [
                $module->tt("create_password_message2", $pw_len_req),
                $module->tt("create_password_upper"),
                $module->tt("create_password_lower"),
                $module->tt("create_password_number"),
                $module->tt("create_password_special")
            ]);
            $any_error = TRUE;
        }
    }

    // Validate confirm password
    $confirm_password = trim($_POST["confirm_password"]);
    if (empty($confirm_password)) {
        $confirm_password_err = $module->tt("create_password_confirm");
        $any_error = TRUE;
    } else {
        if (empty($new_password_err) && ($new_password !== $confirm_password)) {
            $confirm_password_err = $module->tt("create_password_no_match");
            $any_error = TRUE;
        }
    }

    // Check input errors before updating the database
    if (!$any_error) {

        // Grab all user details
        $this_participant = new Participant($module, ["rcpro_username" => $_POST["username"]]);

        // Update password
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $result = $this_participant->storeHash($password_hash);
        if (empty($result) || $result === FALSE) {
            echo $module->tt("error_generic1");
            echo $module->tt("error_generic2");
            return;
        }

        // Password was successfully set. Expire the token.
        $this_participant->expirePasswordResetToken();

        // Store data in session variables
        $Auth->set_login_values($this_participant);

        $UI->ShowParticipantHeader($module->tt("create_password_set"));
        echo "<div style='text-align:center;'><p>" . $module->tt("ui_close_tab") . "</p></div>";
        $UI->EndParticipantPage();
        return;
    }
}

// set csrf token
$Auth->set_csrf_token();

$UI->ShowParticipantHeader($module->tt("create_password_title"));

if ($verified_participant) {

    // create participant
    $participant = new Participant($module, ["rcpro_participant_id" => $verified_participant["log_id"]]);

    $module->logEvent("Participant opened create password page", [
        "rcpro_username" => $participant->rcpro_username
    ]);

    echo "<p>" . $module->tt("create_password_message3") . "</p>";
?>
    <form action="<?= $module->getUrl("src/create-password.php", true) . "&t=" . urlencode($qstring["t"]); ?>" method="post">
        <div class="form-group">
            <span><?= $module->tt("create_password_username_label") ?><span style="color: #900000; font-weight: bold;"><?= $participant->rcpro_username; ?></span></span>
        </div>
        <div class="form-group">
            <label><?= $module->tt("create_password_new_label") ?></label>
            <input type="password" name="new_password" class="form-control <?php echo (!empty($new_password_err)) ? 'is-invalid' : ''; ?>">
            <span class="invalid-feedback"><?php echo $new_password_err; ?></span>
        </div>
        <div class="form-group">
            <label><?= $module->tt("create_password_confirm_label") ?></label>
            <input type="password" name="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>">
            <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
        </div>
        <div class="form-group">
            <input type="submit" class="btn btn-primary" value="<?= $module->tt("ui_button_submit") ?>">
        </div>
        <input type="hidden" name="token" value="<?= $Auth->get_csrf_token(); ?>">
        <input type="hidden" name="username" value="<?= $participant->rcpro_username ?>">
    </form>
    </div>
    </body>

    </html>
<?php } else {
    echo "<div class='red'>" . $module->tt("create_password_error") . "</div>";
}
