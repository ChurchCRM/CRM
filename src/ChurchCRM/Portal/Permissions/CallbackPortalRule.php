<?php

namespace ChurchCRM\Portal\Permissions;

use ChurchCRM\model\ChurchCRM\Person;
use Closure;

/**
 * A rule written as a closure, for plugins that need only a few lines:
 *
 *     new CallbackPortalRule('plugin.smallgroups.leaders', fn (Person $p): array => [...]);
 */
final class CallbackPortalRule implements PortalRule
{
    /**
     * @param Closure(Person): iterable<PortalRuleGrant> $grants
     */
    public function __construct(
        private readonly string $key,
        private readonly Closure $grants,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function grantsFor(Person $person): iterable
    {
        return ($this->grants)($person);
    }
}
