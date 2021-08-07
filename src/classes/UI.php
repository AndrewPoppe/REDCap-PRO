<?php

namespace YaleREDCap\REDCapPRO;

class UI
{

    public static $module;
    function __construct($module)
    {
        self::$module = $module;
    }

    public function ShowParticipantHeader(string $title)
    {
        echo '<!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <title>REDCapPRO ' . $title . '</title>
                    <link rel="shortcut icon" href="' . self::$module->getUrl("images/favicon.ico") . '"/>
                    <link rel="icon" type="image/png" sizes="32x32" href="' . self::$module->getUrl("images/favicon-32x32.png") . '">
                    <link rel="icon" type="image/png" sizes="16x16" href="' . self::$module->getUrl("images/favicon-16x16.png") . '">
                    <link rel="stylesheet" href="' . self::$module->getUrl("lib/bootstrap/css/bootstrap.min.css") . '">
                    <script async src="' . self::$module->getUrl("lib/bootstrap/js/bootstrap.bundle.min.js") . '"></script>
                    <style>
                        body { font: 14px sans-serif; }
                        .wrapper { width: 360px; padding: 20px; }
                        .form-group { margin-top: 20px; }
                        .center { display: flex; justify-content: center; align-items: center; }
                        img#rcpro-logo { position: relative; left: -125px; }
                    </style>
                </head>
                <body>
                    <div class="center">
                        <div class="wrapper">
                            <img id="rcpro-logo" src="' . self::$module->getUrl("images/RCPro_Logo.svg") . '" width="500px"></img>
                            <hr>
                            <div style="text-align: center;"><h2>' . $title . '</h2></div>';
    }

    public function EndParticipantPage()
    {
        echo '</div></div></body></html>';
    }

    public function ShowHeader(string $page)
    {
        $role = SUPER_USER ? 3 : self::$module->getUserRole(USERID); // 3=admin/manager, 2=user, 1=monitor, 0=not found
        $header = "
        <style>
            .rcpro-nav a {
                color: #900000 !important;
                font-weight: bold !important;
            }
            .rcpro-nav a.active:hover {
                color: #900000 !important;
                font-weight: bold !important;
                outline: none !important;
            }
            .rcpro-nav a:hover:not(.active), a:focus {
                color: #900000 !important;
                font-weight: bold !important;
                border: 1px solid #c0c0c0 !important;
                background-color: #e1e1e1 !important;
                outline: none !important;
            }
            .rcpro-nav a:not(.active) {
                background-color: #f7f6f6 !important;
                border: 1px solid #e1e1e1 !important;
                outline: none !important;
            }
        </style>
        <link rel='shortcut icon' href='" . self::$module->getUrl('images/favicon.ico') . "'/>
        <link rel='icon' type='image/png' sizes='32x32' href='" . self::$module->getUrl('images/favicon-32x32.png') . "'>
        <link rel='icon' type='image/png' sizes='16x16' href='" . self::$module->getUrl('images/favicon-16x16.png') . "'>
        <div>
            <img src='" . self::$module->getUrl("images/RCPro_Logo.svg") . "' width='500px'></img>
            <br>
            <nav style='margin-top:20px;'><ul class='nav nav-tabs rcpro-nav'>
                <li class='nav-item'>
                    <a class='nav-link " . ($page === "Home" ? "active" : "") . "' aria-current='page' href='" . self::$module->getUrl("src/home.php") . "'>
                    <i class='fas fa-home'></i>
                    Home</a>
                </li>";
        if ($role >= 1) {
            $header .= "<li class='nav-item'>
                            <a class='nav-link " . ($page === "Manage" ? "active" : "") . "' href='" . self::$module->getUrl("src/manage.php") . "'>
                            <i class='fas fa-users-cog'></i>
                            Manage Participants</a>
                        </li>";
        }
        if ($role >= 2) {
            $header .= "<li class='nav-item'>
                            <a class='nav-link " . ($page === "Enroll" ? "active" : "") . "' href='" . self::$module->getUrl("src/enroll.php") . "'>
                            <i class='fas fa-user-check'></i>
                            Enroll</a>
                        </li>
                        <li class='nav-item'>
                            <a class='nav-link " . ($page === "Register" ? "active" : "") . "' href='" . self::$module->getUrl("src/register.php") . "'>
                            <i class='fas fa-id-card'></i>
                            Register</a>
                        </li>";
        }
        if ($role > 2) {
            $header .= "<li class='nav-item'>
                            <a class='nav-link " . ($page === "Users" ? "active" : "") . "' href='" . self::$module->getUrl("src/manage-users.php") . "'>
                            <i class='fas fa-users'></i>
                            Study Staff</a>
                        </li>
                        <li class='nav-item'>
                            <a class='nav-link " . ($page === "Logs" ? "active" : "") . "' href='" . self::$module->getUrl("src/logs.php") . "'>
                            <i class='fas fa-list'></i>
                            Logs</a>
                        </li>";
        }
        $header .= "</ul></nav>
            </div>";
        echo $header;
    }

    public function ShowControlCenterHeader(string $page)
    {
        $header = "
        <style>
            .rcpro-nav a {
                color: #900000 !important;
                font-weight: bold !important;
            }
            .rcpro-nav a.active:hover {
                color: #900000 !important;
                font-weight: bold !important;
                outline: none !important;
            }
            .rcpro-nav a:hover:not(.active), a:focus {
                color: #900000 !important;
                font-weight: bold !important;
                border: 1px solid #c0c0c0 !important;
                background-color: #e1e1e1 !important;
                outline: none !important;
            }
            .rcpro-nav a:not(.active) {
                background-color: #f7f6f6 !important;
                border: 1px solid #e1e1e1 !important;
                outline: none !important;
            }
        </style>
        <link rel='shortcut icon' href='" . self::$module->getUrl('images/favicon.ico') . "'/>
        <link rel='icon' type='image/png' sizes='32x32' href='" . self::$module->getUrl('images/favicon-32x32.png') . "'>
        <link rel='icon' type='image/png' sizes='16x16' href='" . self::$module->getUrl('images/favicon-16x16.png') . "'>
        <div>
            <img src='" . self::$module->getUrl("images/RCPro_Logo.svg") . "' width='500px'></img>
            <br>
            <nav style='margin-top:20px;'><ul class='nav nav-tabs rcpro-nav'>
                <li class='nav-item'>
                    <a class='nav-link " . ($page === "Projects" ? "active" : "") . "' aria-current='page' href='" . self::$module->getUrl("src/cc_projects.php") . "'>
                    <i class='fas fa-briefcase'></i>
                    Projects</a>
                </li>
                <li class='nav-item'>
                    <a class='nav-link " . ($page === "Participants" ? "active" : "") . "' href='" . self::$module->getUrl("src/cc_participants.php") . "'>
                    <i class='fas fa-users-cog'></i>
                    Participants</a>
                </li>
                <li class='nav-item'>
                    <a class='nav-link " . ($page === "Staff" ? "active" : "") . "' href='" . self::$module->getUrl("src/cc_staff.php") . "'>
                    <i class='fas fa-users'></i>
                    Staff</a>
                </li>
                <li class='nav-item'>
                    <a class='nav-link " . ($page === "Logs" ? "active" : "") . "' href='" . self::$module->getUrl("src/cc_logs.php") . "'>
                    <i class='fas fa-list'></i>
                    Logs</a>
                </li>
        ";

        $header .= "</ul></nav>
        </div><hr style='margin-top:0px;'>";
        echo $header;
    }
}
