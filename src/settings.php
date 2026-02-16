<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

$role = $module->getUserRole($module->safeGetUsername()); // 3=admin/manager, 2=user, 1=monitor, 0=not found
if ( $role < 3 ) {
    header("location:" . $module->getUrl("src/home.php"));
}
$module->includeFont();
$language = new Language($module);
$language->handleLanguageChangeRequest();   

require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$ui = new UI($module);
$ui->ShowHeader("Settings");
echo "<title>" . $module->APPTITLE . " - Settings</title>
<link rel='stylesheet' type='text/css' href='" . $module->getUrl('src/css/rcpro.php') . "'/>
<script src='" . $module->getUrl('lib/jQuery/jquery.highlight.js') . "'></script>";
// Check for errors
if ( isset($_GET["error"]) ) {
    ?>
    <script>
        Swal.fire({
            icon: "error",
            title: "<?= $module->tt("project_error") ?>",
            text: "<?= $module->tt("project_error_general") ?>",
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
            $lang_err = $module->tt("project_settings_lang_err");
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
            $email_err = $module->tt("project_settings_email_err");
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
                $timeout_time_err = $module->tt("project_settings_time_err1");
                $any_err          = true;
            }
            $timeout_maximum = $projectSettings->getMaximumTimeoutMinutes();
            if ($new_settings["timeout-time"] > $timeout_maximum) {
                $timeout_time_err = $module->tt("project_settings_time_err2", [$timeout_maximum]);
                $any_err          = true;
            }
        }

        if ( !$any_err ) {

            $module->setProjectSettings($new_settings);
            ?>
            <script>
                Swal.fire({
                    icon: "success",
                    title: "<?= $module->tt("project_settings_saved") ?>",
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
                title: "<?= $module->tt("project_error") ?>",
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
$module->tt_transferToJavascriptModuleObject();
?>
<div class="settingsContainer wrapper" style="display: none;">
    <h2><?= $module->tt("project_settings_page_title") ?></h2>
    <p><?= $module->tt("project_settings_subtitle") ?></p>
    <div id="parent">
        <form class="rcpro-form" id="settings-form" action="<?= $module->getUrl("src/settings.php"); ?>" method="POST"
            enctype="multipart/form-data" target="_self">
            <div class="card">
                <div class="card-header">
                    <span class="fa-stack">
                        <i class="fas fa-globe fa-2x"></i>
                    </span>
                    <nbsp></nbsp><strong><?= $module->tt("project_settings_languages_and_translation") ?></strong>
                </div>
                <div class="card-body">
                    <div class="card-title"><?= $module->tt("project_settings_languages_and_translation_desc") ?></div>
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
                    <div class="card-title"><?= $module->tt("project_settings_languages_and_translation_info") ?></div>

                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col"></th>
                                <th class="text-center" scope="col"><?= $module->tt("project_active") ?></th>
                                <th class="text-center" scope="col"><?= $module->tt("project_actions") ?></th>
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
                                $editTooltip = $module->tt($lang_item["built_in"] ? "project_settings_lang_edit_builtin" : "project_settings_lang_edit");
                                $deleteTooltip = $module->tt($lang_item["built_in"] ? "project_settings_lang_delete_builtin" : "project_settings_lang_delete");
                                ?>
                            
                                <tr>
                                    <th scope="row"><?= $lang_item["code"] ?></th>
                                    <td class="d-flex justify-content-center align-items-center">
                                        <div class="form-check form-switch">
                                            <input role="button" class="form-check-input languageChoiceActivityCheckbox" 
                                                name="languageChoice_<?= $lang_item["code"] ?>" 
                                                type="checkbox" role="switch" 
                                                id="switchCheckDefault" <?= $active ?> <?= $activeDisabled ?>>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-toolbar justify-content-center" role="toolbar" aria-label="<?= $module->tt("project_settings_lang_actions") ?>">
                                            
                                            <div data-bs-toggle="tooltip" data-bs-title="<?=$editTooltip?>">
                                                <button type="button" class="btn btn-sm btn-outline-secondary edit-language-btn me-1" data-lang-code="<?= $lang_item["code"] ?>" <?= $builtIn ? "disabled" : "" ?>>
                                                    <i class="fas fa-pencil"></i>
                                                </button>
                                            </div>
                                            <div data-bs-toggle="tooltip" data-bs-title="<?=$module->tt("project_settings_lang_copy")?>">
                                                <button type="button" class="btn btn-sm btn-outline-secondary copy-language-btn me-1" data-lang-code="<?= $lang_item["code"] ?>" data-lang-builtIn=<?= $builtIn ? "true" : "false" ?>>
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </div>
                                            <div class="dropdown me-1" data-bs-toggle="tooltip" data-bs-title="<?=$module->tt("project_settings_lang_download")?>" >
                                                <button type="button" class="btn btn-sm btn-outline-secondary btn-toggle" data-bs-toggle="dropdown" aria-expanded="false" >
                                                    <i class="fas fa-download"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item download-language-btn" href="#" data-lang-code="<?= $lang_item["code"] ?>" data-format="json"><i class="fa-light fa-file-brackets-curly"></i> <?= $module->tt("project_settings_lang_json_format") ?></a></li>
                                                    <li><a class="dropdown-item download-language-btn" href="#" data-lang-code="<?= $lang_item["code"] ?>" data-format="ini"><i class="fa-light fa-file-lines"></i> <?= $module->tt("project_settings_lang_ini_format") ?></a></li>
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
                        <button type="button" class="btn btn-sm btn-success btn-toggle" data-bs-toggle="dropdown" aria-expanded="false" id="add-language-btn"><?= $module->tt('project_settings_lang_add')?> <i class="fas fa-caret-down"></i></button>
                        <ul class="dropdown-menu" >
                            <li><a class="dropdown-item" href="#" id="add-language-from-file-btn"><i class="fas fa-file-import"></i> <?= $module->tt('project_settings_lang_from_file')?></a></li>
                            <li><a class="dropdown-item" href="#" id="add-language-manually-btn"><i class="fas fa-keyboard"></i> <?= $module->tt('project_settings_lang_manual_entry')?></a></li>
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
                        <strong><?= $module->tt("project_settings_prevent_email_login"); ?></strong>
                    </div>
                    <div class="card-body">
                        <div class="card-title">
                            <?= $module->tt("project_settings_prevent_email_login_desc"); ?><br>
                        </div>
                        <div class="form-check">
                            <input
                                class="form-check-input <?php echo (!empty($prevent_email_login_err)) ? 'is-invalid' : ''; ?>"
                                type="checkbox" id="prevent-email-login-check" <?= $checked ?> onclick="(function(){
                                $('#prevent-email-login').val($('#prevent-email-login-check').get(0).checked);
                            })()">
                            <label class="form-check-label" style="vertical-align:middle;"
                                for="prevent-email-login-check"><?= $module->tt("project_settings_prevent_email_login_label"); ?></label>
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
                        <strong><?= $module->tt("project_settings_timeout_time"); ?></strong>
                    </div>
                    <div class="card-body">
                        <div class="card-title">
                            <?= $module->tt("project_settings_timeout_time_desc"); ?>
                        </div>
                        <div class="form-group">
                            <label><?= $module->tt("project_settings_timeout_time_label"); ?></label>
                            <input type="text" name="timeout-time"
                                class="form-control <?php echo (!empty($timeout_time_err)) ? 'is-invalid' : ''; ?>"
                                value="<?php echo \REDCap::escapeHtml($projectSettings->getProjectTimeoutMinutes($project_id)); ?>">
                            <span class="invalid-feedback">
                                <?php echo $timeout_time_err; ?>
                            </span>
                        </div>
                        <p>
                            <span>
                                <?= $module->tt("project_settings_timeout_time_default_info", \REDCap::escapeHtml($projectSettings->getSystemTimeoutMinutes())) ?>
                            </span>
                            <br>
                            <span>
                                <?= $module->tt("project_settings_timeout_time_max_info", \REDCap::escapeHtml($projectSettings->getMaximumTimeoutMinutes())) ?>
                            </span>
                        </p>
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
                        <strong><?= $module->tt("project_settings_mfa"); ?></strong>
                    </div>
                    <div class="card-body">
                        <div class="card-title">
                            <strong><?= $module->tt("project_settings_mfa_desc"); ?></strong><br>
                            <em><?= $module->tt("project_settings_mfa_desc2"); ?></em>
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
                            <label class="form-check-label" style="vertical-align:middle;" for="mfa-check"><?= $module->tt("project_settings_mfa_label"); ?></label>
                            <input type="text" name="mfa" id="mfa"
                                value="<?= $mfaChecked === "checked" ? "true" : "false" ?>" hidden>
                            <span class="invalid-feedback">
                                <?php echo $mfa_err; ?>
                            </span>
                        </div>
                        <?php if ( $allowMfaAuthenticatorApp ) { ?>
                            <br><br>
                            <div class="card-title" id="mfa-authenticator-app-title">
                                <strong><?= $module->tt("project_settings_mfa_authenticator_app_desc"); ?></strong><br>
                                <em><?= $module->tt("project_settings_mfa_authenticator_app_desc2"); ?></em>
                            </div>
                            <div class="form-check" id="auto-enroll-settings">
                                <input class="form-check-input" name="mfa-authenticator-app" type="checkbox"
                                    id="mfa-authenticator-app" <?= $mfaAuthenticatorAppChecked ?>>
                                <label class="form-check-label" style="vertical-align:middle;" for="mfa-authenticator-app"><?= $module->tt("project_settings_mfa_authenticator_app_label"); ?></label>
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
                        <strong><?= $module->tt("project_settings_api"); ?></strong>
                    </div>
                    <div class="card-body">
                        <div class="card-title">
                            <strong><?= $module->tt("project_settings_api_desc"); ?></strong><br>
                            <em><?= $module->tt("project_settings_api_desc2"); ?></em><br>
                            <a href="https://github.com/AndrewPoppe/REDCap-PRO#api" target="_blank"
                                rel="noopener noreferrer"><?= $module->tt("project_settings_more_information"); ?></a>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input <?php echo (!empty($api_err)) ? 'is-invalid' : ''; ?>"
                                type="checkbox" id="api-check" <?= $apiChecked ?> onclick="(function(){
                                $('#api').val($('#api-check').get(0).checked);
                            })()">
                            <label class="form-check-label" style="vertical-align:middle;" for="api-check"><?= $module->tt("project_settings_api_label"); ?></label>
                            <input type="text" name="api" id="api"
                                value="<?= $apiChecked === "checked" ? "true" : "false" ?>" hidden>
                            <span class="invalid-feedback">
                                <?php echo $api_err; ?>
                            </span>
                        </div>
                        <p>
                            <?= $module->tt("project_settings_api_info", $module->getProjectlessUrl("src/api.php", true, true)) ?>
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
                        <strong><?= $module->tt("project_settings_self_registration"); ?></strong>
                    </div>
                    <div class="card-body">
                        <div class="card-title">
                            <strong><?= $module->tt("project_settings_self_registration_desc"); ?></strong><br>
                            <em><?= $module->tt("project_settings_self_registration_desc2"); ?></em>
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
                                for="allow-self-registration-form-check"><?= $module->tt("project_settings_self_registration_label"); ?></label>
                            <span class="invalid-feedback">
                                <?php echo $self_registration_err; ?>
                            </span>
                        </div>
                        <?php if ( $allowAutoEnrollSystem ) { ?>
                            <br><br>
                            <div class="card-title" id="auto-enroll-settings-title">
                                <strong><?= $module->tt("project_settings_auto_enrollment_desc"); ?></strong><br>
                                <em><?= $module->tt("project_settings_auto_enrollment_desc2"); ?></em>
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
                                    for="auto-enroll-upon-self-registration"><?= $module->tt("project_settings_auto_enrollment_label"); ?></label>
                                <span class="invalid-feedback">
                                    <?php echo $auto_enroll_err; ?>
                                </span>
                            </div>
                            <br>
                            <div class="form-group">
                                <label id="auto-enroll-notification-email-label"><?= $module->tt("project_settings_auto_enrollment_email"); ?></label>
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
                    <strong><?= $module->tt("project_settings_primary_contact"); ?></strong>
                </div>
                <div class="card-body">
                    <div class="card-title">
                        <?= $module->tt("project_settings_primary_contact_desc"); ?>
                    </div>
                    <div class="form-group">
                        <label><?= $module->tt("project_name"); ?></label>
                        <input type="text" name="pc-name"
                            class="form-control <?php echo (!empty($name_err)) ? 'is-invalid' : ''; ?>"
                            value="<?php echo \REDCap::escapeHtml($settings["pc-name"]); ?>">
                        <span class="invalid-feedback">
                            <?php echo $name_err; ?>
                        </span>
                    </div>
                    <div class="form-group">
                        <label><?= $module->tt("project_email"); ?></label>
                        <input type="email" name="pc-email"
                            class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>"
                            value="<?php echo \REDCap::escapeHtml($settings["pc-email"]); ?>">
                        <span class="invalid-feedback">
                            <?php echo $email_err; ?>
                        </span>
                    </div>
                    <div class="form-group">
                        <label><?= $module->tt("project_phone"); ?></label>
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
                    })()"><?= $module->tt("project_cancel"); ?></button>
                <button type="submit" id="rcpro-submit-button" class="btn btn-rcpro" value="Submit" disabled>
                    <?= $module->tt("project_save_settings"); ?>
                </button>
            </div>
            <input type="hidden" name="redcap_csrf_token" value="<?= $module->framework->getCSRFToken() ?>">
        </form>
    </div>
</div>
<div class="modal" id="createLanguageModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="createLanguageLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content h-100">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="createLanguageLabel"></h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= $module->tt("project_cancel"); ?></button>
                <button type="button" class="btn btn-primary"><?= $module->tt("project_submit"); ?></button>
            </div>
        </div>
    </div>
</div>
<div class='modal' id='loadingModal' tabindex='-1' aria-labelledby='loadingModalLabel' aria-hidden='true' data-bs-backdrop='static' data-bs-keyboard='false'>
    <div class='modal-dialog modal-dialog-centered modal-sm'>
        <div class='modal-content'>
            <div class='modal-body text-center'>
                <div class='spinner-border' role='status' style='width: 3rem; height: 3rem; color:<?= $module::$COLORS['primary'] ?> !important;'>
                    <span class='visually-hidden'><?= $module->tt("project_loading"); ?></span>
                </div>
                <h5 class="mt-3 text-body"><?= $module->tt('project_please_wait') ?></h5>
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
                        title: "<?= $module->tt("project_error") ?>",
                        text: "<?= $module->tt("project_settings_language_update_error") ?>",
                        showConfirmButton: false
                    });
                });
            });

            $('#add-language-from-file-btn').click(function() {
                Swal.fire({
                    title: "<?= $module->tt("project_settings_upload_language_file") ?>",
                    html: `
                        <div>
                            <span><?= $module->tt("project_settings_upload_language_file_desc") ?></span>
                            <br><br>
                            <ul class="text-left">
                                <li><strong><?= $module->tt("project_settings_json_format_label") ?> </strong><a href="#" onclick="rcpro_module.downloadEnglishJson();return false;" id="download-english-json">REDCapPRO-English.json</a></span></li>
                                <li><strong><?= $module->tt("project_settings_ini_format_label") ?> </strong><a href="#" onclick="rcpro_module.downloadEnglishIni();return false;" id="download-english-ini">REDCapPRO-English.ini</a></span></li>
                            </ul>
                        </div>
                        <input type="file" id="language-file-input" class="form-control" accept=".json,.ini">
                    `,
                    showCancelButton: true,
                    confirmButtonText: "<?= $module->tt("project_submit") ?>",
                    cancelButtonText: "<?= $module->tt("project_cancel") ?>",
                    preConfirm: async () => {
                        const fileInput = document.getElementById('language-file-input');
                        if (fileInput.files.length === 0) {
                            Swal.showValidationMessage("<?= $module->tt("project_settings_language_upload_error1") ?>");
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
                                        reject("<?= $module->tt("project_settings_language_upload_error2") ?>");
                                        return;
                                    }
                                } catch (e) {
                                    reject("<?= $module->tt("project_settings_language_upload_error3") ?> " + e.message);
                                }
                            };
                            reader.onerror = function() {
                                reject("<?= $module->tt("project_settings_language_upload_error4") ?>");
                            };
                            reader.readAsText(file);
                        });
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        showLoadingModal();
                        const strings = result.value;
                        window.rcpro_module.ajax("getLanguage", { languageCode: "English" })
                        .then(response => {
                            hideLoadingModal();
                            window.rcpro_module.openAddLanguageModal({
                                strings: strings, 
                                EnglishStrings: response.EnglishStrings, 
                                createNew: true,
                            });
                        })
                        .catch(error => {
                            hideLoadingModal();
                            console.error(error);
                            Swal.fire({
                                icon: "error",
                                title: "<?= $module->tt("project_error") ?>",
                                text: "<?= $module->tt("project_settings_language_upload_error5") ?>",
                                showConfirmButton: false
                            });
                        });
                    }
                });
            });


            /**
             * @typedef {Object} AddLanguageOptions
             * @property {Object} strings - The language strings for the language being added/edited. This is an object where the keys are the string identifiers and the values are the translated strings. When creating a new language, this will be an empty object. When editing an existing language, this will be the current strings for that language.
             * @property {Object} EnglishStrings - The English language strings.
             * @property {boolean} createNew - Whether this is creating a new language or editing an existing one
             * @property {string} languageCode - The code for the language being edited. This is only used when editing an existing language
             * @property {'ltr' | 'rtl'} languageDirection - The text direction for the language being added/edited. This is either "ltr" for left-to-right languages or "rtl" for right-to-left languages.
             */

            /**
             * @param {AddLanguageOptions} options 
             */
            window.rcpro_module.openAddLanguageModal = function(options) {

                console.log(options);
                const allStringSections = Object.keys(options.EnglishStrings);
                const majorSections = Set.from(allStringSections.map(key => key.split(' - ')[0]).filter(section => section !== "Control Center Pages"));
                const sections = {};
                majorSections.forEach(major => {
                    sections[major] = allStringSections.filter(key => key.split(' - ')[0] === major);
                });
                window.things = {
                    allStringSections,
                    majorSections,
                    sections
                }
            
                let modalBody = `<div>
                <ul class="nav nav-tabs" id="languageTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-body active" id="language-settings-tab" data-bs-toggle="tab" data-bs-target="#language-settings-tab-pane" type="button" role="tab" aria-controls="language-settings-tab-pane" aria-selected="true"><i class="fa-solid fa-gears"></i> <?= $module->tt("project_settings_title") ?></button>
                    </li>`;
                    majorSections.forEach(major => {
                        const majorLabel = major.replaceAll(' ', '-');
                        modalBody += `<li class="nav-item" role="presentation">
                            <button class="nav-link text-primary" id="${majorLabel}-tab" data-bs-toggle="tab" data-bs-target="#${majorLabel}-tab-pane" type="button" role="tab" aria-controls="${majorLabel}-tab-pane" aria-selected="false"><i class="fa-solid fa-language"></i> ${major}</button>
                        </li>`;
                    });
                modalBody += `</ul>
                <form id="create-language-form">
                <div class="tab-content" id="languageTabContent">
                    <div class="tab-pane show active" id="language-settings-tab-pane" role="tabpanel" aria-labelledby="language-settings-tab" tabindex="0">
                        <div class="container mt-4">
                            <h3><?= $module->tt("project_settings_language_settings_title") ?></h3>
                            <div class="mt-3">
                                <div class="mb-3">
                                    <label for="new-language-code" class="form-label"><h4><?= $module->tt("project_settings_language_code") ?></h4></label>
                                    <input type="text" class="form-control" id="new-language-code" placeholder="<?= $module->tt("project_settings_language_code_placeholder") ?>" value="${options.languageCode ?? ""}" ${!options.createNew ? "disabled" : ""}>
                                </div>                  
                                <div class="mb-3">
                                    <label for="language-direction-select" class="form-label"><h4><?= $module->tt("project_settings_language_direction") ?></h4></label>
                                    <select class="form-select" id="language-direction-select">
                                        <option value="ltr" ${options.languageDirection !== "rtl" ? "selected" : ""}><?= $module->tt("project_settings_language_direction_ltr") ?></option>
                                        <option value="rtl" ${options.languageDirection === "rtl" ? "selected" : ""}><?= $module->tt("project_settings_language_direction_rtl") ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>`;
                majorSections.forEach(major => {
                    const majorLabel = major.replaceAll(' ', '-');
                    modalBody += `<div class="tab-pane" id="${majorLabel}-tab-pane" role="tabpanel" aria-labelledby="${majorLabel}-tab" tabindex="0">
                    <div class="container mt-4">
                    <h3><?= $module->tt("project_settings_language_translations") ?> - ${major}</h3>
                    <input type="search" incremental="true" class="form-control mb-3 translation-filter" placeholder="<?= $module->tt("project_settings_language_search_placeholder") ?>">
                    <div class="translation-section">
                    `;
                    sections[major].forEach(minorSection => {
                        modalBody += `<div class="card mb-3">
                        <div class="card-header">
                            <h4 class="card-title">${minorSection.replace(/^.*?- /, '')}</h4>
                        </div>
                        <div class="card-body">`;
                        for (const [langKey, langValue] of Object.entries(options.EnglishStrings[minorSection] || {})) {
                            modalBody += `
                            <div class="translation-entry">
                                <label for="${langKey}" class="form-label mt-3 text-danger">${langKey}</label>
                                <div class="form-inline"><span><strong><?= $module->tt("project_settings_language_default_text") ?></strong></span>&nbsp;<span style="user-select: all;">${langValue}</span></div>
                                <input type="text" class="form-control mb-2" id="${langKey}" name="${langKey}" value="${options?.strings?.[langKey] || ""}">
                            </div>`;
                        }
                        modalBody += `</div></div>`;
                    });
                    modalBody += '</div></div></div></form>';
                });

                $('#createLanguageModal .modal-body').html(modalBody);
            
                $('#createLanguageModal .btn-primary').off('click').on('click', function() {
                    if (options.createNew) {
                        options.languageCode = $('#new-language-code').val().trim();
                    } 
                    if (options.languageCode === "") {
                        Swal.fire({
                            icon: "error",
                            title: "<?= $module->tt("project_error") ?>",
                            text: "<?= $module->tt("project_settings_language_code_required") ?>",
                            showConfirmButton: false
                        });
                        return;
                    }
                    const languageDirection = $('#language-direction-select').val();
                    const languageStrings = $('#create-language-form').serializeObject();

                    showLoadingModal();
                    window.rcpro_module.ajax("setLanguage", { code: options.languageCode, strings: languageStrings, direction: languageDirection })
                    .then((response) => {
                        hideLoadingModal();
                        if (response.error) {
                            throw new Error(response.error);
                        }
                        $('#createLanguageModal').modal('hide');
                        Swal.fire({
                            icon: "success",
                            title: "<?= $module->tt("project_success") ?>",
                            text: "<?= $module->tt("project_settings_language_created_success") ?>",
                            showConfirmButton: false
                        }).then(() => {
                            window.location.replace(window.location.href.replace(/\#.*/,''));
                        });
                    })
                    .catch(error => {
                        hideLoadingModal();
                        Swal.fire({
                            icon: "error",
                            title: "<?= $module->tt("project_error") ?>",
                            text: error.message || error,
                            showConfirmButton: false
                        });
                    });
                });
                $('#createLanguageModal #createLanguageLabel').text(options.createNew ? "<?= $module->tt("project_settings_create_language") ?>" : "<?= $module->tt("project_settings_edit_language") ?>");
                $(".translation-filter").on("input", function() {
                    const translationSection = $(this).siblings('.translation-section').first();
                    translationSection.unhighlight();
                    translationSection.find('.card').toggleClass('glowing-border', $(this).val().trim() !== "");
                    const filterValue = $(this).val().trim().toLowerCase();
                    if (filterValue === "") {
                        translationSection.find('.card').show();
                        translationSection.find('.translation-entry').show();
                        return;
                    }
                    translationSection.find('.card').filter(function() {
                        let anyFound = false;
                        let showAll = false;
                        if ($(this).find('.card-title').text().toLowerCase().indexOf(filterValue) > -1) {
                            anyFound = true;
                            showAll = true;
                        }
                        $(this).find('.translation-entry').each(function() {
                            let entryMatches = false;
                            if ($(this).text().toLowerCase().indexOf(filterValue) > -1) {
                                anyFound = true;
                                entryMatches = true;
                            } else if ($(this).find('input').val().toLowerCase().indexOf(filterValue) > -1) {
                                anyFound = true;
                                entryMatches = true;
                            }
                            $(this).toggle(entryMatches || showAll);
                        });
                        $(this).toggle(anyFound);
                    });
                    if (filterValue !== "") {
                        translationSection.highlight(filterValue);
                    }
                });
                $('#createLanguageModal').modal('show');
            };

            $('#add-language-manually-btn').click(function() {
                showLoadingModal();
                window.rcpro_module.ajax("getLanguage", { languageCode: "English" })
                .then(response => {
                    hideLoadingModal();
                    const EnglishStrings = response.EnglishStrings;
                    window.rcpro_module.openAddLanguageModal({strings: {}, EnglishStrings, createNew: true});
                })
                .catch(error => {
                    hideLoadingModal();
                    console.error(error);
                    Swal.fire({
                        icon: "error",
                        title: "<?= $module->tt("project_error") ?>",
                        text: "<?= $module->tt("project_settings_language_load_error") ?>",
                        showConfirmButton: false
                    });
                });
            });

            $('.delete-language-btn').click(function() {
                const languageCode = this.dataset.langCode;
                Swal.fire({
                    title: "<?= $module->tt("project_are_you_sure") ?>",
                    html: window.rcpro_module.tt("project_settings_delete_warning", languageCode),
                    showCancelButton: true,
                    focusConfirm: false,
                    focusCancel: true,
                    confirmButtonText: "<?= $module->tt("project_delete") ?>",
                    cancelButtonText: "<?= $module->tt("project_cancel") ?>"
                }).then((result) => {
                    if (result.isConfirmed) {
                        showLoadingModal();
                        window.rcpro_module.ajax("deleteLanguage", { languageCode })
                        .then(() => {
                            hideLoadingModal();
                            Swal.fire({
                                title: "<?= $module->tt("project_deleted") ?>",
                                text: "<?= $module->tt("project_settings_delete_language_success") ?>",
                                icon: "success",
                            }).then(() => {
                                window.location.replace(window.location.href.replace(/\#.*/,''));
                            });
                        })
                        .catch(error => {
                            hideLoadingModal();
                            console.error(error);
                            Swal.fire({
                                title: "<?= $module->tt("project_error") ?>",
                                text: "<?= $module->tt("project_settings_delete_language_error") ?>",
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
                    const languageDirection = response.direction || "ltr";
                    window.rcpro_module.openAddLanguageModal({
                        strings: languageStrings, 
                        EnglishStrings: englishStrings, 
                        createNew: false, 
                        languageCode,
                        languageDirection
                    });
                })
                .catch(error => {
                    hideLoadingModal();
                    console.error(error);
                    Swal.fire({
                        icon: "error",
                        title: "<?= $module->tt("project_error") ?>",
                        text: "<?= $module->tt("project_settings_language_loading_error") ?>",
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
                    const languageDirection = response.direction || "ltr";
                    const newLanguageCode = languageCode + " <?= $module->tt("project_copy") ?>";
                    window.rcpro_module.openAddLanguageModal({
                        strings: languageStrings, 
                        EnglishStrings: englishStrings, 
                        createNew: true, 
                        languageCode: newLanguageCode, 
                        languageDirection
                    });
                })
                .catch(error => {
                    hideLoadingModal();
                    console.error(error);
                    Swal.fire({
                        icon: "error",
                        title: "<?= $module->tt("project_error") ?>",
                        text: "<?= $module->tt("project_settings_language_copy_error") ?>",
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
                        title: "<?= $module->tt("project_error") ?>",
                        text: "<?= $module->tt("project_settings_missing_language_code") ?>",
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
                            title: "<?= $module->tt("project_error") ?>",
                            text: "<?= $module->tt("project_settings_language_download_error") ?> " + response.error,
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
                        title: "<?= $module->tt("project_error") ?>",
                        text: "<?= $module->tt("project_settings_language_download_error_general") ?>",
                        showConfirmButton: false
                    });
                });
            });

            rcpro_module.downloadEnglishJson = function () {
                showLoadingModal();
                rcpro_module.ajax("downloadLanguageFile", { languageCode: "English", format: "json" })
                .then(response => {
                    hideLoadingModal();
                    console.log(response);
                    if (response.status === "error" || response.error) {
                        Swal.fire({
                            icon: "error",
                            title: "<?= $module->tt("project_error") ?>",
                            text: "<?= $module->tt("project_settings_language_download_error") ?> " + response.error,
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
                        title: "<?= $module->tt("project_error") ?>",
                        text: "<?= $module->tt("project_settings_language_download_error_general") ?>",
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
                            title: "<?= $module->tt("project_error") ?>",
                            text: "<?= $module->tt("project_settings_language_download_error") ?> " + response.error,
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
                        title: "<?= $module->tt("project_error") ?>",
                        text: "<?= $module->tt("project_settings_language_download_error_general") ?>",
                        showConfirmButton: false
                    });
                });
            };
        });
    })(window.jQuery, window, document);
</script>
<style>
    .highlight {
        background-color: <?= $module::$COLORS['highlight'] ?> !important;
    }
    .glowing-border {
        box-shadow: 0 0 10px var(--bs-danger);
    }
</style>
<?php
include_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';