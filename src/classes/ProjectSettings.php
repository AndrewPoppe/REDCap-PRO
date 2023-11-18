<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

class ProjectSettings
{
    public REDCapPRO $module;

    function __construct(REDCapPRO $module)
    {
        $this->module = $module;
    }

    public function getTimeoutWarningMinutes()
    {
        $result = $this->module->framework->getSystemSetting("warning-time");
        if ( !floatval($result) ) {
            // DEFAULT TO 1 MINUTE IF NOT SET
            $result = 1;
        }
        return $result;
    }

    public function getTimeoutMinutes()
    {
        $result = $this->module->framework->getSystemSetting("timeout-time");
        if ( !floatval($result) ) {
            // DEFAULT TO 5 MINUTES IF NOT SET
            $result = 5;
        }
        return $result;
    }

    public function getPasswordLength()
    {
        $result = $this->module->framework->getSystemSetting("password-length");
        if ( !intval($result) ) {
            // DEFAULT TO 8 CHARACTERS IF NOT SET
            $result = 8;
        }
        return $result;
    }

    public function getLoginAttempts()
    {
        $result = $this->module->framework->getSystemSetting("login-attempts");
        if ( !intval($result) ) {
            // DEFAULT TO 3 ATTEMPTS IF NOT SET
            $result = 3;
        }
        return $result;
    }

    public function getLockoutDurationSeconds()
    {
        $result = $this->module->framework->getSystemSetting("lockout-seconds");
        if ( !intval($result) ) {
            // DEFAULT TO 300 SECONDS IF NOT SET
            $result = 300;
        }
        return $result;
    }

    public function getEmailFromAddress()
    {
        $result = \REDCap::escapeHtml($this->module->framework->getSystemSetting("email-from-address"));
        if ( !isset($result) || $result === "" ) {
            $result = "noreply@REDCapPRO.com";
        }
        return $result;
    }

    /**
     * This function is lifted from the EM Framework v5
     * 
     * Finds all available language files for a given module.
     * 
     * @param string $prefix The module prefix.
     * @param string $version The version of the module.
     * 
     * @return array An associative array with the language names as keys and the full path to the INI file as values.
     */
    public function getLanguageFiles()
    {
        $langs = array();
        $path  = $this->module->framework->getModulePath() . DS . "lang" . DS;
        if ( is_dir($path) ) {
            $files = glob($path . "*.{i,I}{n,N}{i,I}", GLOB_BRACE);
            foreach ( $files as $filename ) {
                if ( is_file($filename) ) {
                    $thisLang         = pathinfo($filename, PATHINFO_FILENAME);
                    $langs[$thisLang] = $filename;
                }
            }
        }
        return $langs;
    }

    /**
     * Checks whether email login is allowed in this project
     * 
     * @param int $pid The redcap project ID
     * 
     * @return bool Whether email logins are allowed in this project
     */
    public function emailLoginsAllowed(int $pid)
    {
        $emailLoginPreventedSystem  = $this->module->framework->getSystemSetting("prevent-email-login-system");
        $emailLoginPreventedProject = $this->module->framework->getProjectSetting("prevent-email-login", $pid);

        return !($emailLoginPreventedSystem === true || $emailLoginPreventedProject === true);
    }

    /**
     * Checks whether MFA is enabled in this project
     *
     * @param int $project_id The redcap project ID
     *
     * @return bool Whether MFA is enabled in this project
     */
    public function mfaEnabled(int $project_id)
    {
        $mfaEnabledSystem  = $this->module->framework->getSystemSetting("mfa-system");
        $mfaEnabledProject = $this->module->framework->getProjectSetting("mfa", $project_id);

        return $mfaEnabledSystem === true && $mfaEnabledProject === true;
    }

    /**
     * Checks whether an authenticator app is enabled for this project
     * 
     * @param int $pid The redcap project ID
     * 
     * @return bool Whether an authenticator app is enabled for this project
     */
    public function mfaAuthenticatorAppEnabled(int $pid)
    {
        $mfaAuthenticatorAppEnabledSystem  = $this->module->framework->getSystemSetting("mfa-authenticator-app-system");
        $mfaAuthenticatorAppEnabledProject = $this->module->framework->getProjectSetting("mfa-authenticator-app", $pid);

        return $mfaAuthenticatorAppEnabledSystem === true && $mfaAuthenticatorAppEnabledProject === true;
    }




    public function shouldAllowSelfRegistration(int $pid) : bool
    {
        $allowSelfRegistrationSystem  = $this->module->framework->getSystemSetting("allow-self-registration-system");
        $allowSelfRegistrationProject = $this->module->getProjectSetting("allow-self-registration", $pid);

        return $allowSelfRegistrationSystem === true && $allowSelfRegistrationProject === true;
    }

    public function shouldEnrollUponRegistration(int $pid) : bool
    {
        $enrollUponRegistrationSystem  = $this->module->framework->getSystemSetting("allow-auto-enroll-upon-self-registration-system");
        $enrollUponRegistrationProject = $this->module->getProjectSetting("auto-enroll-upon-self-registration", $pid);

        return $enrollUponRegistrationSystem === true && $enrollUponRegistrationProject === true;
    }

    public function getAutoEnrollNotificationEmail(int $pid) : string
    {
        return $this->module->getProjectSetting("auto-enroll-notification-email", $pid) ?? "";
    }

    /**
     * Checks whether API is enabled in this project
     * 
     * @param int $pid The redcap project ID
     * 
     * @return bool Whether API is enabled in this project
     */
    public function apiEnabled(int $pid)
    {
        $apiEnabledSystem  = $this->module->framework->getSystemSetting("api-enabled-system");
        $apiEnabledProject = $this->module->getProjectSetting("api-enabled", $pid);

        return $apiEnabledSystem === true && $apiEnabledProject === true;
    }
}