<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

// echo "OK";
// $module->framework->log('ok', [
//     "GET"  => json_encode($_GET, JSON_PRETTY_PRINT),
//     "POST" => json_encode($_POST, JSON_PRETTY_PRINT),
// ]);
// $token = filter_input(INPUT_POST, "token", FILTER_SANITIZE_STRING);
// $module->framework->log('ok2');
// $token_clean = $module->framework->sanitizeAPIToken($token);
// echo $token;
echo json_encode($_POST, JSON_PRETTY_PRINT);
try {
    $apiHandler = new APIHandler($module, $_POST);
} catch ( \Throwable $e ) {
    $module->logError("Error using API", $e);
    echo json_encode([
        "error" => $e->getMessage(),
    ], JSON_PRETTY_PRINT);
    return;
}
echo json_encode($apiHandler->getApiData(), JSON_PRETTY_PRINT);