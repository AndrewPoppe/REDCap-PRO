<?php
session_start();

$TABLES = array("USER", "PROJECT", "LINK");
foreach ($TABLES as $TABLE) {
    if ($module->tableExists($TABLE)) {
        echo "<br>Dropping Table: ${TABLE}";
        $module->dropTable($TABLE);
    }
    if (!$module->tableExists($TABLE)) {
        echo "<br>Creating Table: ${TABLE}";
        $module->createTable($TABLE);
        $module->createTestData($TABLE);
    } 
}

if (isset($_POST["REDCapPRO_UID"]) && isset($_POST["REDCapPRO_PW"])) {
    
    
    $stored_hash = $module->getHash($_POST["REDCapPRO_UID"]);
    if (empty($stored_hash)) {
        $pw_hash = password_hash($_POST["REDCapPRO_PW"], PASSWORD_DEFAULT);
        $module->createUser($_POST["REDCapPRO_UID"], $pw_hash);
        echo "USER CREATED";
    } else if (password_verify($_POST["REDCapPRO_PW"], $stored_hash)) {
        echo "AUTHENTICATED!";
    } else {
        echo "NOT AUTHENTICATED!";
    }
    
} else {
    
    ?>
    <html>
    <body>
        <div>
            <form action="<?=$module->getUrl("authenticate.php", true);?>" method="POST" enctype="multipart/form-data" target="_self" id="REDCapPRO_AUTH">
                <label for="REDCapPRO_UID">User ID</label>
                <input id="REDCapPRO_UID" name="REDCapPRO_UID" type="text" required>
                <label for="REDCapPRO_PW">Password</label>
                <input id="REDCapPRO_PW" name="REDCapPRO_PW" type="password" required>
            </form>
            <button type="submit" form="REDCapPRO_AUTH">Submit</button>
        </div>
    </body>
    <script>

    </script>
    </html>

<?

}
