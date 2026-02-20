<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

if ( !$module->framework->isSuperUser() ) {
    exit();
}
?>
<link href="https://cdn.datatables.net/v/bs5/jszip-3.10.1/dt-2.1.3/b-3.1.1/b-colvis-3.1.1/b-html5-3.1.1/sr-1.4.1/datatables.min.css" rel="stylesheet">
<script src="https://cdn.datatables.net/v/bs5/jszip-3.10.1/dt-2.1.3/b-3.1.1/b-colvis-3.1.1/b-html5-3.1.1/sr-1.4.1/datatables.min.js" integrity="sha512-tQIUNMCB0+K4nlOn4FRg/hco5B1sf4yWGpnj+V2MxRSDSVNPD84yzoWogPL58QRlluuXkjvuDD5bzCUTMi6MDw==" crossorigin="anonymous"></script>

<link rel="stylesheet" type="text/css" href="<?= $module->getUrl("src/css/rcpro_cc.php") ?>">

<?php
$module->includeFont();
$language = new Language($module);
$language->handleSystemLanguageChangeRequest();
require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';
$ui = new UI($module);
$ui->ShowControlCenterHeader("Projects");
$module->initializeJavascriptModuleObject();
$module->tt_transferToJavascriptModuleObject();
?>
<div id="loading-container" class="loader-container">
    <div id="loading" class="loader"></div>
</div>
<div class="projectsContainer wrapper" style="display: none;">
    <h2><?= $module->tt("cc_projects") ?></h2>
    <p><?= $module->tt("cc_projects_description") ?></p>
    <div id="projects" class="dataTableParentHidden outer_container">
        <table id="RCPRO_TABLE" style="width:100%;">
            <caption><?= $module->tt("cc_projects_table_caption") ?></caption>
            <thead>
                <th scope="col" class='dt-center'><?= $module->tt("cc_projects_table_project_id") ?></th>
                <th scope="col" class='dt-center'><?= $module->tt("cc_projects_table_redcap_pid") ?></th>
                <th scope="col"><?= $module->tt("cc_title") ?></th>
                <th scope="col" class='dt-center'><?= $module->tt("cc_status") ?></th>
                <th scope="col" class='dt-center'><?= $module->tt("cc_projects_table_n_participants") ?></th>
                <th scope="col" class='dt-center'><?= $module->tt("cc_projects_table_n_staff_members") ?></th>
                <th scope="col" class='dt-center'><?= $module->tt("cc_projects_table_n_records") ?></th>
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
                deferRender: true,
                processing: true,
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
                        title: `<?= $module->tt("cc_projects_table_project_id") ?>`,
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
                        title: `<?= $module->tt("cc_projects_table_redcap_pid") ?>`,
                        className: "dt-center",
                        data: 'project_id'
                    },
                    {
                        title: `<?= $module->tt("cc_title") ?>`,
                        data: 'title'
                    },
                    {
                        title: `<?= $module->tt("cc_status") ?>`,
                        className: "dt-center",
                        data: 'status'
                    },
                    {
                        title: `<?= $module->tt("cc_projects_table_n_participants") ?>`,
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
                        title: `<?= $module->tt("cc_projects_table_n_staff_members") ?>`,
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
                        title: `<?= $module->tt("cc_projects_table_n_records") ?>`,
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
                pageLength: 100,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: RCPRO_module.tt('cc_dt_search_placeholder'),
                    infoFiltered: " - " + RCPRO_module.tt('cc_dt_info_filtered', '_MAX_'),
                    emptyTable: RCPRO_module.tt('cc_dt_empty_table'),
                    info: RCPRO_module.tt('cc_dt_info', { start: '_START_', end: '_END_', total: '_TOTAL_' }),
                    infoEmpty: RCPRO_module.tt('cc_dt_info_empty'),
                    lengthMenu: RCPRO_module.tt('cc_dt_length_menu', '_MENU_'),
                    loadingRecords: RCPRO_module.tt('cc_dt_loading_records'),
                    zeroRecords: RCPRO_module.tt('cc_dt_zero_records'),
                    decimal: RCPRO_module.tt('cc_dt_decimal'),
                    thousands: RCPRO_module.tt('cc_dt_thousands'),
                    select: {
                        rows: {
                            _: RCPRO_module.tt('cc_dt_select_rows_other'),
                            0: RCPRO_module.tt('cc_dt_select_rows_zero'),
                            1: RCPRO_module.tt('cc_dt_select_rows_one')
                        }
                    },
                    paginate: {
                        first: RCPRO_module.tt('cc_dt_paginate_first'),
                        last: RCPRO_module.tt('cc_dt_paginate_last'),
                        next: RCPRO_module.tt('cc_dt_paginate_next'),
                        previous: RCPRO_module.tt('cc_dt_paginate_previous')
                    },
                    aria: {
                        sortAscending: RCPRO_module.tt('cc_dt_aria_sort_ascending'),
                        sortDescending: RCPRO_module.tt('cc_dt_aria_sort_descending')
                    }
                }
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