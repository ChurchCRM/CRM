<?php

namespace ChurchCRM\Plugins\PortalExample;

use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\Plugin\AbstractPlugin;
use ChurchCRM\Portal\Permissions\CallbackPortalRule;
use ChurchCRM\Portal\Permissions\PortalRuleGrant;
use ChurchCRM\Portal\Permissions\PortalScope;

class PortalExamplePlugin extends AbstractPlugin
{
    public const ROSTER = 'plugin.portal-example.roster.view';
    public const NOTICES = 'plugin.portal-example.notices';

    public function getId(): string
    {
        return 'portal-example';
    }

    public function getName(): string
    {
        return 'Portal Example';
    }

    public function getDescription(): string
    {
        return 'Cypress fixture plugin.';
    }

    public function boot(): void
    {
    }

    public function getPortalRules(): array
    {
        return [
            new CallbackPortalRule('plugin.portal-example.group-members', static function (Person $person): array {
                $grants = [];
                $memberships = Person2group2roleP2g2rQuery::create()->filterByPersonId((int) $person->getId())->find();
                foreach ($memberships as $membership) {
                    $group = $membership->getGroup();
                    $grants[] = new PortalRuleGrant(
                        self::ROSTER,
                        PortalScope::group((int) $membership->getGroupId()),
                        sprintf('Member of %s', $group === null ? '' : $group->getName())
                    );
                }

                return $grants;
            }),
        ];
    }

    public function getPortalNavItems(): array
    {
        return [
            ['id' => 'portal-example-rosters', 'label' => 'Rosters', 'url' => '/portal/plugins/portal-example/rosters', 'icon' => 'fa-solid fa-list', 'capability' => self::ROSTER],
            ['id' => 'portal-example-notices', 'label' => 'Notice board', 'url' => '/portal/plugins/portal-example/notices', 'icon' => 'fa-solid fa-thumbtack', 'capability' => self::NOTICES],
        ];
    }
}
