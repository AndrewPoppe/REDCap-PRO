<?php
namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

$role = $module->getUserRole($module->safeGetUsername()); // 3=admin/manager, 2=user, 1=monitor, 0=not found
if ( $role < 3 ) {
    header("location:" . $module->getUrl("src/home.php"));
}
$module->includeFont();

require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$ui = new UI($module);
$ui->ShowHeader("Logs");
$module->initializeJavascriptModuleObject();
?>

<title>
    <?= $module->APPTITLE ?> - Enroll
</title>
<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<link href="https://cdn.datatables.net/v/dt/jszip-3.10.1/dt-2.1.3/b-3.1.1/b-colvis-3.1.1/b-html5-3.1.1/sr-1.4.1/datatables.min.css" rel="stylesheet">
 
<script src="https://cdn.datatables.net/v/dt/jszip-3.10.1/dt-2.1.3/b-3.1.1/b-colvis-3.1.1/b-html5-3.1.1/sr-1.4.1/datatables.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js" defer></script>
<link rel="stylesheet" type="text/css" href="<?= $module->getUrl("src/css/rcpro.php") ?>" />

<div class="manageContainer wrapper log-wrapper" style="display: none;">
    <h2>Project Logs</h2>
    <p>This shows logs initiated from this project only.</p>
    <div id="loading-container" class="loader-container">
        <div id="loading" class="loader"></div>
    </div>
    <div id="logs" class="dataTableParentHidden rcpro-form">
        <table class="rcpro-datatable compact hover" id="RCPRO_TABLE" style="width:100%;">
            <caption>REDCapPRO Study Logs</caption>
            <thead>
                <tr>
                    <?php
                    foreach ( REDCapPRO::$logColumns as $column ) {
                        echo "<th id='rcpro_${column}' class='dt-center'>" . ucwords(str_replace("_", " ", $column)) . "</th>";
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
                // dom: 'lBfrtip',
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
                //     {
                //     extend: 'searchPanes',
                //     config: {
                //         cascadePanes: true,
                //     }

                // },
                // {
                //     extend: 'searchBuilder',
                // },
                    'colvis',
                {
                    text: 'Restore Default',
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
                // pageLength: 100,
                initComplete: function() {
                    $('#RCPRO_TABLE').DataTable().columns.adjust();  
                },
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
            //$('#RCPRO_TABLE').DataTable().columns.adjust().draw();
        });
    }(window.jQuery, window, document));
</script>
<?php
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
?>