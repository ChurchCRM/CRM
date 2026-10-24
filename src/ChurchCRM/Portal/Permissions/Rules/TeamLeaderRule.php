<?php

namespace ChurchCRM\Portal\Permissions\Rules;

use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\VolunteerScopeQuery;
use ChurchCRM\model\ChurchCRM\VolunteerTeamQuery;
use ChurchCRM\Portal\Permissions\BulkPortalRule;
use ChurchCRM\Portal\Permissions\CorePortalCapabilities;
use ChurchCRM\Portal\Permissions\PortalRuleGrant;
use ChurchCRM\Portal\Permissions\PortalScope;
use ChurchCRM\Volunteer\Service\VolunteerAuthorizationService;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Team leader (member sign-in design §8.3): the volunteer `team` scope rows of
 * the person. A `ministry` scope is a coordinator's, not a team leader's, and
 * gives nothing here (volunteer design §4.4).
 */
final class TeamLeaderRule implements BulkPortalRule
{
    public const KEY = 'core.team-leader';

    public function getKey(): string
    {
        return self::KEY;
    }

    public function grantsFor(Person $person): iterable
    {
        $teamIds = (new VolunteerAuthorizationService())->teamScopeIdsForPerson((int) $person->getId());
        if ($teamIds === []) {
            return;
        }

        $teams = VolunteerTeamQuery::create()->filterById($teamIds, Criteria::IN)->orderByName()->find();
        foreach ($teams as $team) {
            yield from self::grants((int) $team->getId(), (string) $team->getName());
        }
    }

    public function getSourceName(): string
    {
        return gettext('Team leader');
    }

    public function getCapabilityKeys(): array
    {
        return [CorePortalCapabilities::TEAM_MANAGE, CorePortalCapabilities::TEAM_SUBSTITUTES];
    }

    public function grantsForPeople(array $personIds): iterable
    {
        if ($personIds === [] || !User::isVolunteerV2Enabled()) {
            return;
        }

        $scopes = [];
        foreach (array_chunk($personIds, 1000) as $chunk) {
            $rows = VolunteerScopeQuery::create()
                ->filterByScopeType(VolunteerAuthorizationService::SCOPE_TEAM)
                ->filterByPersonId($chunk, Criteria::IN)
                ->select(['PersonId', 'ScopeId'])
                ->find();
            foreach ($rows as $row) {
                $scopes[] = [(int) $row['PersonId'], (int) $row['ScopeId']];
            }
        }
        if ($scopes === []) {
            return;
        }

        $teamNames = [];
        $teams = VolunteerTeamQuery::create()
            ->filterById(array_values(array_unique(array_column($scopes, 1))), Criteria::IN)
            ->select(['Id', 'Name'])
            ->find();
        foreach ($teams as $team) {
            $teamNames[(int) $team['Id']] = (string) $team['Name'];
        }

        foreach ($scopes as [$personId, $teamId]) {
            if (isset($teamNames[$teamId])) {
                foreach (self::grants($teamId, $teamNames[$teamId]) as $grant) {
                    yield $personId => $grant;
                }
            }
        }
    }

    /**
     * @return list<PortalRuleGrant>
     */
    private static function grants(int $teamId, string $teamName): array
    {
        $scope = PortalScope::team($teamId);
        $label = sprintf(gettext('Leader of %s'), $teamName);

        return [
            new PortalRuleGrant(CorePortalCapabilities::TEAM_MANAGE, $scope, $label),
            new PortalRuleGrant(CorePortalCapabilities::TEAM_SUBSTITUTES, $scope, $label),
        ];
    }
}
