<?php
session_id($_COOKIE[$module::$AUTH::$SESSION_NAME]);
session_name($module::$AUTH::$SESSION_NAME);
session_start();

$results = [
    "redcap_session_active" => false,
    "redcappro_logged_in" => $module::$AUTH->is_logged_in()
];

// Check whether there is a current REDCap session (non-survey)
$phpsessid = $_COOKIE["PHPSESSID"];
if (isset($phpsessid)) {
    $results["redcap_session_active"] = \Session::read($phpsessid) != false;
}

echo json_encode($results);
