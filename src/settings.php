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
$languages = new Language($module);
$languageList = $languages->getLanguages(false, true);

// Should these settings shown / updated?
$isAdmin                     = $module->framework->getUser()->isSuperUser();
$allowMfaSystem              = $module->framework->getSystemSetting("mfa-system");
$allowApiSystem              = $module->framework->getSystemSetting("api-enabled-system");
$allowSelfRegistrationSystem = $module->framework->getSystemSetting("allow-self-registration-system");
$showMfa                     = $allowMfaSystem && ($isAdmin || !$module->framework->getSystemSetting("mfa-require-admin"));
$showApi                     = $allowApiSystem && ($isAdmin || !$module->framework->getSystemSetting("api-require-admin"));
$showSelfRegistration        = $allowSelfRegistrationSystem && ($isAdmin || !$module->framework->getSystemSetting("self-registration-require-admin"));
$showTimeoutTimeSetting      = $module->framework->getSystemSetting("allow-project-timeout-time-override");

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

        // Validate Primary Contact
        $new_settings["pc-name"]  = \REDCap::escapeHtml($post_settings["pc-name"]);
        $new_settings["pc-email"] = \REDCap::escapeHtml($post_settings["pc-email"]);
        $new_settings["pc-phone"] = \REDCap::escapeHtml($post_settings["pc-phone"]);
        if ( $new_settings["pc-email"] !== "" && !filter_var($new_settings["pc-email"], FILTER_VALIDATE_EMAIL) ) {
            $email_err = "Invalid email format";
            $any_err   = true;
        }

        // Validate MFA
        if ( $showMfa ) {
            $new_settings["mfa"]                   = $post_settings["mfa"] === "true";
            $new_settings["mfa-authenticator-app"] = $post_settings["mfa-authenticator-app"] === "on";
        }

        // Validate API
        if ( $showApi ) {
            $new_settings["api"] = $post_settings["api"] === "true";
        }

        // Validate Self-Registration
        if ( $showSelfRegistration ) {
            $new_settings["allow-self-registration"]            = $post_settings["allow-self-registration"] === "on";
            $new_settings["auto-enroll-upon-self-registration"] = $new_settings["allow-self-registration"] && $post_settings["auto-enroll-upon-self-registration"] === "on";
            $new_email                                          = trim(filter_input(INPUT_POST, "auto-enroll-notification-email", FILTER_VALIDATE_EMAIL));
            $new_settings["auto-enroll-notification-email"]     = $new_email;
        }

        // Validate Timeout Time
        if ( $showTimeoutTimeSetting ) {
            $new_settings["timeout-time"] = (int) $post_settings["timeout-time"];
            if ( $new_settings["timeout-time"] === 0 ) {
                $new_settings["timeout-time"] = $projectSettings->getSystemTimeoutMinutes();
            }
            if (  $new_settings["timeout-time"] < 1 ) {
                $timeout_time_err = "Timeout time must be a positive integer";
                $any_err          = true;
            }
            $timeout_maximum = $projectSettings->getMaximumTimeoutMinutes();
            if ($new_settings["timeout-time"] > $timeout_maximum) {
                $timeout_time_err = "Timeout time must be no more than " . $timeout_maximum . " minutes";
                $any_err          = true;
            }
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
$settings                       = $module->framework->getProjectSettings();
$preventEmailLoginSystem        = $module->framework->getSystemSetting("prevent-email-login-system");
$allowMfaAuthenticatorApp       = $module->framework->getSystemSetting("mfa-authenticator-app-system");
$project_id                     = (int) $module->framework->getProjectId();
$mfaAuthenticatorAppEnabled     = $projectSettings->mfaAuthenticatorAppEnabled($project_id);
$allowAutoEnrollSystem          = (bool) $module->framework->getSystemSetting("allow-auto-enroll-upon-self-registration-system");
$allowSelfRegistration          = $projectSettings->shouldAllowSelfRegistration($project_id);
$autoEnrollUponSelfRegistration = $projectSettings->shouldEnrollUponRegistration($project_id);
$autoEnrollNotificationEmail    = $projectSettings->getAutoEnrollNotificationEmail($project_id);

$module->initializeJavascriptModuleObject();
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
                    <nbsp></nbsp><strong>Languages and Translation</strong>
                </div>
                <div class="card-body">
                    <div class="card-title">Set the default language that participant-facing text will be displayed in.</div>
                    <div class="form-group">
                        <select class="form-select <?php echo (!empty($lang_err)) ? 'is-invalid' : ''; ?>"
                            name="reserved-language-project" aria-label="language select">
                            <?php
                            foreach ( $languageList as $lang_code => $lang_item ) {
                                $selected_lang = $settings["reserved-language-project"];
                                $selected      = ($lang_code === $selected_lang) || (!isset($selected_lang) && $lang_code === "English") ? "selected" : "";
                                ?>
                                <option value="<?= $lang_code ?>" <?= $selected ?>>
                                    <?= $lang_code ?>
                                </option>
                                <?php
                            }
                            ?>
                        </select>
                        <span class="invalid-feedback">
                            <?php echo $lang_err; ?>
                        </span>
                    </div>
                    <div class="card-title">Set the languages that participants can choose from.</div>

                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col"></th>
                                <th scope="col">Active</th>
                                <th class="text-center" scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($languageList as $lang_code => $lang_item) { 
                                $defaultLanguage = $settings["reserved-language-project"] ?? "English";
                                if ($lang_item["active"] || $lang_item["code"] === $defaultLanguage) {
                                    $active = "checked";
                                } else {
                                    $active = "";
                                }
                                $activeDisabled = $lang_item["code"] === $defaultLanguage ? "disabled" : "";
                                $builtIn = $lang_item["built_in"];
                                $editTooltip = $lang_item["built_in"] ? "Built-in languages cannot be edited. You can copy this language to create a custom version that can be edited." : "Edit language";
                                $deleteTooltip = $lang_item["built_in"] ? "Built-in languages cannot be deleted." : "Delete language";
                                ?>
                            
                                <tr>
                                    <th scope="row"><?= $lang_item["code"] ?></th>
                                    <td><div class="form-check form-switch">
  <input class="form-check-input languageChoiceActivityCheckbox" name="languageChoice_<?= $lang_item["code"] ?>" type="checkbox" role="switch" id="switchCheckDefault" <?= $active ?> <?= $activeDisabled ?>></div></td>
                                    <td>
                                        <div class="btn-toolbar justify-content-center" role="toolbar" aria-label="Language actions">
                                            
                                            <div data-bs-toggle="tooltip" data-bs-title="<?=$editTooltip?>">
                                                <button type="button" class="btn btn-sm btn-outline-secondary edit-language-btn me-1" data-lang-code="<?= $lang_item["code"] ?>" <?= $builtIn ? "disabled" : "" ?>>
                                                    <i class="fas fa-pencil"></i>
                                                </button>
                                            </div>
                                            <div data-bs-toggle="tooltip" data-bs-title="Copy language">
                                                <button type="button" class="btn btn-sm btn-outline-secondary copy-language-btn me-1" data-lang-code="<?= $lang_item["code"] ?>" data-lang-builtIn=<?= $builtIn ? "true" : "false" ?>>
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </div>
                                            <div class="dropdown me-1" data-bs-toggle="tooltip" data-bs-title="Download language file" >
                                                <button type="button" class="btn btn-sm btn-outline-secondary btn-toggle" data-bs-toggle="dropdown" aria-expanded="false" >
                                                    <i class="fas fa-download"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item download-language-btn" href="#" data-lang-code="<?= $lang_item["code"] ?>" data-format="json">.json format</a></li>
                                                    <li><a class="dropdown-item download-language-btn" href="#" data-lang-code="<?= $lang_item["code"] ?>" data-format="ini">.ini format</a></li>
                                                </ul>
                                            </div>
                                            <div data-bs-toggle="tooltip" data-bs-title="<?=$deleteTooltip?>">
                                                <button type="button" class="btn btn-sm btn-outline-danger delete-language-btn" data-lang-code="<?= $lang_item["code"] ?>" <?= $builtIn ? "disabled" : "" ?>>
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                    <div class="dropdown">
                        <button type="button" class="btn btn-sm btn-success btn-toggle" data-bs-toggle="dropdown" aria-expanded="false" id="add-language-btn">Add Language <i class="fas fa-caret-down"></i></button>
                        <ul class="dropdown-menu" >
                            <li><a class="dropdown-item" href="#" id="add-language-from-file-btn"><i class="fas fa-file-import"></i> From File</a></li>
                            <li><a class="dropdown-item" href="#" id="add-language-manually-btn"><i class="fas fa-keyboard"></i> Manual Entry</a></li>
                        </ul>
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
                                $('#prevent-email-login').val($('#prevent-email-login-check').get(0).checked);
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
            if ( $showTimeoutTimeSetting ) {
                ?>
                <div class="card">
                    <div class="card-header">
                        <span class="fa-stack">
                            <i class="fas fa-stopwatch fa-2x"></i>
                        </span>
                        <nbsp></nbsp>
                        <strong>Timeout Time</strong>
                    </div>
                    <div class="card-body">
                        <div class="card-title">
                            How long should participants be allowed to be inactive before they are automatically logged out?
                        </div>
                        <div class="form-group">
                            <label>Enter the number of minutes of inactivity before participant is logged out</label>
                            <input type="text" name="timeout-time"
                                class="form-control <?php echo (!empty($timeout_time_err)) ? 'is-invalid' : ''; ?>"
                                value="<?php echo \REDCap::escapeHtml($projectSettings->getProjectTimeoutMinutes($project_id)); ?>">
                            <span class="invalid-feedback">
                                <?php echo $timeout_time_err; ?>
                            </span>
                        </div>
                        <p><span>Leave this value blank to set to the system default of <strong><?= \REDCap::escapeHtml($projectSettings->getSystemTimeoutMinutes())?> minutes.</strong></span>
                        <br>
                        <span>The maximum timeout time allowed on this system is <strong><?= \REDCap::escapeHtml($projectSettings->getMaximumTimeoutMinutes()) ?> minutes.</strong></span></p>
                    </div>
                </div>
                <br>
            <?php }
            if ( $showMfa ) {
                $mfaChecked                 = $settings["mfa"] ? "checked" : "";
                $mfaAuthenticatorAppChecked = $mfaAuthenticatorAppEnabled ? "checked" : "";
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
                            <strong>Should participants be required to use multi-factor authentication (MFA) when logging
                                in?</strong><br>
                            <em>If so, they will be required to enter a code sent to their email address after entering
                                their
                                username and password.<br>This is an additional security measure to prevent unauthorized
                                access.</em>
                            <br>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input <?php echo (!empty($mfa_err)) ? 'is-invalid' : ''; ?>"
                                type="checkbox" id="mfa-check" <?= $mfaChecked ?> onclick="(function(){
                                    const checked = $('#mfa-check').get(0).checked;
                                $('#mfa').val(checked);
                                <?php if ( $allowMfaAuthenticatorApp ) { ?>
                                    $('#mfa-authenticator-app').attr('disabled', !checked);
                                    $('#mfa-authenticator-app-title').toggleClass('text-muted-more', !checked);
                                <?php } ?>
                            })()">
                            <label class="form-check-label" style="vertical-align:middle;" for="mfa-check">Checking this
                                will require MFA.</label>
                            <input type="text" name="mfa" id="mfa"
                                value="<?= $mfaChecked === "checked" ? "true" : "false" ?>" hidden>
                            <span class="invalid-feedback">
                                <?php echo $mfa_err; ?>
                            </span>
                        </div>
                        <?php if ( $allowMfaAuthenticatorApp ) { ?>
                            <br><br>
                            <div class="card-title" id="mfa-authenticator-app-title">
                                <strong>Should an authenticator app such as Google Authenticator or Microsoft Authenticator be
                                    allowed for MFA?</strong><br>
                                <em>Checking this option will allow participants to use an authenticator app to generate their
                                    MFA code.</em>
                            </div>
                            <div class="form-check" id="auto-enroll-settings">
                                <input class="form-check-input" name="mfa-authenticator-app" type="checkbox"
                                    id="mfa-authenticator-app" <?= $mfaAuthenticatorAppChecked ?>>
                                <label class="form-check-label" style="vertical-align:middle;" for="mfa-authenticator-app">Allow
                                    MFA Authenticator App</label>
                            </div>
                        <?php } ?>
                    </div>
                </div>
                <br>
            <?php }
            if ( $showApi ) {
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
                            <a href="https://github.com/AndrewPoppe/REDCap-PRO#api" target="_blank"
                                rel="noopener noreferrer">More information</a>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input <?php echo (!empty($api_err)) ? 'is-invalid' : ''; ?>"
                                type="checkbox" id="api-check" <?= $apiChecked ?> onclick="(function(){
                                $('#api').val($('#api-check').get(0).checked);
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
            if ( $showSelfRegistration ) {
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
                                        const isChecked = $('#allow-self-registration-form-check').get(0).checked;
                                        if (!isChecked) {
                                            $('#auto-enroll-upon-self-registration').get(0).checked = false;
                                            $('#auto-enroll-notification-email').attr('disabled', true);
                                            $('#auto-enroll-notification-email-label').toggleClass('text-muted-more', true);
                                        }
                                        $('#auto-enroll-upon-self-registration').attr('disabled', !isChecked);
                                        $('#auto-enroll-settings-title').toggleClass('text-muted-more', !isChecked);
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
                            <div class="card-title" id="auto-enroll-settings-title">
                                <strong>Should participants be automatically enrolled when they self-register?</strong><br>
                                <em>Checking this option will automatically enroll a participant in your study when they
                                    self-register.</em>
                            </div>
                            <div class="form-check" id="auto-enroll-settings">
                                <input class="form-check-input <?php echo (!empty($auto_enroll_err)) ? 'is-invalid' : ''; ?>"
                                    name="auto-enroll-upon-self-registration" type="checkbox"
                                    id="auto-enroll-upon-self-registration" <?= $autoEnrollUponSelfRegistrationChecked ?>
                                    onchange="(function(){
                                    const isChecked = $('#auto-enroll-upon-self-registration').get(0).checked;
                                    $('#auto-enroll-notification-email').attr('disabled', !isChecked);
                                    $('#auto-enroll-notification-email-label').toggleClass('text-muted-more', !isChecked);
                            })()">
                                <label class="form-check-label" style="vertical-align:middle;"
                                    for="auto-enroll-upon-self-registration">Auto-Enroll Upon Self-Registration</label>
                                <span class="invalid-feedback">
                                    <?php echo $auto_enroll_err; ?>
                                </span>
                            </div>
                            <br>
                            <div class="form-group">
                                <label id="auto-enroll-notification-email-label">Email address to notify when new participants
                                    are auto-enrolled</label>
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
<div class="modal" id="createLanguageModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="createLanguageLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="createLanguageLabel"></h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary">Submit</button>
            </div>
        </div>
    </div>
</div>
<div class='modal' id='loadingModal' tabindex='-1' aria-labelledby='loadingModalLabel' aria-hidden='true' data-bs-backdrop='static' data-bs-keyboard='false'>
    <div class='modal-dialog modal-dialog-centered modal-sm'>
        <div class='modal-content'>
            <div class='modal-body text-center'>
                <div class='spinner-border' role='status' style='width: 3rem; height: 3rem; color:<?= $module::$COLORS['primary'] ?> !important;'>
                    <span class='visually-hidden'>Loading...</span>
                </div>
                <h5 class="mt-3 text-body"><?= $module->framework->tt('mfa_messaging5') ?></h5>
            </div>
        </div>
    </div>
</div>
<script>
    (function ($, window, document) {
        window.rcpro_module = <?= $module->getJavascriptModuleObjectName() ?>;
        function showLoadingModal() {
            const loadingModal = new bootstrap.Modal(document.getElementById(`loadingModal`), {
                backdrop: `static`,
                keyboard: false
            });
            loadingModal.show();
            }
        function hideLoadingModal() {
            const loadingModalElement = document.getElementById(`loadingModal`);
            const loadingModalInstance = bootstrap.Modal.getInstance(loadingModalElement);
            if (loadingModalInstance) {
                loadingModalInstance.hide();
            }
        }
        rcpro_module.download = function(data, filename, type) {
            const file = new Blob([data], {type: type});
            const a = document.createElement("a")
            const url = URL.createObjectURL(file);
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            setTimeout(function() {
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);  
            }, 0); 
        }
        rcpro_module.parseINIString = function(data) {
            const regex = {
                section: /^\s*\[\s*([^\]]*)\s*\]\s*$/,
                param: /^\s*([\w\.\-\_]+)\s*=\s*(.*?)\s*$/,
                comment: /^\s*;.*$/
            };
            const value = {};
            const lines = data.split(/\r\n|\r|\n/);
            for (let i = 0; i < lines.length; i++) {
                if (
                    regex.comment.test(lines[i]) || 
                    regex.section.test(lines[i])) {
                    continue;
                } else if (regex.param.test(lines[i])) {
                    const match = lines[i].match(regex.param);
                    value[match[1]] = match[2];
                } 
            }
            return value;
        }
        $(document).ready(function () {
            const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
            const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl, {container: '#parent'}))
            let form = document.querySelector('#settings-form');
            form.addEventListener('change', function () {
                $('#rcpro-submit-button').attr("disabled", null);
            });
            <?php if ( $showSelfRegistration && $allowAutoEnrollSystem ) { ?>
                const isChecked = $('#allow-self-registration-form-check').get(0).checked;
                if (!isChecked) {
                    $('#auto-enroll-upon-self-registration').get(0).checked = false;
                }

                $('#auto-enroll-upon-self-registration').attr('disabled', !isChecked);
                $('#auto-enroll-settings-title').toggleClass('text-muted-more', !isChecked);
                const autoEnrollChecked = $('#auto-enroll-upon-self-registration').get(0).checked;
                $('#auto-enroll-notification-email').attr('disabled', !autoEnrollChecked);
                $('#auto-enroll-notification-email-label').toggleClass('text-muted-more', !autoEnrollChecked);
            <?php } ?>
            <?php if ( $showMfa && $allowMfaAuthenticatorApp ) { ?>
                const mfaChecked = $('#mfa-check').get(0).checked;
                $('#mfa-authenticator-app').attr('disabled', !mfaChecked);
                $('#mfa-authenticator-app-title').toggleClass('text-muted-more', !mfaChecked);
            <?php } ?>
            $('.languageChoiceActivityCheckbox').change(function() {
                const languageCode = this.name.replace('languageChoice_', '');
                const active = this.checked;
                window.rcpro_module.ajax("setLanguageActiveStatus", { languageCode, active })
                .then(response => {
                    console.log(response);
                })
                .catch(error => {
                    console.error(error);
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "There was a problem updating the language status. Please try again.",
                        showConfirmButton: false
                    });
                });
            });

            $('#add-language-from-file-btn').click(function() {
                Swal.fire({
                    title: "Upload Language File",
                    html: `
                        <div>
                            <span>Language files can be either .json or .ini files. Here are examples of each format:</span>
                            <br><br>
                            <ul class="text-left">
                                <li><strong>JSON format: </strong><a href="#" onclick="rcpro_module.downloadEnglishJson();return false;" id="download-english-json">REDCapPRO-English.json</a></span></li>
                                <li><strong>INI format: </strong><a href="#" onclick="rcpro_module.downloadEnglishIni();return false;" id="download-english-ini">REDCapPRO-English.ini</a></span></li>
                            </ul>
                        </div>
                        <input type="file" id="language-file-input" class="form-control" accept=".json,.ini">
                    `,
                    showCancelButton: true,
                    confirmButtonText: "Upload",
                    preConfirm: async () => {
                        const fileInput = document.getElementById('language-file-input');
                        if (fileInput.files.length === 0) {
                            Swal.showValidationMessage("Please select a file to upload.");
                            return;
                        }
                        const file = fileInput.files[0];
                        return new Promise((resolve, reject) => {
                            const reader = new FileReader();
                            reader.onload = function(event) {
                                try {
                                    if (/\.ini$/i.test(file.name)) {
                                        const iniString = event.target.result;
                                        const languageData = rcpro_module.parseINIString(iniString);
                                        resolve(languageData);
                                    } else if (/\.json$/i.test(file.name)) {
                                        const jsonString = event.target.result;
                                        const languageData = JSON.parse(jsonString);
                                        resolve(languageData);
                                    } else {
                                        reject("Unsupported file type. Please upload a .json or .ini file.");
                                        return;
                                    }
                                } catch (e) {
                                    reject("Invalid file. " + e.message);
                                }
                            };
                            reader.onerror = function() {
                                reject("There was an error reading the file.");
                            };
                            reader.readAsText(file);
                        });
                    }
                }).then((result) => {
                    showLoadingModal();
                    if (result.isConfirmed) {
                        const strings = result.value;
                        window.rcpro_module.ajax("getLanguage", { languageCode: "English" })
                        .then(response => {
                            hideLoadingModal();
                            const englishStrings = response.EnglishStrings;
                            window.rcpro_module.openAddLanguageModal(strings, englishStrings, true);
                        })
                        .catch(error => {
                            hideLoadingModal();
                            console.error(error);
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: "There was a problem loading the language template. Please try again.",
                                showConfirmButton: false
                            });
                        });
                    }
                });
            });

            window.rcpro_module.openAddLanguageModal = function(strings, EnglishStrings, createNew = true, languageCode = "") {
            
                let modalBody = '<div>';
                modalBody += ``;
                if (createNew) {
                    modalBody += `<div class="form-group">
                    <label for="new-language-code" class="form-label"><h3>Language Code</h3></label>
                    <input type="text" class="form-control" id="new-language-code" name="new-language-code" placeholder="e.g. Spanish" value="${languageCode}">
                    </div>`;
                }
                modalBody += `<form id="create-language-form">`;
                for (const [key, value] of Object.entries(EnglishStrings)) {
                    modalBody += `<div class="card mb-3">
                        <div class="card-body bg-light">
                            <h3 class="card-title">${key}</h3>`;
                    for (const [langKey, langValue] of Object.entries(value)) {
                            modalBody += `
                            <label for="${langKey}" class="form-label mt-3 text-danger">${langKey}</label>
                            <div class="form-inline"><span><strong>Default text:</strong></span>&nbsp;<span style="user-select: all;">${langValue}</span></div>
                            <input type="text" class="form-control mb-2" id="${langKey}" name="${langKey}" value="${strings[langKey] || ""}">`;
                    }
                    modalBody += `</div></div>`;
                }
                modalBody += '</form></div>';
                $('#createLanguageModal .modal-body').html(modalBody);
            
                $('#createLanguageModal .btn-primary').off('click').on('click', function() {
                    if (createNew) {
                        languageCode = $('#new-language-code').val().trim();
                    } 
                    if (languageCode === "") {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: "Language code cannot be empty.",
                            showConfirmButton: false
                        });
                        return;
                    }
                    const languageStrings = $('#create-language-form').serializeObject();

                    showLoadingModal();
                    window.rcpro_module.ajax("setLanguage", { code: languageCode, strings: languageStrings })
                    .then(() => {
                        hideLoadingModal();
                        $('#createLanguageModal').modal('hide');
                        Swal.fire({
                            icon: "success",
                            title: "Success",
                            text: "Language created successfully.",
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    })
                    .catch(error => {
                        hideLoadingModal();
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: "There was a problem creating the language. Please try again.",
                            showConfirmButton: false
                        });
                    });
                });
                $('#createLanguageModal #createLanguageLabel').text(createNew ? "Create Language" : "Edit Language");
                $('#createLanguageModal').modal('show');
            };

            $('#add-language-manually-btn').click(function() {
                showLoadingModal();
                window.rcpro_module.ajax("getLanguage", { languageCode: "English" })
                .then(response => {
                    hideLoadingModal();
                    const EnglishStrings = response.EnglishStrings;
                    window.rcpro_module.openAddLanguageModal({}, EnglishStrings, true);
                })
                .catch(error => {
                    hideLoadingModal();
                    console.error(error);
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "There was a problem loading the language template. Please try again.",
                        showConfirmButton: false
                    });
                });
            });

            $('.delete-language-btn').click(function() {
                const languageCode = this.dataset.langCode;
                Swal.fire({
                    title: "Are you sure?",
                    html: `This will permanently delete the <strong>${languageCode}</strong> language and all of its translations.`,
                    showCancelButton: true,
                    focusConfirm: false,
                    focusCancel: true,
                    confirmButtonText: "Delete",
                }).then((result) => {
                    if (result.isConfirmed) {
                        showLoadingModal();
                        window.rcpro_module.ajax("deleteLanguage", { languageCode })
                        .then(() => {
                            hideLoadingModal();
                            Swal.fire({
                                title: "Deleted",
                                text: "Language deleted successfully.",
                                icon: "success",
                            }).then(() => {
                                location.reload();
                            });
                        })
                        .catch(error => {
                            hideLoadingModal();
                            console.error(error);
                            Swal.fire({
                                title: "Error",
                                text: "There was a problem deleting the language. Please try again.",
                                icon: "error"
                            });
                        });
                    }
                });
            });

            $('.edit-language-btn').click(function() {
                var languageCode = this.dataset.langCode;
                showLoadingModal();
                window.rcpro_module.ajax("getLanguage", { languageCode })
                .then(response => {
                    hideLoadingModal();
                    console.log(response);
                    const languageStrings = response.strings;
                    const englishStrings = response.EnglishStrings;
                    window.rcpro_module.openAddLanguageModal(languageStrings, englishStrings, false, languageCode);
                })
                .catch(error => {
                    hideLoadingModal();
                    console.error(error);
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "There was a problem loading the language. Please try again.",
                        showConfirmButton: false
                    });
                });
            });

            $('.copy-language-btn').click(function() {
                var languageCode = this.dataset.langCode;
                showLoadingModal();
                window.rcpro_module.ajax("getLanguage", { languageCode })
                .then(response => {
                    hideLoadingModal();
                    console.log(response);
                    const languageStrings = response.strings;
                    const englishStrings = response.EnglishStrings;
                    const newLanguageCode = languageCode + " Copy";
                    window.rcpro_module.openAddLanguageModal(languageStrings, englishStrings, true, newLanguageCode);
                })
                .catch(error => {
                    hideLoadingModal();
                    console.error(error);
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "There was a problem copying the language. Please try again.",
                        showConfirmButton: false
                    });
                });
            });

            $('.download-language-btn').click(function() {
                const languageCode = this.dataset.langCode;
                const format = this.dataset.format;
                if (!languageCode || !format) {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "Language code or format not specified.",
                        showConfirmButton: false
                    });
                    return;
                }
                showLoadingModal();
                window.rcpro_module.ajax("downloadLanguageFile", { languageCode, format })
                .then(response => {
                    hideLoadingModal();
                    if (response.status === "error" || response.error) {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: "There was a problem downloading the file. Please try again. Error: " + response.error,
                            showConfirmButton: false
                        })
                        return;
                    }
                    const extension = format === "json" ? "json" : "ini";
                    const type = format === "json" ? "application/json" : "application/ini";
                    const filename = `REDCapPRO-${languageCode}.${extension}`;
                    rcpro_module.download(response.fileContents, filename, type);
                })
                .catch(error => {
                    console.error(error);
                    hideLoadingModal();
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "There was a problem downloading the language file. Please try again.",
                        showConfirmButton: false
                    });
                });
            });

            rcpro_module.downloadEnglishJson = function () {
                console.log("Downloading English JSON file");
                showLoadingModal();
                rcpro_module.ajax("downloadLanguageFile", { languageCode: "English", format: "json" })
                .then(response => {
                    hideLoadingModal();
                    console.log(response);
                    if (response.status === "error" || response.error) {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: "There was a problem downloading the file. Please try again. Error: " + response.error,
                            showConfirmButton: false
                        })
                        return;
                    }
                    rcpro_module.download(response.fileContents, "REDCapPRO-English.json", "application/json");
                })
                .catch(error => {
                    console.error(error);
                    hideLoadingModal();
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "There was a problem downloading the file. Please try again.",
                        showConfirmButton: false
                    });
                });
            };

            rcpro_module.downloadEnglishIni = function () {
                console.log("Downloading English INI file");
                showLoadingModal();
                rcpro_module.ajax("downloadLanguageFile", { languageCode: "English", format: "ini" })
                .then(response => {
                    hideLoadingModal();
                    console.log(response);
                    if (response.status === "error" || response.error) {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: "There was a problem downloading the file. Please try again. Error: " + response.error,
                            showConfirmButton: false
                        })
                        return;
                    }
                    rcpro_module.download(response.fileContents, "REDCapPRO-English.ini", "application/ini");
                })
                .catch(error => {
                    hideLoadingModal();
                    console.error(error);
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "There was a problem downloading the file. Please try again.",
                        showConfirmButton: false
                    });
                });
            };
        });
    })(window.jQuery, window, document);
</script>

<?php
include_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';