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
<link href="https://cdn.datatables.net/v/bs5/jszip-3.10.1/dt-2.1.3/b-3.1.1/b-colvis-3.1.1/b-html5-3.1.1/date-1.5.3/sr-1.4.1/datatables.min.css" rel="stylesheet"> 
<script src="https://cdn.datatables.net/v/bs5/jszip-3.10.1/dt-2.1.3/b-3.1.1/b-colvis-3.1.1/b-html5-3.1.1/date-1.5.3/sr-1.4.1/datatables.min.js" integrity="sha512-Dp027lG1kw+OxJjCYvBw8jT9Buw65dP8HYTtfUQ8DAeBUcYn/urzTlQ8jbWXENZaYnujGvQlgJT21m+GsaCH5w==" crossorigin="anonymous"></script>
<script src="https://code.jquery.com/ui/1.14.0/jquery-ui.js" integrity="sha512-cxRn/7O+JWSqvBOGeODaC+v9wr7RFllEwd6SBAub5ZuBPEzBtYZXTx46KNnlzvEfG42CzbwfscbBYZYcgrXKNA==" crossorigin="anonymous" defer></script>
<link rel="stylesheet" type="text/css" href="<?= $module->getUrl("src/css/rcpro_cc.php") ?>">
<?php

$module->initializeJavascriptModuleObject();
$module->tt_transferToJavascriptModuleObject();
?>
<div id="loading-container" class="loader-container">
    <div id="loading" class="loader"></div>
</div>
<div class="logsContainer wrapper" style="display: none;">

    <div id="logs" class="dataTableParentHidden outer_container">
        <table class="compact hover" id="RCPRO_TABLE" style="width:100%;">
            <caption><?= $module->tt("cc_logs_table_caption") ?></caption>
            <thead>
                <tr>
                    <?php
                    foreach ( REDCapPRO::$logColumnsCC as $column ) {
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
                        minDate: $('#min').val(),
                        maxDate: $('#max').val(),
                        cc: true
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
                layout: {
                    topStart: ['pageLength', 'buttons'],
                    topEnd: 'search'
                },
                stateSave: true,
                stateSaveCallback: function (settings, data) {
                    localStorage.setItem('DataTables_cclogs_' + settings.sInstance, JSON.stringify(data))
                },
                stateLoadCallback: function (settings) {
                    return JSON.parse(localStorage.getItem('DataTables_cclogs_' + settings.sInstance))
                },
                colReorder: false,
                buttons: [
                    {
                        extend: 'colvis',
                        className: 'btn btn-primaryrc btn-sm'
                    },
                    'spacer',
                    {
                        text: '<?= $module->tt("cc_restore_default") ?>',
                        action: function (e, dt, node, config) {
                            dt.state.clear();
                            window.location.reload();
                        },
                        className: 'btn btn-secondary btn-sm'
                    },
                    'spacer',
                    {
                        extend: 'csv',
                        exportOptions: {
                            columns: ':visible'
                        },
                        customize: function (csv) {
                            logExport("csv");
                            return csv;
                        },
                        className: 'btn btn-success btn-sm'
                    },
                    'spacer',
                    {
                        extend: 'excel',
                        exportOptions: {
                            columns: ':visible'
                        },
                        customize: function (excel) {
                            logExport("excel");
                            return excel;
                        },
                        className: 'btn btn-success btn-sm'
                    }
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

                        if (title.toLowerCase() === 'timestamp') {
                            $('<br><input type="text" class="form-control form-control-sm" style="min-width:140px;" id="min" placeholder="Min timestamp" />')
                            .val(column.search())
                            .appendTo($(column.header()))
                            .on('click', function (e) {
                                e.stopPropagation();
                            })
                            .on('change clear', function (e) {
                                $('#RCPRO_TABLE').DataTable().search('').draw();
                            });
                            $('<input type="text" class="form-control form-control-sm" style="min-width:140px;" id="max" placeholder="Max timestamp" />')
                            .val(column.search())
                            .appendTo($(column.header()))
                            .on('click', function (e) {
                                e.stopPropagation();
                            })
                            .on('change clear', function (e) {
                                $('#RCPRO_TABLE').DataTable().search('').draw();
                            });
                            var minDate = new DateTime('#min');
                            var maxDate = new DateTime('#max');
                            return;
                        }
        
                        // Create input element and add event listener
                        $('<br><input type="text" class="form-control form-control-sm" style="min-width:140px;" placeholder="Search ' + title + '" />')
                            .val(column.search())
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
                    dataTable.columns.adjust();  
                },
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