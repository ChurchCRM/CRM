<?php

namespace ChurchCRM\Portal\Auth;

use DateTimeImmutable;

/**
 * Who a portal session belongs to (member sign-in design §3): a person, never a
 * user account. `actorUserId` is set only for Login as User, where it is the
 * administrator acting for the person.
 */
final class PortalPrincipal
{
    public function __construct(
        public readonly int $personId,
        public readonly SignInMethod $method,
        public readonly DateTimeImmutable $startedAt,
        public readonly int $sessionVersion,
        public readonly ?int $actorUserId = null,
    ) {
    }

    public function isAssisted(): bool
    {
        return $this->method === SignInMethod::ASSISTED;
    }

    /**
     * @return array{personId: int, method: string, startedAt: int, sessionVersion: int, actorUserId: int|null}
     */
    public function toArray(): array
    {
        return [
            'personId' => $this->personId,
            'method' => $this->method->value,
            'startedAt' => $this->startedAt->getTimestamp(),
            'sessionVersion' => $this->sessionVersion,
            'actorUserId' => $this->actorUserId,
        ];
    }

    public static function fromArray(mixed $data): ?self
    {
        if (!is_array($data) || !is_int($data['personId'] ?? null) || !is_int($data['startedAt'] ?? null)) {
            return null;
        }
        $method = SignInMethod::tryFrom((string) ($data['method'] ?? ''));
        if ($method === null) {
            return null;
        }
        $actorUserId = $data['actorUserId'] ?? null;

        return new self(
            $data['personId'],
            $method,
            (new DateTimeImmutable())->setTimestamp($data['startedAt']),
            (int) ($data['sessionVersion'] ?? 0),
            is_int($actorUserId) ? $actorUserId : null,
        );
    }
}
