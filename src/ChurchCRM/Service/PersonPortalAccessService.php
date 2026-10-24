<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonPasskey;
use ChurchCRM\model\ChurchCRM\PersonPasskeyQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Portal\Auth\SignInAudit;
use ChurchCRM\Portal\Auth\SignInAuditEvent;
use ChurchCRM\Portal\Permissions\EffectiveGrant;
use ChurchCRM\Portal\Permissions\PortalAuthorizationService;
use ChurchCRM\Portal\Permissions\PortalCapabilityRegistry;
use ChurchCRM\Utils\DateTimeUtils;
use DateTimeInterface;
use DomainException;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * The rest of the person page's "Portal sign-in" card (member sign-in design
 * §11.3, §8.6): the person's remembered devices and passkeys, which an
 * administrator can remove one at a time, and their portal permissions with
 * where each comes from.
 */
final class PersonPortalAccessService
{
    /**
     * For administrators only (§11.3): staff with Edit Records see the counts
     * on the card, not these lists.
     *
     * @return array{devices: list<array<string, mixed>>, passkeys: list<array<string, mixed>>, permissions: list<array<string, mixed>>, notInEffect: list<array<string, mixed>>}
     */
    public function forPerson(Person $person): array
    {
        $personId = (int) $person->getId();
        $stored = (new PortalGrantService())->storedGrantsFor($personId);
        $reasons = array_column($stored, 'reason', 'grantId');

        $permissions = $this->permissions($personId);
        foreach ($permissions as &$permission) {
            $permission['reason'] = $permission['grantId'] === null ? null : ($reasons[$permission['grantId']] ?? null);
        }
        unset($permission);

        return [
            'devices' => $this->devices($personId),
            'passkeys' => $this->passkeys($personId),
            'permissions' => $permissions,
            'notInEffect' => array_values(array_filter($stored, static fn (array $grant): bool => $grant['ended'] || !$grant['known'])),
        ];
    }

    /**
     * Signs the device out, the way the member does on their Sign-in and
     * devices page, so a session kept on it ends on its next request.
     *
     * @throws DomainException 404 when the person has no such device still signed in
     */
    public function removeDevice(Person $person, User $actor, int $deviceId): void
    {
        if (!(new RememberedDeviceService())->signOutDevice((int) $person->getId(), $deviceId, $actor)) {
            throw new DomainException(gettext('This person has no such remembered device.'), 404);
        }
    }

    /**
     * @throws DomainException 404 when the person has no such passkey
     */
    public function removePasskey(Person $person, User $actor, int $passkeyId): void
    {
        $personId = (int) $person->getId();
        $passkey = PersonPasskeyQuery::create()->filterByPersonId($personId)->findPk($passkeyId);
        if (!$passkey instanceof PersonPasskey) {
            throw new DomainException(gettext('This person has no such passkey.'), 404);
        }
        $passkey->delete();

        SignInAudit::record(
            SignInAuditEvent::PASSKEY_REMOVED,
            $personId,
            actorUserId: (int) $actor->getId(),
            detail: sprintf('passkey=%d', $passkeyId),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function devices(int $personId): array
    {
        $devices = RememberedDeviceService::liveDevicesQuery($personId)
            ->orderByLastUsedAt(Criteria::DESC)
            ->orderByCreatedAt(Criteria::DESC)
            ->find();

        $rows = [];
        foreach ($devices as $device) {
            $rows[] = [
                'id' => (int) $device->getId(),
                'label' => trim((string) $device->getLabel()) !== '' ? (string) $device->getLabel() : gettext('Unnamed device'),
                'createdAt' => self::display($device->getCreatedAt()),
                'lastUsedAt' => self::display($device->getLastUsedAt()),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function passkeys(int $personId): array
    {
        $rows = [];
        foreach (PersonPasskeyQuery::create()->filterByPersonId($personId)->orderByCreatedAt(Criteria::DESC)->find() as $passkey) {
            $rows[] = [
                'id' => (int) $passkey->getId(),
                'label' => trim((string) $passkey->getLabel()) !== '' ? (string) $passkey->getLabel() : gettext('Unnamed passkey'),
                'createdAt' => self::display($passkey->getCreatedAt()),
                'lastUsedAt' => self::display($passkey->getLastUsedAt()),
            ];
        }

        return $rows;
    }

    /**
     * The person's grants now, every source, with the capability's label, the
     * scope's name and whether the capability is switched on for the church.
     *
     * @return list<array<string, mixed>>
     */
    private function permissions(int $personId): array
    {
        $grants = (new PortalAuthorizationService())->effectiveGrantsForPerson($personId);
        $scopeNames = PortalGrantService::scopeNames(array_map(static fn (EffectiveGrant $grant) => $grant->scope, $grants));

        $rows = [];
        foreach ($grants as $grant) {
            $definition = PortalCapabilityRegistry::get($grant->capability);
            $status = $definition === null ? PortalAuthorizationService::STATUS_ON : PortalAuthorizationService::capabilityStatus($definition);
            $rows[] = $grant->toArray() + [
                'capabilityLabel' => $definition?->label ?? $grant->capability,
                'scopeName' => $scopeNames[$grant->scope->key()] ?? null,
                'status' => $status,
                'statusLabel' => PortalGrantService::statusLabel($status),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => [$a['capabilityLabel'], (string) $a['scopeName']] <=> [$b['capabilityLabel'], (string) $b['scopeName']]);

        return $rows;
    }

    private static function display(mixed $value): ?string
    {
        return $value instanceof DateTimeInterface ? DateTimeUtils::formatDate($value, true) : null;
    }
}
