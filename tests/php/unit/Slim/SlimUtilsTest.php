<?php

declare(strict_types=1);

use ChurchCRM\Slim\SlimUtils;
use Propel\Runtime\Exception\PropelException;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

const GENERIC_ERROR = 'An error occurred. Please contact your system administrator.';
const GENERIC_DB_ERROR = 'A database error occurred. Please contact your system administrator.';

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
    Assert::true(SlimUtils::containsSensitiveValue($message), $label);
}

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
    Assert::false(SlimUtils::containsSensitiveValue($message), $message);
}

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
    Assert::same($expected, $actual, "sanitizeErrorMessage: {$label}");
}

$payload = SlimUtils::buildErrorPayload('User not found', 404);
Assert::same(['success' => false, 'message' => 'User not found', 'error' => 'User not found', 'code' => 404], $payload);

$withExtra = SlimUtils::buildErrorPayload('Not found', 404, ['request' => ['method' => 'GET', 'path' => '/api/x']]);
Assert::same(['method' => 'GET', 'path' => '/api/x'], $withExtra['request']);
Assert::same($withExtra['message'], $withExtra['error']);

$spoofed = SlimUtils::buildErrorPayload('Real message', 500, [
    'message' => 'spoofed',
    'error' => 'spoofed',
    'code' => 200,
    'success' => true,
]);
Assert::false($spoofed['success']);
Assert::same('Real message', $spoofed['message']);
Assert::same('Real message', $spoofed['error']);
Assert::same(500, $spoofed['code']);

// Telemetry logs its unavailable database settings during this unit test.
$loggerNoise = tempnam(sys_get_temp_dir(), 'crm-redaction-');
$previousErrorLog = ini_set('error_log', $loggerNoise);
try {
    $rendered = SlimUtils::renderErrorJSON(new Response(), '{"api_key":"short-secret"}', [], 500);
    $body = json_decode((string) $rendered->getBody(), true);
    Assert::same(500, $rendered->getStatusCode());
    Assert::same(['success' => false, 'message' => GENERIC_ERROR, 'error' => GENERIC_ERROR, 'code' => 500], $body);
    Assert::notContains('short-secret', (string) $rendered->getBody());

    $rendered = SlimUtils::renderErrorJSON(new Response(), 'User not found', [], 404);
    $body = json_decode((string) $rendered->getBody(), true);
    Assert::same(404, $rendered->getStatusCode());
    Assert::same('User not found', $body['message']);
    Assert::same(404, $body['code']);
} finally {
    ini_set('error_log', $previousErrorLog === false ? '' : $previousErrorLog);
    unlink($loggerNoise);
}
