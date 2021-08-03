<?php


function createProjectsCell(array $projects) {
    global $module;
    $result = "<td  class='dt-center'>";
    foreach ($projects as $project) {
        $pid = trim($project["redcap_pid"]);
        $url = $module->getUrl("manage.php?pid=${pid}");
        $result .= "<div><a class='rcpro_project_link' href='${url}'>PID ${pid}</a></div>";
    }
    $result .= "</td>";
    return $result;
}

?>
<!DOCTYPE html>
    <?php
    if (!SUPER_USER) {
        return;
    }
    require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';
    $module->UiShowControlCenterHeader("Manage");
    
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        try {
            $function = NULL;
            // SEND A PASSWORD RESET EMAIL
            if (!empty($_POST["toReset"])) {
                $function = "send password reset email";
                $result = $module->sendPasswordResetEmail($_POST["toReset"]);
                if (!$result) {
                    $icon = "error";
                    $title = "Trouble sending password reset email.";
                } else {
                    $icon = "success";
                    $title = "Successfully reset password for participant.";
                }

            // CHANGE THE PARTICIPANT'S EMAIL ADDRESS
            } else if (!empty($_POST["toChangeEmail"])) {
                $function = "change participant's email address";
                $newEmail = $_POST["newEmail"];
                if ($module->checkEmailExists($newEmail)) {
                    $icon = "error";
                    $title = "The provided email address is already associated with a REDCapPRO account.";
                } else {
                    $result = $module->changeEmailAddress(intval($_POST["toChangeEmail"]), $newEmail);
                    if (!$result) {
                        $icon = "error";
                        $title = "Trouble changing participant's email address.";
                    } else {
                        $icon = "success";
                        $title = "Successfully changed participant's email address.";
                    }
                }
            }
        }
        catch (\Exception $e) {
            $icon = "error";
            $title = "Failed to ${function}.";
            $module->logError("Error attempting to ${function}", $e);
        }
    }

    // Get array of participants
    $participants = $module->getAllParticipants();
    
    ?>
    <script src="<?=$module->getUrl("lib/sweetalert/sweetalert2.all.min.js");?>"></script>
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
        .rcpro_project_link {
            color: #000090 !important;
            font-weight: bold !important;
        }
        .rcpro_project_link:hover {
            color: #900000 !important;
            font-weight: bold !important;
            cursor: pointer !important;
        }
    </style>

    <?php if ($_SERVER["REQUEST_METHOD"] == "POST") { ?>
        <script>
            Swal.fire({icon: "<?=$icon?>", title:"<?=$title?>", confirmButtonColor: "#900000"});
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
                        <caption>Manage REDCapPRO Participants</caption>
                        <thead>
                            <tr>
                                <th id="uid">User_ID</th>
                                <th id="uname" class="dt-center">Username</th>
                                <th id="fname" class="dt-center">First Name</th>
                                <th id="lname" class="dt-center">Last Name</th>
                                <th id="email">Email</th>
                                <th id="projects" class="dt-center">Enrolled Projects</th>
                                <th id="resetpwbutton" class="dt-center">Reset Password</th>
                                <th id="rcpro_changeemail" class="dt-center">Change Email Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($participants as $participant) { 
                                $username_clean       = \REDCap::escapeHtml($participant["rcpro_username"]);
                                $fname_clean          = \REDCap::escapeHtml($participant["fname"]);
                                $lname_clean          = \REDCap::escapeHtml($participant["lname"]);
                                $email_clean          = \REDCap::escapeHtml($participant["email"]);
                                $rcpro_participant_id = intval($participant["log_id"]);
                                $projects_array       = $module->getParticipantProjects($rcpro_participant_id);
                            ?>
                            <tr>
                                <td><?=$participant["log_id"]?></td>
                                <td class="dt-center"><?=$username_clean?></td>
                                <td class="dt-center"><?=$fname_clean?></td>
                                <td class="dt-center"><?=$lname_clean?></td>
                                <td><?=$email_clean?></td>
                                <?=createProjectsCell($projects_array);?>
                                <td class="dt-center"><button type="button" class="btn btn-secondary btn-sm" onclick='(function(){
                                    $("#toReset").val("<?=$participant["log_id"]?>");
                                    $("#toDisenroll").val("");
                                    $("#toChangeEmail").val("");
                                    $("#manage-form").submit();
                                    })();'>Reset</button>
                                </td>
                                <td class="dt-center"><button type="button" class="btn btn-secondary btn-sm" onclick='(function(){
                                    Swal.fire({
                                        title: "Enter the new email address for <?="${fname_clean} ${lname_clean}"?>",
                                            input: "email",
                                            inputPlaceholder: "<?=$email_clean?>",
                                            confirmButtonText: "Change Email",
                                            showCancelButton: true,
                                            confirmButtonColor: "#900000"
                                        }).then((result) => {
                                            if (result.isConfirmed) {
                                                $("#toReset").val("");
                                                $("#toDisenroll").val("");
                                                $("#toChangeEmail").val("<?=$participant["log_id"]?>");
                                                $("#newEmail").val(result.value); 
                                                $("#manage-form").submit();
                                            }
                                        });
                                    })();'>Change</button>
                                </td>
                            </tr>
<?php } ?>
                        </tbody>
                    </table>
                </div>
                <input type="hidden" id="toReset" name="toReset">
                <input type="hidden" id="toChangeEmail" name="toChangeEmail">
                <input type="hidden" id="newEmail" name="newEmail">
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