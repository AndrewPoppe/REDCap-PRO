<?php
echo "<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8'><title>".$module::$APPTITLE." - Menu</title>";

$role = SUPER_USER ? 3 : $module->getUserRole(USERID); // 3=admin/manager, 2=user, 1=monitor, 0=not found
if ($role > 0) {

    require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
    $module->UiShowHeader("Home");


    ?>
    <div class="infoContainer">
        <h2>Placeholder welcome text...</h2>
    </div>












    <?php
    include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
}