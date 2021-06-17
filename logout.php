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

<div style="text-align: center;"><p>You may now close this browser tab.</p></div>
<script src="<?=$module->getUrl("lib/sweetalert/sweetalert2.all.min.js");?>"></script>
<script>
    Swal.fire({
        imageUrl: "<?= $module->getUrl("images/RCPro_Favicon.svg") ?>",
        imageWidth: '150px',
        html: '<strong>Due to inactivity, you have been logged out.</strong>',
        allowOutsideClick: false,
        confirmButtonText: "OK",
        confirmButtonColor: "#900000"
    });
</script>
<?php $module->UiEndParticipantPage(); ?>