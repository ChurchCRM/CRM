<?php

namespace ChurchCRM\Portal;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrence;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrenceQuery;
use ChurchCRM\model\ChurchCRM\VolunteerPositionQuery;
use ChurchCRM\model\ChurchCRM\VolunteerScheduleQuery;
use ChurchCRM\model\ChurchCRM\VolunteerTeam;
use ChurchCRM\model\ChurchCRM\VolunteerTeamQuery;
use ChurchCRM\Service\VolunteerAuthorizationService;
use ChurchCRM\Utils\DateTimeUtils;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * The view-model behind the Member Portal's My Teams pages (MP7, #9868).
 *
 * `/portal/teams` is server-rendered, not a bundle against an API: it is a short
 * list a team leader reads and clicks through, and every fact on it — the team's
 * name, its ministry, how many positions it has and when it next meets — is a
 * plain read the route can do itself. The TEAM page is the one that needs a
 * bundle, and it talks to `/api/ministries/*` like the admin page does.
 *
 * Read scoping happens in the QUERY (volunteer design §4.4): the team ids come
 * from `VolunteerAuthorizationService::getManagedTeamIds()` and are handed to the
 * filter, and an empty allow-list short-circuits rather than being trusted to a
 * `WHERE id IN ()`. Nothing here is sieved in PHP after hydration.
 *
 * Counts and next dates are resolved in THREE queries for the whole list, not one
 * per team: a ministry map, a position count grouped by team, and one pass over
 * the upcoming occurrences of every schedule those teams own.
 */
class PortalTeams
{
    /**
     * The teams this login may run, as the index page renders them.
     *
     * Ordered by ministry then team name, which is how a leader of several thinks
     * of them. `nextOccurrence` is null when nothing is generated ahead.
     *
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     ministryId: int,
     *     ministryName: string,
     *     active: bool,
     *     positionCount: int,
     *     nextOccurrence: array{id: int, date: string}|null,
     *     url: string
     * }>
     */
    public static function listForUser(User $user): array
    {
        $authz = new VolunteerAuthorizationService();
        $teamIds = $authz->getManagedTeamIds($user);
        if ($teamIds === []) {
            return [];
        }

        $teams = iterator_to_array(
            VolunteerTeamQuery::create()
                ->filterById($teamIds, Criteria::IN)
                ->orderByName()
                ->find(),
            false
        );
        if ($teams === []) {
            return [];
        }

        $ministryNames = self::ministryNames($teams);
        $positionCounts = self::positionCounts($teamIds);
        $nextOccurrences = self::nextOccurrences($teamIds);
        $rootPath = SystemURLs::getRootPath();

        $rows = [];
        foreach ($teams as $team) {
            $teamId = (int) $team->getId();
            $ministryId = (int) $team->getMinistryId();
            $rows[] = [
                'id' => $teamId,
                'name' => (string) $team->getName(),
                'ministryId' => $ministryId,
                'ministryName' => $ministryNames[$ministryId] ?? '',
                'active' => (bool) $team->getActive(),
                'positionCount' => $positionCounts[$teamId] ?? 0,
                'nextOccurrence' => $nextOccurrences[$teamId] ?? null,
                'url' => $rootPath . '/portal/teams/' . $teamId,
            ];
        }

        // Ministry first, then team — the query already ordered by team name, so a
        // stable sort on the ministry name is all that is left to do.
        usort(
            $rows,
            static fn (array $a, array $b): int => [$a['ministryName'], $a['name']] <=> [$b['ministryName'], $b['name']]
        );

        return $rows;
    }

    /**
     * The one team a team page is about, or null when this login may not run it.
     *
     * Returning null rather than throwing keeps the route free to answer 404 for a
     * team that does not exist and 403 for one that does — and the route is the
     * only thing that knows which of those the member should be told.
     */
    public static function findManagedTeam(User $user, int $teamId): ?VolunteerTeam
    {
        $team = VolunteerTeamQuery::create()->findPk($teamId);
        if ($team === null) {
            return null;
        }

        return (new VolunteerAuthorizationService())->canManageTeam($user, $teamId) ? $team : null;
    }

    /**
     * Ministry id → name for the ministries the given teams belong to. One query.
     *
     * @param VolunteerTeam[] $teams
     *
     * @return array<int, string>
     */
    private static function ministryNames(array $teams): array
    {
        $ministryIds = [];
        foreach ($teams as $team) {
            $ministryIds[(int) $team->getMinistryId()] = true;
        }
        if ($ministryIds === []) {
            return [];
        }

        $names = [];
        $rows = VolunteerMinistryQuery::create()
            ->filterById(array_keys($ministryIds), Criteria::IN)
            ->select(['Id', 'Name'])
            ->find()
            ->toArray();
        foreach ($rows as $row) {
            $names[(int) $row['Id']] = (string) $row['Name'];
        }

        return $names;
    }

    /**
     * Team id → how many positions it owns. One query; teams with none are absent.
     *
     * Every position, active or not: the number answers "how big is this team's
     * roster of jobs", and a deactivated position is still one of them.
     *
     * @param int[] $teamIds
     *
     * @return array<int, int>
     */
    private static function positionCounts(array $teamIds): array
    {
        $counts = [];
        $rows = VolunteerPositionQuery::create()
            ->filterByTeamId($teamIds, Criteria::IN)
            ->select(['TeamId'])
            ->find()
            ->toArray();
        foreach ($rows as $teamId) {
            $key = (int) $teamId;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Team id → its soonest scheduled occurrence from today on. Two queries.
     *
     * The date is the occurrence's own `vocc_occurrence_date`, which every
     * occurrence carries whether or not it is linked to an event — the linked
     * ones resolve their TIMES from the event (D4), but the date is stored and is
     * what "when does this team next meet" is asking. A cancelled occurrence is
     * not an answer.
     *
     * @param int[] $teamIds
     *
     * @return array<int, array{id: int, date: string}>
     */
    private static function nextOccurrences(array $teamIds): array
    {
        $scheduleTeams = [];
        $rows = VolunteerScheduleQuery::create()
            ->filterByTeamId($teamIds, Criteria::IN)
            ->select(['Id', 'TeamId'])
            ->find()
            ->toArray();
        foreach ($rows as $row) {
            $scheduleTeams[(int) $row['Id']] = (int) $row['TeamId'];
        }
        if ($scheduleTeams === []) {
            return [];
        }

        $today = DateTimeUtils::getTodayDate();

        $next = [];
        $occurrences = VolunteerOccurrenceQuery::create()
            ->filterByScheduleId(array_keys($scheduleTeams), Criteria::IN)
            ->filterByStatus(VolunteerOccurrence::STATUS_SCHEDULED)
            ->filterByOccurrenceDate($today, Criteria::GREATER_EQUAL)
            ->orderByOccurrenceDate()
            ->select(['Id', 'ScheduleId', 'OccurrenceDate'])
            ->find()
            ->toArray();

        foreach ($occurrences as $row) {
            $teamId = $scheduleTeams[(int) $row['ScheduleId']] ?? 0;
            if ($teamId === 0 || isset($next[$teamId])) {
                // Ordered by date, so the first row seen for a team IS its next one.
                continue;
            }
            $next[$teamId] = [
                'id' => (int) $row['Id'],
                'date' => substr((string) $row['OccurrenceDate'], 0, 10),
            ];
        }

        return $next;
    }
}
