<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

if ( !$module->framework->isSuperUser() ) {
    exit();
}
echo '<!DOCTYPE html><html lang="en">';
$module->includeFont();

require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';
$ui = new UI($module);
$ui->ShowControlCenterHeader("Logs");

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
        var t0 = performance.now();
        var tHalf,t1,t2;
        console.log('start: ',t0);

        const RCPRO_module = <?= $module->getJavascriptModuleObjectName() ?>;
        const columns = ["<?= implode('", "', REDCapPRO::$logColumnsCC) ?>"];

        function logExport(type) {
            RCPRO_module.ajax('exportLogs', { cc: true, export_type: type });
        }

        $(document).ready(function () {
            let dataTable = $('#RCPRO_TABLE').DataTable({
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
                        cc: true
                    }
                    tHalf = performance.now();
                    RCPRO_module.ajax('getLogs', payload)
                        .then(response => {
                            t1 = performance.now();
                            //console.log('Got data: ', t1);
                            console.log('Processing: ', t1-tHalf);
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
                        defaultContent: "",
                        className: "dt-center"
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
                stateSave: false,
                stateSaveCallback: function (settings, data) {
                    localStorage.setItem('DataTables_cclogs_' + settings.sInstance, JSON.stringify(data))
                },
                stateLoadCallback: function (settings) {
                    return JSON.parse(localStorage.getItem('DataTables_cclogs_' + settings.sInstance))
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
                scrollY: '60vh',
                scrollCollapse: true,
                initComplete: function() {
                    this.api()
                    .columns()
                    .every(function () {
                        var column = this;
                        var title = column.header().textContent;
        
                        // Create input element and add event listener
                        $('<br><input type="text" placeholder="Search ' + title + '" />')
                            .appendTo($(column.header()))
                            .on('click', function (e) {
                                e.stopPropagation();
                            })
                            .on('click keyup change clear', function (e) {
                                if (column.search() !== this.value) {
                                    column.search(this.value).draw();
                                }
                            });
                    });
                    console.log('End: ', performance.now());
                    
                    dataTable.columns.adjust().draw();  
                },
                drawCallback: function (settings) {
                    t2 = performance.now();
                    console.log('Render: ', t2-t1);
                }
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
        });
    }(window.jQuery, window, document));
</script>
<?php require_once APP_PATH_DOCROOT . 'ControlCenter/footer.php'; ?>