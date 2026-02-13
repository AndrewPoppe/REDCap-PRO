<?php
namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

$redcap_username = $module->safeGetUsername();
$role            = $module->getUserRole($redcap_username); // 3=admin/manager, 2=user, 1=monitor, 0=not found
if ( $role < 2 ) {
    header("location:" . $module->getUrl("src/home.php"));
}
$module->includeFont();

require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$ui = new UI($module);
$ui->ShowHeader("Register");
echo "<title>" . $module->APPTITLE . " - " . $module->tt("project_register_title") . "</title>";
$module->initializeJavascriptModuleObject();
$module->tt_transferToJavascriptModuleObject();

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

// DAGs (for automatic enrollment)
$project_id         = (int) $module->framework->getProjectId();
$dagHelper          = new DAG($module);
$project_dags       = $module->framework->escape($dagHelper->getProjectDags());
$project_dags[null] = $module->tt("project_unassigned");
$projectHasDags     = count($project_dags) > 1;
$redcap_dag         = $module->framework->escape($dagHelper->getCurrentDag($redcap_username, $project_id));
if ( $projectHasDags ) {
    ?>
    <script>
        const projectHasDags = true;
        const userDag = '<?= $redcap_dag ?>';
        const projectDags = JSON.parse(<?= "'" . json_encode($project_dags) . "'" ?>);
    </script>
    <?php
} else {
    ?>
    <script>
        const projectHasDags = false;
        const userDag = '';
    </script>
    <?php
}

// Track all errors
$any_error = false;

// Processing form data when form is submitted
if ( $_SERVER["REQUEST_METHOD"] == "POST" ) {

    // Log submission
    $module->logForm("Submitted Register Form", $_POST);

    // Participant Helper
    $participantHelper = new ParticipantHelper($module);

    // Validate Name
    $fname       = trim($_POST["REDCapPRO_FName"]);
    $fname_clean = \REDCap::escapeHtml($fname);
    if ( empty($fname_clean) ) {
        $fname_err = $module->tt("project_register_first_name_error");
        $any_error = true;
    }
    $lname       = trim($_POST["REDCapPRO_LName"]);
    $lname_clean = \REDCap::escapeHtml($lname);
    if ( empty($lname_clean) ) {
        $lname_err = $module->tt("project_register_last_name_error");
        $any_error = true;
    }

    // Validate email
    $param_email = \REDCap::escapeHtml(trim($_POST["REDCapPRO_Email"]));
    if ( empty($param_email) || !filter_var($param_email, FILTER_VALIDATE_EMAIL) ) {
        $email_err = $module->tt("project_register_email_error");
        $any_error = true;
    } else {
        $result = $participantHelper->checkEmailExists($param_email);
        if ( $result === null ) {
            echo $module->tt("project_error_general");
            return;
        } elseif ( $result === true ) {
            $email_err = $module->tt("project_register_email_exists_error");
            $any_error = true;
        } else {
            // Everything looks good
            $email = $param_email;
        }
    }

    // Validate DAG
    $dag = filter_var($_POST["dag"], FILTER_VALIDATE_INT);
    $dag = $dag === false ? null : $dag;
    if ( !in_array($dag, array_keys($project_dags)) ) {
        $dag_err   = $module->tt("project_register_dag_error");
        $any_error = true;
    }

    // Check for input errors before inserting in database
    if ( !$any_error ) {
        $icon = $title = $html = "";
        try {
            $participantHelper = new ParticipantHelper($module);
            $username          = $participantHelper->createParticipant($email, $fname_clean, $lname_clean);
            $module->sendNewParticipantEmail($username, $email, $fname_clean, $lname_clean);
            $icon  = "success";
            $title = $module->tt("project_register_success_title");
            $module->logEvent("Participant Registered", [
                "rcpro_username" => $username,
                "redcap_user"    => $redcap_username
            ]);

            // If we are also enrolling the participant, do that now
            $action = filter_input(INPUT_POST, "action");
            if ( $action !== "register" ) {
                $rcpro_participant_id = $participantHelper->getParticipantIdFromUsername($username);

                $projectHelper = new ProjectHelper($module);
                $result        = $projectHelper->enrollParticipant($rcpro_participant_id, $project_id, $dag, $username);
                if ( $result === -1 ) {
                    $icon  = "error";
                    $title = $module->tt("project_enroll_participant_already_enrolled");
                } elseif ( !$result ) {
                    $icon  = "error";
                    $title = $module->tt("project_register_enroll_error");
                    $body  = $module->tt("project_register_please_try_enrolling_manually");
                } else {
                    $title = $module->tt("project_register_registered_and_enrolled");
                    $body  = "<strong>" . $module->tt("project_enroll_first_name_label") . "</strong>: " . $fname_clean . "<br>" .
                        "<strong>" . $module->tt("project_enroll_last_name_label") . "</strong>: " . $lname_clean . "<br>" .
                        "<strong>" . $module->tt("project_enroll_email_label") . "</strong>: " . $email . "<br>";
                    $body .= $projectHasDags ? '<strong>' . $module->tt("project_enroll_dag_label") . '</strong>: ' . $project_dags[$dag] . '<br>' : '';
                }
            }

        } catch ( \Exception $e ) {
            $module->logError("Error creating participant", $e);
            $icon  = "error";
            $title = $module->tt("project_register_error_title");
            $html  = $e->getMessage();
        } finally {
            ?>
            <script>
                let success = "<?= $icon ?>" === "success";
                Swal.fire({
                    icon: "<?= $icon ?>",
                    title: "<?= $title ?>",
                    html: "<?= $body ?>",
                    showConfirmButton: false
                })
                    .then(function () {
                        if (success) {
                            window.location.href = "<?= $module->getUrl("src/register.php"); ?>";
                        }
                    });
            </script>
            <?php
        }
    }
}

?>
<link rel="stylesheet" type="text/css" href="<?= $module->getUrl("src/css/rcpro.php") ?>" />

<div class="wrapper" hidden>
    <h2><?= $module->tt("project_register_title") ?></h2>
    <p><?= $module->tt("project_register_instructions1") ?></p>
    <p><em><?= $module->tt("project_register_instructions2") ?></em></p>

    <button id="importCsv" class="btn btn-xs btn-success mb-2" onclick="$('#csvFile').click();"><?= $module->tt("project_import_csv") ?></button>
    <span class="fa-stack fa-1x text-info mb-2 fa-2xs" style="cursor: pointer;"
        onclick="$('#infoModal').modal('show');">
        <i class="fa fa-circle fa-stack-2x icon-background"></i>
        <i class=" fas fa-question fa-stack-1x fa-xl text-white"></i>
    </span>
    <input type="file" id="csvFile" accept=".csv" style="display: none;" />

    <form class="rcpro-form register-form" action="<?= $module->getUrl("src/register.php"); ?>" method="POST"
        enctype="multipart/form-data" target="_self">
        <div class="form-group">
            <label><?= $module->tt("project_first_name") ?></label>
            <input type="text" name="REDCapPRO_FName"
                class="form-control <?php echo (!empty($fname_err)) ? 'is-invalid' : ''; ?>"
                value="<?php echo $fname_clean; ?>">
            <span class="invalid-feedback">
                <?php echo $fname_err; ?>
            </span>
        </div>
        <div class="form-group">
            <label><?= $module->tt("project_last_name") ?></label>
            <input type="text" name="REDCapPRO_LName"
                class="form-control <?php echo (!empty($lname_err)) ? 'is-invalid' : ''; ?>"
                value="<?php echo $lname_clean; ?>">
            <span class="invalid-feedback">
                <?php echo $lname_err; ?>
            </span>
        </div>
        <div class="form-group">
            <label><?= $module->tt("project_email") ?></label>
            <input type="email" name="REDCapPRO_Email"
                class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>"
                value="<?php echo $email; ?>">
            <span class="invalid-feedback">
                <?php echo $email_err; ?>
            </span>
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-rcpro" name="action" value="register"
                title="<?= $module->tt("project_register_button_register_title") ?>"><?= $module->tt("project_register_button_register") ?></button>
            <button type="button" class="btn btn-primary"
                title="<?= $module->tt("project_register_button_register_and_enroll_title") ?>"
                onclick="getDagAndSubmit();"><?= $module->tt("project_register_button_register_and_enroll") ?></button>
        </div>
        <input type="hidden" name="dag">
        <input type="hidden" name="redcap_csrf_token" value="<?= $module->framework->getCSRFToken() ?>">
    </form>
    <div class="modal fade" id="infoModal" tabindex="-1" aria-labelledby="infoModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fs-5" id="infoModalTitle"><?= $module->tt("project_register_import_csv") ?></h5>
                    <button type="button" class="btn-close" data-dismiss="modal" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><?= $module->tt("project_register_import_instructions1") ?></p>
                    <p><?= $module->tt("project_register_import_instructions2", ["importTemplate"]) ?></p>
                    <table class="table table-bordered table-sm">
                        <caption><?= $module->tt("project_register_import_instructions_caption") ?></caption>
                        <thead class="thead-dark table-dark">
                            <tr>
                                <th class="align-middle"><?= $module->tt("project_column_name") ?></th>
                                <th class="align-middle"><?= $module->tt("project_description") ?></th>
                                <th class="align-middle"><?= $module->tt("project_possible_values") ?></th>
                                <th class="align-middle"><?= $module->tt("project_required") ?></th>
                                <th class="align-middle"><?= $module->tt("project_notes") ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="align-middle text-center"><strong><?= $module->tt("project_register_fname_key") ?></strong></td>
                                <td class="align-middle"><?= $module->tt("project_register_fname_desc") ?></td>
                                <td class="align-middle text-center"><?= $module->tt("project_register_fname_possible_values") ?></td>
                                <td class="align-middle text-center"><span class="required"><?= $module->tt("project_required") ?></span></td>
                                <td class="align-middle"></td>
                            </tr>
                            <tr>
                                <td class="align-middle text-center"><strong><?= $module->tt("project_register_lname_key") ?></strong></td>
                                <td class="align-middle"><?= $module->tt("project_register_lname_desc") ?></td>
                                <td class="align-middle text-center"><?= $module->tt("project_register_lname_possible_values") ?></td>
                                <td class="align-middle text-center"><span class="required"><?= $module->tt("project_required") ?></span></td>
                                <td class="align-middle"></td>
                            </tr>
                            <tr>
                                <td class="align-middle text-center"><strong><?= $module->tt("project_register_email_key") ?></strong></td>
                                <td class="align-middle"><?= $module->tt("project_register_email_desc") ?></td>
                                <td class="align-middle text-center"><?= $module->tt("project_register_email_possible_values") ?></td>
                                <td class="align-middle text-center"><span class="required"><?= $module->tt("project_required") ?></span></td>
                                <td class="align-middle"><span class="notes"><?= $module->tt("project_register_email_notes") ?></span></td>
                            </tr>
                            <tr>
                                <td class="align-middle text-center"><strong><?= $module->tt("project_register_enroll_key") ?></strong></td>
                                <td class="align-middle"><?= $module->tt("project_register_enroll_desc") ?></td>
                                <td class="align-middle text-center"><?= $module->tt("project_register_enroll_possible_values") ?></td>
                                <td class="align-middle text-center"><span class="optional"><?= $module->tt("project_optional") ?></span></td>
                                <td class="align-middle"><span class="notes"><?= $module->tt("project_register_enroll_notes") ?></span></td>
                            </tr>
                            <tr>
                                <td class="align-middle text-center"><strong><?= $module->tt("project_register_dag_key") ?></strong></td>
                                <td class="align-middle"><?= $module->tt("project_register_dag_desc") ?></td>
                                <td class="align-middle text-center"><?= $module->tt("project_register_dag_possible_values") ?></td>
                                <td class="align-middle text-center"><span class="optional"><?= $module->tt("project_optional") ?></span></td>
                                <td class="align-middle"><span class="notes"><?= $module->tt("project_register_dag_notes") ?></span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
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
    $(document).ready(function () {

        const templateLink = document.querySelector('#importTemplate');
        const blob = new Blob(['fname,lname,email,enroll,dag\n'], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        templateLink.setAttribute('href', url);
        templateLink.setAttribute('download', 'register_template.csv');

        RCPRO.confirmImport = function () {
            $('.modal').modal('hide');
            if (!window.csv_file_contents || window.csv_file_contents === "") {
                return;
            }
            Swal.fire({ title: "<?= $module->tt('project_please_wait') ?>", allowOutsideClick: false, didOpen: () => { Swal.showLoading() }, onOpen: () => { Swal.showLoading() } });
            RCPRO.ajax('importCsvRegister', { data: window.csv_file_contents, confirm: true })
                .then((response) => {
                    Swal.close();
                    const result = JSON.parse(response);
                    if (result.status != 'error') {
                        Swal.fire({
                            icon: 'success',
                            html: "<?= $module->tt('project_register_success') ?>",
                            confirmButtonText: "<?= $module->tt('project_ok') ?>",
                            customClass: {
                                confirmButton: 'btn btn-primary',
                            },
                            buttonsStyling: false
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: "<?= $module->tt("project_error") ?>",
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
            Swal.fire({ title: "<?= $module->tt('project_please_wait') ?>", allowOutsideClick: false, didOpen: () => { Swal.showLoading() }, onOpen: () => { Swal.showLoading() } });
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
                RCPRO.ajax('importCsvRegister', { data: e.target.result })
                    .then((response) => {
                        Swal.close();
                        const result = JSON.parse(response);
                        if (result.status != 'error') {
                            $(result.table).modal('show');
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: "<?= $module->tt("project_error") ?>",
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
    });
    <?php if ( $dag_err ) { ?>
        Swal.fire({
            icon: "error",
            title: "<?= $module->tt("project_error") ?>",
            text: "<?= $dag_err ?>",
            showConfirmButton: false
        });
    <?php } ?>
    async function getDagAndSubmit() {

        if (projectHasDags && userDag === '') {
            let selectedDag = userDag;
            const result = await Swal.fire({
                title: "<?= $module->tt("project_register_select_dag") ?>",
                input: 'select',
                inputOptions: projectDags,
                inputValue: '',
                showCancelButton: true
            });
            if (result.isDismissed) {
                return;
            }
            selectedDag = result.value;
            $('input[name="dag"]').val(selectedDag);
        } else if (projectHasDags && userDag !== '') {
            $('input[name="dag"]').val(userDag);
        }
        $('form.register-form').submit();
    }
</script>
<?php
include_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';