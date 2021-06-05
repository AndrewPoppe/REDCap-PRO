<?php
use \Session;
//$session_id = $_COOKIE["survey"];
$session_id = $_COOKIE[$module::$APPTITLE."_sessid"];
if (!empty($session_id)) {
    session_id($session_id);
} 
session_start();

// Check if the user is already logged in, if yes then redirect then to the survey
if (isset($_SESSION[$module::$APPTITLE."_loggedin"]) && $_SESSION[$module::$APPTITLE."_loggedin"] === true) {
    $survey_url = $_SESSION[$module::$APPTITLE."_survey_url"];
    $survey_url_active = $_SESSION[$this::$APPTITLE."_survey_link_active"];

    if (empty($survey_url)) {
        // TODO:
        
        echo "NO SURVEY URL";
        return;
    }

    if (empty($survey_url_active) || $survey_url_active !== TRUE) {
        return;
    }

    unset($_SESSION[$this::$APPTITLE."_survey_link_active"]);
    header("location: ${survey_url}");
    return;
} 
 
// Define variables and initialize with empty values
$username = $password = "";
$username_err = $password_err = $login_err = "";
 
// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate token
    if (!$module->validateToken($_POST['token'])) {
        echo "Oops! Something went wrong. Please try again later.";
        return;
    }
 
    // Check if username is empty
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter username.";
    } else {
        $username = trim($_POST["username"]);
    }
    
    // Check if password is empty
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    try {
        // Validate credentials
        if (empty($username_err) && empty($password_err)) {

            // Check if username exists, if yes then verify password
            if (!$module->usernameIsTaken($username)) {

                // Username doesn't exist, display a generic error message
                $module->incrementFailedIp($ip);
                $attempts = $module->checkAttempts(NULL, $ip);
                $remainingAttempts = $module::$LOGIN_ATTEMPTS - $attempts;
                $login_err = "Invalid username or password.<br>You have ${remainingAttempts} attempts remaining before being locked out.";

            } else {

                $user = $module->getUser($username);
                $stored_hash = $module->getHash($user["id"]);
                if (empty($stored_hash)) {
                    $login_err = "Error: please speak with your study coordinator.";
                
                } else if (password_verify($password, $stored_hash)) {
                    // SUCCESSFUL AUTHENTICATION

                    // Rehash password if necessary
                    if (password_needs_rehash($stored_hash, PASSWORD_DEFAULT)) {
                        echo "NEEDS REHASH";
                        return;
                        $new_hash = password_hash($password, PASSWORD_DEFAULT);
                        $module->storeHash($new_hash, $user["id"]);
                    }

                    // Store data in session variables
                    $_SESSION["username"] = $user["username"];
                    $_SESSION[$module::$APPTITLE."_user_id"] = $user["id"];
                    $_SESSION[$module::$APPTITLE."_username"] = $user["username"];
                    $_SESSION[$module::$APPTITLE."_email"] = $user["email"];
                    $_SESSION[$module::$APPTITLE."_fname"] = $user["fname"];
                    $_SESSION[$module::$APPTITLE."_lname"] = $user["lname"];
                    $_SESSION[$module::$APPTITLE."_temp_pw"] = $user["temp_pw"];
                    $_SESSION[$module::$APPTITLE."_loggedin"] = true;
                    //session_commit();
                    
                    // Redirect user to appropriate page
                    if ($user["temp_pw"] === 1) {
                        header("location: ".$module->getUrl("reset-password.php", true));
                    } else if (isset($_SESSION[$module::$APPTITLE."_survey_url"])) {
                        header("location: ".$_SESSION[$module::$APPTITLE."_survey_url"]);
                    } else {
                        echo "Please contact your study coordinator.";
                    }
                    return;
                } else {

                    // Password is not valid, display a generic error message
                    $module->incrementFailedLogin($user["id"]);
                    $ip = $module->getIPAddress();
                    $module->incrementFailedIp($ip);
                    $attempts = $module->checkAttempts($user["id"], $ip);
                    $remainingAttempts = $module::$LOGIN_ATTEMPTS - $attempts;
                    $login_err = "Invalid username or password.<br>You have ${remainingAttempts} attempts remaining before being locked out.";
                    
                }
            }
        }
    }
    catch (\Exception $e) {
        echo "Oops! Something went wrong. Please try again later.";
    }
}
 
// This method starts the html doc
$module->UiShowParticipantHeader("Login");
?>

<p>Please fill in your credentials to login.</p>

            <?php 
            if (!empty($login_err)) {
                echo '<div class="alert alert-danger">' . $login_err . '</div>';
            }        
            ?>

            <form action="<?php $module->getUrl("login.php", true); ?>" method="post">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username; ?>">
                    <span class="invalid-feedback"><?php echo $username_err; ?></span>
                </div>    
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                    <span class="invalid-feedback"><?php echo $password_err; ?></span>
                </div>
                <div class="form-group">
                    <input type="submit" class="btn btn-primary" value="Login">
                </div>
                <input type="hidden" name="token" value="<?=$_SESSION[$module::$APPTITLE."_token"]?>">
            </form>
        </div>
    </div>
</body>
</html>