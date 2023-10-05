<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

if ( !$module->framework->isSuperUser() ) {
    exit();
}
echo '<!DOCTYPE html><html lang="en">';
$module->includeFont();

require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';
$module->UI->ShowControlCenterHeader("Staff");
echo '<link rel="stylesheet" type="text/css" href="' . $module->getUrl("src/css/rcpro_cc.php") . '">';
$module->initializeJavascriptModuleObject();
?>
<div id="loading-container" class="loader-container">
    <div id="loading" class="loader"></div>
</div>
<div class="usersContainer wrapper" style="display: none;">
    <h2>Staff Members</h2>
    <p>All users across studies</p>
    <div id="users" class="dataTableParentHidden outer_container">
        <table class="table" id="RCPRO_TABLE">
            <caption>REDCapPRO Staff</caption>
            <thead>
                <tr>
                    <th id="username">Username</th>
                    <th id="name" class="dt-center">Name</th>
                    <th id="email">Email</th>
                    <th id="projects" class="dt-center">Projects</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
</div>
<script>
    const RCPRO_module = <?= $module->getJavascriptModuleObjectName() ?>;
    (function ($, window, document) {
        $(document).ready(function () {
            let dataTable = $('#RCPRO_TABLE').DataTable({
                deferRender: true,
                ajax: function (data, callback, settings) {
                    RCPRO_module.ajax('getStaffCC', {})
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
                        title: 'Username',
                        className: "rcpro_user_link",
                        data: function (row, type, set, meta) {
                            if (type === 'display') {
                                return `<div onclick="(function(){window.location.href='${row.userLink}';})()">${row.username}</div>`;
                            } else {
                                return row.username || 'ok';
                            }
                        }
                    },
                    {
                        title: 'Name',
                        className: "dt-center",
                        data: 'name'
                    },
                    {
                        title: 'Email',
                        data: 'email'
                    },
                    {
                        title: 'Projects',
                        className: "dt-center",
                        data: function (row, type, set, meta) {
                            if (type === 'display') {
                                const projects = row.projects;
                                let result = "";
                                for (const project of projects) {
                                    const url = `<?= $module->getUrl("src/manage-users.php?pid=") ?>${project}`;
                                    result += `<div><a class="rcpro_project_link" title="Active" href="${url}">PID ${project}</a></div>`;
                                }
                                return result;
                            } else {
                                return row.projects;
                            }
                        }
                    },
                ],
                dom: 'lBfrtip',
                stateSave: true,
                stateSaveCallback: function (settings, data) {
                    localStorage.setItem('DataTables_ccstaff_' + settings.sInstance, JSON.stringify(data))
                },
                stateLoadCallback: function (settings) {
                    return JSON.parse(localStorage.getItem('DataTables_ccstaff_' + settings.sInstance))
                },
                scrollY: '50vh',
                scrollCollapse: true,
                pageLength: 100
            });
            $('#users').removeClass('dataTableParentHidden');
            $('#loading-container').hide();
            $('.wrapper').show();
            dataTable.columns.adjust().draw();

        });
    }(window.jQuery, window, document));
</script>

<?php
require_once APP_PATH_DOCROOT . 'ControlCenter/footer.php';
?>