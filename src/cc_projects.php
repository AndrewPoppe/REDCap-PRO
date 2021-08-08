<?php
if (!SUPER_USER) {
    return;
}

class Project
{
    public static $module;
    public static $rcpro_project_id;
    public static $redcap_pid;
    public static $info;
    public static $staff;

    function __construct($module, $redcap_pid)
    {
        self::$module           = $module;
        self::$redcap_pid       = $redcap_pid;
        self::$rcpro_project_id = self::$module::$PROJECT->getProjectIdFromPID($redcap_pid);
        self::$info             = $this->getProjectInfo();
        self::$staff            = $this->getStaff();
    }

    function getProjectInfo()
    {
        $SQL = "SELECT * FROM redcap_projects WHERE project_id = ?";
        $result_obj = self::$module->query($SQL, [self::$redcap_pid]);
        return $result_obj->fetch_assoc();
    }

    function getStatus()
    {
        $status_value = !is_null(self::$info["completed_time"]) ? "Completed" : self::$info["status"];
        switch ($status_value) {
            case 0:
                $result = "Development";
                break;
            case 1:
                $result = "Production";
                break;
            case 2:
                $result = "Analysis/Cleanup";
                break;
            case "Completed":
                $result = "Completed";
                break;
            default:
                $result = "Unknown";
                break;
        }
        return $result;
    }

    function getParticipantCount()
    {
        $SQL = "SELECT log_id WHERE message = 'LINK' AND rcpro_project_id = ? AND active = 1";
        return self::$module->countLogs($SQL, [self::$rcpro_project_id]);
    }

    function getStaff()
    {
        $managers = self::$module->getProjectSetting("managers", self::$redcap_pid);
        $users    = self::$module->getProjectSetting("users", self::$redcap_pid);
        $monitors = self::$module->getProjectSetting("monitors", self::$redcap_pid);
        $managers = is_null($managers) ? [] : $managers;
        $users    = is_null($users) ? [] : $users;
        $monitors = is_null($monitors) ? [] : $monitors;
        $allStaff = array_merge($managers, $users, $monitors);

        return [
            "managers" => $managers,
            "users"    => $users,
            "monitors" => $monitors,
            "allStaff" => $allStaff
        ];
    }

    function getRecordCount()
    {
        $SQL = "SELECT COUNT(record) num FROM redcap_record_list WHERE project_id = ?";
        $result_obj = self::$module->query($SQL, [self::$redcap_pid]);
        return $result_obj->fetch_assoc()["num"];
    }
}

?>
<!DOCTYPE html>
<html lang='en'>

<head>
    <meta charset='UTF-8'>
    <title>REDCapPRO Projects</title>
    <link rel="stylesheet" type="text/css" href="<?= $module->getUrl("css/rcpro.css") ?>">
</head>

<body>
    <?php
    require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';
    $module::$UI->ShowControlCenterHeader("Projects");
    $redcap_project_ids = $module->getProjectsWithModuleEnabled();
    ?>
    <div class="projectsContainer wrapper">
        <h2>Projects</h2>
        <p>All projects currently utilizing REDCapPRO</p>
        <div id="loading-container" class="loader-container">
            <div id="loading" class="loader"></div>
        </div>
        <div id="projects" class="dataTableParentHidden outer_container">
            <table class="table" id="RCPRO_TABLE" style="width:100%;">
                <caption>REDCapPRO Projects</caption>
                <thead>
                    <th class='dt-center'>Project ID</th>
                    <th class='dt-center'>REDCap PID</th>
                    <th>Title</th>
                    <th class='dt-center'>Status</th>
                    <th class='dt-center'># Participants</th>
                    <th class='dt-center'># Staff Members</th>
                    <th class='dt-center'># Records</th>
                </thead>
                <tbody>
                    <?php
                    foreach ($redcap_project_ids as $id) {
                        echo "<tr class='hover'>";
                        $thisProject = new Project($module, $id);
                        echo "<td class='dt-center'><a class='rcpro_project_link' href='" . $module->getUrl("src/home.php?pid=$id") . "'>" . $thisProject::$rcpro_project_id . "</a></td>";
                        echo "<td class='dt-center'><a class='rcpro_project_link' href='" . $module->getUrl("src/home.php?pid=$id") . "'>$id</a></td>";
                        echo "<td>" . $thisProject::$info["app_title"] . "</td>";
                        echo "<td class='dt-center'>" . $thisProject->getStatus() . "</td>";
                        echo "<td class='dt-center'><a class='rcpro_participant_link' href='" . $module->getUrl("src/manage.php?pid=$id") . "'>" . $thisProject->getParticipantCount() . "</a></td>";
                        echo "<td class='dt-center'><a class='rcpro_user_link' href='" . $module->getUrl("src/manage-users.php?pid=$id") . "'>" . count($thisProject::$staff["allStaff"]) . "</a></td>";
                        echo "<td class='dt-center'>" . $thisProject->getRecordCount() . "</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
        (function($, window, document) {
            $(document).ready(function() {
                $('#RCPRO_TABLE').DataTable({
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

                $('#projects').removeClass('dataTableParentHidden');
                $('#loading-container').hide();
                $('#RCPRO_TABLE').DataTable().columns.adjust().draw();

            });
        }(window.jQuery, window, document));
    </script>
</body>

</html>