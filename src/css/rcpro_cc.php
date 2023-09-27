<?php header("Content-type: text/css; charset: UTF-8"); ?>

table.dataTable:not(#RCPRO_TABLE) thead > tr > th {
border-bottom: 1px solid #ccc !important;
}
table.dataTable#RCPRO thead > tr > th {
border-bottom: none !important;
}

table#RCPRO_TABLE tbody tr:first-child td {
border-top: none !important;
border-bottom: none !important;
}
table.dataTable.no-footer {
border: none !important;
}

table#RCPRO_TABLE tr,
table#RCPRO_TABLE tbody {
border: none !important;
}


table.dataTable th {
border-top: none !important;
}

table.dataTable td {
border-top: 1px solid #eeeeee !important;
border-bottom: none !important;
}

div.dataTables_wrapper.no-footer div.dataTables_scrollBody {
border-bottom: 1px solid #ccc !important;
}

.wrapper {
display: inline-block;
padding: 20px;
margin-left: auto;
margin-right: auto;
width: 50vw !important;
}

.outer_container {
border-radius: 5px;
border: 1px solid #cccccc;
padding: 20px;
box-shadow: 0px 0px 5px #eeeeee;
}

#RCPRO_TABLE tr.even,
#RCPRO_TABLE tr.even td {
background-color: white !important;
}

#RCPRO_TABLE tr.odd,
#RCPRO_TABLE tr.odd td {
background-color:
<?= $module::$COLORS["lightGrey"] ?> !important;
}

#RCPRO_TABLE tbody .hover:hover {
background-color:
<?= $module::$COLORS["mediumGrey"] ?> !important;
}

#RCPRO_TABLE tbody .pointer {
cursor: pointer;
}


table#RCPRO_TABLE td, {
vertical-align: middle;
}



table {
border-collapse: collapse;
}

table.dataTable tbody tr.even {
background-color:
<?= $module::$COLORS["lightGrey"] ?>;
}


table.dataTable tr.dtrg-group.dtrg-level-0 td {
background-color:
<?= $module::$COLORS["primary"] ?>;
color: #f9f9f9;
}

table.dataTable tr.dtrg-group.dtrg-level-1 td {
background-color: #ccc;
font-weight: bold;
}


.rcpro_project_link {
color:
<?= $module::$COLORS["blue"] ?> !important;
font-weight: bold !important;
}

.rcpro_project_link:hover {
color:
<?= $module::$COLORS["primary"] ?> !important;
font-weight: bold !important;
cursor: pointer !important;
}

.rcpro_project_link_inactive {
color: #101010 !important;
text-decoration: line-through !important;
}

.rcpro_project_link_inactive:hover {
color: black !important;
cursor: pointer !important;
text-decoration: line-through !important;
}

.rcpro_participant_link,
.rcpro_user_link {
color:
<?= $module::$COLORS["blue"] ?> !important;
font-weight: bold !important;
}

#RCPRO_TABLE td.rcpro_participant_link:hover,
#RCPRO_TABLE td.rcpro_user_link:hover {
color:
<?= $module::$COLORS["primary"] ?> !important;
font-weight: bold !important;
cursor: pointer !important;
background-color:
<?= $module::$COLORS["mediumGrey"] ?> !important;
}

.dt-center {
text-align: center;
}

button:hover {
outline: none !important;
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
background-color:
<?= $module::$COLORS["primary"] ?> !important;
color: #f9f9f9;
}

div.dtsp-panesContainer tr:hover {
background-color: #aaa !important;
cursor: pointer;
}

div.dtsp-panesContainer tr.selected:hover {
background-color:
<?= $module::$COLORS["primaryHighlight"] ?> !important;
color: black;
cursor: pointer;
}

.dataTableParentHidden {
overflow: hidden;
height: 0px;
width: 100%;
display: none;
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
border: 16px solid
<?= $module::$COLORS["mediumGrey"] ?>;
/* Light grey */
border-top: 16px solid
<?= $module::$COLORS["primary"] ?>;
/* Red */
border-radius: 50%;
width: 120px;
height: 120px;
animation: spin 0.75s linear infinite;
}

@keyframes spin {
0% {
transform: rotate(0deg);
}

100% {
transform: rotate(360deg);
}
}