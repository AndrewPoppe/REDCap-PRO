<?php

namespace YaleREDCap\REDCapPRO;

class ProjectSettings
{

    private $module;

    function __construct(REDCapPRO $module)
    {
        $this->module = $module;
    }

    public function getTimeoutWarningMinutes()
    {
        $result = $this->module->getSystemSetting("warning-time");
        if (!floatval($result)) {
            // DEFAULT TO 1 MINUTE IF NOT SET
            $result = 1;
        }
        return $result;
    }

    public function getTimeoutMinutes()
    {
        $result = $this->module->getSystemSetting("timeout-time");
        if (!floatval($result)) {
            // DEFAULT TO 5 MINUTES IF NOT SET
            $result = 5;
        }
        return $result;
    }

    public function getPasswordLength()
    {
        $result = $this->module->getSystemSetting("password-length");
        if (!intval($result)) {
            // DEFAULT TO 8 CHARACTERS IF NOT SET
            $result = 8;
        }
        return $result;
    }

    public function getLoginAttempts()
    {
        $result = $this->module->getSystemSetting("login-attempts");
        if (!intval($result)) {
            // DEFAULT TO 3 ATTEMPTS IF NOT SET
            $result = 3;
        }
        return $result;
    }

    public function getLockoutDurationSeconds()
    {
        $result = $this->module->getSystemSetting("lockout-seconds");
        if (!intval($result)) {
            // DEFAULT TO 300 SECONDS IF NOT SET
            $result = 300;
        }
        return $result;
    }

    public function getEmailFromAddress()
    {
        $result = \REDCap::escapeHtml($this->module->getSystemSetting("email-from-address"));
        if (!isset($result) || $result === "") {
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
     * @return Array An associative array with the language names as keys and the full path to the INI file as values.
     */
    public function getLanguageFiles()
    {
        $langs = array();
        $path = $this->module->getModulePath() . constant("DS") . "lang" . constant("DS");
        if (is_dir($path)) {
            $files = glob($path . "*.{i,I}{n,N}{i,I}", constant("GLOB_BRACE"));
            foreach ($files as $filename) {
                if (is_file($filename)) {
                    $lang = pathinfo($filename, constant("PATHINFO_FILENAME"));
                    $langs[$lang] = $filename;
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
        $emailLoginPreventedSystem  = $this->module->getSystemSetting("prevent-email-login-system");
        $emailLoginPreventedProject = $this->module->getProjectSetting("prevent-email-login", $pid);

        return !($emailLoginPreventedSystem === true || $emailLoginPreventedProject === true);
    }
}
