<?php

namespace YaleREDCap\REDCapPRO;

if (!constant("SUPER_USER")) {
    exit;
}

function createProjectsCell(array $projects)
{
    global $module;
    $result = "<td  class='dt-center'>";
    foreach ($projects as $project) {
        $link_class = 'rcpro_project_link';
        $url = $module->getUrl("src/manage-users.php?pid=${project}");
        $result .= "<div><a class='${link_class}' href='${url}'>PID ${project}</a></div>";
    }
    $result .= "</td>";
    return $result;
}

// Helpers
$UI = new UI($module);

require_once constant("APP_PATH_DOCROOT") . 'ControlCenter/header.php';
$UI->ShowControlCenterHeader("Staff");
echo '<link rel="stylesheet" type="text/css" href="' . $module->getUrl("src/css/rcpro_cc.php") . '">';

// Get array of staff (users)
$users = $module->getAllUsers();
?>
<div id="loading-container" class="loader-container">
    <div id="loading" class="loader"></div>
</div>
<div class="usersContainer wrapper" style="display: none;">
    <h2>Staff Members</h2>
    <p>All users across studies</p>
    <div id="users" class="dataTableParentHidden outer_container">
        <table class="table" id="RCPRO_TABLE">
            <caption>REDCapPRO Staff</caption>
            <thead>
                <tr>
                    <th id="username">Username</th>
                    <th id="name" class="dt-center">Name</th>
                    <th id="email">Email</th>
                    <th id="projects" class="dt-center">Projects</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user) {
                    $userLink = constant("APP_PATH_WEBROOT_FULL") . constant("APP_PATH_WEBROOT") . "ControlCenter/view_users.php?username=" . $user["username"];
                ?>
                    <tr>
                        <td class="rcpro_user_link" onclick="(function(){window.location.href='<?= $userLink ?>';})();"><?= $user["username"] ?></td>
                        <td class="dt-center"><?= $user["name"] ?></td>
                        <td><?= $user["email"] ?></td>
                        <?= createProjectsCell($user["projects"]); ?>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>
<script>
    (function($, window, document) {
        $(document).ready(function() {
            let dataTable = $('#RCPRO_TABLE').DataTable({
                dom: 'lBfrtip',
                stateSave: true,
                stateSaveCallback: function(settings, data) {
                    localStorage.setItem('DataTables_ccstaff_' + settings.sInstance, JSON.stringify(data))
                },
                stateLoadCallback: function(settings) {
                    return JSON.parse(localStorage.getItem('DataTables_ccstaff_' + settings.sInstance))
                },
                scrollY: '50vh',
                scrollCollapse: true,
                pageLength: 100
            });
            $('#users').removeClass('dataTableParentHidden');
            $('#loading-container').hide();
            $('.wrapper').show();
            dataTable.columns.adjust().draw();

        });
    }(window.jQuery, window, document));
</script>

<?php
require_once constant("APP_PATH_DOCROOT") . 'ControlCenter/footer.php';
?>