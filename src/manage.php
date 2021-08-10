<?php

$role = SUPER_USER ? 3 : $module->getUserRole(USERID); // 3=admin/manager, 2=user, 1=monitor, 0=not found
if ($role === 0) {
    header("location:" . $module->getUrl("src/home.php"));
}

echo "<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8'><title>" . $module::$APPTITLE . " - Manage</title>";
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module::$UI->ShowHeader("Manage");

// RCPRO Project ID 
$rcpro_project_id = $module::$PROJECT->getProjectIdFromPID($project_id);

// DAGs
$rcpro_user_dag = $module::$DAG->getCurrentDag(USERID, $project_id);
$project_dags = $module::$DAG->getProjectDags();
$user_dags = $module::$DAG->getPossibleDags(USERID, PROJECT_ID);
$project_dags[NULL] = "Unassigned";
$projectHasDags = count($project_dags) > 1;

// Management Functions

function disenroll(int $rcpro_participant_id)
{
    global $role, $module, $rcpro_project_id;
    $function = "disenroll participant";

    // Check role
    if ($role <= 1) {
        $title = "You do not have the required role to do that.";
        $icon = "error";
    } else {
        $rcpro_username = $module::$PARTICIPANT->getUserName($rcpro_participant_id);
        $result = $module::$PROJECT->disenrollParticipant($rcpro_participant_id, $rcpro_project_id, $rcpro_username);
        if (!$result) {
            $title = "Trouble disenrolling participant.";
            $icon = "error";
        } else {
            $title = "Successfully disenrolled participant from project.";
            $icon = "success";
        }
    }
    return [$function, false, $icon, $title];
}

function resetPassword(int $rcpro_participant_id)
{
    global $module;
    $function = "send password reset email";

    $result = $module->sendPasswordResetEmail($rcpro_participant_id);
    if (!$result) {
        $icon = "error";
        $title = "Trouble sending password reset email.";
    } else {
        $icon = "success";
        $title = "Successfully reset password for participant.";
    }
    return [$function, false, $icon, $title];
}

function changeEmail(int $rcpro_participant_id, string $newEmail)
{
    global $role, $module;
    $function = "change participant's email address";

    // Check role
    if ($role <= 2) {
        $title = "You do not have the required role to do that.";
        $icon = "error";
    }

    // Check that email is not already associated with a participant
    else if ($module::$PARTICIPANT->checkEmailExists($newEmail)) {
        $title = "The provided email address is already associated with a REDCapPRO account.";
        $icon = "error";
    }

    // Try to change email
    else {
        $result = $module::$PARTICIPANT->changeEmailAddress($rcpro_participant_id, $newEmail);
        if (!$result) {
            $title = "Trouble changing participant's email address.";
            $icon = "error";
        } else {
            $title = "Successfully changed participant's email address.";
            $icon = "success";
        }
    }
    return [$function, false, $icon, $title];
}

function switchDAG(int $rcpro_participant_id, ?string $newDAG)
{
    global $role, $module, $project_dags, $rcpro_project_id;
    $function = "switch participant's Data Access Group";

    // Check role
    if ($role < 2) {
        $title = "You do not have the required role to do that.";
        $icon = "error";
    }

    // Check new DAG
    else if (!isset($newDAG) || !in_array($newDAG, array_keys($project_dags))) {
        $title = "The provided DAG is invalid.";
        $icon = "error";
    } else {
        $newDAG = $newDAG === "" ? NULL : $newDAG;
        $link_id = $module::$PROJECT->getLinkId($rcpro_participant_id, $rcpro_project_id);
        $result = $module::$DAG->updateDag($link_id, $newDAG);
        if (!$result) {
            $title = "Trouble switching participant's Data Access Group.";
            $icon = "error";
        } else {
            $participant_info = $module::$PARTICIPANT->getParticipantInfo($rcpro_participant_id);
            $module->log("Participant DAG Switched", [
                "rcpro_participant_id" => $participant_info["User_ID"],
                "rcpro_username" => $participant_info["Username"],
                "rcpro_project_id" => $rcpro_project_id,
                "rcpro_link_id" => $link_id,
                "project_dag" => $newDAG
            ]);
            $title = "Successfully switched participant's Data Access Group.";
            $icon = "success";
        }
    }
    return [$function, false, $icon, $title];
}

/**
 * Function to pick the first non-empty string value from the given arguments
 * If you want a default value in case all of the given variables are empty,  
 * pass an extra parameter as the last value.
 *
 * @return  mixed  The first non-empty value from the arguments passed   
 */
function coalesce_string()
{
    $args = func_get_args();

    while (count($args) && !($arg = array_shift($args)));

    return intval($arg) !== 0 ? $arg : null;
}

// Dealing with an action
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate token
    if (!$module::$AUTH->validate_csrf_token($_POST['token'])) {
        header("location:" . $module->getUrl("src/manage.php"));
        return;
    }

    try {
        $function = NULL;
        $showConfirm = false;
        $error = false;

        $rcpro_participant_id = intval(coalesce_string($_POST["toDisenroll"], $_POST["toReset"], $_POST["toChangeEmail"], $_POST["toSwitchDag"]));

        if (!$error && $rcpro_participant_id === 0) {
            $function = "make a change to this participant's account";
            $icon = "error";
            $title = "No participant was chosen";
            $error = true;
        }

        // Check that the participant is actually enrolled in this project
        if (!$error && !$module::$PROJECT->participantEnrolled($rcpro_participant_id, $rcpro_project_id)) {
            $function = "make a change to this participant's account";
            $icon = "error";
            $title = "Participant is Not Enrolled";
            $error = true;
        }

        // Check that the Data Access Group of the participant matches that of the user
        if (!$error && $projectHasDags) {
            $rcpro_link_id = $module::$PROJECT->getLinkId($rcpro_participant_id, $rcpro_project_id);
            $participant_dag = intval($module::$DAG->getParticipantDag($rcpro_link_id));
            $user_dag = $module::$DAG->getCurrentDag(USERID, PROJECT_ID);
            if (isset($user_dag) && $participant_dag !== $user_dag) {
                $function = "make a change to this participant's account";
                $icon = "error";
                $title = "Wrong Data Access Group";
                $error = true;
            }
        }

        // DISENROLL THE PARTICIPANT FROM THE STUDY
        if (!$error && !empty($_POST["toDisenroll"])) {
            list($function, $showConfirm, $icon, $title) = disenroll(intval($_POST["toDisenroll"]));
        }

        // SEND A PASSWORD RESET EMAIL
        else if (!$error && !empty($_POST["toReset"])) {
            list($function, $showConfirm, $icon, $title) = resetPassword(intval($_POST["toReset"]));
        }

        // CHANGE THE PARTICIPANT'S EMAIL ADDRESS
        else if (!$error && !empty($_POST["toChangeEmail"])) {
            list($function, $showConfirm, $icon, $title) = changeEmail(intval($_POST["toChangeEmail"]), $_POST["newEmail"]);
        }

        // CHANGE THE PARTICIPANT'S DATA ACCESS GROUP
        else if (!$error && !empty($_POST["toSwitchDag"])) {
            list($function, $showConfirm, $icon, $title) = switchDAG(intval($_POST["toSwitchDag"]), $_POST["newDag"]);
        }
    } catch (\Exception $e) {
        $icon = "error";
        $title = "Failed to ${function}.";
        $module->logError("Error attempting to ${function}", $e);
    }
}

// set csrf token
$module::$AUTH->set_csrf_token();

// Get list of participants
$participantList = $module::$PARTICIPANT->getProjectParticipants($rcpro_project_id, $rcpro_user_dag);



?>
<style>
    .wrapper {
        display: inline-block;
        padding: 20px;
    }

    .manage-form {
        border-radius: 5px;
        border: 1px solid #cccccc;
        padding: 20px 20px 0px 20px;
        box-shadow: 0px 0px 5px #eeeeee;
    }

    .manage-button {
        margin: 20px 5px 0px;
    }

    #RCPRO_Manage_Users tr.even {
        background-color: white !important;
        cursor: pointer;
    }

    #RCPRO_Manage_Users tr.odd {
        background-color: white !important;
        cursor: pointer;
    }

    #RCPRO_Manage_Users tr:hover {
        background-color: #dddddd !important;
    }

    #RCPRO_Manage_Users tr.selected {
        background-color: #900000 !important;
        color: white !important;
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

    .btn-rcpro {
        background-color: #900000;
        border-color: #900000;
        color: white;
    }

    .btn-rcpro:active,
    .btn-rcpro:hover {
        background-color: #700000;
        border-color: #700000;
        color: white;
    }

    .btn-rcpro:focus {
        outline: 0;
        box-shadow: 0 0 0 0.2rem #90000063;
    }
</style>
<script src="<?= $module->getUrl("lib/sweetalert/sweetalert2.all.min.js"); ?>"></script>
</head>

<body>

    <link rel='stylesheet' type='text/css' href='https://cdn.datatables.net/select/1.3.3/css/select.dataTables.min.css' />
    <script type='text/javascript' src='https://cdn.datatables.net/select/1.3.3/js/dataTables.select.min.js' defer></script>

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
                                <?php if ($projectHasDags) { ?>
                                    <th id="rcpro_dag" class="dt-center">Data Access Group</th>
                                <?php } ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($participantList as $participant) {
                                $username_clean = \REDCap::escapeHtml($participant["rcpro_username"]);
                                if ($role > 1) {
                                    $fname_clean    = \REDCap::escapeHtml($participant["fname"]);
                                    $lname_clean    = \REDCap::escapeHtml($participant["lname"]);
                                    $email_clean    = \REDCap::escapeHtml($participant["email"]);
                                }
                                $link_id        = $module::$PROJECT->getLinkId($participant["log_id"], $rcpro_project_id);
                                $dag_id         = $module::$DAG->getParticipantDag($link_id);
                                $dag_name       = \REDCap::getGroupNames(false, $dag_id);
                                $dag_name_clean = count($dag_name) === 1 ? \REDCap::escapeHtml($dag_name) : "Unassigned";
                            ?>
                                <?php
                                //TODO: ONLY ADD DATA TO ROW IF THE USER HAS THE APPROPRIATE ROLE!!!!! 
                                ?>
                                <tr data-id="<?= $participant["log_id"] ?>" data-username="<?= $username_clean ?>" data-fname="<?= $fname_clean ?>" data-lname="<?= $lname_clean ?>" data-email="<?= $email_clean ?>">
                                    <td><?= $username_clean ?></td>
                                    <?php if ($role > 1) { ?>
                                        <td class="dt-center"><?= $fname_clean ?></td>
                                        <td class="dt-center"><?= $lname_clean ?></td>
                                        <td><?= $email_clean ?></td>
                                    <?php } ?>
                                    <?php if ($role > 1 && $projectHasDags) { ?>
                                        <td class="dt-center">
                                            <select class="dag_select form-select form-select-sm" name="dag_select_<?= $username_clean ?>" id="dag_select_<?= $username_clean ?>" orig_value="<?= $dag_id ?>" form="manage-form" onchange='(function(){
                                                let el = $("#dag_select_<?= $username_clean ?>");
                                                let newDAG = el.val();
                                                let origDAG = el.attr("orig_value");
                                                let newDAGName = $("#dag_select_<?= $username_clean ?> option:selected").text();
                                                let oldDAGName = "<?= $dag_name_clean ?>";
                                                if (newDAG !== origDAG) {
                                                    Swal.fire({
                                                        title: "Switch Data Access Group for <?= $fname_clean . " " . $lname_clean ?>?",
                                                        html: `From ${oldDAGName} to ${newDAGName}`,
                                                        icon: "warning",
                                                        iconColor: "#900000",
                                                        confirmButtonText: "Switch DAG",
                                                        allowEnterKey: false,
                                                        showCancelButton: true,
                                                        confirmButtonColor: "#900000"
                                                    }).then((resp) => {
                                                        if (resp.isConfirmed) {
                                                            clearForm();
                                                            $("#toSwitchDag").val("<?= $participant["log_id"] ?>");
                                                            $("#newDag").val(newDAG); 
                                                            $("#manage-form").submit();
                                                        } else {
                                                            el.val(origDAG);
                                                        }
                                                    });
                                                }
                                                })();'>
                                                <?php foreach ($user_dags as $this_dag_id) { ?>
                                                    <option value="<?= $this_dag_id ?>" <?= $this_dag_id == $dag_id ? "selected" : "" ?>><?= $project_dags[$this_dag_id] ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                    <?php } else if ($projectHasDags) { ?>
                                        <td class="dt-center"><?= $dag_name_clean ?></td>
                                    <?php } ?>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-secondary btn-sm manage-button" onclick='(function(){
                        let table = $("#RCPRO_Manage_Users").DataTable();
                        let row = table.rows( { selected: true } );
                        if (row[0].length) {
                            let participant_id = row.nodes()[0].dataset.id;
                            clearForm();
                            $("#toReset").val(participant_id);
                            $("#manage-form").submit();
                        }
                    })();'>Reset Password</button>
                    <?php if ($role > 2) { ?>
                        <button type="button" class="btn btn-secondary btn-sm manage-button" onclick='(function(){
                            let table = $("#RCPRO_Manage_Users").DataTable();
                            let row = table.rows( { selected: true } );
                            if (row[0].length) {
                                let dataset = row.nodes()[0].dataset;
                                Swal.fire({
                                    title: `Enter the new email address for ${dataset.fname} ${dataset.lname}`,
                                    input: "email",
                                    inputPlaceholder: dataset.email,
                                    confirmButtonText: "Change Email",
                                    showCancelButton: true,
                                    confirmButtonColor: "#900000"
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        clearForm();
                                        $("#toChangeEmail").val(dataset.id);
                                        $("#newEmail").val(result.value); 
                                        $("#manage-form").submit();
                                    }
                                });
                            }
                        })();'>Change Email</button>
                    <?php } ?>
                    <?php if ($role > 1) { ?>
                        <button type="button" class="btn btn-rcpro btn-sm manage-button" onclick='(function(){
                            let table = $("#RCPRO_Manage_Users").DataTable();
                            let row = table.rows( { selected: true } );
                            if (row[0].length) {
                                let dataset = row.nodes()[0].dataset;
                                Swal.fire({
                                    icon: "warning",
                                    iconColor: "#900000",
                                    title: `Are you sure you want to remove ${dataset.fname} ${dataset.lname} from this project?`,
                                    confirmButtonText: "Remove Participant",
                                    allowEnterKey: false,
                                    showCancelButton: true,
                                    confirmButtonColor: "#900000"
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        clearForm();
                                        $("#toDisenroll").val(dataset.id);
                                        $("#manage-form").submit();
                                    }
                                });
                            }
                        })();'>Disenroll</button>
                    <?php } ?>
                </div>
                <input type="hidden" id="toReset" name="toReset">
                <input type="hidden" id="toChangeEmail" name="toChangeEmail">
                <input type="hidden" id="newEmail" name="newEmail">
                <input type="hidden" id="toDisenroll" name="toDisenroll">
                <input type="hidden" id="toSwitchDag" name="toSwitchDag">
                <input type="hidden" id="newDag" name="newDag">
                <input type="hidden" name="token" value="<?= $module::$AUTH->get_csrf_token(); ?>">
            <?php } ?>
        </form>
    </div>
    <script>
        $(document).ready(function() {
            // Function for resetting manage-form values
            window.clearForm = function() {
                $("#toReset").val("");
                $("#toChangeEmail").val("");
                $("#newEmail").val("");
                $("#toDisenroll").val("");
                $("#toSwitchDag").val("");
                $("#newDag").val("");
            }

            // Initialize DataTable
            let datatable = $('#RCPRO_Manage_Users').DataTable({
                select: {
                    style: 'single'
                }
            });

            // Activate/Deactivate buttons based on selections
            datatable.on('select', function(e, dt, type, indexes) {
                $('.manage-button').attr("disabled", false);
            });
            datatable.on('deselect', function(e, dt, type, indexes) {
                $('.manage-button').attr("disabled", true);
            });

            // Start with buttons deactivated
            $('.manage-button').attr("disabled", true);

            // Clicking on dag selector shouldn't select the row
            $('.dag_select').click((evt) => {
                evt.stopPropagation();
            });
        });
    </script>
</body>

<?php
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
