<?php

namespace YaleREDCap\REDCapPRO;

/**
 * Handles reCAPTCHA on the participant self-registration page.
 *
 * Uses reCAPTCHA Enterprise (createAssessment API) when a Google Cloud project
 * ID and API key are configured. Otherwise falls back to the legacy siteverify
 * API using the secret key.
 */
class Recaptcha
{
    const ACTION            = 'submit';
    const DEFAULT_THRESHOLD = 0.5;

    private $module;
    private $siteKey;
    private $projectId;
    private $apiKey;
    private $secretKey;
    private $threshold;

    public function __construct(REDCapPRO $module)
    {
        $this->module    = $module;
        $this->siteKey   = trim($module->framework->getSystemSetting('recaptcha-site-key') ?? '');
        $this->projectId = trim($module->framework->getSystemSetting('recaptcha-project-id') ?? '');
        $this->apiKey    = trim($module->framework->getSystemSetting('recaptcha-api-key') ?? '');
        $this->secretKey = trim($module->framework->getSystemSetting('recaptcha-secret-key') ?? '');
        $threshold       = $module->framework->getSystemSetting('recaptcha-score-threshold');
        $this->threshold = is_numeric($threshold) ? (float) $threshold : self::DEFAULT_THRESHOLD;
    }

    public function isEnabled() : bool
    {
        return $this->siteKey !== '';
    }

    public function usesEnterprise() : bool
    {
        return $this->projectId !== '' && $this->apiKey !== '';
    }

    public function getSiteKey() : string
    {
        return $this->siteKey;
    }

    public function getScriptUrl() : string
    {
        return $this->usesEnterprise()
            ? 'https://www.google.com/recaptcha/enterprise.js'
            : 'https://www.google.com/recaptcha/api.js';
    }

    /**
     * Verify a reCAPTCHA token with Google
     *
     * @param string $token The g-recaptcha-response value from the form
     *
     * @return bool True if the token is valid and the score passes the threshold
     */
    public function verify(string $token) : bool
    {
        $failureReason = $this->usesEnterprise() ? $this->verifyEnterprise($token) : $this->verifyLegacy($token);
        if ( $failureReason === null ) {
            return true;
        }
        $this->module->logEvent("reCAPTCHA verification failed", [
            "recaptcha_mode"   => $this->usesEnterprise() ? "enterprise" : "legacy",
            "recaptcha_reason" => $failureReason
        ]);
        return false;
    }

    /**
     * @return string|null Failure reason, or null on success
     */
    private function verifyEnterprise(string $token)
    {
        $url  = 'https://recaptchaenterprise.googleapis.com/v1/projects/' . rawurlencode($this->projectId) .
            '/assessments?key=' . rawurlencode($this->apiKey);
        $body = [
            'event' => [
                'token'          => $token,
                'siteKey'        => $this->siteKey,
                'expectedAction' => self::ACTION
            ]
        ];

        $info     = [];
        $response = http_post($url, $body, 10, 'application/json', '', [], $info, true);
        if ( $response === false ) {
            return 'Could not reach the reCAPTCHA Enterprise API (HTTP ' . ($info['http_code'] ?? 'unknown') . ')';
        }

        $data = json_decode($response, true);
        if ( !is_array($data) ) {
            return 'Unexpected response from the reCAPTCHA Enterprise API';
        }
        if ( isset($data['error']) ) {
            return 'reCAPTCHA Enterprise API error: ' . ($data['error']['status'] ?? '') . ' ' . ($data['error']['message'] ?? '');
        }

        $tokenProperties = $data['tokenProperties'] ?? [];
        if ( empty($tokenProperties['valid']) ) {
            return 'Invalid token: ' . ($tokenProperties['invalidReason'] ?? 'unknown reason');
        }
        if ( ($tokenProperties['action'] ?? '') !== self::ACTION ) {
            return 'Unexpected action: ' . ($tokenProperties['action'] ?? '');
        }

        $score = $data['riskAnalysis']['score'] ?? null;
        if ( !is_numeric($score) ) {
            return 'No score returned';
        }
        if ( (float) $score < $this->threshold ) {
            return 'Score ' . $score . ' is below threshold ' . $this->threshold .
                ' (' . implode(', ', $data['riskAnalysis']['reasons'] ?? []) . ')';
        }
        return null;
    }

    /**
     * @return string|null Failure reason, or null on success
     */
    private function verifyLegacy(string $token)
    {
        $info     = [];
        $response = http_post('https://www.google.com/recaptcha/api/siteverify', [
            'secret'   => $this->secretKey,
            'response' => $token
        ], 10, 'application/x-www-form-urlencoded', '', [], $info, true);
        if ( $response === false ) {
            return 'Could not reach the reCAPTCHA siteverify API (HTTP ' . ($info['http_code'] ?? 'unknown') . ')';
        }

        $data = json_decode($response, true);
        if ( !is_array($data) ) {
            return 'Unexpected response from the reCAPTCHA siteverify API';
        }
        if ( empty($data['success']) ) {
            return 'siteverify error: ' . implode(', ', $data['error-codes'] ?? [ 'unknown' ]);
        }
        // v3 keys return a score and action; v2 keys do not
        if ( isset($data['action']) && $data['action'] !== self::ACTION ) {
            return 'Unexpected action: ' . $data['action'];
        }
        if ( isset($data['score']) && (float) $data['score'] < $this->threshold ) {
            return 'Score ' . $data['score'] . ' is below threshold ' . $this->threshold;
        }
        return null;
    }
}
