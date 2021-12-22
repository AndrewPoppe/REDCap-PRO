<?php

namespace YaleREDCap\REDCapPRO;

$currentUser = new REDCapProUser($module);
$role = $currentUser->getUserRole($module->getProjectId());
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
    ($qstring["page"] === "src/cc_logs" && defined("SUPER_USER") && constant("SUPER_USER"))
) {
    $module->logEvent("Exported logs", [
        "export_type" => $_POST["export_type"],
        "redcap_user" => $currentUser->username,
        "export_page" => $qstring["page"]
    ]);
}
