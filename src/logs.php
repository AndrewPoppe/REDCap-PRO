<?php
namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

$role = $module->getUserRole($module->safeGetUsername()); // 3=admin/manager, 2=user, 1=monitor, 0=not found
if ( $role < 3 ) {
    header("location:" . $module->getUrl("src/home.php"));
}
$module->includeFont();
$language = new Language($module);
$language->handleLanguageChangeRequest();

require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$ui = new UI($module);
$ui->ShowHeader("Logs");
$module->initializeJavascriptModuleObject();
$module->tt_transferToJavascriptModuleObject();
?>

<title>
    <?= $module->APPTITLE ?>
</title>
<link rel="stylesheet" href="https://code.jquery.com/ui/1.14.2/themes/base/jquery-ui.css" integrity="sha512-k0u6mixrDpfcaeu7sUpo4OcH8rfxzgXtiqauVEbd6CXNo6Sa0SuVkxbWvT7SiPKvNpfovncDTNvm3yX1I+xiNQ==" crossorigin="anonymous">
<link href="https://cdn.datatables.net/v/dt/jszip-3.10.1/dt-2.3.8/b-3.2.6/b-colvis-3.2.6/b-html5-3.2.6/sr-1.4.3/datatables.min.css" rel="stylesheet" integrity="sha512-f7O60zIL8r8zheoepLkxShesZy62kEhnT2VkuIPBX/yeEZQn4QYBxMA20B7rq6KmoSJ5Fi/WPrjrW8YpuDtAwA==" crossorigin="anonymous">
<script src="https://cdn.datatables.net/v/dt/jszip-3.10.1/dt-2.3.8/b-3.2.6/b-colvis-3.2.6/b-html5-3.2.6/sr-1.4.3/datatables.min.js" integrity="sha512-q484kVJD3XN3+1RmkkYXAe38xBRNO+KX7EmGL5f9WgtQCBt4bAfypf4SIV/lhFU8a5/jGlF4MC1SFwp6F7W+Uw==" crossorigin="anonymous"></script>
<script src="https://code.jquery.com/ui/1.14.2/jquery-ui.js" integrity="sha512-nF9ZY+o4GTMKGuKfrwYSdqrbG59QLhdJgtXOPiRZ4CkswwdjyTaPn0qvbRoIsih0ZbEKfCN9dU7lhqkIQUdY/A==" crossorigin="anonymous" defer></script>
<link rel="stylesheet" type="text/css" href="<?= $module->getUrl("src/css/rcpro.php") ?>" />

<div class="manageContainer wrapper log-wrapper" style="display: none;">
    <h2><?= $module->tt("project_logs_page_title") ?></h2>
    <p><?= $module->tt("project_logs_page_subtitle") ?></p>
    <div id="loading-container" class="loader-container">
        <div id="loading" class="loader"></div>
    </div>
    <div id="logs" class="dataTableParentHidden rcpro-form">
        <table class="rcpro-datatable compact hover" id="RCPRO_TABLE" style="width:100%;">
            <caption><?= $module->tt("project_logs_caption") ?></caption>
            <thead>
                <tr>
                    <?php
                    foreach ( REDCapPRO::$logColumns as $column ) {
                        echo "<th id='rcpro_{$column}' class='dt-center'>" . ucwords(str_replace("_", " ", $column)) . "</th>";
                    }
                    ?>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
</div>
<script>
    (function ($, window, document) {

        const RCPRO_module = <?= $module->getJavascriptModuleObjectName() ?>;
        const columns = ["<?= implode('", "', REDCapPRO::$logColumns) ?>"];

        function logExport(type) {
            RCPRO_module.ajax('exportLogs', { cc: false, export_type: type });
        }

        $(document).ready(function () {
            $('#RCPRO_TABLE').DataTable({
                deferRender: true,
                serverSide: true,
                processing: true,
                searchDelay: 1000,
                order: [[0, 'desc']],
                ajax: function (data, callback, settings) {
                    const payload = {
                        draw: data.draw,
                        search: data.search,
                        start: data.start,
                        length: data.length,
                        order: data.order,
                        columns: data.columns,
                        cc: false
                    }
                    RCPRO_module.ajax('getLogs', payload)
                        .then(response => {
                            callback(response);
                        })
                        .catch(error => {
                            console.error(error);
                            callback({ data: [] });
                        });
                },
                columns: columns.map(column => {
                    return {
                        data: column,
                        defaultContent: ""
                    }
                }),
                createdRow: function (row, data, dataIndex, cells) {
                    let allData = "<div style=\"display: block; text-align:left;\"><ul>";
                    for (column of columns) {
                        const value = data[column];
                        if (value && value != "") {
                            allData += "<li><strong>" + column + "</strong>: " + value + "</li>";
                        }
                    }
                    allData += "</ul></div>";
                    $(row).addClass('hover pointer');
                    $(row).on('click', function () {
                        Swal.fire({
                            confirmButtonColor: "<?= $module::$COLORS["primary"] ?>",
                            allowEnterKey: false,
                            html: allData
                        });
                    });
                },
                layout: {
                    topStart: ['pageLength', 'buttons'],
                    topEnd: 'search'
                },
                stateSave: true,
                stateSaveCallback: function (settings, data) {
                    localStorage.setItem('DataTables_logs_' + settings.sInstance, JSON.stringify(data))
                },
                stateLoadCallback: function (settings) {
                    return JSON.parse(localStorage.getItem('DataTables_logs_' + settings.sInstance))
                },
                colReorder: false,
                buttons: [
                    'colvis',
                {
                    text: '<?= $module->tt("project_logs_restore_default") ?>',
                    action: function (e, dt, node, config) {
                        dt.state.clear();
                        window.location.reload();
                    }
                },
                {
                    extend: 'csv',
                    exportOptions: {
                        columns: ':visible'
                    },
                    customize: function (csv) {
                        logExport("csv");
                        return csv;
                    }
                },
                {
                    extend: 'excel',
                    exportOptions: {
                        columns: ':visible'
                    },
                    customize: function (excel) {
                        logExport("excel");
                        return excel;
                    }
                },
                ],
                scrollX: true,
                scrollY: '50vh',
                scrollCollapse: true,
                initComplete: function() {
                    $('#RCPRO_TABLE').DataTable().columns.adjust();  
                },
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: RCPRO_module.tt('project_dt_search_placeholder'),
                    infoFiltered: " - " + RCPRO_module.tt('project_dt_info_filtered', '_MAX_'),
                    emptyTable: RCPRO_module.tt('project_dt_empty_table'),
                    info: RCPRO_module.tt('project_dt_info', { start: '_START_', end: '_END_', total: '_TOTAL_' }),
                    infoEmpty: RCPRO_module.tt('project_dt_info_empty'),
                    lengthMenu: RCPRO_module.tt('project_dt_length_menu', '_MENU_'),
                    loadingRecords: RCPRO_module.tt('project_dt_loading_records'),
                    zeroRecords: RCPRO_module.tt('project_dt_zero_records'),
                    decimal: RCPRO_module.tt('project_dt_decimal'),
                    thousands: RCPRO_module.tt('project_dt_thousands'),
                    select: {
                        rows: {
                            _: RCPRO_module.tt('project_dt_select_rows_other'),
                            0: RCPRO_module.tt('project_dt_select_rows_zero'),
                            1: RCPRO_module.tt('project_dt_select_rows_one')
                        }
                    },
                    paginate: {
                        first: RCPRO_module.tt('project_dt_paginate_first'),
                        last: RCPRO_module.tt('project_dt_paginate_last'),
                        next: RCPRO_module.tt('project_dt_paginate_next'),
                        previous: RCPRO_module.tt('project_dt_paginate_previous')
                    },
                    aria: {
                        sortAscending: RCPRO_module.tt('project_dt_aria_sort_ascending'),
                        sortDescending: RCPRO_module.tt('project_dt_aria_sort_descending')
                    }
                } 
            });

            $('#logs').removeClass('dataTableParentHidden');
            $('#loading-container').hide();
            $('.wrapper').show();

            $('#RCPRO_TABLE').DataTable().on('buttons-action', function (e, buttonApi, dataTable, node, config) {
                const text = buttonApi.text();
                if (text.search(/Panes|Builder/)) {
                    $('.dt-button-collection').draggable();
                }
            });
        });
    }(window.jQuery, window, document));
</script>
<?php
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
?>