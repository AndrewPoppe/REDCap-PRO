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

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        try {
            $function = empty($_POST["toDisenroll"]) ? "reset" : "disenroll";
            if ($function === "reset") {
                $result = $module->sendPasswordResetEmail($_POST["toReset"]);
                $icon = "success";
                $msg = "Successfully reset password for participant.";
            } else {
                $result = $module->disenrollParticipant($_POST["toDisenroll"], $proj_id);
                if (!$result) {
                    $icon = "error";
                    $msg = "Trouble disenrolling participant.";
                } else {
                    $icon = "success";
                    $msg = "Successfully disenrolled participant from project.";
                }
            }
            $title = $msg;
        }
        catch (\Exception $e) {
            $icon = "error";
            $title = "Failed to ${function} participant.";
        }
    }

    // Get list of users
    $project = $module->getProject();
    $userList = $project->getUsers();


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
            background-color: #f0f0f0 !important;
        }
        #RCPRO_Manage_Staff tr.odd {
            background-color: white !important;
        }
    </style>
</head>
<body>

<?php if ($_SERVER["REQUEST_METHOD"] == "POST") { ?>
    <script>
        Swal.fire({icon: "<?=$icon?>", title:"<?=$title?>"});
    </script>
<?php } ?>

    <div class="manageContainer wrapper">
        <h2>Manage Study Staff</h2>
        <p>Set staff permissions to REDCapPRO</p>
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
                            <td><select class="role_select" id="role_select_<?=$username?>" orig_value="<?=$role?>">
                                <option value=0 <?=$role === 0 ? "selected" : "";?>>No Access</option>
                                <option value=1 <?=$role === 1 ? "selected" : "";?>>Normal User</option>
                                <option value=2 <?=$role === 2 ? "selected" : "";?>>Monitor</option>
                                <option value=3 <?=$role === 3 ? "selected" : "";?>>Manager</option>
                            </select></td>
                        </tr>
<?php } ?>
                    </table>
                </div>        
        </form>
        <button class="btn btn-primary role_select_button" id="role_select_submit" type="submit" disabled>Save Changes</button>
        <button class="btn btn-secondary role_select_button" id="role_select_cancel" disabled>Cancel</button>    
<?php } ?>
    </div>
    <script>
        (function() {
            function checkRoles() {
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
            $('#RCPRO_Manage_Staff').DataTable();
            $('.role_select').on("change", (evt) => {
                checkRoles();
                console.log(evt.target.value);
            });
        })();
    </script>
</body>


<?php
}
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';