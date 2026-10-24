<?php

namespace ChurchCRM\Portal\Auth;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\signin\EmailChangeConfirmEmail;
use ChurchCRM\Emails\signin\EmailChangeNoticeEmail;
use ChurchCRM\model\ChurchCRM\FamilyEmailMoveQuery;
use ChurchCRM\model\ChurchCRM\Map\PersonSigninTableMap;
use ChurchCRM\model\ChurchCRM\Note;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonDeviceQuery;
use ChurchCRM\model\ChurchCRM\PersonEmailChange;
use ChurchCRM\model\ChurchCRM\PersonEmailChangeQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PersonSignin;
use ChurchCRM\model\ChurchCRM\PersonSigninQuery;
use ChurchCRM\model\ChurchCRM\PortalGrantQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Portal\MemberLanguage;
use ChurchCRM\Portal\PortalSelfService;
use ChurchCRM\Service\RateLimiter;
use ChurchCRM\Service\RememberedDeviceService;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use DateTimeImmutable;
use DateTimeInterface;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Throwable;

/**
 * The rules for changing a person's personal email, which is their sign-in
 * identity (member sign-in design §9). Every path that changes `per_Email`
 * goes through here:
 *
 *  - staff, imports, the API and registration forms set it at once
 *    (`changeRefusal()` before the save, `recordChange()` after it). Once the
 *    person has signed in, a new address ends their sessions and remembered
 *    devices, notifies the previous address and stays unconfirmed;
 *  - the member, in the portal, gets a link at the new address and the change
 *    happens when it is used (`requestChange()`, `useLink()`).
 *
 * An unconfirmed address can't be used to sign in until the link in the notice
 * to the proven address is used, staff confirm it on the person page
 * (`confirmByStaff()`), or, after a passkey sign-in, the member uses the link
 * `sendConfirmationLink()` sends to it (row 8).
 */
final class SignInEmailPolicy
{
    public const SOURCE_FAMILY_EMAIL = 'family_email';
    public const KIND_CHANGE = 'change';
    public const KIND_CONFIRM = 'confirm';
    /** How staff confirmed an address, in the audit row's `via`. */
    public const CONFIRMED_ON_PERSON_PAGE = 'person_page';
    public const CONFIRMED_IN_BULK = 'bulk_confirm';

    private const EMAIL_MAX_LENGTH = 100;
    private const REQUEST_BUCKET = 'email-change.person';
    private const REQUESTS_PER_HOUR = 5;

    public static function sameAddress(?string $first, ?string $second): bool
    {
        return MemberSignInRule::normalizeEmail((string) $first) === MemberSignInRule::normalizeEmail((string) $second);
    }

    /**
     * "Has signed in" in §9: an email sign-in proved an address. Every other
     * member way in follows one; a staff account opening its own portal does not count.
     */
    public function hasSignedIn(?PersonSignin $state): bool
    {
        return $state instanceof PersonSignin && $state->getProvenAt() !== null;
    }

    /**
     * A person with a staff account or given portal grants, whose email only
     * an administrator may change or confirm.
     */
    public function needsAdministrator(Person $person): bool
    {
        return $this->peopleNeedingAdministrator([(int) $person->getId()]) !== [];
    }

    /**
     * Which of these people have a staff account or given portal grants.
     *
     * @param int[] $personIds
     *
     * @return int[]
     */
    public function peopleNeedingAdministrator(array $personIds): array
    {
        if ($personIds === []) {
            return [];
        }

        $found = [];
        foreach (UserQuery::create()->filterByPersonId($personIds, Criteria::IN)->select(['PersonId'])->find() as $personId) {
            $found[(int) $personId] = true;
        }

        $now = $this->now();
        foreach (PortalGrantQuery::create()->filterByPersonId($personIds, Criteria::IN)->find() as $grant) {
            $endsAt = $grant->getEndsAt();
            if (!$endsAt instanceof DateTimeInterface || $endsAt > $now) {
                $found[(int) $grant->getPersonId()] = true;
            }
        }

        return array_keys($found);
    }

    /**
     * Why `$actor` may not set this person's personal email to `$newEmail`,
     * or null when they may. Saving the address on record again is always
     * allowed and changes nothing.
     */
    public function changeRefusal(Person $person, ?string $newEmail, ?User $actor): ?string
    {
        if ($person->isNew() || self::sameAddress($person->getEmail(), $newEmail)) {
            return null;
        }
        if ($actor?->isAdmin() !== true && $this->needsAdministrator($person)) {
            return gettext('Only an administrator can change the email of a person who has a staff account or portal permissions.');
        }

        return null;
    }

    /**
     * After a save by staff, an import, the API or a registration form that
     * may have changed `per_Email` from `$oldEmail`. Before the first sign-in
     * the new address is the address on record and works at once. After it,
     * every portal session and remembered device ends, the previous address is
     * told, and the new address stays unconfirmed. Passkeys stay. Audited.
     */
    public function recordChange(Person $person, ?string $oldEmail, EmailChangeChannel $channel, ?User $actor): void
    {
        $oldEmail = trim((string) $oldEmail);
        $newEmail = trim((string) $person->getEmail());
        if (self::sameAddress($oldEmail, $newEmail)) {
            return;
        }

        $personId = (int) $person->getId();
        $now = $this->now();
        $state = PersonSigninQuery::create()->findPk($personId);
        $signedIn = $this->hasSignedIn($state);
        $unconfirmed = false;
        $devicesRevoked = 0;

        $connection = Propel::getWriteConnection(PersonSigninTableMap::DATABASE_NAME);
        $connection->beginTransaction();
        try {
            $this->cancelOpenLinks($personId, null, $now);
            if ($signedIn) {
                $state->endAllSessions();
                if ($newEmail !== '') {
                    $state->setUnconfirmedEmail($newEmail)
                        ->setUnconfirmedSource($channel->value)
                        ->setUnconfirmedAt($now);
                    $unconfirmed = true;
                } else {
                    $this->clearUnconfirmed($state);
                }
                $state->save($connection);
                $devicesRevoked = PersonDeviceQuery::create()
                    ->filterByPersonId($personId)
                    ->filterByRevokedAt(null, Criteria::ISNULL)
                    ->update(RememberedDeviceService::revokedValues($now), $connection);
            } elseif ($state instanceof PersonSignin && $state->getUnconfirmedEmail() !== null) {
                $this->clearUnconfirmed($state);
                $state->save($connection);
            }
            $connection->commit();
        } catch (Throwable $e) {
            $connection->rollBack();

            throw $e;
        }

        if ($channel === EmailChangeChannel::STAFF && $actor?->isAdmin() === true && $this->needsAdministrator($person)) {
            $note = new Note();
            $note->setPerId($personId);
            $note->setType('edit');
            $note->setEntered((int) $actor->getId());
            $note->setText(sprintf(
                gettext('Email changed by an administrator from %1$s to %2$s'),
                InputUtils::escapeHTML($oldEmail),
                InputUtils::escapeHTML($newEmail)
            ));
            $note->save();
        }

        SignInAudit::record(
            SignInAuditEvent::EMAIL_CHANGED,
            $personId,
            actorUserId: $actor === null ? null : (int) $actor->getId(),
            email: $newEmail === '' ? null : $newEmail,
            detail: self::detail([
                'from' => $oldEmail,
                'via' => $channel->value,
                'unconfirmed' => $unconfirmed ? 'yes' : null,
                'devices' => $signedIn ? (string) $devicesRevoked : null,
            ]),
        );

        if ($signedIn) {
            $this->sendNotices($person, $state, $oldEmail, $newEmail, EmailChangeNoticeEmail::BY_SOMEONE_ELSE, $unconfirmed);
        }
    }

    /**
     * Why the member may not change their own email in the portal to
     * `$newEmail`, or null. `$staffActor` is the staff account behind a STAFF
     * portal session; only an administrator there may change the email of a
     * person with a staff account or given grants.
     */
    public function requestRefusal(Person $person, string $newEmail, ?User $staffActor = null): ?EmailChangeRefusedException
    {
        $newEmail = trim($newEmail);
        if ($newEmail === '') {
            return new EmailChangeRefusedException(gettext('Your email address cannot be removed here. Please contact the church office.'), 400);
        }
        if (mb_strlen($newEmail) > self::EMAIL_MAX_LENGTH || filter_var($newEmail, FILTER_VALIDATE_EMAIL) === false) {
            return new EmailChangeRefusedException(gettext('That is not a valid email address.'), 400);
        }
        if (!$this->memberMayChange($person, $staffActor)) {
            return new EmailChangeRefusedException(gettext('To change this email, please contact the church office.'), 403);
        }

        return null;
    }

    public function memberMayChange(Person $person, ?User $staffActor = null): bool
    {
        return $staffActor?->isAdmin() === true || !$this->needsAdministrator($person);
    }

    /**
     * The member's own change, in the portal: a link goes to the new address
     * and the change happens when it is used. The previous address is told.
     *
     * @return bool false when the address is the one on record already
     *
     * @throws EmailChangeRefusedException
     */
    public function requestChange(Person $person, string $newEmail, ?User $staffActor = null): bool
    {
        $newEmail = trim($newEmail);
        if (self::sameAddress($person->getEmail(), $newEmail)) {
            return false;
        }
        $refusal = $this->requestRefusal($person, $newEmail, $staffActor);
        if ($refusal !== null) {
            throw $refusal;
        }

        $personId = (int) $person->getId();
        if (!(new RateLimiter(self::REQUEST_BUCKET, self::REQUESTS_PER_HOUR, 3600))->attempt((string) $personId)) {
            throw new EmailChangeRefusedException(gettext('You have asked to change your email too many times. Please try again later.'), 429);
        }

        $now = $this->now();
        $this->cancelOpenLinks($personId, self::KIND_CHANGE, $now);
        if (!$this->sendConfirmEmail($person, $newEmail, $now)) {
            throw new EmailChangeRefusedException(gettext('The confirmation email could not be sent. Please try again later.'), 503);
        }

        $this->sendNotices(
            $person,
            PersonSigninQuery::create()->findPk($personId),
            trim((string) $person->getEmail()),
            $newEmail,
            EmailChangeNoticeEmail::BY_MEMBER,
            false
        );

        return true;
    }

    /**
     * The passkey way to confirm an unconfirmed address (§9). Row 8 calls this
     * from the member's Sign-in and devices page after a passkey sign-in: a
     * link goes to the unconfirmed address, and using it confirms the address
     * as the member's proven email.
     *
     * @return bool false when there is nothing to confirm or the email could not be sent
     */
    public function sendConfirmationLink(Person $person): bool
    {
        $personId = (int) $person->getId();
        $state = PersonSigninQuery::create()->findPk($personId);
        $address = trim((string) $state?->getUnconfirmedEmail());
        if ($address === '' || !self::sameAddress($address, $person->getEmail())) {
            return false;
        }

        $now = $this->now();
        $this->cancelOpenLinks($personId, self::KIND_CHANGE, $now);

        return $this->sendConfirmEmail($person, $address, $now);
    }

    /** The address the member asked to change to, while its link is still open. */
    public function pendingEmail(Person $person): ?string
    {
        $link = $this->openLinks((int) $person->getId(), self::KIND_CHANGE, $this->now())
            ->orderById(Criteria::DESC)
            ->findOne();

        return $link instanceof PersonEmailChange && !self::sameAddress($link->getEmail(), $person->getEmail())
            ? $link->getEmail()
            : null;
    }

    /** An emailed link that can still be used, without spending it. */
    public function findUsableLink(string $token): ?PersonEmailChange
    {
        if (preg_match('/^[0-9a-f]{64}$/', $token) !== 1) {
            return null;
        }

        return $this->openLinks(null, null, $this->now())
            ->filterByTokenHash(hash('sha256', $token))
            ->findOne();
    }

    /**
     * Spends an emailed link and applies it: a `change` link makes its address
     * the person's proven email, a `confirm` link confirms the unconfirmed
     * address it names. Each link works once.
     *
     * @return PersonEmailChange|null the link used, or null when it can't be used
     */
    public function useLink(string $token): ?PersonEmailChange
    {
        $link = $this->findUsableLink($token);
        if (!$link instanceof PersonEmailChange) {
            return null;
        }

        $now = $this->now();
        $spent = $this->openLinks(null, null, $now)
            ->filterById($link->getId())
            ->update(['UsedAt' => $now->format('Y-m-d H:i:s')]);
        $person = PersonQuery::create()->findPk((int) $link->getPersonId());
        if ($spent !== 1 || !$person instanceof Person) {
            return null;
        }

        $applied = $link->getKind() === self::KIND_CHANGE
            ? $this->applyChangeLink($person, $link, $now)
            : $this->applyConfirmLink($person, $link, $now);

        return $applied ? $link : null;
    }

    /**
     * Why a staff member may not confirm this person's unconfirmed email on the
     * person page, or null. An address moved from the family email may be
     * confirmed by staff with Edit Records; a replacement address after the
     * first sign-in, and any address of a person with a staff account or given
     * grants, only by an administrator.
     */
    public function confirmRefusal(Person $person, ?PersonSignin $state, bool $byAdministrator): ?string
    {
        if (trim((string) $state?->getUnconfirmedEmail()) === '') {
            return gettext('This person has no unconfirmed email.');
        }
        if ($byAdministrator) {
            return null;
        }
        if ($this->needsAdministrator($person)) {
            return gettext('Only an administrator can confirm the email of a person who has a staff account or portal permissions.');
        }
        if ($state->getUnconfirmedSource() !== self::SOURCE_FAMILY_EMAIL) {
            return gettext('Only an administrator can confirm an email that replaced one the person signed in with.');
        }

        return null;
    }

    /**
     * Staff confirm the unconfirmed address `$email` after checking who the
     * person is, in person or by phone. `$via` is CONFIRMED_ON_PERSON_PAGE or
     * CONFIRMED_IN_BULK.
     *
     * @throws EmailChangeRefusedException 403 when the actor may not, 409 when there is nothing to confirm or the address changed
     */
    public function confirmByStaff(Person $person, string $email, User $actor, string $via = self::CONFIRMED_ON_PERSON_PAGE): void
    {
        if (!$actor->isEditRecordsEnabled()) {
            throw new EmailChangeRefusedException(gettext('You are not allowed to confirm this email.'), 403);
        }

        $personId = (int) $person->getId();
        $state = PersonSigninQuery::create()->findPk($personId);
        $refusal = $this->confirmRefusal($person, $state, $actor->isAdmin());
        if ($refusal !== null) {
            throw new EmailChangeRefusedException($refusal, trim((string) $state?->getUnconfirmedEmail()) === '' ? 409 : 403);
        }
        if (!self::sameAddress($state->getUnconfirmedEmail(), $email)) {
            throw new EmailChangeRefusedException(gettext('The unconfirmed email has changed. Reload the page and try again.'), 409);
        }

        $address = (string) $state->getUnconfirmedEmail();
        $source = (string) $state->getUnconfirmedSource();
        $this->clearUnconfirmed($state);
        $state->save();
        $this->cancelOpenLinks($personId, self::KIND_CONFIRM, $this->now());

        SignInAudit::record(
            SignInAuditEvent::EMAIL_CONFIRMED,
            $personId,
            actorUserId: (int) $actor->getId(),
            email: $address,
            detail: self::detail(['via' => $via, 'source' => $source]),
        );
    }

    public static function sourceLabel(?string $source): string
    {
        return match ($source) {
            self::SOURCE_FAMILY_EMAIL => gettext('Moved from the family email'),
            EmailChangeChannel::STAFF->value => gettext('Changed by staff after the person signed in'),
            EmailChangeChannel::IMPORT->value => gettext('Changed by an import after the person signed in'),
            EmailChangeChannel::API->value => gettext('Changed through the API after the person signed in'),
            default => gettext('Changed after the person signed in'),
        };
    }

    private function applyChangeLink(Person $person, PersonEmailChange $link, DateTimeImmutable $now): bool
    {
        $personId = (int) $person->getId();
        $address = (string) $link->getEmail();
        $oldEmail = trim((string) $person->getEmail());
        $changed = !self::sameAddress($oldEmail, $address);

        if ($changed) {
            $person->setEmail($address)
                ->setDateLastEdited($now)
                ->setEditedBy($personId)
                ->saveWithoutUpdateNote();
            $note = new Note();
            $note->setPerson($person);
            $note->setType(PortalSelfService::PORTAL_NOTE_TYPE);
            $note->setEntered($personId);
            $note->setText(sprintf(gettext('Edited via the Member Portal: %s'), gettext('Email')));
            $note->save();
        }

        $state = PersonSigninQuery::create()->findPk($personId) ?? (new PersonSignin())->setPersonId($personId);
        $state->setProvenEmail($address)->setProvenAt($now);
        $this->clearUnconfirmed($state);
        $state->save();
        $this->cancelOpenLinks($personId, null, $now);

        SignInAudit::record(
            $changed ? SignInAuditEvent::EMAIL_CHANGED : SignInAuditEvent::EMAIL_CONFIRMED,
            $personId,
            email: $address,
            detail: self::detail([
                'from' => $changed ? $oldEmail : null,
                'via' => $changed ? EmailChangeChannel::PORTAL->value : 'link_to_address',
            ]),
        );

        return true;
    }

    private function applyConfirmLink(Person $person, PersonEmailChange $link, DateTimeImmutable $now): bool
    {
        $personId = (int) $person->getId();
        $state = PersonSigninQuery::create()->findPk($personId);
        if (
            !$state instanceof PersonSignin
            || !self::sameAddress($state->getUnconfirmedEmail(), $link->getEmail())
            || !self::sameAddress($person->getEmail(), $link->getEmail())
        ) {
            return false;
        }

        $source = (string) $state->getUnconfirmedSource();
        $this->clearUnconfirmed($state);
        $state->save();
        $this->cancelOpenLinks($personId, self::KIND_CONFIRM, $now);

        SignInAudit::record(
            SignInAuditEvent::EMAIL_CONFIRMED,
            $personId,
            email: (string) $link->getEmail(),
            detail: self::detail(['via' => 'link_to_previous_address', 'source' => $source]),
        );

        return true;
    }

    private function clearUnconfirmed(PersonSignin $state): void
    {
        if ($state->getUnconfirmedSource() === self::SOURCE_FAMILY_EMAIL) {
            FamilyEmailMoveQuery::create()
                ->filterByPersonId((int) $state->getPersonId())
                ->filterByNeedsConfirm(true)
                ->update(['NeedsConfirm' => false]);
        }
        $state->setUnconfirmedEmail(null)->setUnconfirmedSource(null)->setUnconfirmedAt(null);
    }

    /**
     * The person's previous address, and the address they proved by signing in
     * when that is another one. Only the proven address gets the link that
     * confirms an unconfirmed replacement.
     */
    private function sendNotices(Person $person, ?PersonSignin $state, string $oldEmail, string $newEmail, string $by, bool $withConfirmLink): void
    {
        $proven = trim((string) $state?->getProvenEmail());
        $recipients = [];
        foreach ([$oldEmail, $proven] as $address) {
            if ($address !== '' && !self::sameAddress($address, $newEmail)) {
                $recipients[MemberSignInRule::normalizeEmail($address)] = $address;
            }
        }

        foreach ($recipients as $address) {
            $confirmUrl = null;
            $expiresAt = null;
            if ($withConfirmLink && $proven !== '' && self::sameAddress($address, $proven)) {
                [$token, $expiresAt] = $this->issueLink((int) $person->getId(), self::KIND_CONFIRM, $newEmail, $this->now());
                $confirmUrl = self::linkUrl($token);
            }
            MemberLanguage::inLanguageOf(
                (int) $person->getId(),
                static fn (): bool => (new EmailChangeNoticeEmail($address, $person, $oldEmail, $newEmail, $by, $confirmUrl, $expiresAt))->send()
            );
        }
    }

    private function sendConfirmEmail(Person $person, string $address, DateTimeImmutable $now): bool
    {
        [$token, $expiresAt] = $this->issueLink((int) $person->getId(), self::KIND_CHANGE, $address, $now);
        $sent = MemberLanguage::inLanguageOf(
            (int) $person->getId(),
            static fn (): bool => (new EmailChangeConfirmEmail($address, $person, self::linkUrl($token), $expiresAt))->send()
        );
        if ($sent) {
            return true;
        }

        PersonEmailChangeQuery::create()
            ->filterByTokenHash(hash('sha256', $token))
            ->update(['CancelledAt' => $now->format('Y-m-d H:i:s')]);

        return false;
    }

    /**
     * @return array{0: string, 1: DateTimeImmutable} the token, which only the email carries, and when it expires
     */
    private function issueLink(int $personId, string $kind, string $address, DateTimeImmutable $now): array
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = $now->modify(sprintf('+%d minutes', max(1, SystemConfig::getIntValue('iSignInLinkMinutes'))));
        (new PersonEmailChange())
            ->setPersonId($personId)
            ->setKind($kind)
            ->setEmail($address)
            ->setTokenHash(hash('sha256', $token))
            ->setCreatedAt($now)
            ->setExpiresAt($expiresAt)
            ->save();

        return [$token, $expiresAt];
    }

    private function cancelOpenLinks(int $personId, ?string $kind, DateTimeImmutable $now): void
    {
        $this->openLinks($personId, $kind, $now)->update(['CancelledAt' => $now->format('Y-m-d H:i:s')]);
    }

    private function openLinks(?int $personId, ?string $kind, DateTimeImmutable $now): PersonEmailChangeQuery
    {
        $query = PersonEmailChangeQuery::create()
            ->filterByUsedAt(null, Criteria::ISNULL)
            ->filterByCancelledAt(null, Criteria::ISNULL)
            ->filterByExpiresAt($now->format('Y-m-d H:i:s'), Criteria::GREATER_THAN);
        if ($personId !== null) {
            $query->filterByPersonId($personId);
        }
        if ($kind !== null) {
            $query->filterByKind($kind);
        }

        return $query;
    }

    private static function linkUrl(string $token): string
    {
        return SystemURLs::getURL() . '/portal/email/confirm/' . $token;
    }

    /**
     * @param array<string, string|null> $parts
     */
    private static function detail(array $parts): string
    {
        $pairs = [];
        foreach ($parts as $key => $value) {
            if ($value !== null) {
                $pairs[] = $key . '=' . $value;
            }
        }

        return implode('; ', $pairs);
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', DateTimeUtils::getConfiguredTimezone());
    }
}
