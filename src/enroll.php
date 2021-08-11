<?php

$role = SUPER_USER ? 3 : $module->getUserRole(USERID); // 3=admin/manager, 2=user, 1=monitor, 0=not found
if ($role < 2) {
    header("location:" . $module->getUrl("src/home.php"));
}

echo "<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8'>
<title>" . $module::$APPTITLE . " - Enroll</title>";
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module::$UI->ShowHeader("Enroll");



?>
<link rel="stylesheet" type="text/css" href="<?= $module->getUrl("css/rcpro.php") ?>" />
</head>

<body>

    <?php
    if (isset($_POST["id"]) && isset($project_id)) {

        // Validate token
        if (!$module::$AUTH->validate_csrf_token($_POST['token'])) {
            header("location:" . $module->getUrl("src/enroll.php"));
        }

        $rcpro_participant_id = intval($_POST["id"]);
        $redcap_dag = $module::$DAG->getCurrentDag(USERID, PROJECT_ID);
        $pid = intval($project_id);
        $rcpro_username = $module::$PARTICIPANT->getUserName($rcpro_participant_id);
        $result = $module::$PROJECT->enrollParticipant($rcpro_participant_id, $pid, $redcap_dag, $rcpro_username);

        if ($result === -1) {
            echo "<script defer>Swal.fire({'title':'This user is already enrolled in this project', 'icon':'info', 'showConfirmButton': false});</script>";
        } else if ($result === TRUE) {
            echo "<script defer>Swal.fire({'title':'The user was successfully enrolled in this project', 'icon':'success', 'showConfirmButton': false});</script>";
        } else if (!$result) {
            echo "<script defer>Swal.fire({'title':'There was a problem enrolling this user in this project', 'icon':'error', 'showConfirmButton': false});</script>";
        }
    }

    // set csrf token
    $module::$AUTH->set_csrf_token();
    ?>

    <div class="wrapper enroll-wrapper">
        <h2>Enroll a Participant</h2>
        <p>Search for a participant by email , name, or username, and enroll the selected participant in this project.</p>
        <p><em>If the participant does not have an account, you can register them </em><strong><a href="<?= $module->getUrl("src/register.php"); ?>">here</a></strong>.</p>
        <script>
            function showResult(str) {
                if (str.length < 3) {
                    document.getElementById("searchResults").innerHTML = "";
                    return;
                }
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.onreadystatechange = function() {
                    if (this.readyState == 4 && this.status == 200) {
                        document.getElementById("searchResults").innerHTML = this.responseText;
                    }
                }
                xmlhttp.open("GET", "<?= $module->getUrl("src/livesearch.php") ?>&q=" + str, true);
                xmlhttp.send();
            }

            function populateSelection(fname, lname, email, id, username) {
                $("#fname").val(fname);
                $("#lname").val(lname);
                $("#email").val(email);
                $("#id").val(id);
                $("#username").val(username);
                $("#enroll-form").hide();
                $("#confirm-form").show();
            }

            function resetForm() {
                $('#REDCapPRO_Search').val("");
                showResult("");
                $("#enroll-form").show();
                $("#confirm-form").hide();
            }
        </script>
        <form class="rcpro-form enroll-form" id="enroll-form" onkeydown="return event.key != 'Enter';">
            <div class="form-group">
                <div id="searchContainer">
                    <label>Search</label>
                    <input type="text" name="REDCapPRO_Search" id="REDCapPRO_Search" class="form-control" onkeyup="showResult(this.value)">
                    <div class="searchResults" id="searchResults"></div>
                </div>
            </div>
        </form>
        <form class="rcpro-form confirm-form" name="confirm-form" id="confirm-form" action="<?= $module->getUrl("src/enroll.php"); ?>" method="POST" enctype="multipart/form-data" target="_self" style="display:none;">
            <div class="form-group">
                <div class="selection" id="selectionContainer">
                    <div class="mb-3 row">
                        <label for="username" class="col-sm-3 col-form-label">Username:</label>
                        <div class="col-sm-9">
                            <input type="text" id="username" name="username" class="form-control-plaintext" disabled readonly>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label for="fname" class="col-sm-3 col-form-label">First Name:</label>
                        <div class="col-sm-9">
                            <input type="text" id="fname" name="fname" class="form-control-plaintext" disabled readonly>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label for="lname" class="col-sm-3 col-form-label">Last Name:</label>
                        <div class="col-sm-9">
                            <input type="text" id="lname" name="lname" class="form-control-plaintext" disabled readonly>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label for="email" class="col-sm-3 col-form-label">Email:</label>
                        <div class="col-sm-9">
                            <input type="text" id="email" name="email" class="form-control-plaintext" disabled readonly>
                        </div>
                    </div>

                    <?php if ($module::$DAG->getProjectDags()) {
                        $userDag = $module::$DAG->getCurrentDag(USERID, PROJECT_ID);
                        $dagName = isset($userDag) ? \REDCap::getGroupNames(false, $userDag) : "No Assignment";
                    ?>
                        <div class="mb-3 row">
                            <label for="dag" class="col-sm-3 col-form-label">Data Access Group:</label>
                            <div class="col-sm-9">
                                <input type="text" id="dag" name="dag" class="form-control-plaintext" disabled readonly value="<?= $dagName ?>">
                            </div>
                        </div>
                    <?php } ?>

                    <input type="text" id="id" name="id" class="form-control" readonly hidden>
                    <input type="hidden" name="token" value="<?= $module::$AUTH->get_csrf_token(); ?>">
                    <div>
                        <hr>
                        <button type="submit" class="btn btn-rcpro">Enroll Participant</button>
                        <button type="button" onclick="(function() { resetForm(); return false;})()" class="btn btn-secondary">Cancel</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</body>

</html>




<?php
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
