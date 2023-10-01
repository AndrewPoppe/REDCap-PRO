<?php

namespace YaleREDCap\REDCapPRO;

/** @var REDCapPRO $module */

$token = $module->framework->sanitizeAPIToken(filter_input(INPUT_GET, "token", FILTER_SANITIZE_STRING));
echo $token;