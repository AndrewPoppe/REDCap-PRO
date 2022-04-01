<?php

$role = SUPER_USER ? 3 : $module->getUserRole(USERID); // 3=admin/manager, 2=user, 1=monitor, 0=not found
if ($role < 2) {
    header("location:" . $module->getUrl("src/home.php"));
}

require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module::$UI->ShowHeader("Enroll");

echo "<title>" . $module::$APPTITLE . " - Enroll</title>";


?>
<link rel="stylesheet" type="text/css" href="<?= $module->getUrl("src/css/rcpro.php") ?>" />

<?php
// Check for errors
if (isset($_GET["error"])) {
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

if (isset($_POST["id"]) && isset($project_id)) {

    // Validate token
    if (!$module::$AUTH->validate_csrf_token($_POST['token'])) {
        header("location:" . $module->getUrl("src/enroll.php?error"));
    }

    $rcpro_participant_id = intval($_POST["id"]);

    // Log submission
    $module->logForm("Submitted Enroll Form", $_POST);

    // If participant is not active, don't enroll them
    if (!$module::$PARTICIPANT->isParticipantActive($rcpro_participant_id)) {

        echo "<script defer>Swal.fire({'title':'This participant is not currently active in REDCapPRO', 'html':'Contact your REDCap Administrator with questions.', 'icon':'info', 'showConfirmButton': false});</script>";
    } else {

        $redcap_dag = $module::$DAG->getCurrentDag(USERID, PROJECT_ID);
        $pid = intval($project_id);
        $rcpro_username = $module::$PARTICIPANT->getUserName($rcpro_participant_id);
        $result = $module::$PROJECT->enrollParticipant($rcpro_participant_id, $pid, $redcap_dag, $rcpro_username);

        if ($result === -1) {
            echo "<script defer>Swal.fire({'title':'This participant is already enrolled in this project', 'icon':'info', 'showConfirmButton': false});</script>";
        } else if ($result === TRUE) {
            echo "<script defer>Swal.fire({'title':'The participant was successfully enrolled in this project', 'icon':'success', 'showConfirmButton': false});</script>";
        } else if (!$result) {
            echo "<script defer>Swal.fire({'title':'There was a problem enrolling this participant in this project', 'icon':'error', 'showConfirmButton': false});</script>";
        }
    }
}

// Initialize Javascript object
$module->initializeJavascriptModuleObject();
$jsname = $module->getJavascriptModuleObjectName();
?>

<div class="wrapper enroll-wrapper" hidden>
    <h2>Enroll a Participant</h2>
    <p>Search for a participant by their email address and enroll the selected participant in this project.</p>
    <p><em>If the participant does not have an account, you can register them </em><strong><a href="<?= $module->getUrl("src/register.php"); ?>">here</a></strong>.</p>
    <script>
        let module = <?= $jsname ?>;

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
            let url = new URL("<?= $module->getUrl("src/livesearch.php") ?>");
            url.searchParams.append("q", str);

            xmlhttp.open("GET", url, true);
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

        function checkForClear() {
            let inputField = $('#REDCapPRO_Search');
            let inputButton = $('#emailSearchButton');
            if (inputField.val() === "") {
                inputButton.prop('disabled', true);
                document.getElementById("searchResults").innerHTML = "";
                return true;
            }
            inputButton.prop('disabled', false);
            return false;
        }

        function isEmailFieldValid() {
            let inputField = $("#REDCapPRO_Search");
            if (!checkForClear() && !inputField[0].checkValidity()) {
                let response = "<font style='color: red;'>Search term is not an email address</font>";
                document.getElementById("searchResults").innerHTML = response;
                return false;
            }
            return true;
        }

        function searchEmail() {
            let inputField = $("#REDCapPRO_Search");
            if (checkForClear()) {

            }
            if (isEmailFieldValid()) {
                let searchValue = inputField.val();
                let searchValueClean = encodeURIComponent(searchValue);
                showResult(searchValue);
                return;
            }
        }
    </script>
    <form class="rcpro-form enroll-form" id="enroll-form" onkeydown="return event.key != 'Enter';">
        <div class="form-group">
            <div id="searchContainer">
                <input type="email" placeholder="Enter the participant's email address..." name="REDCapPRO_Search" id="REDCapPRO_Search" class="form-control">
                <div class="searchResults" id="searchResults"></div>
                <button type="button" id="emailSearchButton" class="btn btn-rcpro enroll-button" style="margin-top: 10px;" onclick='searchEmail()' disabled>Search</button>
            </div>
        </div>
    </form>
    <script>
        const input = document.getElementById("REDCapPRO_Search");
        input.addEventListener('click', checkForClear, false);
        input.addEventListener('blur', checkForClear, false);
        input.addEventListener('keydown', checkForClear, false);
        input.addEventListener('keyup', checkForClear, false);
        input.addEventListener('change', checkForClear, false);
    </script>
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
                <input type="hidden" name="token" value="<?= $module::$AUTH->set_csrf_token(); ?>">
                <div>
                    <hr>
                    <button type="submit" class="btn btn-rcpro">Enroll Participant</button>
                    <button type="button" onclick="(function() { resetForm(); return false;})()" class="btn btn-secondary">Cancel</button>
                </div>
            </div>
        </div>
    </form>
</div>




<?php
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
