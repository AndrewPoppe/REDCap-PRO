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
        self::$rcpro_project_id = self::$module->getProjectIdFromPID($redcap_pid);
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
    <style>
        .wrapper {
            display: inline-block;
            padding: 20px;
            margin-left: auto;
            margin-right: auto;
        }

        div.dataTableParentHidden {
            overflow: hidden;
            height: 0px;
            display: none;
        }

        #projects {
            border-radius: 5px;
            border: 1px solid #cccccc;
            padding: 20px;
            box-shadow: 0px 0px 5px #eeeeee;
            min-width: 50vw !important;
        }

        #RCPRO_Projects tr.even {
            background-color: white !important;
        }

        #RCPRO_Projects tr.odd {
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

        .rcpro_link {
            color: #000090 !important;
            font-weight: bold !important;
        }

        .rcpro_link:hover {
            color: #900000 !important;
            font-weight: bold !important;
            cursor: pointer !important;
        }

        .loader-container {
            width: 90%;
            display: flex;
            justify-content: center;
            height: 33vh;
            align-items: center;
        }

        .loader {
            border: 16px solid #ddd;
            /* Light grey */
            border-top: 16px solid #900000;
            /* Red */
            border-radius: 50%;
            width: 120px;
            height: 120px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <?php
    require_once APP_PATH_DOCROOT . 'ControlCenter/header.php';
    $module->UiShowControlCenterHeader("Projects");
    $redcap_project_ids = $module->getProjectsWithModuleEnabled();
    ?>
    <div class="projectsContainer wrapper">
        <h2>Projects</h2>
        <p>All projects currently utilizing REDCapPRO</p>
        <div id="loading-container" class="loader-container">
            <div id="loading" class="loader"></div>
        </div>
        <div id="projects" class="dataTableParentHidden">
            <table class="table" id="RCPRO_Projects" style="width:100%;">
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
                        echo "<tr>";
                        $thisProject = new Project($module, $id);
                        echo "<td class='dt-center'><a class='rcpro_link' href='" . $module->getUrl("src/home.php?pid=$id") . "'>" . $thisProject::$rcpro_project_id . "</a></td>";
                        echo "<td class='dt-center'><a class='rcpro_link' href='" . $module->getUrl("src/home.php?pid=$id") . "'>$id</a></td>";
                        echo "<td>" . $thisProject::$info["app_title"] . "</td>";
                        echo "<td class='dt-center'>" . $thisProject->getStatus() . "</td>";
                        echo "<td class='dt-center'><a class='rcpro_link' href='" . $module->getUrl("src/manage.php?pid=$id") . "'>" . $thisProject->getParticipantCount() . "</a></td>";
                        echo "<td class='dt-center'><a class='rcpro_link' href='" . $module->getUrl("src/manage-users.php?pid=$id") . "'>" . count($thisProject::$staff["allStaff"]) . "</a></td>";
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
                $('#RCPRO_Projects').DataTable({
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
                $('#RCPRO_Projects').DataTable().columns.adjust().draw();

            });
        }(window.jQuery, window, document));
    </script>
</body>

</html>