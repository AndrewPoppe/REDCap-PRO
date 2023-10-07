<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

$role = $module->getUserRole($module->safeGetUsername()); // 3=admin/manager, 2=user, 1=monitor, 0=not found
if ( $role < 3 ) {
    header("location:" . $module->getUrl("src/home.php"));
}
$module->includeFont();

require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->UI->ShowHeader("Users");
echo "<title>" . $module->APPTITLE . " - Staff</title>
<link rel='stylesheet' type='text/css' href='" . $module->getUrl('src/css/rcpro.php') . "'/>";

// Get list of users
$project = $module->getProject();

// Check for errors
if ( isset($_GET["error"]) ) {
    ?>
    <script>
        Swal.fire({
            icon: "error",
            title: "Error",
            text: "There was a problem. Please try again.",
            showConfirmButton: false
        });
    </script>
    <?php
}

$module->framework->initializeJavascriptModuleObject();

// Update roles if requested
if ( $_SERVER["REQUEST_METHOD"] == "POST" ) {

    // Log submission
    $module->logForm("Submitted Manage Staff Form", $_POST);

    try {
        $userList = $project->getUsers();
        foreach ( $userList as $user ) {
            $username = $user->getUsername();
            parse_str($username, $username_temp);
            $username_clean = array_key_first($username_temp);
            $newRole        = strval($_POST["role_select_${username_clean}"]);
            $oldRole        = strval($module->getUserRole($username));
            if ( isset($newRole) && $newRole !== '' && $newRole !== $oldRole ) {
                $module->changeUserRole($username, $oldRole, $newRole);
            }
        }
        ?>
        <script>
            Swal.fire({
                icon: "success",
                title: "Roles successfully changed",
                showConfirmButton: false
            });
        </script>
        <?php
    } catch ( \Exception $e ) {
        ?>
        <script>
            Swal.fire({
                icon: "error",
                title: "Error",
                text: "<?php echo $e->getMessage(); ?>",
                showConfirmButton: false
            });
        </script>
        <?php
    }
}


?>

<div class="manageContainer wrapper" style="display: none;">
    <h2>Manage Study Staff</h2>
    <p>Set <span id="infotext" onclick="(function() {
            Swal.fire({
                icon: 'info',
                iconColor: 'black',
                title: 'Staff Roles',
                confirmButtonText: 'Got it!',
                confirmButtonColor: '<?php echo $module::$COLORS["secondary"]; ?>',
                html: 'Staff may have one of the following roles:<br><br>'+
                    '<div style=\'text-align:left;\'>'+
                        '<ul>'+
                            '<li><strong>Manager:</strong> Highest permissions. Has the ability to grant/revoke staff access. You are a manager if you are reading this.</li>'+
                            '<li><strong>User:</strong> Able to view participant identifying information, register participants, enroll/disenroll participants in the study, and initiate password reset.</li>'+
                            '<li><strong>Monitor:</strong> Basic access. Can only view usernames and initiate password resets.</li>'+
                        '</ul><br>'+
                        '</div>'
            })})();">staff permissions</span> to REDCapPRO</p>
    <div id="loading-container" class="loader-container">
        <div id="loading" class="loader"></div>
    </div>
    <div id="parent" class="dataTableParentHidden">
        <form class="rcpro-form" id="manage-users-form" action="<?php echo $module->getUrl("src/manage-users.php"); ?>"
            method="POST" enctype="multipart/form-data" target="_self">
            <div class="form-group">
                <table class="rcpro-datatable" id="RCPRO_TABLE">
                    <caption></caption>
                    <thead>
                        <tr>
                            <th id="rcpro_username">Username</th>
                            <th id="rcpro_name">Name</th>
                            <th id="rcpro_email">Email</th>
                            <th id="rcpro_role">User Role</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
                <button class="btn btn-rcpro rcpro-form-button role_select_button" id="role_select_submit" type="submit"
                    disabled>Save Changes</button>
                <button class="btn btn-secondary rcpro-form-button role_select_button" id="role_select_reset"
                    disabled>Reset</button>
            </div>
            <input type="hidden" name="redcap_csrf_token" value="<?= $module->framework->getCSRFToken() ?>">
        </form>
    </div>
</div>
<script>
    const RCPRO_module = <?= $module->framework->getJavascriptModuleObjectName() ?>;
    (function ($, window, document) {
        $(document).ready(function () {
            function checkRoleChanges() {
                let changed = false;
                $('.role_select').each(function (i, el) {
                    let val = $(el).val();
                    let orig_val = $(el).attr('orig_value');
                    if (val !== orig_val) {
                        changed = true;
                    }
                });
                return changed;
            }

            function handleButtons() {
                let changed = checkRoleChanges();
                if (changed) {
                    $('#role_select_submit').removeAttr("disabled");
                    $('#role_select_reset').removeAttr("disabled");
                } else {
                    $('#role_select_submit').attr("disabled", true);
                    $('#role_select_reset').attr("disabled", true);
                }
            }

            $('#role_select_reset').on('click', function (evt) {
                evt.preventDefault();
                $('.role_select').each(function (i, el) {
                    $(el).val($(el).attr('orig_value'));
                    $('#role_select_submit').attr("disabled", true);
                    $('#role_select_reset').attr("disabled", true);
                });
                handleButtons();
            });

            const dt = $('#RCPRO_TABLE').DataTable({
                deferRender: true,
                ajax: function (data, callback, settings) {
                    RCPRO_module.ajax('getStaff', {})
                        .then(response => {
                            callback({ data: response });
                        })
                        .catch(error => {
                            console.error(error);
                            callback({ data: [] });
                        });
                },
                columns: [
                    {
                        title: 'Username',
                        data: 'username'
                    },
                    {
                        title: 'Name',
                        data: 'fullname'
                    },
                    {
                        title: 'Email',
                        data: 'email'
                    },
                    {
                        title: 'User Role',
                        data: function (row, type, val, meta) {
                            if (type === 'display') {
                                const select = document.createElement('select');
                                select.name = `role_select_${row.username}`;
                                select.id = `role_select_${row.username}`;
                                $(select).addClass('role_select');
                                $(select).attr('orig_value', row.role);
                                $(select).attr('form', 'manage-users-form');
                                select.add(new Option('No Access', 0, row.role == 0 ? 'selected' : ''));
                                select.add(new Option('Monitor', 1, row.role == 1 ? 'selected' : ''));
                                select.add(new Option('Normal User', 2, row.role == 2 ? 'selected' : ''));
                                select.add(new Option('Manager', 3, row.role == 3 ? 'selected' : ''));

                                return select.outerHTML;
                            }
                            return row.role;
                        },
                        // createdCell: function (td, cellData, rowData, row, col) {
                        //     $(td).data('order', rowData.role);
                        //     $(td).attr('data-order', rowData.role);

                        // }
                    }
                ],
                stateSave: true,
                stateSaveCallback: function (settings, data) {
                    localStorage.setItem('DataTables_staff_' + settings.sInstance, JSON.stringify(data))
                },
                stateLoadCallback: function (settings) {
                    return JSON.parse(localStorage.getItem('DataTables_staff_' + settings.sInstance))
                },
                drawCallback: function (settings) {
                    $('.role_select').one("change", handleButtons);
                },
                scrollY: '50vh',
                scrollCollapse: true
            });
            $('.role_select').one("change", handleButtons);
            $('#parent').removeClass('dataTableParentHidden');
            $('.wrapper').show();
            $('#loading-container').hide();
            dt.columns.adjust().draw();
        });
    })(window.jQuery, window, document);
</script>

<?php
include_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';