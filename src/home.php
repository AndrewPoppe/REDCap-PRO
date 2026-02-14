<?php
namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

$role = $module->getUserRole($module->safeGetUsername()); // 3=admin/manager, 2=user, 1=monitor, 0=not found
$module->includeFont();
$language = new Language($module);
$language->handleLanguageChangeRequest();   
echo "<title>" . $module->APPTITLE . " - " . $module->tt("project_home_title") . "</title>";
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$ui = new UI($module);
$ui->ShowHeader("Home");
$hereLink = "";
?>


<link rel="stylesheet" type="text/css" href="<?= $module->getUrl("src/css/rcpro.php") ?>" />
<div class="infoContainer wrapper">
    <div class="rcpro-form home-form">
        <h4><?= $module->tt("project_home_overview") ?></h4>
        <br>
        <ul>
            <li><?= $module->tt("project_home_bullet1") ?></li>
            <li><?= $module->tt("project_home_bullet2") ?></li>
            <li><?= $module->tt("project_home_bullet3") ?></li>
        </ul>

        <div style="text-align: center; margin:20px;">
            <?= $module->tt("project_home_click") ?>
            <a style='font-size:inherit;' href='https://github.com/AndrewPoppe/REDCap-PRO#readme' target="_blank"
                rel="noreferrer noopener"><?= $module->tt("project_home_here") ?></a>
            <?= $module->tt("project_home_footer") ?>
        </div>
    </div>
</div>

<?php
include_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';