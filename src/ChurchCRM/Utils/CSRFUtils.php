<?php

namespace ChurchCRM\Utils;

/**
 * CSRF (Cross-Site Request Forgery) protection utility.
 *
 * Implements the standard synchronizer-token pattern: a single, stable token
 * per user session. The token is:
 *   - generated lazily on first use and reused (not rotated) for the rest of
 *     its lifetime, so rendering any number of forms — across pages, tabs, or
 *     duplicate/prefetch requests — never invalidates a token already embedded
 *     in an outstanding form;
 *   - validated with a timing-safe comparison;
 *   - expired after TOKEN_LIFETIME and refreshable via regenerateToken().
 *
 * The `$formId` parameters on the public methods are retained for backward
 * compatibility with existing call sites but are ignored: every form shares
 * the one session-wide token, matching how mainstream MVC frameworks
 * (Laravel, Django, Rails) implement CSRF protection.
 */
class CSRFUtils
{
    /**
     * Session key for storing the CSRF token
     */
    private const SESSION_KEY = 'csrf_token';

    /**
     * Token lifetime in seconds (default: 2 hours)
     */
    private const TOKEN_LIFETIME = 7200;

    /**
     * Ensure a PHP session is active before touching $_SESSION.
     */
    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    /**
     * Return the session-wide CSRF token, generating one on first use and
     * reusing the existing (non-expired) token thereafter.
     *
     * @param string $formId Ignored — retained for backward compatibility.
     * @return string The CSRF token
     */
    public static function generateToken(string $formId = 'default'): string
    {
        self::ensureSession();

        $existing = $_SESSION[self::SESSION_KEY] ?? null;
        if (
            is_array($existing)
            && isset($existing['token'], $existing['timestamp'])
            && (time() - $existing['timestamp']) <= self::TOKEN_LIFETIME
        ) {
            return $existing['token'];
        }

        // Generate a cryptographically secure random token.
        // 32 bytes = 256 bits of entropy, which becomes 64 hex characters after bin2hex()
        $token = bin2hex(random_bytes(32));

        $_SESSION[self::SESSION_KEY] = [
            'token' => $token,
            'timestamp' => time(),
        ];

        return $token;
    }

    /**
     * Validate a submitted token against the session-wide token.
     *
     * @param string $token   The token to validate
     * @param string $formId  Ignored — retained for backward compatibility.
     * @param bool   $consume Whether to consume the token after a successful validation
     * @return bool True if the token is valid, false otherwise
     */
    public static function validateToken(string $token, string $formId = 'default', bool $consume = false): bool
    {
        self::ensureSession();

        $stored = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($stored) || !isset($stored['token'], $stored['timestamp'])) {
            return false;
        }

        // Check if the token has expired
        if ((time() - $stored['timestamp']) > self::TOKEN_LIFETIME) {
            unset($_SESSION[self::SESSION_KEY]);
            return false;
        }

        // Timing-safe comparison
        $isValid = hash_equals((string) $stored['token'], $token);

        // Tokens are NOT consumed by default so a form can be resubmitted after a
        // validation error. Pass $consume = true for one-time use (e.g. after a
        // successful privileged operation).
        if ($isValid && $consume) {
            unset($_SESSION[self::SESSION_KEY]);
        }

        return $isValid;
    }

    /**
     * Force a fresh session-wide token. Useful after a successful privileged
     * operation (e.g. a password change) when the old token should be retired.
     *
     * @param string $formId Ignored — retained for backward compatibility.
     * @return string The new CSRF token
     */
    public static function regenerateToken(string $formId = 'default'): string
    {
        self::ensureSession();
        unset($_SESSION[self::SESSION_KEY]);

        return self::generateToken();
    }

    /**
     * Get the CSRF token hidden-input HTML for inclusion in forms.
     *
     * @param string $formId Ignored — retained for backward compatibility.
     * @return string HTML input field with the CSRF token
     */
    public static function getTokenInputField(string $formId = 'default'): string
    {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . InputUtils::escapeAttribute($token) . '">';
    }

    /**
     * Verify the CSRF token carried in request data.
     *
     * @param array  $requestData The request data (typically $_POST or the parsed body)
     * @param string $formId      Ignored — retained for backward compatibility.
     * @return bool True if the token is valid, false otherwise
     */
    public static function verifyRequest(array $requestData, string $formId = 'default'): bool
    {
        if (!isset($requestData['csrf_token']) || !is_string($requestData['csrf_token'])) {
            return false;
        }

        return self::validateToken($requestData['csrf_token']);
    }
}
