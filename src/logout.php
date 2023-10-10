<?php
namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

// Initialize the session
$auth = new Auth($module->APPTITLE);
$auth->init();

// Destroy the session.
$auth->destroySession();

// Unset all of the session variables
session_unset();

// Whether to cancel showing the popup
$cancelPopup = $_GET['cancelPopup'];

// This method starts the html doc
$ui = new UI($module);
$ui->ShowParticipantHeader($module->tt("logout_title"));
?>
<style>
    .swal2-timer-progress-bar {
        background: #900000 !important;
    }

    button.swal2-confirm:focus {
        box-shadow: 0 0 0 3px rgb(144 0 0 / 50%) !important;
    }
</style>
<div style="text-align: center;">
    <p>
        <?= $module->tt("ui_close_tab") ?>
    </p>
</div>
<?php if ( !$cancelPopup ) { ?>
    <script src="<?= $module->getUrl("lib/sweetalert/sweetalert2.all.min.js"); ?>"></script>
    <script>
        Swal.fire({
            imageUrl: "<?= $module->getUrl("images/RCPro_Favicon.svg") ?>",
            imageWidth: '150px',
            html: '<strong><?= $module->tt("logout_message1") ?></strong>',
            allowOutsideClick: false,
            confirmButtonText: "OK",
            confirmButtonColor: "#900000"
        });
    </script>
<?php }
$ui->EndParticipantPage(); ?>