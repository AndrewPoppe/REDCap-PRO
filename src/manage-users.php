<?php

namespace YaleREDCap\REDCapPRO;

$currentUser = new REDCapProUser($module, USERID);
$role = $currentUser->getUserRole($module->getProjectId());
if ($role < 3) {
    header("location:" . $module->getUrl("src/home.php"));
}

// Helpers
$Auth = new Auth($module::$APPTITLE);
$UI = new UI($module);

require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$UI->ShowHeader("Users");
echo "<title>" . $module::$APPTITLE . " - Staff</title>
<link rel='stylesheet' type='text/css' href='" . $module->getUrl('src/css/rcpro.php') . "'/>";
?>


<?php

// Get list of users
$project = new Project($module, ["redcap_pid" => $module->getProjectId()]);
$userList = $project->getUsers();


// Update roles if requested
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate token
    if (!$Auth->validate_csrf_token($_POST['token'])) {
        header("location:" . $module->getUrl("src/manage-users.php"));
        return;
    }

    // Log submission
    $module->logForm("Submitted Manage Staff Form", $_POST);

    try {
        foreach ($userList as $user) {
            $username = $user->username;
            $newRole = strval($_POST["role_select_${username}"]);
            $oldRole = strval($user->getUserRole($project->redcap_pid));
            if (isset($newRole) && $newRole !== $oldRole) {
                $currentUser->changeUserRole($username, $project->redcap_pid, $oldRole, $newRole);
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
$Auth->set_csrf_token();

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
                                $username       = $user->username;
                                $username_clean = \REDCap::escapeHtml($username);
                                $fullname_clean = \REDCap::escapeHtml($user->getUserFullname());
                                $email_clean    = \REDCap::escapeHtml($user->user->getEmail());
                                $role           = $user->getUserRole($project->redcap_pid);
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
            <input type="hidden" name="token" value="<?= $Auth->get_csrf_token(); ?>">
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
