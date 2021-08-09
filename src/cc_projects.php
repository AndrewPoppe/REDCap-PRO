<?php

namespace YaleREDCap\REDCapPRO;

require_once("classes/Project.php");

if (!SUPER_USER) {
    return;
}

?>
<!DOCTYPE html>
<html lang='en'>

<head>
    <meta charset='UTF-8'>
    <title>REDCapPRO Projects</title>
    <link rel="stylesheet" type="text/css" href="<?= $module->getUrl("css/rcpro.css") ?>">
</head>

<body>
    <?php
    require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';
    $module::$UI->ShowControlCenterHeader("Projects");
    $redcap_project_ids = $module->getProjectsWithModuleEnabled();
    ?>
    <div class="projectsContainer wrapper">
        <h2>Projects</h2>
        <p>All projects currently utilizing REDCapPRO</p>
        <div id="loading-container" class="loader-container">
            <div id="loading" class="loader"></div>
        </div>
        <div id="projects" class="dataTableParentHidden outer_container">
            <table class="table" id="RCPRO_TABLE" style="width:100%;">
                <caption>REDCapPRO Projects</caption>
                <thead>
                    <th class='dt-center'>Project ID</th>
                    <th class='dt-center'>REDCap PID</th>
                    <th>Title</th>
                    <th class='dt-center'>Status</th>
                    <th class='dt-center'># Participants</th>
                    <th class='dt-center'># Staff Members</th>
                    <th class='dt-center'># Records</th>
                </thead>
                <tbody>
                    <?php
                    foreach ($redcap_project_ids as $id) {
                        $thisProject                = new Project($module, $id);
                        $rcpro_project_id           = $module::$PROJECT->getProjectIdFromPID($thisProject::$redcap_pid);
                        $project_rcpro_home         = $module->getUrl("src/home.php?pid=${id}");
                        $project_home               = APP_PATH_WEBROOT_FULL . APP_PATH_WEBROOT . "index.php?pid=${id}";
                        $project_rcpro_manage       = $module->getUrl("src/manage.php?pid=${id}");
                        $project_rcpro_manage_users = $module->getUrl("src/manage-users.php?pid=${id}");
                        $project_records            = APP_PATH_WEBROOT_FULL . APP_PATH_WEBROOT . "DataEntry/record_status_dashboard.php?pid=${id}"
                    ?>
                        <tr>
                            <!-- Project ID -->
                            <td class='dt-center rcpro_participant_link' onclick="(function(){window.open('<?= $project_rcpro_home ?>', '_blank').focus();})()">
                                <?= $rcpro_project_id ?>
                            </td>
                            <!-- REDCap PID -->
                            <td class='dt-center'>
                                <?= $id ?>
                            </td>
                            <!-- Title -->
                            <td>
                                <?= $thisProject::$info["app_title"] ?>
                            </td>
                            <!-- Status -->
                            <td class='dt-center'>
                                <?= $thisProject->getStatus() ?>
                            </td>
                            <!-- # Participants -->
                            <td class='dt-center rcpro_participant_link' onclick="(function(){window.open('<?= $project_rcpro_manage ?>', '_blank').focus();})()">
                                <?= $thisProject->getParticipantCount($rcpro_project_id) ?>
                            </td>
                            <!-- # Staff Members -->
                            <td class='dt-center rcpro_participant_link' onclick="(function(){window.open('<?= $project_rcpro_manage_users ?>', '_blank').focus();})()">
                                <?= count($thisProject::$staff["allStaff"]) ?>
                            </td>
                            <!-- # Records -->
                            <td class='dt-center rcpro_participant_link' onclick="(function(){window.open('<?= $project_records ?>', '_blank').focus();})()">
                                <?= $thisProject->getRecordCount() ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
        (function($, window, document) {
            $(document).ready(function() {
                let dataTable = $('#RCPRO_TABLE').DataTable({
                    dom: 'lBfrtip',
                    stateSave: true,
                    stateSaveCallback: function(settings, data) {
                        localStorage.setItem('DataTables_' + settings.sInstance, JSON.stringify(data))
                    },
                    stateLoadCallback: function(settings) {
                        return JSON.parse(localStorage.getItem('DataTables_' + settings.sInstance))
                    },
                    scrollY: '50vh',
                    scrollCollapse: true,
                    pageLength: 100
                });

                $('#projects').removeClass('dataTableParentHidden');
                $('#loading-container').hide();
                dataTable.columns.adjust().draw();

            });
        }(window.jQuery, window, document));
    </script>
</body>

</html>