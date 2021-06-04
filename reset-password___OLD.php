<?php
use \Session;
$session_id = $_COOKIE["survey"];
if (!empty($session_id)) {
    session_id($session_id);
}
session_start();


// Check if the user is logged in, otherwise redirect to login page
if(!isset($_SESSION[$module::$APPTITLE."_loggedin"]) || $_SESSION[$module::$APPTITLE."_loggedin"] !== true) {
    //$module->createSession();
    header("location: ".$module->getUrl("login.php", true));
    return;
}

// If we're not using a temporary password, then we don't need to reset.
if (isset($_SESSION[$module::$APPTITLE."_temp_pw"]) && $_SESSION[$module::$APPTITLE."_temp_pw"] == 0) {
    if (isset($_SESSION[$module::$APPTITLE."_survey_url"])) {
        header("location: ".$_SESSION[$module::$APPTITLE."_survey_url"]);
    } else {
        echo "WHERE TO GO?";
    }
    return;
}

 
// Define variables and initialize with empty values
$new_password = $confirm_password = "";
$new_password_err = $confirm_password_err = "";
 
// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST") {
 
    // Validate token
    if (!$module->validateToken($_POST['token'])) {
        echo "Oops! Something went wrong. Please try again later.";
        return;
    }

    // Validate password
    if (empty(trim($_POST["new_password"]))) {
        $new_password_err = "Please enter your new password.";     
        $any_error = TRUE;
    } else {
        $new_password = trim($_POST["new_password"]);
        // Validate password strength
        $pw_len_req   = 8;
        $uppercase    = preg_match('@[A-Z]@', $new_password);
        $lowercase    = preg_match('@[a-z]@', $new_password);
        $number       = preg_match('@[0-9]@', $new_password);
        $specialChars = preg_match('@[^\w]@', $new_password);
        $goodLength   = strlen($new_password) >= $pw_len_req;

        if (!$uppercase || !$lowercase || !$number || !$specialChars || !$goodLength) {
            $new_password_err = "Password should be at least ${pw_len_req} characters in length and should include at least one of each of the following: 
            <br>- upper-case letter
            <br>- lower-case letter
            <br>- number
            <br>- special character";
            $any_error = TRUE;
        } 
    }
    
    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))){
        $confirm_password_err = "Please confirm the password.";     
        $any_error = TRUE;
    } else{
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($new_password_err) && ($new_password != $confirm_password)){
            $confirm_password_err = "Password did not match.";
            $any_error = TRUE;
        }
    }
        
    // Check input errors before updating the database
    if(!$any_error) {

        // Update password
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $result = $module->updatePassword($password_hash, $_SESSION[$module::$APPTITLE."_user_id"], FALSE);
        if (!$result) {
            echo "Oops! Something went wrong. Please try again later.";
            return;
        }

        $user = $module->getUser($_SESSION[$module::$APPTITLE."_username"]);
        // Store data in session variables
        $_SESSION["username"] = $user["username"];
        $_SESSION[$module::$APPTITLE."_user_id"] = $user["id"];
        $_SESSION[$module::$APPTITLE."_username"] = $user["username"];
        $_SESSION[$module::$APPTITLE."_email"] = $user["email"];
        $_SESSION[$module::$APPTITLE."_fname"] = $user["fname"];
        $_SESSION[$module::$APPTITLE."_lname"] = $user["lname"];
        $_SESSION[$module::$APPTITLE."_temp_pw"] = $user["temp_pw"];
        $_SESSION[$module::$APPTITLE."_loggedin"] = true;

        if (isset($_SESSION[$module::$APPTITLE."_survey_url"])) {
            header("location: ".$_SESSION[$module::$APPTITLE."_survey_url"]);
        } else {
            echo "<h2>Password successfully set.</h2>
            <p>You may now close this tab.</p>";
        }
        return;
    }
}

$module->UiShowParticipantHeader("Reset Password");
if ($_SESSION[$module::$APPTITLE."_temp_pw"] == 1) {
    echo "<p>Please fill out this form to set your password.</p>";
} else {
    echo "<p>Please fill out this form to reset your password.</p>";
}
?>
        <form action="<?php $module->getUrl("reset-password.php", true); ?>" method="post"> 
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" class="form-control <?php echo (!empty($new_password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $new_password; ?>">
                <span class="invalid-feedback"><?php echo $new_password_err; ?></span>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>">
                <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
            </div>
            <div class="form-group">
                <input type="submit" class="btn btn-primary" value="Submit">
            </div>
            <input type="hidden" name="token" value="<?=$_SESSION[$module::$APPTITLE."_token"]?>">
        </form>
    </div>    
</body>
</html>