<?php

namespace ChurchCRM\Portal;

use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Note;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\ORMUtils;
use DateTime;
use DateTimeImmutable;

/**
 * Everything a member may change about themselves and their family, and the
 * only place the Member Portal writes to `person_per` and `family_fam`
 * (design §5.2).
 *
 * Three rules hold for every method here, and the routes rely on all three:
 *
 *  1. **The actor is the session.** Every method takes the acting `Person`;
 *     none takes a person or family id from a request. There is therefore no
 *     parameter an attacker could point at somebody else (design P11).
 *  2. **Only allow-listed fields are read.** `PERSON_FIELDS` and
 *     `FAMILY_FIELDS` map a request key to its setter. Anything else in the
 *     body — `admin`, `familyId`, `EnteredBy` — is not looked at, so it is
 *     ignored rather than rejected, and a future column cannot be reached by
 *     guessing its name.
 *  3. **Every write leaves a trail.** A `Note` naming the changed fields is
 *     written on the person or the family, so staff reviewing the timeline see
 *     who changed what and that the change came from the portal.
 */
class PortalSelfService
{
    /**
     * The person fields a member may change, each mapped to its model getter
     * and setter. `birthday` is deliberately absent: it is a composite of three
     * columns gated by `bPortalAllowBirthdayEdit`, so it is handled on its own.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const PERSON_FIELDS = [
        'title' => ['getTitle', 'setTitle'],
        'firstName' => ['getFirstName', 'setFirstName'],
        'middleName' => ['getMiddleName', 'setMiddleName'],
        'lastName' => ['getLastName', 'setLastName'],
        'suffix' => ['getSuffix', 'setSuffix'],
        'email' => ['getEmail', 'setEmail'],
        'workEmail' => ['getWorkEmail', 'setWorkEmail'],
        'homePhone' => ['getHomePhone', 'setHomePhone'],
        'cellPhone' => ['getCellPhone', 'setCellPhone'],
        'workPhone' => ['getWorkPhone', 'setWorkPhone'],
    ];

    /**
     * The family fields an adult of the family may change. `weddingDate` is a
     * date column and is handled on its own, like `birthday` above.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const FAMILY_FIELDS = [
        'address1' => ['getAddress1', 'setAddress1'],
        'address2' => ['getAddress2', 'setAddress2'],
        'city' => ['getCity', 'setCity'],
        'state' => ['getState', 'setState'],
        'zip' => ['getZip', 'setZip'],
        'country' => ['getCountry', 'setCountry'],
        'homePhone' => ['getHomePhone', 'setHomePhone'],
        'email' => ['getEmail', 'setEmail'],
    ];

    /**
     * The request attribute holding the acting `Person`. Both portal
     * middlewares put the session's own person there, so no route — page or
     * API — ever has to work out for itself who is acting (design P11).
     */
    public const ACTOR_ATTRIBUTE = 'portalActor';

    /** The note type the external verify flow writes, and so does the portal. */
    public const VERIFY_NOTE_TYPE = 'verify';

    /** The note type every portal self-edit writes. */
    public const PORTAL_NOTE_TYPE = 'edit';

    public const CONFIRM_NO_CHANGE = 'no-change';
    public const CONFIRM_CHANGE_NEEDED = 'change-needed';

    // -- Reads ---------------------------------------------------------------

    /**
     * The acting member's own profile, in the shape the profile page and
     * `GET /api/portal/me` both render.
     *
     * @return array<string, mixed>
     */
    public static function getProfile(Person $actor): array
    {
        $family = $actor->getFamily();

        return [
            'id' => (int) $actor->getId(),
            'title' => (string) $actor->getTitle(),
            'firstName' => (string) $actor->getFirstName(),
            'middleName' => (string) $actor->getMiddleName(),
            'lastName' => (string) $actor->getLastName(),
            'suffix' => (string) $actor->getSuffix(),
            'email' => (string) $actor->getEmail(),
            'workEmail' => (string) $actor->getWorkEmail(),
            'homePhone' => (string) $actor->getHomePhone(),
            'cellPhone' => (string) $actor->getCellPhone(),
            'workPhone' => (string) $actor->getWorkPhone(),
            'birthday' => self::formatBirthday($actor),
            'fullName' => $actor->getFullName(),
            'familyRole' => $actor->getFamilyRoleName(),
            'familyId' => $family ? (int) $family->getId() : 0,
            'familyName' => $family ? (string) $family->getName() : '',
            'photoUrl' => self::getPhotoUrl($actor),
            'hasPhoto' => $actor->getPhoto()->hasUploadedPhoto(),
            'canEditBirthday' => PortalSettings::allowsBirthdayEdit(),
        ];
    }

    /**
     * The acting member's family: the address card, every member, and whether
     * this member is one of the adults who may change it.
     *
     * @return array<string, mixed>
     */
    public static function getFamilyView(Person $actor): array
    {
        $family = $actor->getFamily();
        if ($family === null) {
            return ['family' => null, 'members' => [], 'canEdit' => false, 'canConfirm' => false];
        }

        $members = [];
        foreach ($family->getPeopleSorted() as $member) {
            $members[] = [
                'id' => (int) $member->getId(),
                'fullName' => $member->getFullName(),
                'role' => $member->getFamilyRoleName(),
                'email' => (string) $member->getEmail(),
                'cellPhone' => (string) $member->getCellPhone(),
                'photoUrl' => self::getPhotoUrl($member),
                'initials' => self::getInitials($member),
                'isSelf' => (int) $member->getId() === (int) $actor->getId(),
                'isAdult' => self::isAdultOf($family, $member),
            ];
        }

        return [
            'family' => [
                'id' => (int) $family->getId(),
                'name' => (string) $family->getName(),
                'address1' => (string) $family->getAddress1(),
                'address2' => (string) $family->getAddress2(),
                'city' => (string) $family->getCity(),
                'state' => (string) $family->getState(),
                'zip' => (string) $family->getZip(),
                'country' => (string) $family->getCountry(),
                'homePhone' => (string) $family->getHomePhone(),
                'email' => (string) $family->getEmail(),
                'weddingDate' => (string) ($family->getWeddingdate('Y-m-d') ?? ''),
            ],
            'members' => $members,
            'canEdit' => self::isAdultOf($family, $actor),
            'canConfirm' => true,
        ];
    }

    /**
     * The family roles offered in the "add a family member" form, in the order
     * the administrator arranged them.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public static function getFamilyRoles(): array
    {
        $roles = [];
        $options = ListOptionQuery::create()
            ->filterById(2)
            ->orderByOptionSequence()
            ->find();
        foreach ($options as $option) {
            $roles[] = ['id' => (int) $option->getOptionId(), 'name' => (string) $option->getOptionName()];
        }

        return $roles;
    }

    // -- The adult-of-the-family rule ----------------------------------------

    /**
     * Whether this person is one of the family's adults — the audience the
     * verify flow addresses, and the only one that may change the family's
     * address and contact details (design §5.2).
     *
     * "Adult" is not a column: it is whichever family roles the administrator
     * put in `sDirRoleHead` and `sDirRoleSpouse`, which is exactly what
     * `Family::getAdults()` reads. Asking the model means a church that renamed
     * or renumbered its roles gets the same answer here as everywhere else.
     */
    public static function isAdultOf(Family $family, Person $person): bool
    {
        foreach ($family->getAdults() as $adult) {
            if ((int) $adult->getId() === (int) $person->getId()) {
                return true;
            }
        }

        return false;
    }

    // -- Writes --------------------------------------------------------------

    /**
     * Apply the allow-listed person fields present in `$body` to the acting
     * member, write the timeline note, and hand back the refreshed profile.
     *
     * @param array<string, mixed> $body
     *
     * @return array{profile: array<string, mixed>, updated: array<int, string>}
     *
     * @throws PortalSelfServiceException when the model refuses the result
     */
    public static function updateProfile(Person $actor, array $body): array
    {
        $changed = [];

        foreach (self::PERSON_FIELDS as $field => [$getter, $setter]) {
            if (!array_key_exists($field, $body)) {
                continue;
            }
            $value = self::asString($body[$field]);
            if ($value === self::asString($actor->{$getter}())) {
                continue;
            }
            $actor->{$setter}($value);
            $changed[] = $field;
        }

        if (array_key_exists('birthday', $body) && PortalSettings::allowsBirthdayEdit()) {
            if (self::applyBirthday($actor, self::asString($body['birthday']))) {
                $changed[] = 'birthday';
            }
        }

        if ($changed === []) {
            return ['profile' => self::getProfile($actor), 'updated' => []];
        }

        if (!$actor->validate()) {
            throw new PortalSelfServiceException(
                gettext('Some of your details could not be saved. Please check the highlighted fields.'),
                ORMUtils::getValidationErrors($actor->getValidationFailures())
            );
        }

        $actor->setDateLastEdited(new DateTime());
        $actor->setEditedBy((int) $actor->getId());
        // The model's own "Updated" note would say nothing about where the edit
        // came from; the portal writes a more specific one in its place.
        $actor->saveWithoutUpdateNote();
        self::writeEditNote($actor, $actor, null, $changed, self::personFieldLabels());

        return ['profile' => self::getProfile($actor), 'updated' => $changed];
    }

    /**
     * Apply the allow-listed family fields present in `$body`.
     *
     * @param array<string, mixed> $body
     *
     * @return array{family: array<string, mixed>, updated: array<int, string>}
     *
     * @throws PortalSelfServiceException when the actor is not an adult of the
     *                                    family, or the model refuses the result
     */
    public static function updateFamily(Person $actor, array $body): array
    {
        $family = self::requireEditableFamily($actor);
        $changed = [];

        foreach (self::FAMILY_FIELDS as $field => [$getter, $setter]) {
            if (!array_key_exists($field, $body)) {
                continue;
            }
            $value = self::asString($body[$field]);
            if ($value === self::asString($family->{$getter}())) {
                continue;
            }
            $family->{$setter}($value);
            $changed[] = $field;
        }

        if (array_key_exists('weddingDate', $body) && self::applyWeddingDate($family, self::asString($body['weddingDate']))) {
            $changed[] = 'weddingDate';
        }

        if ($changed === []) {
            return ['family' => self::getFamilyView($actor)['family'], 'updated' => []];
        }

        if (!$family->validate()) {
            throw new PortalSelfServiceException(
                gettext('Some of your family details could not be saved. Please check the highlighted fields.'),
                ORMUtils::getValidationErrors($family->getValidationFailures())
            );
        }

        $family->setDateLastEdited(new DateTime());
        $family->setEditedBy((int) $actor->getId());
        $family->saveWithoutUpdateNote();
        self::writeEditNote($actor, null, $family, $changed, self::familyFieldLabels());

        return ['family' => self::getFamilyView($actor)['family'], 'updated' => $changed];
    }

    /**
     * Record that the member has looked over their family's details.
     *
     * The note is the one `/external/verify` writes — same type, same author,
     * same text — so the People → Verify dashboard, which selects on
     * `EnteredBy = Person::SELF_VERIFY`, lists a portal confirmation exactly as
     * it lists a token-link one. That is what "appears on the admin side as
     * today" means, and it is why the confirming member's own id is not used.
     *
     * @throws PortalSelfServiceException when the member has no family
     */
    public static function confirmFamily(Person $actor, string $result, string $comment): void
    {
        $family = $actor->getFamily();
        if ($family === null) {
            throw new PortalSelfServiceException(gettext('You are not part of a family record yet.'), [], 404);
        }

        if ($result !== self::CONFIRM_NO_CHANGE && $result !== self::CONFIRM_CHANGE_NEEDED) {
            throw new PortalSelfServiceException(gettext('Please choose whether your details need changing.'));
        }

        $comment = trim($comment);
        if ($result === self::CONFIRM_CHANGE_NEEDED && $comment === '') {
            throw new PortalSelfServiceException(gettext('Please tell us what needs changing.'));
        }

        $note = new Note();
        $note->setFamily($family);
        $note->setType(self::VERIFY_NOTE_TYPE);
        $note->setEntered(Person::SELF_VERIFY);
        $note->setText(gettext('No Changes'));
        if ($result === self::CONFIRM_CHANGE_NEEDED) {
            $note->setText(InputUtils::escapeHTML($comment));
        }
        $note->save();
    }

    /**
     * Add somebody to the member's family as a *pending* self-registration.
     *
     * The new row is a `Person` in the member's family carrying
     * `Person::SELF_REGISTER`, which is the same marker the public
     * registration form leaves, so the entry turns up on People → Self
     * Registrations for staff to review. It is never a live member added by a
     * member: staff decide.
     *
     * @param array<string, mixed> $body
     *
     * @throws PortalSelfServiceException
     */
    public static function addFamilyMember(Person $actor, array $body): Person
    {
        $family = self::requireEditableFamily($actor);

        $person = new Person();
        $person->setFirstName(self::asString($body['firstName'] ?? ''));
        $person->setLastName(self::asString($body['lastName'] ?? ''));
        $person->setFamily($family);
        $person->setFmrId(self::resolveRoleId($body['role'] ?? null));
        $person->setEnteredBy(Person::SELF_REGISTER);
        $person->setDateEntered(new DateTime());

        $birthday = self::asString($body['birthday'] ?? '');
        if ($birthday !== '') {
            self::applyBirthday($person, $birthday);
        }

        if (!$person->validate()) {
            throw new PortalSelfServiceException(
                gettext('That family member could not be added. Please check the highlighted fields.'),
                ORMUtils::getValidationErrors($person->getValidationFailures())
            );
        }

        $person->save();

        $note = new Note();
        $note->setFamily($family);
        $note->setType(self::PORTAL_NOTE_TYPE);
        $note->setEntered((int) $actor->getId());
        $note->setText(sprintf(
            gettext('Added through the Member Portal and waiting for review: %s'),
            $person->getFullName()
        ));
        $note->save();

        return $person;
    }

    /**
     * Replace the acting member's own photo. The avatar endpoint this
     * delegates to is behind `EditRecords`, which a self-service login never
     * has, so the portal calls the model the same way that route does.
     *
     * @throws PortalSelfServiceException
     */
    public static function updatePhoto(Person $actor, string $imageBase64): void
    {
        if (trim($imageBase64) === '') {
            throw new PortalSelfServiceException(gettext('No photo was sent.'));
        }

        // setImageFromBase64() writes its own 'photo' timeline note and stamps
        // the person as edited, so nothing further is needed here.
        $actor->setImageFromBase64($imageBase64);
    }

    // -- Internals -----------------------------------------------------------

    /**
     * The acting member's family, or a refusal the route turns into 403/404.
     *
     * @throws PortalSelfServiceException
     */
    private static function requireEditableFamily(Person $actor): Family
    {
        $family = $actor->getFamily();
        if ($family === null) {
            throw new PortalSelfServiceException(gettext('You are not part of a family record yet.'), [], 404);
        }

        if (!self::isAdultOf($family, $actor)) {
            throw new PortalSelfServiceException(
                gettext('Only an adult of your family can change these details. Please contact the church office.'),
                [],
                403
            );
        }

        return $family;
    }

    /**
     * Write the timeline note that tells staff a member made this change and
     * which fields it touched.
     *
     * @param array<int, string>    $changed
     * @param array<string, string> $labels
     */
    private static function writeEditNote(
        Person $actor,
        ?Person $person,
        ?Family $family,
        array $changed,
        array $labels
    ): void {
        $names = [];
        foreach ($changed as $field) {
            $names[] = $labels[$field] ?? $field;
        }

        $note = new Note();
        if ($person !== null) {
            $note->setPerson($person);
        }
        if ($family !== null) {
            $note->setFamily($family);
        }
        $note->setType(self::PORTAL_NOTE_TYPE);
        $note->setEntered((int) $actor->getId());
        $note->setText(sprintf(gettext('Edited via the Member Portal: %s'), implode(', ', $names)));
        $note->save();
    }

    /**
     * @return array<string, string>
     */
    private static function personFieldLabels(): array
    {
        return [
            'title' => gettext('Title'),
            'firstName' => gettext('First Name'),
            'middleName' => gettext('Middle Name'),
            'lastName' => gettext('Last Name'),
            'suffix' => gettext('Suffix'),
            'email' => gettext('Email'),
            'workEmail' => gettext('Work Email'),
            'homePhone' => gettext('Home Phone'),
            'cellPhone' => gettext('Mobile Phone'),
            'workPhone' => gettext('Work Phone'),
            'birthday' => gettext('Birthday'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function familyFieldLabels(): array
    {
        return [
            'address1' => gettext('Address'),
            'address2' => gettext('Address Line 2'),
            'city' => gettext('City'),
            'state' => gettext('State'),
            'zip' => gettext('Zip'),
            'country' => gettext('Country'),
            'homePhone' => gettext('Home Phone'),
            'email' => gettext('Email'),
            'weddingDate' => gettext('Wedding Date'),
        ];
    }

    /**
     * Set or clear the three birthday columns from an ISO `YYYY-MM-DD` value —
     * the format `<input type="date">` submits, which is unambiguous in every
     * locale.
     *
     * @throws PortalSelfServiceException
     */
    private static function applyBirthday(Person $person, string $value): bool
    {
        if ($value === '') {
            if ($person->getBirthYear() === null && $person->getBirthMonth() === null) {
                return false;
            }
            $person->setBirthDay(null);
            $person->setBirthMonth(null);
            $person->setBirthYear(null);

            return true;
        }

        $date = self::parseIsoDate($value);
        if ($date === null) {
            throw new PortalSelfServiceException(gettext('That birthday is not a valid date.'));
        }
        if ($date > new DateTimeImmutable('today')) {
            throw new PortalSelfServiceException(gettext('A birthday cannot be in the future.'));
        }

        if (self::formatBirthday($person) === $date->format('Y-m-d')) {
            return false;
        }

        $person->setBirthDay((int) $date->format('d'));
        $person->setBirthMonth((int) $date->format('m'));
        $person->setBirthYear((int) $date->format('Y'));

        return true;
    }

    /**
     * @throws PortalSelfServiceException
     */
    private static function applyWeddingDate(Family $family, string $value): bool
    {
        $current = (string) ($family->getWeddingdate('Y-m-d') ?? '');

        if ($value === '') {
            if ($current === '') {
                return false;
            }
            $family->setWeddingdate(null);

            return true;
        }

        $date = self::parseIsoDate($value);
        if ($date === null) {
            throw new PortalSelfServiceException(gettext('That wedding date is not a valid date.'));
        }
        if ($date > new DateTimeImmutable('today')) {
            throw new PortalSelfServiceException(gettext('A wedding date cannot be in the future.'));
        }
        if ($current === $date->format('Y-m-d')) {
            return false;
        }

        $family->setWeddingdate($date->format('Y-m-d'));

        return true;
    }

    /**
     * A strict `YYYY-MM-DD` parse: `2026-02-31` is rejected rather than rolled
     * over into March, which is what `DateTime` would do on its own.
     */
    private static function parseIsoDate(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return ($date !== false && $date->format('Y-m-d') === $value) ? $date : null;
    }

    private static function formatBirthday(Person $person): string
    {
        $birthDate = $person->getBirthDate();

        return $birthDate instanceof DateTimeImmutable ? $birthDate->format('Y-m-d') : '';
    }

    /**
     * The photo endpoint for a person, cache-busted by the file's own
     * modification time so a fresh upload is visible immediately.
     *
     * Empty when nobody has uploaded a photo: the endpoint 404s in that case,
     * and a template that is told there is no URL renders initials instead of
     * a broken image.
     */
    private static function getPhotoUrl(Person $person): string
    {
        $photo = new Photo('Person', (int) $person->getId());
        if (!$photo->hasUploadedPhoto()) {
            return '';
        }

        $url = SystemURLs::getRootPath() . '/api/person/' . (int) $person->getId() . '/photo';
        $version = $photo->getPhotoModifiedTime();

        return $version ? $url . '?v=' . $version : $url;
    }

    /**
     * The role a proposed family member starts on: the configured "child"
     * role. The first role in the list would be head of household, which is
     * the wrong default for somebody an existing household is adding.
     */
    public static function getDefaultNewMemberRoleId(): int
    {
        $childRoles = explode(',', (string) SystemConfig::getValue('sDirRoleChild'));

        return (int) ($childRoles[0] ?? 0);
    }

    /**
     * The submitted family role, kept only when it is one this installation
     * actually offers. Anything else falls back to the default above, because
     * staff review the entry before it becomes a member anyway.
     */
    private static function resolveRoleId(mixed $role): int
    {
        $roleId = (int) $role;
        foreach (self::getFamilyRoles() as $option) {
            if ($option['id'] === $roleId) {
                return $roleId;
            }
        }

        return self::getDefaultNewMemberRoleId();
    }

    /**
     * The two letters a member's avatar falls back to when there is no photo.
     */
    private static function getInitials(Person $person): string
    {
        return mb_strtoupper(
            mb_substr((string) $person->getFirstName(), 0, 1) . mb_substr((string) $person->getLastName(), 0, 1)
        );
    }

    private static function asString(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }
        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return '';
    }
}
