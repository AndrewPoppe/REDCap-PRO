<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

// Initialize Authentication
$module->AUTH->init();

// Check if user is logged in
if (!$module->AUTH->is_logged_in()) {
    header("location: " . $module->getUrl("src/login.php", true));
    return;
}

// Check if MFA is enabled
$settings = new ProjectSettings($module);
if (!$settings->mfaEnabled((int) $module->framework->getProjectId())) {
    header("location: " . $module->getUrl("src/login.php", true));
    return;
}

// Processing form data when form is submitted
if ( $_SERVER["REQUEST_METHOD"] == "POST" ) {
    try{
        $code          = filter_input(INPUT_POST, "mfa_token", FILTER_VALIDATE_INT);
        $codeIsCorrect = $module->AUTH->check_mfa_code($code);
        if ($codeIsCorrect) {
            // Redirect user to appropriate page
            if ( $module->AUTH->is_survey_url_set() ) {
                header("location: " . $module->AUTH->get_survey_url());
            } elseif ( isset($qstring["s"]) ) {
                header("location: " . APP_PATH_SURVEY_FULL . $_SERVER['QUERY_STRING']);
            } else {
                $study_contact = $module->getContactPerson();
                echo $module->tt("login_err9");
                if ( isset($study_contact["name"]) ) {
                    echo ":<br>" . $study_contact["info"];
                }
            }
        } else {
            $mfa_err = "The code you entered is incorrect.";
        }
    } catch (\Throwable $e) {
        $module->log($e->getMessage());
    }
}

$resend = filter_input(INPUT_GET, "resend", FILTER_VALIDATE_BOOLEAN);
if ($resend) {
    $module->AUTH->clear_mfa_code();
    $code             = $module->AUTH->get_mfa_code();
    $participantEmail = $module->PARTICIPANT->getEmail($module->AUTH->get_participant_id());
    $module->sendMfaTokenEmail($participantEmail, $code);
}

// This method starts the html doc
$module->UI->ShowParticipantHeader('');
?>

<div style="text-align: center;">
    <span style="font-size: large;">
        <?= $resend ? "A new code has been sent to the email address:" : 
        "A 6-digit code has been sent to the email address:" ?>
        <strong><?= $module->PARTICIPANT->getEmail($module->AUTH->get_participant_id())?></strong>
        <br>
        Please enter it below.
    </span>
</div>

<form action="<?= $module->getUrl("src/mfa.php", true); ?>" method="post">
    <div class="form-group">
        <label>
            Enter 6-digit code:
        </label>
        <input type="text" name="mfa_token" class="form-control <?= (!empty($mfa_err)) ? 'is-invalid' : ''; ?>">
        <span class="invalid-feedback">
            <?= $mfa_err; ?>
        </span>
    </div>
    <div class="form-group d-grid">
        <input type="submit" class="btn btn-primary" value="<?= 'Submit' ?? $module->tt("mfa_button_text") ?>">
    </div>
    <input type="hidden" name="redcap_csrf_token" value="<?= $module->framework->getCSRFToken() ?>">
</form>
<hr>
<div style="text-align: center;">
    Need a new code? <a href="<?= $module->getUrl("src/mfa.php?resend=true", true); ?>">Resend</a>
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
<?php $module->UI->EndParticipantPage(); ?>