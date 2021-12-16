<?php

$redcap_username = $module->getREDCapUsername(true);
$role = $module->getUserRole($redcap_username); // 3=admin/manager, 2=user, 1=monitor, 0=not found
$isSuperUser = $module->getUser($redcap_username)->isSuperUser();
if ($role < 3) {
    exit;
}

# Parse query string to grab page.
$referer = $_SERVER['HTTP_REFERER'];
$qstring_orig = explode("?", $referer)[1];
parse_str($qstring_orig, $qstring);

if ($qstring["prefix"] !== "redcap_pro") {
    exit;
}

if (
    $qstring["page"] === "src/logs" ||
    ($qstring["page"] === "src/cc_logs" && $isSuperUser)
) {
    $module->logEvent("Exported logs", [
        "export_type" => $_POST["export_type"],
        "redcap_user" => $module->getREDCapUsername(),
        "export_page" => $qstring["page"]
    ]);
}
