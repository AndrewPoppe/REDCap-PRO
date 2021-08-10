<?php

$role = SUPER_USER ? 3 : $module->getUserRole(USERID); // 3=admin/manager, 2=user, 1=monitor, 0=not found
if ($role < 3) {
    header("location:" . $module->getUrl("src/home.php"));
}
?>
<!DOCTYPE html>
<html lang='en'>

<head>
    <meta charset='UTF-8'>
    <title><?= $module::$APPTITLE ?> - Enroll</title>
    <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.25/b-1.7.1/b-colvis-1.7.1/b-html5-1.7.1/cr-1.5.4/date-1.1.0/sb-1.1.0/sp-1.3.0/sl-1.3.3/datatables.min.css" />
    <script type="text/javascript" src="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.25/b-1.7.1/b-colvis-1.7.1/b-html5-1.7.1/cr-1.5.4/date-1.1.0/sb-1.1.0/sp-1.3.0/sl-1.3.3/datatables.min.js" defer></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js" defer></script>
    <style>
        .wrapper {
            display: inline-block;
            padding: 20px;
            margin-left: auto;
            margin-right: auto;
            width: 95% !important;
        }

        #RCPRO_Logs tr.even {
            background-color: white !important;
        }

        #RCPRO_Logs tr.odd {
            background-color: #f9f9f9 !important;
        }

        #RCPRO_Logs tr:hover {
            background-color: #ddd !important;
            cursor: pointer;
        }

        table.dataTable tbody td {
            vertical-align: middle;
        }

        .dt-center {
            text-align: center;
        }

        button:hover {
            outline: none !important;
        }

        table {
            border-collapse: collapse;
        }

        button.dt-button {
            padding: .1em .2em;
        }

        div.dt-buttons {
            margin-left: 10px;
        }

        div.dtsp-panesContainer {
            width: auto;
        }

        div.dtsp-panesContainer tr {
            background-color: #fff !important;
        }

        div.dtsp-panesContainer tr.selected {
            background-color: #900000 !important;
            color: #f9f9f9;
        }

        div.dtsp-panesContainer tr:hover {
            background-color: #aaa !important;
            cursor: pointer;
        }

        div.dtsp-panesContainer tr.selected:hover {
            background-color: #ffbfbf !important;
            color: black;
            cursor: pointer;
        }

        div.dataTableParentHidden {
            overflow: hidden;
            height: 0px;
            width: 100%;
            display: none;
        }

        #logs {
            border-radius: 5px;
            border: 1px solid #cccccc;
            padding: 20px;
            box-shadow: 0px 0px 5px #eeeeee;
        }

        div.ui-draggable {
            cursor: move;
            cursor: grab;
            cursor: -moz-grab;
            cursor: -webkit-grab;
        }

        div.ui-draggable-dragging {
            cursor: grabbing;
            cursor: -moz-grabbing;
            cursor: -webkit-grabbing;
        }

        div.dtsb-searchBuilder {
            cursor: inherit;
        }

        div.dtsb-searchBuilder select {
            cursor: pointer;
        }

        .loader-container {
            width: 90%;
            display: flex;
            justify-content: center;
            height: 33vh;
            align-items: center;
        }

        .loader {
            border: 16px solid #f3f3f3;
            /* Light grey */
            border-top: 16px solid #900000;
            /* Red */
            border-radius: 50%;
            width: 120px;
            height: 120px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</head>
<?php
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module::$UI->ShowHeader("Logs");

$columns = [
    "timestamp",
    "message",
    "rcpro_username",
    "ui_id",
    "ip",
    "project_id",
    "record",
    "fname",
    "lname",
    "email",
    "project_dag",
    "group_id",
    "event",
    "instrument",
    "repeat_instance",
    "response_id",
    "survey_hash",
    "rcpro_ip",
    "rcpro_participant_id",
    "rcpro_email",
    "redcap_user",
    "new_email",
    "old_email",
    "error_code",
    "error_file",
    "error_line",
    "error_message",
    "error_string",
    "active",
    "pid",
    "rcpro_project_id",
    "failed_attempts",
    "last_modified_ts",
    "lockout_ts",
    "token_ts",
    "token_valid"
];

$tableData = $module->queryLogs("SELECT " . implode(", ", $columns));

?>

<body>
    <div class="manageContainer wrapper">
        <h2>Project Logs</h2>
        <p>This shows logs initiated from this project only.</p>
        <div id="loading-container" class="loader-container">
            <div id="loading" class="loader"></div>
        </div>
        <div id="logs" class="dataTableParentHidden">
            <table class="table compact hover" id="RCPRO_Logs" style="width:100%;">
                <caption>REDCapPRO Study Logs</caption>
                <thead>
                    <tr>
                        <?php
                        foreach ($columns as $column) {
                            echo "<th id='rcpro_${column}' class='dt-center'>" . ucwords(str_replace("_", " ", $column)) . "</th>";
                        }
                        ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    while ($row = $tableData->fetch_assoc()) {
                        $tds = "";
                        $allData = "<div style=\\\"display: block; text-align:left;\\\"><ul>";
                        foreach ($columns as $column) {
                            $value = str_replace("\n", "\\n", addslashes(\REDCap::escapeHtml($row[$column])));
                            $tds .= "<td>$value</td>";
                            if ($value != "") {
                                $allData .= "<li><strong>${column}</strong>: $value</li>";
                            }
                        }
                        $allData .= "</ul></div>";
                        echo "<tr onclick='(function() {Swal.fire({confirmButtonColor:\"#900000\", allowEnterKey: false, html:\"" . $allData . "\"})})()'>";
                        echo $tds;
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
        (function($, window, document) {
            $(document).ready(function() {
                $('#RCPRO_Logs').DataTable({
                    dom: 'lBfrtip',
                    stateSave: true,
                    stateSaveCallback: function(settings, data) {
                        localStorage.setItem('DataTables_' + settings.sInstance, JSON.stringify(data))
                    },
                    stateLoadCallback: function(settings) {
                        return JSON.parse(localStorage.getItem('DataTables_' + settings.sInstance))
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
                    ],
                    scrollX: true,
                    scrollY: '50vh',
                    scrollCollapse: true,
                    pageLength: 100
                });

                $('#logs').removeClass('dataTableParentHidden');
                $('#loading-container').hide();

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
</body>