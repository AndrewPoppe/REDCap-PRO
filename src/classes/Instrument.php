<?php

namespace YaleREDCap\REDCapPRO;

class Instrument
{
    public static $module;
    public static $instrument_name;
    public static $rcpro_dag;
    public static $PARTICIPANT;
    public static $PROJECT;
    public $dd;
    public $username;
    public $email;
    public $fname;
    public $lname;

    function __construct($module, $instrument_name, $rcpro_dag)
    {
        self::$module = $module;
        self::$PARTICIPANT = $module::$PARTICIPANT;
        self::$PROJECT = $module::$PROJECT;
        self::$instrument_name = $instrument_name;
        self::$rcpro_dag = $rcpro_dag;
        $this->dd = $this->getDD();
        $this->username = $this->getUsernameField();
        if (isset($this->username)) {
            $this->email = $this->getEmailField();
            $this->fname = $this->getFirstNameField();
            $this->lname = $this->getLastNameField();
        }
    }

    function getDD()
    {
        $json = \REDCap::getDataDictionary("json", false, null, [self::$instrument_name], false);
        return json_decode($json, true);
    }

    function getUsernameField()
    {
        foreach ($this->dd as $field) {
            if (
                strpos($field["field_annotation"], "@RCPRO-USERNAME") !== FALSE
                && $field["field_type"] === "text"
            ) {
                return $field["field_name"];
            }
        }
    }

    function getEmailField()
    {
        foreach ($this->dd as $field) {
            if (
                strpos($field["field_annotation"], "@RCPRO-EMAIL") !== FALSE
                && $field["field_type"] === "text"
                && $field["text_validation_type_or_show_slider_number"] === "email"
            ) {
                return $field["field_name"];
            }
        }
    }

    function getFirstNameField()
    {
        foreach ($this->dd as $field) {
            if (
                strpos($field["field_annotation"], "@RCPRO-FNAME") !== FALSE
                && $field["field_type"] === "text"
            ) {
                return $field["field_name"];
            }
        }
    }

    function getLastNameField()
    {
        foreach ($this->dd as $field) {
            if (
                strpos($field["field_annotation"], "@RCPRO-LNAME") !== FALSE
                && $field["field_type"] === "text"
            ) {
                return $field["field_name"];
            }
        }
    }

    function update_form()
    {
        if (isset($this->username)) {
            $rcpro_project_id = self::$PROJECT->getProjectIdFromPID(PROJECT_ID);
            $participants = self::$PARTICIPANT->getProjectParticipants($rcpro_project_id, self::$rcpro_dag);
            $options = "<option value=''>''</option>";
            $participants_json = json_encode($participants);
            foreach ($participants as $participant) {
                $inst_username = \REDCap::escapeHtml($participant["rcpro_username"]);
                $inst_email    = \REDCap::escapeHtml($participant["email"]);
                $inst_fname    = \REDCap::escapeHtml($participant["fname"]);
                $inst_lname    = \REDCap::escapeHtml($participant["lname"]);
                $options      .= "<option value='$inst_username' >$inst_username - $inst_fname $inst_lname - $inst_email</option>";
            }
            $replacement =  "<select id='username_selector'>$options</select>";
            self::$module->initializeJavascriptModuleObject();
?>
            <script>
                (function($, window, document) {
                    let module = <?= self::$module->getJavascriptModuleObjectName() ?>;
                    let participants_json = '<?= $participants_json ?>';
                    let participants_obj = JSON.parse(participants_json);
                    let participants = Object.values(participants_obj);
                    let empty_participant = {
                        email: "",
                        fname: "",
                        lname: ""
                    };

                    let username_input = $("input[name='<?= $this->username ?>']");
                    username_input.hide();
                    let username_select = $("<?= $replacement ?>")[0];
                    username_input.after(username_select);
                    $('#username_selector').select2({
                            placeholder: 'Select a participant',
                            allowClear: true
                        }).val(username_input.val()).trigger('change')
                        .on("change", (evt) => {
                            let val = evt.target.value;
                            let participant = empty_participant;
                            if (val !== "") {
                                participant = participants.filter((p) => p.rcpro_username === val)[0];
                            }
                            username_input.val(participant.rcpro_username);
                            let logParameters = {
                                rcpro_username: participant.rcpro_username,
                                redcap_user: "<?= USERID ?>"
                            };

                            // If there is an email field, update it
                            <?php if (isset($this->email)) { ?>
                                let email_input = $('input[name="<?= $this->email ?>"]');
                                if (email_input) {
                                    email_input.val(participant.email);
                                }
                            <?php } ?>

                            // If there is a fname field, update it
                            <?php if (isset($this->fname)) { ?>
                                let fname_input = $('input[name="<?= $this->fname ?>"]');
                                if (fname_input) {
                                    fname_input.val(participant.fname);
                                }
                            <?php } ?>

                            // If there is a lname field, update it
                            <?php if (isset($this->lname)) { ?>
                                let lname_input = $('input[name="<?= $this->lname ?>"]');
                                if (lname_input) {
                                    lname_input.val(participant.lname);
                                }
                            <?php } ?>

                            // Log this.
                            module.log("Populated REDCapPRO User Info On Form", logParameters);
                        });

                })(window.jQuery, window, document);
            </script>
<?php
        }
    }
}
