<?php

namespace ChurchCRM\Portal\Permissions;

use DateTimeInterface;

/**
 * A grant a person holds right now, with where it comes from (member sign-in
 * design §8.3): everyone signed in, a rule, or given by an administrator.
 * `sourceLabel` is what the admin pages show, e.g. "Leader of Sunday Greeters".
 */
final class EffectiveGrant
{
    public const SOURCE_EVERYONE = 'everyone';
    public const SOURCE_RULE = 'rule';
    public const SOURCE_GIVEN = 'given';

    public function __construct(
        public readonly string $capability,
        public readonly PortalScope $scope,
        public readonly string $source,
        public readonly string $sourceLabel,
        public readonly ?string $ruleKey = null,
        public readonly ?int $grantId = null,
        public readonly ?int $grantedByUserId = null,
        public readonly ?DateTimeInterface $endsAt = null,
    ) {
    }

    /**
     * @return array{capability: string, scope: array{type: string, id: int}, source: string, sourceLabel: string, ruleKey: string|null, grantId: int|null, grantedByUserId: int|null, endsAt: string|null}
     */
    public function toArray(): array
    {
        return [
            'capability' => $this->capability,
            'scope' => $this->scope->toArray(),
            'source' => $this->source,
            'sourceLabel' => $this->sourceLabel,
            'ruleKey' => $this->ruleKey,
            'grantId' => $this->grantId,
            'grantedByUserId' => $this->grantedByUserId,
            'endsAt' => $this->endsAt?->format('Y-m-d H:i:s'),
        ];
    }
}
