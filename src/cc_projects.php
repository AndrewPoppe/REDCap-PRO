<?php

namespace YaleREDCap\REDCapPRO;

if (!SUPER_USER) {
    return;
}

?>

<title>REDCapPRO Projects</title>
<link rel="stylesheet" type="text/css" href="<?= $module->getUrl("src/css/rcpro_cc.php") ?>">

<?php
require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';
$module::$UI->ShowControlCenterHeader("Projects");
$redcap_project_ids = $module->getProjectsWithModuleEnabled();
?>
<div id="loading-container" class="loader-container">
    <div id="loading" class="loader"></div>
</div>
<div class="projectsContainer wrapper" style="display: none;">
    <h2>Projects</h2>
    <p>All projects currently utilizing REDCapPRO</p>
    <div id="projects" class="dataTableParentHidden outer_container">
        <table class="table" id="RCPRO_TABLE" style="width:100%;">
            <caption>REDCapPRO Projects</caption>
            <thead>
                <th scope="col" class='dt-center'>Project ID</th>
                <th scope="col" class='dt-center'>REDCap PID</th>
                <th scope="col">Title</th>
                <th scope="col" class='dt-center'>Status</th>
                <th scope="col" class='dt-center'># Participants</th>
                <th scope="col" class='dt-center'># Staff Members</th>
                <th scope="col" class='dt-center'># Records</th>
            </thead>
            <tbody>
                <?php
                foreach ($redcap_project_ids as $id) {
                    $project                    = new Project($module, ["redcap_pid" => $id]);
                    $rcpro_project_id           = $project->rcpro_project_id;
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
                            <?= $project->info["app_title"] ?>
                        </td>
                        <!-- Status -->
                        <td class='dt-center'>
                            <?= $project->getStatus() ?>
                        </td>
                        <!-- # Participants -->
                        <td class='dt-center rcpro_participant_link' onclick="(function(){window.open('<?= $project_rcpro_manage ?>', '_blank').focus();})()">
                            <?= $project->getParticipantCount($rcpro_project_id) ?>
                        </td>
                        <!-- # Staff Members -->
                        <td class='dt-center rcpro_participant_link' onclick="(function(){window.open('<?= $project_rcpro_manage_users ?>', '_blank').focus();})()">
                            <?= count($project->staff["allStaff"]) ?>
                        </td>
                        <!-- # Records -->
                        <td class='dt-center rcpro_participant_link' onclick="(function(){window.open('<?= $project_records ?>', '_blank').focus();})()">
                            <?= $project->getRecordCount() ?>
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
                    localStorage.setItem('DataTables_ccproj_' + settings.sInstance, JSON.stringify(data))
                },
                stateLoadCallback: function(settings) {
                    return JSON.parse(localStorage.getItem('DataTables_ccproj_' + settings.sInstance))
                },
                scrollY: '50vh',
                scrollCollapse: true,
                pageLength: 100
            });

            $('#projects').removeClass('dataTableParentHidden');
            $('#loading-container').hide();
            $('.wrapper').show();
            dataTable.columns.adjust().draw();

        });
    }(window.jQuery, window, document));
</script>
<?php
require_once APP_PATH_DOCROOT . 'ControlCenter/footer.php';
?>