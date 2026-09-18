<?php

namespace ChurchCRM\Volunteer\Service;

use ChurchCRM\model\ChurchCRM\Map\VolunteerMinistryTableMap;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\VolunteerPosition;
use ChurchCRM\model\ChurchCRM\VolunteerQualification;
use ChurchCRM\model\ChurchCRM\VolunteerQualificationQuery;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Volunteer\VolunteerException;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Psr\Log\LoggerInterface;

/**
 * Qualifications — who may fill which position (design §2.7, #9707).
 *
 * Split out of VolunteerMinistryService on 2026-09-18: a qualification is a fact
 * about a PERSON and a POSITION, granted by a coordinator or that position's
 * team leader, and it is what assignment eligibility (§2.11.2 I2), self-signup
 * and the ministry page's matrix all ask about. The ministry service keeps the
 * structure — ministries, teams, positions, the pool Group — and this class
 * keeps the grants. The one place the two meet is D19: granting a
 * qualification also puts the person in the ministry's pool, which is why this
 * service holds a ministry service and asks it for the pool Group.
 *
 * Revocation is deactivation (`vqal_Active = 0`), never deletion, so grant
 * history and every assignment that predates the revocation stay readable.
 *
 * Constructor injection with defaults, not a container (F17): the
 * authorization service memoises scope rows per request, so callers that
 * already hold one hand it down.
 */
class VolunteerQualificationService
{
    private LoggerInterface $logger;

    private VolunteerAuthorizationService $authz;

    private VolunteerMinistryService $ministries;

    public function __construct(?VolunteerAuthorizationService $authz = null, ?VolunteerMinistryService $ministries = null)
    {
        $this->authz = $authz ?? new VolunteerAuthorizationService();
        $this->ministries = $ministries ?? new VolunteerMinistryService($this->authz);
        $this->logger = LoggerUtils::getAppLogger();
    }

    public function getAuthorizationService(): VolunteerAuthorizationService
    {
        return $this->authz;
    }

    /**
     * Grant a qualification, or reactivate the one that is already there.
     *
     * Idempotent by `vqal_person_position_uidx`: calling this twice leaves one
     * row. Use `findQualification()` first when the caller needs to answer 201
     * vs 200.
     *
     * @throws VolunteerException 403 outside scope, 404 for an unknown person
     */
    public function grantQualification(
        int $personId,
        VolunteerPosition $position,
        User $actor,
        ?string $notes = null
    ): VolunteerQualification {
        $this->assertCanManagePosition($actor, (int) $position->getId());

        if (PersonQuery::create()->findPk($personId) === null) {
            throw VolunteerException::notFound(gettext('Person not found'));
        }

        $connection = Propel::getWriteConnection(VolunteerMinistryTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $qualification = $this->writeQualification($personId, $position, $actor, $notes);
            $joinedPool = $this->qualifyIntoPool((int) $position->getMinistryId(), $personId);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();

            throw $e;
        }

        $this->logger->info('Volunteer qualification granted', [
            'qualificationId' => $qualification->getId(),
            'personId' => $personId,
            'positionId' => $position->getId(),
            'addedToPool' => $joinedPool,
            'actor' => $actor->getId(),
        ]);

        return $qualification;
    }

    /**
     * D19: a newly qualified person joins the ministry's pool Group.
     *
     * The caller has already authorized the qualification (`assertCanManagePosition`),
     * and a team leader may grant on their own team's positions without coordinating
     * the ministry — so this is a managed write, decided by the grant, not a second
     * permission question. Returns true when a membership row was actually created.
     */
    private function qualifyIntoPool(int $ministryId, int $personId): bool
    {
        $group = $this->ministries->getPoolGroup($ministryId);
        if ($group === null) {
            return false;
        }

        return $this->ministries->joinPoolGroup($group, $personId);
    }

    /**
     * Revoke a qualification by **deactivating** it (§2.7). The row survives,
     * so the grant history and every assignment that predates the revocation
     * stay readable.
     *
     * D19: this does NOT remove the person from the ministry's pool Group. They may
     * hold other qualifications, they are still one of the ministry's volunteers, and
     * un-rostering somebody as a side effect of ending one qualification is a surprise
     * a coordinator cannot undo without noticing it happened. Removing them from the
     * pool is `removePoolMember()`, a separate, deliberate act.
     *
     * @throws VolunteerException
     */
    public function revokeQualification(VolunteerQualification $qualification, User $actor): VolunteerQualification
    {
        $this->assertCanManagePosition($actor, (int) $qualification->getPositionId());

        $qualification->setActive(false);
        $qualification->save();

        $this->logger->info('Volunteer qualification revoked', [
            'qualificationId' => $qualification->getId(),
            'personId' => $qualification->getPersonId(),
            'positionId' => $qualification->getPositionId(),
            'actor' => $actor->getId(),
        ]);

        return $qualification;
    }

    /** The one row for this pair, active or not; null when it was never granted. */
    public function findQualification(int $personId, int $positionId): ?VolunteerQualification
    {
        return VolunteerQualificationQuery::create()
            ->filterByPersonId($personId)
            ->filterByPositionId($positionId)
            ->findOne();
    }

    /**
     * Who is qualified for a position. `$activeOnly = null` includes revoked
     * rows, which is what makes the grant history visible.
     *
     * @return VolunteerQualification[]
     */
    public function listQualifications(int $positionId, ?bool $activeOnly = null): array
    {
        $query = VolunteerQualificationQuery::create()->filterByPositionId($positionId);

        if ($activeOnly !== null) {
            $query->filterByActive($activeOnly);
        }

        return iterator_to_array($query->orderByGrantedDate()->orderById()->find(), false);
    }

    /**
     * One person's qualifications, optionally narrowed to a set of ministries —
     * which is how the read is scoped for a coordinator (§4.4): the filter goes
     * into the query, not into a loop over hydrated rows.
     *
     * @param int[]|null $ministryIds null means "no narrowing" (manager/admin)
     *
     * @return VolunteerQualification[]
     */
    public function listQualificationsForPerson(int $personId, ?array $ministryIds = null): array
    {
        $query = VolunteerQualificationQuery::create()->filterByPersonId($personId);

        if ($ministryIds !== null) {
            if ($ministryIds === []) {
                return [];
            }
            $query->usePositionQuery()
                    ->filterByMinistryId($ministryIds, Criteria::IN)
                ->endUse();
        }

        return iterator_to_array($query->orderById()->find(), false);
    }

    /**
     * The active-qualification person ids for one position — the eligibility
     * answer #9709 will ask for when it assigns.
     *
     * @return int[]
     */
    public function getQualifiedPersonIds(int $positionId): array
    {
        $ids = [];
        $rows = VolunteerQualificationQuery::create()
            ->filterByPositionId($positionId)
            ->filterByActive(true)
            ->select(['PersonId'])
            ->find();

        foreach ($rows as $personId) {
            $ids[] = (int) $personId;
        }

        return array_values(array_unique($ids));
    }

    /**
     * The whole matrix body in ONE query: person id → position id →
     * qualification id, for every position asked about.
     *
     * §5.4 requires the matrix to render 15–200 people without re-fetching per
     * cell, and this is what makes that true — the screen fetches positions,
     * pool people and this map, then draws. The qualification id is carried
     * (rather than just a boolean) so unticking a box can revoke the exact row
     * without a lookup request per click.
     *
     * @param int[] $positionIds
     *
     * @return array<int, array<int, int>>
     */
    public function getQualificationsByPerson(array $positionIds): array
    {
        if ($positionIds === []) {
            return [];
        }

        $map = [];
        $rows = VolunteerQualificationQuery::create()
            ->filterByPositionId($positionIds, Criteria::IN)
            ->filterByActive(true)
            ->select(['Id', 'PersonId', 'PositionId'])
            ->find();

        foreach ($rows as $row) {
            $personId = (int) $row['PersonId'];
            $map[$personId][(int) $row['PositionId']] = (int) $row['Id'];
        }

        return $map;
    }

    /**
     * Insert or reactivate the single row for (person, position). Shared by the
     * one-at-a-time grant and the cart bulk grant so the two cannot drift on
     * what "re-grant" means.
     */
    private function writeQualification(
        int $personId,
        VolunteerPosition $position,
        User $actor,
        ?string $notes
    ): VolunteerQualification {
        $qualification = $this->findQualification($personId, (int) $position->getId());

        if ($qualification === null) {
            $qualification = new VolunteerQualification();
            $qualification->setPersonId($personId);
            $qualification->setPositionId((int) $position->getId());
        }

        // A re-grant re-stamps who granted it and when: that is the fact a
        // coordinator wants to see, and the row is the only place it lives.
        $qualification->setActive(true);
        // Naive wall-clock in sTimeZone, like every other V2 timestamp (§2.0).
        $qualification->setGrantedDate(DateTimeUtils::getNowDateTime());
        $qualification->setGrantedByPersonId((int) $actor->getId());

        if ($notes !== null) {
            $qualification->setNotes($this->normalizeDescription($notes));
        }

        $qualification->save();

        return $qualification;
    }

    /** @throws VolunteerException */
    private function assertCanManagePosition(User $actor, int $positionId): void
    {
        if (!$this->authz->canManagePosition($actor, $positionId)) {
            throw VolunteerException::forbidden(gettext('Not authorized for this position'));
        }
    }

    /** An absent or blank note is stored as NULL, never as ''. */
    private function normalizeDescription(?string $description): ?string
    {
        if ($description === null) {
            return null;
        }

        $description = trim($description);

        return $description === '' ? null : $description;
    }
}
