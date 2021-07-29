<?php

$role = SUPER_USER ? 3 : $module->getUserRole(USERID); // 3=admin/manager, 2=user, 1=monitor, 0=not found
if ($role < 2) {
    exit();
}

$q = $_GET["q"];
$wq = "%${q}%";


$result = $module->searchParticipants($wq);

$hint = "";
while ($row = $result->fetch_assoc()) {
    $fname = \REDCap::escapeHtml($row["fname"]);
    $lname = \REDCap::escapeHtml($row["lname"]);
    $email = \REDCap::escapeHtml($row["email"]);
    $username = \REDCap::escapeHtml($row["rcpro_username"]);
    $id = \REDCap::escapeHtml($row["log_id"]);
    $hint .= "<div class='searchResult' onclick='populateSelection(\"${fname}\", \"${lname}\", \"${email}\", \"${id}\", \"${username}\");'><strong>${username}</strong> - ${fname} ${lname} - ${email}</div>";
}

if ($hint === "") {
    $response = "No Participants Found";
} else {
    $response = $hint;
}

echo $response;
?>

