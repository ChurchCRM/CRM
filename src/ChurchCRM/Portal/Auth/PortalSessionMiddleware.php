<?php

namespace ChurchCRM\Portal\Auth;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PersonSignin;
use ChurchCRM\model\ChurchCRM\PersonSigninQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Service\EmailSignInService;
use ChurchCRM\Service\RememberedDeviceService;
use ChurchCRM\Slim\Middleware\BrowserRequestTrait;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\RedirectUtils;
use DateTimeImmutable;
use DateTimeInterface;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpForbiddenException;
use Throwable;

/**
 * The portal session gate for `/portal` and `/api/portal` (member sign-in
 * design §5). On every request it:
 *
 *  - refuses API keys: the portal acts only for the session's own person;
 *  - reloads the person and their sign-in row, and ends the portal session when
 *    the rules no longer hold, the session version changed, or it sat idle for
 *    `iPortalIdleMinutes`;
 *  - turns a signed-in staff session into a STAFF principal for its own person
 *    through the shared step (§6.4);
 *  - otherwise starts a DEVICE session from a remembered device's cookie, and
 *    replaces its token (§6.2);
 *  - records the activity, except during Login as User.
 *
 * A STAFF principal outlives its staff session's own timeout: from then on it
 * is a portal session like any other, and staff routes refuse it.
 */
final class PortalSessionMiddleware implements MiddlewareInterface
{
    use BrowserRequestTrait;

    public const PRINCIPAL_ATTRIBUTE = 'portalPrincipal';

    private const ACTIVITY_WRITE_SECONDS = 60;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        PortalContext::reset();

        if ($request->getHeaderLine('x-api-key') !== '') {
            throw new HttpForbiddenException(
                $request,
                gettext('The Member Portal is only available to a signed-in person, not to API clients.')
            );
        }

        $staffUser = PortalContext::activeStaffUser();
        [$principal, $person, $state] = $this->currentSession($staffUser);

        if ($staffUser instanceof User && !$principal instanceof PortalPrincipal) {
            try {
                $principal = PortalSessionStarter::start((int) $staffUser->getPersonId(), SignInMethod::STAFF);
            } catch (PortalSignInRefusedException) {
                PortalContext::set(null, null, null);

                throw new HttpForbiddenException($request, gettext('You cannot open the Member Portal. Please contact the church office.'));
            }
            $person = PersonQuery::create()->findPk($principal->personId);
            $state = PersonSigninQuery::create()->findPk($principal->personId);
        }

        $deviceToken = null;
        if (!$principal instanceof PortalPrincipal && !$staffUser instanceof User && RememberedDeviceCookie::isPresent($request) && EmailSignInService::isEnabled()) {
            $deviceToken = (new RememberedDeviceService())->resume(RememberedDeviceCookie::read($request));
            if (!$deviceToken instanceof RememberedDeviceToken) {
                PortalContext::set(null, null, null);

                return RememberedDeviceCookie::without($this->signedOut($request));
            }
            $principal = PortalSessionStore::load();
            $person = PersonQuery::create()->findPk($principal->personId);
            $state = PersonSigninQuery::create()->findPk($principal->personId);
        }

        if (!$principal instanceof PortalPrincipal) {
            PortalContext::set(null, null, null);

            return $this->signedOut($request);
        }

        PortalSessionStore::touch();
        $this->recordActivity($principal, $state);
        PortalContext::set($principal, $person, $staffUser);

        $response = $handler->handle($request->withAttribute(self::PRINCIPAL_ATTRIBUTE, $principal));

        return $deviceToken instanceof RememberedDeviceToken ? RememberedDeviceCookie::with($response, $deviceToken) : $response;
    }

    /**
     * The stored principal with its person and sign-in row, or nulls after
     * ending a session that may not continue.
     *
     * @return array{0: PortalPrincipal|null, 1: Person|null, 2: PersonSignin|null}
     */
    private function currentSession(?User $staffUser): array
    {
        $principal = PortalSessionStore::load();
        if (!$principal instanceof PortalPrincipal) {
            return [null, null, null];
        }

        $person = PersonQuery::create()->findPk($principal->personId);
        $state = PersonSigninQuery::create()->findPk($principal->personId);

        $reason = PortalSignInRules::refusalReason($person, $state, $principal->method);
        if ($reason === null && $principal->sessionVersion !== ($state instanceof PersonSignin ? (int) $state->getSessionVersion() : 0)) {
            $reason = SignInReason::SESSION_VERSION;
        }
        if ($reason === null && $this->isIdle()) {
            $reason = SignInReason::IDLE;
        }
        if ($reason === null && $staffUser instanceof User && !self::belongsToStaffSession($principal, $staffUser)) {
            $reason = SignInReason::REPLACED;
        }
        $deviceId = PortalSessionStore::deviceId();
        if ($reason === null && $deviceId !== null && RememberedDeviceService::isSignedOut($deviceId)) {
            $reason = SignInReason::DEVICE_REMOVED;
        }

        if ($reason !== null) {
            PortalSessionStore::clear();
            SignInAudit::record(
                SignInAuditEvent::SESSION_ENDED,
                $person instanceof Person ? $principal->personId : null,
                $principal->method,
                $reason,
                $principal->actorUserId,
            );

            return [null, null, null];
        }

        return [$principal, $person, $state];
    }

    private function isIdle(): bool
    {
        $lastActivity = PortalSessionStore::lastActivity();
        if ($lastActivity === null) {
            return true;
        }
        $idleMinutes = SystemConfig::getIntValue('iPortalIdleMinutes');

        return $idleMinutes > 0 && time() - $lastActivity > $idleMinutes * 60;
    }

    /**
     * A staff session on this browser owns the portal session only when it is
     * that person's STAFF principal, or that administrator's Login as User.
     * Anything else was left by somebody else and is replaced.
     */
    private static function belongsToStaffSession(PortalPrincipal $principal, User $staffUser): bool
    {
        return match ($principal->method) {
            SignInMethod::STAFF => $principal->personId === (int) $staffUser->getPersonId(),
            SignInMethod::ASSISTED => $principal->actorUserId === (int) $staffUser->getId(),
            default => false,
        };
    }

    private function recordActivity(PortalPrincipal $principal, ?PersonSignin $state): void
    {
        if (!$principal->method->updatesSignInRecord()) {
            return;
        }

        try {
            $now = new DateTimeImmutable('now', DateTimeUtils::getConfiguredTimezone());
            $last = $state?->getLastActivity();
            if (!$last instanceof DateTimeInterface || $now->getTimestamp() - $last->getTimestamp() >= self::ACTIVITY_WRITE_SECONDS) {
                $state ??= (new PersonSignin())->setPersonId($principal->personId);
                $state->setLastActivity($now)->save();
                $deviceId = PortalSessionStore::deviceId();
                if ($deviceId !== null) {
                    RememberedDeviceService::recordUse($deviceId, $now);
                }
            }
        } catch (Throwable $e) {
            LoggerUtils::getAppLogger()->warning('Could not record Member Portal activity', [
                'personId' => $principal->personId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function signedOut(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->isBrowserRequest($request)) {
            $response = new Response();
            $response->getBody()->write(json_encode(SlimUtils::buildErrorPayload(gettext('No logged in user'), 401)));

            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $uri = $request->getUri();
        $safePath = RedirectUtils::stripAndValidatePath($uri->getQuery() !== '' ? $uri->getPath() . '?' . $uri->getQuery() : $uri->getPath());
        if ($safePath !== '') {
            $_SESSION['location'] = $safePath;
        }

        return (new Response())->withStatus(302)->withHeader('Location', AuthenticationManager::getPortalLoginURL());
    }
}
