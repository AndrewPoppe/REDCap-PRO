<?php
namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

$auth = new Auth($module->APPTITLE);
session_id($_COOKIE[$auth->SESSION_NAME]);
session_name($auth->SESSION_NAME);
session_start();

$results = [
    "redcap_session_active" => false,
    "redcappro_logged_in"   => $auth->is_logged_in()
];

// Check whether there is a current REDCap session (non-survey)
$phpsessid = $auth->get_redcap_session_id();
if ( isset($phpsessid) ) {
    $results["redcap_session_active"] = \Session::read($phpsessid) != false;
}

echo json_encode($results);