<?php

/**
 * scripts/test-error-redaction.php — regression tests for the /api error
 * redaction rules and the canonical error payload (#9737).
 *
 * Usage:
 *   php scripts/test-error-redaction.php
 *   npm run test:php
 *
 * ChurchCRM has no PHPUnit suite, so this is a dependency-free CLI harness.
 * It calls the real static methods on ChurchCRM\Slim\SlimUtils through the
 * Composer autoloader and prints one TAP-style line per case. It needs
 * `composer install` in src/ but no database, web server or Config.php.
 *
 * Two kinds of case are kept side by side on purpose: messages that carry a
 * secret *value* and must be replaced by the generic text, and ordinary
 * messages that merely contain a word like "user", "host" or "password" and
 * must reach the caller intact. A change that fixes one side by breaking the
 * other fails here.
 *
 * Exit codes:
 *   0  — every case passed
 *   1  — at least one case failed
 *   2  — vendor autoloader missing
 */

declare(strict_types=1);

use ChurchCRM\Slim\SlimUtils;
use Propel\Runtime\Exception\PropelException;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

$autoload = __DIR__ . '/../src/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Missing {$autoload} — run `composer install` in src/ first.\n");
    exit(2);
}
require $autoload;

const GENERIC_ERROR = 'An error occurred. Please contact your system administrator.';
const GENERIC_DB_ERROR = 'A database error occurred. Please contact your system administrator.';

$passed = 0;
$failed = 0;

/**
 * Record one case. $detail is printed only on failure.
 */
$check = function (bool $ok, string $label, string $detail = '') use (&$passed, &$failed): void {
    $n = $passed + $failed + 1;
    if ($ok) {
        $passed++;
        echo "ok {$n} - {$label}\n";
        return;
    }
    $failed++;
    echo "not ok {$n} - {$label}" . ($detail !== '' ? "  # {$detail}" : '') . "\n";
};

// ──────────────────────────────────────────────────────────────────
//  containsSensitiveValue(): messages that carry a secret value.
// ──────────────────────────────────────────────────────────────────

$mustRedact = [
    'bare credential assignment'              => 'password=hunter2',
    'credential name with colon'              => 'api_key: short-secret',
    'quoted JSON key, no whitespace'          => '{"password":"hunter2"}',
    'quoted JSON key, short api_key value'    => '{"api_key":"short-secret"}',
    'quoted key with spaces around the colon' => '{ "secret" : "s3cr3t" }',
    'single-quoted PHP array key'             => "['token' => 'abc123']",
    'Authorization header'                    => 'Authorization: Bearer abc.def.ghi',
    'DSN fragment'                            => 'mysql:host=db;dbname=churchcrm;user=crm',
    'credentials embedded in a URL'           => 'https://crm:hunter2@db.example.com/churchcrm',
    'PEM key material'                        => "-----BEGIN RSA PRIVATE KEY-----\nMIIEow",
    'JSON Web Token'                          => 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxIn0.abcdefghijk',
    'URL-safe opaque token (40 chars)'        => 'key ' . str_repeat('a1B2', 10) . ' was rejected',
    'AWS secret key split by slashes'         => 'bad signature for wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
    'standard base64 with lowercase runs'     => 'ABCDE/abcde/FGHIJ/KLMNO/PQRST/UVWXY/01234/56789=',
    'standard base64 with plus and padding'   => 'blob dGhpcyBpcyBhIHNlY3JldCB2YWx1ZQ==+abcdefghij/KLMNO=',
    'complete IPv4 address'                   => 'Connection to 10.0.12.34 refused',
];

foreach ($mustRedact as $label => $message) {
    $check(
        SlimUtils::containsSensitiveValue($message) === true,
        "redacts: {$label}",
        'containsSensitiveValue(' . json_encode($message) . ') returned false'
    );
}

// ──────────────────────────────────────────────────────────────────
//  containsSensitiveValue(): ordinary messages that must stay readable.
// ──────────────────────────────────────────────────────────────────

$mustRemainReadable = [
    'User not found',
    'Volunteer opportunity not found',
    'Ghostwriter field is required',
    'Value must be between 1.5 and 3.5',
    'password must be at least 8 characters',
    'Password is required',
    'Invalid login or password',
    'The token has expired',
    'Username must be unique',
    'Host is unreachable',
    'Version 7.7.0 requires PHP 8.4',
    'Person 1234 is not a member of family 56',
];

foreach ($mustRemainReadable as $message) {
    $check(
        SlimUtils::containsSensitiveValue($message) === false,
        'keeps readable: ' . $message,
        'containsSensitiveValue(' . json_encode($message) . ') returned true'
    );
}

// ──────────────────────────────────────────────────────────────────
//  sanitizeErrorMessage(): the classification applied to real exceptions.
// ──────────────────────────────────────────────────────────────────

$request = (new ServerRequestFactory())->createServerRequest('GET', '/api/user/1/setting/theme');

$sanitized = [
    'generic exception carrying serialized credentials' => [
        new RuntimeException('{"password":"hunter2"}'),
        GENERIC_ERROR,
    ],
    'generic exception carrying standard base64' => [
        new RuntimeException('ABCDE/abcde/FGHIJ/KLMNO/PQRST/UVWXY/01234/56789='),
        GENERIC_ERROR,
    ],
    'generic exception with an ordinary message' => [
        new RuntimeException('User not found'),
        'User not found',
    ],
    'ORM exception collapses to the database message' => [
        new PropelException('Unable to execute INSERT statement [INSERT INTO events_event (event_title) VALUES (:p0)]'),
        GENERIC_DB_ERROR,
    ],
    'PDO exception collapses to the database message' => [
        new PDOException('SQLSTATE[22001]: String data, right truncated'),
        GENERIC_DB_ERROR,
    ],
    'HTTP exception message is intentionally user-facing' => [
        new HttpNotFoundException($request, 'User not found'),
        'User not found',
    ],
];

foreach ($sanitized as $label => [$exception, $expected]) {
    $actual = SlimUtils::sanitizeErrorMessage($exception);
    $check($actual === $expected, "sanitizeErrorMessage: {$label}", 'got ' . json_encode($actual));
}

// ──────────────────────────────────────────────────────────────────
//  buildErrorPayload(): one shape, canonical keys always win.
// ──────────────────────────────────────────────────────────────────

$payload = SlimUtils::buildErrorPayload('User not found', 404);
$check(
    array_keys($payload) === ['success', 'message', 'error', 'code']
        && $payload['success'] === false
        && $payload['message'] === 'User not found'
        && $payload['error'] === 'User not found'
        && $payload['code'] === 404,
    'buildErrorPayload: canonical shape',
    json_encode($payload)
);

$withExtra = SlimUtils::buildErrorPayload('Not found', 404, ['request' => ['method' => 'GET', 'path' => '/api/x']]);
$check(
    ($withExtra['request']['path'] ?? null) === '/api/x' && $withExtra['error'] === $withExtra['message'],
    'buildErrorPayload: non-colliding extra keys are kept',
    json_encode($withExtra)
);

$spoofed = SlimUtils::buildErrorPayload('Real message', 500, [
    'message' => 'spoofed',
    'error'   => 'spoofed',
    'code'    => 200,
    'success' => true,
]);
$check(
    $spoofed['message'] === 'Real message'
        && $spoofed['error'] === 'Real message'
        && $spoofed['code'] === 500
        && $spoofed['success'] === false,
    'buildErrorPayload: extra keys cannot displace the canonical ones',
    json_encode($spoofed)
);

// ──────────────────────────────────────────────────────────────────
//  renderErrorJSON(): the response a route handler actually sends.
//
//  It also logs through the app logger, whose telemetry handler cannot
//  read its settings without a database and reports that via error_log().
//  Capture that output and surface it as a TAP diagnostic rather than
//  letting it look like a failure in CI.
// ──────────────────────────────────────────────────────────────────

$loggerNoise = tempnam(sys_get_temp_dir(), 'crm-redaction-');
$previousErrorLog = ini_set('error_log', $loggerNoise);

$rendered = SlimUtils::renderErrorJSON(new Response(), '{"api_key":"short-secret"}', [], 500);
$body = json_decode((string) $rendered->getBody(), true);
$check(
    $rendered->getStatusCode() === 500
        && is_array($body)
        && $body['message'] === GENERIC_ERROR
        && $body['error'] === GENERIC_ERROR
        && $body['code'] === 500
        && $body['success'] === false
        && !str_contains((string) $rendered->getBody(), 'short-secret'),
    'renderErrorJSON: serialized credential is replaced by the generic message',
    (string) $rendered->getBody()
);

$rendered = SlimUtils::renderErrorJSON(new Response(), 'User not found', [], 404);
$body = json_decode((string) $rendered->getBody(), true);
$check(
    $rendered->getStatusCode() === 404 && is_array($body) && $body['message'] === 'User not found' && $body['code'] === 404,
    'renderErrorJSON: ordinary message reaches the caller intact',
    (string) $rendered->getBody()
);

ini_set('error_log', $previousErrorLog === false ? '' : $previousErrorLog);
foreach (array_unique(file($loggerNoise, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) as $line) {
    echo '# logger (expected without a database): ' . preg_replace('/^\[[^\]]*\] /', '', $line) . "\n";
}
unlink($loggerNoise);

// ──────────────────────────────────────────────────────────────────

$total = $passed + $failed;
echo "1..{$total}\n";
echo "# {$total} cases, {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
