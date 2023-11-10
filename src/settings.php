<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

$role = $module->getUserRole($module->safeGetUsername()); // 3=admin/manager, 2=user, 1=monitor, 0=not found
if ( $role < 3 ) {
    header("location:" . $module->getUrl("src/home.php"));
}
$module->includeFont();

require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$ui = new UI($module);
$ui->ShowHeader("Settings");
echo "<title>" . $module->APPTITLE . " - Settings</title>
<link rel='stylesheet' type='text/css' href='" . $module->getUrl('src/css/rcpro.php') . "'/>";

// Check for errors
if ( isset($_GET["error"]) ) {
    ?>
    <script>
        Swal.fire({
            icon: "error",
            title: "Error",
            text: "There was a problem. Please try again.",
            showConfirmButton: false
        });
    </script>
    <?php
}

// Get possible languages
$projectSettings = new ProjectSettings($module);
$languageList    = $projectSettings->getLanguageFiles();

// Update settings if requested
if ( $_SERVER["REQUEST_METHOD"] == "POST" ) {

    // Log submission
    $module->logForm("Submitted Settings Form", $_POST);

    // Validate Settings
    $post_settings = $_POST;
    $new_settings  = array();
    try {
        // Validate Language
        $new_settings["reserved-language-project"] = \REDCap::escapeHtml($post_settings["reserved-language-project"]);
        if ( !in_array($new_settings["reserved-language-project"], array_keys($languageList), true) ) {
            $lang_err = "Invalid language selected";
            $any_err  = true;
        }
        if ( $new_settings["reserved-language-project"] === "English" ) {
            $new_settings["reserved-language-project"] = null;
        }

        // Validate Prevent Email Login
        $new_settings["prevent-email-login"] = $post_settings["prevent-email-login"] === "true";

        // Validate MFA
        $new_settings["mfa"] = $post_settings["mfa"] === "true";

        // Validate API
        $new_settings["api"] = $post_settings["api"] === "true";

        // Validate Primary Contact
        $new_settings["pc-name"]  = \REDCap::escapeHtml($post_settings["pc-name"]);
        $new_settings["pc-email"] = \REDCap::escapeHtml($post_settings["pc-email"]);
        $new_settings["pc-phone"] = \REDCap::escapeHtml($post_settings["pc-phone"]);
        if ( $new_settings["pc-email"] !== "" && !filter_var($new_settings["pc-email"], FILTER_VALIDATE_EMAIL) ) {
            $email_err = "Invalid email format";
            $any_err   = true;
        }

        if ( $module->getSystemSetting("allow-self-registration-system") ) {
            // Validate Allow Self-Registration
            $new_settings["allow-self-registration"] = $post_settings["allow-self-registration"] === "on";

            // Validate Auto-Enroll Upon Self-Registration
            $new_settings["auto-enroll-upon-self-registration"] = $new_settings["allow-self-registration"] && $post_settings["auto-enroll-upon-self-registration"] === "on";

            // Validate Auto-Enroll Notification Email
            $new_email                                      = trim(filter_input(INPUT_POST, "auto-enroll-notification-email", FILTER_VALIDATE_EMAIL));
            $new_settings["auto-enroll-notification-email"] = $new_email;
        }

        if ( !$any_err ) {

            $module->setProjectSettings($new_settings);
            ?>
            <script>
                Swal.fire({
                    icon: "success",
                    title: "Settings Saved",
                    showConfirmButton: false
                });
            </script>
            <?php
        }
    } catch ( \Exception $e ) {
        ?>
        <script>
            Swal.fire({
                icon: "error",
                title: "Error",
                text: "<?= $e->getMessage(); ?>",
                showConfirmButton: false
            });
        </script>
        <?php
    }
}

// Get current project settings
$settings                       = $module->getProjectSettings();
$preventEmailLoginSystem        = $module->getSystemSetting("prevent-email-login-system");
$allowMfa                       = $module->getSystemSetting("mfa");
$project_id                     = (int) $module->framework->getProjectId();
$allowSelfRegistrationSystem    = $module->framework->getSystemSetting("allow-self-registration-system");
$allowAutoEnrollSystem          = (bool) $module->framework->getSystemSetting("allow-auto-enroll-upon-self-registration-system");
$allowSelfRegistration          = $projectSettings->shouldAllowSelfRegistration($project_id);
$autoEnrollUponSelfRegistration = $projectSettings->shouldEnrollUponRegistration($project_id);
$autoEnrollNotificationEmail    = $projectSettings->getAutoEnrollNotificationEmail($project_id);
$selfRegistrationAdminOnly      = $module->getSystemSetting("self-registration-require-admin");
$allowApi                       = $module->getSystemSetting("api-enabled-system");
$apiSettingsAdminOnly           = $module->getSystemSetting("api-require-admin");

?>

<div class="settingsContainer wrapper" style="display: none;">
    <h2>Settings</h2>
    <p>Project-level configuration</p>
    <div id="parent">
        <form class="rcpro-form" id="settings-form" action="<?= $module->getUrl("src/settings.php"); ?>" method="POST"
            enctype="multipart/form-data" target="_self">
            <div class="card">
                <div class="card-header">
                    <span class="fa-stack">
                        <i class="fas fa-globe fa-2x"></i>
                    </span>
                    <nbsp></nbsp><strong>Language</strong>
                </div>
                <div class="card-body">
                    <div class="card-title">Set the language that participant-facing text will be displayed in.</div>
                    <div class="form-group">
                        <select class="form-select <?php echo (!empty($lang_err)) ? 'is-invalid' : ''; ?>"
                            name="reserved-language-project" aria-label="language select">
                            <?php
                            foreach ( $languageList as $lang_item => $file ) {
                                $selected_lang = $settings["reserved-language-project"];
                                $selected      = ($lang_item === $selected_lang) || (!isset($selected_lang) && $lang_item === "English") ? "selected" : "";
                                ?>
                                <option value="<?= $lang_item ?>" <?= $selected ?>>
                                    <?= $lang_item ?>
                                </option>
                                <?php
                            }
                            ?>
                        </select>
                        <span class="invalid-feedback">
                            <?php echo $lang_err; ?>
                        </span>
                    </div>
                </div>
            </div>
            <br>
            <?php
            if ( !$preventEmailLoginSystem ) {
                $checked = $settings["prevent-email-login"] ? "checked" : "";
                ?>
                <div class="card">
                    <div class="card-header">
                        <span class="fa-stack">
                            <i class="fas fa-envelope fa-stack-1x"></i>
                            <i class="fas fa-ban fa-stack-2x" style="color: <?= $module::$COLORS["ban"] ?>"></i>
                        </span>
                        <nbsp></nbsp>
                        <strong>Prevent Email Login</strong>
                    </div>
                    <div class="card-body">
                        <div class="card-title">
                            Should participants be prevented from using their email address to log in to the system.<br>

                        </div>
                        <div class="form-check">
                            <input
                                class="form-check-input <?php echo (!empty($prevent_email_login_err)) ? 'is-invalid' : ''; ?>"
                                type="checkbox" id="prevent-email-login-check" <?= $checked ?> onclick="(function(){
                                $('#prevent-email-login').val($('#prevent-email-login-check')[0].checked);
                            })()">
                            <label class="form-check-label" style="vertical-align:middle;"
                                for="prevent-email-login-check">Checking this will require that they login using their
                                participant username only.</label>
                            <input type="text" name="prevent-email-login" id="prevent-email-login"
                                value="<?= $checked === "checked" ? "true" : "false" ?>" hidden>
                            <span class="invalid-feedback">
                                <?php echo $prevent_email_login_err; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <br>
            <?php }
            if ( $allowMfa ) {
                $mfaChecked = $settings["mfa"] ? "checked" : "";
                ?>
                <div class="card">
                    <div class="card-header">
                        <span class="fa-stack">
                            <i class="fas fa-id-badge fa-2x"></i>
                        </span>
                        <nbsp></nbsp>
                        <strong>Multifactor Authentication</strong>
                    </div>
                    <div class="card-body">
                        <div class="card-title">
                            Should participants be required to use multi-factor authentication (MFA) when logging in?<br>
                            If so, they will be required to enter a code sent to their email address after entering their
                            username and password.<br>This is an additional security measure to prevent unauthorized access.
                            <br>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input <?php echo (!empty($mfa_err)) ? 'is-invalid' : ''; ?>"
                                type="checkbox" id="mfa-check" <?= $mfaChecked ?> onclick="(function(){
                                $('#mfa').val($('#mfa-check')[0].checked);
                            })()">
                            <label class="form-check-label" style="vertical-align:middle;" for="mfa-check">Checking this
                                will require MFA.</label>
                            <input type="text" name="mfa" id="mfa"
                                value="<?= $mfaChecked === "checked" ? "true" : "false" ?>" hidden>
                            <span class="invalid-feedback">
                                <?php echo $mfa_err; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <br>
            <?php }
            if ( $allowApi && (!$apiSettingsAdminOnly || $module->framework->getUser()->isSuperUser()) ) {
                $apiChecked = $settings["api"] ? "checked" : "";
                ?>
                <div class="card">
                    <div class="card-header">
                        <span class="fa-stack">
                            <i class="fas fa-laptop-code fa-2x"></i>
                        </span>
                        <nbsp></nbsp>
                        <strong>API</strong>
                    </div>
                    <div class="card-body">
                        <div class="card-title">
                            Should users be allowed to use the API to register and enroll participants?<br>
                            If so, they will be able to use their REDCap API tokens to register and enroll participants in
                            this project.<br>
                            <a href="https://github.com/AndrewPoppe/REDCap-PRO#api" target="_blank" rel="noopener noreferrer">More information</a>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input <?php echo (!empty($api_err)) ? 'is-invalid' : ''; ?>"
                                type="checkbox" id="api-check" <?= $apiChecked ?> onclick="(function(){
                                $('#api').val($('#api-check')[0].checked);
                            })()">
                            <label class="form-check-label" style="vertical-align:middle;" for="api-check">Checking this
                                will allow users to use the API.</label>
                            <input type="text" name="api" id="api"
                                value="<?= $apiChecked === "checked" ? "true" : "false" ?>" hidden>
                            <span class="invalid-feedback">
                                <?php echo $api_err; ?>
                            </span>
                        </div>
                        <p>
                            The API URL for this system is
                            <code><?= $module->getProjectlessUrl("src/api.php", true, true) ?></code>
                        </p>
                    </div>
                </div>
                <br>
            <?php } ?>
            <?php
            if ( $allowSelfRegistrationSystem && (!$selfRegistrationAdminOnly || $module->framework->getUser()->isSuperUser()) ) {
                $allowSelfRegistrationChecked          = $allowSelfRegistration ? "checked" : "";
                $autoEnrollUponSelfRegistrationChecked = $autoEnrollUponSelfRegistration ? "checked" : "";
                ?>
                <div class="card">
                    <div class="card-header">
                        <span class="fa-stack">
                            <i class="fas fa-pen-to-square fa-stack-2x"></i>
                        </span>
                        <nbsp></nbsp>
                        <strong>Participant Self-Registration</strong>
                    </div>
                    <div class="card-body">
                        <div class="card-title">
                            <strong>Should participants be allowed to create their own accounts?</strong><br>
                            <em>Checking this option will allow participants to register themselves
                                if they do not already have an account.</em>
                        </div>
                        <div class="form-check">
                            <input
                                class="form-check-input <?php echo (!empty($self_registration_err)) ? 'is-invalid' : ''; ?>"
                                aria-expanded="<?= $allowSelfRegistrationChecked ?>" type="checkbox"
                                id="allow-self-registration-form-check" <?= $allowSelfRegistrationChecked ?>
                                name="allow-self-registration" onchange="(function(){
                                    <?php if ( $allowAutoEnrollSystem ) { ?>
                                        const isChecked = $('#allow-self-registration-form-check')[0].checked;
                                        if (!isChecked) {
                                            $('#auto-enroll-upon-self-registration')[0].checked = false;
                                            $('#auto-enroll-notification-email').attr('disabled', true);
                                        }
                                        $('#auto-enroll-upon-self-registration').attr('disabled', !isChecked);
                                    <?php } ?>
                            })()">
                            <label class="form-check-label" style="vertical-align:middle;"
                                for="allow-self-registration-form-check">Allow Participant Self-Registration</label>
                            <span class="invalid-feedback">
                                <?php echo $self_registration_err; ?>
                            </span>
                        </div>
                        <?php if ( $allowAutoEnrollSystem ) { ?>
                            <br><br>
                            <div class="card-title">
                                <strong>Should participants be automatically enrolled when they self-register?</strong><br>
                                <em>Checking this option will automatically enroll a participant in your study when they
                                    self-register.</em>
                            </div>
                            <div class="form-check" id="auto-enroll-settings">
                                <input class="form-check-input <?php echo (!empty($auto_enroll_err)) ? 'is-invalid' : ''; ?>"
                                    name="auto-enroll-upon-self-registration" type="checkbox"
                                    id="auto-enroll-upon-self-registration" <?= $autoEnrollUponSelfRegistrationChecked ?>
                                    onchange="(function(){
                                    const isChecked = $('#auto-enroll-upon-self-registration')[0].checked;
                                    $('#auto-enroll-notification-email').attr('disabled', !isChecked);
                            })()">
                                <label class="form-check-label" style="vertical-align:middle;"
                                    for="auto-enroll-upon-self-registration">Auto-Enroll Upon Self-Registration</label>
                                <span class="invalid-feedback">
                                    <?php echo $auto_enroll_err; ?>
                                </span>
                            </div>
                            <br>
                            <div class="form-group">
                                <label>Email address to notify when new participants are auto-enrolled</label>
                                <input type="email" name="auto-enroll-notification-email" id="auto-enroll-notification-email"
                                    class="form-control <?= (!empty($auto_enroll_notification_err) ? 'is-invalid' : '') ?>"
                                    value="<?= $autoEnrollNotificationEmail ?>">
                                <span class="invalid-feedback">
                                    <?php echo $auto_enroll_notification_err; ?>
                                </span>
                            </div>
                        <?php } ?>
                    </div>
                </div>
                <br>
            <?php } ?>
            <div class="card">
                <div class="card-header">
                    <span class="fa-stack">
                        <i class="fas fa-address-card fa-2x"></i>
                    </span>
                    <nbsp></nbsp>
                    <strong>Primary Contact Person</strong>
                </div>
                <div class="card-body">
                    <div class="card-title">
                        This is who participants should contact when they have questions.
                    </div>
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="pc-name"
                            class="form-control <?php echo (!empty($name_err)) ? 'is-invalid' : ''; ?>"
                            value="<?php echo \REDCap::escapeHtml($settings["pc-name"]); ?>">
                        <span class="invalid-feedback">
                            <?php echo $name_err; ?>
                        </span>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="pc-email"
                            class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>"
                            value="<?php echo \REDCap::escapeHtml($settings["pc-email"]); ?>">
                        <span class="invalid-feedback">
                            <?php echo $email_err; ?>
                        </span>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="pc-phone"
                            class="form-control <?php echo (!empty($phone_err)) ? 'is-invalid' : ''; ?>"
                            value="<?php echo \REDCap::escapeHtml($settings["pc-phone"]); ?>">
                        <span class="invalid-feedback">
                            <?php echo $phone_err; ?>
                        </span>
                    </div>
                </div>
            </div>
            <br>
            <div class="form-group">
                <button type="button" class="btn btn-secondary" value="Cancel" onclick="(function() {
                    window.location.href = '<?= $module->getUrl('src/settings.php') ?>';
                    })()">Cancel</button>
                <button type="submit" id="rcpro-submit-button" class="btn btn-rcpro" value="Submit" disabled>Save
                    Settings</button>
            </div>
            <input type="hidden" name="redcap_csrf_token" value="<?= $module->framework->getCSRFToken() ?>">
        </form>
    </div>
</div>
<script>
    (function ($, window, document) {
        $(document).ready(function () {
            let form = document.querySelector('#settings-form');
            form.addEventListener('change', function () {
                $('#rcpro-submit-button').attr("disabled", null);
            });
            <?php if ( $allowAutoEnrollSystem ) { ?>
                const isChecked = $('#allow-self-registration-form-check')[0].checked;
                if (!isChecked) {
                    $('#auto-enroll-upon-self-registration')[0].checked = false;
                }

                $('#auto-enroll-upon-self-registration').attr('disabled', !isChecked);
                $('#auto-enroll-notification-email').attr('disabled', !$('#auto-enroll-upon-self-registration')[0].checked);
            <?php } ?>
        });
    })(window.jQuery, window, document);
</script>

<?php
include_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';