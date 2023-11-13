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

// Initialize Participant Helper and get basic participant info
$participantHelper = new ParticipantHelper($module);
$rcpro_participant_id = (int) $auth->get_participant_id();
$participantEmail = $participantHelper->getEmail($rcpro_participant_id);

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
            // Authenticator App
            $mfa_secret = $participantHelper->getMfaSecret($rcpro_participant_id);
            if (empty($mfa_secret)) {
                $mfa_secret = $auth->create_totp_mfa_secret();
                $participantHelper->storeMfaSecret($rcpro_participant_id, $mfa_secret);
            }
            if ($module->framework->throttle('message = "Checked Authenticator App MFA Code" AND participant_id = ?', [$rcpro_participant_id],60, 10)) {
                $codeIsCorrect = false;
                $mfa_err = $module->tt("mfa_err3");
            } else {
                $codeIsCorrect = $auth->check_totp_mfa_code($code, $mfa_secret);
                $module->framework->log("Checked Authenticator App MFA Code", [
                    'participant_id' => $rcpro_participant_id,
                    'codeIsCorrect' => $codeIsCorrect
                ]);
            }
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
            $mfa_err = $mfa_err ?? $module->tt("mfa_err1");
        }
    } catch ( \Throwable $e ) {
        $module->log($e->getMessage());
    }
}

// Check if user initiated a resend of email MFA code or if email is the only MFA method enabled
$resend = filter_input(INPUT_GET, "resend", FILTER_VALIDATE_BOOLEAN);
$mfaAuthenticatorAppEnabled = $settings->mfaAuthenticatorAppEnabled($project_id);
if ( $resend || (!$isPost && !$mfaAuthenticatorAppEnabled)) {
    $auth->clear_email_mfa_code();
    $code = $auth->get_email_mfa_code();
    $module->sendMfaTokenEmail($participantEmail, $code);
}

// Which should be shown?
$showEmail = $resend || $emailMfa || !$mfaAuthenticatorAppEnabled;
$showAuthenticatorApp = !$showEmail && $isPost;

// This method starts the html doc
$ui = new UI($module);
$ui->ShowParticipantHeader('');
?>

<!-- Email MFA only -->
<!-- Either it is the only MFA method enabled or the user chose Email MFA -->

<div id="emailMFAContainer" class="mfaOptionContainer" style="display: <?= $showEmail ? 'block' : 'none' ?>;">
    <div style="text-align: center;">
        <h4>
            <div class="row align-items-center">
                <div class="col-2">
                <span class="fa-layers fa-fw fa-2x" style="color: #900000;">
                            <i class="fa-solid fas fa-envelope"></i>
                        </span>
                </div>
                <div class="col">
                    <?= $module->framework->tt("mfa_text7")?>
                </div>
            </div>    
        </h4>
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
            <!-- <label>
                <?= $module->tt("mfa_text3") ?>
            </label> -->
            <input type="text" name="mfa_token" placeholder="<?= $module->framework->tt("mfa_text3") ?>" class="form-control <?= (!empty($mfa_err)) ? 'is-invalid' : ''; ?>">
            <span class="invalid-feedback">
                <?= $mfa_err; ?>
            </span>
        </div>
        <div class="form-group row">
            <?= $mfaAuthenticatorAppEnabled ? '<div class="col-6"><button type="button" class="btn btn-secondary btn-mfa-control" onclick="window.rcpro.showMFAChoice();">'. $module->tt("mfa_cancel_button_text") . '</button></div>' : '' ?>
            <div class="col"><button type="submit" class="btn btn-primary btn-mfa-control"><?= $module->tt("mfa_submit_button_text") ?></button></div>
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

<!-- Authenticator App MFA -->
<!-- User chose Authenticator App MFA -->
<div id="mfaAuthenticatorContainer" class="mfaOptionContainer" style="display: <?= $showAuthenticatorApp ? 'block' : 'none' ?>;">
    <?php if ($mfaAuthenticatorAppEnabled) { ?>
    <div style="text-align: center;">
        <h4>
            <div class="row align-items-center">
                <div class="col-2">
                    <span class="fa-layers fa-fw fa-2x" style="color: #900000;">
                        <i class="fa-solid fa-mobile-screen" data-fa-transform="grow-4"></i>
                        <i class="fa-solid fa-lock-hashtag" data-fa-transform="shrink-8 up-2"></i>
                    </span>
                </div>
                <div class="col">
                    <?= $module->framework->tt("mfa_text4")?>
                </div>    
            </div>
        </h4>
        <span style="font-size: large;">
            <?= $module->framework->tt("mfa_text5") ?>
        </span>
    </div>

    <form action="<?= $module->getUrl("src/mfa.php", true); ?>" method="post">
        <div class="form-group">
            <!-- <label>
                <?= $module->tt("mfa_text6") ?>
            </label> -->
            <input type="text" name="mfa_token" placeholder="<?= $module->framework->tt("mfa_text6") ?>" class="form-control <?= (!empty($mfa_err)) ? 'is-invalid' : ''; ?>">
            <span class="invalid-feedback">
                <?= $mfa_err; ?>
            </span>
        </div>
        <div class="form-group row">
            <div class="col-6"><button type="button" class="btn btn-secondary btn-mfa-control" onclick="window.rcpro.showMFAChoice();"><?=$module->tt("mfa_cancel_button_text")?></button></div>
            <div class="col"><button type="submit" class="btn btn-primary btn-mfa-control"><?= $module->tt("mfa_submit_button_text") ?></button></div>
        </div>
        <input type="hidden" name="redcap_csrf_token" value="<?= $module->framework->getCSRFToken() ?>">
        <input type="hidden" name="authApp" value="true">
    </form>
    <hr>
    <div style="text-align: center;">
        <a href="javascript:;" onclick="window.rcpro.showMFAInfo();return false;">
            <?= $module->tt('mfa_info1') ?>
        </a>
    </div>
    <?php } ?>
</div>


<!-- Choose MFA Method -->
<div id="mfaChoiceContainer" style="display: <?= ($showEmail || $showAuthenticatorApp) ? 'none' : 'block' ?>;">
    <h4>Choose MFA Method</h4>
    <div class="container" style="border-collapse: collapse;" >
            <div class="row align-items-center p-2 mfa-option" onclick="window.rcpro.chooseAuthenticatorAppMFA();">
                <div class="col-2">
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
            <div class="row align-items-center p-2 mfa-option" onclick="window.rcpro.chooseEmailMFA();">
                <div class="col-2">
                    <span class="fa-layers fa-fw fa-2x" style="color: #900000;">
                        <i class="fa-solid fas fa-envelope"></i>
                    </span>
                </div>
                <div class="col" id="emailChoice">
                    <span>
                        <strong style="color: #900000;">Use Email</strong>
                        <br>
                        <span style="font-size: small;">Not Recommended</span>
                    </span>
                </div>
                <div class="col mfaLoading text-center" id="emailLoading" style="display: none;">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </ul>
    </div>
</div>

<style>
    .wrapper {
        width: 540px;
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

    div.mfaOptionContainer  {
        width: 360px;
        margin: auto;
    }

    button.btn-mfa-control {
        width: 100%;
    }

    .mfaLoading {
        color: #900000;
    }
</style>
<script src="<?= $module->framework->getUrl('lib/jQuery/jquery-3.7.1.min.js', true) ?>"></script>
<?php $module->framework->initializeJavascriptModuleObject(); ?>
<script>

    window.rcpro = <?= $module->framework->getJavascriptModuleObjectName() ?>;

    window.rcpro.chooseAuthenticatorAppMFA = function() {
        $('#mfaChoiceContainer').hide();
        $('#mfaAuthenticatorContainer').show();
    }

    window.rcpro.chooseEmailMFA = function() {
        window.rcpro.showEmailLoading();
        window.rcpro.ajax('sendMfaTokenEmail', [])
            .then(function(result) {
                window.rcpro.hideEmailLoading();
                if (!result) {
                    console.log('Error sending email');
                    return;
                }
                $('#mfaChoiceContainer').hide();
                $('#emailMFAContainer').show();
            });
    }

    window.rcpro.showMFAChoice = function() {
        $('#mfaChoiceContainer').show();
        $('#mfaAuthenticatorContainer').hide();
        $('#emailMFAContainer').hide();
    }

    window.rcpro.showMFAInfo = function() {
        window.rcpro.ajax('showMFAInfo', [])
            .then(function(result) {
                if (!result) {
                    console.log('Error showing MFA info');
                    return;
                }
                window.rcpro.showModal(result);
            });
    }

    window.rcpro.showModal = function(results) {
        console.log(results);
        const modal = $('#authAppInfoTemplate').clone();
        $(modal).find('#authenticatorAppQr').attr('src', results.url);
        modal.modal('show');
    }

    window.rcpro.showEmailLoading = function() {
        $('#emailLoading').show();
        $('#emailChoice').hide();
    }

    window.rcpro.hideEmailLoading = function() {
        $('#emailLoading').hide();
        $('#emailChoice').show();
    }

</script>
<!-- Authenticator App Info Modal Template -->
<div class="modal fade" id="authAppInfoTemplate" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="authAppInfoLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="authAppInfoLabel">TESTTESTTEST</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <img id="authenticatorAppQr">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">TESTTESTTEST</button>
        <button type="button" class="btn btn-primary">TESTTESTTEST</button>
      </div>
    </div>
  </div>
</div>
<?php $ui->EndParticipantPage(); ?>