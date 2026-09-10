<?php

declare(strict_types=1);

// Local PHP development-server adapter for the real CRM module entry points.
if (PHP_SAPI !== 'cli-server' || getenv('PLUGIN_MIGRATION_BROWSER_TEST') !== '1'
    || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit;
}
$root = realpath(__DIR__ . '/../../../src');
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (str_contains($path, '..') || str_contains($path, '\\') || str_contains($path, "\0")) {
    http_response_code(400);
    exit;
}
if (is_file($root . $path)) {
    return false;
}
$module = explode('/', trim($path, '/'))[0];
$script = preg_match('/^[a-z][a-z0-9-]*$/D', $module) && is_file($root . '/' . $module . '/index.php')
    ? '/' . $module . '/index.php' : '/index.php';
$_SERVER['SCRIPT_NAME'] = $script;
$_SERVER['SCRIPT_FILENAME'] = $root . $script;
$_SERVER['PHP_SELF'] = $script;
chdir(dirname($root . $script));
require $root . $script;
