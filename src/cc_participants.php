<?php


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

function getParticipantInfo($rcpro_participant_id)
{
    global $module;
    $SQL = "SELECT log_id AS 'User_ID', rcpro_username AS Username, timestamp AS 'Registered At', redcap_user AS 'Registered By' WHERE log_id = ?";
    $result_obj = $module->queryLogs($SQL, [$rcpro_participant_id]);
    return $result_obj->fetch_assoc();
}

?>
<!DOCTYPE html>
<?php
if (!SUPER_USER) {
    return;
}
require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';
$module::$UI->ShowControlCenterHeader("Participants");

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
    } catch (\Exception $e) {
        $icon = "error";
        $title = "Failed to ${function}.";
        $module->logError("Error attempting to ${function}", $e);
    }
}

// Get array of participants
$participants = $module->getAllParticipants();

?>
<script src="<?= $module->getUrl("lib/sweetalert/sweetalert2.all.min.js"); ?>"></script>
<style>
    .wrapper {
        display: inline-block;
        padding: 20px;
        margin-left: auto;
        margin-right: auto;
        width: 50vw !important;
    }

    .participants-form {
        border-radius: 5px;
        border: 1px solid #cccccc;
        padding: 20px;
        box-shadow: 0px 0px 5px #eeeeee;
        min-width: 50vw !important;
    }

    #RCPRO_Participants tr.even {
        background-color: white !important;
    }

    #RCPRO_Participants tr.odd {
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

    .rcpro_project_link_inactive {
        color: #101010 !important;
        text-decoration: line-through !important;
    }

    .rcpro_project_link_inactive:hover {
        color: #000000 !important;
        cursor: pointer !important;
        text-decoration: line-through !important;
    }

    .rcpro_participant_link {
        color: #000090 !important;
        font-weight: bold !important;
    }

    .rcpro_participant_link:hover {
        color: #900000 !important;
        font-weight: bold !important;
        cursor: pointer !important;
        background-color: #ddd !important;
    }
</style>

<?php if ($_SERVER["REQUEST_METHOD"] == "POST") { ?>
    <script>
        Swal.fire({
            icon: "<?= $icon ?>",
            title: "<?= $title ?>",
            confirmButtonColor: "#900000"
        });
    </script>
<?php } ?>

<div class="participantsContainer wrapper">
    <h2>Manage Participants</h2>
    <p>All participants across studies</p>
    <form class="participants-form" id="participants-form" action="<?= $module->getUrl("src/cc_participants.php"); ?>" method="POST" enctype="multipart/form-data" target="_self">
        <?php if (count($participants) === 0 || empty($participants)) { ?>
            <div>
                <p>No participants have been enrolled in this study</p>
            </div>
        <?php } else { ?>
            <div class="form-group">
                <table class="table" id="RCPRO_Participants">
                    <caption>REDCapPRO Participants</caption>
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
                            $info                 = getParticipantInfo($rcpro_participant_id);
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
                        ?>
                            <tr>
                                <td class="rcpro_participant_link" onclick="<?= $onclick ?>"><?= $participant["log_id"] ?></td>
                                <td class="dt-center"><?= $username_clean ?></td>
                                <td class="dt-center"><?= $fname_clean ?></td>
                                <td class="dt-center"><?= $lname_clean ?></td>
                                <td><?= $email_clean ?></td>
                                <?= createProjectsCell($projects_array); ?>
                                <td class="dt-center"><button type="button" class="btn btn-secondary btn-sm" onclick='(function(){
                                    $("#toReset").val("<?= $participant["log_id"] ?>");
                                    $("#toDisenroll").val("");
                                    $("#toChangeEmail").val("");
                                    $("#participants-form").submit();
                                    })();'>Reset</button>
                                </td>
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
                                                $("#participants-form").submit();
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
        <?php } ?>
    </form>
</div>
<script>
    (function($, window, document) {
        $(document).ready(function() {
            let participantsTable = $('#RCPRO_Participants').DataTable({
                dom: 'lBfrtip',
                stateSave: true,
                stateSaveCallback: function(settings, data) {
                    localStorage.setItem('DataTables_' + settings.sInstance, JSON.stringify(data))
                },
                stateLoadCallback: function(settings) {
                    return JSON.parse(localStorage.getItem('DataTables_' + settings.sInstance))
                },
                scrollY: '50vh',
                scrollCollapse: true,
                pageLength: 100
            });
        });
    }(window.jQuery, window, document));
</script>
<?php
require_once APP_PATH_DOCROOT . 'ControlCenter/footer.php';
?>
</body>

</html>