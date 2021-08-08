<?php

//TODO: Move these to Main Class
function getAllUsers()
{
    global $module;
    $projects = $module->getProjectsWithModuleEnabled();
    $users = array();
    foreach ($projects as $pid) {
        $staff = getStaff($pid);
        foreach ($staff as $user) {
            if (isset($users[$user])) {
                array_push($users[$user]['projects'], $pid);
            } else {
                $newUser = $module->getUser($user);
                $newUserArr = [
                    "username" => $user,
                    "email" => $newUser->getEmail(),
                    "name" => $module->getUserFullname($user),
                    "projects" => [$pid]
                ];
                $users[$user] = $newUserArr;
            }
        }
    }
    return $users;
}

function getStaff($redcap_pid)
{
    global $module;
    $managers = $module->getProjectSetting("managers", $redcap_pid);
    $users    = $module->getProjectSetting("users", $redcap_pid);
    $monitors = $module->getProjectSetting("monitors", $redcap_pid);
    $managers = is_null($managers) ? [] : $managers;
    $users    = is_null($users) ? [] : $users;
    $monitors = is_null($monitors) ? [] : $monitors;

    return array_merge($managers, $users, $monitors);
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

?>
<!DOCTYPE html>
<link rel="stylesheet" type="text/css" href="<?= $module->getUrl("css/rcpro.css") ?>">
<?php
if (!SUPER_USER) {
    return;
}
require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';
$module::$UI->ShowControlCenterHeader("Staff");

// Get array of staff (users)
$users = getAllUsers();
?>
<div class="usersContainer wrapper">
    <h2>Staff Members</h2>
    <p>All users across studies</p>
    <div id="loading-container" class="loader-container">
        <div id="loading" class="loader"></div>
    </div>
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
                    $userLink = APP_PATH_WEBROOT_FULL . APP_PATH_WEBROOT . "ControlCenter/view_users.php?username=" . $user["username"];
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
            let usersTable = $('#RCPRO_TABLE').DataTable({
                dom: 'lBfrtip',
                stateSave: true,
                stateSaveCallback: function(settings, data) {
                    localStorage.setItem('DataTables_' + settings.sInstance, JSON.stringify(data))
                },
                stateLoadCallback: function(settings) {
                    return JSON.parse(localStorage.getItem('DataTables_' + settings.sInstance))
                },
                scrollY: '50vh',
                scrollCollapse: true,
                pageLength: 100
            });
            $('#users').removeClass('dataTableParentHidden');
            $('#loading-container').hide();
            usersTable.columns.adjust().draw();

        });
    }(window.jQuery, window, document));
</script>

<?php
require_once APP_PATH_DOCROOT . 'ControlCenter/footer.php';
?>
</body>

</html>