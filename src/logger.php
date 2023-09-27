<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

$role = $module->getUserRole($module->framework->getUser()->getUsername()); // 3=admin/manager, 2=user, 1=monitor, 0=not found
if ( $role < 3 ) {
    exit;
}

# Parse query string to grab page.
$referer      = $_SERVER['HTTP_REFERER'];
$qstring_orig = explode("?", $referer)[1];
parse_str($qstring_orig, $qstring);

if ( $qstring["prefix"] !== explode('_v', $module->getModuleDirectoryName())[0] ) {
    exit;
}

if (
    $qstring["page"] === "src/logs" ||
    ($qstring["page"] === "src/cc_logs" && $module->framework->getUser()->isSuperUser())
) {
    $module->logEvent("Exported logs", [
        "export_type" => $_POST["export_type"],
        "redcap_user" => $module->framework->getUser()->getUsername(),
        "export_page" => $qstring["page"]
    ]);
}