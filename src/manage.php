<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

$role = $module->getUserRole($module->safeGetUsername()); // 3=admin/manager, 2=user, 1=monitor, 0=not found
if ( $role === 0 ) {
    header("location:" . $module->getUrl("src/home.php"));
}


require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->UI->ShowHeader("Manage");
echo "<title>" . $module->APPTITLE . " - Manage</title>";

// Check for errors
if ( isset($_GET["error"]) ) {
    ?>
    <script>
        Swal.fire({
            icon: "error",
            title: "Error",
            text: "There was a problem. Please try again.",
            showConfirmButton: false
        });
    </script>
    <?php
}

// RCPRO Project ID 
$project_id       = (int) $module->framework->getProjectId();
$rcpro_project_id = $module->PROJECT->getProjectIdFromPID($project_id);

// DAGs
$rcpro_user_dag     = $module->DAG->getCurrentDag($module->safeGetUsername(), $project_id);
$project_dags       = $module->DAG->getProjectDags();
$user_dags          = $module->DAG->getPossibleDags($module->safeGetUsername(), $project_id);
$project_dags[NULL] = "Unassigned";
$projectHasDags     = count($project_dags) > 1;

// constants
$no_permission = "You do not have the required role to do that.";

// Management Functions
function disenroll(int $rcpro_participant_id)
{
    global $role, $module, $rcpro_project_id, $no_permission;
    $function = "disenroll participant";

    // Check role
    if ( $role <= 1 ) {
        $title = $no_permission;
        $icon  = "error";
    } else {
        $rcpro_username = $module->PARTICIPANT->getUserName($rcpro_participant_id);
        $result         = $module->PROJECT->disenrollParticipant($rcpro_participant_id, $rcpro_project_id, $rcpro_username);
        if ( !$result ) {
            $title = "Trouble disenrolling participant.";
            $icon  = "error";
        } else {
            $title = "Successfully disenrolled participant from project.";
            $icon  = "success";
        }
    }
    return [ $function, false, $icon, $title ];
}

function resetPassword(int $rcpro_participant_id)
{
    global $module;
    $function = "send password reset email";

    $result = $module->sendPasswordResetEmail($rcpro_participant_id);
    if ( !$result ) {
        $icon  = "error";
        $title = "Trouble sending password reset email.";
    } else {
        $icon  = "success";
        $title = "Successfully reset password for participant.";
    }
    return [ $function, false, $icon, $title ];
}

function changeName(int $rcpro_participant_id, string $newFirstName, string $newLastName)
{
    global $role, $module, $no_permission;
    $function = "change participant's name";

    $trimmedFirstName = trim($newFirstName);
    $trimmedLastName  = trim($newLastName);

    // Check role
    if ( $role <= 2 ) {
        $title = $no_permission;
        $icon  = "error";
    }

    // Check that names are valid
    else if ( $trimmedFirstName === "" || $trimmedLastName === "" ) {
        $title = "You need to provide valid first and last names.";
        $icon  = "error";
    }

    // Try to change name
    else {
        $result = $module->PARTICIPANT->changeName($rcpro_participant_id, $trimmedFirstName, $trimmedLastName);
        if ( !$result ) {
            $title = "Trouble updating participant's name.";
            $icon  = "error";
        } else {
            $title = "Successfully updated participant's name.";
            $icon  = "success";
        }
    }
    return [ $function, false, $icon, $title ];
}

function changeEmail(int $rcpro_participant_id, string $newEmail)
{
    global $role, $module, $no_permission;
    $function = "change participant's email address";

    // Check role
    if ( $role <= 2 ) {
        $title = $no_permission;
        $icon  = "error";
    }

    // Check that email is not already associated with a participant
    else if ( $module->PARTICIPANT->checkEmailExists($newEmail) ) {
        $title = "The provided email address is already associated with a REDCapPRO account.";
        $icon  = "error";
    }

    // Try to change email
    else {
        $result = $module->PARTICIPANT->changeEmailAddress($rcpro_participant_id, $newEmail);
        if ( !$result ) {
            $title = "Trouble changing participant's email address.";
            $icon  = "error";
        } else {
            $title = "Successfully changed participant's email address.";
            $icon  = "success";
        }
    }
    return [ $function, false, $icon, $title ];
}

function switchDAG(int $rcpro_participant_id, ?string $newDAG)
{
    global $role, $module, $project_dags, $rcpro_project_id, $no_permission;
    $function = "switch participant's Data Access Group";

    // Check role
    if ( $role < 2 ) {
        $title = $no_permission;
        $icon  = "error";
    }

    // Check new DAG
    else if ( !isset($newDAG) || !in_array($newDAG, array_keys($project_dags)) ) {
        $title = "The provided DAG is invalid.";
        $icon  = "error";
    } else {
        $newDAG  = $newDAG === "" ? NULL : $newDAG;
        $link_id = $module->PROJECT->getLinkId($rcpro_participant_id, $rcpro_project_id);
        $result  = $module->DAG->updateDag($link_id, $newDAG);
        if ( !$result ) {
            $title = "Trouble switching participant's Data Access Group.";
            $icon  = "error";
        } else {
            $participant_info = $module->PARTICIPANT->getParticipantInfo($rcpro_participant_id);
            $module->logEvent("Participant DAG Switched", [
                "rcpro_participant_id" => $participant_info["User_ID"],
                "rcpro_username"       => $participant_info["Username"],
                "rcpro_project_id"     => $rcpro_project_id,
                "rcpro_link_id"        => $link_id,
                "project_dag"          => $newDAG
            ]);
            $title = "Successfully switched participant's Data Access Group.";
            $icon  = "success";
        }
    }
    return [ $function, false, $icon, $title ];
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

    while ( count($args) && !($arg = array_shift($args)) );

    return intval($arg) !== 0 ? $arg : null;
}

// Dealing with an action
if ( $_SERVER["REQUEST_METHOD"] == "POST" ) {

    // Log submission
    $module->logForm("Submitted Manage Participants Form", $_POST);

    try {
        $function    = NULL;
        $showConfirm = false;
        $error       = false;

        $rcpro_participant_id = intval(
            coalesce_string(
                $_POST["toDisenroll"],
                $_POST["toReset"],
                $_POST["toChangeEmail"],
                $_POST["toSwitchDag"],
                $_POST["toChangeName"]
            )
        );

        $generic_function = "make a change to this participant's account";
        if ( !$error && $rcpro_participant_id === 0 ) {
            $function = $generic_function;
            $icon     = "error";
            $title    = "No participant was chosen";
            $error    = true;
        }

        // Check that the participant is actually enrolled in this project
        if ( !$error && !$module->PROJECT->participantEnrolled($rcpro_participant_id, $rcpro_project_id) ) {
            $function = $generic_function;
            $icon     = "error";
            $title    = "Participant is Not Enrolled";
            $error    = true;
        }

        // Check that the Data Access Group of the participant matches that of the user
        if ( !$error && $projectHasDags ) {
            $rcpro_link_id   = $module->PROJECT->getLinkId($rcpro_participant_id, $rcpro_project_id);
            $participant_dag = intval($module->DAG->getParticipantDag($rcpro_link_id));
            $user_dag        = $module->DAG->getCurrentDag($module->safeGetUsername(), $module->framework->getProjectId());
            if ( isset($user_dag) && $participant_dag !== $user_dag ) {
                $function = $generic_function;
                $icon     = "error";
                $title    = "Wrong Data Access Group";
                $error    = true;
            }
        }

        // DISENROLL THE PARTICIPANT FROM THE STUDY
        if ( !$error && !empty($_POST["toDisenroll"]) ) {
            list( $function, $showConfirm, $icon, $title ) = disenroll(intval($_POST["toDisenroll"]));
        }

        // SEND A PASSWORD RESET EMAIL
        else if ( !$error && !empty($_POST["toReset"]) ) {
            list( $function, $showConfirm, $icon, $title ) = resetPassword(intval($_POST["toReset"]));
        }

        // CHANGE THE PARTICIPANT'S NAME
        else if ( !$error && !empty($_POST["toChangeName"]) ) {
            list( $function, $showConfirm, $icon, $title ) = changeName(intval($_POST["toChangeName"]), $_POST["newFirstName"], $_POST["newLastName"]);
        }

        // CHANGE THE PARTICIPANT'S EMAIL ADDRESS
        else if ( !$error && !empty($_POST["toChangeEmail"]) ) {
            list( $function, $showConfirm, $icon, $title ) = changeEmail(intval($_POST["toChangeEmail"]), $_POST["newEmail"]);
        }

        // CHANGE THE PARTICIPANT'S DATA ACCESS GROUP
        else if ( !$error && !empty($_POST["toSwitchDag"]) ) {
            list( $function, $showConfirm, $icon, $title ) = switchDAG(intval($_POST["toSwitchDag"]), $_POST["newDag"]);
        }
    } catch ( \Exception $e ) {
        $icon  = "error";
        $title = "Failed to ${function}.";
        $module->logError("Error attempting to ${function}", $e);
    }
}

// Get list of participants
$participantList = $module->PARTICIPANT->getProjectParticipants($rcpro_project_id, $rcpro_user_dag);



?>
<link rel="stylesheet" type="text/css" href="<?= $module->getUrl("src/css/rcpro.php") ?>" />
<script src="<?= $module->getUrl("lib/sweetalert/sweetalert2.all.min.js"); ?>"></script>
<link rel='stylesheet' type='text/css' href='https://cdn.datatables.net/select/1.3.3/css/select.dataTables.min.css' />
<script type='text/javascript' src='https://cdn.datatables.net/select/1.3.3/js/dataTables.select.min.js' defer></script>

<?php if ( $_SERVER["REQUEST_METHOD"] == "POST" ) { ?>
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
    <div id="loading-container" class="loader-container">
        <div id="loading" class="loader"></div>
    </div>
    <div id="parent" class="dataTableParentHidden" style="display:hidden;">
        <form class="rcpro-form manage-form" id="manage-form" action="<?= $module->getUrl("src/manage.php"); ?>"
            method="POST" enctype="multipart/form-data" target="_self">
            <?php if ( count($participantList) === 0 ) { ?>
                <div>
                    <p>No participants have been enrolled in this study</p>
                </div>
            <?php } else { ?>
                <div class="form-group">
                    <table class="rcpro-datatable" id="RCPRO_TABLE" style="width:100%;">
                        <caption>Study Participants</caption>
                        <thead>
                            <tr>
                                <th id="rcpro_username" class="dt-center">Username</th>
                                <?php if ( $role > 1 ) { ?>
                                    <th id="rcpro_fname" class="dt-center">First Name</th>
                                    <th id="rcpro_lname" class="dt-center">Last Name</th>
                                    <th id="rcpro_email">Email</th>
                                <?php } ?>
                                <?php if ( $projectHasDags ) { ?>
                                    <th id="rcpro_dag" class="dt-center">Data Access Group</th>
                                <?php } ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $participantList as $participant ) {
                                $username_clean = \REDCap::escapeHtml($participant["rcpro_username"]);
                                $password_set   = $participant["pw_set"] === 'True';
                                if ( $role > 1 ) {
                                    $fname_clean = \REDCap::escapeHtml($participant["fname"]);
                                    $lname_clean = \REDCap::escapeHtml($participant["lname"]);
                                    $email_clean = \REDCap::escapeHtml($participant["email"]);
                                }
                                $link_id        = $module->PROJECT->getLinkId($participant["log_id"], $rcpro_project_id);
                                $dag_id         = $module->DAG->getParticipantDag($link_id);
                                $dag_name       = \REDCap::getGroupNames(false, $dag_id);
                                $dag_name_clean = count((array) $dag_name) === 1 ? \REDCap::escapeHtml($dag_name) : "Unassigned"; // FIX: PHP 8 count needs the typecast here
                                ?>
                                <tr class="pointer" data-id="<?= $participant["log_id"] ?>"
                                    data-username="<?= $username_clean ?>" data-fname="<?= $fname_clean ?>"
                                    data-lname="<?= $lname_clean ?>" data-email="<?= $email_clean ?>">
                                    <td class="dt-center">
                                        <?= "<span style='white-space:nowrap'><i " . ($password_set ? "title='Password Set' class='fas fa-solid fa-check-circle'" : "title='Password NOT Set' class='far fa-regular fa-circle-xmark") . "' style='margin-left:2px;margin-right:2px;color:" . ($password_set ? $module::$COLORS["green"] : $module::$COLORS["ban"]) . ";'></i>&nbsp;$username_clean</span>" ?>
                                    </td>
                                    <?php if ( $role > 1 ) { ?>
                                        <td class="dt-center">
                                            <?= $fname_clean ?>
                                        </td>
                                        <td class="dt-center">
                                            <?= $lname_clean ?>
                                        </td>
                                        <td>
                                            <?= $email_clean ?>
                                        </td>
                                    <?php } ?>
                                    <?php if ( $role > 1 && $projectHasDags && $rcpro_user_dag === null ) { ?>
                                        <td class="dt-center">
                                            <select class="dag_select form-select form-select-sm"
                                                name="dag_select_<?= $username_clean ?>" id="dag_select_<?= $username_clean ?>"
                                                orig_value="<?= $dag_id ?>" form="manage-form" onchange='(function(){
                                                let el = $("#dag_select_<?= $username_clean ?>");
                                                let newDAG = el.val();
                                                let origDAG = el.attr("orig_value");
                                                let newDAGName = $("#dag_select_<?= $username_clean ?> option:selected").text();
                                                let oldDAGName = "<?= $dag_name_clean ?>";
                                                if (newDAG !== origDAG) {
                                                    Swal.fire({
                                                        title: "Switch Data Access Group for <?= $fname_clean . " " . $lname_clean ?>?",
                                                        html: "From "+oldDAGName+" to "+newDAGName,
                                                        icon: "warning",
                                                        iconColor: "<?= $module::$COLORS["primary"] ?>",
                                                        confirmButtonText: "Switch DAG",
                                                        allowEnterKey: false,
                                                        showCancelButton: true,
                                                        confirmButtonColor: "<?= $module::$COLORS["primary"] ?>"
                                                    }).then(function(resp) {
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
                                                <?php
                                                foreach ( $project_dags as $project_dag_id => $project_dag_name ) {
                                                    $selected = $project_dag_id == $dag_id ? "selected" : "";
                                                    echo "<option value='${project_dag_id}' ${selected}>${project_dag_name}</option>";
                                                }
                                                ?>
                                            </select>
                                        </td>
                                    <?php } else if ( $projectHasDags ) { ?>
                                            <td class="dt-center">
                                            <?= $dag_name_clean ?>
                                            </td>
                                    <?php } ?>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-secondary rcpro-form-button" onclick='(function(){
                        let table = $("#RCPRO_TABLE").DataTable();
                        let row = table.rows( { selected: true } );
                        if (row[0].length) {
                            let participant_id = row.nodes()[0].dataset.id;
                            clearForm();
                            $("#toReset").val(participant_id);
                            $("#manage-form").submit();
                        }
                    })();'>Reset Password</button>
                    <?php if ( $role > 2 ) { ?>
                        <button type="button" class="btn btn-secondary rcpro-form-button" onclick='(function(){
                            let table = $("#RCPRO_TABLE").DataTable();
                            let row = table.rows( { selected: true } );
                            if (row[0].length) {
                                let dataset = row.nodes()[0].dataset;
                                Swal.fire({
                                    title: "Enter the new name for this participant", 
                                    html: `<input id="swal-fname" class="swal2-input" value="${dataset.fname}"><input id="swal-lname" class="swal2-input" value="${dataset.lname}">`,
                                    confirmButtonText: "Change Participant Name",
                                    showCancelButton: true,
                                    allowEnterKey: false,
                                    confirmButtonColor: "<?= $module::$COLORS["primary"] ?>",
                                    preConfirm: () => {
                                        return {
                                            fname: document.getElementById("swal-fname").value,
                                            lname: document.getElementById("swal-lname").value
                                        }
                                    }
                                }).then(function(result) {
                                    if (result.isConfirmed) {
                                        fname = trim(result.value.fname);
                                        lname = trim(result.value.lname);
                                        if (!fname || !lname) {
                                            Swal.fire({
                                                title: "You must provide a first and last name",
                                                icon: "error",
                                                showConfirmButton: false,
                                                showCancelButton: false
                                            });
                                        } else {
                                            clearForm();
                                            $("#toChangeName").val(dataset.id);
                                            $("#newFirstName").val(fname); 
                                            $("#newLastName").val(lname); 
                                            $("#manage-form").submit();
                                        }
                                    }
                                });
                            }
                        })();'>Change Name</button>
                        <button type="button" class="btn btn-secondary rcpro-form-button" onclick='(function(){
                            let table = $("#RCPRO_TABLE").DataTable();
                            let row = table.rows( { selected: true } );
                            if (row[0].length) {
                                let dataset = row.nodes()[0].dataset;
                                Swal.fire({
                                    title: "Enter the new email address for "+dataset.fname+" "+dataset.lname,
                                    input: "email",
                                    inputPlaceholder: dataset.email,
                                    confirmButtonText: "Change Email",
                                    showCancelButton: true,
                                    confirmButtonColor: "<?= $module::$COLORS["primary"] ?>"
                                }).then(function(result) {
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
                    <?php if ( $role > 1 ) { ?>
                        <button type="button" class="btn btn-rcpro rcpro-form-button" onclick='(function(){
                            let table = $("#RCPRO_TABLE").DataTable();
                            let row = table.rows( { selected: true } );
                            if (row[0].length) {
                                let dataset = row.nodes()[0].dataset;
                                Swal.fire({
                                    icon: "warning",
                                    iconColor: "<?= $module::$COLORS["primary"] ?>",
                                    title: "Are you sure you want to remove "+dataset.fname+" "+dataset.lname+" from this project?",
                                    confirmButtonText: "Remove Participant",
                                    allowEnterKey: false,
                                    showCancelButton: true,
                                    confirmButtonColor: "<?= $module::$COLORS["primary"] ?>"
                                }).then(function(result) {
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
                <input type="hidden" id="toChangeName" name="toChangeName">
                <input type="hidden" id="newFirstName" name="newFirstName">
                <input type="hidden" id="newLastName" name="newLastName">
                <input type="hidden" id="toDisenroll" name="toDisenroll">
                <input type="hidden" id="toSwitchDag" name="toSwitchDag">
                <input type="hidden" id="newDag" name="newDag">
                <input type="hidden" name="redcap_csrf_token" value="<?= $module->framework->getCSRFToken() ?>">
            <?php } ?>
        </form>
    </div>
</div>
<script>
    (function ($, window, document) {
        $(document).ready(function () {
            // Function for resetting manage-form values
            window.clearForm = function () {
                $("#toReset").val("");
                $("#toChangeEmail").val("");
                $("#newEmail").val("");
                $("#toChangeName").val("");
                $("#newFirstName").val("");
                $("#newLastName").val("");
                $("#toDisenroll").val("");
                $("#toSwitchDag").val("");
                $("#newDag").val("");
            }

            // Initialize DataTable
            let datatable = $('#RCPRO_TABLE').DataTable({
                select: {
                    style: 'single'
                },
                stateSave: true,
                stateSaveCallback: function (settings, data) {
                    localStorage.setItem('DataTables_manage_' + settings.sInstance, JSON.stringify(data))
                },
                stateLoadCallback: function (settings) {
                    return JSON.parse(localStorage.getItem('DataTables_manage_' + settings.sInstance))
                },
                scrollY: '50vh',
                scrollCollapse: true
            });

            // Activate/Deactivate buttons based on selections
            datatable.on('select', function (e, dt, type, indexes) {
                $('.rcpro-form-button').attr("disabled", false);
            });
            datatable.on('deselect', function (e, dt, type, indexes) {
                $('.rcpro-form-button').attr("disabled", true);
            });

            // Start with buttons deactivated
            $('.rcpro-form-button').attr("disabled", true);

            // Clicking on dag selector shouldn't select the row
            $('.dag_select').click(function (evt) {
                evt.stopPropagation();
            });
            $('#parent').removeClass('dataTableParentHidden').show();
            $('#loading-container').hide();
            datatable.columns.adjust().draw();
        });
    })(window.jQuery, window, document);
</script>

<?php
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';