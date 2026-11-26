<?php

declare(strict_types=1);

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

function curl_init(string $url): object { return new \stdClass(); }
function curl_setopt_array(object $handle, array $options): bool
{
    $GLOBALS['pagePayloads'][] = json_decode($options[CURLOPT_POSTFIELDS], true, 512, JSON_THROW_ON_ERROR);
    return true;
}
function curl_exec(object $handle): string { return 'Ok'; }
function curl_getinfo(object $handle, int $option): int { return 200; }

putenv('CHURCHCRM_DISABLE_POSTHOG_TELEMETRY');
putenv('PHP_TEST_DISPOSABLE_DB');
$value = static fn (string $value) => new class($value) {
    public function __construct(private string $value) {}
    public function getValue(): string { return $this->value; }
};
(new \ReflectionProperty(SystemConfig::class, 'configs'))->setValue(null, [
    'sLanguage' => $value('en_US'),
    'sTelemetryLevel' => $value('full'),
    'sSystemID' => $value('install-id'),
    'sInstallDataProfile' => $value('production'),
]);

foreach (['', '/churchcrm'] as $root) {
    SystemURLs::init($root, [], APP_ROOT);
    $GLOBALS['pagePayloads'] = [];
    $_SERVER['PHP_SELF'] = $root . '/v2/index.php';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $app = AppFactory::create();
    $app->setBasePath($root . '/v2');
    $app->get('/people/list', 'unused');
    $app->get('/person/{id:[0-9]+}', 'unused');
    $app->get('/confirm/{token}', 'unused');
    $app->get('/search/{name}[/{page:[0-9]{1,3}}]', 'unused');
    $GLOBALS['app'] = $app;
    foreach (['/people/list', '/person/123?private=value', '/confirm/secret-token', '/search/private-name/2', '/missing'] as $path) {
        $_SERVER['REQUEST_URI'] = $root . '/v2' . $path;
        require APP_ROOT . '/Include/PageTelemetry.php';
    }

    unset($GLOBALS['app']);
    $_SERVER['PHP_SELF'] = $root . '/PersonEditor.php';
    $_SERVER['REQUEST_URI'] = $root . '/PersonEditor.php?PersonID=123';
    require APP_ROOT . '/Include/PageTelemetry.php';

    Assert::same([
        '/v2/people/list',
        '/v2/person/{id:[0-9]+}',
        '/v2/confirm/{token}',
        '/v2/search/{name}[/{page:[0-9]{1,3}}]',
        '/v2/index.php',
        '/PersonEditor.php',
    ], array_column(array_column($GLOBALS['pagePayloads'], 'properties'), 'route'));
}
