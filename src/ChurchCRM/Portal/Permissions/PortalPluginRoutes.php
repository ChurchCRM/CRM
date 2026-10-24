<?php

namespace ChurchCRM\Portal\Permissions;

use Psr\Http\Message\ResponseInterface;
use Slim\Interfaces\RouteCollectorProxyInterface;
use Slim\Interfaces\RouteInterface;

/**
 * What a plugin's `portalRoutesFile` gets instead of the Slim group (member
 * sign-in design §8.5). Every route names the portal capability it needs, so
 * a plugin cannot add a portal route that any signed-in member reaches.
 *
 * `$capability` is a capability key, checked with `RequirePortalCapability::any()`,
 * or a `RequirePortalCapability` for a scoped check such as `onRouteArgument()`.
 */
final class PortalPluginRoutes
{
    /**
     * @param RouteCollectorProxyInterface<ResponseInterface> $group
     */
    public function __construct(private readonly RouteCollectorProxyInterface $group)
    {
    }

    public function get(string $pattern, callable $handler, string|RequirePortalCapability $capability): RouteInterface
    {
        return $this->map('GET', $pattern, $handler, $capability);
    }

    public function post(string $pattern, callable $handler, string|RequirePortalCapability $capability): RouteInterface
    {
        return $this->map('POST', $pattern, $handler, $capability);
    }

    public function put(string $pattern, callable $handler, string|RequirePortalCapability $capability): RouteInterface
    {
        return $this->map('PUT', $pattern, $handler, $capability);
    }

    public function patch(string $pattern, callable $handler, string|RequirePortalCapability $capability): RouteInterface
    {
        return $this->map('PATCH', $pattern, $handler, $capability);
    }

    public function delete(string $pattern, callable $handler, string|RequirePortalCapability $capability): RouteInterface
    {
        return $this->map('DELETE', $pattern, $handler, $capability);
    }

    private function map(string $method, string $pattern, callable $handler, string|RequirePortalCapability $capability): RouteInterface
    {
        $guard = is_string($capability) ? RequirePortalCapability::any($capability) : $capability;

        return $this->group->map([$method], $pattern, $handler)->add($guard);
    }
}
