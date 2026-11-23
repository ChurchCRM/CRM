<?php

declare(strict_types=1);

namespace ChurchCRM\Authorization;

/**
 * Who is asking. A portal member is never staff. Role keys are the built-in
 * roles they hold. Ministry ids are the coordinator's volunteer scopes.
 */
final class AccessPrincipal
{
    /**
     * @param list<string> $roleKeys
     * @param list<int> $ministryIds
     * @param array<string, bool|string> $modules
     */
    public function __construct(
        public readonly bool $portal,
        public readonly bool $owner,
        public readonly array $roleKeys,
        public readonly array $ministryIds,
        public readonly array $modules,
    ) {
    }
}
