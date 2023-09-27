<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

// This method starts the html doc
$module->UI->ShowParticipantHeader($module->tt("login_title"));
?>

<div style="text-align: center;">
    <p>
        <?= $module->tt("login_message1") ?>
    </p>
</div>


<form action="<?= $module->getUrl("src/mfa.php", true); ?>" method="post">
    <div class="form-group">
        <label>
            Enter MFA Token
        </label>
        <input type="text" name="mfa_token" class="form-control <?= (!empty($mfa_err)) ? 'is-invalid' : ''; ?>">
        <span class="invalid-feedback">
            <?= $mfa_err; ?>
        </span>
    </div>
    <div class="form-group d-grid">
        <input type="submit" class="btn btn-primary" value="<?= $module->tt("mfa_button_text") ?? 'Submit' ?>">
    </div>
    <input type="hidden" name="redcap_csrf_token" value="<?= $module->framework->getCSRFToken() ?>">
</form>
<hr>
<div style="text-align: center;">
    <?= $module->tt("login_forgot") ?>
    <a href="<?= $module->getUrl("src/forgot-username.php", true); ?>">
        <?= $module->tt("login_username") ?>
    </a>
    <?= $module->tt("login_or") ?>
    <a href="<?= $module->getUrl("src/forgot-password.php", true); ?>">
        <?= $module->tt("login_password") ?>
    </a>?
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