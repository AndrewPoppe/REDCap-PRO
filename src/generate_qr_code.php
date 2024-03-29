<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

// Only allow logged in participants to access this page
$auth = new Auth($module->APPTITLE);
$auth->init();
if (!$auth->is_logged_in()) {
    header("Location: " . $module->getUrl("src/login.php", true));
    exit;
}

// Get OTPAUTH URI
$otpauth_encoded = filter_input(INPUT_GET, 'otpauth', FILTER_SANITIZE_ENCODED);
$otpauth = urldecode($otpauth_encoded);

// Grab the secret from the URI
$otpauth_secret = $auth->get_totp_mfa_secret_from_otpauth($otpauth);

// Get this participant's secret
$participantHelper = new ParticipantHelper($module);
$rcpro_participant_id = $auth->get_participant_id();
$participant_secret = $participantHelper->getMfaSecret($rcpro_participant_id);

// Make sure the participant's secret matches the secret in the URI
if ($participant_secret !== $otpauth_secret) {
    header("Location: " . $module->getUrl("src/login.php", true));
    exit;
}

// Get QR Code class
$qrPath1 = APP_PATH_LIBRARIES . "phpqrcode/lib/full/qrlib.php";
$qrPath2 = APP_PATH_LIBRARIES . "phpqrcode/qrlib.php";

if (file_exists($qrPath1)) {
    require_once $qrPath1;
} elseif (file_exists($qrPath2)) {
    require_once $qrPath2;
} else {
    throw new REDCapProException("Could not find QR Code library");
}

// Output QR code image
\QRcode::png($otpauth, false, 'H', 4);