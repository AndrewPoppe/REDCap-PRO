<?php
namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

$role = $module->getUserRole($module->safeGetUsername()); // 3=admin/manager, 2=user, 1=monitor, 0=not found
$module->includeFont();
echo "<title>" . $module->APPTITLE . " - Home</title>";
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$ui = new UI($module);
$ui->ShowHeader("Home");
?>


<link rel="stylesheet" type="text/css" href="<?= $module->getUrl("src/css/rcpro.php") ?>" />
<div class="infoContainer wrapper">
    <div class="rcpro-form home-form">
        <h4>Overview</h4>
        <br>
        <ul>
            <li>REDCapPRO allows participants/patients
                to directly report study data (<em>i.e.</em>, ePRO).</li>

            <li>Its primary purpose is to allow the
                identification of a survey participant and to log that information
                in a REDCap project's audit trail in a manner compliant with
                regulatory stipulations (primarily FDA's 21 CFR Part 11).</li>

            <li>To achieve
                this, project users must first register a participant with REDCapPRO
                and then enroll that participant in the REDCap project.</li>
        </ul>

        <div style="text-align: center; margin:20px;">
            Click <a style="font-size:inherit;" href="https://github.com/AndrewPoppe/REDCap-PRO#readme" target="_blank"
                rel="noreferrer noopener">here</a> for more information
        </div>
    </div>
</div>

<?php

try {
    $auth = new Auth('REDCapPRO');
    $email = 'test@test.com';
    $secret = $auth->create_totp_mfa_secret();
    $otpauth = $auth->create_totp_mfa_otpauth($email, $secret);

    var_dump($otpauth);

    print "<img src='".$auth->get_totp_mfa_qr_url($otpauth, $module)."'>";

} catch (\Throwable $e) {
    echo "". $e->getMessage() ."";
}

include_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';