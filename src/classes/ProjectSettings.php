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
}
