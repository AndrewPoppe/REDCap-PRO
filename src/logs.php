<?php
namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

$role = SUPER_USER ? 3 : $module->getUserRole(USERID); // 3=admin/manager, 2=user, 1=monitor, 0=not found
if ($role < 3) {
    header("location:" . $module->getUrl("src/home.php"));
}

require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->UI->ShowHeader("Logs");
$module->initializeJavascriptModuleObject();
?>

<title><?= $module->APPTITLE ?> - Enroll</title>
<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.25/b-1.7.1/b-colvis-1.7.1/b-html5-1.7.1/cr-1.5.4/date-1.1.0/sb-1.1.0/sp-1.3.0/sl-1.3.3/datatables.min.css" />
<script type="text/javascript" src="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.25/b-1.7.1/b-colvis-1.7.1/b-html5-1.7.1/cr-1.5.4/date-1.1.0/sb-1.1.0/sp-1.3.0/sl-1.3.3/datatables.min.js" defer></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js" defer></script>
<link rel="stylesheet" type="text/css" href="<?= $module->getUrl("src/css/rcpro.php") ?>" />

<div class="manageContainer wrapper log-wrapper" style="display: none;">
    <h2>Project Logs</h2>
    <p>This shows logs initiated from this project only.</p>
    <div id="loading-container" class="loader-container">
        <div id="loading" class="loader"></div>
    </div>
    <div id="logs" class="dataTableParentHidden rcpro-form">
        <table class="table rcpro-datatable compact hover" id="RCPRO_Logs" style="width:100%;">
            <caption>REDCapPRO Study Logs</caption>
            <thead>
                <tr>
                    <?php
                    foreach (REDCapPRO::$logColumns as $column) {
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
    (function($, window, document) {

        const RCPRO_module = <?= $module->getJavascriptModuleObjectName() ?>;
        const columns = ["<?= implode('", "', REDCapPRO::$logColumns)?>"];

        function logExport(type) {
            $.ajax({
                'type': 'POST',
                'url': "<?= $module->getUrl("src/logger.php") ?>",
                'data': JSON.stringify({
                    export_type: type
                })
            });
        }

        $(document).ready(function() {
            $('#RCPRO_Logs').DataTable({
                deferRender: true,
                ajax: function (data, callback, settings) {
                    RCPRO_module.ajax('getLogs', { cc: false })
                        .then(response => {
                            callback({ data: response});
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
                createdRow: function(row, data, dataIndex, cells) {
                    let allData = "<div style=\"display: block; text-align:left;\"><ul>";
                    for (column of columns) {
                        const value = data[column];
                        if (value && value != "") {
                            allData += "<li><strong>" + column + "</strong>: " + value + "</li>";
                        }
                    }
                    allData += "</ul></div>";
                    $(row).addClass('hover pointer');
                    $(row).on('click', function() {
                        Swal.fire({
                            confirmButtonColor: "<?= $module::$COLORS["primary"] ?>",
                            allowEnterKey: false,
                            html: allData
                        });
                    });
                },
                dom: 'lBfrtip',
                stateSave: true,
                stateSaveCallback: function(settings, data) {
                    localStorage.setItem('DataTables_logs_' + settings.sInstance, JSON.stringify(data))
                },
                stateLoadCallback: function(settings) {
                    return JSON.parse(localStorage.getItem('DataTables_logs_' + settings.sInstance))
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
                        action: function(e, dt, node, config) {
                            dt.state.clear();
                            window.location.reload();
                        }
                    },
                    {
                        extend: 'csv',
                        exportOptions: {
                            columns: ':visible'
                        },
                        customize: function(csv) {
                            logExport("csv");
                            return csv;
                        }
                    },
                    {
                        extend: 'excel',
                        exportOptions: {
                            columns: ':visible'
                        },
                        customize: function(excel) {
                            logExport("excel");
                            return excel;
                        }
                    },
                ],
                scrollX: true,
                scrollY: '50vh',
                scrollCollapse: true,
                pageLength: 100
            });

            $('#logs').removeClass('dataTableParentHidden');
            $('#loading-container').hide();
            $('.wrapper').show();

            $('#RCPRO_Logs').DataTable().on('buttons-action', function(e, buttonApi, dataTable, node, config) {
                const text = buttonApi.text();
                if (text.search(/Panes|Builder/)) {
                    $('.dt-button-collection').draggable();
                }
            });
            $('#RCPRO_Logs').DataTable().columns.adjust().draw();
        });
    }(window.jQuery, window, document));
</script>
<?php
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
?>