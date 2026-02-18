<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

// This is an API endpoint that can be used to register and enroll participants
// It is NOAUTH and No CSRF, so API token is required
try {

    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    if ( $action == "register" ) {
        $apiHandler = new APIParticipantRegister($module, $_POST);
    } elseif ( $action == "enroll" ) {
        $apiHandler = new APIParticipantEnroll($module, $_POST);
    } else {
        throw new \Error($module->tt("api_error1"));
    }

    $projectSettings = new ProjectSettings($module);
    if ( !$projectSettings->apiEnabled($apiHandler->project->getProjectId()) ) {
        throw new \Error($module->tt("api_error2"));
    }

    if ( !$apiHandler->valid ) {
        echo json_encode($apiHandler->errorMessages, JSON_PRETTY_PRINT);
        throw new \Error($module->tt("api_error3"));
    }
} catch ( \Throwable $e ) {
    $module->logError("Error using API", $e);
    echo json_encode([
        "error" => $e->getMessage(),
    ], JSON_PRETTY_PRINT);
    return;
}

// Only allow Normal Users and above to use the API
if ( ((int) $apiHandler->getRole()) < 2 ) {
    echo json_encode([
        "error" => $module->tt("api_error4"),
    ], JSON_PRETTY_PRINT);
    return;
}

// Try to enroll/register the participants
try {
    $result = $apiHandler->takeAction();
} catch ( \Throwable $e ) {
    $module->logError("Error using the REDCapPRO API", $e);
    echo json_encode([
        "error" => $e->getMessage(),
    ], JSON_PRETTY_PRINT);
    return;
}

echo json_encode($result, JSON_PRETTY_PRINT);