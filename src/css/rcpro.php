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
font-size: small;
}


table.dataTable th {
border-top: none !important;
font-size: small;
}

table.dataTable td {
border-top: 1px solid #eeeeee !important;
border-bottom: none !important;
}

div.dataTables_wrapper.no-footer div.dataTables_scrollBody {
border-bottom: 1px solid #ccc !important;
}

.wrapper {
display: inline-block !important;
padding: 20px !important;
}

.rcpro-form {
border-radius: 5px !important;
border: 1px solid #cccccc !important;
padding: 20px 20px 0px 20px !important;
box-shadow: 0px 0px 5px #eeeeee !important;
}

.rcpro-form-button {
margin-top: 10px !important;
}

table#RCPRO_TABLE tr.even,
table#RCPRO_TABLE tr.even td {
background-color: white !important;
}

table#RCPRO_TABLE tr.odd,
table#RCPRO_TABLE tr.odd td {
background-color:
<?= $module::$COLORS["lightGrey"] ?> !important;
}

table#RCPRO_TABLE tbody .hover:hover {
background-color:
<?= $module::$COLORS["mediumGrey"] ?> !important;
}

table#RCPRO_TABLE tbody .pointer {
cursor: pointer;
}

table#RCPRO_TABLE tbody tr:hover td {
background-color:
<?= $module::$COLORS["mediumGrey"] ?> !important;
}

table#RCPRO_TABLE tbody tr.selected:hover td {
background-color:
<?= $module::$COLORS["primaryDark"] ?> !important;
box-shadow: none;
}

table#RCPRO_TABLE tbody tr.selected td {
background-color:
<?= $module::$COLORS["primary"] ?> !important;
color: white !important;
box-shadow: none;
}

#RCPRO_Logs_wrapper {
margin-bottom: 10px;
}

.dt-center {
text-align: center !important;
}

button:hover {
outline: none !important;
}

.btn-rcpro {
background-color:
<?= $module::$COLORS["primary"] ?> !important;
border-color:
<?= $module::$COLORS["primary"] ?> !important;
color: white !important;
}

.btn-rcpro:active,
.btn-rcpro:hover {
background-color:
<?= $module::$COLORS["primaryDark"] ?> !important;
border-color:
<?= $module::$COLORS["primaryDark"] ?> !important;
color: white !important;
}

.btn-rcpro:focus {
outline: 0 !important;
box-shadow: 0 0 0 0.2rem
<?= $module::$COLORS["primary"] ?>63 !important;
}

.enroll-wrapper {
width: 720px !important;
}

.enroll-form {
width: 540px !important;
}

.confirm-form {
width: 500px !important;
}

.manage-form {
min-width: 640px !important;
}

div#logs {
padding-bottom: 10px !important;
}

.home-form {
width: 50vw !important;
font-size: large;
}

.home-form a {
font-weight: bold;
font-size: inherit;
color:
<?= $module::$COLORS["primary"] ?>;
}

.searchResult {
cursor: pointer !important;
padding: 5px !important;
margin-top: 5px !important;
background-color:
<?= $module::$COLORS["mediumGrey"] ?> !important;
border-radius: 5px !important;
color: black !important;
}

.searchResult:hover {
background-color:
<?= $module::$COLORS["primary"] ?> !important;
color: white !important;
}

.register-form {
width: 360px !important;
}

#infotext {
cursor: pointer !important;
text-decoration: underline !important;
font-weight: bold !important;
color:
<?= $module::$COLORS["secondary"] ?> !important;
}

#infotext:hover {
text-shadow: 0px 0px 5px
<?= $module::$COLORS["secondary"] ?> !important;
}

.swal2-icon.swal2-info {
color:
<?= $module::$COLORS["secondary"] ?> !important;
border-color:
<?= $module::$COLORS["secondary"] ?> !important;
}

.log-wrapper {
margin-left: auto !important;
margin-right: auto !important;
width: 95% !important;
}

table {
border-collapse: collapse !important;
}

button.dt-button {
padding: .1em .2em !important;
}

div.dt-buttons {
margin-left: 10px !important;
}

div.dtsp-panesContainer {
width: auto !important;
}

div.dtsp-panesContainer tr {
background-color: white !important;
}

div.dtsp-panesContainer tr.selected {
background-color:
<?= $module::$COLORS["primary"] ?> !important;
color:
<?= $module::$COLORS["lightGrey"] ?> !important;
}

div.dtsp-panesContainer tr:hover {
background-color:
<?= $module::$COLORS["mediumGrey"] ?> !important;
cursor: pointer !important;
}

div.dtsp-panesContainer tr.selected:hover {
background-color:
<?= $module::$COLORS["primaryLight"] ?> !important;
color: black !important;
cursor: pointer !important;
}

div.dataTableParentHidden {
overflow: hidden !important;
height: 0px !important;
width: 100% !important;
display: none !important;
}

div.ui-draggable {
cursor: move !important;
cursor: grab !important;
cursor: -moz-grab !important;
cursor: -webkit-grab !important;
}

div.ui-draggable-dragging {
cursor: grabbing !important;
cursor: -moz-grabbing !important;
cursor: -webkit-grabbing !important;
}

div.dtsb-searchBuilder {
cursor: inherit !important;
}

div.dtsb-searchBuilder select {
cursor: pointer !important;
}

.loader-container {
width: 90% !important;
display: flex;
justify-content: center !important;
height: 33vh !important;
align-items: center !important;
}

.loader {
border: 16px solid
<?= $module::$COLORS["mediumGrey"] ?> !important;
border-top: 16px solid
<?= $module::$COLORS["primary"] ?> !important;
border-radius: 50% !important;
width: 120px !important;
height: 120px !important;
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

.text-muted-more {
    color: #aaa;
}

.logocenter {
  margin: auto;
  width: 25%;
  border: 3px solid green;
  padding: 250px;
}

.logocentercustom {
  display: flex;
  justify-content: center;
}
