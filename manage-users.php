<?php
$role = $module->getUserRole(USERID); // 3=admin/manager, 2=monitor, 1=user, 0=not found
if (SUPER_USER) {
    $role = 3;
}
if ($role >= 3) {
    
    echo "<title>".$module::$APPTITLE." - Staff</title>";
    require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
    $module->UiShowHeader("Users");

    $proj_id = $module->getProjectId($project_id);

    // Get list of users
    $project = $module->getProject();
    $userList = $project->getUsers();


    // Update roles if requested
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
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
                    Swal.fire({icon: "success", title:"Roles successfully changed"});
                </script>
            <?php
        }
        catch (\Exception $e) {
            ?>
                <script>
                    Swal.fire({icon: "error", title:"Error", text:"<?=$e->getMessage();?>"});
                </script>
            <?php
        }
    }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        .wrapper { 
            display: inline-block; 
            padding: 20px; 
        }
        .manage-users-form {
            border-radius: 5px;
            border: 1px solid #cccccc;
            padding: 20px;
            box-shadow: 0px 0px 5px #eeeeee;
        }
        #RCPRO_Manage_Staff tr.even {
            background-color: white !important;
        }
        #RCPRO_Manage_Staff tr.odd {
            background-color: white !important;
        }
        table.dataTable tbody td {
            vertical-align: middle;
        }
        #infotext {
            cursor: pointer;
            text-decoration: underline;
            font-weight: bold;
            color: #17a2b8;
        }
        #infotext:hover {
            text-shadow: 0px 0px 5px #17a2b8;
        }
        button:hover {
            outline: none !important;
        }
    </style>
</head>
<body>

    <div class="manageContainer wrapper">
        <h2>Manage Study Staff</h2>
        <p>Set <span id="infotext" onclick="(function() {
                    Swal.fire({
                        icon: 'info',
                        iconColor: '#17a2b8',
                        title: 'Staff Roles',
                        confirmButtonText: 'Got it!',
                        confirmButtonColor: '#17a2b8',
                        html: `Staff may have one of the following roles:<br><br>
                            <div style='text-align:left;'>
                                <ul>
                                    <li><strong>Manager:</strong> Highest permissions. Has the ability to grant/revoke staff access. You are a manager if you are reading this.</li>
                                    <li><strong>Monitor:</strong> Able to view participant identifying information, register participants, enroll/disenroll participants in the study, and initiate password reset.</li>
                                    <li><strong>User:</strong> Basic access. Can only view usernames and initiate password resets.</li>
                                </ul><br>
                                </div>`
                    })})();">staff permissions</span> to REDCapPRO</p>
        <form class="manage-users-form" id="manage-users-form" action="<?= $module->getUrl("manage-users.php"); ?>" method="POST" enctype="multipart/form-data" target="_self">
<?php if (count($userList) === 0) { ?>
                <div>
                    <p>No users have access to this project.</p>
                </div>
<?php } else { ?>
                <div class="form-group">
                    <table class="table" id="RCPRO_Manage_Staff">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>User Role</th>
                            </tr>
                        </thead>
                        <tbody>
<?php foreach ($userList as $user) { 
    $username = $user->getUsername();
    $role = $module->getUserRole($username);
    ?>
                        <tr>
                            <td><?=$username?></td>
                            <td><?=$module->getUserFullname($username)?></td>
                            <td><?=$user->getEmail()?></td>
                            <td data-order="<?=$role?>"><select class="role_select" name="role_select_<?=$username?>" id="role_select_<?=$username?>" orig_value="<?=$role?>" form="manage-users-form">
                                <option value=0 <?=$role === 0 ? "selected" : "";?>>No Access</option>
                                <option value=1 <?=$role === 1 ? "selected" : "";?>>Normal User</option>
                                <option value=2 <?=$role === 2 ? "selected" : "";?>>Monitor</option>
                                <option value=3 <?=$role === 3 ? "selected" : "";?>>Manager</option>
                            </select></td>
                        </tr>
<?php } ?>
                    </table>
                </div>
            <button class="btn btn-primary role_select_button" id="role_select_submit" type="submit" disabled>Save Changes</button>
            <button class="btn btn-secondary role_select_button" id="role_select_reset" disabled>Reset</button>
        </form>    
<?php } ?>
    </div>
    <script>
        (function() {
            function checkRoleChanges() {
                let changed = false;
                $('.role_select').each((i, el) => {
                    let val = $(el).val();
                    let orig_val = $(el).attr('orig_value');
                    console.log(i,val,orig_val);
                    if (val !== orig_val) {
                        changed = true;
                    }
                });
                return changed;
            }

            $('#role_select_reset').on('click', (evt) => {
                evt.preventDefault();
                $('.role_select').each((i, el) => {
                    $(el).val($(el).attr('orig_value'));
                    $('#role_select_submit').attr("disabled", true);
                    $('#role_select_reset').attr("disabled", true);
                });
            });

            $('#RCPRO_Manage_Staff').DataTable();
            $('.role_select').on("change", (evt) => {
                let changed = checkRoleChanges();
                if (changed) {
                    $('#role_select_submit').removeAttr("disabled");
                    $('#role_select_reset').removeAttr("disabled");
                } else {
                    $('#role_select_submit').attr("disabled", true);
                    $('#role_select_reset').attr("disabled", true);
                }
                console.log(evt.target.value);
            });
        })();
    </script>
</body>


<?php
}
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';