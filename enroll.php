<?php

// Initialize Authentication
$module::$AUTH::init();

echo "<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8'>
<title>".$module::$APPTITLE." - Enroll</title>";
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->UiShowHeader("Enroll");


?>
    <style>
        .wrapper { 
            width: 720px; 
            padding: 20px; 
        }
        .enroll-form {
            width: 360px;
            border-radius: 5px;
            border: 1px solid #cccccc;
            padding: 20px;
            box-shadow: 0px 0px 5px #eeeeee;
        }
        .confirm-form {
            width: 500px;
            border-radius: 5px;
            border: 1px solid #cccccc;
            padding: 20px;
            box-shadow: 0px 0px 5px #eeeeee;
        }
        .searchResult {
            cursor: pointer;
            padding: 5px;
            margin-top: 5px;
            background-color: #d1e8ff;
            border-radius: 5px;
            color: black;
        }
        .searchResult:hover {
            background-color: #0582ff;
            color: white;
        }
        button:hover {
            outline: none !important;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <h2>Enroll a Participant</h2>
        <p>Search for a participant by email or name, and enroll the selected participant in this project.</p>
        <script>
            function showResult(str) {
                if (str.length < 3) {
                    document.getElementById("searchResults").innerHTML="";
                    return;
                }
                var xmlhttp=new XMLHttpRequest();
                xmlhttp.onreadystatechange=function() {
                    if (this.readyState==4 && this.status==200) {
                        document.getElementById("searchResults").innerHTML=this.responseText;
                    }
                }
                xmlhttp.open("GET","<?=$module->getUrl("livesearch.php")?>&q="+str,true);
                xmlhttp.send();
            }
            function populateSelection(fname,lname,email,id) {
                $("#fname").val(fname);
                $("#lname").val(lname);
                $("#email").val(email);
                $("#id").val(id);
                $("#enroll-form").hide();
                $("#confirm-form").show();
            }
            function resetForm() {
                $('#REDCapPRO_Search').val("");
                showResult("");
                $("#enroll-form").show();
                $("#confirm-form").hide();
            }
        </script>
        <form class="enroll-form" id="enroll-form">
            <div class="form-group">
                <div id="searchContainer">
                    <label>Search</label>
                    <input type="text" name="REDCapPRO_Search" id="REDCapPRO_Search" class="form-control" onkeyup="showResult(this.value)">
                    <div class="searchResults" id="searchResults"></div>
                </div>
            </div>
        </form>
        <form class="confirm-form" name="confirm-form" id="confirm-form" action="<?= $module->getUrl("enroll.php");?>" method="POST" enctype="multipart/form-data" target="_self" style="display:none;">
            <div class="form-group">
                <div class="selection" id="selectionContainer">
                    <div class="mb-3 row">
                        <label for="fname" class="col-sm-3 col-form-label">First Name:</label>
                        <div class="col-sm-9">
                            <input type="text" id="fname" name="fname" class="form-control-plaintext" disabled readonly>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label for="lname" class="col-sm-3 col-form-label">Last Name:</label>
                        <div class="col-sm-9">
                            <input type="text" id="lname" name="lname" class="form-control-plaintext" disabled readonly>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label for="email" class="col-sm-3 col-form-label">Email:</label>
                        <div class="col-sm-9">
                            <input type="text" id="email" name="email" class="form-control-plaintext" disabled readonly>
                        </div>
                    </div>
                    <input type="text" id="id" name="id" class="form-control" readonly hidden>
                    <div>
                        <hr>
                        <button type="submit" class="btn btn-primary">Enroll Participant</button>
                        <button type="button" onclick="(function() { resetForm(); return false;})()" class="btn btn-secondary">Cancel</button>
                    </div>
                </div>
            </div>
        </form> 

        <?php 
            if (isset($_POST["id"]) && isset($project_id)) {
                $rcpro_participant_id = intval($_POST["id"]);
                $pid = intval($project_id);
                $result = $module->enrollParticipant($rcpro_participant_id, $pid);

                if ($result === -1) {
                    echo "<script>Swal.fire({'title':'This user is already enrolled in this project', 'icon':'info'});</script>";
                }
                else if ($result === TRUE) {
                    echo "<script>Swal.fire({'title':'The user was successfully enrolled in this project', 'icon':'success'});</script>";
                } 
                else if (!$result) {
                    echo "<script>Swal.fire({'title':'There was a problem enrolling this user in this project', 'icon':'error'});</script>";
                }
            }
        ?>
    </div>    
</body>
</html>




<?php
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';