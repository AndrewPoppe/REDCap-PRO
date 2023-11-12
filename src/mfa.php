<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

// Initialize Authentication
$auth = new Auth($module->APPTITLE);
$auth->init();

$project_id = (int) $module->framework->getProject()->getProjectId();

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
$isPost = $_SERVER["REQUEST_METHOD"] == "POST";
if ( $isPost ) {
    try {

        // Is Email
        $emailMfa   = filter_input(INPUT_POST, "emailMfa", FILTER_VALIDATE_BOOLEAN);
        $code          = filter_input(INPUT_POST, "mfa_token", FILTER_VALIDATE_INT);

        if ($emailMfa) {
            $codeIsCorrect = $auth->check_email_mfa_code($code);
        } else {
            $codeIsCorrect = $auth->check_totp_mfa_code($code);
        }
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
$participantEmail = $participantHelper->getEmail($auth->get_participant_id());

// Check if user initiated a resend of email MFA code or if email is the only MFA method enabled
$resend = filter_input(INPUT_GET, "resend", FILTER_VALIDATE_BOOLEAN);
$mfaAuthenticatorAppEnabled = $settings->mfaAuthenticatorAppEnabled($project_id);
if ( $resend || (!$isPost && !$mfaAuthenticatorAppEnabled)) {
    $auth->clear_email_mfa_code();
    $code             = $auth->get_email_mfa_code();
    $module->sendMfaTokenEmail($participantEmail, $code);
}

// Which should be shown?
$showEmail = $resend || $emailMfa;

// This method starts the html doc
$ui = new UI($module);
$ui->ShowParticipantHeader('');
?>

<!-- Email MFA only -->
<!-- Either it is the only MFA method enabled or the user chose Email MFA -->

<div id="emailMFAContainer" style="display: <?= $showEmail ? 'block' : 'none' ?>;">
    <div style="text-align: center;">
        <span style="font-size: large;">
            <?= $resend ? $module->tt("mfa_resend1") : $module->tt("mfa_text1") ?>
            <strong>
                <?= $participantEmail ?>
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
            <?= $mfaAuthenticatorAppEnabled ? '<button type="button" class="btn btn-secondary" onclick="showMFAChoice();">Cancel</button>' : '' ?>
            <input type="submit" class="btn btn-primary" value="<?= 'Submit' ?? $module->tt("mfa_button_text") ?>">
        </div>
        <input type="hidden" name="redcap_csrf_token" value="<?= $module->framework->getCSRFToken() ?>">
        <input type="hidden" name="emailMfa" value="true">
    </form>
    <hr>
    <div style="text-align: center;">
        <?= $module->tt('mfa_resend2') ?> <a href="<?= $module->getUrl("src/mfa.php?resend=true", true); ?>">
            <?= $module->tt('mfa_resend3') ?>
        </a>
    </div>
</div>

<!-- Authenticator App MFA only -->
<!-- User chose Authenticator App MFA -->
<div id="mfaAuthenticatorContainer" style="display: none;">
    <?php if ($mfaAuthenticatorAppEnabled) { ?>
        Testing Authenticator App MFA
    <?php } ?>
</div>


<!-- Choose MFA Method -->
<div id="mfaChoiceContainer" style="display: <?= $showEmail ? 'none' : 'block' ?>;">
    <h4>Choose MFA Method</h4>
    <div class="container" style="border-collapse: collapse;" >
            <div class="row align-items-center p-2 mfa-option" onclick="chooseAuthenticatorAppMFA();">
                <div class="col-1">
                    <span class="fa-layers fa-fw fa-2x" style="color: #900000;">
                        <i class="fa-solid fa-mobile-screen" data-fa-transform="grow-4"></i>
                        <i class="fa-solid fa-lock-hashtag" data-fa-transform="shrink-8 up-2"></i>
                    </span>
                </div>
                <div class="col">
                    <span>
                        <strong style="color: #900000;">Use an Authenticator App</strong>
                        <br>
                        <span style="font-size: small;">Recommended</span>
                    </span>
                </div>
            </div>
            <div class="row align-items-center p-2 mfa-option" onclick="chooseEmailMFA();">
                <div class="col-1">
                    <span class="fa-layers fa-fw fa-2x" style="color: #900000;">
                        <i class="fa-solid fas fa-envelope"></i>
                    </span>
                </div>
                <div class="col">
                    <span>
                        <strong style="color: #900000;">Use Email</strong>
                        <br>
                        <span style="font-size: small;">Not Recommended</span>
                    </span>
                </div>
            </div>
        </ul>
    </div>
</div>

<style>
    .wrapper {
        width: 720px;
    }
    img#rcpro-logo {
        display: block;
        margin-left: auto;
        margin-right: auto;
        left: 0;
    }
    
    .mfa-option {
        cursor: pointer;
        border: 1px solid #e1e1e1;
        border-bottom: none;
    }
    .mfa-option:hover {
        background-color: #f7f6f6;
    }
    .mfa-option:last-child {
        border-bottom: 1px solid #e1e1e1;
    }
    
    a {
        text-decoration: none !important;
        color: #900000 !important;
        font-weight: bold !important;
    }
    a:hover {
        text-shadow: 0px 0px 5px #900000;
    }

    div#emailMFAContainer {
        width: 360px;
        margin: auto;
    }
</style>
<script src="<?= $module->framework->getUrl('lib/jQuery/jquery-3.7.1.min.js', true) ?>"></script>
<script defer src="<?= $module->framework->getUrl('src/js/mfa.js', true) ?>"></script>
<?php $ui->EndParticipantPage(); ?>