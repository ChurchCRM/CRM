<?php

namespace ChurchCRM\Portal\Auth;

use DateTimeImmutable;

/**
 * A remembered device's token for the cookie, never stored. The value is null
 * after a sign-in with the token the device just replaced: the browser already
 * holds the current one.
 */
final class RememberedDeviceToken
{
    public function __construct(
        public readonly int $deviceId,
        public readonly ?string $value,
        public readonly DateTimeImmutable $expiresAt,
    ) {
    }
}
