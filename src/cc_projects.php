<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

if ( !$module->framework->isSuperUser() ) {
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<title>REDCapPRO Projects</title>
<link rel="stylesheet" type="text/css" href="<?= $module->getUrl("src/css/rcpro_cc.php") ?>">

<?php
$module->includeFont();
require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';
$module->UI->ShowControlCenterHeader("Projects");
$module->initializeJavascriptModuleObject();
?>
<div id="loading-container" class="loader-container">
    <div id="loading" class="loader"></div>
</div>
<div class="projectsContainer wrapper" style="display: none;">
    <h2>Projects</h2>
    <p>All projects currently utilizing REDCapPRO</p>
    <div id="projects" class="dataTableParentHidden outer_container">
        <table id="RCPRO_TABLE" style="width:100%;">
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

            </tbody>
        </table>
    </div>
</div>
<script>
    (function ($, window, document) {
        const RCPRO_module = <?= $module->getJavascriptModuleObjectName() ?>;
        $(document).ready(function () {
            let dataTable = $('#RCPRO_TABLE').DataTable({
                dom: 'lftip',
                deferRender: true,
                ajax: function (data, callback, settings) {
                    RCPRO_module.ajax('getProjectsCC', {})
                        .then(response => {
                            callback({ data: response });
                        })
                        .catch(error => {
                            console.error(error);
                            callback({ data: [] });
                        });
                },
                columns: [
                    {
                        title: 'Project ID',
                        className: "dt-center rcpro_participant_link",
                        data: function (row, type, set, meta) {
                            if (type === 'display') {
                                return `<div onclick="(function(){window.open('${row.project_rcpro_home}', '_blank').focus();})()">${row.rcpro_project_id}</div>`;
                            } else {
                                return row.rcpro_project_id;
                            }
                        }
                    },
                    {
                        title: 'REDCap PID',
                        className: "dt-center",
                        data: 'project_id'
                    },
                    {
                        title: 'Title',
                        data: 'title'
                    },
                    {
                        title: 'Status',
                        className: "dt-center",
                        data: 'status'
                    },
                    {
                        title: '# Participants',
                        className: "dt-center rcpro_participant_link",
                        data: function (row, type, set, meta) {
                            if (type === 'display') {
                                return `<div onclick="(function(){window.open('${row.project_rcpro_manage}', '_blank').focus();})()">${row.participant_count}</div>`;
                            } else {
                                return row.participant_count;
                            }
                        }
                    },
                    {
                        title: '# Staff Members',
                        className: "dt-center rcpro_participant_link",
                        data: function (row, type, set, meta) {
                            if (type === 'display') {
                                return `<div onclick="(function(){window.open('${row.project_rcpro_manage_users}', '_blank').focus();})()">${row.staff_count}</div>`;
                            } else {
                                return row.staff_count;
                            }
                        }
                    },
                    {
                        title: '# Records',
                        className: "dt-center rcpro_participant_link",
                        data: function (row, type, set, meta) {
                            if (type === 'display') {
                                return `<div onclick="(function(){window.open('${row.project_records}', '_blank').focus();})()">${row.record_count}</div>`;
                            } else {
                                return row.record_count;
                            }
                        }
                    }
                ],
                stateSave: true,
                stateSaveCallback: function (settings, data) {
                    localStorage.setItem('DataTables_ccproj_' + settings.sInstance, JSON.stringify(data))
                },
                stateLoadCallback: function (settings) {
                    return JSON.parse(localStorage.getItem('DataTables_ccproj_' + settings.sInstance))
                },
                scrollX: true,
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