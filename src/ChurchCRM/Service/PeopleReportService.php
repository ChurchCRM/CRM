<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\Classification;
use ChurchCRM\model\ChurchCRM\EventAttendQuery;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\Map\FamilyTableMap;
use ChurchCRM\model\ChurchCRM\Map\PersonTableMap;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PersonVolunteerOpportunityQuery;
use ChurchCRM\model\ChurchCRM\PropertyQuery;
use ChurchCRM\model\ChurchCRM\RecordPropertyQuery;
use ChurchCRM\model\ChurchCRM\VolunteerOpportunityQuery;
use ChurchCRM\Utils\DateTimeUtils;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * The people reports that used to be the predefined Data & Reports queries
 * (query_qry 9, 18, 22, 25, 26, 100, 201, 300, 301), rebuilt on Propel.
 *
 * Every report takes an optional classification filter (list_lst 1 plus
 * 0 = Unassigned; empty means all) and the parameters its query had.
 * Parameter definitions drive both the filter form and the validation of
 * the query string, so the form and the runner cannot drift apart.
 */
class PeopleReportService
{
    public const UNASSIGNED_CLASSIFICATION = 0;

    private const FAMILY_ROLE_HEAD = 1;
    private const FAMILY_ROLE_SPOUSE = 2;

    /**
     * @return array<string, array{name: string, description: string, params: array<string, array<string, mixed>>, columns: array<string, string>}>
     */
    public function getReports(): array
    {
        $month = ['type' => 'month', 'label' => gettext('Month')];
        $classification = ['type' => 'classification', 'label' => gettext('Classification')];

        return [
            'person-by-property' => [
                'name' => gettext('Person by Property'),
                'description' => gettext('Returns person records which are assigned the given property.'),
                'params' => [
                    'property' => ['type' => 'property', 'label' => gettext('Property'), 'required' => true],
                    'classification' => $classification,
                ],
                'columns' => ['Name' => gettext('Name'), 'Value' => gettext('Value')],
            ],
            'birthdays' => [
                'name' => gettext('Birthdays'),
                'description' => gettext('People with birthdays in a particular month'),
                'params' => ['month' => $month, 'classification' => $classification],
                'columns' => ['Day' => gettext('Day'), 'Name' => gettext('Name')],
            ],
            'membership-anniversaries' => [
                'name' => gettext('Membership Anniversaries'),
                'description' => gettext('People who joined in a particular month'),
                'params' => ['month' => $month, 'classification' => $classification],
                'columns' => ['Day' => gettext('Day'), 'Date' => gettext('Date'), 'Name' => gettext('Name')],
            ],
            'volunteers' => [
                'name' => gettext('Volunteers'),
                'description' => gettext('Find volunteers for a particular opportunity'),
                'params' => [
                    'opportunity' => ['type' => 'opportunity', 'label' => gettext('Volunteer Opportunity'), 'required' => true],
                    'classification' => $classification,
                ],
                'columns' => ['Name' => gettext('Name')],
            ],
            'recent-friends' => [
                'name' => gettext('Recent Friends'),
                'description' => gettext('Friends who signed up in previous months'),
                'params' => [
                    'months' => ['type' => 'number', 'label' => gettext('Months'), 'help' => gettext('Number of months since becoming a friend'), 'default' => 1, 'min' => 1, 'max' => 24],
                    'classification' => $classification,
                ],
                'columns' => ['Name' => gettext('Name')],
            ],
            'volunteers-two-opportunities' => [
                'name' => gettext('Volunteers for Two Opportunities'),
                'description' => gettext('Find volunteers who match two specific opportunity codes'),
                'params' => [
                    'opportunity1' => ['type' => 'opportunity', 'label' => gettext('First Volunteer Opportunity'), 'required' => true],
                    'opportunity2' => ['type' => 'opportunity', 'label' => gettext('Second Volunteer Opportunity'), 'required' => true],
                    'classification' => $classification,
                ],
                'columns' => ['Name' => gettext('Name')],
            ],
            'missing-people' => [
                'name' => gettext('Missing People'),
                'description' => gettext('Find people who did not attend an event'),
                'params' => [
                    'events' => ['type' => 'events', 'label' => gettext('Events'), 'required' => true],
                    'classification' => $classification,
                ],
                'columns' => ['Name' => gettext('Name'), 'LastName' => gettext('Last Name')],
            ],
            'wedding-anniversaries' => [
                'name' => gettext('Wedding Anniversaries'),
                'description' => gettext('People with wedding anniversaries in a particular month'),
                'params' => ['month' => $month, 'classification' => $classification],
                'columns' => ['Day' => gettext('Day'), 'Date' => gettext('Date'), 'Name' => gettext('Name')],
            ],
            'birthdays-anniversaries' => [
                'name' => gettext('Birthdays & Anniversaries'),
                'description' => gettext('People with birthdays or wedding anniversaries in a particular month'),
                'params' => ['month' => $month, 'classification' => $classification],
                'columns' => ['Type' => gettext('Type'), 'Day' => gettext('Day'), 'Name' => gettext('Name')],
            ],
        ];
    }

    public function getReport(string $slug): ?array
    {
        return $this->getReports()[$slug] ?? null;
    }

    /**
     * @return array<int, string> month number => localized name
     */
    public function getMonthNames(): array
    {
        return [
            1 => gettext('January'), 2 => gettext('February'), 3 => gettext('March'),
            4 => gettext('April'), 5 => gettext('May'), 6 => gettext('June'),
            7 => gettext('July'), 8 => gettext('August'), 9 => gettext('September'),
            10 => gettext('October'), 11 => gettext('November'), 12 => gettext('December'),
        ];
    }

    public function getNextMonth(): int
    {
        return DateTimeUtils::getCurrentMonth() % 12 + 1;
    }

    /**
     * @return array<int, string> classification id => name, Unassigned last
     */
    public function getClassificationOptions(): array
    {
        $options = [];
        foreach (Classification::getAll() as $classification) {
            $options[(int) $classification->getOptionId()] = $classification->getOptionName();
        }
        $options[self::UNASSIGNED_CLASSIFICATION] = gettext('Unassigned');

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public function getPropertyOptions(): array
    {
        $options = [];
        foreach (PropertyQuery::create()->filterByProClass('p')->orderByProName()->find() as $property) {
            $options[(int) $property->getProId()] = $property->getProName();
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public function getVolunteerOpportunityOptions(): array
    {
        $options = [];
        foreach (VolunteerOpportunityQuery::create()->orderByName()->find() as $opportunity) {
            $options[(int) $opportunity->getId()] = (string) $opportunity->getName();
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public function getEventOptions(): array
    {
        $options = [];
        foreach (EventQuery::create()->orderByStart(Criteria::DESC)->find() as $event) {
            $options[(int) $event->getId()] = $event->getTitle() . ' (' . $event->getStart('Y-m-d') . ')';
        }

        return $options;
    }

    /**
     * Options for a parameter type, so the form and the validator share one list.
     *
     * @return array<int, string>
     */
    public function getOptionsForType(string $type): array
    {
        return match ($type) {
            'month' => $this->getMonthNames(),
            'classification' => $this->getClassificationOptions(),
            'property' => $this->getPropertyOptions(),
            'opportunity' => $this->getVolunteerOpportunityOptions(),
            'events' => $this->getEventOptions(),
            default => [],
        };
    }

    /**
     * Normalize raw query-string input to typed parameter values.
     *
     * @param array<string, mixed> $input
     * @return array{values: array<string, mixed>, missing: string[]}
     */
    public function resolveParams(string $slug, array $input): array
    {
        $report = $this->getReport($slug);
        if ($report === null) {
            return ['values' => [], 'missing' => []];
        }

        $values = [];
        $missing = [];
        foreach ($report['params'] as $key => $param) {
            $raw = $input[$key] ?? null;
            switch ($param['type']) {
                case 'month':
                    $month = (int) ($raw ?? 0);
                    $values[$key] = ($month >= 1 && $month <= 12) ? $month : $this->getNextMonth();
                    break;
                case 'number':
                    $number = is_numeric($raw) ? (int) $raw : (int) $param['default'];
                    $values[$key] = max((int) $param['min'], min((int) $param['max'], $number));
                    break;
                case 'classification':
                case 'events':
                    $allowed = array_keys($this->getOptionsForType($param['type']));
                    $values[$key] = $this->filterIds($raw, $allowed);
                    if (!empty($param['required']) && $values[$key] === []) {
                        $missing[] = $key;
                    }
                    break;
                default:
                    $allowed = array_keys($this->getOptionsForType($param['type']));
                    $id = is_numeric($raw) ? (int) $raw : null;
                    $values[$key] = ($id !== null && in_array($id, $allowed, true)) ? $id : null;
                    if (!empty($param['required']) && $values[$key] === null) {
                        $missing[] = $key;
                    }
            }
        }

        return ['values' => $values, 'missing' => $missing];
    }

    /**
     * @param array<string, mixed> $values resolved by resolveParams()
     * @return array<int, array<string, mixed>> rows with Id, Name and the report's columns
     */
    public function run(string $slug, array $values): array
    {
        $classifications = $values['classification'] ?? [];

        return match ($slug) {
            'person-by-property' => $this->personByProperty((int) $values['property'], $classifications),
            'birthdays' => $this->birthdays((int) $values['month'], $classifications),
            'membership-anniversaries' => $this->membershipAnniversaries((int) $values['month'], $classifications),
            'volunteers' => $this->volunteers([(int) $values['opportunity']], $classifications),
            'recent-friends' => $this->recentFriends((int) $values['months'], $classifications),
            'volunteers-two-opportunities' => $this->volunteers([(int) $values['opportunity1'], (int) $values['opportunity2']], $classifications),
            'missing-people' => $this->missingPeople($values['events'], $classifications),
            'wedding-anniversaries' => $this->weddingAnniversaries((int) $values['month'], $classifications),
            'birthdays-anniversaries' => $this->birthdaysAndAnniversaries((int) $values['month'], $classifications),
            default => [],
        };
    }

    /**
     * @param int[] $classifications
     */
    private function personByProperty(int $propertyId, array $classifications): array
    {
        $valuesByPerson = [];
        $assignments = RecordPropertyQuery::create()->filterByPropertyId($propertyId)->find();
        foreach ($assignments as $assignment) {
            $valuesByPerson[(int) $assignment->getRecordId()] = (string) $assignment->getPropertyValue();
        }
        if ($valuesByPerson === []) {
            return [];
        }

        $query = PersonQuery::create()->filterById(array_keys($valuesByPerson))->orderByLastName()->orderByFirstName();
        $rows = [];
        foreach ($this->applyClassification($query, $classifications)->find() as $person) {
            $rows[] = $this->row($person, ['Value' => $valuesByPerson[(int) $person->getId()]]);
        }

        return $rows;
    }

    private function birthdays(int $month, array $classifications): array
    {
        $query = PersonQuery::create()->filterByBirthMonth($month)->orderByBirthDay()->orderByLastName()->orderByFirstName();
        $rows = [];
        foreach ($this->applyClassification($query, $classifications)->find() as $person) {
            $rows[] = $this->row($person, ['Day' => (int) $person->getBirthDay()]);
        }

        return $rows;
    }

    private function membershipAnniversaries(int $month, array $classifications): array
    {
        $query = PersonQuery::create()
            ->filterByMembershipDate(null, Criteria::ISNOTNULL)
            ->where('MONTH(' . PersonTableMap::COL_PER_MEMBERSHIPDATE . ') = ?', $month, \PDO::PARAM_INT)
            ->orderByMembershipDate()
            ->orderByLastName();
        $rows = [];
        foreach ($this->applyClassification($query, $classifications)->find() as $person) {
            $date = $person->getMembershipDate();
            $rows[] = $this->row($person, [
                'Day' => (int) $date->format('j'),
                'Date' => $date->format('Y-m-d'),
            ]);
        }

        return $rows;
    }

    /**
     * People signed up for every one of the given opportunities.
     *
     * @param int[] $opportunityIds
     */
    private function volunteers(array $opportunityIds, array $classifications): array
    {
        $personIds = null;
        foreach ($opportunityIds as $opportunityId) {
            $ids = PersonVolunteerOpportunityQuery::create()
                ->filterByVolunteerOpportunityId($opportunityId)
                ->select('PersonId')
                ->find()
                ->toArray();
            $ids = array_map('intval', $ids);
            $personIds = $personIds === null ? $ids : array_intersect($personIds, $ids);
        }
        if (empty($personIds)) {
            return [];
        }

        $query = PersonQuery::create()->filterById(array_values($personIds))->orderByLastName()->orderByFirstName();

        return $this->rows($this->applyClassification($query, $classifications)->find());
    }

    private function recentFriends(int $months, array $classifications): array
    {
        $cutoff = DateTimeUtils::getToday()->modify('-' . $months . ' months');
        $query = PersonQuery::create()
            ->filterByFriendDate($cutoff, Criteria::GREATER_THAN)
            ->orderByMembershipDate()
            ->orderByLastName();

        return $this->rows($this->applyClassification($query, $classifications)->find());
    }

    /**
     * @param int[] $eventIds
     */
    private function missingPeople(array $eventIds, array $classifications): array
    {
        $attended = EventAttendQuery::create()
            ->filterByEventId($eventIds)
            ->select('PersonId')
            ->find()
            ->toArray();
        $attended = array_values(array_unique(array_map('intval', $attended)));

        $query = PersonQuery::create()->orderByLastName()->orderByFirstName();
        if ($attended !== []) {
            $query->filterById($attended, Criteria::NOT_IN);
        }

        $rows = [];
        foreach ($this->applyClassification($query, $classifications)->find() as $person) {
            $rows[] = $this->row($person, ['LastName' => $person->getLastName()]);
        }

        return $rows;
    }

    private function weddingAnniversaries(int $month, array $classifications): array
    {
        $rows = $this->anniversaryRows($month, $classifications);
        usort($rows, static fn (array $a, array $b): int => [$a['Day'], $a['SortName']] <=> [$b['Day'], $b['SortName']]);

        return array_map(static function (array $row): array {
            unset($row['SortName']);
            return $row;
        }, $rows);
    }

    private function birthdaysAndAnniversaries(int $month, array $classifications): array
    {
        $rows = [];
        $query = PersonQuery::create()->filterByBirthMonth($month)->filterByBirthDay(0, Criteria::GREATER_THAN);
        foreach ($this->applyClassification($query, $classifications)->find() as $person) {
            $rows[] = $this->row($person, ['Type' => gettext('Birthday'), 'Day' => (int) $person->getBirthDay(), 'SortName' => $person->getLastName() . ' ' . $person->getFirstName()]);
        }
        foreach ($this->anniversaryRows($month, $classifications) as $row) {
            $rows[] = ['Type' => gettext('Anniversary')] + $row;
        }
        usort($rows, static fn (array $a, array $b): int => [$a['Day'], $a['Type'], $a['SortName']] <=> [$b['Day'], $b['Type'], $b['SortName']]);

        return array_map(static function (array $row): array {
            unset($row['SortName']);
            return $row;
        }, $rows);
    }

    /**
     * Heads and spouses whose family wedding date falls in the month.
     */
    private function anniversaryRows(int $month, array $classifications): array
    {
        $query = PersonQuery::create()
            ->filterByFmrId([self::FAMILY_ROLE_HEAD, self::FAMILY_ROLE_SPOUSE])
            ->useFamilyQuery()
                ->filterByWeddingDate(null, Criteria::ISNOTNULL)
                ->where('MONTH(' . FamilyTableMap::COL_FAM_WEDDINGDATE . ') = ?', $month, \PDO::PARAM_INT)
            ->endUse()
            ->joinWith('Family');
        $rows = [];
        foreach ($this->applyClassification($query, $classifications)->find() as $person) {
            $date = $person->getFamily()->getWeddingDate();
            $rows[] = $this->row($person, [
                'Day' => (int) $date->format('j'),
                'Date' => $date->format('Y-m-d'),
                'SortName' => $person->getLastName() . ' ' . $person->getFirstName(),
            ]);
        }

        return $rows;
    }

    /**
     * @param int[] $classifications empty = no filter
     */
    private function applyClassification(PersonQuery $query, array $classifications): PersonQuery
    {
        if ($classifications !== []) {
            $query->filterByClsId($classifications, Criteria::IN);
        }

        return $query;
    }

    /**
     * @param iterable<Person> $people
     */
    private function rows(iterable $people): array
    {
        $rows = [];
        foreach ($people as $person) {
            $rows[] = $this->row($person);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $columns
     */
    private function row(Person $person, array $columns = []): array
    {
        return [
            'Id' => (int) $person->getId(),
            'FamilyId' => (int) $person->getFamId(),
            'Name' => $person->getFullName(),
        ] + $columns;
    }

    /**
     * @param mixed $raw query-string value, scalar or array
     * @param int[] $allowed
     * @return int[]
     */
    private function filterIds(mixed $raw, array $allowed): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        $ids = [];
        foreach ((array) $raw as $value) {
            if (is_numeric($value) && in_array((int) $value, $allowed, true)) {
                $ids[] = (int) $value;
            }
        }

        return array_values(array_unique($ids));
    }
}
