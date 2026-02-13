<?php

namespace YaleREDCap\REDCapPRO;

class UI
{

    public REDCapPRO $module;
    private $language;
    private $languageList;
    private $currentLanguage;
    function __construct($module)
    {
        $this->module = $module;
        $this->language = new Language($this->module);
        $this->languageList = $this->language->getLanguages(true);
        $this->currentLanguage = $this->language->getCurrentLanguage();
    }

    private function showLanguageOptions()
    {
        $response = '';
        if (count($this->languageList) > 1) {
            $response .= '<div class="">
                <div class="dropdown" data-bs-toggle="tooltip" data-bs-title="' . $this->module->framework->tt("ui_language_selection_label") . '">
                <button type="button" class="btn btn-lg text-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-solid fa-language"></i>
                </button>
                <ul class="dropdown-menu" id="languageDropdownMenu">
                ';
            foreach ($this->languageList as $lang_item) {
                $response .= '<li class="dropdown-item"><a href="" class="languageSelect" value="' . $lang_item['code'] . '">' . $lang_item['code'] . '</a></li>';
            }
                $response .= '</ul></div></div>
                <div class="modal" id="loadingModal" tabindex="-1" aria-labelledby="loadingModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                    <div class="modal-dialog modal-dialog-centered modal-sm">
                        <div class="modal-content" style="background-color: transparent; border: none;">
                            <div class="modal-body text-center">
                                <div class="spinner-border" role="status" style="width: 3rem; height: 3rem; color:' . $this->module::$COLORS["primary"] . ';" !important;">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <script>
                    function showLoadingModal() {
                        const loadingModal = new bootstrap.Modal(document.getElementById(`loadingModal`), {
                            backdrop: `static`,
                            keyboard: false
                        });
                        loadingModal.show();
                        }
                    function hideLoadingModal() {
                        const loadingModalElement = document.getElementById(`loadingModal`);
                        const loadingModalInstance = bootstrap.Modal.getInstance(loadingModalElement);
                        if (loadingModalInstance) {
                            loadingModalInstance.hide();
                        }
                    }
                    document.addEventListener("DOMContentLoaded", (event) => {
                        document.querySelectorAll(".languageSelect").forEach(item => {
                            selectedLang = item.getAttribute("value");
                            const url = new URL(window.location.href);
                            url.searchParams.set("language", selectedLang);
                            item.href = url.toString();
                            item.addEventListener("click", (e) => {
                                showLoadingModal();
                            });
                        });
                        const tooltipTriggerList = [].slice.call(document.querySelectorAll(`[data-bs-toggle="tooltip"]`));
                        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                            return new bootstrap.Tooltip(tooltipTriggerEl, {
                                trigger: "hover"
                            });
                        });
                    });
                </script>';
            
        }
        return $response;
    }

    private function showLanguageOptions()
    {
        $response = '';
        if (count($this->languageList) > 1) {
            $response .= '<div class="">
                <div class="dropdown" data-bs-toggle="tooltip" data-bs-title="' . $this->module->framework->tt("ui_language_selection_label") . '">
                <button type="button" class="btn btn-lg text-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-solid fa-language"></i>
                </button>
                <ul class="dropdown-menu" id="languageDropdownMenu">
                ';
            foreach ($this->languageList as $lang_item) {
                $response .= '<li class="dropdown-item"><a href="" class="languageSelect" value="' . $lang_item['code'] . '">' . $lang_item['code'] . '</a></li>';
            }
                $response .= '</ul></div></div>
                <div class="modal" id="loadingModal" tabindex="-1" aria-labelledby="loadingModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                    <div class="modal-dialog modal-dialog-centered modal-sm">
                        <div class="modal-content" style="background-color: transparent; border: none;">
                            <div class="modal-body text-center">
                                <div class="spinner-border" role="status" style="width: 3rem; height: 3rem; color:' . $this->module::$COLORS["primary"] . ';" !important;">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <script>
                    function showLoadingModal() {
                        const loadingModal = new bootstrap.Modal(document.getElementById(`loadingModal`), {
                            backdrop: `static`,
                            keyboard: false
                        });
                        loadingModal.show();
                        }
                    function hideLoadingModal() {
                        const loadingModalElement = document.getElementById(`loadingModal`);
                        const loadingModalInstance = bootstrap.Modal.getInstance(loadingModalElement);
                        if (loadingModalInstance) {
                            loadingModalInstance.hide();
                        }
                    }
                    document.addEventListener("DOMContentLoaded", (event) => {
                        document.querySelectorAll(".languageSelect").forEach(item => {
                            selectedLang = item.getAttribute("value");
                            const url = new URL(window.location.href);
                            url.searchParams.set("language", selectedLang);
                            item.href = url.toString();
                            item.addEventListener("click", (e) => {
                                showLoadingModal();
                            });
                        });
                        const tooltipTriggerList = [].slice.call(document.querySelectorAll(`[data-bs-toggle="tooltip"]`));
                        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                            return new bootstrap.Tooltip(tooltipTriggerEl, {
                                trigger: "hover"
                            });
                        });
                    });
                </script>';
            
        }
        return $response;
    }

    public function ShowParticipantHeader(string $title)
    {
        $customLogoEnabled = (bool) $this->module->framework->getSystemSetting('allow-custom-logo-system');
        $customLogo        = $customLogoEnabled ? $this->module->framework->getProjectSetting('project-header-logo') : null;
        if ( !empty($customLogo) ) {
            $participantLogo = $customLogo;
        } else {
            $systemLogoEnabled = (bool) $this->module->framework->getSystemSetting('system-header-logo-enabled');
            $systemLogoEdoc   = $this->module->framework->getSystemSetting('system-header-logo-file');
            if ( $systemLogoEnabled && !empty($systemLogoEdoc) ) {
                try {
                    [$mime, , $content] = \REDCap::getFile($systemLogoEdoc);
                    $participantLogo = 'data:' . $mime . ';base64,' . base64_encode($content);
                } catch ( \Throwable $e ) {
                    $participantLogo = $this->module->getUrl('images/RCPro_Logo_Alternate.svg');
                }
            } else {
                $participantLogo = $this->module->getUrl('images/RCPro_Logo_Alternate.svg');
            }
        }
        echo '<!DOCTYPE html>
                <html lang="en" dir="' . $dir . '">
                <head>
                    <meta charset="UTF-8">
                    <title>REDCapPRO ' . $title . '</title>
                    <link rel="preconnect" href="https://fonts.googleapis.com">
                    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
                    <link href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
                    <link rel="shortcut icon" href="' . $this->module->getUrl("images/favicon.ico") . '"/>
                    <link rel="icon" type="image/png" sizes="32x32" href="' . $this->module->getUrl("images/favicon-32x32.png") . '">
                    <link rel="icon" type="image/png" sizes="16x16" href="' . $this->module->getUrl("images/favicon-16x16.png") . '">
                    '. $bootstrapCSS . '
                    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
                    <script src="https://kit.fontawesome.com/f60568a59c.js" crossorigin="anonymous"></script>
                    <style>
                        body {  font-family: "Atkinson Hyperlegible", sans-serif; }
                        .wrapper { width: 360px; }
                        .form-group { margin-top: 20px; }
                        .center { display: flex; justify-content: center; align-items: center; }
                    </style>
                </head>
                <body>
                    <div class="center flex-column p-3">
                        <div id="rcpro-header" class="row d-flex align-items-start justify-content-center">
                            <div style="width: 500px;">
                                <img id="rcpro-logo" class="w-100" src="' . $participantLogo . '">
                            </div>
                        </div>
                        <div class="wrapper px-3">
                            <hr>
                            
                            <div style="text-align: center;"><h2 class="title">' . $title . '</h2></div>';
    }

    public function EndParticipantPage()
    {

        echo '</div></div></body></html>';
        
    }

    public function ShowHeader(string $page)
    {
        $role   = $this->module->getUserRole($this->module->safeGetUsername()); // 3=admin/manager, 2=user, 1=monitor, 0=not found
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
        <link rel='shortcut icon' href='" . $this->module->getUrl('images/favicon.ico') . "'/>
        <link rel='icon' type='image/png' sizes='32x32' href='" . $this->module->getUrl('images/favicon-32x32.png') . "'>
        <link rel='icon' type='image/png' sizes='16x16' href='" . $this->module->getUrl('images/favicon-16x16.png') . "'>
        <script src='https://kit.fontawesome.com/f60568a59c.js' crossorigin='anonymous'></script>
        <div>
            <img src='" . $this->module->getUrl("images/RCPro_Logo.svg") . "' width='395px'>
            <br>
            <nav style='margin-top:20px;'><ul class='nav nav-tabs rcpro-nav'>
                <li class='nav-item'>
                    <a class='nav-link " . ($page === "Home" ? "active" : "") . "' aria-current='page' href='" . $this->module->getUrl("src/home.php") . "'>
                    <i class='fas fa-home'></i>
                    " . $this->module->tt("project_home_title") . "</a>
                </li>";
        if ( $role >= 1 ) {
            $header .= "<li class='nav-item'>
                            <a class='nav-link " . ($page === "Manage" ? "active" : "") . "' href='" . $this->module->getUrl("src/manage.php") . "'>
                            <i class='fas fa-users-cog'></i>
                            " . $this->module->tt("project_manage_participants_title") . "</a>
                        </li>";
        }
        if ( $role >= 2 ) {
            $header .= "<li class='nav-item'>
                            <a class='nav-link " . ($page === "Enroll" ? "active" : "") . "' href='" . $this->module->getUrl("src/enroll.php") . "'>
                            <i class='fas fa-user-check'></i>
                            " . $this->module->tt("project_enroll_title") . "</a>
                        </li>
                        <li class='nav-item'>
                            <a class='nav-link " . ($page === "Register" ? "active" : "") . "' href='" . $this->module->getUrl("src/register.php") . "'>
                            <i class='fas fa-id-card'></i>
                            " . $this->module->tt("project_register_title") . "</a>
                        </li>";
        }
        if ( $role > 2 ) {
            $header .= "<li class='nav-item'>
                            <a class='nav-link " . ($page === "Users" ? "active" : "") . "' href='" . $this->module->getUrl("src/manage-users.php") . "'>
                            <i class='fas fa-users'></i>
                            " . $this->module->tt("project_study_staff_title") . "</a>
                        </li>
                        <li class='nav-item'>
                            <a class='nav-link " . ($page === "Settings" ? "active" : "") . "' href='" . $this->module->getUrl("src/settings.php") . "'>
                            <i class='fas fa-cog'></i>
                            " . $this->module->tt("project_settings_title") . "</a>
                        </li>
                        <li class='nav-item'>
                            <a class='nav-link " . ($page === "Logs" ? "active" : "") . "' href='" . $this->module->getUrl("src/logs.php") . "'>
                            <i class='fas fa-list'></i>
                            " . $this->module->tt("project_logs_title") . "</a>
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
        <link rel='shortcut icon' href='" . $this->module->getUrl('images/favicon.ico') . "'/>
        <link rel='icon' type='image/png' sizes='32x32' href='" . $this->module->getUrl('images/favicon-32x32.png') . "'>
        <link rel='icon' type='image/png' sizes='16x16' href='" . $this->module->getUrl('images/favicon-16x16.png') . "'>
        <div>
            <img src='" . $this->module->getUrl("images/RCPro_Logo.svg") . "' width='500px'>
            <br>
            <nav style='margin-top:20px;'><ul class='nav nav-tabs rcpro-nav'>
                <li class='nav-item'>
                    <a class='nav-link " . ($page === "Projects" ? "active" : "") . "' aria-current='page' href='" . $this->module->getUrl("src/cc_projects.php") . "'>
                    <i class='fas fa-briefcase'></i>
                    Projects</a>
                </li>
                <li class='nav-item'>
                    <a class='nav-link " . ($page === "Participants" ? "active" : "") . "' href='" . $this->module->getUrl("src/cc_participants.php") . "'>
                    <i class='fas fa-users-cog'></i>
                    Participants</a>
                </li>
                <li class='nav-item'>
                    <a class='nav-link " . ($page === "Staff" ? "active" : "") . "' href='" . $this->module->getUrl("src/cc_staff.php") . "'>
                    <i class='fas fa-users'></i>
                    Staff</a>
                </li>
                <li class='nav-item'>
                    <a class='nav-link " . ($page === "Logs" ? "active" : "") . "' href='" . $this->module->getUrl("src/cc_logs.php") . "'>
                    <i class='fas fa-list'></i>
                    Logs</a>
                </li>
        ";

        $header .= "</ul></nav>
        </div><hr style='margin-top:0px;'>";
        echo $header;
    }
}