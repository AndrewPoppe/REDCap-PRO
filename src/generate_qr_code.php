<?php

namespace YaleREDCap\REDCapPRO;

require_once APP_PATH_LIBRARIES . "phpqrcode/lib/full/qrlib.php";

$otpauth = filter_input(INPUT_GET, 'otpauth', FILTER_SANITIZE_ENCODED);

\QRcode::png(urldecode($otpauth), false, 'H', 4);