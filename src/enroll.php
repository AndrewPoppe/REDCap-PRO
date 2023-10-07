<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

$role = $module->getUserRole($module->safeGetUsername()); // 3=admin/manager, 2=user, 1=monitor, 0=not found
if ( $role < 2 ) {
    header("location:" . $module->getUrl("src/home.php"));
}
$module->includeFont();

require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->UI->ShowHeader("Enroll");

echo "<title>" . $module->APPTITLE . " - Enroll</title>";


?>
<link rel="stylesheet" type="text/css" href="<?= $module->getUrl("src/css/rcpro.php") ?>" />

<?php
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

if ( isset($_POST["id"]) && isset($project_id) ) {

    $rcpro_participant_id = intval($_POST["id"]);

    // Log submission
    $module->logForm("Submitted Enroll Form", $_POST);

    // If participant is not active, don't enroll them
    if ( !$module->PARTICIPANT->isParticipantActive($rcpro_participant_id) ) {

        echo "<script defer>Swal.fire({'title':'This participant is not currently active in REDCapPRO', 'html':'Contact your REDCap Administrator with questions.', 'icon':'info', 'showConfirmButton': false});</script>";
    } else {

        $redcap_dag = $module->DAG->getCurrentDag($module->safeGetUsername(), $module->framework->getProjectId());
        $dag        = filter_var($_POST["dag"], FILTER_VALIDATE_INT);
        $dag        = $dag === 0 ? null : $dag;
        if ( (!empty($redcap_dag) && $dag !== $redcap_dag) || !in_array($dag, array_keys($module->DAG->getProjectDags())) ) {
            $dag = $redcap_dag;
        }
        $pid            = intval($project_id);
        $rcpro_username = $module->PARTICIPANT->getUserName($rcpro_participant_id);
        $result         = $module->PROJECT->enrollParticipant($rcpro_participant_id, $pid, $dag, $rcpro_username);

        if ( $result === -1 ) {
            echo "<script defer>Swal.fire({'title':'This participant is already enrolled in this project', 'icon':'info', 'showConfirmButton': false});</script>";
        } elseif ( $result === true ) {
            echo "<script defer>Swal.fire({'title':'The participant was successfully enrolled in this project', 'icon':'success', 'showConfirmButton': false});</script>";
        } elseif ( !$result ) {
            echo "<script defer>Swal.fire({'title':'There was a problem enrolling this participant in this project', 'icon':'error', 'showConfirmButton': false});</script>";
        }
    }
}

// Initialize Javascript object
$module->initializeJavascriptModuleObject();
?>

<div class="wrapper enroll-wrapper" hidden>
    <h2>Enroll a Participant</h2>
    <p>Search for a participant by their email address and enroll the selected participant in this project.</p>
    <p><em>If the participant does not have an account, you can register them </em><strong><a
                href="<?= $module->getUrl("src/register.php"); ?>">here</a></strong>.</p>
    <button id="importCsv" class="btn btn-xs btn-success mb-2" onclick="$('#csvFile').click();">Import CSV</button>
    <span class="fa-stack fa-1x text-info mb-2 fa-2xs" style="cursor: pointer;"
        onclick="$('#infoModal').modal('show');">
        <i class="fa fa-circle fa-stack-2x icon-background"></i>
        <i class=" fas fa-question fa-stack-1x fa-xl text-white"></i>
    </span>
    <input type="file" id="csvFile" accept=".csv" style="display: none;" />
    <form class="rcpro-form enroll-form" id="enroll-form" onkeydown="return event.key != 'Enter';">
        <div class="form-group">
            <div id="searchContainer">
                <input type="email" placeholder="Enter the participant's email address..." name="REDCapPRO_Search"
                    id="REDCapPRO_Search" class="form-control">
                <div class="searchResults" id="searchResults"></div>
                <button type="button" id="emailSearchButton" class="btn btn-rcpro enroll-button"
                    style="margin-top: 10px;" onclick='RCPRO.searchEmail()' disabled>Search</button>
            </div>
        </div>
    </form>
    <form class="rcpro-form confirm-form" name="confirm-form" id="confirm-form"
        action="<?= $module->getUrl("src/enroll.php"); ?>" method="POST" enctype="multipart/form-data" target="_self"
        style="display:none;">
        <div class="form-group">
            <div class="selection" id="selectionContainer">
                <div class="mb-3 row">
                    <label for="username" class="col-sm-3 col-form-label">Username:</label>
                    <div class="col-sm-9">
                        <input type="text" id="username" name="username" class="form-control-plaintext" disabled
                            readonly>
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

                <?php if ( $module->DAG->getProjectDags() ) {
                    $userDag = $module->DAG->getCurrentDag($module->safeGetUsername(), $module->framework->getProjectId());
                    if ( isset($userDag) && $userDag != "" ) {
                        $dagName = isset($userDag) ? \REDCap::getGroupNames(false, $userDag) : "No Assignment";
                        ?>
                        <div class="mb-3 row">
                            <label for="dag" class="col-sm-3 col-form-label">Data Access Group:</label>
                            <div class="col-sm-9">
                                <input type="text" id="dag" name="dag" class="form-control-plaintext" disabled readonly
                                    value="<?= $dagName ?>">
                            </div>
                        </div>
                    <?php } else { ?>
                        <div class="mb-3 row">
                            <label for="dag" class="col-sm-3 col-form-label">Data Access Group:</label>
                            <div class="col-sm-9">
                                <select class="form-control" id="dag" name="dag">
                                    <option value="">No Assignment</option>
                                    <?php
                                    $projectDags = $module->framework->escape($module->DAG->getProjectDags());
                                    foreach ( $projectDags as $dag => $name ) {
                                        echo "<option value='" . $dag . "' " . ($dag == "" ? "selected" : "") . ">$name</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        <?php } ?>
                    <?php } ?>

                    <input type="text" id="id" name="id" class="form-control" readonly hidden>
                    <input type="hidden" name="redcap_csrf_token" value="<?= $module->framework->getCSRFToken() ?>">
                    <div>
                        <hr>
                        <button type="submit" class="btn btn-rcpro">Enroll Participant</button>
                        <button type="button" onclick="(function() { RCPRO.resetForm(); return false;})()"
                            class="btn btn-secondary">Cancel</button>
                    </div>
                </div>
            </div>
    </form>

</div>
<div class="modal fade" id="infoModal" tabindex="-1" aria-labelledby="infoModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-5" id="infoModalTitle">Import Participants via CSV</h5>
                <button type="button" class="btn-close" data-dismiss="modal" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>You can register (and optionally enroll) many participants at once by importing a CSV file. The
                    file must be formatted with the following columns.</p>
                <p><a id="importTemplate">Click here</a> to
                    download an import template.</p>
                <table class="table table-bordered table-sm">
                    <caption>Registration Import File Format</caption>
                    <thead class="thead-dark table-dark">
                        <tr>
                            <th class="align-middle">Column name</th>
                            <th class="align-middle">Description</th>
                            <th class="align-middle">Possible values</th>
                            <th class="align-middle">Required</th>
                            <th class="align-middle">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="align-middle text-center"><strong>username</strong></td>
                            <td class="align-middle">REDCapPRO username of the participant</td>
                            <td class="align-middle text-center">Any text</td>
                            <td class="align-middle text-center"><span class="required">Required</span></td>
                            <td class="align-middle"><span class="notes">Either this column or the <code>email</code>
                                    column must be present in the import file. <strong>NOT BOTH</strong></span></td>
                        </tr>
                        <tr>
                            <td class="align-middle text-center"><strong>email</strong></td>
                            <td class="align-middle">Email address of the participant</td>
                            <td class="align-middle text-center">Valid email</td>
                            <td class="align-middle text-center"><span class="required">Required</span></td>
                            <td class="align-middle"><span class="notes">Either this column or the <code>username</code>
                                    column must be present in the import file. <strong>NOT BOTH</strong></span></td>
                        </tr>
                        <tr>
                            <td class="align-middle text-center"><strong>dag</strong></td>
                            <td class="align-middle">Data Access Group to enroll the participant into</td>
                            <td class="align-middle text-center">Integer value representing the Data Access Group ID
                                number</td>
                            <td class="align-middle text-center"><span class="optional">Optional</span></td>
                            <td class="align-middle"><span class="notes">This value can be found on the DAGs page in
                                    the project. If enroll is not "Y" for a row, then the DAG value is ignored for
                                    that row.<br>The usual DAG rules apply, so you can only assign a participant to
                                    a DAG if that DAG exists in the project. If you are assigned to a DAG yourself,
                                    you can only assign participants to that DAG. If you are not assigned to a DAG,
                                    you can assign the participant to any DAG.</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<style>
    .btn-close {
        box-sizing: content-box;
        width: 1em;
        height: 1em;
        padding: .25em .25em;
        color: #000;
        background: transparent url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23000'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") center/1em auto no-repeat;
        border: 0;
        border-radius: .375rem;
        opacity: .5;
    }

    span.required {
        color: red;
        font-size: smaller;
    }

    span.optional {
        color: gray;
        font-size: smaller;
    }

    span.notes {
        font-size: smaller;
    }
</style>
<script>
    const RCPRO = <?= $module->getJavascriptModuleObjectName() ?>;

    RCPRO.showResult = function (str) {
        if (str.length < 3) {
            document.getElementById("searchResults").innerHTML = "";
            return;
        }
        RCPRO.ajax('searchParticipantByEmail', { searchTerm: str }).then(response => {
            document.getElementById("searchResults").innerHTML = response;
        });
    }

    RCPRO.populateSelection = function (fname, lname, email, id, username) {
        $("#fname").val(fname);
        $("#lname").val(lname);
        $("#email").val(email);
        $("#id").val(id);
        $("#username").val(username);
        $("#enroll-form").hide();
        $("#confirm-form").show();
    }

    RCPRO.resetForm = function () {
        $('#REDCapPRO_Search').val("");
        RCPRO.showResult("");
        $("#enroll-form").show();
        $("#confirm-form").hide();
    }

    RCPRO.checkForClear = function () {
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

    RCPRO.isEmailFieldValid = function () {
        let inputField = $("#REDCapPRO_Search");
        if (!RCPRO.checkForClear() && !inputField[0].checkValidity()) {
            let response = "<font style='color: red;'>Search term is not an email address</font>";
            document.getElementById("searchResults").innerHTML = response;
            return false;
        }
        return true;
    }

    RCPRO.searchEmail = function () {
        let inputField = $("#REDCapPRO_Search");
        if (RCPRO.checkForClear()) {

        }
        if (RCPRO.isEmailFieldValid()) {
            let searchValue = inputField.val();
            let searchValueClean = encodeURIComponent(searchValue);
            RCPRO.showResult(searchValue);
            return;
        }
    }

    const templateLink = document.querySelector('#importTemplate');
    const blob = new Blob(['email,dag\n'], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    templateLink.setAttribute('href', url);
    templateLink.setAttribute('download', 'register_template.csv');

    RCPRO.confirmImport = function () {
        $('.modal').modal('hide');
        if (!window.csv_file_contents || window.csv_file_contents === "") {
            return;
        }
        Swal.fire({ title: 'Please wait...', allowOutsideClick: false, didOpen: () => { Swal.showLoading() }, onOpen: () => { Swal.showLoading() } });
        RCPRO.ajax('importCsvEnroll', { data: window.csv_file_contents, confirm: true })
            .then((response) => {
                Swal.close();
                const result = JSON.parse(response);
                if (result.status != 'error') {
                    Swal.fire({
                        icon: 'success',
                        html: 'Successfully enrolled participants',
                        confirmButtonText: 'OK',
                        customClass: {
                            confirmButton: 'btn btn-primary',
                        },
                        buttonsStyling: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        html: result.message,
                        showConfirmButton: false
                    });
                }
            })
            .catch((error) => {
                console.error(error);
            });
    }

    RCPRO.handleFiles = function () {
        Swal.fire({ title: 'Please wait...', allowOutsideClick: false, didOpen: () => { Swal.showLoading() }, onOpen: () => { Swal.showLoading() } });
        if (this.files.length !== 1) {
            return;
        }
        const file = this.files[0];
        this.value = null;

        if (file.type !== "text/csv" && file.name.toLowerCase().indexOf('.csv') === -1) {
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            window.csv_file_contents = e.target.result;
            RCPRO.ajax('importCsvEnroll', { data: e.target.result })
                .then((response) => {
                    Swal.close();
                    const result = JSON.parse(response);
                    if (result.status != 'error') {
                        $(result.table).modal('show');
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            html: result.message,
                            showConfirmButton: false
                        });
                    }
                })
                .catch((error) => {
                    console.error(error);
                });
        };
        reader.readAsText(file);
    }

    const importFileElement = document.getElementById("csvFile");
    importFileElement.addEventListener("change", RCPRO.handleFiles);

    const input = document.getElementById("REDCapPRO_Search");
    input.addEventListener('click', RCPRO.checkForClear, false);
    input.addEventListener('blur', RCPRO.checkForClear, false);
    input.addEventListener('keydown', RCPRO.checkForClear, false);
    input.addEventListener('keyup', RCPRO.checkForClear, false);
    input.addEventListener('change', RCPRO.checkForClear, false);
</script>



<?php
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';