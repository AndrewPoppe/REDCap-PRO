<?php

namespace YaleREDCap\REDCapPRO;

class UI
{

    public REDCapPRO $module;
    private $language;
    private $languageList;
    private $currentLanguage;
    private $languageDirection;
    function __construct($module)
    {
        $this->module = $module;
        $this->language = new Language($this->module);
        $this->languageList = $this->language->getLanguages(true);
        $this->currentLanguage = $this->language->getCurrentLanguage();
        $this->languageDirection = $this->language->getCurrentLanguageDirection();
    }

    private function showLanguageOptions()
    {
        $response = '';
        if (count($this->languageList) > 1) {
            $response .= '<div class="langOptions">
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
                        setTimeout(() => {
                            
                            const tooltipTriggerList = [].slice.call(document.querySelectorAll(`.langOptions [data-bs-toggle="tooltip"]`));
                            const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                                return new bootstrap.Tooltip(tooltipTriggerEl, {
                                    container: ".wrapper",
                                    trigger: "hover"
                                });
                            });

                        }, 10);
                    });
                </script>';
            
        }
        return $response;
    }

    public function ShowParticipantHeader(string $title)
    {
        $bootstrapCSS = $this->languageDirection === 'rtl' ? 
        '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.rtl.min.css" integrity="sha384-CfCrinSRH2IR6a4e6fy2q6ioOX7O6Mtm1L9vRvFZ1trBncWmMePhzvafv7oIcWiW" crossorigin="anonymous">' : 
        '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">';
        echo '<!DOCTYPE html>
                <html lang="en">
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
                <body dir="' . $this->languageDirection . '">
                    <div class="center flex-column p-3">
                        <div id="rcpro-header" class="row d-flex align-items-start justify-content-center">
                            <div style="width: 100px;"></div>
                            <div style="width: 500px;">
                                <img id="rcpro-logo" src="' . $this->module->getUrl("images/RCPro_Logo_Alternate.svg") . '">
                            </div>
                            <div style="width: 100px;">
                                ' . $this->showLanguageOptions() . '
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
        <script>
            document.body.dir = '" . $this->languageDirection . "';
        </script>
        <div>
            <div class='flex-row d-flex align-items-center'>
            <img class='me-3' src='" . $this->module->getUrl("images/RCPro_Logo.svg") . "' width='395px'>
            " . $this->showLanguageOptions() . "
            </div>
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
        <script>
            document.body.dir = '" . $this->languageDirection . "';
        </script>
        <div>
            <img src='" . $this->module->getUrl("images/RCPro_Logo.svg") . "' width='500px'>
            <br>
            <nav style='margin-top:20px;'><ul class='nav nav-tabs rcpro-nav border-bottom'>
                <li class='nav-item'>
                    <a class='nav-link " . ($page === "Projects" ? "active" : "") . "' aria-current='page' href='" . $this->module->getUrl("src/cc_projects.php") . "'>
                    <i class='fas fa-briefcase'></i>
                    " . $this->module->tt("cc_projects") . "</a>
                </li>
                <li class='nav-item'>
                    <a class='nav-link " . ($page === "Participants" ? "active" : "") . "' href='" . $this->module->getUrl("src/cc_participants.php") . "'>
                    <i class='fas fa-users-cog'></i>
                    " . $this->module->tt("cc_participants") . "</a>
                </li>
                <li class='nav-item'>
                    <a class='nav-link " . ($page === "Staff" ? "active" : "") . "' href='" . $this->module->getUrl("src/cc_staff.php") . "'>
                    <i class='fas fa-users'></i>
                    " . $this->module->tt("cc_staff") . "</a>
                </li>
                <li class='nav-item'>
                    <a class='nav-link " . ($page === "Logs" ? "active" : "") . "' href='" . $this->module->getUrl("src/cc_logs.php") . "'>
                    <i class='fas fa-list'></i>
                    " . $this->module->tt("cc_logs") . "</a>
                </li>
        ";

        $header .= "</ul></nav>
        </div><hr class='border-0 mt-0'>";
        echo $header;
    }
}