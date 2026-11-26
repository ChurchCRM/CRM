<?php

declare(strict_types=1);

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Slim\Middleware\PageRouteMiddleware;
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
    $app = AppFactory::create();
    $app->setBasePath($root . '/v2');
    $app->add(new PageRouteMiddleware($app->getBasePath()));
    $app->addRoutingMiddleware();
    $render = static function ($request, $response) {
        require APP_ROOT . '/Include/PageTelemetry.php';
        return $response;
    };
    $app->get('/people/list', $render);
    $app->get('/person/{id:[0-9]+}', $render);
    $app->get('/search/{name:[a-z-]+}[/{page:[0-9]{1,3}}]', $render);
    $app->get('/failure', static function () {
        throw new \RuntimeException('Render failed');
    });
    foreach (['/people/list', '/person/123?private=value', '/search/private-name?email=secret', '/search/private-name/2'] as $path) {
        $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', $root . '/v2' . $path));
        Assert::same(200, $response->getStatusCode());
        Assert::null(PageRouteMiddleware::getRoutePattern());
    }
    Assert::exception(
        static fn () => $app->handle((new ServerRequestFactory())->createServerRequest('GET', $root . '/v2/failure')),
        \RuntimeException::class,
        'Render failed',
    );
    Assert::null(PageRouteMiddleware::getRoutePattern());

    $_SERVER['PHP_SELF'] = $root . '/PersonEditor.php?PersonID=123';
    require APP_ROOT . '/Include/PageTelemetry.php';
    Assert::same(['page_view', 'page_view', 'page_view', 'page_view', 'page_view'], array_column($GLOBALS['pagePayloads'], 'event'));
    Assert::same([
        '/v2/people/list', '/v2/person/{id}', '/v2/search/{name}/{page}', '/v2/search/{name}/{page}', '/PersonEditor.php',
    ], array_column(array_column($GLOBALS['pagePayloads'], 'properties'), 'route'));
}
