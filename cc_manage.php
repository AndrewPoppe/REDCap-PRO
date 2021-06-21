<?php


?>
<!DOCTYPE html>
    <?php
    if (!SUPER_USER) {
        return;
    }
    require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';
    $module->UiShowControlCenterHeader("Manage");
    
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        var_dump($_POST);
        try {
            $function = empty($_POST["toDisenroll"]) ? "reset" : "disenroll";
            if ($function === "reset") {
                /*$result = $module->sendPasswordResetEmail($_POST["toReset"]);
                $icon = "success";
                $msg = "Successfully reset password for participant.";
                */
            } else {
                list($user, $project) = $_POST["toDisenroll"];
                /*$result = $module->disenrollParticipant($_POST["toDisenroll"], $proj_id);
                if (!$result) {
                    $icon = "error";
                    $msg = "Trouble disenrolling participant.";
                } else {
                    $icon = "success";
                    $msg = "Successfully disenrolled participant from project.";
                }*/
                echo $user, $project;
            }
            $title = $msg;
        }
        catch (\Exception $e) {
            $icon = "error";
            $title = "Failed to ${function} participant.";
        }
    }

    // Get array of participants
    $participants = $module->getAllParticipants();
    
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

    <?php if ($_SERVER["REQUEST_METHOD"] == "POST") { ?>
        <script>
            Swal.fire({icon: "<?=$icon?>", title:"<?=$title?>"});
        </script>
    <?php } ?>

    <div class="manageContainer wrapper">
        <h2>Manage Study Participants</h2>
        <p>Reset passwords, disenroll from study, etc.</p>
        <form class="manage-form" id="manage-form" action="<?= $module->getUrl("cc_manage.php"); ?>" method="POST" enctype="multipart/form-data" target="_self">
<?php if (count($participants) === 0 || empty($participants)) { ?>
                <div>
                    <p>No participants have been enrolled in this study</p>
                </div>
<?php } else { ?>
                <div class="form-group">
                    <table class="table" id="RCPRO_Manage_Users">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th class="dt-center">First Name</th>
                                <th class="dt-center">Last Name</th>
                                <th>Email</th>
                                <th class="dt-center">Reset Password</th>
                            </tr>
                        </thead>
                        <tbody>
<?php foreach ($participants as $participant) { ?>
                            <tr>
                                <td><?=$participant["username"]?></td>
                                <td class="dt-center"><?=$participant["fname"]?></td>
                                <td class="dt-center"><?=$participant["lname"]?></td>
                                <td><?=$participant["email"]?></td>
                                <td class="dt-center"><button type="button" class="btn btn-secondary btn-sm" onclick='(function(){
                                    $("#toReset").val("<?=$participant["id"]?>");
                                    $("#toDisenroll").val("");
                                    $("#manage-form").submit();
                                    })();'>Reset</button></td>
                            </tr>
<?php } ?>
                        </tbody>
                    </table>
                </div>
                <input type="hidden" id="toReset" name="toReset">
                <input type="hidden" id="toDisenroll" name="toDisenroll">        
        </form>
            
<?php } ?>
    </div>
    <script>
        $('#RCPRO_Manage_Users').DataTable();
    </script>

    <?php
    require_once APP_PATH_DOCROOT . 'ControlCenter/footer.php';
    ?>
</body>
</html>