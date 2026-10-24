<?php

namespace ChurchCRM\Portal\Auth;

use DateTimeImmutable;

/** A freshly issued device token: the value for the cookie, never stored. */
final class RememberedDeviceToken
{
    public function __construct(
        public readonly int $deviceId,
        public readonly string $value,
        public readonly DateTimeImmutable $expiresAt,
    ) {
    }
}
