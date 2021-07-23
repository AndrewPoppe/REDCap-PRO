<?php
// Initialize the session
session_start();
 
// Unset all of the session variables
$_SESSION = array();
 
// Destroy the session.
session_destroy();

// This method starts the html doc
$module->UiShowParticipantHeader("Logged Out");
?>
<style>
    .swal2-timer-progress-bar {
        background: #900000 !important;
    }
    button.swal2-confirm:focus {
        box-shadow: 0 0 0 3px rgb(144 0 0 / 50%) !important;
    }
</style>
<div style="text-align: center;"><p>You may now close this browser tab.</p></div>
<script src="<?=$module->getUrl("lib/sweetalert/sweetalert2.all.min.js");?>"></script>
<script>
    Swal.fire({
        imageUrl: "<?= $module->getUrl("images/RCPro_Favicon.svg") ?>",
        imageWidth: '150px',
        html: '<strong>You have been logged out due to inactivity.</strong>',
        allowOutsideClick: false,
        confirmButtonText: "OK",
        confirmButtonColor: "#900000"
    });
</script>

<?php $module->UiEndParticipantPage(); ?>