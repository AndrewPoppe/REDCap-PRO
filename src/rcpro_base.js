let rcpro = {
    logo: "",
    logoutPage: "",
    sessionCheckPage: "",
    module: null,
    timeout_minutes: 0,
    warning_minutes: 0,
    warningOpen: false,
    seconds: 0,
    stop: false
}
rcpro.warning_duration = rcpro.timeout_minutes - rcpro.warning_minutes;
rcpro.initTimeout = function() {
    let lastTS = Date.now();
    let timeout;
    let events = ["scroll", "click", "keyup", "wheel"];
    let startTimer = function timer() {
        if (rcpro.stop) {
            return
        }
        let newTS = Date.now();
        rcpro.seconds = Math.floor((newTS - lastTS) / 1000);
        if (rcpro.seconds >= (rcpro.timeout_minutes * 60)) {
            rcpro.logout();
        }
        if (rcpro.seconds >= (rcpro.warning_minutes * 60) && !rcpro.warningOpen) {
            rcpro.warningOpen = true;
            rcpro.logoutWarning();
        }
        timeout = setTimeout(timer, 1000);
    }
    let resetTimer = function() {
        if (rcpro.warningOpen) {
            Swal.close();
        }
        rcpro.seconds = 0;
        lastTS = Date.now();
        clearTimeout(timeout);
        startTimer();
    }
    events.forEach((evt) => {
        document.addEventListener(evt, resetTimer);
    });
    startTimer();
};

rcpro.initSessionCheck = function() {
    setInterval(async function() {
        let result = await fetch(rcpro.sessionCheckPage)
            .then(resp => resp.json());
        if (result.redcap_session_active || !result.redcappro_logged_in) {
            rcpro.logout(true);
        }
    }, 1000);
}

rcpro.logoutWarning = function() {
    let timerInterval;
    return Swal.fire({
        imageUrl: rcpro.logo,
        imageWidth: '150px',
        html: `<strong>${rcpro.module.tt("timeout_message1")}</strong><br>${rcpro.module.tt("timeout_message2")}`,
        confirmButtonText: rcpro.module.tt("timeout_button_text"),
        confirmButtonColor: "#900000",
        allowEnterKey: false,
        onOpen: () => {
            timerInterval = setInterval(() => {
                const content = Swal.getHtmlContainer()
                if (content) {
                    let remaining = (rcpro.timeout_minutes * 60) - rcpro.seconds;
                    let rDate = new Date(remaining * 1000);
                    let formatted = `${rDate.getMinutes()}:${String(rDate.getSeconds()).padStart(2,0)}`;
                    content.innerHTML = `<strong>${rcpro.module.tt("timeout_message1", formatted)}</strong><br>${rcpro.module.tt("timeout_message2")}`;
                }
            }, 100)
        },
        onClose: () => {
            rcpro.warningOpen = false;
            clearInterval(timerInterval)
        }
    });
};
rcpro.logout = function(cancelPopup) {
    rcpro.stop = true;
    $('body').html('');
    let logoutPage = cancelPopup ? rcpro.logoutPage + "&cancelPopup=true" : rcpro.logoutPage;
    location.href = logoutPage;
}

window.rcpro = rcpro;