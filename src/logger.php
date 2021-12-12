<?php

$role = SUPER_USER ? 3 : $module->getUserRole(USERID); // 3=admin/manager, 2=user, 1=monitor, 0=not found
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
    ($qstring["page"] === "src/cc_logs" && SUPER_USER)
) {
    $module->logEvent("Exported logs", [
        "export_type" => $_POST["export_type"],
        "redcap_user" => USERID,
        "export_page" => $qstring["page"]
    ]);
}
