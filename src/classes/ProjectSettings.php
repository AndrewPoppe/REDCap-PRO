<?php

namespace YaleREDCap\REDCapPRO;

class ProjectSettings
{
    public static $module;

    function __construct($module)
    {
        self::$module = $module;
    }

    public function getTimeoutWarningMinutes()
    {
        $result = self::$module->getSystemSetting("warning-time");
        if (!floatval($result)) {
            // DEFAULT TO 1 MINUTE IF NOT SET
            $result = 1;
        }
        return $result;
    }

    public function getTimeoutMinutes()
    {
        $result = self::$module->getSystemSetting("timeout-time");
        if (!floatval($result)) {
            // DEFAULT TO 5 MINUTES IF NOT SET
            $result = 5;
        }
        return $result;
    }

    public function getPasswordLength()
    {
        $result = self::$module->getSystemSetting("password-length");
        if (!intval($result)) {
            // DEFAULT TO 8 CHARACTERS IF NOT SET
            $result = 8;
        }
        return $result;
    }

    public function getLoginAttempts()
    {
        $result = self::$module->getSystemSetting("login-attempts");
        if (!intval($result)) {
            // DEFAULT TO 3 ATTEMPTS IF NOT SET
            $result = 3;
        }
        return $result;
    }

    public function getLockoutDurationSeconds()
    {
        $result = self::$module->getSystemSetting("lockout-seconds");
        if (!intval($result)) {
            // DEFAULT TO 300 SECONDS IF NOT SET
            $result = 300;
        }
        return $result;
    }


    /**
     * Gets the requested project setting and sets the provided value if no 
     * setting is found.
     * 
     * @param mixed $pid
     * @param string $setting
     * @param mixed $default
     * 
     * @return mixed setting value
     */
    private function getProjectSetting(string $setting, $pid, $default = null)
    {
        $value = self::$module->getProjectSetting($setting, $pid);
        if (!isset($value)) {
            $value = $default;
            self::$module->setProjectSetting($setting, $value, $pid);
        }
        return $value;
    }

    /**
     * Grab project-level settings and return as array
     * 
     * @param mixed $pid
     * 
     * @return array|Exception
     */
    public function getProjectSettings($pid)
    {
        try {

            $result = [
                "primary-contact" => [
                    "name"  => $this->getProjectSetting("pc-name", $pid),
                    "email" => $this->getProjectSetting("pc-email", $pid),
                    "phone" => $this->getProjectSetting("pc-phone", $pid),
                ],
                "language" => $this->getProjectSetting("reserved-language-project", $pid),
                "prevent-email-login" => self::$module->getProjectSetting("prevent-email-login"),
            ];
        } catch (\Exception $e) {
            self::$module->logError("Error fetching project settings", $e);
        }
        return $result;
    }

    /**
     * Set project setting values
     * 
     * @param array $settings
     * @param mixed $pid
     * 
     * @return 
     */
    public function setProjectSettings(array $settings, $pid)
    {
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
        $path = self::$module->getModulePath() . DS . "lang" . DS;
        if (is_dir($path)) {
            $files = glob($path . "*.{i,I}{n,N}{i,I}", GLOB_BRACE);
            foreach ($files as $filename) {
                if (is_file($filename)) {
                    $lang = pathinfo($filename, PATHINFO_FILENAME);
                    $langs[$lang] = $filename;
                }
            }
        }
        return $langs;
    }
}
