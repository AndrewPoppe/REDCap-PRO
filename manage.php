<?php


//$SQL = "UPDATE redcap_external_modules_log_parameters SET value = value+1 WHERE log_id = ? AND name = 'failed_attempts'";
//$module->query($SQL, [1413]);

/*$result = $module->queryLogs("select failed_attempts where log_id = 1413");
while($row = $result->fetch_assoc()) {
    var_dump($row);
}*/
echo date_create()->modify('+60 seconds')->format('U');



$role = SUPER_USER ? 3 : $module->getUserRole(USERID); // 3=admin/manager, 2=monitor, 1=user, 0=not found
if ($role > 0) {
    
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
    <meta charset='UTF-8'><title>".$module::$APPTITLE." - Manage</title>";
    require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
    $module->UiShowHeader("Manage");

    $rcpro_proj_id = $module->getProjectId($project_id);

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        try {
            $function = empty($_POST["toDisenroll"]) ? "reset" : "disenroll";
            if ($function === "reset") {
                $result = $module->sendPasswordResetEmail($_POST["toReset"]);
                $icon = "success";
                $msg = "Successfully reset password for participant.";
            } else {
                $result = $module->disenrollParticipant($_POST["toDisenroll"], $rcpro_proj_id);
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
    // TODO: seriously... project_id and rcpro_proj_id... figure something out
    $participantList = $module->getProjectParticipants($rcpro_proj_id);

?>
    <style>
        .wrapper { 
            display: inline-block; 
            padding: 20px; 
        }
        .manage-form {
            border-radius: 5px;
            border: 1px solid #cccccc;
            padding: 20px;
            box-shadow: 0px 0px 5px #eeeeee;
        }
        #RCPRO_Manage_Users tr.even {
            background-color: white !important;
        }
        #RCPRO_Manage_Users tr.odd {
            background-color: white !important;
        }
        table.dataTable tbody td {
            vertical-align: middle;
        }
        .dt-center {
            text-align: center;
        }
        button:hover {
            outline: none !important;
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
                    <table class="table" id="RCPRO_Manage_Users">
                        <caption>Study Participants</caption>
                        <thead>
                            <tr>
                                <th id="rcpro_username">Username</th>
                                <?php if ($role > 1) { ?>
                                    <th id="rcpro_fname" class="dt-center">First Name</th>
                                    <th id="rcpro_lname" class="dt-center">Last Name</th>
                                    <th id="rcpro_email" >Email</th>
                                <?php } ?>
                                <th id="rcpro_resetpw" class="dt-center">Reset Password</th>
                                <?php if ($role > 1) { ?>
                                    <th id="rcpro_disenroll" class="dt-center">Disenroll</th>
                                <?php } ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($participantList as $participant) { 
                                $username_clean = \REDCap::escapeHtml($participant["username"]);
                                $fname_clean    = \REDCap::escapeHtml($participant["fname"]);
                                $lname_clean    = \REDCap::escapeHtml($participant["lname"]);
                                $email_clean    = \REDCap::escapeHtml($participant["email"]);     
                                ?>
                                <tr>
                                    <td><?=$username_clean?></td>
                                    <?php if ($role > 1) { ?>
                                        <td class="dt-center"><?=$fname_clean?></td>
                                        <td class="dt-center"><?=$lname_clean?></td>
                                        <td><?=$email_clean?></td>
                                    <?php } ?>
                                    <td class="dt-center"><button type="button" class="btn btn-secondary btn-sm" onclick='(function(){
                                        $("#toReset").val("<?=$participant["id"]?>");
                                        $("#toDisenroll").val("");
                                        $("#manage-form").submit();
                                        })();'>Reset</button></td>
                                    <?php if ($role > 1) { ?>
                                        <td class="dt-center"><button type="button" class="btn btn-danger btn-sm" onclick='(function(){
                                            $("#toReset").val("");
                                            $("#toDisenroll").val("<?=$participant["id"]?>");
                                            $("#manage-form").submit();
                                            })();'>Disenroll</button></td>
                                    <?php } ?>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <input type="hidden" id="toReset" name="toReset">
                <input type="hidden" id="toDisenroll" name="toDisenroll">
            <?php } ?>        
        </form>
    </div>
    <script>
        $('#RCPRO_Manage_Users').DataTable();
    </script>
</body>




<?php
}
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';