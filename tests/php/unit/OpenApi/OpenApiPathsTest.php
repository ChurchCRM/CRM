<?php

declare(strict_types=1);

use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';
require dirname(__DIR__, 4) . '/docs/openapi/OpenApiPaths.php';

Assert::same('/api/person/{id}', OpenApiPaths::sitePath('/person/{id}', '/api'));
Assert::same('/admin/api/user/{userId}/password/reset', OpenApiPaths::sitePath('/api/user/{userId}/password/reset', '/admin'));
Assert::same('/admin/api/import/csv/families', OpenApiPaths::sitePath('/admin/api/import/csv/families', '/admin'));
Assert::same('/finance/api/funds', OpenApiPaths::sitePath('/finance/api/funds', '/finance'));
Assert::same('/api/person/{}', OpenApiPaths::pattern('/api/person/{personId:[0-9]+}'));
Assert::same('/api/public/data/countries/{}/states', OpenApiPaths::pattern('/api/public/data/countries/{countryCode:[A-Za-z]{2}}/states'));

$source = <<<'PHP'
<?php
$app->group('/api/user/{userId:[0-9]+}', function (RouteCollectorProxy $group): void {
    $group->post('/password/reset', function (): void {
    });
    $group->delete('/', function (): void {
    });
});
$app->get('/cart/emails', function (): void {
});
PHP;

$routes = OpenApiPaths::routesFromSource($source, '/admin');
Assert::true(isset($routes['POST /admin/api/user/{}/password/reset']));
Assert::true(isset($routes['DELETE /admin/api/user/{}/']));
Assert::true(isset($routes['GET /admin/cart/emails']));
Assert::false(isset($routes['GET /admin/api/user/{}/password/reset']));
