<?php

declare(strict_types=1);

namespace ChurchCRM\Authorization;

/** One staff permission the registry knows. */
final class PermissionDefinition
{
    public const ORDINARY = 'ordinary';
    public const SENSITIVE = 'sensitive';

    public function __construct(
        public readonly string $key,
        public readonly ?string $module,
        public readonly string $sensitivity,
        public readonly string $since,
    ) {
    }
}
