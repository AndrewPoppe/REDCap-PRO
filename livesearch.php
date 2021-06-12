<?php


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
    echo $e->getMessage();
}


$hint = "";
while ($row = $result->fetch_assoc()) {
    $fname = $row["fname"];
    $lname = $row["lname"];
    $email = $row["email"];
    $id = $row["id"];
    $hint .= "<div class='searchResult' onclick='populateSelection(\"${fname}\", \"${lname}\", \"${email}\", \"${id}\");'>${fname} ${lname} - ${email}</div>";
}

if ($hint === "") {
    $response = "No Participants Found";
} else {
    $response = $hint;
}

echo $response;
?>

