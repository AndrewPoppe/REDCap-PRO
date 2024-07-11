<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

$recaptcha_site_key = $module->framework->getSystemSetting('recaptcha-site-key');
if ( isset($recaptcha_site_key) ) {
    echo '<script src="https://www.google.com/recaptcha/api.js" async defer></script>';
    echo '<script type="text/javascript">function onSubmit(token) {document.querySelector("#createAccountForm").submit();}</script>';
}

// Initialize Authentication
$auth = new Auth($module->APPTITLE);
$auth->init();

// UI Helper
$ui = new UI($module);

// Check if the user is already logged in, if yes then redirect then to the login page (MFA handled there)
if ( $auth->is_logged_in() ) {
    header("location: " . $module->getUrl("src/login.php", true));
    return;
}

// Check to make sure a project survey led them here
$project_id = $auth->get_redcap_project_id();
if ( !$auth->is_survey_url_set() || empty($project_id) ) {
    echo "You must access this page from a REDCap survey link.";
    return;
}


// Project Settings
$settings = new ProjectSettings($module);

// Processing form data when form is submitted
if ( $_SERVER["REQUEST_METHOD"] == "POST" ) {

    $any_errors = false;

    // Check reCAPTCHA if enabled
    if ( isset($recaptcha_site_key) ) {
        $recpatch_secret_key = $module->framework->getSystemSetting('recaptcha-secret-key');
        $recaptcha_response  = trim(filter_input(INPUT_POST, 'g-recaptcha-response', FILTER_VALIDATE_REGEXP, array(
            "options" => array(
                "regexp" => "/^[a-zA-Z0-9_-]+$/"
            )
        )));

        if ( empty($recaptcha_response) ) {
            $any_errors    = true;
            $recaptcha_err = "Please check the reCAPTCHA box";
        } else {

            // Verify the reCAPTCHA response (returns JSON data)
            $verify_response = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=' . $recpatch_secret_key . '&response=' . $recaptcha_response);

            // Decode JSON data of API response
            $response_data = json_decode($verify_response);
            if ( !$response_data->success ) {
                $any_errors    = true;
                $recaptcha_err = "reCAPTCHA failed";
            }
        }
    }

    // Check if any fields are empty or invalid
    // Show error message if so
    $fname = trim(filter_input(INPUT_POST, 'fname', FILTER_SANITIZE_STRING));
    $lname = trim(filter_input(INPUT_POST, 'lname', FILTER_SANITIZE_STRING));
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL));

    if ( empty($fname) ) {
        $any_errors = true;
        $fname_err  = "Please enter your first name";
    }
    if ( empty($lname) ) {
        $any_errors = true;
        $lname_err  = "Please enter your last name";
    }
    if ( empty($email) ) {
        $any_errors = true;
        $email_err  = "Please enter a valid email address";
    }

    // Check that account does not already exist
    if ( !$any_errors ) {
        try {
            $participantHelper = new ParticipantHelper($module);
            $emailExists       = $participantHelper->checkEmailExists($email);
            if ( $emailExists ) {
                // Send email to user with link to reset password
                $rcpro_participant_id = $participantHelper->getParticipantIdFromEmail($email);
                $rcpro_username       = $participantHelper->getUserName($rcpro_participant_id);
                $module->sendPasswordResetEmail($rcpro_participant_id, true);
            } else {
                // Create the account
                $rcpro_username       = $participantHelper->createParticipant($email, $fname, $lname);
                $rcpro_participant_id = $participantHelper->getParticipantIdFromUsername($rcpro_username);
                $module->sendNewParticipantEmail($rcpro_username, $email, $fname, $lname, $auth->get_survey_hash());
            }

            // Enroll the user in the project if that setting is enabled
            if ( $settings->shouldEnrollUponRegistration((int) $project_id) ) {
                $data_access_group = $auth->get_data_access_group_id();
                $projectHelper     = new ProjectHelper($module);
                $projectHelper->enrollParticipant($rcpro_participant_id, $project_id, $data_access_group, $rcpro_username);
                $emailToNotify = $settings->getAutoEnrollNotificationEmail((int) $project_id);
                if ( !empty($emailToNotify) ) {
                    $module->sendAutoEnrollNotificationEmail($emailToNotify, $project_id);
                }
            }

            // Give confirmation message
            $ui->ShowParticipantHeader('Account Created');
            ?>
            <div style="text-align: center;">
                <p>
                    Please check your email for a link to set your password. If you already have an account, you will be sent a password reset email.
                </p>
            </div>
            <?php
            $ui->EndParticipantPage();
            return;
        } catch ( \Throwable $e ) {
            $any_errors = true;
            echo "There was a problem. Please try again later.";
            return;
        }
    }
}

// This method starts the html doc
$ui->ShowParticipantHeader('Create Account');
?>

<div style="text-align: center;">
    <p>
        Please supply the information below to create a new REDCapPRO account
    </p>
</div>

<form id="createAccountForm" action="<?= $module->getUrl("src/create-account.php", true); ?>" method="post">
    <div class="form-group">
        <label>
            First name
        </label>
        <input type="text" name="fname" class="form-control <?= (!empty($fname_err)) ? 'is-invalid' : ''; ?>"
            value="<?= $fname; ?>">
        <span class="invalid-feedback">
            <?= $fname_err; ?>
        </span>
    </div>
    <div class="form-group">
        <label>
            Last name
        </label>
        <input type="text" name="lname" class="form-control <?= (!empty($lname_err)) ? 'is-invalid' : ''; ?>"
            value="<?= $lname; ?>">
        <span class="invalid-feedback">
            <?= $lname_err; ?>
        </span>
    </div>
    <div class="form-group">
        <label>
            Email address
        </label>
        <input type="email" name="email" class="form-control <?= (!empty($email_err)) ? 'is-invalid' : ''; ?>"
            value="<?= $email; ?>">
        <span class="invalid-feedback">
            <?= $email_err; ?>
        </span>
    </div>
    <div class="form-group d-grid">
        <?php if ( isset($recaptcha_site_key) ) { ?>
            <!-- Google reCAPTCHA trigger -->
            <button class="btn btn-primary g-recaptcha" data-sitekey="<?= $recaptcha_site_key ?>" data-callback="onSubmit" data-action="submit">Create Account</button>
            <?php if ( !empty($recaptcha_err) ) { ?>
                <input class="is-invalid" hidden>
                <span class="invalid-feedback">
                    <?= $recaptcha_err; ?>
                </span>
            <?php } ?>
        <?php } else { ?>
            <button type="submit" class="btn btn-primary">Create Account</button>
        <?php } ?>
    </div>
    <input type="hidden" name="redcap_csrf_token" value="<?= $module->framework->getCSRFToken() ?>">
</form>
<hr>
<div style="text-align: center;">
    Already have an account?
    <a href="<?= $module->getUrl("src/login.php", true); ?>">
        Login
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

    div.is-invalid {
        /* border: 1px solid #dc3545; */
    }
</style>
<?php $ui->EndParticipantPage(); ?>