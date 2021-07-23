<?php

$role = SUPER_USER ? 3 : $module->getUserRole(USERID); // 3=admin/manager, 2=monitor, 1=user, 0=not found
if ($role < 2) {
    exit();
}

$q = $_GET["q"];
$wq = "%${q}%";
$table = $module->getTable("USER");


$SQL = "SELECT fname, lname, email, id FROM ${table}". 
" WHERE fname LIKE ?
OR lname LIKE ?
OR email LIKE ?";

try {
    $result = $module->query($SQL, [$wq, $wq, $wq]);
}
catch (\Exception $e) {
    $module->logError("Error performing livesearch.", $e);
    echo $e->getMessage();
    exit();
}


$hint = "";
while ($row = $result->fetch_assoc()) {
    $fname = \REDCap::escapeHtml($row["fname"]);
    $lname = \REDCap::escapeHtml($row["lname"]);
    $email = \REDCap::escapeHtml($row["email"]);
    $id = \REDCap::escapeHtml($row["id"]);
    $hint .= "<div class='searchResult' onclick='populateSelection(\"${fname}\", \"${lname}\", \"${email}\", \"${id}\");'>${fname} ${lname} - ${email}</div>";
}

if ($hint === "") {
    $response = "No Participants Found";
} else {
    $response = $hint;
}

echo $response;
?>

