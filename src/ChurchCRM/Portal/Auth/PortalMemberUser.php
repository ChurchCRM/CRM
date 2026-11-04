<?php

namespace ChurchCRM\Portal\Auth;

use ChurchCRM\model\ChurchCRM\User;
use LogicException;
use Propel\Runtime\Connection\ConnectionInterface;

/**
 * The person of a portal session, in the shape the Volunteer v2 services take.
 *
 * Those services and middlewares are typed on `User`, and a member has no user
 * account (member sign-in design D1). This in-memory stand-in carries only the
 * person id and the self-service shape — Edit Self and no other permission — so
 * every volunteer check answers for a member exactly as it did for a
 * self-service account. Team authority comes from the portal permissions model
 * (`VolunteerAuthorizationService` reads `volunteer.team.manage` for it).
 *
 * It is never written: `save()` and `delete()` throw.
 */
final class PortalMemberUser extends User
{
    public static function forPerson(int $personId): self
    {
        $user = new self();
        $user->setPersonId($personId);
        $user->setEditSelf(true);

        return $user;
    }

    public function save(?ConnectionInterface $con = null): int
    {
        throw new LogicException('A portal member has no user account to save.');
    }

    public function delete(?ConnectionInterface $con = null): void
    {
        throw new LogicException('A portal member has no user account to delete.');
    }
}
