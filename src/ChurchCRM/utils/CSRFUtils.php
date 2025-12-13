<?php

namespace ChurchCRM\Utils;

/**
 * CSRF (Cross-Site Request Forgery) protection utility.
 * Provides methods to generate and validate CSRF tokens for protecting
 * against CSRF attacks on state-changing operations.
 */
class CSRFUtils
{
    /**
     * Session key for storing CSRF tokens
     */
    private const SESSION_KEY = 'csrf_tokens';

    /**
     * Token lifetime in seconds (default: 2 hours)
     */
    private const TOKEN_LIFETIME = 7200;

    /**
     * Generate a new CSRF token and store it in the session.
     * Each token is associated with a form identifier to allow multiple forms
     * on the same page.
     *
     * @param string $formId Unique identifier for the form (e.g., 'changePassword')
     * @return string The generated CSRF token
     */
    public static function generateToken(string $formId = 'default'): string
    {
        // Ensure session is started
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Initialize token storage if needed
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        // Generate a cryptographically secure random token
        // 32 bytes = 256 bits of entropy, which becomes 64 hex characters after bin2hex()
        $token = bin2hex(random_bytes(32));
        
        // Store token with timestamp for expiration checking
        $_SESSION[self::SESSION_KEY][$formId] = [
            'token' => $token,
            'timestamp' => time()
        ];

        // Clean up old tokens
        self::cleanupExpiredTokens();

        return $token;
    }

    /**
     * Validate a CSRF token against the stored token for a specific form.
     *
     * @param string $token The token to validate
     * @param string $formId The form identifier
     * @param bool $consume Whether to consume the token after validation (default: false)
     * @return bool True if the token is valid, false otherwise
     */
    public static function validateToken(string $token, string $formId = 'default', bool $consume = false): bool
    {
        // Ensure session is started
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Check if tokens exist in session
        if (!isset($_SESSION[self::SESSION_KEY][$formId])) {
            return false;
        }

        $storedData = $_SESSION[self::SESSION_KEY][$formId];
        $storedToken = $storedData['token'] ?? null;
        $storedTime = $storedData['timestamp'] ?? null;
        $currentTime = time();
        $timeDiff = $currentTime - $storedTime;
        
        // Check if token has expired
        if ($timeDiff > self::TOKEN_LIFETIME) {
            // Remove expired token
            unset($_SESSION[self::SESSION_KEY][$formId]);
            return false;
        }

        // Validate token using timing-safe comparison
        $isValid = hash_equals($storedToken, $token);

        // Optionally consume token after validation
        // By default, tokens are NOT consumed to allow form resubmission on validation errors
        // The form templates regenerate a new token when re-rendered, so if validation fails
        // and the form is shown again with errors, a fresh token will be automatically generated.
        // Set $consume = true when you want one-time use (e.g., after successful operation)
        if ($isValid && $consume) {
            unset($_SESSION[self::SESSION_KEY][$formId]);
        }

        return $isValid;
    }

    /**
     * Regenerate a CSRF token for a specific form.
     * Useful after successful form submission to get a fresh token.
     *
     * @param string $formId The form identifier
     * @return string The new generated CSRF token
     */
    public static function regenerateToken(string $formId = 'default'): string
    {
        // Remove old token
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION[self::SESSION_KEY][$formId])) {
            unset($_SESSION[self::SESSION_KEY][$formId]);
        }
        
        // Generate and return new token
        return self::generateToken($formId);
    }

    /**
     * Clean up expired tokens from the session to prevent memory bloat.
     *
     * @return void
     */
    private static function cleanupExpiredTokens(): void
    {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            return;
        }

        $currentTime = time();
        foreach ($_SESSION[self::SESSION_KEY] as $formId => $data) {
            if ($currentTime - $data['timestamp'] > self::TOKEN_LIFETIME) {
                unset($_SESSION[self::SESSION_KEY][$formId]);
            }
        }
    }

    /**
     * Get the CSRF token input field HTML for inclusion in forms.
     *
     * @param string $formId The form identifier
     * @return string HTML input field with CSRF token
     */
    public static function getTokenInputField(string $formId = 'default'): string
    {
        $token = self::generateToken($formId);
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Verify CSRF token from request data.
     * This is a convenience method that extracts the token from request data
     * and validates it.
     *
     * @param array $requestData The request data (typically $_POST or parsed body)
     * @param string $formId The form identifier
     * @return bool True if the token is valid, false otherwise
     */
    public static function verifyRequest(array $requestData, string $formId = 'default'): bool
    {
        if (!isset($requestData['csrf_token'])) {
            return false;
        }

        return self::validateToken($requestData['csrf_token'], $formId);
    }
}
