<?php

declare(strict_types=1);

namespace ChurchCRM\Authorization;

/**
 * The answer to a permission check (member sign-in design §8.2). The reason is
 * for logs and the admin preview; it is never shown to the member.
 */
class Decision
{
    public const GRANTED = 'granted';
    public const UNKNOWN_CAPABILITY = 'unknown_capability';
    public const OWNER_OFF = 'owner_off';
    public const FEATURE_OFF = 'feature_off';
    public const SCOPE_REQUIRED = 'scope_required';
    public const SCOPE_MISMATCH = 'scope_mismatch';
    public const NO_PERSON = 'no_person';
    public const NO_GRANT = 'no_grant';

    // Protected so Portal\Permissions\Decision can be built by allow() and deny().
    protected function __construct(
        public readonly bool $allowed,
        public readonly string $reason,
        public readonly ?EffectiveGrant $grant = null,
    ) {
    }

    public static function allow(EffectiveGrant $grant): static
    {
        return new static(true, self::GRANTED, $grant);
    }

    public static function deny(string $reason): static
    {
        return new static(false, $reason);
    }

    /** True when the capability exists and is switched on for this church. */
    public function capabilityIsLive(): bool
    {
        return !in_array($this->reason, [self::UNKNOWN_CAPABILITY, self::OWNER_OFF, self::FEATURE_OFF], true);
    }
}
