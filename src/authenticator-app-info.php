<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

// Initialize authentication session on page
$auth = new Auth($module->APPTITLE);
$auth->init();

// UI
$ui = new UI($module);

// Parse query string to grab token.
parse_str($_SERVER['QUERY_STRING'], $qstring);

// Redirect to login page if we shouldn't be here
if ( !isset($qstring["t"]) ) {
    header("location: " . $module->getUrl("src/login.php", true));
    exit;
}

// Verify password reset token
$participantHelper    = new ParticipantHelper($module);
$verified_participant = $participantHelper->verifyAuthenticatorAppInfoToken($qstring["t"]);

$ui->ShowParticipantHeader($module->tt("mfa_info_page_title"));

if (!$verified_participant) { ?>
    <div class='red' style="text-align: center;">
        <?= $module->tt("mfa_info_page_err1") ?>
    </div>
    <?php
    $ui->EndParticipantPage();
    exit;
}

// They have a valid token. Set their Login and MFA verification status to true.
$auth->set_login_values($verified_participant);
$auth->set_mfa_verification_status(true);

$rcpro_participant_id = $verified_participant["rcpro_participant_id"];
$participant_email = $participantHelper->getEmail($rcpro_participant_id);
$mfa_secret = $participantHelper->getMfaSecret($rcpro_participant_id);
if (empty($mfa_secret)) {
    $mfa_secret = $auth->create_totp_mfa_secret();
    $participantHelper->storeMfaSecret($rcpro_participant_id, $mfa_secret);
}
$otpauth = $auth->create_totp_mfa_otpauth($participant_email, $mfa_secret);
$url = $auth->get_totp_mfa_qr_url($otpauth, $module);

?>
<div class="accordion accordion-rcpro" id="authAppInfoAccordion">
    <div class="accordion-item">
        <h2 class="accordion-header" id="authAppHeading1">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#authAppInfoStep1" aria-expanded="true" aria-controls="authAppInfoStep1">
                <strong><?= $module->framework->tt('mfa_info3')?></strong>
            </button>
        </h2>
        <div id="authAppInfoStep1" class="accordion-collapse collapse show" aria-labelledby="authAppHeading1" data-bs-parent="#authAppInfoAccordion">
            <div class="accordion-body">
                <p><?= $module->framework->tt('mfa_info4')?></p>
                <ul>
                <li><?= $module->framework->tt('mfa_info5')?>
                    <ul>
                        <li><img style="width: 2rem;" src="<?= $module->framework->getUrl('images/ga.webp', true)?>"> <strong><?= $module->framework->tt('mfa_info20') ?></strong></li>
                        <li><img style="width: 2rem;" src="<?= $module->framework->getUrl('images/ma.webp', true)?>"> <strong><?= $module->framework->tt('mfa_info21') ?></strong></li>
                    </ul>
                </li>
                <li><?= $module->framework->tt('mfa_info6')?></li>
                <li><?= $module->framework->tt('mfa_info7')?></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="accordion-item">
        <h2 class="accordion-header" id="authAppHeading2">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#authAppInfoStep2" aria-expanded="false" aria-controls="authAppInfoStep2">
                <strong><?= $module->framework->tt('mfa_info8')?></strong>
            </button>
        </h2>
        <div id="authAppInfoStep2" class="accordion-collapse collapse" aria-labelledby="authAppHeading2" data-bs-parent="#authAppInfoAccordion">
            <div class="accordion-body">
                <div class="row align-items-center">
                    <div class="col-7">
                        <p><strong><?= $module->framework->tt('mfa_info9')?></strong></p>
                        <p><?= $module->framework->tt('mfa_info10')?></p>
                        <br>
                        <div class="border border-rcpro p-2 rounded" style="font-size:small">
                            <i class="fa-solid fas fa-asterisk text-rcpro"></i> <?= $module->framework->tt('mfa_info11')?>
                            <ul>
                                <li><span><?= $module->framework->tt('mfa_info12')?></span> <strong><span id="authAppAccountName"><?= $module->framework->escape($participant_email) ?></span></strong></span></li>
                                <li><span><?= $module->framework->tt('mfa_info13')?></span> <strong><span id="authAppAccountKey"><?= $module->framework->escape($mfa_secret) ?></span></strong></span></li>
                                <li><span><?= $module->framework->tt('mfa_info14')?></span> <em><?= $module->framework->tt('mfa_info15')?></em></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col text-center">
                        <img id="authenticatorAppQr" src="<?= $url ?>"><br>
                        <a id="authenticatorAppUrl" href="<?= $url ?>" target="_blank" rel="noopener noreferer"><?= $module->framework->tt('mfa_info19')?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="accordion-item">
        <h2 class="accordion-header" id="authAppHeading3">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" aria-labelledby="authAppHeading3" data-bs-target="#authAppInfoStep3" aria-expanded="false" aria-controls="authAppInfoStep3">
                <strong><?= $module->framework->tt('mfa_info16')?></strong>
            </button>
        </h2>
        <div id="authAppInfoStep3" class="accordion-collapse collapse" data-bs-parent="#authAppInfoAccordion">
            <div class="accordion-body">
                <p><strong><?= $module->framework->tt('mfa_info17')?></strong></p>
                <p><i class="fa-solid fa-check text-success"></i> <?= $module->framework->tt('mfa_info18')?></p>
            </div>
        </div>
    </div>
</div>
<style>
    .wrapper {
        width: 800px;
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
        color: <?= $module::$COLORS["primary"] ?> !important;
        font-weight: bold !important;
    }
    a:hover {
        text-shadow: 0px 0px 5px <?= $module::$COLORS["primary"] ?>;
    }

    div.mfaOptionContainer  {
        width: 360px;
        margin: auto;
    }

    button.btn-mfa-control {
        width: 100%;
    }

    .mfaLoading {
        color: <?= $module::$COLORS["primary"] ?>;
    }
    
    .text-rcpro {
        color: <?= $module::$COLORS["primary"] ?>;
    }
    .bg-rcpro {
        background-color: <?= $module::$COLORS["primary"] ?>;
    }
    .border-rcpro {
        border-color: <?= $module::$COLORS["primary"] ?> !important;
    }

    .accordion-rcpro button.accordion-button {
        color: <?= $module::$COLORS["primary"] ?>;
    }
    .accordion-rcpro button.accordion-button:hover {
        background-color: <?= $module::$COLORS["primaryExtraLight"] ?>80;
    }
    .accordion-rcpro button.accordion-button:not(.collapsed) {
        background-color: <?= $module::$COLORS["primaryExtraLight"] ?>;
    }
    .accordion-rcpro button.accordion-button:focus {
        box-shadow: none;
    }
    .accordion-rcpro button.accordion-button:not(.collapsed)::after {
        background-image: url("data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23900000'><path fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/></svg>") !important;
    }
</style>
<?php $ui->EndParticipantPage(); ?>