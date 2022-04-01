<?php

$role = SUPER_USER ? 3 : $module->getUserRole(USERID); // 3=admin/manager, 2=user, 1=monitor, 0=not found
if ($role < 3) {
    header("location:" . $module->getUrl("src/home.php"));
}

require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module::$UI->ShowHeader("Users");
echo "<title>" . $module::$APPTITLE . " - Staff</title>
<link rel='stylesheet' type='text/css' href='" . $module->getUrl('src/css/rcpro.php') . "'/>";

$proj_id = $module::$PROJECT->getProjectIdFromPID($project_id);

// Get list of users
$project = $module->getProject();
$userList = $project->getUsers();

// Check for errors
if (isset($_GET["error"])) {
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

// Update roles if requested
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate token
    if (!$module::$AUTH->validate_csrf_token($_POST['token'])) {
        header("location:" . $module->getUrl("src/manage-users.php?error"));
        return;
    }

    // Log submission
    $module->logForm("Submitted Manage Staff Form", $_POST);

    try {
        foreach ($userList as $user) {
            $username = $user->getUsername();
            parse_str($username, $username_temp);
            $username_clean = array_key_first($username_temp);
            $newRole = strval($_POST["role_select_${username_clean}"]);
            $oldRole = strval($module->getUserRole($username));
            if (isset($newRole) && $newRole !== '' && $newRole !== $oldRole) {
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

?>

<div class="manageContainer wrapper" style="display: none;">
    <h2>Manage Study Staff</h2>
    <p>Set <span id="infotext" onclick="(function() {
            Swal.fire({
                icon: 'info',
                iconColor: 'black',
                title: 'Staff Roles',
                confirmButtonText: 'Got it!',
                confirmButtonColor: '<?= $module::$COLORS["secondary"] ?>',
                html: 'Staff may have one of the following roles:<br><br>'+
                    '<div style=\'text-align:left;\'>'+
                        '<ul>'+
                            '<li><strong>Manager:</strong> Highest permissions. Has the ability to grant/revoke staff access. You are a manager if you are reading this.</li>'+
                            '<li><strong>User:</strong> Able to view participant identifying information, register participants, enroll/disenroll participants in the study, and initiate password reset.</li>'+
                            '<li><strong>Monitor:</strong> Basic access. Can only view usernames and initiate password resets.</li>'+
                        '</ul><br>'+
                        '</div>'
            })})();">staff permissions</span> to REDCapPRO</p>
    <div id="loading-container" class="loader-container">
        <div id="loading" class="loader"></div>
    </div>
    <div id="parent" class="dataTableParentHidden">
        <form class="rcpro-form" id="manage-users-form" action="<?= $module->getUrl("src/manage-users.php"); ?>" method="POST" enctype="multipart/form-data" target="_self">
            <?php if (count($userList) === 0) { ?>
                <div>
                    <p>No users have access to this project.</p>
                </div>
            <?php } else { ?>
                <div class="form-group">
                    <table class="table rcpro-datatable" id="RCPRO_Manage_Staff">
                        <caption></caption>
                        <thead>
                            <tr>
                                <th id="rcpro_username">Username</th>
                                <th id="rcpro_name">Name</th>
                                <th id="rcpro_email">Email</th>
                                <th id="rcpro_role">User Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($userList as $user) {
                                $username       = $user->getUsername();
                                $username_clean = \REDCap::escapeHtml($username);
                                $fullname_clean = \REDCap::escapeHtml($module->getUserFullname($username));
                                $email_clean    = \REDCap::escapeHtml($user->getEmail());
                                $role           = $module->getUserRole($username);
                            ?>
                                <tr>
                                    <td><?= $username_clean ?></td>
                                    <td><?= $fullname_clean ?></td>
                                    <td><?= $email_clean ?></td>
                                    <td data-order="<?= $role ?>"><select class="role_select" name="role_select_<?= $username_clean ?>" id="role_select_<?= $username_clean ?>" orig_value="<?= $role ?>" form="manage-users-form">
                                            <option value=0 <?= $role === 0 ? "selected" : ""; ?>>No Access</option>
                                            <option value=1 <?= $role === 1 ? "selected" : ""; ?>>Monitor</option>
                                            <option value=2 <?= $role === 2 ? "selected" : ""; ?>>Normal User</option>
                                            <option value=3 <?= $role === 3 ? "selected" : ""; ?>>Manager</option>
                                        </select></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                    <button class="btn btn-rcpro rcpro-form-button role_select_button" id="role_select_submit" type="submit" disabled>Save Changes</button>
                    <button class="btn btn-secondary rcpro-form-button role_select_button" id="role_select_reset" disabled>Reset</button>
                </div>
            <?php } ?>
            <input type="hidden" name="token" value="<?= $module::$AUTH->set_csrf_token(); ?>">
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
