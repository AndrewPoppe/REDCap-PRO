<?php header("Content-type: text/css; charset: UTF-8");?>

.testColor {
    color: <?=$module::$COLORS["darkGrey"] ?>;
}

.wrapper {
    display: inline-block;
    padding: 20px;
}

.rcpro-form {
    border-radius: 5px;
    border: 1px solid #cccccc;
    padding: 20px 20px 0px 20px;
    box-shadow: 0px 0px 5px #eeeeee;
}

.rcpro-form-button-old {
    margin: 20px 5px 0px;
}

.rcpro-datatable tr.even {
    background-color: white !important;    
}

.rcpro-datatable tr.odd {
    background-color: white !important;    
}

#RCPRO_TABLE tr {
    cursor: pointer;
}

#RCPRO_TABLE tr:hover {
    background-color: <?=$module::$COLORS["mediumGrey"] ?> !important;
}

#RCPRO_TABLE tr.selected {
    background-color: <?=$module::$COLORS["primary"] ?> !important;
    color: white !important;
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

.btn-rcpro {
    background-color: <?=$module::$COLORS["primary"] ?>;
    border-color: <?=$module::$COLORS["primary"] ?>;
    color: white;
}

.btn-rcpro:active,
.btn-rcpro:hover {
    background-color: <?=$module::$COLORS["primaryDark"] ?>;
    border-color: <?=$module::$COLORS["primaryDark"] ?>;
    color: white;
}

.btn-rcpro:focus {
    outline: 0;
    box-shadow: 0 0 0 0.2rem <?=$module::$COLORS["primary"] ?>63;
}

.enroll-wrapper {
    width: 720px;
}

.enroll-form {
    width: 540px !important;
}

.confirm-form {
    width: 500px !important;
}

.searchResult {
    cursor: pointer;
    padding: 5px;
    margin-top: 5px;
    background-color: <?=$module::$COLORS["mediumGrey"] ?>;
    border-radius: 5px;
    color: black;
}

.searchResult:hover {
    background-color: <?=$module::$COLORS["primary"] ?>;
    color: white;
}

.register-form {
    width: 360px !important;
}

#infotext {
    cursor: pointer;
    text-decoration: underline;
    font-weight: bold;
    color: <?= $module::$COLORS["secondary"] ?>;
}

#infotext:hover {
    text-shadow: 0px 0px 5px <?= $module::$COLORS["secondary"] ?>;
}

.swal2-icon.swal2-info {
    color: <?= $module::$COLORS["secondary"] ?>;
    border-color: <?= $module::$COLORS["secondary"] ?>;
}