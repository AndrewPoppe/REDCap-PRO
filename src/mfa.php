<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

// Initialize Authentication
$auth = new Auth($module->APPTITLE);
$auth->init();

// Check if user is logged in
if ( !$auth->is_logged_in() ) {
    header("location: " . $module->getUrl("src/login.php", true));
    return;
}

// Check if MFA is enabled
$settings = new ProjectSettings($module);
if ( !$settings->mfaEnabled((int) $module->framework->getProjectId()) ) {
    header("location: " . $module->getUrl("src/login.php", true));
    return;
}

// Processing form data when form is submitted
if ( $_SERVER["REQUEST_METHOD"] == "POST" ) {
    try {
        $code          = filter_input(INPUT_POST, "mfa_token", FILTER_VALIDATE_INT);
        $codeIsCorrect = $auth->check_mfa_code($code);
        if ( $codeIsCorrect ) {
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
        } else {
            $mfa_err = $module->tt("mfa_err1");
        }
    } catch ( \Throwable $e ) {
        $module->log($e->getMessage());
    }
}

$participantHelper = new ParticipantHelper($module);

$resend = filter_input(INPUT_GET, "resend", FILTER_VALIDATE_BOOLEAN);
if ( $resend ) {
    $auth->clear_mfa_code();
    $code             = $auth->get_mfa_code();
    $participantEmail = $participantHelper->getEmail($auth->get_participant_id());
    $module->sendMfaTokenEmail($participantEmail, $code);
}

// This method starts the html doc
$ui = new UI($module);
$ui->ShowParticipantHeader('');
?>

<div style="text-align: center;">
    <span style="font-size: large;">
        <?= $resend ? $module->tt("mfa_resend1") : $module->tt("mfa_text1") ?>
        <strong>
            <?= $participantHelper->getEmail($auth->get_participant_id()) ?>
        </strong>
        <br>
        <?= $module->tt("mfa_text2") ?>
    </span>
</div>

<form action="<?= $module->getUrl("src/mfa.php", true); ?>" method="post">
    <div class="form-group">
        <label>
            <?= $module->tt("mfa_text3") ?>
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
    <?= $module->tt('mfa_resend2') ?> <a href="<?= $module->getUrl("src/mfa.php?resend=true", true); ?>">
        <?= $module->tt('mfa_resend3') ?>
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