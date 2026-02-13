<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

$role = $module->getUserRole($module->safeGetUsername()); // 3=admin/manager, 2=user, 1=monitor, 0=not found
if ( $role === 0 ) {
    header("location:" . $module->getUrl("src/home.php"));
}
$module->includeFont();


require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$ui = new UI($module);
$ui->ShowHeader("Manage");
echo "<title>" . $module->APPTITLE . " - " . $module->tt("manage_title") . "</title>";


// Check for errors
if ( isset($_GET["error"]) ) {
    ?>
    <script>
        Swal.fire({
            icon: "error",
            title: "<?= $module->tt("project_error") ?>",
            text: "<?= $module->tt("project_error_general") ?>",
            showConfirmButton: false
        });
    </script>
    <?php
}

// Participant Helper
$participantHelper = new ParticipantHelper($module);

// RCPRO Project ID
$project_id       = (int) $module->framework->getProjectId();
$projectHelper    = new ProjectHelper($module);
$rcpro_project_id = $projectHelper->getProjectIdFromPID($project_id);

// DAGs
$dagHelper          = new DAG($module);
$rcpro_user_dag     = $dagHelper->getCurrentDag($module->safeGetUsername(), $project_id);
$project_dags       = $dagHelper->getProjectDags();
$user_dags          = $dagHelper->getPossibleDags($module->safeGetUsername(), $project_id);
$project_dags[NULL] = $module->tt("project_unassigned");
$projectHasDags     = count($project_dags) > 1;

// constants
$no_permission = $module->tt("project_manage_participants_no_permission");

// Management Functions
function disenroll(int $rcpro_participant_id)
{
    global $role, $module, $rcpro_project_id, $no_permission, $participantHelper, $projectHelper;
    $function = "disenroll participant";

    // Check role
    if ( $role <= 1 ) {
        $title = $no_permission;
        $icon  = "error";
    } else {
        $rcpro_username = $participantHelper->getUserName($rcpro_participant_id);
        $result         = $projectHelper->disenrollParticipant($rcpro_participant_id, $rcpro_project_id, $rcpro_username);
        if ( !$result ) {
            $title = $module->tt("project_manage_participants_error_disenroll");
            $icon  = "error";
        } else {
            $title = $module->tt("project_manage_participants_success_disenroll");
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
        $title = $module->tt("project_manage_participants_error_reset_password");
    } else {
        $icon  = "success";
        $title = $module->tt("project_manage_participants_success_reset_password");
    }
    return [ $function, false, $icon, $title ];
}

function changeName(int $rcpro_participant_id, string $newFirstName, string $newLastName)
{
    global $role, $module, $no_permission, $participantHelper;
    $function = "change participant's name";

    $trimmedFirstName = trim($newFirstName);
    $trimmedLastName  = trim($newLastName);

    // Check role
    if ( $role <= 2 ) {
        $title = $no_permission;
        $icon  = "error";
    }

    // Check that names are valid
    elseif ( $trimmedFirstName === "" || $trimmedLastName === "" ) {
        $title = $module->tt("project_manage_participants_error_invalid_name");
        $icon  = "error";
    }

    // Try to change name
    else {
        $result = $participantHelper->changeName($rcpro_participant_id, $trimmedFirstName, $trimmedLastName);
        if ( !$result ) {
            $title = $module->tt("project_manage_participants_error_name_change");
            $icon  = "error";
        } else {
            $title = $module->tt("project_manage_participants_success_name_change");
            $icon  = "success";
        }
    }
    return [ $function, false, $icon, $title ];
}

function changeEmail(int $rcpro_participant_id, string $newEmail)
{
    global $role, $module, $no_permission, $participantHelper;
    $function = "change participant's email address";

    // Check role
    if ( $role <= 2 ) {
        $title = $no_permission;
        $icon  = "error";
    }

    // Check that email is not already associated with a participant
    elseif ( $participantHelper->checkEmailExists($newEmail) ) {
        $title = $module->tt("project_manage_participants_error_email_exists");
        $icon  = "error";
    }

    // Try to change email
    else {
        $result = $participantHelper->changeEmailAddress($rcpro_participant_id, $newEmail);
        if ( !$result ) {
            $title = $module->tt("project_manage_participants_error_email_change");
            $icon  = "error";
        } else {
            $title = $module->tt("project_manage_participants_success_email_change");
            $icon  = "success";
        }
    }
    return [ $function, false, $icon, $title ];
}

function switchDAG(int $rcpro_participant_id, ?string $newDAG)
{
    global $role, $module, $project_dags, $rcpro_project_id, $no_permission, $dagHelper, $participantHelper, $projectHelper;
    $function = "switch participant's Data Access Group";

    // Check role
    if ( $role < 2 ) {
        $title = $no_permission;
        $icon  = "error";
    }

    // Check new DAG
    elseif ( !isset($newDAG) || !in_array($newDAG, array_keys($project_dags)) ) {
        $title = $module->tt("project_manage_participants_invalid_dag");
        $icon  = "error";
    } else {
        $newDAG  = $newDAG === "" ? NULL : $newDAG;
        $link_id = $projectHelper->getLinkId($rcpro_participant_id, $rcpro_project_id);
        $result  = $dagHelper->updateDag($link_id, $newDAG);
        if ( !$result ) {
            $title = $module->tt("project_manage_participants_error_dag_change");
            $icon  = "error";
        } else {
            $participant_info = $participantHelper->getParticipantInfo($rcpro_participant_id);
            $module->logEvent("Participant DAG Switched", [
                "rcpro_participant_id" => $participant_info["User_ID"],
                "rcpro_username"       => $participant_info["Username"],
                "rcpro_project_id"     => $rcpro_project_id,
                "rcpro_link_id"        => $link_id,
                "project_dag"          => $newDAG
            ]);
            $title = $module->tt("project_manage_participants_success_dag_change");
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
            $title    = $module->tt("project_manage_participants_error_no_participant");
            $error    = true;
        }

        // Check that the participant is actually enrolled in this project
        if ( !$error && !$projectHelper->participantEnrolled($rcpro_participant_id, $rcpro_project_id) ) {
            $function = $generic_function;
            $icon     = "error";
            $title    = $module->tt("project_manage_participants_error_not_enrolled");
            $error    = true;
        }

        // Check that the Data Access Group of the participant matches that of the user
        if ( !$error && $projectHasDags ) {
            $rcpro_link_id   = $projectHelper->getLinkId($rcpro_participant_id, $rcpro_project_id);
            $participant_dag = intval($dagHelper->getParticipantDag($rcpro_link_id));
            $user_dag        = $dagHelper->getCurrentDag($module->safeGetUsername(), $module->framework->getProjectId());
            if ( isset($user_dag) && $participant_dag !== $user_dag ) {
                $function = $generic_function;
                $icon     = "error";
                $title    = $module->tt("project_manage_participants_error_wrong_dag");
                $error    = true;
            }
        }

        // DISENROLL THE PARTICIPANT FROM THE STUDY
        if ( !$error && !empty($_POST["toDisenroll"]) ) {
            list( $function, $showConfirm, $icon, $title ) = disenroll(intval($_POST["toDisenroll"]));
        }

        // SEND A PASSWORD RESET EMAIL
        elseif ( !$error && !empty($_POST["toReset"]) ) {
            list( $function, $showConfirm, $icon, $title ) = resetPassword(intval($_POST["toReset"]));
        }

        // CHANGE THE PARTICIPANT'S NAME
        elseif ( !$error && !empty($_POST["toChangeName"]) ) {
            list( $function, $showConfirm, $icon, $title ) = changeName(intval($_POST["toChangeName"]), $_POST["newFirstName"], $_POST["newLastName"]);
        }

        // CHANGE THE PARTICIPANT'S EMAIL ADDRESS
        elseif ( !$error && !empty($_POST["toChangeEmail"]) ) {
            list( $function, $showConfirm, $icon, $title ) = changeEmail(intval($_POST["toChangeEmail"]), $_POST["newEmail"]);
        }

        // CHANGE THE PARTICIPANT'S DATA ACCESS GROUP
        elseif ( !$error && !empty($_POST["toSwitchDag"]) ) {
            list( $function, $showConfirm, $icon, $title ) = switchDAG(intval($_POST["toSwitchDag"]), $_POST["newDag"]);
        }
    } catch ( \Exception $e ) {
        $icon  = "error";
        $title = $module->tt("project_manage_participants_error_general");
        $module->logError("Error attempting to {$function}", $e);
    }
}

$module->initializeJavascriptModuleObject();
$module->tt_transferToJavascriptModuleObject();

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
    <h2><?= $module->tt("project_manage_participants_page_title") ?></h2>
    <p><?= $module->tt("project_manage_participants_subtitle") ?></p>
    <div id="loading-container" class="loader-container">
        <div id="loading" class="loader"></div>
    </div>
    <div id="parent" class="dataTableParentHidden" style="display:hidden;">
        <form class="rcpro-form manage-form" id="manage-form" action="<?= $module->getUrl("src/manage.php"); ?>"
            method="POST" enctype="multipart/form-data" target="_self">
            <div class="form-group">
                <table class="rcpro-datatable" id="RCPRO_TABLE" style="width:100%;">
                    <caption><?= $module->tt("project_manage_participants_study_participants") ?></caption>
                    <thead>
                        <tr>
                            <th id="rcpro_username" class="dt-center"><?= $module->tt("project_username") ?></th>
                            <?php if ( $role > 1 ) { ?>
                                <th id="rcpro_fname" class="dt-center"><?= $module->tt("project_first_name") ?></th>
                                <th id="rcpro_lname" class="dt-center"><?= $module->tt("project_last_name") ?></th>
                                <th id="rcpro_email"><?= $module->tt("project_email") ?></th>
                            <?php } ?>
                            <?php if ( $role > 1 && $projectHasDags ) { ?>
                                <th id="rcpro_dag" class="dt-center"><?= $module->tt("project_dag") ?></th>
                            <?php } ?>
                        </tr>
                    </thead>
                    <tbody>

                    </tbody>
                </table>
                <button type="button" class="btn btn-secondary rcpro-form-button" onclick='(function(){
                        let table = $("#RCPRO_TABLE").DataTable();
                        let row = table.rows( { selected: true } );
                        if (row[0].length) {
                            let participant_id = $(row.nodes()[0]).data().id;
                            clearForm();
                            $("#toReset").val(participant_id);
                            $("#manage-form").submit();
                        }
                    })();'><?= $module->tt("project_reset_password") ?></button>
                <?php if ( $role > 2 ) { ?>
                    <button type="button" class="btn btn-secondary rcpro-form-button" onclick='(function(){
                            let table = $("#RCPRO_TABLE").DataTable();
                            let row = table.rows( { selected: true } );
                            if (row[0].length) {
                                let dataset = $(row.nodes()[0]).data();
                                Swal.fire({
                                    title: "<?= $module->tt("project_manage_participants_enter_new_name") ?>", 
                                    html: `<input id="swal-fname" class="swal2-input" value="${dataset.fname}"><input id="swal-lname" class="swal2-input" value="${dataset.lname}">`,
                                    confirmButtonText: "<?= $module->tt("project_change_name") ?>",
                                    showCancelButton: true,
                                    cancelButtonText: "<?= $module->tt("project_cancel") ?>",
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
                                                title: "<?= $module->tt("project_manage_participants_must_enter_name") ?>",
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
                        })();'><?= $module->tt("project_change_name") ?></button>
                    <button type="button" class="btn btn-secondary rcpro-form-button" onclick='(function(){
                            let table = $("#RCPRO_TABLE").DataTable();
                            let row = table.rows( { selected: true } );
                            if (row[0].length) {
                                let dataset = $(row.nodes()[0]).data();
                                Swal.fire({
                                    title: window.RCPRO_module.tt("project_manage_participants_enter_new_email", [dataset.fname, dataset.lname]),
                                    input: "email",
                                    inputPlaceholder: dataset.email,
                                    confirmButtonText: "<?= $module->tt("project_change_email") ?>",
                                    showCancelButton: true,
                                    cancelButtonText: "<?= $module->tt("project_cancel") ?>",
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
                        })();'><?= $module->tt("project_change_email") ?></button>
                <?php } ?>
                <?php if ( $role > 1 ) { ?>
                    <button type="button" class="btn btn-rcpro rcpro-form-button" onclick='(function(){
                            let table = $("#RCPRO_TABLE").DataTable();
                            let row = table.rows( { selected: true } );
                            if (row[0].length) {
                                let dataset = $(row.nodes()[0]).data();
                                Swal.fire({
                                    icon: "warning",
                                    iconColor: "<?= $module::$COLORS["primary"] ?>",
                                    title: window.RCPRO_module.tt("project_manage_participants_confirm_disenroll", [dataset.fname, dataset.lname]),
                                    confirmButtonText: "<?= $module->tt("project_manage_participants_disenroll") ?>",
                                    allowEnterKey: false,
                                    showCancelButton: true,
                                    cancelButtonText: "<?= $module->tt("project_cancel") ?>",
                                    confirmButtonColor: "<?= $module::$COLORS["primary"] ?>"
                                }).then(function(result) {
                                    if (result.isConfirmed) {
                                        clearForm();
                                        $("#toDisenroll").val(dataset.id);
                                        $("#manage-form").submit();
                                    }
                                });
                            }
                        })();'><?= $module->tt("project_disenroll") ?></button>
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
        </form>
    </div>
</div>
<script>
    const RCPRO_module = <?= $module->framework->getJavascriptModuleObjectName() ?>;
    window.RCPRO_module = RCPRO_module;
    RCPRO_module.role = <?= $role ?>;
    RCPRO_module.projectHasDags = JSON.parse('<?= json_encode($projectHasDags) ?>');
    RCPRO_module.projectDags = JSON.parse('<?= json_encode($project_dags) ?>');
    RCPRO_module.rcpro_user_dag = JSON.parse('<?= json_encode($rcpro_user_dag) ?>');
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
            const columnDef = [{
                title: "<?= $module->tt("project_username") ?>",
                data: function (row, type, val, meta) {
                    if (row.password_set) {
                        return `<span style='white-space:nowrap;'><i title='<?= $module->tt("project_manage_participants_password_set") ?>' class='fas fa-solid fa-check-circle' style='margin-left:2px;margin-right:2px;color:<?= $module::$COLORS["green"] ?>;'></i>&nbsp;${row.username}</span>`;
                    } else {
                        return `<span style='white-space:nowrap;'><i title='<?= $module->tt("project_manage_participants_password_not_set") ?>' class='far ra-regular fa-circle-xmark' style='margin-left:2px;margin-right:2px;color:<?= $module::$COLORS["ban"] ?>;'></i>&nbsp;${row.username}</span>`;
                    }
                }
            }];
            if (RCPRO_module.role > 1) {
                columnDef.push({
                    title: '<?= $module->tt("project_first_name") ?>',
                    data: 'fname',
                    className: 'dt-center'
                });
                columnDef.push({
                    title: '<?= $module->tt("project_last_name") ?>',
                    data: 'lname',
                    className: 'dt-center'
                });
                columnDef.push({
                    title: '<?= $module->tt("project_email") ?>',
                    data: 'email'
                });
            }
            if (RCPRO_module.role > 1 && RCPRO_module.projectHasDags && RCPRO_module.rcpro_user_dag === null) {
                columnDef.push({
                    title: '<?= $module->tt("project_dag") ?>',
                    data: function (row, type, val, meta) {
                        if (type === 'display') {
                            const select = document.createElement("select");
                            select.classList.add("dag_select", "form-select", "form-select-sm");
                            $(select).attr("name", "dag_select_" + row.username);
                            $(select).attr("id", "dag_select_" + row.username);
                            $(select).attr("orig_value", row.dag_id);
                            $(select).attr("orig_dag", row.dag_name);
                            $(select).attr("form", "manage-form");
                            $(select).attr("fname", row.fname);
                            $(select).attr("lname", row.lname);
                            $(select).attr("rcpro_participant_id", row.rcpro_participant_id);
                            $(select).attr('onchange', '(' + function (el) {
                                const $el = $(el);
                                let newDAG = $el.val();
                                let origDAG = $el.attr("orig_value");
                                let newDAGName = $el.find("option:selected").text();
                                let oldDAGName = $el.attr("orig_dag");
                                if (newDAG !== origDAG) {
                                    Swal.fire({
                                        title: RCPRO_module.tt("project_manage_participants_switch_dag_for", [$el.attr('fname'), $el.attr('lname')]),
                                        html:  RCPRO_module.tt("project_manage_participants_dag_from_to", [oldDAGName, newDAGName]),
                                        icon: "warning",
                                        iconColor: "<?= $module::$COLORS["primary"] ?>",
                                        confirmButtonText: "<?= $module->tt("project_manage_participants_switch_dag") ?>",
                                        allowEnterKey: false,
                                        showCancelButton: true,
                                        cancelButtonText: "<?= $module->tt("project_cancel") ?>",
                                        confirmButtonColor: "<?= $module::$COLORS["primary"] ?>"
                                    }).then(function (resp) {
                                        if (resp.isConfirmed) {
                                            clearForm();
                                            $("#toSwitchDag").val($el.attr('rcpro_participant_id'));
                                            $("#newDag").val(newDAG);
                                            $("#manage-form").submit();
                                        } else {
                                            $el.val(origDAG);
                                        }
                                    });
                                }
                            }.toString() + ')(this);');
                            for (const project_dag_id in RCPRO_module.projectDags) {
                                const dag_id = row.dag_id === null ? '' : row.dag_id;
                                const selected = project_dag_id == dag_id;
                                const option = document.createElement("option");
                                option.value = project_dag_id;
                                $(option).attr('selected', selected);
                                option.text = RCPRO_module.projectDags[project_dag_id];
                                select.append(option);
                            }
                            return select.outerHTML;
                        }
                        return row.dag_name;
                    },
                    className: 'dt-center'
                });
            } else if (RCPRO_module.role > 1 && RCPRO_module.projectHasDags) {
                columnDef.push({
                    title: '<?= $module->tt("project_dag") ?>',
                    data: 'dag_name',
                    className: 'dt-center'
                });
            }


            let datatable = $('#RCPRO_TABLE').DataTable({
                deferRender: true,
                processing: true,
                ajax: function (data, callback, settings) {
                    RCPRO_module.ajax('getParticipants', {})
                        .then(response => {
                            callback({ data: response });
                        })
                        .catch(error => {
                            console.error(error);
                            callback({ data: [] });
                        });
                },
                columns: columnDef,
                createdRow: function (row, data, dataIndex) {
                    $(row).addClass('pointer');
                    $(row).data("id", data.rcpro_participant_id);
                    $(row).data("username", data.username);
                    $(row).data("fname", data.fname);
                    $(row).data("lname", data.lname);
                    $(row).data("email", data.email);
                },
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
include_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';