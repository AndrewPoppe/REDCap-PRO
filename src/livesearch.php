<?php

namespace YaleREDCap\REDCapPRO;

$role = SUPER_USER ? 3 : $module->getUserRole(USERID); // 3=admin/manager, 2=user, 1=monitor, 0=not found
if ($role < 2) {
    exit();
}

$q = $_GET["q"];

if (!filter_var($q, FILTER_VALIDATE_EMAIL)) {
    echo "<font style='color: red;'>Search term is not an email address</font>";
    return;
}

$result = $module->PARTICIPANT_HELPER->searchParticipants($q);

$hint = "";
$rcpro_participant_id = null;
while ($row = $result->fetch_assoc()) {
    $fname    = \REDCap::escapeHtml($row["fname"]);
    $lname    = \REDCap::escapeHtml($row["lname"]);
    $email    = \REDCap::escapeHtml($row["email"]);
    $username = \REDCap::escapeHtml($row["rcpro_username"]);
    $id       = \REDCap::escapeHtml($row["log_id"]);
    $rcpro_participant_id = $id;
    $hint .= "<div class='searchResult' onclick='populateSelection(\"${fname}\", \"${lname}\", \"${email}\", \"${id}\", \"${username}\");'><strong>${username}</strong> - ${fname} ${lname} - ${email}</div>";
}

$participant = new Participant($module, ["rcpro_participant_id" => $rcpro_participant_id]);

if (!$participant->exists) {
    $response = "No Participants Found";
} else if (!$participant->isActive()) {
    $response = "<div class='searchResult'>The user associated with this email is not currently active in REDCapPRO.<br>Contact your REDCap Administrator with questions.</div>";
} else {
    $response = $hint;
}

echo $response;
