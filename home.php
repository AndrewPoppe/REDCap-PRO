<?php
echo "<title>".$module::$APPTITLE." - Menu</title>";

$role = $module->getUserRole(USERID); // 3=admin/manager, 2=monitor, 1=user, 0=not found
if (SUPER_USER) {
    $role = 3;
}
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