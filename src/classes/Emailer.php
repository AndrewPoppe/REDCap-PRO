<?php

namespace YaleREDCap\REDCapPRO;

class Emailer
{

    private $module;
    private $redcap_pid;
    private $in_project;

    function __construct(REDCapPRO $module)
    {
        $this->module = $module;
        $this->redcap_pid = $this->getRedcapPid();
        $this->in_project = isset($this->redcap_pid);
    }

    private function getRedcapPid()
    {
        $pid = NULL;
        $project_constant = "PROJECT_ID";
        if (defined($project_constant)) {
            $pid = constant($project_constant);
        }
        return $pid;
    }

    /**
     * @param string $email
     * @param string|null $subject
     * 
     * @return [type]
     */
    public function createEmailLink(string $email, ?string $subject)
    {
        // Auth Helper
        $Auth = new Auth($this->module);
        if (!isset($subject)) {
            $subject = $this->module->tt("email_inquiry_subject");
        }
        $body = "";
        if ($Auth->is_logged_in()) {
            $username = $Auth->get_username();
            $body .= $this->module->tt("email_inquiry_username", $username) . "\n";
        }
        if ($this->redcap_pid) {
            $body .= $this->module->tt("email_inquiry_project_id", $this->redcap_pid) . "\n";
            $body .= $this->module->tt("email_inquiry_project_title", \REDCap::getProjectTitle());
        }
        $link = "mailto:${email}?subject=" . rawurlencode($subject) . "&body=" . rawurlencode($body);
        return "<br><strong>Email:</strong> <a href='${link}'>$email</a>";
    }

    /**
     * Gets project contact person's details
     * 
     * @return array of contact details
     */
    public function getContactPerson(string $subject = NULL)
    {
        $name  = $this->module->getProjectSetting("pc-name");
        $email = $this->module->getProjectSetting("pc-email");
        $phone = $this->module->getProjectSetting("pc-phone");

        $name_string = isset($name) && $name !== "" ? "<strong>" . $this->module->tt("email_contact_name_string") . "</strong>" . $name : "";
        $email_string = isset($email) && $email !== "" ? $this->createEmailLink($email, $subject) : "";
        $phone_string = isset($phone) && $phone !== "" ? "<br><strong>" . $this->module->tt("email_contact_phone_string") . "</strong>" . $phone : "";
        $info  = "${name_string} ${email_string} ${phone_string}";

        return [
            "name" => $name,
            "email" => $email,
            "phone" => $phone,
            "info" => $info,
            "name_string" => $name_string,
            "email_string" => $email_string,
            "phone_string" => $phone_string
        ];
    }

    public function sendEmailUpdateEmail(string $username, string $new_email, string $old_email)
    {
        $ProjectSettings = new ProjectSettings($this->module);
        $subject = $this->module->tt("email_update_subject");
        $from    = $ProjectSettings->getEmailFromAddress();
        $old_email_clean = \REDCap::escapeHtml($old_email);
        $new_email_clean = \REDCap::escapeHtml($new_email);
        $body    = "<html><body><div>
        <img src='" . REDCapPRO::$LOGO_ALTERNATE_URL . "' alt='img' width='500px'><br>
        <p>" . $this->module->tt("email_update_greeting") . "</p>
        <p>" . $this->module->tt("email_update_message1") . "<strong> ${username}</strong><br>
            <ul>
                <li><strong>" . $this->module->tt("email_update_old_email") . "</strong> ${old_email_clean}</li>
                <li><strong>" . $this->module->tt("email_update_new_email") . "</strong> ${new_email_clean}</li>
            </ul>
        </p>";
        $body .= "<p><strong>" . $this->module->tt("email_update_message2") . "</strong>";
        if ($this->in_project) {
            $study_contact = $this->getContactPerson($this->module->tt("email_update_subject"));
            if (isset($study_contact["info"])) {
                $body .= "<br>" . $study_contact["info"];
            }
        }
        $body .= "</p></div></body></html>";

        try {
            return \REDCap::email($new_email, $from, $subject, $body, $old_email);
        } catch (\Exception $e) {
            $this->module->logError("Error sending email reset email", $e);
        }
    }

    /**
     * Send new participant email to set password
     * 
     * This acts as an email verification process as well
     * 
     * @param string $username
     * @param string $email
     * @param string $fname
     * @param string $lname
     * 
     * @return bool|NULL
     */
    public function sendNewParticipantEmail(string $username, string $email, string $fname, string $lname)
    {
        // generate token
        try {
            $ProjectSettings = new ProjectSettings($this->module);
            $participant = new Participant($this->module, ["rcpro_username" => $username]);
            $hours_valid = 24;
            $token       = $participant->createResetToken($hours_valid);

            // create email
            $subject = $this->module->tt("email_new_participant_subject");
            $from    = $ProjectSettings->getEmailFromAddress();
            $body    = "<html><body><div>
            <img src='" . REDCapPRO::$LOGO_ALTERNATE_URL . "' alt='img' width='500px'><br>
            <p>" . $this->module->tt("email_new_participant_greeting", [$fname, $lname]) . "
            <br>" . $this->module->tt("email_new_participant_message1") . "
            <br>" . $this->module->tt("email_new_participant_message2") . " <strong>${username}</strong>
            <br>" . $this->module->tt("email_new_participant_message3") . "</p>

            <p>" . $this->module->tt("email_new_participant_message4") . "
            <br>" . $this->module->tt("email_new_participant_message5") . " 
            <a href='" . $this->module->getUrl("src/create-password.php", true) . "&t=${token}'>" . $this->module->tt("email_new_participant_link_text") . "</a>
            <br>" . $this->module->tt("email_new_participant_message6", $hours_valid) . "</p>
            <br>";
            $body .= "<p>" . $this->module->tt("email_new_participant_message7");
            if ($this->in_project) {
                $study_contact = $this->getContactPerson($subject);
                if (isset($study_contact["info"])) {
                    $body .= "<br>" . $study_contact["info"];
                }
            }
            $body .= "</p></div></body></html>";

            return \REDCap::email($email, $from, $subject, $body);
        } catch (\Exception $e) {
            $this->module->logError("Error sending new participant email", $e);
        }
    }

    /**
     * Send an email with a link for the participant to reset their email
     * 
     * @param mixed $rcpro_participant_id
     * 
     * @return void
     */
    public function sendPasswordResetEmail(Participant $participant)
    {
        try {
            // Settings Helper
            $ProjectSettings = new ProjectSettings($this->module);

            // generate token
            $token    = $participant->createResetToken();
            $to       = $participant->email;
            $username_clean = \REDCap::escapeHtml($participant->rcpro_username);

            // create email
            $subject = $this->module->tt("email_password_reset_subject");
            $from = $ProjectSettings->getEmailFromAddress();
            $body = "<html><body><div>
            <img src='" . REDCapPRO::$LOGO_ALTERNATE_URL . "' alt='img' width='500px'><br>
            <p>" . $this->module->tt("email_password_reset_greeting") . "
            <br>" . $this->module->tt("email_password_reset_message1") . "<br>
            <br>" . $this->module->tt("email_password_reset_message2") . "
            <br>" . $this->module->tt("email_password_reset_message3") . "<strong>${username_clean}</strong>
            <br>
            <br>" . $this->module->tt("email_password_reset_message4") . "<a href='" . $this->module->getUrl("src/reset-password.php", true) . "&t=${token}'>" . $this->module->tt("email_password_reset_link_text") . "</a>
            <br><em>" . $this->module->tt("email_password_reset_message5") . "<a href='" . $this->module->getUrl("src/forgot-password.php", true) . "'>" . $this->module->tt("email_password_reset_link_text") . "</a>
            </em></p><br>";
            $body .= "<p>" . $this->module->tt("email_password_reset_message6");
            if (defined("PROJECT_ID")) {
                $study_contact = $this->getContactPerson($subject);
                if (isset($study_contact["info"])) {
                    $body .= "<br>" . $study_contact["info"];
                }
            }
            $body .= "</p></div></body></html>";

            $result = \REDCap::email($to, $from, $subject, $body);
            $status = $result ? "Sent" : "Failed to send";

            // Get current project (or "system" if initiated in the control center)
            $current_pid = $this->module->getProjectId() ?? "system";

            // Get all projects to which participant is currently enrolled
            $projects = $participant->getEnrolledProjects();

            // Determine who initiated this reset
            $redcap_user = defined("USERID") ? constant("USERID") : NULL;

            foreach ($projects as $project) {
                $this->module->logEvent("Password Reset Email - ${status}", [
                    "rcpro_participant_id"  => $participant->rcpro_participant_id,
                    "rcpro_username"        => $username_clean,
                    "rcpro_email"           => $to,
                    "redcap_user"           => $redcap_user,
                    "project_id"            => $project->redcap_pid,
                    "rcpro_project_id"      => $project->rcpro_project_id,
                    "initiating_project_id" => $current_pid
                ]);
            }
            return $result;
        } catch (\Exception $e) {
            $this->module->logEvent("Password Reset Failed", [
                "rcpro_participant_id" => $participant->rcpro_participant_id,
                "redcap_user"          => constant("USERID")
            ]);
            $this->module->logError("Error sending password reset email", $e);
        }
    }

    /**
     * Sends an email that just contains the participant's username.
     * 
     * @param string $email
     * @param string $username
     * 
     * @return bool|NULL success or failure
     */
    public function sendUsernameEmail(string $email, string $username)
    {
        $ProjectSettings = new ProjectSettings($this->module);
        $subject = $this->module->tt("email_username_subject");
        $from    = $ProjectSettings->getEmailFromAddress();
        $body    = "<html><body><div>
        <img src='" . REDCapPRO::$LOGO_ALTERNATE_URL . "' alt='img' width='500px'><br>
        <p>" . $this->module->tt("email_username_greeting") . "</p>
        <p>" . $this->module->tt("email_username_message1") . "<strong> ${username}</strong><br>
        " . $this->module->tt("email_username_message2") . "</p>

        <p>" . $this->module->tt("email_username_message3") . "<br><br>";

        $body .= $this->module->tt("email_username_message4");
        if (defined("PROJECT_ID")) {
            $study_contact = $this->getContactPerson($subject);
            if (isset($study_contact["info"])) {
                $body .= "<br>" . $study_contact["info"];
            }
        }
        $body .= "</p></div></body></html>";

        try {
            return \REDCap::email($email, $from, $subject, $body);
        } catch (\Exception $e) {
            $this->module->logError("Error sending username email", $e);
        }
    }
}
