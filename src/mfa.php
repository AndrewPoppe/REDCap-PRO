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
$participantHelper    = new ParticipantHelper($module);
$rcpro_participant_id = (int) $auth->get_participant_id();
$participantEmail     = $participantHelper->getEmail($rcpro_participant_id);

// Check if Authenticator App Secret already exists, if applicable
$showFullAuthenticatorAppInfo = false;
$mfa_secret                   = $participantHelper->getMfaSecret($rcpro_participant_id);
if ( empty($mfa_secret) && $settings->mfaAuthenticatorAppEnabled($project_id) ) {
    $showFullAuthenticatorAppInfo = true;
    $mfa_secret                   = $auth->create_totp_mfa_secret();
    $participantHelper->storeMfaSecret($rcpro_participant_id, $mfa_secret);
}

// Processing form data when form is submitted
$isPost = $_SERVER["REQUEST_METHOD"] == "POST";
if ( $isPost ) {
    try {

        // Is Email
        $emailMfa = filter_input(INPUT_POST, "emailMfa", FILTER_VALIDATE_BOOLEAN);
        $code     = filter_input(INPUT_POST, "mfa_token", FILTER_VALIDATE_INT);
        if ( $emailMfa ) {
            $thisPreferredMethod = 'email';
            $codeIsCorrect       = $auth->check_email_mfa_code($code);
        } else {
            // Authenticator App
            if ( $module->framework->throttle('message = "Checked Authenticator App MFA Code" AND participant_id = ?', [ $rcpro_participant_id ], 60, 10) ) {
                $codeIsCorrect = false;
                $mfa_err       = $module->framework->tt("mfa_err3");
            } else {
                $thisPreferredMethod = 'authenticator-app';
                $codeIsCorrect       = $auth->check_totp_mfa_code($code, $mfa_secret);
                $module->framework->log("Checked Authenticator App MFA Code", [
                    'participant_id' => $rcpro_participant_id,
                    'codeIsCorrect'  => $codeIsCorrect
                ]);
            }
        }
        if ( $codeIsCorrect ) {
            // Set MFA method preference
            $participantHelper->setMfaMethodPreference($rcpro_participant_id, $thisPreferredMethod);
            // Set MFA verification status
            $auth->set_mfa_verification_status(true);
            // Redirect user to appropriate page
            if ( $auth->is_survey_url_set() ) {
                header("location: " . $auth->get_survey_url());
            } elseif ( isset($qstring["s"]) ) {
                header("location: " . APP_PATH_SURVEY_FULL . $_SERVER['QUERY_STRING']);
            } else {
                $study_contact = $module->getContactPerson();
                echo $module->framework->tt("login_err9");
                if ( isset($study_contact["name"]) ) {
                    echo ":<br>" . $study_contact["info"];
                }
            }
        } else {
            $mfa_err = $mfa_err ?? $module->framework->tt("mfa_err1");
        }
    } catch ( \Throwable $e ) {
        $module->log($e->getMessage());
    }
}

// Is the option to use an Authenticator App enabled?
$mfaAuthenticatorAppEnabled = $settings->mfaAuthenticatorAppEnabled($project_id);

// Get user's current MFA method preference
$mfaMethodfPreference = $mfaAuthenticatorAppEnabled ? $participantHelper->getMfaMethodPreference($rcpro_participant_id) : null;

// Check if user initiated a resend of email MFA code or if email is the only MFA method enabled or if email is the user's current preference
$resend = filter_input(INPUT_GET, "resend", FILTER_VALIDATE_BOOLEAN);

if ( $resend || (!$isPost && !$mfaAuthenticatorAppEnabled) || (!$isPost && $mfaMethodfPreference === 'email') ) {
    $auth->clear_email_mfa_code();
    $code = $auth->get_email_mfa_code();
    $module->sendMfaTokenEmail($participantEmail, $code);
}

// Which should be shown?
$showEmail            = $mfaMethodfPreference === 'email' || $resend || $emailMfa || !$mfaAuthenticatorAppEnabled;
$showAuthenticatorApp = !$showEmail && ($isPost || $mfaMethodfPreference === 'authenticator-app');

// This method starts the html doc
$ui = new UI($module);
$ui->ShowParticipantHeader($module->framework->tt('mfa_text8'));

?>

<!-- Email MFA only -->
<!-- Either it is the only MFA method enabled or the user chose Email MFA or it is their current preference -->


<div id="emailMFAContainer" class="mfaOptionContainer" style="display: <?= $showEmail ? 'block' : 'none' ?>;">
    <div style="text-align: center;">
        <h4>
            <div class="row align-items-center">
                <div class="col-2">
                    <span class="fa-layers fa-fw fa-2x text-rcpro">
                        <i class="fa-solid fas fa-envelope"></i>
                    </span>
                </div>
                <div class="col">
                    <?= $module->framework->tt("mfa_text7") ?>
                </div>
            </div>
        </h4>
        <span style="font-size: large;">
            <?= $resend ? $module->framework->tt("mfa_resend1") : $module->framework->tt("mfa_text1") ?>
            <strong>
                <?= $participantEmail ?>
            </strong>
            <br>
            <?= $module->framework->tt("mfa_text2") ?>
        </span>
    </div>

    <form class="needs-validation" action="<?= $module->getUrl("src/mfa.php", true); ?>" method="post" novalidate>
        <div class="form-group">
            <input type="text" name="mfa_token" placeholder="<?= $module->framework->tt("mfa_text3") ?>"
                class="form-control <?= (!empty($mfa_err)) ? 'is-invalid' : ''; ?>" required pattern="\d{6,6}"
                inputmode="numeric" autocomplete="one-time-code">
            <span class="invalid-feedback" data-original="<?= $module->framework->tt("mfa_err5"); ?>">
                <?= $mfa_err ?>
            </span>
        </div>
        <div class="form-group row">
            <?= $mfaAuthenticatorAppEnabled ? '<div class="col-6"><button type="button" class="btn btn-secondary btn-mfa-control" onclick="window.rcpro.showMFAChoice();">' . $module->framework->tt("mfa_cancel_button_text2") . '</button></div>' : '' ?>
            <div class="col"><button type="submit" class="btn btn-primary btn-mfa-control">
                    <?= $module->framework->tt("mfa_submit_button_text") ?>
                </button></div>
        </div>
        <input type="hidden" name="redcap_csrf_token" value="<?= $module->framework->getCSRFToken() ?>">
        <input type="hidden" name="emailMfa" value="true">
    </form>
    <hr>
    <div style="text-align: center;">
        <?= $module->framework->tt('mfa_resend2') ?> <a href="<?= $module->getUrl("src/mfa.php?resend=true", true); ?>">
            <?= $module->framework->tt('mfa_resend3') ?>
        </a>
    </div>
</div>

<!-- Authenticator App MFA -->
<!-- User chose Authenticator App MFA -->
<div id="mfaAuthenticatorContainer" class="mfaOptionContainer"
    style="display: <?= $showAuthenticatorApp ? 'block' : 'none' ?>;">
    <?php if ( $mfaAuthenticatorAppEnabled ) { ?>
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
                        <?= $module->framework->tt("mfa_text4") ?>
                    </div>
                </div>
            </h4>
            <span style="font-size: large;">
                <?= $module->framework->tt("mfa_text5") ?>
            </span>
        </div>

        <form class="needs-validation" action="<?= $module->getUrl("src/mfa.php", true); ?>" method="post" novalidate>
            <div class="form-group">
                <input type="text" name="mfa_token" placeholder="<?= $module->framework->tt("mfa_text6") ?>"
                    class="form-control <?= (!empty($mfa_err)) ? 'is-invalid' : ''; ?>" required pattern="\d{6,8}"
                    inputmode="numeric" autocomplete="one-time-code">
                <span class="invalid-feedback" data-original="<?= $module->framework->tt("mfa_err5"); ?>">
                    <?= $mfa_err ?>
                </span>
            </div>
            <div class="form-group row">
                <div class="col-6"><button type="button" class="btn btn-secondary btn-mfa-control"
                        onclick="window.rcpro.showMFAChoice();">
                        <?= $module->framework->tt("mfa_cancel_button_text2") ?>
                    </button>
                </div>
                <div class="col"><button type="submit" class="btn btn-primary btn-mfa-control">
                        <?= $module->framework->tt("mfa_submit_button_text") ?>
                    </button></div>
            </div>
            <input type="hidden" name="redcap_csrf_token" value="<?= $module->framework->getCSRFToken() ?>">
            <input type="hidden" name="authApp" value="true">
        </form>
        <hr>
        <div style="text-align: center;">
            <?= $module->framework->tt('mfa_info25') ?>
            <a href="javascript:;" onclick="window.rcpro.showMFAInfo();return false;">
                <?= $showFullAuthenticatorAppInfo ? $module->framework->tt('mfa_info1') : $module->framework->tt('mfa_info24') ?>
            </a>
        </div>
    <?php } ?>
</div>


<!-- Choose MFA Method -->
<div id="mfaChoiceContainer" style="display: <?= ($showEmail || $showAuthenticatorApp) ? 'none' : 'block' ?>;">
    <div style="text-align: center;">
        <p>
            <?= $module->tt("mfa_text9") ?>
        </p>
        <p>
            <?= $module->tt("mfa_text10") ?>
        </p>
    </div>
    <div class="container" style="border-collapse: collapse;">
        <div class="row align-items-center p-2 mfa-option" onclick="window.rcpro.chooseAuthenticatorAppMFA();">
            <div class="col-2">
                <span class="fa-layers fa-fw fa-2x text-rcpro">
                    <i class="fa-solid fa-mobile-screen" data-fa-transform="grow-4"></i>
                    <i class="fa-solid fa-lock-hashtag" data-fa-transform="shrink-8 up-2"></i>
                </span>
            </div>
            <div class="col">
                <span>
                    <strong class="text-rcpro">
                        <?= $module->framework->tt('mfa_text11') ?>
                    </strong>
                    <br>
                    <span style="font-size: small;">
                        <?= $module->framework->tt('mfa_text13') ?>
                    </span>
                </span>
            </div>
        </div>
        <div class="row align-items-center p-2 mfa-option" onclick="window.rcpro.chooseEmailMFA();">
            <div class="col-2">
                <span class="fa-layers fa-fw fa-2x text-rcpro">
                    <i class="fa-solid fas fa-envelope"></i>
                </span>
            </div>
            <div class="col" id="emailChoice">
                <span>
                    <strong class="text-rcpro">
                        <?= $module->framework->tt('mfa_text12') ?>
                    </strong>
                    <br>
                    <span style="font-size: small;">
                        <?= $module->framework->tt('mfa_text14') ?>
                    </span>
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
        color:
            <?= $module::$COLORS["primary"] ?>
            !important;
        font-weight: bold !important;
    }

    a:hover {
        text-shadow: 0px 0px 5px
            <?= $module::$COLORS["primary"] ?>
        ;
    }

    div.mfaOptionContainer {
        width: 360px;
        margin: auto;
    }

    button.btn-mfa-control {
        width: 100%;
    }

    .mfaLoading {
        color:
            <?= $module::$COLORS["primary"] ?>
        ;
    }

    .text-rcpro {
        color:
            <?= $module::$COLORS["primary"] ?>
        ;
    }

    .bg-rcpro {
        background-color:
            <?= $module::$COLORS["primary"] ?>
        ;
    }

    .border-rcpro {
        border-color:
            <?= $module::$COLORS["primary"] ?>
            !important;
    }

    .accordion-rcpro button.accordion-button {
        color:
            <?= $module::$COLORS["primary"] ?>
        ;
    }

    .accordion-rcpro button.accordion-button:hover {
        background-color:
            <?= $module::$COLORS["primaryExtraLight"] ?>
            80;
    }

    .accordion-rcpro button.accordion-button:not(.collapsed) {
        background-color:
            <?= $module::$COLORS["primaryExtraLight"] ?>
        ;
    }

    .accordion-rcpro button.accordion-button:focus {
        box-shadow: none;
    }

    .accordion-rcpro button.accordion-button:not(.collapsed)::after {
        background-image: url("data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23900000'><path fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/></svg>") !important;
    }

    .fast-spin {
        -webkit-animation: fa-spin 0.5s infinite linear;
        animation: fa-spin 0.5s infinite linear;
    }
</style>
<script src="<?= $module->framework->getUrl('lib/jQuery/jquery-3.7.1.min.js', true) ?>"></script>
<?php $module->framework->initializeJavascriptModuleObject(); ?>
<script>

    window.rcpro = <?= $module->framework->getJavascriptModuleObjectName() ?>;

    window.rcpro.chooseAuthenticatorAppMFA = function () {
        $('.is-invalid').removeClass('is-invalid');
        $('#mfaChoiceContainer').hide();
        $('#mfaAuthenticatorContainer').show();
    }

    window.rcpro.chooseEmailMFA = function () {
        $('.is-invalid').removeClass('is-invalid');
        window.rcpro.showEmailLoading();
        window.rcpro.ajax('sendMfaTokenEmail', [])
            .then(function (result) {
                window.rcpro.hideEmailLoading();
                if (!result) {
                    window.rcpro.showToast('error', 'Error sending email');
                    return;
                }
                $('#mfaChoiceContainer').hide();
                $('#emailMFAContainer').show();
            });
    }

    window.rcpro.showMFAChoice = function () {
        $('#mfaChoiceContainer').show();
        $('#mfaAuthenticatorContainer').hide();
        $('#emailMFAContainer').hide();
    }

    window.rcpro.showMFAInfo = function () {
        <?php if ( $showFullAuthenticatorAppInfo ) { ?>
            window.rcpro.toasts.loading.show();
            window.rcpro.ajax('showMFAInfo', [])
                .then(function (result) {
                    window.rcpro.toasts.loading.hide();
                    if (!result) {
                        console.log('Error showing MFA info');
                        return;
                    }
                    window.rcpro.showModal(result);
                })
                .catch(function (error) {
                    window.rcpro.showToast('error', error);
                });
        <?php } else { ?>
            window.rcpro.toasts.loading.show();
            window.rcpro.ajax('sendMFAInfo', [])
                .then(function (result) {
                    if (!result) {
                        window.rcpro.showToast('error', 'Error sending Authenticator info');
                        return;
                    }
                    window.rcpro.showToast('success', 'Sent email with Authenticator info');
                })
                .catch(function (error) {
                    window.rcpro.showToast('error', error);
                });
        <?php } ?>
    }

    window.rcpro.showModal = function (results) {
        console.log(results);
        const modal = $('#authAppInfoModal');
        modal.find('#authenticatorAppQr').attr('src', results.url);
        modal.find('#authenticatorAppUrl').attr('href', results.url);
        modal.find('#authAppAccountName').text(results.username);
        modal.find('#authAppAccountKey').text(results.mfa_secret);
        modal.modal('show');
    }

    window.rcpro.showToast = function (type, message = '') {
        window.rcpro.toasts.loading.hide();
        const toast = window.rcpro.toasts[type];
        toast._element.querySelector('.message').innerText = new String(message).trim() ?? '';
        toast.show();
    }

    window.rcpro.showEmailLoading = function () {
        $('#emailLoading').show();
        $('#emailChoice').hide();
    }

    window.rcpro.hideEmailLoading = function () {
        $('#emailLoading').hide();
        $('#emailChoice').show();
    }

    $(document).ready(function () {
        const loadToastEl = document.getElementById('loadingToast');
        const loadToast = new bootstrap.Toast(loadToastEl, { autohide: false });

        const successToastEl = document.getElementById('successToast');
        const successToast = new bootstrap.Toast(successToastEl, { autohide: true });

        const errorToastEl = document.getElementById('errorToast');
        const errorToast = new bootstrap.Toast(errorToastEl, { autohide: true });

        window.rcpro.toasts = {
            'loading': loadToast,
            'success': successToast,
            'error': errorToast
        };

        $('.needs-validation').each(function () {
            $(this).on('submit', function (event) {
                $(this).find('is-invalid').removeClass('is-invalid');
                $(this).find('.invalid-feedback').each(function () {
                    $(this).text($(this).data('original'));
                });
                if (!this.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                $(this).addClass('was-validated');
            });
        });
    });
</script>
<!-- Authenticator App Info Modal -->
<div class="modal fade" id="authAppInfoModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="authAppInfoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-rcpro text-light">
                <h5 class="modal-title" id="authAppInfoLabel">
                    <?= $module->framework->tt('mfa_info2') ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h4><i class="fa-regular fa-circle-exclamation text-rcpro"></i>
                    <?= $module->framework->tt('mfa_info22') ?>
                </h4>
                <p>
                    <?= $module->framework->tt('mfa_info23') ?>
                </p>
                <div class="accordion accordion-rcpro" id="authAppInfoAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="authAppHeading1">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse"
                                data-bs-target="#authAppInfoStep1" aria-expanded="true"
                                aria-controls="authAppInfoStep1">
                                <strong>
                                    <?= $module->framework->tt('mfa_info3') ?>
                                </strong>
                            </button>
                        </h2>
                        <div id="authAppInfoStep1" class="accordion-collapse collapse show"
                            aria-labelledby="authAppHeading1" data-bs-parent="#authAppInfoAccordion">
                            <div class="accordion-body">
                                <p>
                                    <?= $module->framework->tt('mfa_info4') ?>
                                </p>
                                <ul>
                                    <li>
                                        <?= $module->framework->tt('mfa_info5') ?>
                                        <ul>
                                            <li><img style="width: 2rem;"
                                                    src="<?= $module->framework->getUrl('images/ga.webp', true) ?>">
                                                <strong>
                                                    <?= $module->framework->tt('mfa_info20') ?>
                                                </strong>
                                            </li>
                                            <li><img style="width: 2rem;"
                                                    src="<?= $module->framework->getUrl('images/ma.webp', true) ?>">
                                                <strong>
                                                    <?= $module->framework->tt('mfa_info21') ?>
                                                </strong>
                                            </li>
                                        </ul>
                                    </li>
                                    <li>
                                        <?= $module->framework->tt('mfa_info6') ?>
                                    </li>
                                    <li>
                                        <?= $module->framework->tt('mfa_info7') ?>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="authAppHeading2">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#authAppInfoStep2" aria-expanded="false"
                                aria-controls="authAppInfoStep2">
                                <strong>
                                    <?= $module->framework->tt('mfa_info8') ?>
                                </strong>
                            </button>
                        </h2>
                        <div id="authAppInfoStep2" class="accordion-collapse collapse" aria-labelledby="authAppHeading2"
                            data-bs-parent="#authAppInfoAccordion">
                            <div class="accordion-body">
                                <div class="row align-items-center">
                                    <div class="col-7">
                                        <p><strong>
                                                <?= $module->framework->tt('mfa_info9') ?>
                                            </strong></p>
                                        <p>
                                            <?= $module->framework->tt('mfa_info10') ?>
                                        </p>
                                        <br>
                                        <div class="border border-rcpro p-2 rounded" style="font-size:small">
                                            <i class="fa-solid fas fa-asterisk text-rcpro"></i>
                                            <?= $module->framework->tt('mfa_info11') ?>
                                            <ul>
                                                <li><span>
                                                        <?= $module->framework->tt('mfa_info12') ?>
                                                    </span> <strong><span
                                                            id="authAppAccountName"></span></strong></span></li>
                                                <li><span>
                                                        <?= $module->framework->tt('mfa_info13') ?>
                                                    </span> <strong><span id="authAppAccountKey"></span></strong></span>
                                                </li>
                                                <li><span>
                                                        <?= $module->framework->tt('mfa_info14') ?>
                                                    </span> <em>
                                                        <?= $module->framework->tt('mfa_info15') ?>
                                                    </em></li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="col text-center">
                                        <img id="authenticatorAppQr"><br>
                                        <a id="authenticatorAppUrl" href="" target="_blank" rel="noopener noreferer">
                                            <?= $module->framework->tt('mfa_info19') ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="authAppHeading3">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                aria-labelledby="authAppHeading3" data-bs-target="#authAppInfoStep3"
                                aria-expanded="false" aria-controls="authAppInfoStep3">
                                <strong>
                                    <?= $module->framework->tt('mfa_info16') ?>
                                </strong>
                            </button>
                        </h2>
                        <div id="authAppInfoStep3" class="accordion-collapse collapse"
                            data-bs-parent="#authAppInfoAccordion">
                            <div class="accordion-body">
                                <p><strong>
                                        <?= $module->framework->tt('mfa_info17') ?>
                                    </strong></p>
                                <p><i class="fa-solid fa-check text-success"></i>
                                    <?= $module->framework->tt('mfa_info18') ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Toasts -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="loadingToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-body text-bg-dark">
            <div class="row align-items-center">
                <div class="col-2">
                    <i class="text-rcpro fa-duotone fa-spinner-third fast-spin fa-2xl ms-2"></i>
                </div>
                <div class="col">
                    <span class="message">Please wait...</span>
                </div>
            </div>
        </div>
    </div>
    <div id="successToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-body text-bg-success">
            <div class="row align-items-center">
                <div class="col-2">
                    <i class="fa-regular fa-circle-check fa-2xl ms-2"></i>
                </div>
                <div class="col">
                    <span class="message"></span>
                </div>
            </div>
        </div>
    </div>
    <div id="errorToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-body text-bg-danger">
            <div class="row align-items-center">
                <div class="col-2">
                    <i class="fa-regular fa-circle-xmark fa-2xl ms-2"></i>
                </div>
                <div class="col">
                    <span class="message"></span>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $ui->EndParticipantPage(); ?>