<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

// Initialize Authentication
$auth = new Auth($module->APPTITLE);
$auth->init();

# Parse query string to grab token.
parse_str($_SERVER['QUERY_STRING'], $qstring);

// Redirect to login page if we shouldn't be here
if ( !isset($qstring["t"]) && $_SERVER["REQUEST_METHOD"] !== "POST" ) {
    header("location: " . $module->getUrl("src/login.php", true));
    return;
}

// Get survey hash if included
if ( isset($qstring['s']) ) {
    $surveyHash = $module->framework->escape(filter_var($qstring['s'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $auth->set_survey_url(APP_PATH_SURVEY_FULL . "?s=$surveyHash");
}

// Define variables and initialize with empty values
$new_password     = $confirm_password = "";
$new_password_err = $confirm_password_err = "";

// UI
$ui = new UI($module);

// Verify password reset token
$participantHelper = new ParticipantHelper($module);
$verified_participant = $participantHelper->verifyPasswordResetToken($qstring["t"]);

// Processing form data when form is submitted
if ( $_SERVER["REQUEST_METHOD"] === "POST" ) {

    // Validate password
    $new_password = trim($_POST["new_password"]);
    if ( empty($new_password) ) {
        $new_password_err = $module->tt("create_password_message1");
        $any_error        = TRUE;
    } else {
        // Validate password strength
        $settings     = new ProjectSettings($module);
        $pw_len_req   = $settings->getPasswordLength();
        $uppercase    = preg_match('@[A-Z]@', $new_password);
        $lowercase    = preg_match('@[a-z]@', $new_password);
        $number       = preg_match('@[0-9]@', $new_password);
        $specialChars = preg_match('@[^\w]@', $new_password);
        $goodLength   = strlen($new_password) >= $pw_len_req;

        if ( !$uppercase || !$lowercase || !$number || !$specialChars || !$goodLength ) {
            $new_password_err = implode("<br>- ", [
                $module->tt("create_password_message2", $pw_len_req),
                $module->tt("create_password_upper"),
                $module->tt("create_password_lower"),
                $module->tt("create_password_number"),
                $module->tt("create_password_special")
            ]);
            $any_error        = TRUE;
        }
    }

    // Validate confirm password
    $confirm_password = trim($_POST["confirm_password"]);
    if ( empty($confirm_password) ) {
        $confirm_password_err = $module->tt("create_password_confirm");
        $any_error            = TRUE;
    } else {
        if ( empty($new_password_err) && ($new_password !== $confirm_password) ) {
            $confirm_password_err = $module->tt("create_password_no_match");
            $any_error            = TRUE;
        }
    }

    // Check input errors before updating the database
    if ( !$any_error ) {

        // Grab all user details
        $participant = $participantHelper->getParticipant($_POST["username"]);

        // Update password
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $result        = $participantHelper->storeHash($password_hash, $participant["log_id"]);
        if ( empty($result) || $result === FALSE ) {
            echo $module->tt("error_generic1");
            echo $module->tt("error_generic2");
            return;
        }

        // Password was successfully set. Expire the token.
        $participantHelper->expirePasswordResetToken($participant["log_id"]);

        // Store data in session variables
        $auth->set_login_values($participant);

        $ui->ShowParticipantHeader($module->tt("create_password_set"));
        if ( !empty($surveyHash) ) {
            global $lang;
            echo "<div style='text-align:center;'><a class='btn btn-primary' role='button' href='" . $auth->get_survey_url() . "'>" . $lang["multilang_690"] . "</button></div>";
        } else {
            echo "<div style='text-align:center;'><p>" . $module->tt("ui_close_tab") . "</p></div>";
        }
        $ui->EndParticipantPage();
        return;
    }
}

$ui->ShowParticipantHeader($module->tt("create_password_title"));

if ( $verified_participant ) {
    // They have a valid token. Set their Login status to true.
    $auth->set_login_values($verified_participant);

    $module->logEvent("Participant opened create password page", [
        "rcpro_username" => $verified_participant["rcpro_username"]
    ]);

    echo "<p>" . $module->tt("create_password_message3") . "</p>";
    ?>
    <form action="<?= $module->getUrl("src/create-password.php", true) . "&t=" . urlencode($qstring["t"]) . (isset($surveyHash) ? "&s=" . urlencode($surveyHash) : ""); ?>"
        method="post">
        <div class="form-group">
            <span>
                <?= $module->tt("create_password_username_label") ?><span style="color: #900000; font-weight: bold;">
                    <?= $verified_participant["rcpro_username"]; ?>
                </span>
            </span>
        </div>
        <div class="form-group">
            <label>
                <?= $module->tt("create_password_new_label") ?>
            </label>
            <input type="password" name="new_password"
                class="form-control <?php echo (!empty($new_password_err)) ? 'is-invalid' : ''; ?>" autocomplete="new-password">
            <span class="invalid-feedback">
                <?php echo $new_password_err; ?>
            </span>
        </div>
        <div class="form-group">
            <label>
                <?= $module->tt("create_password_confirm_label") ?>
            </label>
            <input type="password" name="confirm_password"
                class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" autocomplete="new-password">
            <span class="invalid-feedback">
                <?php echo $confirm_password_err; ?>
            </span>
        </div>
        <div class="form-group">
            <input type="submit" class="btn btn-primary" value="<?= $module->tt("ui_button_submit") ?>">
        </div>
        <input type="hidden" name="redcap_csrf_token" value="<?= $module->framework->getCSRFToken() ?>">
        <input type="hidden" name="username" value="<?= $verified_participant["rcpro_username"] ?>">
    </form>
    </div>
    </body>

    </html>
<?php } else {
    echo "<div class='red'>" . $module->tt("create_password_error") . "</div>";
}