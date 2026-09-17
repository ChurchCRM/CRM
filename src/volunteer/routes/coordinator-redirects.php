<?php

/**
 * The coordinator area's retired /volunteer/* URLs (product-owner review,
 * 2026-09-17): the module now lives at /ministries, and these are permanent
 * redirects so bookmarks, the legacy editor's old target and links in already
 * sent emails keep working. Behind the same rollout gate as the module they
 * point at, so a caller who may not reach the area is turned away here too.
 */

use ChurchCRM\dto\SystemURLs;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('', function (RouteCollectorProxy $group): void {
    $redirectTo = static function (callable $target): callable {
        return static function (Request $request, Response $response, array $args) use ($target): Response {
            return $response
                ->withStatus(302)
                ->withHeader('Location', SystemURLs::getRootPath() . $target($args));
        };
    };

    $group->get('/', $redirectTo(static fn (): string => '/ministries/dashboard'));
    $group->get('/dashboard', $redirectTo(static fn (): string => '/ministries/dashboard'));
    $group->get('/ministries', $redirectTo(static fn (): string => '/ministries/dashboard'));
    $group->get('/ministries/{ministryId:[0-9]+}', $redirectTo(static fn (array $args): string => '/ministries/' . (int) $args['ministryId']));
    $group->get('/occurrences/{occurrenceId:[0-9]+}', $redirectTo(static fn (array $args): string => '/ministries/occurrences/' . (int) $args['occurrenceId']));
});
