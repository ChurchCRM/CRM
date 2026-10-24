<?php

namespace ChurchCRM\Portal\Auth;

use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PersonSignin;
use ChurchCRM\model\ChurchCRM\PersonSigninQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Utils\DateTimeUtils;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The shared step (member sign-in design §5): the only code that starts a
 * portal session. Every way in ends here once the person is verified.
 */
final class PortalSessionStarter
{
    /**
     * @param User|null   $actor       the administrator, for ASSISTED (Login as User) only
     * @param string|null $provenEmail for an email method, the address the code or link went to
     *
     * @throws PortalSignInRefusedException when the person may not sign in; the refusal is audited
     */
    public static function start(
        int $personId,
        SignInMethod $method,
        ?User $actor = null,
        ?string $provenEmail = null,
    ): PortalPrincipal {
        if ($method === SignInMethod::ASSISTED && !$actor instanceof User) {
            throw new InvalidArgumentException('Login as User needs the acting administrator');
        }
        $actorUserId = $method === SignInMethod::ASSISTED ? (int) $actor->getId() : null;
        $provenEmail = $method->isEmail() && $provenEmail !== null && trim($provenEmail) !== '' ? trim($provenEmail) : null;

        $person = PersonQuery::create()->findPk($personId);
        $state = PersonSigninQuery::create()->findPk($personId);

        $refusal = PortalSignInRules::refusalReason($person, $state, $method);
        if ($refusal !== null) {
            SignInAudit::record(SignInAuditEvent::REFUSED, $person === null ? null : $personId, $method, $refusal, $actorUserId, $provenEmail);

            throw new PortalSignInRefusedException($refusal);
        }

        if ($method->regeneratesSessionId()) {
            PortalSessionStore::regenerateId();
        }

        $now = new DateTimeImmutable('now', DateTimeUtils::getConfiguredTimezone());
        if ($method->updatesSignInRecord()) {
            $state ??= (new PersonSignin())->setPersonId($personId);
            if ($state->getFirstSignIn() === null) {
                $state->setFirstSignIn($now);
            }
            $state->setLastSignIn($now)->setLastMethod($method->value);
            if ($provenEmail !== null) {
                $state->setProvenEmail($provenEmail)->setProvenAt($now);
            }
            $state->save();
        }

        $principal = new PortalPrincipal(
            $personId,
            $method,
            $now,
            $state instanceof PersonSignin ? (int) $state->getSessionVersion() : 0,
            $actorUserId,
        );
        PortalSessionStore::save($principal);
        SignInAudit::record(SignInAuditEvent::SIGNED_IN, $personId, $method, null, $actorUserId, $provenEmail);

        return $principal;
    }
}
