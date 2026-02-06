<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

# Initialize authentication session on page
$auth = new Auth($module->APPTITLE);
$auth->init();

// Language Helper
$language = new Language($module);
$language->handleLanguageChangeRequest();

$ui = new UI($module);
$ui->ShowParticipantHeader($module->tt("forgot_password_title"));

// Processing form data when form is submitted
if ( $_SERVER["REQUEST_METHOD"] === "POST" ) {

    $err = null;

    // Validate username/email
    if ( empty(trim($_POST["username"])) ) {
        $err = $module->tt("forgot_password_err1");
    } else {
        $username = \REDCap::escapeHtml(trim($_POST["username"]));
        // Check input errors before sending reset email
        if ( !$err ) {
            $participantHelper    = new ParticipantHelper($module);
            $rcpro_participant_id = $participantHelper->getParticipantIdFromUsername($username);
            if ( !isset($rcpro_participant_id) ) {
                $rcpro_participant_id = $participantHelper->getParticipantIdFromEmail($username);
            }
            if ( isset($rcpro_participant_id) ) {
                $module->logEvent("Password Reset Email Sent", [
                    "rcpro_participant_id" => $rcpro_participant_id,
                    "rcpro_username"       => $username
                ]);
                $module->sendPasswordResetEmail($rcpro_participant_id, true);
            }
            echo '<div style="text-align: center; font-size: large;"><p><br>' . $module->tt("forgot_password_message1") . '</p></div>';
            return;
        }
    }
}

echo '<div style="text-align: center;"><p>' . $module->tt("forgot_password_message2") . '</p></div>';
?>
<form action="<?= $module->getUrl("src/forgot-password.php", true); ?>" method="post">
    <div class="form-group">
        <label>
            <?= $module->tt("forgot_password_username_label") ?>
        </label>
        <input type="text" name="username" class="form-control <?php echo (!empty($err)) ? 'is-invalid' : ''; ?>" autocomplete="username" inputmode="email">
        <span class="invalid-feedback">
            <?php echo $err; ?>
        </span>
    </div>
    <div class="form-group d-grid">
        <input type="submit" class="btn btn-primary" value="<?= $module->tt("ui_button_submit") ?>">
    </div>
    <input type="hidden" name="redcap_csrf_token" value="<?= $module->framework->getCSRFToken() ?>">
</form>
<hr>
<div style="text-align: center;">
    <a href="<?= $module->getUrl("src/forgot-username.php", true); ?>">
        <?= $module->tt("forgot_password_forgot_username") ?>
    </a>
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
<?php $ui->EndParticipantPage(); ?>