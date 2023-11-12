
function chooseAuthenticatorAppMFA() {
    $('#mfaChoiceContainer').hide();
    $('#mfaAuthenticatorContainer').show();
}

function chooseEmailMFA() {
    $('#mfaChoiceContainer').hide();
    $('#emailMFAContainer').show();
}

function showMFAChoice() {
    $('#mfaChoiceContainer').show();
    $('#mfaAuthenticatorContainer').hide();
    $('#emailMFAContainer').hide();
}