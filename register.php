<?php

$role = $module->getUserRole(USERID); // 3=admin/manager, 2=monitor, 1=user, 0=not found
if (SUPER_USER) {
    $role = 3;
}
if ($role < 2) {
    header("location:".$module->getUrl("home.php"));
}

// Initialize tables if necesary
$module->initTables();




echo "<title>".$module::$APPTITLE." - Register</title>";
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->UiShowHeader("Register");
 
// Track any error
$any_error = FALSE;
 
// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST"){

    // Validate Name
    $fname = trim($_POST["REDCapPRO_FName"]);
    if (empty($fname)) {
        $fname_err = "Please enter a first name for this participant.";
        $any_error = TRUE;
    }
    $lname = trim($_POST["REDCapPRO_LName"]);
    if (empty($lname)) {
        $lname_err = "Please enter a last name for this participant.";
        $any_error = TRUE;
    }
 
    // Validate email
    $param_email = trim($_POST["REDCapPRO_Email"]);
    if (empty($param_email) || !filter_var($param_email, FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address.";
        $any_error = TRUE;
    } else {
        $result = $module->checkEmailExists($param_email);
        if ($result === NULL) {
            echo "Oops! Something went wrong. Please try again later.";
            return;
        } else if ($result === TRUE){
            $email_err = "This email is already associated with an account.";
            $any_error = TRUE;
        } else{
            //TODO: clean this input first
            $email = $param_email;
        }
    }
    
    // Check input errors before inserting in database
    if (!$any_error){
        $icon = $title = $html = "";
        try {
            $username = $module->createUser($email, $fname, $lname);
            $module->sendNewUserEmail($username, $email, $fname, $lname);
            $icon = "success";
            $title = "Participant Registered";
        }
        catch (\Exception $e) {
            $module->log($e->getMessage());
            $icon = "error";
            $title = "Error Registering Participant";
            $html = $e->getMessage();
        }
        finally {
            ?>
            <script>
                let success = "<?=$icon?>" === "success";
                Swal.fire({
                    icon: "<?=$icon?>",
                    title: "<?=$title?>",
                    html: "<?=$body?>"
                })
                .then(() => {
                    if (success) {
                        window.location.href = "<?=$module->getUrl("register.php");?>";
                    }
                });
            </script>
            <?php
        }
    }
}
?>
 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>REDCap PRO - Register</title>
    <style>
        .wrapper{ width: 720px; padding: 20px; }
        .register-form {
            width: 360px;
            border-radius: 5px;
            border: 1px solid #cccccc;
            padding: 20px;
            box-shadow: 0px 0px 5px #eeeeee;
        }
        button:hover {
            outline: none !important;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <h2>Register a Participant</h2>
        <p>Submit this form to create a new account for this participant.</p>
        <p><em>If the participant already has an account, you can enroll them in this project </em><strong><a href="<?=$module->getUrl("enroll.php");?>">here</a></strong>.</p>
        <form class="register-form" action="<?= $module->getUrl("register.php"); ?>" method="POST" enctype="multipart/form-data" target="_self" >
            <div class="form-group">
                <label>First Name</label>
                <input type="text" name="REDCapPRO_FName" class="form-control <?php echo (!empty($fname_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $fname; ?>">
                <span class="invalid-feedback"><?php echo $fname_err; ?></span>
            </div> 
            <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="REDCapPRO_LName" class="form-control <?php echo (!empty($lname_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $lname; ?>">
                <span class="invalid-feedback"><?php echo $lname_err; ?></span>
            </div> 
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="REDCapPRO_Email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>">
                <span class="invalid-feedback"><?php echo $email_err; ?></span>
            </div>    
            <div class="form-group">
                <button type="submit" class="btn btn-primary" value="Submit">Submit</button>
            </div>
        </form>
    </div>    
</body>
</html>
<?php
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';