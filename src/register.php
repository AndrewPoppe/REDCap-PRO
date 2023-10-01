<?php
namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

$redcap_username = $module->safeGetUsername();
$role            = $module->getUserRole($redcap_username); // 3=admin/manager, 2=user, 1=monitor, 0=not found
if ( $role < 2 ) {
    header("location:" . $module->getUrl("src/home.php"));
}

require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->UI->ShowHeader("Register");
echo "<title>" . $module->APPTITLE . " - Register</title>";

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

// DAGs (for automatic enrollment)
$project_id         = (int) $module->framework->getProjectId();
$project_dags       = $module->DAG->getProjectDags();
$project_dags[null] = "Unassigned";
$projectHasDags     = count($project_dags) > 1;
$redcap_dag         = $module->DAG->getCurrentDag($redcap_username, $project_id);
if ( $projectHasDags ) {
    ?>
    <script>
        const projectHasDags = true;
        const userDag = '<?= $redcap_dag ?>';
        const projectDags = JSON.parse(<?= "'" . json_encode($project_dags) . "'" ?>);
    </script>
    <?php
}

// Track all errors
$any_error = false;

// Processing form data when form is submitted
if ( $_SERVER["REQUEST_METHOD"] == "POST" ) {

    // Log submission
    $module->logForm("Submitted Register Form", $_POST);

    // Validate Name
    $fname       = trim($_POST["REDCapPRO_FName"]);
    $fname_clean = \REDCap::escapeHtml($fname);
    if ( empty($fname_clean) ) {
        $fname_err = "Please enter a first name for this participant.";
        $any_error = true;
    }
    $lname       = trim($_POST["REDCapPRO_LName"]);
    $lname_clean = \REDCap::escapeHtml($lname);
    if ( empty($lname_clean) ) {
        $lname_err = "Please enter a last name for this participant.";
        $any_error = true;
    }

    // Validate email
    $param_email = \REDCap::escapeHtml(trim($_POST["REDCapPRO_Email"]));
    if ( empty($param_email) || !filter_var($param_email, FILTER_VALIDATE_EMAIL) ) {
        $email_err = "Please enter a valid email address.";
        $any_error = true;
    } else {
        $result = $module->PARTICIPANT->checkEmailExists($param_email);
        if ( $result === null ) {
            echo "Oops! Something went wrong. Please try again later.";
            return;
        } elseif ( $result === true ) {
            $email_err = "This email is already associated with an account.";
            $any_error = true;
        } else {
            // Everything looks good
            $email = $param_email;
        }
    }

    // Validate DAG
    $dag = $projectHasDags ? \REDCap::escapeHtml(trim($_POST["dag"])) : '';
    if ( !in_array($dag, array_keys($project_dags)) ) {
        $dag_err   = "That is not a valid Data Access Group.";
        $any_error = true;
    }

    // Check for input errors before inserting in database
    if ( !$any_error ) {
        $icon = $title = $html = "";
        try {
            $username = $module->PARTICIPANT->createParticipant($email, $fname_clean, $lname_clean);
            $module->sendNewParticipantEmail($username, $email, $fname_clean, $lname_clean);
            $icon  = "success";
            $title = "Participant Registered";
            $module->logEvent("Participant Registered", [
                "rcpro_username" => $username,
                "redcap_user"    => $redcap_username
            ]);

            // If we are also enrolling the participant, do that now
            if ( $_POST["action"] !== "register" ) {
                $rcpro_participant_id = $module->PARTICIPANT->getParticipantIdFromUsername($username);

                $result = $module->PROJECT->enrollParticipant($rcpro_participant_id, $project_id, $dag, $username);
                if ( $result === -1 ) {
                    $icon  = "error";
                    $title = 'This participant is already enrolled in this project';
                } elseif ( !$result ) {
                    $icon  = "error";
                    $title = 'The participant was successfully registered, but there was a problem automatically enrolling them in this project.';
                    $body  = 'Please try enrolling them manually on the Enroll tab.';
                } else {
                    $title = 'The participant was successfully registered and enrolled in this project';
                    $body  = "<strong>First name</strong>: " . $fname_clean . "<br>" .
                        "<strong>Last name</strong>: " . $lname_clean . "<br>" .
                        "<strong>Email address</strong>: " . $email . "<br>";
                    $body .= $projectHasDags ? '<strong>Data Access Group</strong>: ' . $project_dags[$dag] . '<br>' : '';
                }
            }

        } catch ( \Exception $e ) {
            $module->logError("Error creating participant", $e);
            $icon  = "error";
            $title = "Error Registering Participant";
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
    <h2>Register a Participant</h2>
    <p>Submit this form to create a new account for this participant.</p>
    <p><em>If the participant already has an account, you can enroll them in this project </em><strong><a
                href="<?= $module->getUrl("src/enroll.php"); ?>">here</a></strong>.</p>
    <form class="rcpro-form register-form" action="<?= $module->getUrl("src/register.php"); ?>" method="POST"
        enctype="multipart/form-data" target="_self">
        <div class="form-group">
            <label>First Name</label>
            <input type="text" name="REDCapPRO_FName"
                class="form-control <?php echo (!empty($fname_err)) ? 'is-invalid' : ''; ?>"
                value="<?php echo $fname_clean; ?>">
            <span class="invalid-feedback">
                <?php echo $fname_err; ?>
            </span>
        </div>
        <div class="form-group">
            <label>Last Name</label>
            <input type="text" name="REDCapPRO_LName"
                class="form-control <?php echo (!empty($lname_err)) ? 'is-invalid' : ''; ?>"
                value="<?php echo $lname_clean; ?>">
            <span class="invalid-feedback">
                <?php echo $lname_err; ?>
            </span>
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="REDCapPRO_Email"
                class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>"
                value="<?php echo $email; ?>">
            <span class="invalid-feedback">
                <?php echo $email_err; ?>
            </span>
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-rcpro" name="action" value="register"
                title="Register the participant but do not enroll">Register</button>
            <button type="button" class="btn btn-primary"
                title="Automatically enroll this participant in the study once they are registered"
                onclick="getDagAndSubmit();">Register and
                Enroll</button>
        </div>
        <input type="hidden" name="dag">
        <input type="hidden" name="redcap_csrf_token" value="<?= $module->framework->getCSRFToken() ?>">
    </form>
</div>
<script>
    <?php if ( $dag_err ) { ?>
        Swal.fire({
            icon: "error",
            title: "Error",
            text: "<?= $dag_err ?>",
            showConfirmButton: false
        });
    <?php } ?>
    async function getDagAndSubmit() {
        let selectedDag = userDag;
        if (projectHasDags && userDag === '') {

            const result = await Swal.fire({
                title: "Select a Data Access Group",
                input: 'select',
                inputOptions: projectDags,
                inputValue: '',
                showCancelButton: true
            });
            selectedDag = result.value;

        }
        $('input[name="dag"]').val(selectedDag);
        $('form.register-form').submit();
    }
</script>
<?php
include_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';