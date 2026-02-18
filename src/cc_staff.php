<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

if ( !$module->framework->isSuperUser() ) {
    exit();
}
echo '<!DOCTYPE html><html lang="en">';
$module->includeFont();
$language = new Language($module);
$language->handleSystemLanguageChangeRequest();

require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';
$ui = new UI($module);
$ui->ShowControlCenterHeader("Staff");
echo '<link rel="stylesheet" type="text/css" href="' . $module->getUrl("src/css/rcpro_cc.php") . '">';
$module->initializeJavascriptModuleObject();
$module->tt_transferToJavascriptModuleObject();
?>
<link href="https://cdn.datatables.net/v/bs5/jszip-3.10.1/dt-2.1.3/b-3.1.1/b-colvis-3.1.1/b-html5-3.1.1/sr-1.4.1/datatables.min.css" rel="stylesheet">
<script src="https://cdn.datatables.net/v/bs5/jszip-3.10.1/dt-2.1.3/b-3.1.1/b-colvis-3.1.1/b-html5-3.1.1/sr-1.4.1/datatables.min.js" integrity="sha512-tQIUNMCB0+K4nlOn4FRg/hco5B1sf4yWGpnj+V2MxRSDSVNPD84yzoWogPL58QRlluuXkjvuDD5bzCUTMi6MDw==" crossorigin="anonymous"></script>
 
<div id="loading-container" class="loader-container">
    <div id="loading" class="loader"></div>
</div>
<div class="usersContainer wrapper" style="display: none;">
    <h2><?= $module->tt("cc_staff_title") ?></h2>
    <p><?= $module->tt("cc_staff_subtitle") ?></p>
    <div id="users" class="dataTableParentHidden outer_container">
        <table class="table" id="RCPRO_TABLE">
            <caption><?= $module->tt("cc_staff_table_caption") ?></caption>
            <thead>
                <tr>
                    <th id="username"><?= $module->tt("cc_staff_table_username") ?></th>
                    <th id="name" class="dt-center"><?= $module->tt("cc_staff_table_name") ?></th>
                    <th id="email"><?= $module->tt("cc_staff_table_email") ?></th>
                    <th id="projects" class="dt-center"><?= $module->tt("cc_staff_table_projects") ?></th>
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
                processing: true,
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
                        title: '<?= $module->tt("cc_staff_table_username") ?>',
                        className: "rcpro_user_link",
                        data: 'username',
                        createdCell: function (td, cellData, rowData, row, col) {
                            $(td).on('click', function () {
                                window.location.href = rowData.userLink;
                            });
                        }
                    },
                    {
                        title: '<?= $module->tt("cc_staff_table_name") ?>',
                        className: "dt-center",
                        data: 'name'
                    },
                    {
                        title: '<?= $module->tt("cc_staff_table_email") ?>',
                        data: 'email'
                    },
                    {
                        title: '<?= $module->tt("cc_staff_table_projects") ?>',
                        className: "dt-center",
                        data: function (row, type, set, meta) {
                            if (type === 'display') {
                                const projects = row.projects;
                                let result = "";
                                for (const project of projects) {
                                    const url = `<?= $module->getUrl("src/manage-users.php?pid=") ?>${project}`;
                                    result += `<div><a class="rcpro_project_link" title="Active" href="${url}"><?= $module->tt("cc_pid") ?> ${project}</a></div>`;
                                }
                                return result;
                            } else {
                                return row.projects;
                            }
                        }
                    },
                ],
                stateSave: true,
                stateSaveCallback: function (settings, data) {
                    localStorage.setItem('DataTables_ccstaff_' + settings.sInstance, JSON.stringify(data))
                },
                stateLoadCallback: function (settings) {
                    return JSON.parse(localStorage.getItem('DataTables_ccstaff_' + settings.sInstance))
                },
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