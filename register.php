<?php
 
// Define variables and initialize with empty values
$email = $password = $confirm_password = "";
$email_err = $password_err = $confirm_password_err = "";

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
            $email = $param_email;
        }
    }
    
    // Validate password
    if (empty(trim($_POST["REDCapPRO_PW"]))){
        $password_err = "Please enter a password.";     
        $any_error = TRUE;
    } else {
        $password1 = trim($_POST["REDCapPRO_PW"]);
        // Validate password strength
        $pw_len_req   = 8;
        $uppercase    = preg_match('@[A-Z]@', $password1);
        $lowercase    = preg_match('@[a-z]@', $password1);
        $number       = preg_match('@[0-9]@', $password1);
        $specialChars = preg_match('@[^\w]@', $password1);
        $goodLength   = strlen($password1) >= $pw_len_req;

        if (!$uppercase || !$lowercase || !$number || !$specialChars || !$goodLength) {
            $password_err = "Password should be at least ${pw_len_req} characters in length and should include at least one of each of the following: 
            <br>- upper-case letter
            <br>- lower-case letter
            <br>- number
            <br>- special character";
            $any_error = TRUE;
        } else {
            $password = trim($_POST["REDCapPRO_PW"]);
        }
    }
    
    // Validate confirm password
    if (empty(trim($_POST["Confirm_REDCapPRO_PW"]))){
        $confirm_password_err = "Please confirm password.";     
        $any_error = TRUE;
    } else{
        $confirm_password = trim($_POST["Confirm_REDCapPRO_PW"]);
        if (empty($password_err) && ($password != $confirm_password)){
            $confirm_password_err = "Password did not match.";
            $any_error = TRUE;
        }
    }
    
    // Check input errors before inserting in database
    if (!$any_error){
        
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $module->createUser($email, $password_hash, $fname, $lname);
            //header("location: login.php");
        }
        catch (\Exception $e) {
            echo "Oops! Something went wrong. Please try again later.";
        }
    }
}
?>
 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>REDCap PRO - Register</title>
    <!--<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">-->
    <style>
        .wrapper{ width: 720px; padding: 20px; }
        .register-form {width: 360px;}
    </style>
</head>
<body>
    <div class="wrapper">
        <h2>Register a Participant</h2>
        <p>Submit this form to create a new account for this participant.</p>
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
                <label>Password</label>
                <input type="password" name="REDCapPRO_PW" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $password; ?>">
                <span class="invalid-feedback"><?php echo $password_err; ?></span>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="Confirm_REDCapPRO_PW" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $confirm_password; ?>">
                <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
            </div>
            <div class="form-group">
                <input type="submit" class="btn btn-primary" value="Submit">
                <!--<input type="reset" class="btn btn-secondary ml-2" value="Reset">-->
            </div>
            <p>If the participant already has an account, you can enroll them in this project <a href="enroll.php">here</a>.</p>
        </form>
    </div>    
</body>
</html>