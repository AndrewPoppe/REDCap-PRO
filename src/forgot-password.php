<?php

# Initialize authentication session on page
$module::$AUTH->init();

$module->UiShowParticipantHeader("Forgot Password?");

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Validate token
    if (!$module::$AUTH->validate_csrf_token($_POST['token'])) {
        $module->log("Invalid CSRF Token");
        echo "Oops! Something went wrong. Please try again later.";
        return;
    }

    $err = null;

    // Validate username
    if (empty(trim($_POST["username"]))) {
        $err = "Please enter your username.";
    } else {
        $username = \REDCap::escapeHtml(trim($_POST["username"]));
        // Check input errors before sending reset email
        if (!$err) {
            $rcpro_participant_id = $module->getParticipantIdFromUsername($username);
            if (!empty($rcpro_participant_id)) {
                $module->log("Password Reset Email Sent", [
                    "rcpro_participant_id" => $rcpro_participant_id,
                    "rcpro_username"       => $username
                ]);
                $module->sendPasswordResetEmail($rcpro_participant_id);
            }
            echo "If a user account exists with the supplied username, a password reset email was sent to the email address associated with that account.";
        }
    }
} else {


    // set csrf token
    $module::$AUTH->set_csrf_token();

    echo '<div style="text-align: center;"><p>Provide the username associated with your account.</p></div>';
?>
    <form action="<?= $module->getUrl("src/forgot-password.php", true); ?>" method="post">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" class="form-control <?php echo (!empty($err)) ? 'is-invalid' : ''; ?>">
            <span class="invalid-feedback"><?php echo $err; ?></span>
        </div>
        <div class="form-group d-grid">
            <input type="submit" class="btn btn-primary" value="Submit">
        </div>
        <input type="hidden" name="token" value="<?= $module::$AUTH->get_csrf_token(); ?>">
    </form>
    <hr>
    <div style="text-align: center;">
        <a href="<?= $module->getUrl("src/forgot-username.php", true); ?>">Forgot Username?</a>
    </div>
<?php } ?>
</div>
<style>
    a {
        text-decoration: none !important;
        color: #900000 !important;
        font-weight: bold !important;
    }

    a:hover {
        text-shadow: 0px 0px 5px #900000;
    }
</style>
</body>

</html>