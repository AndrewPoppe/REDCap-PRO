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
        $this->redcap_pid = $this->get_redcap_pid();
        $this->in_project = isset($this->redcap_pid);
    }

    private function get_redcap_pid()
    {
        $pid = NULL;
        $project_constant = "PROJECT_ID";
        if (defined($project_constant)) {
            $pid = constant($project_constant);
        }
        return $pid;
    }

    public function create_email_link(string $email, ?string $subject): string
    {
        if (!isset($subject)) {
            $subject = $this->module->tt("email_inquiry_subject");
        }

        $body = "";

        $Auth = new Auth($this->module);
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

    public function get_contact_person_details_array(string $subject = NULL): array
    {
        $name  = $this->module->getProjectSetting("pc-name");
        $email = $this->module->getProjectSetting("pc-email");
        $phone = $this->module->getProjectSetting("pc-phone");

        $name_string = isset($name) && $name !== "" ? "<strong>" . $this->module->tt("email_contact_name_string") . "</strong>" . $name : "";
        $email_string = isset($email) && $email !== "" ? $this->create_email_link($email, $subject) : "";
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

    public function send_email_update_email(string $username, string $new_email, string $old_email): bool
    {
        try {
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
                $study_contact = $this->get_contact_person_details_array($this->module->tt("email_update_subject"));
                if (isset($study_contact["info"])) {
                    $body .= "<br>" . $study_contact["info"];
                }
            }
            $body .= "</p></div></body></html>";

            return \REDCap::email($new_email, $from, $subject, $body, $old_email);
        } catch (\Exception $e) {
            $this->module->logError("Error sending email reset email", $e);
        }
    }

    public function send_new_participant_email(string $username, string $email, string $fname, string $lname): bool
    {
        try {
            $ProjectSettings = new ProjectSettings($this->module);
            $participant = new Participant($this->module, ["rcpro_username" => $username]);
            $hours_valid = 24;
            $token       = $participant->createResetToken($hours_valid);

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
                $study_contact = $this->get_contact_person_details_array($subject);
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

    public function send_password_reset_email(Participant $participant): bool
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
            if ($this->in_project) {
                $study_contact = $this->get_contact_person_details_array($subject);
                if (isset($study_contact["info"])) {
                    $body .= "<br>" . $study_contact["info"];
                }
            }
            $body .= "</p></div></body></html>";

            $result = \REDCap::email($to, $from, $subject, $body);
            $status = $result ? "Sent" : "Failed to send";

            // Get current project (or "system" if initiated in the control center)
            $current_pid = $this->module->getProjectId() ?? "system";

            $enrolled_projects = $participant->getEnrolledProjects();

            // Determine who initiated this reset
            $redcap_user = constant("USERID");

            foreach ($enrolled_projects as $enrolled_project) {
                $this->module->logEvent("Password Reset Email - ${status}", [
                    "rcpro_participant_id"  => $participant->rcpro_participant_id,
                    "rcpro_username"        => $username_clean,
                    "rcpro_email"           => $to,
                    "redcap_user"           => $redcap_user,
                    "project_id"            => $enrolled_project->redcap_pid,
                    "rcpro_project_id"      => $enrolled_project->rcpro_project_id,
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

    public function send_username_reminder_email(string $email, string $username): bool
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
        if ($this->in_project) {
            $study_contact = $this->get_contact_person_details_array($subject);
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
