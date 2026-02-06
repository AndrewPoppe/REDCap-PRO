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
$ui->ShowParticipantHeader($module->tt("forgot_username_title"));

// Processing form data when form is submitted
if ( $_SERVER["REQUEST_METHOD"] === "POST" ) {

    $err = null;

    // Validate username
    if ( empty(trim($_POST["email"])) ) {
        $err = $module->tt("forgot_username_err1");
    } else {
        $email = \REDCap::escapeHtml(trim($_POST["email"]));
        // Check input errors before sending reset email
        if ( !$err ) {
            $participantHelper    = new ParticipantHelper($module);
            $rcpro_participant_id = $participantHelper->getParticipantIdFromEmail($email);
            if ( !empty($rcpro_participant_id) ) {
                $username = $participantHelper->getUserName($rcpro_participant_id);
                $module->logEvent("Username Reminder Email Sent", [
                    "rcpro_participant_id" => $rcpro_participant_id,
                    "rcpro_username"       => $username
                ]);
                $module->sendUsernameEmail($email, $username);
            }
            echo $module->tt("forgot_username_message1");
            return;
        }
    }
}

echo '<div style="text-align: center;"><p>' . $module->tt("forgot_username_message2") . '</p></div>';
?>
<form action="<?= $module->getUrl("src/forgot-username.php", true); ?>" method="post">
    <div class="form-group">
        <label>
            <?= $module->tt("forgot_username_email_label") ?>
        </label>
        <input type="email" name="email" class="form-control <?php echo (!empty($err)) ? 'is-invalid' : ''; ?>" autocomplete="username">
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
    <a href="<?= $module->getUrl("src/forgot-password.php", true); ?>">
        <?= $module->tt("forgot_username_forgot_password") ?>
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