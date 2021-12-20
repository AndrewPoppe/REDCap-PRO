<?php

namespace YaleREDCap\REDCapPRO;

function createProjectsCell(array $projects)
{
    global $module;
    $result = "<td  class='dt-center'>";
    foreach ($projects as $project) {
        if ($project["active"] == 1) {
            $link_class = 'rcpro_project_link';
            $title = "Active";

            $pid = trim($project["redcap_pid"]);
            $url = $module->getUrl("src/manage.php?pid=${pid}");
            $result .= "<div><a class='${link_class}' title='${title}' href='${url}'>PID ${pid}</a></div>";
        }
    }
    $result .= "</td>";
    return $result;
}


?>
<?php
if (!SUPER_USER) {
    return;
}
require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';
$module->UI->ShowControlCenterHeader("Participants");

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate token
    if (!$module->AUTH->validate_csrf_token($_POST['token'])) {
        header("location:" . $module->getUrl("src/cc_participants.php"));
        return;
    }

    // Log submission
    $module->logForm("Submitted Control Center Participants Form", $_POST);

    try {
        $function = NULL;
        $rcpro_participant_id = intval(coalesce_string(
            $_POST["toReset"],
            $_POST["toChangeName"],
            $_POST["toChangeEmail"],
            $_POST["toUpdateActivity"]
        ));
        $participant = new Participant($module, ["rcpro_participant_id" => $rcpro_participant_id]);

        // SEND A PASSWORD RESET EMAIL
        if (!empty($_POST["toReset"])) {
            $function = "send password reset email";
            $result = $module->sendPasswordResetEmail($rcpro_participant_id);
            if (!$result) {
                $icon = "error";
                $title = "Trouble sending password reset email.";
            } else {
                $icon = "success";
                $title = "Successfully reset password for participant.";
            }

            // UPDATE THE PARTICIPANT'S NAME
        } else if (!empty($_POST["toChangeName"])) {
            $function = "update participant's name";
            $newFirstName = trim($_POST["newFirstName"]);
            $newLastName = trim($_POST["newLastName"]);
            // Check that names are valid
            if ($newFirstName === "" || $newLastName === "") {
                $title = "You need to provide valid first and last names.";
                $icon = "error";
            }

            // Try to change name
            else {
                $result = $participant->changeName($newFirstName, $newLastName);
                if (!$result) {
                    $title = "Trouble updating participant's name.";
                    $icon = "error";
                } else {
                    $title = "Successfully updated participant's name.";
                    $icon = "success";
                }
            }

            // CHANGE THE PARTICIPANT'S EMAIL ADDRESS
        } else if (!empty($_POST["toChangeEmail"])) {
            $function = "change participant's email address";
            $newEmail = $_POST["newEmail"];
            if ($module->PARTICIPANT_HELPER->checkEmailExists($newEmail)) {
                $icon = "error";
                $title = "The provided email address is already associated with a REDCapPRO account.";
            } else {
                $result = $participant->changeEmailAddress($newEmail);
                if (!$result) {
                    $icon = "error";
                    $title = "Trouble changing participant's email address.";
                } else {
                    $icon = "success";
                    $title = "Successfully changed participant's email address.";
                }
            }

            // DEACTIVATE OR REACTIVATE A PARTICIPANT
        } else if (!empty($_POST["toUpdateActivity"])) {
            $function = "update participant's active status";
            $reactivate = $_POST["statusAction"] === "reactivate";
            if (!$module->PARTICIPANT_HELPER->checkParticipantExists($rcpro_participant_id)) {
                $icon = "error";
                $title = "The provided participant does not exist in the system.";
            } else {
                if ($reactivate) {
                    $result = $participant->reactivate();
                } else {
                    $result = $participant->deactivate();
                }
                if (!$result) {
                    $verb = $reactivate ? "reactivating" : "deactivating";
                    $icon = "error";
                    $title = "Trouble $verb this participant.";
                } else {
                    $verb = $reactivate ? "reactivated" : "deactivated";
                    $icon = "success";
                    $title = "Successfully $verb this participant.";
                }
            }
        }
    } catch (\Exception $e) {
        $icon = "error";
        $title = "Failed to ${function}.";
        $module->logError("Error attempting to ${function}", $e);
    }
}

// set csrf token
$module->AUTH->set_csrf_token();

// Get array of participants
$participants = $module->PARTICIPANT_HELPER->getAllParticipants();

?>
<script src="<?= $module->getUrl("lib/sweetalert/sweetalert2.all.min.js"); ?>"></script>
<link rel="stylesheet" type="text/css" href="<?= $module->getUrl("src/css/rcpro_cc.php") ?>">

<?php if ($_SERVER["REQUEST_METHOD"] == "POST") { ?>
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
    <form class="dataTableParentHidden participants-form outer_container" id="participants-form" style="min-width:50vw !important;" action="<?= $module->getUrl("src/cc_participants.php"); ?>" method="POST" enctype="multipart/form-data" target="_self">
        <?php if (count($participants) === 0 || empty($participants)) { ?>
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
                        <?php foreach ($participants as $participant) {
                            $username_clean       = \REDCap::escapeHtml($participant->rcpro_username);
                            $password_set         = $participant->isPasswordSet();
                            $name                 = $participant->getName();
                            $fname_clean          = \REDCap::escapeHtml($name["fname"]);
                            $lname_clean          = \REDCap::escapeHtml($name["lname"]);
                            $email_clean          = \REDCap::escapeHtml($participant->email);
                            $projects_array       = $participant->getEnrolledProjects();
                            $info                 = $participant->getInfo();
                            $allData              = "<div style='display: block; text-align:left;'><ul>";
                            foreach ($info as $title => $value) {
                                $value_clean = \REDCap::escapeHtml($value);
                                $title_clean = \REDCap::escapeHtml($title);
                                if ($value_clean != "") {
                                    $allData .= "<li><strong>${title_clean}</strong>: ${value_clean}</li>";
                                }
                            }
                            $allData .= "</ul></div>";
                            $allData = str_replace("\n", "\\n", addslashes($allData));
                            $onclick = <<<EOL
                                (function() {
                                    Swal.fire({
                                        confirmButtonColor:'#900000', 
                                        allowEnterKey: false, 
                                        html:'$allData'
                                    })
                                })();
                                EOL;
                            $isActive = $participant->isActive();
                        ?>
                            <tr>
                                <td class="rcpro_participant_link" onclick="<?= $onclick ?>"><?= $participant["log_id"] ?></td>
                                <td class="dt-center"><?= $username_clean ?></td>
                                <td class="dt-center"><i data-filterValue="<?= $isActive ?>" title='<?= $isActive ? "Active" : "Inactive" ?>' class='fas <?= $isActive ? "fa-check" : "fa-ban" ?>' style='color:<?= $isActive ? $module::$COLORS["green"] : $module::$COLORS["ban"] ?>'></td>
                                <td class="dt-center"><i data-filterValue="<?= $password_set ?>" title='Password Set' class='fas <?= ($password_set ? "fa-check-circle" : "fa-fw") ?>' style='margin-left:2px;margin-right:2px;color:<?= $module::$COLORS["green"] ?>;'></i></td>
                                <td class="dt-center"><?= $fname_clean ?></td>
                                <td class="dt-center"><?= $lname_clean ?></td>
                                <td><?= $email_clean ?></td>
                                <?= createProjectsCell($projects_array); ?>
                                <td class="dt-center">
                                    <div style="display:flex; justify-content:center; align-items:center;">
                                        <a onclick='(function(){
                                            clearForm();
                                            $("#toReset").val("<?= $participant["log_id"] ?>");
                                            $("#participants-form").submit();
                                            })();' title="Reset Password" style="cursor:pointer; padding:0 5px;">
                                            <i class="fas fa-key"></i>
                                        </a>
                                        <a onclick='(function(){
                                            Swal.fire({
                                                    title: "Enter the new name for this participant", 
                                                    html: `<input id="swal-fname" class="swal2-input" value="<?= $fname_clean ?>"><input id="swal-lname" class="swal2-input" value="<?= $lname_clean ?>">`,
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
                                                            $("#toChangeName").val("<?= $participant["log_id"] ?>");
                                                            $("#newFirstName").val(result.value.fname); 
                                                            $("#newLastName").val(result.value.lname); 
                                                            $("#participants-form").submit();
                                                        }
                                                    }
                                                });
                                            })();' title="Update Participant Name" style="cursor:pointer; padding:0 5px;">
                                            <i class="fas fa-user"></i>
                                        </a>
                                        <a onclick='(function(){
                                            Swal.fire({
                                                    title: "Enter the new email address for <?= "${fname_clean} ${lname_clean}" ?>",
                                                    input: "email",
                                                    inputPlaceholder: "<?= $email_clean ?>",
                                                    confirmButtonText: "Change Email",
                                                    showCancelButton: true,
                                                    confirmButtonColor: "<?= $module::$COLORS["primary"] ?>",
                                                    allowEnterKey: false
                                                }).then((result) => {
                                                    if (result.isConfirmed) {
                                                        clearForm();
                                                        $("#toChangeEmail").val("<?= $participant["log_id"] ?>");
                                                        $("#newEmail").val(result.value); 
                                                        $("#participants-form").submit();
                                                    }
                                                });
                                            })();' title="Change Email Address" style="cursor:pointer; padding:0 5px;">
                                            <i class="fas fa-envelope"></i>
                                        </a>
                                        <a onclick='(function(){
                                            Swal.fire({
                                                    title: "Are you sure you want to <?= ($isActive ? "deactivate" : "reactivate") . " ${fname_clean} ${lname_clean}?" ?> ",
                                                    confirmButtonText: "<?= $isActive ? "Deactivate" : "Reactivate" ?> ",
                                                    icon: "warning",
                                                    iconColor: "<?= $module::$COLORS["primary"] ?>",
                                                    showCancelButton: true,
                                                    confirmButtonColor: "<?= $module::$COLORS["primary"] ?>",
                                                    allowEnterKey: false
                                                }).then((result) => {
                                                    if (result.isConfirmed) {
                                                        clearForm();
                                                        $("#toUpdateActivity").val("<?= $participant["log_id"] ?>");
                                                        $("#statusAction").val("<?= $isActive ? "deactivate" : "reactivate" ?>");
                                                        $("#participants-form").submit();
                                                    }
                                                });
                                            })();' title="<?= $isActive ? "Deactivate" : "Reactivate" ?> Participant" style="cursor:pointer; padding:0 5px; color:<?= $isActive ? $module::$COLORS["ban"] : $module::$COLORS["green"] ?>">
                                            <i class="fas <?= $isActive ? "fa-user-slash" : "fa-user-plus" ?>"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
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
            <input type="hidden" name="token" value="<?= $module->AUTH->get_csrf_token(); ?>">
        <?php } ?>
    </form>
</div>
<script>
    (function($, window, document) {
        $(document).ready(function() {

            // Function for resetting manage-form values
            window.clearForm = function() {
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
                stateSaveCallback: function(settings, data) {
                    localStorage.setItem('DataTables_ccpart_' + settings.sInstance, JSON.stringify(data))
                },
                stateLoadCallback: function(settings) {
                    return JSON.parse(localStorage.getItem('DataTables_ccpart_' + settings.sInstance))
                },
                scrollY: '50vh',
                sScrollX: '100%',
                scrollCollapse: true,
                pageLength: 100,
                columnDefs: [{
                        "targets": 2,
                        "data": function(row, type, val, meta) {
                            if (type === "set") {
                                row.active = val;
                                row.active_display = val;
                                row.active_filter = val;
                                return;
                            } else if (type === "filter" || type === "sort") {
                                return $(row.active_filter).data().filtervalue;
                            }
                            return row.active;
                        }
                    },
                    {
                        "targets": 3,
                        "data": function(row, type, val, meta) {
                            if (type === "set") {
                                row.pw_set = val;
                                row.pw_set_display = val;
                                row.pw_set_filter = val;
                                return;
                            } else if (type === "filter" || type === "sort") {
                                return $(row.pw_set_filter).data().filtervalue;
                            }
                            return row.pw_set;
                        }
                    }
                ]
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