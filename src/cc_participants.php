<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

if ( !$module->framework->isSuperUser() ) {
    exit();
}
echo '<!DOCTYPE html><html lang="en">';
$module->includeFont();
$language = new Language($module);
$language->handleSystemLanguageChangeRequest();

// Check for errors
if ( isset($_GET["error"]) ) {
    ?>
    <script>
        Swal.fire({
            icon: "error",
            title: "<?= $module->tt("cc_error") ?>",
            text: "<?= $module->tt("cc_error_general") ?>",
            showConfirmButton: false
        });
    </script>
    <?php
}

require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';
$ui = new UI($module);
$ui->ShowControlCenterHeader("Participants");
?>
<link href="https://cdn.datatables.net/v/bs5/jszip-3.10.1/dt-2.1.3/b-3.1.1/b-colvis-3.1.1/b-html5-3.1.1/sr-1.4.1/datatables.min.css" rel="stylesheet">
<script src="https://cdn.datatables.net/v/bs5/jszip-3.10.1/dt-2.1.3/b-3.1.1/b-colvis-3.1.1/b-html5-3.1.1/sr-1.4.1/datatables.min.js" integrity="sha512-tQIUNMCB0+K4nlOn4FRg/hco5B1sf4yWGpnj+V2MxRSDSVNPD84yzoWogPL58QRlluuXkjvuDD5bzCUTMi6MDw==" crossorigin="anonymous"></script>
<?php 
$participantHelper = new ParticipantHelper($module);

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
                $title = $module->tt("cc_participants_pw_reset_error");
            } else {
                $icon  = "success";
                $title = $module->tt("cc_participants_pw_reset_success");
            }

            // UPDATE THE PARTICIPANT'S NAME
        } else if ( !empty($_POST["toChangeName"]) ) {
            $function             = "update participant's name";
            $rcpro_participant_id = intval($_POST["toChangeName"]);
            $newFirstName         = trim($_POST["newFirstName"]);
            $newLastName          = trim($_POST["newLastName"]);
            // Check that names are valid
            if ( $newFirstName === "" || $newLastName === "" ) {
                $title = $module->tt("cc_participants_invalid_name");
                $icon  = "error";
            }

            // Try to change name
            else {
                $result = $participantHelper->changeName($rcpro_participant_id, $newFirstName, $newLastName);
                if ( !$result ) {
                    $title = $module->tt("cc_participants_name_change_error");
                    $icon  = "error";
                } else {
                    $title = $module->tt("cc_participants_name_change_success");
                    $icon  = "success";
                }
            }

            // CHANGE THE PARTICIPANT'S EMAIL ADDRESS
        } else if ( !empty($_POST["toChangeEmail"]) ) {
            $function = "change participant's email address";
            $newEmail = $_POST["newEmail"];
            if ( $participantHelper->checkEmailExists($newEmail) ) {
                $icon  = "error";
                $title = $module->tt("cc_participants_email_exists");
            } else {
                $result = $participantHelper->changeEmailAddress(intval($_POST["toChangeEmail"]), $newEmail);
                if ( !$result ) {
                    $icon  = "error";
                    $title = $module->tt("cc_participants_email_change_error");
                } else {
                    $icon  = "success";
                    $title = $module->tt("cc_participants_email_change_success");
                }
            }

            // DEACTIVATE OR REACTIVATE A PARTICIPANT
        } else if ( !empty($_POST["toUpdateActivity"]) ) {
            $toUpdate   = intval($_POST["toUpdateActivity"]);
            $function   = "update participant's active status";
            $reactivate = $_POST["statusAction"] === "reactivate";
            if ( !$participantHelper->checkParticipantExists($toUpdate) ) {
                $icon  = "error";
                $title = $module->tt("cc_participants_not_exist");
            } else {
                if ( $reactivate ) {
                    $result = $participantHelper->reactivateParticipant($toUpdate);
                } else {
                    $result = $participantHelper->deactivateParticipant($toUpdate);
                }
                if ( !$result ) {
                    $icon  = "error";
                    $tt  = $reactivate ? "cc_participants_reactivating_error" : "cc_participants_deactivating_error";
                    $title = $module->tt($tt);
                } else {
                    $icon  = "success";
                    $tt  = $reactivate ? "cc_participants_reactivating_success" : "cc_participants_deactivating_success";
                    $title = $module->tt($tt);
                }
            }
        }
    } catch ( \Exception $e ) {
        $icon  = "error";
        $title = $module->tt("cc_participants_error_general", $function);
        $module->logError("Error attempting to {$function}", $e);
    }
}
$module->initializeJavascriptModuleObject();
$module->tt_transferToJavascriptModuleObject();
// Get array of participants
$participants = $participantHelper->getAllParticipants();

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
    <h2><?= $module->tt("cc_participants_title") ?></h2>
    <p><?= $module->tt("cc_participants_subtitle") ?></p>
    <form class="dataTableParentHidden participants-form outer_container" id="participants-form"
        style="min-width:50vw !important;" action="<?= $module->getUrl("src/cc_participants.php"); ?>" method="POST"
        enctype="multipart/form-data" target="_self">
        <?php if ( count($participants) === 0 || empty($participants) ) { ?>
            <div>
                <p><?= $module->tt("cc_participants_no_participants") ?></p>
            </div>
        <?php } else { ?>
            <div class="form-group">
                <table class="table" id="RCPRO_TABLE">
                    <caption><?= $module->tt("cc_participants_table_caption") ?></caption>
                    <thead>
                        <tr>
                            <th id="uid"><?= $module->tt("cc_participants_table_uid") ?></th>
                            <th id="uname" class="dt-center"><?= $module->tt("cc_participants_table_username") ?></th>
                            <th id="active" class="dt-center"><?= $module->tt("cc_participants_table_active") ?></th>
                            <th id="pw_set" class="dt-center"><?= $module->tt("cc_participants_table_pw_set") ?></th>
                            <th id="fname" class="dt-center"><?= $module->tt("cc_participants_table_fname") ?></th>
                            <th id="lname" class="dt-center"><?= $module->tt("cc_participants_table_lname") ?></th>
                            <th id="email"><?= $module->tt("cc_participants_table_email") ?></th>
                            <th id="projects" class="dt-center"><?= $module->tt("cc_participants_table_projects") ?></th>
                            <th id="actions" class="dt-center"><?= $module->tt("cc_participants_table_actions") ?></th>
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
                title: RCPRO_module.tt("cc_participants_enter_new_name"),
                html: `<input id="swal-fname" class="swal2-input" value="${fname}"><input id="swal-lname" class="swal2-input" value="${lname}">`,
                confirmButtonText: RCPRO_module.tt("cc_participants_change_name"),
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
                            title: RCPRO_module.tt("cc_participants_must_provide_name"),
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
                title: RCPRO_module.tt("cc_participants_enter_new_email", [fname, lname]),
                input: "email",
                inputPlaceholder: email,
                confirmButtonText: RCPRO_module.tt("cc_participants_change_email"),
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
                title: RCPRO_module.tt("cc_participants_toggle_active_status", [activeStatus ? "deactivate" : "reactivate", fname, lname]),
                confirmButtonText: RCPRO_module.tt(activeStatus ? "cc_participants_deactivate" : "cc_participants_reactivate"),
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
                stateSave: true,
                deferRender: true,
                processing: true,
                ajax: function (data, callback, settings) {
                    t0 = performance.now();
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
                        title: '<?= $module->tt("cc_participants_table_uid") ?>',
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
                        title: '<?= $module->tt("cc_participants_table_username") ?>',
                        className: "dt-center",
                        data: 'username'
                    },
                    {
                        title: '<?= $module->tt("cc_participants_table_active") ?>',
                        className: "dt-center",
                        data: function (row, type, set, meta) {
                            if (type === 'display') {
                                const isActive = row.isActive;
                                const title = RCPRO_module.tt(isActive ? "cc_participants_active" : "cc_participants_inactive");
                                const color = isActive ? '<?= $module::$COLORS['green'] ?>' : '<?= $module::$COLORS['ban'] ?>';
                                return `<i data-filterValue="${isActive}" title="${title}" class="fas ${isActive ? 'fa-check' : 'fa-ban'}" style="color:${color}"></i>`;
                            } else {
                                return row.isActive;
                            }
                        }
                    },
                    {
                        title: '<?= $module->tt("cc_participants_table_pw_set") ?>',
                        className: "dt-center",
                        data: function (row, type, set, meta) {
                            if (type === 'display') {
                                const pw_set = row.password_set;
                                const title = RCPRO_module.tt(pw_set ? "cc_participants_password_set" : "cc_participants_password_not_set");
                                const color = pw_set ? '<?= $module::$COLORS['green'] ?>' : '<?= $module::$COLORS['ban'] ?>';
                                return `<i data-filterValue="${pw_set}" title="${title}" class="fas ${pw_set ? 'fa-check-circle' : 'fa-fw'}" style="margin-left:2px;margin-right:2px;color:${color};"></i>`;
                            } else {
                                return row.password_set;
                            }
                        }
                    },
                    {
                        title: '<?= $module->tt("cc_participants_table_fname") ?>',
                        className: "dt-center",
                        data: 'fname'
                    },
                    {
                        title: '<?= $module->tt("cc_participants_table_lname") ?>',
                        className: "dt-center",
                        data: 'lname'
                    },
                    {
                        title: '<?= $module->tt("cc_participants_table_email") ?>',
                        data: 'email'
                    },
                    {
                        title: '<?= $module->tt("cc_participants_table_projects") ?>',
                        className: "dt-center",
                        data: function (row, type, set, meta) {
                            if (type === 'display') {
                                const projects = row.projects_array;
                                let result = "";
                                for (const project of projects) {
                                    if (project.active == 1) {
                                        const pid = project.redcap_pid.trim();
                                        const url = `<?= $module->getUrl("src/manage.php?pid=") ?>${pid}`;
                                        result += `<div><a class="rcpro_project_link" title="<?= $module->tt("cc_participants_active") ?>" href="${url}"><?= $module->tt("cc_pid") ?> ${pid}</a></div>`;
                                    }
                                }
                                return result;
                            } else {
                                return row.projects_array;
                            }
                        }
                    },
                    {
                        title: '<?= $module->tt("cc_participants_table_actions") ?>',
                        className: "dt-center",
                        data: function (row, type, set, meta) {
                            if (type === 'display') {
                                let result = '<div style="display:flex; justify-content:center; align-items:center;">';
                                result += `<a onclick="(function(){clearForm();$('#toReset').val('${row.rcpro_participant_id}');$('#participants-form').submit();})();" title="<?= $module->tt("cc_participants_reset_password") ?>" style="cursor: pointer; padding: 0 5px;"><i class="fas fa-key"></i></a>`;
                                result += `<a onclick="(function(){RCPRO_module.changeParticipantName(\'${row.rcpro_participant_id}\', \'${row.fname}\', \'${row.lname}\')})();"
                                    title="<?= $module->tt("cc_participants_update_participant_name") ?>" style="cursor: pointer; padding: 0 5px;"><i class="fas fa-user"></i></a>`;
                                result += `<a onclick="(function(){RCPRO_module.changeEmailAddress(\'${row.rcpro_participant_id}\', \'${row.fname}\', \'${row.lname}\', \'${row.email}\')})();"
                                    title="<?= $module->tt("cc_participants_change_email_address") ?>" style="cursor: pointer; padding: 0 5px;"><i class="fas fa-envelope"></i></a>`;
                                result += `<a onclick="(function(){RCPRO_module.toggleActiveStatus(\'${row.rcpro_participant_id}\', ${row.isActive}, \'${row.fname}\', \'${row.lname}\')})();"
                                    title="${RCPRO_module.tt(row.isActive ? "cc_participants_deactivate_participant" : "cc_participants_reactivate_participant")}" style="cursor: pointer; padding: 0 5px; color:${row.isActive ? "<?= $module::$COLORS["ban"] ?>" : "<?= $module::$COLORS["green"] ?>"}"><i class="fas ${row.isActive ? "fa-user-slash" : "fa-user-plus"} "></i></a>`;
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
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: RCPRO_module.tt('cc_dt_search_placeholder'),
                    infoFiltered: " - " + RCPRO_module.tt('cc_dt_info_filtered', '_MAX_'),
                    emptyTable: RCPRO_module.tt('cc_dt_empty_table'),
                    info: RCPRO_module.tt('cc_dt_info', { start: '_START_', end: '_END_', total: '_TOTAL_' }),
                    infoEmpty: RCPRO_module.tt('cc_dt_info_empty'),
                    lengthMenu: RCPRO_module.tt('cc_dt_length_menu', '_MENU_'),
                    loadingRecords: RCPRO_module.tt('cc_dt_loading_records'),
                    zeroRecords: RCPRO_module.tt('cc_dt_zero_records'),
                    decimal: RCPRO_module.tt('cc_dt_decimal'),
                    thousands: RCPRO_module.tt('cc_dt_thousands'),
                    select: {
                        rows: {
                            _: RCPRO_module.tt('cc_dt_select_rows_other'),
                            0: RCPRO_module.tt('cc_dt_select_rows_zero'),
                            1: RCPRO_module.tt('cc_dt_select_rows_one')
                        }
                    },
                    paginate: {
                        first: RCPRO_module.tt('cc_dt_paginate_first'),
                        last: RCPRO_module.tt('cc_dt_paginate_last'),
                        next: RCPRO_module.tt('cc_dt_paginate_next'),
                        previous: RCPRO_module.tt('cc_dt_paginate_previous')
                    },
                    aria: {
                        sortAscending: RCPRO_module.tt('cc_dt_aria_sort_ascending'),
                        sortDescending: RCPRO_module.tt('cc_dt_aria_sort_descending')
                    }
                }
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