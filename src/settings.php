<?php

$role = SUPER_USER ? 3 : $module->getUserRole(USERID); // 3=admin/manager, 2=user, 1=monitor, 0=not found
if ($role < 3) {
    header("location:" . $module->getUrl("src/home.php"));
}

require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module::$UI->ShowHeader("Settings");
echo "<title>" . $module::$APPTITLE . " - Settings</title>
<link rel='stylesheet' href='" . $module->getUrl("lib/bootstrap/css/bootstrap.min.css") . "'>
<link rel='stylesheet' type='text/css' href='" . $module->getUrl('src/css/rcpro.php') . "'/>
<script async src='" . $module->getUrl("lib/bootstrap/js/bootstrap.bundle.min.js") . "'></script>";

// Get current project settings
$settings = $module::$SETTINGS->getProjectSettings($module->getProjectId());
$langs = $module::$SETTINGS->getLanguageFiles();
$preventEmailLoginSystem = $module->getSystemSetting("prevent-email-login-system");
var_dump($settings);
var_dump($_POST);

// Update settings if requested
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate token
    if (!$module::$AUTH->validate_csrf_token($_POST['token'])) {
        header("location:" . $module->getUrl("src/settings.php"));
        return;
    }

    try {
        foreach ($userList as $user) {
            $username = $user->getUsername();
            $newRole = strval($_POST["role_select_${username}"]);
            $oldRole = strval($module->getUserRole($username));
            if (isset($newRole) && $newRole !== $oldRole) {
                $module->changeUserRole($username, $oldRole, $newRole);
            }
        }
?>
        <script>
            Swal.fire({
                icon: "success",
                title: "Roles successfully changed",
                showConfirmButton: false
            });
        </script>
    <?php
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

// set csrf token
$module::$AUTH->set_csrf_token();

?>

<div class="settingsContainer wrapper" style="display: none;">
    <h2>Settings</h2>
    <p>Project-level configuration</p>
    <div id="parent">
        <form class="rcpro-form" id="settings-form" action="<?= $module->getUrl("src/settings.php"); ?>" method="POST" enctype="multipart/form-data" target="_self">
            <div class="card">
                <div class="card-header"><strong>Language</strong></div>
                <div class="card-body">
                    <div class="card-title">Set the language that participant-facing text will be displayed in.</div>
                    <div class="form-group">
                        <select class="form-select" name="language" aria-label="language select">
                            <?php
                            foreach ($langs as $lang => $file) {
                                $selected_lang = $settings["language"];
                                $selected = ($lang === $selected_lang) || (!isset($selected_lang) && $lang === "English") ? "selected" : "";
                            ?>
                                <option value="<?= $lang ?>" <?= $selected ?>><?= $lang ?></option>
                            <?php
                            }
                            ?>
                        </select>
                        <span class="invalid-feedback"><?php echo $email_err; ?></span>
                    </div>
                </div>
            </div>
            <br>
            <?php
            if (!$preventEmailLoginSystem) {
                $checked = $settings["prevent-email-login"] ? "checked" : "";
            ?>
                <div class="card">
                    <div class="card-header"><strong>Prevent Email Login</strong></div>
                    <div class="card-body">
                        <div class="card-title">
                            Should participants be prevented from using their email address to log in to the system.<br>

                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="prevent-email-login" id="prevent-email-login" <?= $checked ?>>
                            <label class="form-check-label" for="prevent-email-login">Checking this will require that they login using their participant username only.</label>
                        </div>
                    </div>
                </div>
                <br>
            <?php } ?>
            <div class="card">
                <div class="card-header"><strong>Primary Contact Person</strong></div>
                <div class="card-body">
                    <div class="card-title">This is who participants should contact when they have questions.</div>
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="pc-name" class="form-control <?php echo (!empty($name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $name_clean; ?>">
                        <span class="invalid-feedback"><?php echo $name_err; ?></span>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="pc-email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email_clean; ?>">
                        <span class="invalid-feedback"><?php echo $email_err; ?></span>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="pc-phone" class="form-control <?php echo (!empty($phone_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $phone_clean; ?>">
                        <span class="invalid-feedback"><?php echo $phone_err; ?></span>
                    </div>
                </div>
            </div>
            <br>
            <div class="form-group">
                <button type="cancel" class="btn btn-secondary" value="Cancel">Cancel</button>
                <button type="submit" class="btn btn-rcpro" value="Submit">Save Settings</button>
            </div>
            <input type="hidden" name="token" value="<?= $module::$AUTH->get_csrf_token(); ?>">
        </form>
    </div>
</div>
<script>
    (function($, window, document) {
        $(document).ready(function() {
            function checkRoleChanges() {
                let changed = false;
                $('.role_select').each(function(i, el) {
                    let val = $(el).val();
                    let orig_val = $(el).attr('orig_value');
                    if (val !== orig_val) {
                        changed = true;
                    }
                });
                return changed;
            }

            $('#role_select_reset').on('click', function(evt) {
                evt.preventDefault();
                $('.role_select').each(function(i, el) {
                    $(el).val($(el).attr('orig_value'));
                    $('#role_select_submit').attr("disabled", true);
                    $('#role_select_reset').attr("disabled", true);
                });
            });

            $('#RCPRO_Manage_Staff').DataTable({
                stateSave: true,
                stateSaveCallback: function(settings, data) {
                    localStorage.setItem('DataTables_staff_' + settings.sInstance, JSON.stringify(data))
                },
                stateLoadCallback: function(settings) {
                    return JSON.parse(localStorage.getItem('DataTables_staff_' + settings.sInstance))
                }
            });
            $('.role_select').on("change", function(evt) {
                let changed = checkRoleChanges();
                if (changed) {
                    $('#role_select_submit').removeAttr("disabled");
                    $('#role_select_reset').removeAttr("disabled");
                } else {
                    $('#role_select_submit').attr("disabled", true);
                    $('#role_select_reset').attr("disabled", true);
                }
            });
            $('#parent').removeClass('dataTableParentHidden');
            $('.wrapper').show();
            $('#loading-container').hide();
        });
    })(window.jQuery, window, document);
</script>

<?php
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
