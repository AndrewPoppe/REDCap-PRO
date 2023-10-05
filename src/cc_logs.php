<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

if ( !$module->framework->isSuperUser() ) {
    exit();
}
$module->includeFont();

require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';
$module->UI->ShowControlCenterHeader("Logs");

?>
<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<link rel="stylesheet" type="text/css"
    href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.25/b-1.7.1/b-colvis-1.7.1/b-html5-1.7.1/cr-1.5.4/date-1.1.0/sb-1.1.0/sp-1.3.0/sl-1.3.3/datatables.min.css" />
<script type="text/javascript"
    src="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.25/b-1.7.1/b-colvis-1.7.1/b-html5-1.7.1/cr-1.5.4/date-1.1.0/sb-1.1.0/sp-1.3.0/sl-1.3.3/datatables.min.js"
    defer></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js" defer></script>
<link rel="stylesheet" type="text/css" href="<?= $module->getUrl("src/css/rcpro_cc.php") ?>">
<?php

$module->initializeJavascriptModuleObject();

?>
<div id="loading-container" class="loader-container">
    <div id="loading" class="loader"></div>
</div>
<div class="logsContainer wrapper" style="display: none;">

    <div id="logs" class="dataTableParentHidden outer_container">
        <table class="compact hover" id="RCPRO_TABLE" style="width:100%;">
            <caption>REDCapPRO Logs</caption>
            <thead>
                <tr>
                    <?php
                    foreach ( REDCapPRO::$logColumnsCC as $column ) {
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
        const columns = ["<?= implode('", "', REDCapPRO::$logColumnsCC) ?>"];

        function logExport(type) {
            RCPRO_module.ajax('exportLogs', { cc: true, export_type: type });
        }

        $(document).ready(function () {
            let dataTable = $('#RCPRO_TABLE').DataTable({
                deferRender: true,
                ajax: function (data, callback, settings) {
                    RCPRO_module.ajax('getLogs', { cc: true })
                        .then(response => {
                            callback({ data: response });
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
                dom: 'lBfrtip',
                stateSave: true,
                stateSaveCallback: function (settings, data) {
                    localStorage.setItem('DataTables_cclogs_' + settings.sInstance, JSON.stringify(data))
                },
                stateLoadCallback: function (settings) {
                    return JSON.parse(localStorage.getItem('DataTables_cclogs_' + settings.sInstance))
                },
                colReorder: true,
                buttons: [{
                    extend: 'searchPanes',
                    config: {
                        cascadePanes: true,
                    }

                },
                {
                    extend: 'searchBuilder',
                },
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
                scrollY: '60vh',
                scrollCollapse: true
            });

            $('#logs').removeClass('dataTableParentHidden');
            $('#loading-container').hide();
            $('.wrapper').show();

            dataTable.on('buttons-action', function (e, buttonApi, dataTable, node, config) {
                const text = buttonApi.text();
                if (text.search(/Panes|Builder/)) {
                    $('.dt-button-collection').draggable();
                }
            });
            dataTable.columns.adjust().draw();
        });
    }(window.jQuery, window, document));
</script>
<?php require_once APP_PATH_DOCROOT . 'ControlCenter/footer.php'; ?>