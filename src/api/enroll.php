<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

// This is an API endpoint that can be used to enroll already-registered users
// It is NOAUTH and No CSRF, so API token is required
try {
    $apiHandler = new APIParticipantEnroll($module, $_POST);

    $projectSettings = new ProjectSettings($module);
    if ( !$projectSettings->apiEnabled($apiHandler->project->getProjectId()) ) {
        throw new \Error("API is not enabled for this project");
    }

    if ( !$apiHandler->valid ) {
        echo json_encode($apiHandler->errorMessages, JSON_PRETTY_PRINT);
        throw new \Error("Invalid API payload");
    }
} catch ( \Throwable $e ) {
    $module->logError("Error using API", $e);
    echo json_encode([
        "error" => $e->getMessage(),
    ], JSON_PRETTY_PRINT);
    return;
}

// Only allow Normal Users and above to enroll users
if ( (int) $apiHandler->getRole() < 2 ) {
    echo json_encode([
        "error" => "You do not have permission to use this API",
    ], JSON_PRETTY_PRINT);
    return;
}

// Try to enroll the users
try {
    $result = $apiHandler->enrollUsers();
} catch ( \Throwable $e ) {
    $module->logError("Error enrolling users via API", $e);
    echo json_encode([
        "error" => $e->getMessage(),
    ], JSON_PRETTY_PRINT);
    return;
}

echo json_encode($result, JSON_PRETTY_PRINT);