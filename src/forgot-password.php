<?php

namespace YaleREDCap\REDCapPRO;

# Initialize authentication session on page
$module::$AUTH->init();

$module::$UI->ShowParticipantHeader($module->tt("forgot_password_title"));

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Validate token
    if (!$module::$AUTH->validate_csrf_token($_POST['token'])) {
        $module->logEvent("Invalid CSRF Token");
        echo $module->tt("error_generic1");
        echo "<br>";
        echo $module->tt("error_generic2");
        return;
    }

    $err = null;

    // Validate username/email
    if (empty(trim($_POST["username"]))) {
        $err = $module->tt("forgot_password_err1");
    } else {
        $username = \REDCap::escapeHtml(trim($_POST["username"]));

        // First, assume the participant typed their REDCapPRO username
        $participant = new Participant($module, ["rcpro_username" => $username]);

        // If the participant does not exist, try again assuming
        // the participant typed their email address
        if (!$participant->exists) {
            $participant = new Participant($module, ["email" => $username]);
        }

        // If there is such a participant, send the password reset email
        if ($participant->exists) {
            $module->logEvent("Password Reset", [
                "rcpro_participant_id" => $participant->rcpro_participant_id,
                "rcpro_username"       => $participant->rcpro_username
            ]);
            $module->sendPasswordResetEmail($participant);
        }
        echo '<div style="text-align: center; font-size: large;"><p><br>' . $module->tt("forgot_password_message1") . '</p></div>';
        return;
    }
}


// set csrf token
$module::$AUTH->set_csrf_token();

echo '<div style="text-align: center;"><p>' . $module->tt("forgot_password_message2") . '</p></div>';
?>
<form action="<?= $module->getUrl("src/forgot-password.php", true); ?>" method="post">
    <div class="form-group">
        <label><?= $module->tt("forgot_password_username_label") ?></label>
        <input type="text" name="username" class="form-control <?php echo (!empty($err)) ? 'is-invalid' : ''; ?>">
        <span class="invalid-feedback"><?php echo $err; ?></span>
    </div>
    <div class="form-group d-grid">
        <input type="submit" class="btn btn-primary" value="<?= $module->tt("ui_button_submit") ?>">
    </div>
    <input type="hidden" name="token" value="<?= $module::$AUTH->get_csrf_token(); ?>">
</form>
<hr>
<div style="text-align: center;">
    <a href="<?= $module->getUrl("src/forgot-username.php", true); ?>"><?= $module->tt("forgot_password_forgot_username") ?></a>
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
<?php $module::$UI->EndParticipantPage(); ?>