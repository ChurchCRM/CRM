<?php

namespace ChurchCRM\Portal\Auth;

use ChurchCRM\model\ChurchCRM\Person;

/**
 * The answer to "who may sign in with this typed address" (member sign-in
 * design §4, the email rule). Either a person who may sign in, or a refusal
 * for the audit, with the person it concerns when there is exactly one.
 */
final class SignInCandidate
{
    /**
     * @param int[] $matchedPersonIds everyone whose personal email matched, for the office notice
     */
    private function __construct(
        public readonly string $email,
        public readonly ?Person $person,
        public readonly ?SignInReason $refusal,
        public readonly array $matchedPersonIds,
    ) {
    }

    public static function allowed(string $email, Person $person): self
    {
        return new self($email, $person, null, [(int) $person->getId()]);
    }

    /**
     * @param int[] $matchedPersonIds
     */
    public static function refused(string $email, SignInReason $reason, ?Person $person = null, array $matchedPersonIds = []): self
    {
        return new self($email, $person, $reason, $matchedPersonIds);
    }

    public function isAllowed(): bool
    {
        return $this->refusal === null && $this->person instanceof Person;
    }
}
