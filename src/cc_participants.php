<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

if ( !$module->framework->isSuperUser() ) {
    exit();
}
echo '<!DOCTYPE html><html lang="en">';
$module->includeFont();

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

require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';
$module->UI->ShowControlCenterHeader("Participants");

if ( $_SERVER["REQUEST_METHOD"] == "POST" ) {

    // Log submission
    $module->logForm("Submitted Control Center Participants Form", $_POST);

    try {
        $function = NULL;
        // SEND A PASSWORD RESET EMAIL
        if ( !empty($_POST["toReset"]) ) {
            $function = "send password reset email";
            $result   = $module->sendPasswordResetEmail($_POST["toReset"]);
            if ( !$result ) {
                $icon  = "error";
                $title = "Trouble sending password reset email.";
            } else {
                $icon  = "success";
                $title = "Successfully reset password for participant.";
            }

            // UPDATE THE PARTICIPANT'S NAME
        } else if ( !empty($_POST["toChangeName"]) ) {
            $function             = "update participant's name";
            $rcpro_participant_id = intval($_POST["toChangeName"]);
            $newFirstName         = trim($_POST["newFirstName"]);
            $newLastName          = trim($_POST["newLastName"]);
            // Check that names are valid
            if ( $newFirstName === "" || $newLastName === "" ) {
                $title = "You need to provide valid first and last names.";
                $icon  = "error";
            }

            // Try to change name
            else {
                $result = $module->PARTICIPANT->changeName($rcpro_participant_id, $newFirstName, $newLastName);
                if ( !$result ) {
                    $title = "Trouble updating participant's name.";
                    $icon  = "error";
                } else {
                    $title = "Successfully updated participant's name.";
                    $icon  = "success";
                }
            }

            // CHANGE THE PARTICIPANT'S EMAIL ADDRESS
        } else if ( !empty($_POST["toChangeEmail"]) ) {
            $function = "change participant's email address";
            $newEmail = $_POST["newEmail"];
            if ( $module->PARTICIPANT->checkEmailExists($newEmail) ) {
                $icon  = "error";
                $title = "The provided email address is already associated with a REDCapPRO account.";
            } else {
                $result = $module->PARTICIPANT->changeEmailAddress(intval($_POST["toChangeEmail"]), $newEmail);
                if ( !$result ) {
                    $icon  = "error";
                    $title = "Trouble changing participant's email address.";
                } else {
                    $icon  = "success";
                    $title = "Successfully changed participant's email address.";
                }
            }

            // DEACTIVATE OR REACTIVATE A PARTICIPANT
        } else if ( !empty($_POST["toUpdateActivity"]) ) {
            $toUpdate   = intval($_POST["toUpdateActivity"]);
            $function   = "update participant's active status";
            $reactivate = $_POST["statusAction"] === "reactivate";
            if ( !$module->PARTICIPANT->checkParticipantExists($toUpdate) ) {
                $icon  = "error";
                $title = "The provided participant does not exist in the system.";
            } else {
                if ( $reactivate ) {
                    $result = $module->PARTICIPANT->reactivateParticipant($toUpdate);
                } else {
                    $result = $module->PARTICIPANT->deactivateParticipant($toUpdate);
                }
                if ( !$result ) {
                    $verb  = $reactivate ? "reactivating" : "deactivating";
                    $icon  = "error";
                    $title = "Trouble $verb this participant.";
                } else {
                    $verb  = $reactivate ? "reactivated" : "deactivated";
                    $icon  = "success";
                    $title = "Successfully $verb this participant.";
                }
            }
        }
    } catch ( \Exception $e ) {
        $icon  = "error";
        $title = "Failed to ${function}.";
        $module->logError("Error attempting to ${function}", $e);
    }
}
$module->initializeJavascriptModuleObject();
// Get array of participants
$participants = $module->PARTICIPANT->getAllParticipants();

?>
<script src="<?= $module->getUrl("lib/sweetalert/sweetalert2.all.min.js"); ?>"></script>
<link rel="stylesheet" type="text/css" href="<?= $module->getUrl("src/css/rcpro_cc.php") ?>">

<?php if ( $_SERVER["REQUEST_METHOD"] == "POST" ) { ?>
    <script>
        Swal.fire({
            icon: "<?= $icon ?>",
            title: "<?= $title ?>",
            confirmButtonColor: "#900000"
        });
    </script>
<?php } ?>

<div id="loading-container" class="loader-container">
    <div id="loading" class="loader"></div>
</div>
<div class="participantsContainer wrapper" style="display: none;">
    <h2>Manage Participants</h2>
    <p>All participants across studies</p>
    <form class="dataTableParentHidden participants-form outer_container" id="participants-form"
        style="min-width:50vw !important;" action="<?= $module->getUrl("src/cc_participants.php"); ?>" method="POST"
        enctype="multipart/form-data" target="_self">
        <?php if ( count($participants) === 0 || empty($participants) ) { ?>
            <div>
                <p>No participants have been registered in this system</p>
            </div>
        <?php } else { ?>
            <div class="form-group">
                <table class="table" id="RCPRO_TABLE">
                    <caption>REDCapPRO Participants</caption>
                    <thead>
                        <tr>
                            <th id="uid">User_ID</th>
                            <th id="uname" class="dt-center">Username</th>
                            <th id="active" class="dt-center">Active</th>
                            <th id="pw_set" class="dt-center">Password Set</th>
                            <th id="fname" class="dt-center">First Name</th>
                            <th id="lname" class="dt-center">Last Name</th>
                            <th id="email">Email</th>
                            <th id="projects" class="dt-center">Enrolled Projects</th>
                            <th id="actions" class="dt-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
            <input type="hidden" id="toReset" name="toReset">
            <input type="hidden" id="toChangeEmail" name="toChangeEmail">
            <input type="hidden" id="newEmail" name="newEmail">
            <input type="hidden" id="toChangeName" name="toChangeName">
            <input type="hidden" id="newFirstName" name="newFirstName">
            <input type="hidden" id="newLastName" name="newLastName">
            <input type="hidden" id="toDisenroll" name="toDisenroll">
            <input type="hidden" id="toUpdateActivity" name="toUpdateActivity">
            <input type="hidden" id="statusAction" name="statusAction">
            <input type="hidden" name="redcap_csrf_token" value="<?= $module->framework->getCSRFToken() ?>">
        <?php } ?>
    </form>
</div>
<script>
    const RCPRO_module = <?= $module->getJavascriptModuleObjectName() ?>;
    (function ($, window, document) {

        RCPRO_module.changeParticipantName = function (rcpro_participant_id, fname, lname) {
            Swal.fire({
                title: 'Enter the new name for this participant',
                html: `<input id="swal-fname" class="swal2-input" value="${fname}"><input id="swal-lname" class="swal2-input" value="${lname}">`,
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
            }).then(function (result) {
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
                        $("#toChangeName").val(rcpro_participant_id);
                        $("#newFirstName").val(result.value.fname);
                        $("#newLastName").val(result.value.lname);
                        $("#participants-form").submit();
                    }
                }
            });
        }

        RCPRO_module.changeEmailAddress = function (rcpro_participant_id, fname, lname, email) {
            Swal.fire({
                title: `Enter the new email address for ${fname} ${lname}`,
                input: "email",
                inputPlaceholder: email,
                confirmButtonText: "Change Email",
                showCancelButton: true,
                confirmButtonColor: "<?= $module::$COLORS["primary"] ?>",
                allowEnterKey: false
            }).then((result) => {
                if (result.isConfirmed) {
                    clearForm();
                    $("#toChangeEmail").val(rcpro_participant_id);
                    $("#newEmail").val(result.value);
                    $("#participants-form").submit();
                }
            });
        }

        RCPRO_module.toggleActiveStatus = function (rcpro_participant_id, activeStatus, fname, lname) {
            Swal.fire({
                title: `Are you sure you want to ${activeStatus ? "deactivate" : "reactivate"} ${fname} ${lname}?`,
                confirmButtonText: activeStatus ? "Deactivate" : "Reactivate",
                icon: "warning",
                iconColor: "<?= $module::$COLORS["primary"] ?>",
                showCancelButton: true,
                confirmButtonColor: "<?= $module::$COLORS["primary"] ?>",
                allowEnterKey: false
            }).then((result) => {
                if (result.isConfirmed) {
                    clearForm();
                    $("#toUpdateActivity").val(rcpro_participant_id);
                    $("#statusAction").val(activeStatus ? "deactivate" : "reactivate");
                    $("#participants-form").submit();
                }
            });
        }

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
                $("#toUpdateActivity").val("");
                $("#statusAction").val("");
            }

            let dataTable = $('#RCPRO_TABLE').DataTable({
                dom: 'lBfrtip',
                stateSave: true,
                deferRender: true,
                ajax: function (data, callback, settings) {
                    RCPRO_module.ajax('getParticipantsCC', {})
                        .then(response => {
                            callback({ data: response });
                        })
                        .catch(error => {
                            console.error(error);
                            callback({ data: [] });
                        });
                },
                columns: [
                    {
                        title: 'User_ID',
                        className: "dt-center rcpro_participant_link",
                        data: 'rcpro_participant_id',
                        createdCell: function (td, cellData, rowData, row, col) {
                            const info = rowData.info;
                            let allData = '<div style=\'display: block; text-align:left;\'><ul>';
                            for (const title in info) {
                                const value = info[title];
                                if (value != "") {
                                    allData += `<li><strong>${title}</strong>: ${value}</li>`;
                                }
                            }
                            allData += "</ul></div>";
                            $(td).on('click', function () {
                                Swal.fire({
                                    confirmButtonColor: '<?= $module::$COLORS['primary'] ?>',
                                    allowEnterKey: false,
                                    html: allData
                                });
                            });
                        }
                    },
                    {
                        title: 'Username',
                        className: "dt-center",
                        data: 'username'
                    },
                    {
                        title: 'Active',
                        className: "dt-center",
                        data: function (row, type, set, meta) {
                            if (type === 'display') {
                                const isActive = row.isActive;
                                const title = isActive ? "Active" : "Inactive";
                                const color = isActive ? '<?= $module::$COLORS['green'] ?>' : '<?= $module::$COLORS['ban'] ?>';
                                return `<i data-filterValue="${isActive}" title="${title}" class="fas ${isActive ? 'fa-check' : 'fa-ban'}" style="color:${color}"></i>`;
                            } else {
                                return row.isActive;
                            }
                        }
                    },
                    {
                        title: 'Password Set',
                        className: "dt-center",
                        data: function (row, type, set, meta) {
                            if (type === 'display') {
                                const pw_set = row.password_set;
                                const title = pw_set ? "Password Set" : "Password Not Set";
                                const color = pw_set ? '<?= $module::$COLORS['green'] ?>' : '<?= $module::$COLORS['ban'] ?>';
                                return `<i data-filterValue="${pw_set}" title="${title}" class="fas ${pw_set ? 'fa-check-circle' : 'fa-fw'}" style="margin-left:2px;margin-right:2px;color:${color};"></i>`;
                            } else {
                                return row.password_set;
                            }
                        }
                    },
                    {
                        title: 'First Name',
                        className: "dt-center",
                        data: 'fname'
                    },
                    {
                        title: 'Last Name',
                        className: "dt-center",
                        data: 'lname'
                    },
                    {
                        title: 'Email',
                        data: 'email'
                    },
                    {
                        title: 'Enrolled Projects',
                        className: "dt-center",
                        data: function (row, type, set, meta) {
                            if (type === 'display') {
                                const projects = row.projects_array;
                                let result = "";
                                for (const project of projects) {
                                    if (project.active == 1) {
                                        const pid = project.redcap_pid.trim();
                                        const url = `<?= $module->getUrl("src/manage.php?pid=") ?>${pid}`;
                                        result += `<div><a class="rcpro_project_link" title="Active" href="${url}">PID ${pid}</a></div>`;
                                    }
                                }
                                return result;
                            } else {
                                return row.projects_array;
                            }
                        }
                    },
                    {
                        title: 'Actions',
                        className: "dt-center",
                        data: function (row, type, set, meta) {
                            if (type === 'display') {
                                let result = '<div style="display:flex; justify-content:center; align-items:center;">';
                                result += `<a onclick="(function(){clearForm();$('#toReset').val('${row.rcpro_participant_id}');$('#participants-form').submit();})();" title="Reset Password" style="cursor: pointer; padding: 0 5px;"><i class="fas fa-key"></i></a>`;
                                result += `<a onclick="(function(){RCPRO_module.changeParticipantName(\'${row.rcpro_participant_id}\', \'${row.fname}\', \'${row.lname}\')})();"
                                    title="Update Participant Name" style="cursor: pointer; padding: 0 5px;"><i class="fas fa-user"></i></a>`;
                                result += `<a onclick="(function(){RCPRO_module.changeEmailAddress(\'${row.rcpro_participant_id}\', \'${row.fname}\', \'${row.lname}\', \'${row.email}\')})();"
                                    title="Change Email Address" style="cursor: pointer; padding: 0 5px;"><i class="fas fa-envelope"></i></a>`;
                                result += `<a onclick="(function(){RCPRO_module.toggleActiveStatus(\'${row.rcpro_participant_id}\', ${row.isActive}, \'${row.fname}\', \'${row.lname}\')})();"
                                    title="${row.isActive ? "Deactivate" : "Reactivate"} Participant" style="cursor: pointer; padding: 0 5px; color:${row.isActive ? "<?= $module::$COLORS["ban"] ?>" : "<?= $module::$COLORS["green"] ?>"}"><i class="fas ${row.isActive ? "fa-user-slash" : "fa-user-plus"} "></i></a>`;
                                result += '</div>';
                                return result;
                            } else {
                                return "";
                            }
                        }
                    }
                ],
                stateSaveCallback: function (settings, data) {
                    localStorage.setItem('DataTables_ccpart_' + settings.sInstance, JSON.stringify(data))
                },
                stateLoadCallback: function (settings) {
                    return JSON.parse(localStorage.getItem('DataTables_ccpart_' + settings.sInstance))
                },
                scrollY: '50vh',
                sScrollX: '100%',
                scrollCollapse: true,
                pageLength: 100,
            });
            $('#participants-form').removeClass('dataTableParentHidden');
            $('#loading-container').hide();
            $('.wrapper').show();
            dataTable.columns.adjust().draw();
        });
    }(window.jQuery, window, document));
</script>
<?php
require_once APP_PATH_DOCROOT . 'ControlCenter/footer.php';
?>