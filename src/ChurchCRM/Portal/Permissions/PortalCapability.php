<?php

namespace ChurchCRM\Portal\Permissions;

use Closure;

/**
 * One thing a person may see or do in the portal (member sign-in design §8.1).
 *
 * `owner` is `core` or a plugin id. `featureSwitch` is a boolean SystemConfig
 * key: while it is off the capability is denied for everyone. Core may add an
 * `available` check for a switch that is not a single setting, such as the
 * Volunteer v2 rollout state.
 */
final class PortalCapability
{
    public const OWNER_CORE = 'core';

    /**
     * @param Closure(): bool|null $available
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $description,
        public readonly string $owner,
        public readonly string $scopeType = PortalScope::NONE,
        public readonly ?string $featureSwitch = null,
        private readonly ?Closure $available = null,
    ) {
    }

    public function isCore(): bool
    {
        return $this->owner === self::OWNER_CORE;
    }

    public function isScoped(): bool
    {
        return $this->scopeType !== PortalScope::NONE;
    }

    /** @internal PortalAuthorizationService */
    public function passesAvailability(): bool
    {
        return $this->available === null || ($this->available)() === true;
    }
}
