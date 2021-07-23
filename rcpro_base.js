let rcpro = {
    logo:"",
    logoutPage:"",
    timeout_minutes: 0.5, //TODO: MAKE THESE MODULE SETTINGS
    warning_minutes: 0.1,
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
        rcpro.seconds = Math.floor((newTS - lastTS)/1000);
        if (rcpro.seconds >= (rcpro.timeout_minutes*60)) {
            rcpro.logout();
        }
        if (rcpro.seconds >= (rcpro.warning_minutes*60) && !rcpro.warningOpen) {
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
    
rcpro.logoutWarning = function() {
    let timerInterval;
    return Swal.fire({
        imageUrl: rcpro.logo,
        imageWidth: '150px',
        html: `<strong>Due to inactivity, you will be logged out in <b></b></strong><br>Click the button below to continue on this page.`,
        confirmButtonText: "Continue on this page",
        confirmButtonColor: "#900000",
        onOpen: () => {
            timerInterval = setInterval(() => {
                const content = Swal.getHtmlContainer()
                if (content) {
                    const b = content.querySelector('b')
                    if (b) {
                        let remaining = (rcpro.timeout_minutes*60) - rcpro.seconds;
                        let rDate = new Date(remaining*1000);
                        let formatted = `${rDate.getMinutes()}:${String(rDate.getSeconds()).padStart(2,0)}`;
                        b.textContent = formatted;
                    }
                }
            }, 100)
        },
        onClose: () => {
            rcpro.warningOpen = false;
            clearInterval(timerInterval)
        }
    });
};
rcpro.logout = function () {
    rcpro.stop = true;
    $('body').html('');
    location.href = rcpro.logoutPage;
}

window.rcpro = rcpro;
console.log("REDCapPRO LOADED");