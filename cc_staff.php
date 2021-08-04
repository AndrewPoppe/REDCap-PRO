<?php

function getAllUsers() {
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

function getStaff($redcap_pid) {
    global $module;
    $managers = $module->getProjectSetting("managers", $redcap_pid);
    $users    = $module->getProjectSetting("users", $redcap_pid);
    $monitors = $module->getProjectSetting("monitors", $redcap_pid);
    $managers = is_null($managers) ? [] : $managers;
    $users    = is_null($users) ? [] : $users;
    $monitors = is_null($monitors) ? [] : $monitors;
    
    return array_merge($managers, $users, $monitors);
}

function createProjectsCell(array $projects) {
    global $module;
    $result = "<td  class='dt-center'>";
    foreach ($projects as $project) {
        $link_class = 'rcpro_project_link';    
        $url = $module->getUrl("manage-users.php?pid=${project}");
        $result .= "<div><a class='${link_class}' href='${url}'>PID ${project}</a></div>";
    }
    $result .= "</td>";
    return $result;
}

?>
<!DOCTYPE html>
    <?php
    if (!SUPER_USER) {
        return;
    }
    require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';
    $module->UiShowControlCenterHeader("Staff");
    
    // Get array of staff (users)
    $users = getAllUsers();    
    ?>
    <style>
        .wrapper { 
            display: inline-block; 
            padding: 20px; 
        }
        div.dataTableParentHidden {
            overflow: hidden;
            height: 0px;
            display: none;
        }
        #users {
            border-radius: 5px;
            border: 1px solid #cccccc;
            padding: 20px;
            box-shadow: 0px 0px 5px #eeeeee;
            min-width: 50vw !important;
        }
        #RCPRO_USERS {
            width: 100% !important;
        }
        #RCPRO_USERS tr.even {
            background-color: white !important;
        }
        #RCPRO_USERS tr.odd {
            background-color: white !important;
        }
        table.dataTable tbody td {
            vertical-align: middle;
        }
        .dt-center {
            text-align: center;
        }
        button:hover {
            outline: none !important;
        }
        .rcpro_project_link {
            color: #000090 !important;
            font-weight: bold !important;
        }
        .rcpro_project_link:hover {
            color: #900000 !important;
            font-weight: bold !important;
            cursor: pointer !important;
        }
        .rcpro_project_link_inactive {
            color: #101010 !important;
            text-decoration: line-through !important;
        }
        .rcpro_project_link_inactive:hover {
            color: #000000 !important;
            cursor: pointer !important;
            text-decoration: line-through !important;
        }
        .rcpro_user_link {
            color: #000090 !important;
            font-weight: bold !important;
        }
        .rcpro_user_link:hover {
            color: #900000 !important;
            font-weight: bold !important;
            cursor: pointer !important;
            background-color: #ddd !important;
        }
        .loader {
            border: 16px solid #ddd; /* Light grey */
            border-top: 16px solid #900000; /* Red */
            border-radius: 50%;
            width: 120px;
            height: 120px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>

    <div class="usersContainer wrapper">
        <h2>Staff Members</h2>
        <p>All users across studies</p>
        <div id="loading-container" class="loader-container">
            <div id="loading" class="loader"></div>
        </div>
        <div id="users" class="dataTableParentHidden">
            <table class="table" id="RCPRO_USERS">
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
                        $userLink = APP_PATH_WEBROOT_FULL . APP_PATH_WEBROOT . "ControlCenter/view_users.php?username=".$user["username"];   
                    ?>
                        <tr>
                            <td class="rcpro_user_link" onclick="(function(){window.location.href='<?=$userLink?>';})();"><?=$user["username"]?></td>
                            <td class="dt-center"><?=$user["name"]?></td>
                            <td><?=$user["email"]?></td>
                            <?=createProjectsCell($user["projects"]);?>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
        (function($, window, document) {            
			$(document).ready( function () {
				let usersTable = $('#RCPRO_USERS').DataTable({
                    dom: 'lBfrtip',
					stateSave: true,
                    stateSaveCallback: function(settings,data) {
                        localStorage.setItem( 'DataTables_' + settings.sInstance, JSON.stringify(data) )
                    },
                    stateLoadCallback: function(settings) {
                        return JSON.parse( localStorage.getItem( 'DataTables_' + settings.sInstance ) )
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