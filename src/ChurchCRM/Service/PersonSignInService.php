<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Map\PersonSigninTableMap;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonDeviceQuery;
use ChurchCRM\model\ChurchCRM\PersonPasskeyQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PersonSignin;
use ChurchCRM\model\ChurchCRM\PersonSigninQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Portal\Auth\MemberSignInRule;
use ChurchCRM\Portal\Auth\SignInAudit;
use ChurchCRM\Portal\Auth\SignInAuditEvent;
use ChurchCRM\Portal\Auth\SignInEmailPolicy;
use ChurchCRM\Portal\Auth\SignInReason;
use ChurchCRM\Utils\DateTimeUtils;
use DateTimeImmutable;
use DateTimeInterface;
use DomainException;
use InvalidArgumentException;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Throwable;

/**
 * Disable sign-in and Sign out everywhere for one person (member sign-in design
 * A13, §11.3). Disable sign-in also blocks the person's staff account, because
 * it is one of the blocks for everyone (§4).
 *
 * The administrator's reason is kept on the person's sign-in row only, never in
 * the audit, which holds no personal content (§14).
 */
final class PersonSignInService
{
    public const REASON_MAX_LENGTH = 255;

    /**
     * @return array{devicesRemoved: int, passkeysRemoved: int}
     *
     * @throws InvalidArgumentException when the reason is missing or too long
     * @throws DomainException          403 for yourself or the last administrator who can sign in, 409 when already disabled
     */
    public function disable(Person $person, User $actor, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException(gettext('Say why sign-in is being disabled.'));
        }
        if (mb_strlen($reason) > self::REASON_MAX_LENGTH) {
            throw new InvalidArgumentException(sprintf(gettext('The reason can be at most %d characters long.'), self::REASON_MAX_LENGTH));
        }

        $personId = (int) $person->getId();
        if ($personId === (int) $actor->getPersonId()) {
            throw new DomainException(gettext("You can't disable your own sign-in."), 403);
        }
        if ((new UserService())->isLastSignInCapableAdmin($personId)) {
            throw new DomainException(gettext("Can't disable sign-in for the only administrator who can sign in."), 403);
        }

        $state = $this->stateFor($personId);
        if ($state->getSignInDisabled()) {
            throw new DomainException(gettext('Sign-in is already disabled for this person.'), 409);
        }

        $connection = Propel::getWriteConnection(PersonSigninTableMap::DATABASE_NAME);
        $connection->beginTransaction();
        try {
            $state->setSignInDisabled(true)
                ->setDisabledByUserId((int) $actor->getPersonId())
                ->setDisabledAt($this->now())
                ->setDisabledReason($reason)
                ->endAllSessions()
                ->save($connection);
            $devicesRemoved = PersonDeviceQuery::create()->filterByPersonId($personId)->delete($connection);
            $passkeysRemoved = PersonPasskeyQuery::create()->filterByPersonId($personId)->delete($connection);
            $connection->commit();
        } catch (Throwable $e) {
            $connection->rollBack();

            throw $e;
        }

        (new EmailSignInService())->cancelOpenRequests($personId, SignInReason::SIGN_IN_DISABLED, (int) $actor->getPersonId());
        SignInAudit::record(
            SignInAuditEvent::SIGN_IN_DISABLED,
            $personId,
            actorUserId: (int) $actor->getPersonId(),
            detail: sprintf('devices=%d passkeys=%d', $devicesRemoved, $passkeysRemoved),
        );

        return ['devicesRemoved' => $devicesRemoved, 'passkeysRemoved' => $passkeysRemoved];
    }

    /**
     * @throws DomainException 409 when sign-in is not disabled
     */
    public function enable(Person $person, User $actor): void
    {
        $personId = (int) $person->getId();
        $state = PersonSigninQuery::create()->findPk($personId);
        if (!$state instanceof PersonSignin || !$state->getSignInDisabled()) {
            throw new DomainException(gettext('Sign-in is not disabled for this person.'), 409);
        }

        $state->setSignInDisabled(false)
            ->setDisabledByUserId(null)
            ->setDisabledAt(null)
            ->setDisabledReason(null)
            ->save();

        SignInAudit::record(SignInAuditEvent::SIGN_IN_ENABLED, $personId, actorUserId: (int) $actor->getPersonId());
    }

    /**
     * Ends every portal session of the person on its next request, revokes
     * their remembered devices and cancels the sign-in codes and links already
     * emailed. Passkeys stay: the member keeps a way back in.
     * `$actor` is null when the member does it from the Sign-in and devices page.
     *
     * @return int how many devices were revoked
     */
    public function signOutEverywhere(Person $person, ?User $actor): int
    {
        $personId = (int) $person->getId();
        $connection = Propel::getWriteConnection(PersonSigninTableMap::DATABASE_NAME);
        $connection->beginTransaction();
        try {
            $this->stateFor($personId)->endAllSessions()->save($connection);
            $devicesRevoked = PersonDeviceQuery::create()
                ->filterByPersonId($personId)
                ->filterByRevokedAt(null, Criteria::ISNULL)
                ->update(RememberedDeviceService::revokedValues($this->now()), $connection);
            $connection->commit();
        } catch (Throwable $e) {
            $connection->rollBack();

            throw $e;
        }

        $actorUserId = $actor === null ? null : (int) $actor->getPersonId();
        (new EmailSignInService())->cancelOpenRequests($personId, SignInReason::SIGNED_OUT_EVERYWHERE, $actorUserId);
        SignInAudit::record(
            SignInAuditEvent::SESSION_ENDED,
            $personId,
            reason: SignInReason::SIGNED_OUT_EVERYWHERE,
            actorUserId: $actorUserId,
            detail: sprintf('devices=%d', $devicesRevoked),
        );

        return $devicesRevoked;
    }

    /**
     * What the person page's Portal sign-in card shows (§11.3). The
     * administrator's reason for Disable sign-in is included only for
     * administrators. Whether the viewer may confirm an unconfirmed email
     * follows §9: see SignInEmailPolicy::confirmRefusal().
     *
     * @return array<string, mixed>
     */
    public function statusFor(Person $person, bool $forAdministrator): array
    {
        $personId = (int) $person->getId();
        $state = PersonSigninQuery::create()->findPk($personId);
        $reason = MemberSignInRule::fromSettings()->statusFor($person, $state);
        $disabled = $state instanceof PersonSignin && $state->getSignInDisabled();
        $disabledBy = $disabled && $state->getDisabledByUserId() !== null
            ? PersonQuery::create()->findPk((int) $state->getDisabledByUserId())
            : null;
        $unconfirmedEmail = trim((string) $state?->getUnconfirmedEmail());
        $confirmRefusal = $unconfirmedEmail === ''
            ? null
            : (new SignInEmailPolicy())->confirmRefusal($person, $state, $forAdministrator);

        return [
            'personId' => $personId,
            'memberSignInEnabled' => SystemConfig::getBooleanValue('bMemberSignIn'),
            'maySignIn' => $reason === null,
            'reason' => $reason?->value,
            'reasonLabel' => $reason?->label(),
            'signInDisabled' => $disabled,
            'disabledAt' => $disabled ? self::formatTime($state->getDisabledAt()) : null,
            'disabledBy' => $disabledBy instanceof Person ? ['id' => (int) $disabledBy->getId(), 'name' => $disabledBy->getFullName()] : null,
            'disabledReason' => $disabled && $forAdministrator ? $state->getDisabledReason() : null,
            'lastSignIn' => $state instanceof PersonSignin ? self::formatTime($state->getLastSignIn()) : null,
            'unconfirmedEmail' => $unconfirmedEmail === '' ? null : $unconfirmedEmail,
            'unconfirmedSource' => $unconfirmedEmail === '' ? null : $state->getUnconfirmedSource(),
            'unconfirmedSourceLabel' => $unconfirmedEmail === '' ? null : SignInEmailPolicy::sourceLabel($state->getUnconfirmedSource()),
            'unconfirmedAt' => $unconfirmedEmail === '' ? null : self::formatTime($state->getUnconfirmedAt()),
            'canConfirmEmail' => $unconfirmedEmail !== '' && $confirmRefusal === null,
            'confirmEmailRefusal' => $confirmRefusal,
            'devices' => RememberedDeviceService::liveDevicesQuery($personId)->count(),
            'passkeys' => PersonPasskeyQuery::create()->filterByPersonId($personId)->count(),
        ];
    }

    private function stateFor(int $personId): PersonSignin
    {
        return PersonSigninQuery::create()->findPk($personId) ?? (new PersonSignin())->setPersonId($personId);
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', DateTimeUtils::getConfiguredTimezone());
    }

    private static function formatTime(mixed $value): ?string
    {
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d H:i:s') : null;
    }
}
