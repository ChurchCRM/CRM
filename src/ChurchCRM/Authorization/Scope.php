<?php

declare(strict_types=1);

namespace ChurchCRM\Authorization;

/**
 * What a grant is held on (member sign-in design §8.1): a scope type and an
 * id. A capability with scope type `none` is held on `Scope::none()`.
 */
class Scope
{
    public const NONE = 'none';
    public const FAMILY = 'family';
    public const TEAM = 'team';
    public const MINISTRY = 'ministry';
    public const GROUP = 'group';

    public const CORE_TYPES = [self::NONE, self::FAMILY, self::TEAM, self::MINISTRY, self::GROUP];

    public function __construct(
        public readonly string $type,
        public readonly int $id = 0,
    ) {
    }

    public static function none(): static
    {
        return new static(self::NONE, 0);
    }

    public static function family(int $familyId): static
    {
        return new static(self::FAMILY, $familyId);
    }

    public static function team(int $teamId): static
    {
        return new static(self::TEAM, $teamId);
    }

    public static function ministry(int $ministryId): static
    {
        return new static(self::MINISTRY, $ministryId);
    }

    public static function group(int $groupId): static
    {
        return new static(self::GROUP, $groupId);
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->id === $other->id;
    }

    public function key(): string
    {
        return $this->type . ':' . $this->id;
    }

    /**
     * @return array{type: string, id: int}
     */
    public function toArray(): array
    {
        return ['type' => $this->type, 'id' => $this->id];
    }
}
