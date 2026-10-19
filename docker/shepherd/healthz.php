<?php

declare(strict_types=1);

/**
 * Shepherd deployment readiness probe.
 *
 * This intentionally avoids ChurchCRM's normal bootstrap because bootstrap
 * can write configuration, initialize sessions, and redirect on failure. The
 * probe performs only bounded, read-only dependency checks and returns a
 * deliberately small public payload.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET' && $method !== 'HEAD') {
    header('Allow: GET, HEAD');
    http_response_code(405);
    if ($method !== 'HEAD') {
        echo json_encode(['status' => 'method_not_allowed'], JSON_THROW_ON_ERROR);
    }
    exit;
}

$checks = [
    'database' => 'unavailable',
    'storage' => 'unavailable',
    'mail' => 'not_configured',
];

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $database = mysqli_init();
    if ($database === false) {
        throw new RuntimeException('Unable to initialize the database client');
    }

    $database->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
    $database->real_connect(
        getenv('SHEPHERD_DB_HOST') ?: 'database',
        getenv('SHEPHERD_DB_USER') ?: 'shepherd',
        getenv('SHEPHERD_DB_PASSWORD') ?: '',
        getenv('SHEPHERD_DB_NAME') ?: 'shepherd',
        (int) (getenv('SHEPHERD_DB_PORT') ?: 3306),
    );
    $database->set_charset('utf8mb4');

    // Querying an application table distinguishes an initialized Shepherd
    // database from a reachable but empty MariaDB instance.
    $result = $database->query(
        "SELECT cfg_name, cfg_value FROM config_cfg WHERE cfg_name = 'sSMTPHost' LIMIT 1",
    );
    $mailConfig = $result->fetch_assoc();
    $result->free();
    $database->close();

    $checks['database'] = 'ok';
    $environmentMailHost = trim((string) (getenv('SHEPHERD_SMTP_HOST') ?: ''));
    $databaseMailHost = trim((string) ($mailConfig['cfg_value'] ?? ''));
    if ($environmentMailHost !== '' || $databaseMailHost !== '') {
        // This is a configuration check only. A health endpoint must not open
        // an SMTP connection every 30 seconds or risk delaying readiness.
        $checks['mail'] = 'configured';
    }
} catch (Throwable) {
    // Never include exception messages in the response or logs: database
    // drivers commonly embed hostnames and connection details in them.
}

$requiredPaths = [
    'Images',
    'Images/Person',
    'Images/Family',
    'uploads',
    'SQL',
    'logs',
    'tmp_attach',
    'plugins',
];
$storageReady = true;
clearstatcache();
foreach ($requiredPaths as $path) {
    $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . $path;
    if (!is_dir($absolutePath) || !is_writable($absolutePath)) {
        $storageReady = false;
        break;
    }
}
if ($storageReady) {
    $checks['storage'] = 'ok';
}

$ready = $checks['database'] === 'ok' && $checks['storage'] === 'ok';
$status = $ready ? 'ready' : 'unready';
http_response_code($ready ? 200 : 503);

if (!$ready) {
    error_log((string) json_encode([
        'event' => 'shepherd_readiness_failed',
        'database' => $checks['database'],
        'storage' => $checks['storage'],
    ], JSON_UNESCAPED_SLASHES));
}

if ($method !== 'HEAD') {
    echo json_encode([
        'status' => $status,
        'checks' => $checks,
    ], JSON_THROW_ON_ERROR);
}
