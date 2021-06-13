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
                    <table class="table" id="RCPRO_Manage_Users">
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
    
    ?>
                        <tr>
                            <td><?=$user->getUsername()?></td>
                            <td><?=$module->getUserFullname($user->getUsername())?></td>
                            <td><?=$user->getEmail()?></td>
                            <td>TEST</td>
                        </tr>
<?php } ?>
                    </table>
                </div>
                <input type="hidden" id="toReset" name="toReset">
                <input type="hidden" id="toDisenroll" name="toDisenroll">        
        </form>
            
<?php } ?>
    </div>
</body>




<?php
}
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';