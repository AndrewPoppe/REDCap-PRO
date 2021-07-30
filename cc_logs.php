<?php
if (!SUPER_USER) {
    return;
}


?>
<!DOCTYPE html>
    <?php
    
    require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';
    $module->UiShowControlCenterHeader("Logs");

    ?>
    <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.24/af-2.3.5/b-1.7.0/b-colvis-1.7.0/b-html5-1.7.0/b-print-1.7.0/rg-1.1.2/sb-1.0.1/sp-1.2.2/sl-1.3.3/datatables.min.css"/>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/colreorder/1.5.3/css/colReorder.dataTables.min.css">
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js" defer></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js" defer></script>
    <script type="text/javascript" src="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.24/af-2.3.5/b-1.7.0/b-colvis-1.7.0/b-html5-1.7.0/b-print-1.7.0/rg-1.1.2/sb-1.0.1/sp-1.2.2/sl-1.3.3/datatables.min.js" defer></script>
    <script type="text/javascript" src="https://cdn.datatables.net/colreorder/1.5.3/js/dataTables.colReorder.min.js" defer></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js" defer></script>
    <style>
        .wrapper { 
            display: inline-block; 
            padding: 20px;
            margin-left:auto; 
            margin-right:auto;
            width: 50vw !important;
        }
        #RCPRO_Logs tr.even {
            background-color: white !important;
        }
        #RCPRO_Logs tr.odd {
            background-color: white !important;
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
        table.dataTable tbody tr.even {
            background-color: #f2f2f2;
        }
        button.dt-button {
            padding: .1em .2em;
        }
        table.dataTable tr.dtrg-group.dtrg-level-0 td {
            background-color: #00356b;
            color: #f9f9f9;
        }
        table.dataTable tr.dtrg-group.dtrg-level-1 td {
            background-color: #ccc;
            font-weight: bold;
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
            background-color: #809ab5 !important;
        }
        div.dtsp-panesContainer tr:hover {
            background-color: #aaa !important;
            cursor: pointer;
        }
        div.dtsp-panesContainer tr.selected:hover {
            background-color: #406890 !important;
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
            border: 16px solid #ddd; /* Light grey */
            border-top: 16px solid #900000; /* Red */
            border-radius: 50%;
            width: 120px;
            height: 120px;
            animation: spin 2s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    

    <?php

$columns = [
    "timestamp",
    "message",
    "ui_id",
    "ip",
    "project_id",
    "record",
    "fname",
    "lname",
    "email",
    "pw",
    "rcpro_username",
    "event",
    "instrument",
    "project",
    "repeat_instance",
    "response_id",
    "survey_hash",
    "rcpro_ip",
    "rcpro_participant_id",
    "rcpro_email",
    "redcap_project",
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
    "token",
    "token_ts",
    "token_valid"
];

$tableData = $module->queryLogs("SELECT ".implode(", ", $columns)." WHERE project_id IS NULL OR project_id IS NOT NULL");

?>

<body>
    <div class="logsContainer wrapper">
        <div id="loading-container" class="loader-container">
            <div id="loading" class="loader"></div>
        </div>
        <div id="logs" class="dataTableParentHidden">
            <table class="table" id="RCPRO_Logs" style="width:100%;">
                <caption>REDCapPRO Logs</caption>
                <thead>
                    <tr>
                        <?php 
                        foreach ($columns as $column) {
                            echo "<th id='rcpro_${column}' class='dt-center'>".ucwords(str_replace("_", " ", $column))."</th>";
                        }
                        ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    while ($row = $tableData->fetch_assoc()) {
                        echo "<tr>";
                        foreach ($columns as $column) {
                            echo "<td>$row[$column]</td>";
                        }
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
		(function($, window, document) {
			$(document).ready( function () { 
				$('#RCPRO_Logs').DataTable({
					//pageLength: 1000,
					dom: 'lBfrtip',
					stateSave: true,
                    stateSaveCallback: function(settings,data) {
                        localStorage.setItem( 'DataTables_' + settings.sInstance, JSON.stringify(data) )
                    },
                    stateLoadCallback: function(settings) {
                        return JSON.parse( localStorage.getItem( 'DataTables_' + settings.sInstance ) )
                    },
					colReorder: true,
					buttons: [
						{
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
							exportOptions: { columns: ':visible' }
						},
						{ 
							extend: 'excel',
							exportOptions: { columns: ':visible' }
						},
						{ 
							extend: 'pdf',
							exportOptions: { columns: ':visible' }
						}
					],
					scrollX: true,
					scrollY: '60vh',
					scrollCollapse: true
				});

				$('#logs').removeClass('dataTableParentHidden');
                $('#loading-container').hide();
				
				$('#RCPRO_Logs').DataTable().on( 'buttons-action', function ( e, buttonApi, dataTable, node, config ) {
					const text = buttonApi.text();
					if (text.search(/Panes|Builder/)) {
						$('.dt-button-collection').draggable();
					}
				});
			});
		}(window.jQuery, window, document));
		</script>
        <?php require_once APP_PATH_DOCROOT . 'ControlCenter/footer.php'; ?>
</body>