<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

// This is an API endpoint that can be used to register users
// It is NOAUTH and No CSRF, so API token is required
try {
    $apiHandler = new APIParticipantRegister($module, $_POST);
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

// Only allow Normal Users and above to register users
if ( (int) $apiHandler->getRole() < 2 ) {
    echo json_encode([
        "error" => "You do not have permission to use this API",
    ], JSON_PRETTY_PRINT);
    return;
}

// Try to register the users
try {
    $result = $apiHandler->registerUsers();
} catch ( \Throwable $e ) {
    $module->logError("Error registering users via API", $e);
    echo json_encode([
        "error" => $e->getMessage(),
    ], JSON_PRETTY_PRINT);
    return;
}

echo json_encode($result, JSON_PRETTY_PRINT);