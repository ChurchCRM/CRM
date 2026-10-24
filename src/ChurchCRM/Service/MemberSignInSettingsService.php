<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Portal\Auth\MemberSignInRule;
use InvalidArgumentException;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * The settings on Admin → Member Portal → Sign-in (member sign-in design
 * §11.1). Every write of these keys goes through `normalize()`, whether it
 * comes from the Sign-in tab or the generic config API, so the rules here can't
 * be bypassed.
 */
final class MemberSignInSettingsService
{
    public const KEYS = [
        'bMemberSignIn',
        'sMemberSignInFamilyRoles',
        'sMemberSignInClassifications',
        'iMemberSignInMinAge',
        'iSignInLinkMinutes',
        'iMemberSignInRequestsPerIP',
        'iPortalIdleMinutes',
        'iRememberDeviceDays',
        'bPortalPasskeys',
        'sMemberSignInNotificationRecipientIDs',
        'bAllowLoginAsUser',
        'bLoginAsUserCanWrite',
    ];

    private const FAMILY_ROLE_LIST = 2;
    private const CLASSIFICATION_LIST = 1;

    /** @var array<string, array{0: int, 1: int}> */
    private const NUMBER_RANGES = [
        'iMemberSignInMinAge' => [0, 120],
        'iSignInLinkMinutes' => [1, 1440],
        'iMemberSignInRequestsPerIP' => [0, 10000],
        'iPortalIdleMinutes' => [1, 1440],
        'iRememberDeviceDays' => [0, 365],
    ];

    public static function owns(string $key): bool
    {
        return in_array($key, self::KEYS, true);
    }

    /**
     * The value to store for one setting.
     *
     * @throws InvalidArgumentException with a message for the administrator
     */
    public function normalize(string $key, mixed $value): string
    {
        return match ($key) {
            'bMemberSignIn' => $this->normalizeMemberSignIn($value),
            'bPortalPasskeys', 'bAllowLoginAsUser', 'bLoginAsUserCanWrite' => self::normalizeBoolean($value),
            'sMemberSignInFamilyRoles' => $this->normalizeListIds($value, self::FAMILY_ROLE_LIST, gettext('Pick family roles from the list.')),
            'sMemberSignInClassifications' => $this->normalizeListIds($value, self::CLASSIFICATION_LIST, gettext('Pick classifications from the list.')),
            'iMemberSignInMinAge', 'iSignInLinkMinutes', 'iMemberSignInRequestsPerIP', 'iPortalIdleMinutes', 'iRememberDeviceDays' => self::normalizeNumber($key, $value),
            'sMemberSignInNotificationRecipientIDs' => $this->normalizeOfficeList($value),
            default => throw new InvalidArgumentException(gettext('Configuration item not found')),
        };
    }

    /**
     * Validates every given setting first, then saves them all, or none.
     *
     * @param array<string, mixed> $input
     *
     * @return array<string, string> the problems by setting; empty when saved
     */
    public function save(array $input): array
    {
        $values = [];
        $errors = [];
        foreach (self::KEYS as $key) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            try {
                $values[$key] = $this->normalize($key, $input[$key]);
            } catch (InvalidArgumentException $e) {
                $errors[$key] = $e->getMessage();
            }
        }

        if ($errors === []) {
            foreach ($values as $key => $value) {
                SystemConfig::setValue($key, $value);
            }
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    public function values(): array
    {
        $values = [];
        foreach (self::KEYS as $key) {
            $values[$key] = (string) SystemConfig::getValue($key);
        }

        return $values;
    }

    /** How many people the saved settings let in, before the email rule. */
    public function maySignInCount(): int
    {
        return MemberSignInRule::fromSettings()->maySignInQuery()->count();
    }

    /** Why member sign-in can't be turned on, or null when it can. */
    public function emailProblem(): ?string
    {
        if (SystemConfig::isEmailEnabled()) {
            return null;
        }

        return gettext('Member sign-in sends each member a code by email, so it can only be turned on while ChurchCRM can send email. Turn on email and set up the mail server first.');
    }

    /** Why members won't be offered passkeys on this site, or null. */
    public function passkeyProblem(): ?string
    {
        $parts = parse_url((string) SystemURLs::getURL());
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return gettext('Passkeys need the site address to be set in Config.php.');
        }
        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false) {
            return gettext('Passkeys need a host name, not an IP address, in the site address.');
        }
        if (($parts['scheme'] ?? '') !== 'https' && $host !== 'localhost') {
            return gettext('Passkeys need the site to be served over HTTPS. Members are not offered passkeys until it is.');
        }

        return null;
    }

    /**
     * Family roles, plus "No family role" for people without one.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function familyRoleChoices(): array
    {
        $choices = [];
        foreach (ListOptionQuery::create()->filterById(self::FAMILY_ROLE_LIST)->orderByOptionSequence()->find() as $role) {
            $choices[] = ['id' => (int) $role->getOptionId(), 'name' => $role->getOptionName()];
        }
        $choices[] = ['id' => 0, 'name' => gettext('No family role')];

        return $choices;
    }

    /**
     * Classifications, plus Unassigned, each with the number of active people
     * in it: living, active, and in an active family or none.
     *
     * @return array<int, array{id: int, name: string, activeCount: int}>
     */
    public function classificationChoices(): array
    {
        $counts = [];
        $rows = PersonQuery::create()
            ->filterByDateDeceased(null, Criteria::ISNULL)
            ->filterByDateDeactivated(null, Criteria::ISNULL)
            ->useFamilyQuery(null, Criteria::LEFT_JOIN)
                ->filterByDateDeactivated(null, Criteria::ISNULL)
            ->endUse()
            ->groupByClsId()
            ->withColumn('COUNT(*)', 'ActiveCount')
            ->select(['ClsId', 'ActiveCount'])
            ->find();
        foreach ($rows as $row) {
            $counts[(int) $row['ClsId']] = (int) $row['ActiveCount'];
        }

        $choices = [];
        foreach (ListOptionQuery::create()->filterById(self::CLASSIFICATION_LIST)->orderByOptionSequence()->find() as $classification) {
            $id = (int) $classification->getOptionId();
            $choices[] = ['id' => $id, 'name' => $classification->getOptionName(), 'activeCount' => $counts[$id] ?? 0];
        }
        $choices[] = ['id' => 0, 'name' => gettext('Unassigned'), 'activeCount' => $counts[0] ?? 0];

        return $choices;
    }

    /**
     * The office list as the people picker shows it.
     *
     * @return array<int, array{id: int, text: string}>
     */
    public function officeRecipients(): array
    {
        $ids = MemberSignInRule::idList(SystemConfig::getValue('sMemberSignInNotificationRecipientIDs'));
        if ($ids === []) {
            return [];
        }

        $people = [];
        foreach (PersonQuery::create()->filterById($ids, Criteria::IN)->find() as $person) {
            $people[(int) $person->getId()] = ['id' => (int) $person->getId(), 'text' => self::pickerText($person->getFullName(), $person->getEmail())];
        }

        return array_values(array_filter(array_map(static fn (int $id): ?array => $people[$id] ?? null, $ids)));
    }

    /**
     * People who can be added to the office list: living, with a personal email.
     * Same result shape as GET /api/persons/search/{query}.
     *
     * @return array<int, array{id: int, objid: int, text: string}>
     */
    public function searchOfficeCandidates(string $query): array
    {
        $like = '%' . trim($query) . '%';
        $results = [];
        $people = PersonQuery::create()
            ->filterByDateDeceased(null, Criteria::ISNULL)
            ->filterByEmail('', Criteria::NOT_EQUAL)
            ->condition('first', 'Person.FirstName LIKE ?', $like)
            ->condition('last', 'Person.LastName LIKE ?', $like)
            ->condition('email', 'Person.Email LIKE ?', $like)
            ->where(['first', 'last', 'email'], Criteria::LOGICAL_OR)
            ->orderByLastName()
            ->orderByFirstName()
            ->limit(15)
            ->find();
        foreach ($people as $index => $person) {
            $results[] = ['id' => $index + 1, 'objid' => (int) $person->getId(), 'text' => self::pickerText($person->getFullName(), $person->getEmail())];
        }

        return $results;
    }

    private static function pickerText(string $name, ?string $email): string
    {
        return $email === null || trim($email) === '' ? $name : sprintf('%s (%s)', $name, trim($email));
    }

    private function normalizeMemberSignIn(mixed $value): string
    {
        $normalized = self::normalizeBoolean($value);
        if ($normalized === '1' && !SystemConfig::getBooleanValue('bMemberSignIn') && $this->emailProblem() !== null) {
            throw new InvalidArgumentException((string) $this->emailProblem());
        }

        return $normalized;
    }

    private static function normalizeBoolean(mixed $value): string
    {
        $flag = is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($flag === null) {
            throw new InvalidArgumentException(gettext('Choose yes or no.'));
        }

        return $flag ? '1' : '0';
    }

    private static function normalizeNumber(string $key, mixed $value): string
    {
        [$min, $max] = self::NUMBER_RANGES[$key];
        $text = is_int($value) ? (string) $value : trim((string) (is_scalar($value) ? $value : ''));
        if (!preg_match('/^\d+$/', $text) || (int) $text < $min || (int) $text > $max) {
            throw new InvalidArgumentException(sprintf(gettext('Enter a whole number from %1$d to %2$d.'), $min, $max));
        }

        return (string) (int) $text;
    }

    /**
     * @return int[]
     */
    private static function idsFrom(mixed $value, string $message): array
    {
        if (is_string($value)) {
            $value = trim($value) === '' ? [] : explode(',', $value);
        }
        if (!is_array($value)) {
            throw new InvalidArgumentException($message);
        }

        $ids = [];
        foreach ($value as $item) {
            $text = trim((string) (is_scalar($item) ? $item : ''));
            if (!preg_match('/^\d+$/', $text)) {
                throw new InvalidArgumentException($message);
            }
            $ids[] = (int) $text;
        }

        return array_values(array_unique($ids));
    }

    private function normalizeListIds(mixed $value, int $listId, string $message): string
    {
        $ids = self::idsFrom($value, $message);
        $known = ListOptionQuery::create()->filterById($listId)->select('OptionId')->find()->toArray();
        $known = array_map('intval', $known);
        $known[] = 0;
        foreach ($ids as $id) {
            if (!in_array($id, $known, true)) {
                throw new InvalidArgumentException($message);
            }
        }
        sort($ids);

        return implode(',', $ids);
    }

    private function normalizeOfficeList(mixed $value): string
    {
        $ids = self::idsFrom($value, gettext('Pick people for the office list.'));
        if ($ids === []) {
            return '';
        }

        $withEmail = PersonQuery::create()
            ->filterById($ids, Criteria::IN)
            ->filterByDateDeceased(null, Criteria::ISNULL)
            ->filterByEmail('', Criteria::NOT_EQUAL)
            ->select('Id')
            ->find()
            ->toArray();
        if (count($withEmail) !== count($ids)) {
            throw new InvalidArgumentException(gettext('Only living people with their own email can be on the office list.'));
        }

        return implode(',', $ids);
    }
}
