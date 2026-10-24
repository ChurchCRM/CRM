<?php

namespace ChurchCRM\Portal\Permissions;

use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Portal\Auth\PortalContext;
use ChurchCRM\Portal\Auth\PortalPrincipal;
use ChurchCRM\Slim\SlimUtils;
use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpNotFoundException;

/**
 * Route middleware for portal pages and `/api/portal` (member sign-in design
 * §8.2). It runs inside `PortalSessionMiddleware`, so the principal is known.
 *
 *  - A capability that is unknown, or whose owner or feature switch is off,
 *    answers 404: for this church the page does not exist.
 *  - A scope read from the route that is not the person's answers 404 as well,
 *    whether or not it exists, so ids cannot be probed.
 *  - A capability the person does not hold anywhere answers 403.
 *
 * The scope that passed is put on the request as `portalScope`.
 */
final class RequirePortalCapability implements MiddlewareInterface
{
    public const SCOPE_ATTRIBUTE = 'portalScope';

    /**
     * @param Closure(ServerRequestInterface, PortalPrincipal): (PortalScope|false|null)|null $resolveScope
     *        null: held on any scope; false from the closure: no scope applies, let the handler answer
     */
    private function __construct(
        private readonly string $capability,
        private readonly ?Closure $resolveScope,
        private readonly ?string $refusal,
    ) {
    }

    /** Held on at least one scope, or held at all for an unscoped capability. */
    public static function any(string $capability, ?string $refusal = null): self
    {
        return new self($capability, null, $refusal);
    }

    /** Held on the scope a route argument names, such as `{teamId}`. */
    public static function onRouteArgument(string $capability, string $argument): self
    {
        return new self(
            $capability,
            static function (ServerRequestInterface $request) use ($capability, $argument): PortalScope {
                $type = PortalCapabilityRegistry::get($capability)?->scopeType ?? PortalScope::NONE;

                return new PortalScope($type, (int) SlimUtils::getRouteArgument($request, $argument));
            },
            null,
        );
    }

    /**
     * Held on the person's own family. A person with no family passes without a
     * scope: the handler shows that they are not in a family yet.
     */
    public static function onOwnFamily(string $capability, ?string $refusal = null): self
    {
        return new self(
            $capability,
            static function (): PortalScope|false {
                $person = PortalContext::person();
                $familyId = $person instanceof Person ? (int) $person->getFamId() : 0;

                return $familyId > 0 ? PortalScope::family($familyId) : false;
            },
            $refusal,
        );
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $principal = PortalContext::principal();
        if (!$principal instanceof PortalPrincipal) {
            throw new HttpForbiddenException($request, gettext('No logged in user'));
        }

        $authz = new PortalAuthorizationService();

        if ($this->resolveScope === null) {
            $scopes = $authz->scopesFor($principal, $this->capability);
            if ($scopes === []) {
                $this->refuse($request, $authz->decide($principal, $this->capability, $this->anyScope()), false);
            }

            return $handler->handle($request->withAttribute(self::SCOPE_ATTRIBUTE, $scopes[0]));
        }

        $scope = ($this->resolveScope)($request, $principal);
        if ($scope === false) {
            return $handler->handle($request);
        }

        $decision = $authz->decide($principal, $this->capability, $scope);
        if (!$decision->allowed) {
            $this->refuse($request, $decision, $this->refusal === null);
        }

        return $handler->handle($request->withAttribute(self::SCOPE_ATTRIBUTE, $scope));
    }

    /** A scope of the right type, so decide() reports why an unscoped check fails. */
    private function anyScope(): ?PortalScope
    {
        $type = PortalCapabilityRegistry::get($this->capability)?->scopeType;

        return $type === null || $type === PortalScope::NONE ? null : new PortalScope($type, 0);
    }

    /**
     * @throws HttpNotFoundException|HttpForbiddenException
     */
    private function refuse(ServerRequestInterface $request, Decision $decision, bool $hideScope): never
    {
        if (!$decision->capabilityIsLive() || $hideScope) {
            throw new HttpNotFoundException($request);
        }

        throw new HttpForbiddenException($request, $this->refusal ?? gettext('This part of the Member Portal is not available to you.'));
    }
}
