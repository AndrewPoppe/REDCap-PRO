<?php
$role = $module->getUserRole(USERID); // 3=admin/manager, 2=monitor, 1=user, 0=not found
if (SUPER_USER) {
    $role = 3;
}
if ($role > 0) {
    
    echo "<title>".$module::$APPTITLE." - Menu</title>";
    require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
    $module->UiShowHeader("Manage");

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

    // Get list of participants
    // TODO: seriously... project_id and proj_id... figure something out
    $participantList = $module->getProjectParticipants($proj_id);


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>REDCap PRO - Manage</title>
    <style>
        .wrapper { 
            width: 720px; 
            padding: 20px; 
        }
        .manage-form {
            width: 720px;
            border-radius: 5px;
            border: 1px solid #cccccc;
            padding: 20px;
            box-shadow: 0px 0px 5px #eeeeee;
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
        <h2>Manage Study Participants</h2>
        <p>Reset passwords, disenroll from study, etc.</p>
        <form class="manage-form" id="manage-form" action="<?= $module->getUrl("manage.php"); ?>" method="POST" enctype="multipart/form-data" target="_self">
<?php if (count($participantList) === 0) { ?>
                <div>
                    <p>No participants have been enrolled in this study</p>
                </div>
<?php } else { ?>
                <div class="form-group">
                    <table class="table">
                    <!--<table class="dataTable no-footer" role="grid">-->
                        <tr>
                            <th>Username</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Email</th>
                            <th>Reset Password</th>
                            <th>Disenroll</th>
                        </tr>
<?php foreach ($participantList as $participant) { ?>
                        <tr>
                            <td><?=$participant["username"]?></td>
                            <td><?=$participant["fname"]?></td>
                            <td><?=$participant["lname"]?></td>
                            <td><?=$participant["email"]?></td>
                            <td><button type="button" class="btn btn-primary" onclick='(function(){
                                $("#toReset").val("<?=$participant["id"]?>");
                                $("#toDisenroll").val("");
                                $("#manage-form").submit();
                                })();'>Reset</button></td>
                            <td><button type="button" class="btn btn-secondary" onclick='(function(){
                                $("#toReset").val("");
                                $("#toDisenroll").val("<?=$participant["id"]?>");
                                $("#manage-form").submit();
                                })();'>Disenroll</button></td>
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