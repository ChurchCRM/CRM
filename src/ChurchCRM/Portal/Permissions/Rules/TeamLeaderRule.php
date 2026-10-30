<?php

namespace ChurchCRM\Portal\Permissions\Rules;

use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\VolunteerTeamQuery;
use ChurchCRM\Portal\Permissions\CorePortalCapabilities;
use ChurchCRM\Portal\Permissions\PortalRule;
use ChurchCRM\Portal\Permissions\PortalRuleGrant;
use ChurchCRM\Portal\Permissions\PortalScope;
use ChurchCRM\Volunteer\Service\VolunteerAuthorizationService;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Team leader (member sign-in design §8.3): the volunteer `team` scope rows of
 * the person. A `ministry` scope is a coordinator's, not a team leader's, and
 * gives nothing here (volunteer design §4.4).
 */
final class TeamLeaderRule implements PortalRule
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
            $scope = PortalScope::team((int) $team->getId());
            $label = sprintf(gettext('Leader of %s'), (string) $team->getName());
            yield new PortalRuleGrant(CorePortalCapabilities::TEAM_MANAGE, $scope, $label);
            yield new PortalRuleGrant(CorePortalCapabilities::TEAM_SUBSTITUTES, $scope, $label);
        }
    }
}
