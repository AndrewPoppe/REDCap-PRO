<?php

$role = SUPER_USER ? 3 : $module->getUserRole(USERID); // 3=admin/manager, 2=user, 1=monitor, 0=not found
if ($role > 0) {

    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
    <meta charset='UTF-8'><title>" . $module::$APPTITLE . " - Manage</title>";
    require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
    $module->UiShowHeader("Manage");

    $rcpro_project_id = $module->getProjectIdFromPID($project_id);

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        try {
            $function = NULL;
            $showConfirm = false;
            // DISENROLL THE PARTICIPANT FROM THE STUDY
            if (!empty($_POST["toDisenroll"])) {
                $function = "disenroll participant";
                if ($role <= 1) {
                    $icon = "error";
                    $title = "You do not have the required role to do that.";
                } else {
                    $result = $module->disenrollParticipant($_POST["toDisenroll"], $rcpro_project_id);
                    if (!$result) {
                        $icon = "error";
                        $title = "Trouble disenrolling participant.";
                    } else {
                        $icon = "success";
                        $title = "Successfully disenrolled participant from project.";
                    }
                }

                // SEND A PASSWORD RESET EMAIL
            } else if (!empty($_POST["toReset"])) {
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
                if ($role <= 2) {
                    $icon = "error";
                    $title = "You do not have the required role to do that.";
                } else {
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
        } catch (\Exception $e) {
            $icon = "error";
            $title = "Failed to ${function}.";
            $module->logError("Error attempting to ${function}", $e);
        }
    }

    // Get list of participants
    $participantList = $module->getProjectParticipants($rcpro_project_id);

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
    <script src="<?= $module->getUrl("lib/sweetalert/sweetalert2.all.min.js"); ?>"></script>
    </head>

    <body>

        <?php if ($_SERVER["REQUEST_METHOD"] == "POST") { ?>
            <script>
                Swal.fire({
                    icon: "<?= $icon ?>",
                    title: "<?= $title ?>",
                    showConfirmButton: "<?= $showConfirm ?>"
                });
            </script>
        <?php } ?>

        <div class="manageContainer wrapper">
            <h2>Manage Study Participants</h2>
            <p>Reset passwords, disenroll from study, etc.</p>
            <form class="manage-form" id="manage-form" action="<?= $module->getUrl("src/manage.php"); ?>" method="POST" enctype="multipart/form-data" target="_self">
                <?php if (count($participantList) === 0) { ?>
                    <div>
                        <p>No participants have been enrolled in this study</p>
                    </div>
                <?php } else { ?>
                    <div class="form-group">
                        <table class="table" id="RCPRO_Manage_Users" style="width:100%;">
                            <caption>Study Participants</caption>
                            <thead>
                                <tr>
                                    <th id="rcpro_username">Username</th>
                                    <?php if ($role > 1) { ?>
                                        <th id="rcpro_fname" class="dt-center">First Name</th>
                                        <th id="rcpro_lname" class="dt-center">Last Name</th>
                                        <th id="rcpro_email">Email</th>
                                    <?php } ?>
                                    <th id="rcpro_resetpw" class="dt-center">Reset Password</th>
                                    <?php if ($role > 2) { ?>
                                        <th id="rcpro_changeemail" class="dt-center">Change Email Address</th>
                                    <?php } ?>
                                    <?php if ($role > 1) { ?>
                                        <th id="rcpro_disenroll" class="dt-center">Disenroll</th>
                                    <?php } ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($participantList as $participant) {
                                    $username_clean = \REDCap::escapeHtml($participant["rcpro_username"]);
                                    $fname_clean    = \REDCap::escapeHtml($participant["fname"]);
                                    $lname_clean    = \REDCap::escapeHtml($participant["lname"]);
                                    $email_clean    = \REDCap::escapeHtml($participant["email"]);
                                ?>
                                    <tr>
                                        <td><?= $username_clean ?></td>
                                        <?php if ($role > 1) { ?>
                                            <td class="dt-center"><?= $fname_clean ?></td>
                                            <td class="dt-center"><?= $lname_clean ?></td>
                                            <td><?= $email_clean ?></td>
                                        <?php } ?>
                                        <td class="dt-center"><button type="button" class="btn btn-secondary btn-sm" onclick='(function(){
                                        $("#toReset").val("<?= $participant["log_id"] ?>");
                                        $("#toDisenroll").val("");
                                        $("#toChangeEmail").val("");
                                        $("#manage-form").submit();
                                        })();'>Reset</button></td>
                                        <?php if ($role > 2) { ?>
                                            <td class="dt-center"><button type="button" class="btn btn-secondary btn-sm" onclick='(function(){
                                            Swal.fire({
                                                title: "Enter the new email address for <?= "${fname_clean} ${lname_clean}" ?>",
                                                input: "email",
                                                inputPlaceholder: "<?= $email_clean ?>",
                                                confirmButtonText: "Change Email",
                                                showCancelButton: true,
                                                confirmButtonColor: "#900000"
                                            }).then((result) => {
                                                if (result.isConfirmed) {
                                                    $("#toReset").val("");
                                                    $("#toDisenroll").val("");
                                                    $("#toChangeEmail").val("<?= $participant["log_id"] ?>");
                                                    $("#newEmail").val(result.value); 
                                                    $("#manage-form").submit();
                                                }
                                            });
                                            })();'>Change Email</button>
                                            </td>
                                        <?php } ?>
                                        <?php if ($role > 1) { ?>
                                            <td class="dt-center"><button type="button" class="btn btn-danger btn-sm" onclick='(function(){
                                            
                                            Swal.fire({
                                                icon: "warning",
                                                iconColor: "#900000",
                                                title: "Are you sure you want to remove <?= "${fname_clean} ${lname_clean}" ?> from this project?",
                                                confirmButtonText: "Remove Participant",
                                                allowEnterKey: false,
                                                showCancelButton: true,
                                                confirmButtonColor: "#900000"
                                            }).then((result) => {
                                                if (result.isConfirmed) {
                                                    $("#toReset").val("");
                                                    $("#toDisenroll").val("<?= $participant["log_id"] ?>");
                                                    $("#toChangeEmail").val("");
                                                    $("#manage-form").submit();
                                                }
                                            });
                                        })();'>Disenroll</button></td>
                                        <?php } ?>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <input type="hidden" id="toReset" name="toReset">
                    <input type="hidden" id="toChangeEmail" name="toChangeEmail">
                    <input type="hidden" id="newEmail" name="newEmail">
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
