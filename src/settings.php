<?php

namespace YaleREDCap\REDCapPRO;

$currentUser = new REDCapProUser($module);
$role = $currentUser->getUserRole($module->getProjectId());
if ($role < 3) {
    header("location:" . $module->getUrl("src/home.php"));
}

// Helpers
$Auth = new Auth($module);
$UI = new UI($module);
$ProjectSettings = new ProjectSettings($module);

require_once constant("APP_PATH_DOCROOT") . 'ProjectGeneral/header.php';
$UI->ShowHeader("Settings");
echo "<title>" . $module::$APPTITLE . " - Settings</title>
<link rel='stylesheet' type='text/css' href='" . $module->getUrl('src/css/rcpro.php') . "'/>";

// Get possible languages
$langs = $ProjectSettings->getLanguageFiles();

// Update settings if requested
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate token
    if (!$Auth->validate_csrf_token($_POST['token'])) {
        header("location:" . $module->getUrl("src/settings.php"));
        return;
    }

    // Log submission
    $module->logForm("Submitted Settings Form", $_POST);

    // Validate Settings
    $post_settings = $_POST;
    $new_settings = array();
    try {
        // Validate Language
        $new_settings["reserved-language-project"] = \REDCap::escapeHtml($post_settings["reserved-language-project"]);
        if (!in_array($new_settings["reserved-language-project"], array_keys($langs), true)) {
            $lang_err = "Invalid language selected";
            $any_err = true;
        }
        if ($new_settings["reserved-language-project"] === "English") {
            $new_settings["reserved-language-project"] = null;
        }

        // Validate Prevent Email Login
        $new_settings["prevent-email-login"] = $post_settings["prevent-email-login"] === "true";

        // Validate Primary Contact
        $new_settings["pc-name"] = \REDCap::escapeHtml($post_settings["pc-name"]);
        $new_settings["pc-email"] = \REDCap::escapeHtml($post_settings["pc-email"]);
        $new_settings["pc-phone"] = \REDCap::escapeHtml($post_settings["pc-phone"]);
        if ($new_settings["pc-email"] !== "" && !filter_var($new_settings["pc-email"], FILTER_VALIDATE_EMAIL)) {
            $email_err = "Invalid email format";
            $any_err = true;
        }


        if (!$any_err) {

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
    } catch (\Exception $e) {
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
$settings = $module->getProjectSettings();
$preventEmailLoginSystem = $module->getSystemSetting("prevent-email-login-system");

// set csrf token
$Auth->set_csrf_token();

?>

<div class="settingsContainer wrapper" style="display: none;">
    <h2>Settings</h2>
    <p>Project-level configuration</p>
    <div id="parent">
        <form class="rcpro-form" id="settings-form" action="<?= $module->getUrl("src/settings.php"); ?>" method="POST" enctype="multipart/form-data" target="_self">
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
                        <select class="form-select <?php echo (!empty($lang_err)) ? 'is-invalid' : ''; ?>" name="reserved-language-project" aria-label="language select">
                            <?php
                            foreach ($langs as $lang => $file) {
                                $selected_lang = $settings["reserved-language-project"];
                                $selected = ($lang === $selected_lang) || (!isset($selected_lang) && $lang === "English") ? "selected" : "";
                            ?>
                                <option value="<?= $lang ?>" <?= $selected ?>><?= $lang ?></option>
                            <?php
                            }
                            ?>
                        </select>
                        <span class="invalid-feedback"><?php echo $lang_err; ?></span>
                    </div>
                </div>
            </div>
            <br>
            <?php
            if (!$preventEmailLoginSystem) {
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
                            <input class="form-check-input <?php echo (!empty($prevent_email_login_err)) ? 'is-invalid' : ''; ?>" type="checkbox" id="prevent-email-login-check" <?= $checked ?> onclick="(function(){
                                $('#prevent-email-login').val($('#prevent-email-login-check')[0].checked);
                            })()">
                            <label class="form-check-label" style="vertical-align:middle;" for="prevent-email-login-check">Checking this will require that they login using their participant username only.</label>
                            <input type="text" name="prevent-email-login" id="prevent-email-login" value="<?= $checked === "checked" ? "true" : "false" ?>" hidden>
                            <span class="invalid-feedback"><?php echo $prevent_email_login_err; ?></span>
                        </div>
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
                        <input type="text" name="pc-name" class="form-control <?php echo (!empty($name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo \REDCap::escapeHtml($settings["pc-name"]); ?>">
                        <span class="invalid-feedback"><?php echo $name_err; ?></span>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="pc-email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo \REDCap::escapeHtml($settings["pc-email"]); ?>">
                        <span class="invalid-feedback"><?php echo $email_err; ?></span>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="pc-phone" class="form-control <?php echo (!empty($phone_err)) ? 'is-invalid' : ''; ?>" value="<?php echo \REDCap::escapeHtml($settings["pc-phone"]); ?>">
                        <span class="invalid-feedback"><?php echo $phone_err; ?></span>
                    </div>
                </div>
            </div>
            <br>
            <div class="form-group">
                <button type="button" class="btn btn-secondary" value="Cancel" onclick="(function() {
                    window.location.href = '<?= $module->getUrl("src/settings.php") ?>';
                    })()">Cancel</button>
                <button type="submit" id="rcpro-submit-button" class="btn btn-rcpro" value="Submit" disabled>Save Settings</button>
            </div>
            <input type="hidden" name="token" value="<?= $Auth->get_csrf_token(); ?>">
        </form>
    </div>
</div>
<script>
    (function($, window, document) {
        $(document).ready(function() {
            let form = document.querySelector('#settings-form');
            form.addEventListener('change', function() {
                $('#rcpro-submit-button').attr("disabled", null);
            });
        });
    })(window.jQuery, window, document);
</script>

<?php
include constant("APP_PATH_DOCROOT") . 'ProjectGeneral/footer.php';
