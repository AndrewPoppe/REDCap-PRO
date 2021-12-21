<?php

namespace YaleREDCap\REDCapPRO;

// Initialize the session
session_start();

// Unset all of the session variables
$_SESSION = array();

// Destroy the session.
session_destroy();

// Helpers
$UI = new UI($module);

// This method starts the html doc
$UI->ShowParticipantHeader($module->tt("logout_title"));
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
    <p><?= $module->tt("ui_close_tab") ?></p>
</div>
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

<?php $UI->EndParticipantPage(); ?>