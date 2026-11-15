<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Exceptions\ImportantDateRefusedException;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\FamilyImportantDate;
use ChurchCRM\model\ChurchCRM\FamilyImportantDateQuery;
use ChurchCRM\model\ChurchCRM\ImportantDateType;
use ChurchCRM\model\ChurchCRM\ImportantDateTypeQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonImportantDate;
use ChurchCRM\model\ChurchCRM\PersonImportantDateQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Utils\AnnualDate;
use ChurchCRM\Utils\DateTimeUtils;

/**
 * Church-defined yearly dates on people and families, and the types they are
 * recorded under. Recorded dates are kept exactly as entered; when each one is
 * observed follows AnnualDate (configured timezone, February 29 rule).
 */
class ImportantDateService
{
    private const MIN_YEAR = 1800;

    /**
     * Suggested types a church can add in one step: key => [label, applies to].
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function presets(): array
    {
        return [
            'baptism'            => [gettext('Baptism'), ImportantDateType::APPLIES_TO_PERSON],
            'dedication'         => [gettext('Dedication'), ImportantDateType::APPLIES_TO_PERSON],
            'confirmation'       => [gettext('Confirmation'), ImportantDateType::APPLIES_TO_PERSON],
            'first-communion'    => [gettext('First Communion'), ImportantDateType::APPLIES_TO_PERSON],
            'spiritual-birthday' => [gettext('Spiritual Birthday'), ImportantDateType::APPLIES_TO_PERSON],
            'ordination'         => [gettext('Ordination'), ImportantDateType::APPLIES_TO_PERSON],
            'house-blessing'     => [gettext('House Blessing'), ImportantDateType::APPLIES_TO_FAMILY],
        ];
    }

    // ── Types ────────────────────────────────────────────────────────────────

    /** @return list<array<string, mixed>> every type, with how many records use it */
    public function typeSummaries(): array
    {
        $personCounts = $this->usageCounts(PersonImportantDateQuery::create());
        $familyCounts = $this->usageCounts(FamilyImportantDateQuery::create());

        $out = [];
        foreach (ImportantDateTypeQuery::create()->orderForDisplay()->find() as $type) {
            $id = (int) $type->getId();
            $out[] = $this->typeToArray($type) + ['usage' => $personCounts[$id] ?? $familyCounts[$id] ?? 0];
        }

        return $out;
    }

    /** @return list<array{key: string, name: string, appliesTo: string}> presets not yet added */
    public function availablePresets(): array
    {
        $taken = ImportantDateTypeQuery::create()->select(['Key'])->find()->getData();
        $out = [];
        foreach (self::presets() as $key => [$name, $appliesTo]) {
            if (!in_array($key, $taken, true)) {
                $out[] = ['key' => $key, 'name' => $name, 'appliesTo' => $appliesTo];
            }
        }

        return $out;
    }

    /** @return list<ImportantDateType> active types that can be recorded on a person or a family */
    public function activeTypesFor(string $appliesTo): array
    {
        return iterator_to_array(ImportantDateTypeQuery::create()
            ->filterByAppliesTo($appliesTo)
            ->filterByActive(true)
            ->orderForDisplay()
            ->find(), false);
    }

    public function createType(string $name, string $appliesTo, bool $allowMultiple = false, bool $showOnCalendar = true, ?string $presetKey = null): ImportantDateType
    {
        $presets = self::presets();
        if ($presetKey !== null && $presetKey !== '') {
            if (!isset($presets[$presetKey])) {
                throw new ImportantDateRefusedException(gettext('Unknown suggested date type'));
            }
            [$name, $appliesTo] = $presets[$presetKey];
        }

        $name = $this->validName($name);
        if (!in_array($appliesTo, [ImportantDateType::APPLIES_TO_PERSON, ImportantDateType::APPLIES_TO_FAMILY], true)) {
            throw new ImportantDateRefusedException(gettext('A date type applies to people or to families'));
        }
        $this->assertNameFree($name, $appliesTo);

        $type = new ImportantDateType();
        $type->setKey($this->uniqueKey($presetKey ?: $name));
        $type->setName($name);
        $type->setAppliesTo($appliesTo);
        $type->setAllowMultiple($allowMultiple);
        $type->setShowOnCalendar($showOnCalendar);
        $type->setActive(true);
        $type->setSortOrder((int) ImportantDateTypeQuery::create()->count());
        $type->save();

        return $type;
    }

    /** @param array{name?: string, allowMultiple?: bool, showOnCalendar?: bool, active?: bool, sortOrder?: int} $changes */
    public function updateType(ImportantDateType $type, array $changes): ImportantDateType
    {
        if (array_key_exists('name', $changes)) {
            $name = $this->validName((string) $changes['name']);
            if (mb_strtolower($name) !== mb_strtolower((string) $type->getName())) {
                $this->assertNameFree($name, (string) $type->getAppliesTo());
            }
            $type->setName($name);
        }
        if (array_key_exists('allowMultiple', $changes)) {
            $allow = (bool) $changes['allowMultiple'];
            if (!$allow && $type->getAllowMultiple() && $this->hasOwnerWithSeveral($type)) {
                throw new ImportantDateRefusedException(gettext('Some records have more than one date of this type. Remove the extra dates before allowing only one.'), true);
            }
            $type->setAllowMultiple($allow);
        }
        if (array_key_exists('showOnCalendar', $changes)) {
            $type->setShowOnCalendar((bool) $changes['showOnCalendar']);
        }
        if (array_key_exists('active', $changes)) {
            $type->setActive((bool) $changes['active']);
        }
        if (array_key_exists('sortOrder', $changes)) {
            $type->setSortOrder(max(0, (int) $changes['sortOrder']));
        }
        $type->save();

        return $type;
    }

    public function deleteType(ImportantDateType $type): void
    {
        if ($this->usage($type) > 0) {
            throw new ImportantDateRefusedException(gettext('Dates are recorded under this type. Turn it off instead, so the recorded dates are kept.'), true);
        }
        $type->delete();
    }

    public function usage(ImportantDateType $type): int
    {
        return $type->appliesToPerson()
            ? PersonImportantDateQuery::create()->filterByTypeId($type->getId())->count()
            : FamilyImportantDateQuery::create()->filterByTypeId($type->getId())->count();
    }

    /** @return array<string, mixed> */
    public function typeToArray(ImportantDateType $type): array
    {
        return [
            'id'             => (int) $type->getId(),
            'key'            => (string) $type->getKey(),
            'name'           => (string) $type->getName(),
            'appliesTo'      => (string) $type->getAppliesTo(),
            'allowMultiple'  => (bool) $type->getAllowMultiple(),
            'showOnCalendar' => (bool) $type->getShowOnCalendar(),
            'active'         => (bool) $type->getActive(),
            'sortOrder'      => (int) $type->getSortOrder(),
        ];
    }

    // ── Dates on a person or a family ───────────────────────────────────────

    /**
     * Every yearly date on the person, recorded ones and church-defined ones,
     * soonest first.
     *
     * @return list<array<string, mixed>>
     */
    public function datesForPerson(Person $person, ?\DateTimeInterface $today = null): array
    {
        $today ??= DateTimeUtils::getStartOfToday();
        $living = !$person->isDeceased();
        $editUrl = '/PersonEditor.php?PersonID=' . $person->getId();
        $rows = [];

        if ($person->getBirthMonth() && $person->getBirthDay()) {
            $year = $person->hideAge() ? null : (int) $person->getBirthYear();
            $rows[] = $this->row('birthday', gettext('Birthday'), new AnnualDate((int) $person->getBirthMonth(), (int) $person->getBirthDay(), $year), $today, $living ? 'turning' : null, $editUrl);
        }
        if ($person->getMembershipDate() !== null) {
            $rows[] = $this->row('membership', gettext('Membership'), AnnualDate::fromDate($person->getMembershipDate()), $today, 'years', $editUrl);
        }
        if ($person->getFriendDate() !== null && !SystemConfig::getBooleanValue('bHideFriendDate')) {
            $rows[] = $this->row('friend', gettext('Friend'), AnnualDate::fromDate($person->getFriendDate()), $today, 'years', $editUrl);
        }
        if ($person->getDateDeceased() !== null) {
            $rows[] = $this->row('death', gettext('Date of Death'), AnnualDate::fromDate($person->getDateDeceased()), $today, 'years', $editUrl);
        }

        $records = PersonImportantDateQuery::create()->filterByPersonId($person->getId())->joinWithType()->find();
        foreach ($records as $record) {
            $rows[] = $this->recordRow($record, $record->getType(), $today);
        }

        return $this->sortSoonestFirst($rows);
    }

    /** @return list<array<string, mixed>> */
    public function datesForFamily(Family $family, ?\DateTimeInterface $today = null): array
    {
        $today ??= DateTimeUtils::getStartOfToday();
        $rows = [];

        if ($family->getWeddingdate() !== null && !SystemConfig::getBooleanValue('bHideWeddingDate')) {
            $rows[] = $this->row('wedding', gettext('Wedding Anniversary'), AnnualDate::fromDate($family->getWeddingdate()), $today, 'years', '/FamilyEditor.php?FamilyID=' . $family->getId());
        }

        $records = FamilyImportantDateQuery::create()->filterByFamilyId($family->getId())->joinWithType()->find();
        foreach ($records as $record) {
            $rows[] = $this->recordRow($record, $record->getType(), $today);
        }

        return $this->sortSoonestFirst($rows);
    }

    /** @param array{typeId?: mixed, month?: mixed, day?: mixed, year?: mixed, note?: mixed} $input */
    public function addPersonDate(Person $person, array $input, User $user): PersonImportantDate
    {
        $type = $this->typeForNewRecord($input['typeId'] ?? null, ImportantDateType::APPLIES_TO_PERSON);
        if (!$type->getAllowMultiple() && PersonImportantDateQuery::create()->filterByPersonId($person->getId())->filterByTypeId($type->getId())->exists()) {
            throw new ImportantDateRefusedException(sprintf(gettext('%s is already recorded for this person. Edit it instead.'), $type->getName()), true);
        }

        $record = new PersonImportantDate();
        $record->setPersonId($person->getId());
        $record->setTypeId($type->getId());
        $this->applyDate($record, $input);
        $record->setDateEntered(DateTimeUtils::getNowDateTime());
        $record->setEnteredBy($user->getId());
        $record->save();

        return $record;
    }

    /** @param array{typeId?: mixed, month?: mixed, day?: mixed, year?: mixed, note?: mixed} $input */
    public function addFamilyDate(Family $family, array $input, User $user): FamilyImportantDate
    {
        $type = $this->typeForNewRecord($input['typeId'] ?? null, ImportantDateType::APPLIES_TO_FAMILY);
        if (!$type->getAllowMultiple() && FamilyImportantDateQuery::create()->filterByFamilyId($family->getId())->filterByTypeId($type->getId())->exists()) {
            throw new ImportantDateRefusedException(sprintf(gettext('%s is already recorded for this family. Edit it instead.'), $type->getName()), true);
        }

        $record = new FamilyImportantDate();
        $record->setFamilyId($family->getId());
        $record->setTypeId($type->getId());
        $this->applyDate($record, $input);
        $record->setDateEntered(DateTimeUtils::getNowDateTime());
        $record->setEnteredBy($user->getId());
        $record->save();

        return $record;
    }

    /** The type stays as recorded; only the date and note change. */
    public function updateDate(PersonImportantDate|FamilyImportantDate $record, array $input, User $user): void
    {
        $this->applyDate($record, $input);
        $record->setDateLastEdited(DateTimeUtils::getNowDateTime());
        $record->setEditedBy($user->getId());
        $record->save();
    }

    public function removeDate(PersonImportantDate|FamilyImportantDate $record): void
    {
        $record->delete();
    }

    public function findPersonDate(Person $person, int $id): ?PersonImportantDate
    {
        return PersonImportantDateQuery::create()->filterByPersonId($person->getId())->findPk($id);
    }

    public function findFamilyDate(Family $family, int $id): ?FamilyImportantDate
    {
        return FamilyImportantDateQuery::create()->filterByFamilyId($family->getId())->findPk($id);
    }

    /**
     * Observed occurrences from $from to $to (inclusive) of dates whose type is
     * active and shown on the calendar, for living, active people and active families.
     *
     * @return list<array{record: PersonImportantDate|FamilyImportantDate, type: ImportantDateType, owner: Person|Family, date: \DateTimeImmutable, years: int|null}>
     */
    public function calendarOccurrences(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->occurrencesBetween($from, $to, true, []);
    }

    /**
     * Dates of active types observed from today through $days ahead, for living,
     * active people and active families, soonest first. $typeIds narrows to those
     * types; empty means every active type.
     *
     * @param list<int> $typeIds
     *
     * @return list<array{ownerKind: string, ownerName: string, ownerUrl: string, type: string, typeId: int, observed: string, observedLabel: string, recorded: string, daysUntil: int, relative: string, yearsLabel: string|null, note: string|null}>
     */
    public function upcoming(int $days, array $typeIds = [], ?\DateTimeInterface $today = null): array
    {
        $from = \DateTimeImmutable::createFromInterface($today ?? DateTimeUtils::getStartOfToday())->setTime(0, 0);
        $to = $from->modify('+' . max(0, $days) . ' days');

        $rows = [];
        foreach ($this->occurrencesBetween($from, $to, false, $typeIds) as $occurrence) {
            $record = $occurrence['record'];
            $owner = $occurrence['owner'];
            $daysUntil = (int) round(($occurrence['date']->setTime(12, 0)->getTimestamp() - $from->setTime(12, 0)->getTimestamp()) / 86400);
            $rows[] = [
                'ownerKind'  => $owner instanceof Person ? ImportantDateType::APPLIES_TO_PERSON : ImportantDateType::APPLIES_TO_FAMILY,
                'ownerName'  => $owner instanceof Person ? (string) $owner->getFullName() : (string) $owner->getSalutation(),
                'ownerUrl'   => (string) $owner->getViewURI(),
                'type'       => (string) $occurrence['type']->getName(),
                'typeId'     => (int) $occurrence['type']->getId(),
                'observed'   => $occurrence['date']->format('Y-m-d'),
                'observedLabel' => $occurrence['date']->format(SystemConfig::getValue('sDateFormatNoYear')),
                'recorded'   => $this->formatRecorded($record->getAnnualDate()),
                'daysUntil'  => $daysUntil,
                'relative'   => self::relativeLabel($daysUntil),
                'yearsLabel' => $this->yearsLabel($occurrence['years'], 'years'),
                'note'       => $record->getNote(),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => [$a['daysUntil'], $a['type'], $a['ownerName']] <=> [$b['daysUntil'], $b['type'], $b['ownerName']]);

        return $rows;
    }

    /**
     * @param list<int> $typeIds empty means every active type
     *
     * @return list<array{record: PersonImportantDate|FamilyImportantDate, type: ImportantDateType, owner: Person|Family, date: \DateTimeImmutable, years: int|null}>
     */
    private function occurrencesBetween(\DateTimeInterface $from, \DateTimeInterface $to, bool $calendarOnly, array $typeIds): array
    {
        $typeFilter = static function (ImportantDateTypeQuery $types) use ($calendarOnly): ImportantDateTypeQuery {
            $types->filterByActive(true);
            if ($calendarOnly) {
                $types->filterByShowOnCalendar(true);
            }

            return $types;
        };
        $personQuery = $typeFilter(PersonImportantDateQuery::create()->useTypeQuery())->endUse()
            ->usePersonQuery()->filterByLiving()->filterByActive()->endUse()
            ->with('Type')
            ->with('Person');
        $familyQuery = $typeFilter(FamilyImportantDateQuery::create()->useTypeQuery())->endUse()
            ->useFamilyQuery()->filterByActive()->endUse()
            ->with('Type')
            ->with('Family');
        if ($typeIds !== []) {
            $personQuery->filterByTypeId(array_map('intval', $typeIds));
            $familyQuery->filterByTypeId(array_map('intval', $typeIds));
        }

        $fromDay = $from->format('Y-m-d');
        $toDay = $to->format('Y-m-d');
        $out = [];
        foreach ([$personQuery->find(), $familyQuery->find()] as $records) {
            foreach ($records as $record) {
                $annual = $record->getAnnualDate();
                $owner = $record instanceof PersonImportantDate ? $record->getPerson() : $record->getFamily();
                for ($year = (int) $from->format('Y'); $year <= (int) $to->format('Y'); $year++) {
                    $date = $annual->occurrenceInYear($year);
                    if ($date === null || $date->format('Y-m-d') < $fromDay || $date->format('Y-m-d') > $toDay) {
                        continue;
                    }
                    if ($annual->getYear() !== null && $year < $annual->getYear()) {
                        continue;
                    }
                    $out[] = ['record' => $record, 'type' => $record->getType(), 'owner' => $owner, 'date' => $date, 'years' => $annual->yearsOnOccurrence($year)];
                }
            }
        }

        return $out;
    }

    /** Today / in N days / N days ago, from a signed day count. */
    public static function relativeLabel(int $days): string
    {
        if ($days === 0) {
            return gettext('Today');
        }
        if ($days > 0) {
            return sprintf(ngettext('in %d day', 'in %d days', $days), $days);
        }

        return sprintf(ngettext('%d day ago', '%d days ago', -$days), -$days);
    }

    // ── Internals ───────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function recordRow(PersonImportantDate|FamilyImportantDate $record, ImportantDateType $type, \DateTimeInterface $today): array
    {
        $row = $this->row('custom', (string) $type->getName(), $record->getAnnualDate(), $today, 'years', null);
        $row['id'] = (int) $record->getId();
        $row['typeId'] = (int) $type->getId();
        $row['note'] = $record->getNote();
        $row['typeActive'] = (bool) $type->getActive();

        return $row;
    }

    /** @return array<string, mixed> */
    private function row(string $source, string $label, AnnualDate $annual, \DateTimeInterface $today, ?string $yearsStyle, ?string $editUrl): array
    {
        $daysUntil = $annual->daysUntilNext($today);
        $years = null;
        if ($daysUntil !== null && $yearsStyle !== null) {
            $nextYear = (int) \DateTimeImmutable::createFromInterface($today)->modify("+$daysUntil days")->format('Y');
            $years = $annual->yearsOnOccurrence($nextYear);
        }

        return [
            'id'         => null,
            'source'     => $source,
            'typeId'     => null,
            'label'      => $label,
            'month'      => $annual->hasMonthDay() ? $annual->getMonth() : null,
            'day'        => $annual->hasMonthDay() ? $annual->getDay() : null,
            'year'       => $annual->getYear() ?: null,
            'recorded'   => $this->formatRecorded($annual),
            'daysUntil'  => $daysUntil,
            'relative'   => $daysUntil === null ? '' : self::relativeLabel($daysUntil),
            'yearsLabel' => $this->yearsLabel($years, $yearsStyle),
            'note'       => null,
            'editable'   => $source === 'custom',
            'editUrl'    => $editUrl,
        ];
    }

    private function yearsLabel(?int $years, ?string $style): ?string
    {
        if ($years === null || $years < 1) {
            return null;
        }

        return $style === 'turning'
            ? sprintf(gettext('turning %d'), $years)
            : sprintf(ngettext('%d year', '%d years', $years), $years);
    }

    private function formatRecorded(AnnualDate $annual): string
    {
        if (!$annual->hasMonthDay()) {
            return '';
        }
        $year = $annual->getYear() ?: null;
        $date = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year ?? 2000, $annual->getMonth(), $annual->getDay()));

        return $date->format(SystemConfig::getValue($year === null ? 'sDateFormatNoYear' : 'sDateFormatLong'));
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function sortSoonestFirst(array $rows): array
    {
        usort($rows, static fn (array $a, array $b): int => [$a['daysUntil'] ?? PHP_INT_MAX, $a['label']] <=> [$b['daysUntil'] ?? PHP_INT_MAX, $b['label']]);

        return $rows;
    }

    private function typeForNewRecord(mixed $typeId, string $appliesTo): ImportantDateType
    {
        $type = ImportantDateTypeQuery::create()->findPk((int) $typeId);
        if ($type === null || $type->getAppliesTo() !== $appliesTo) {
            throw new ImportantDateRefusedException(gettext('Choose a date type'));
        }
        if (!$type->getActive()) {
            throw new ImportantDateRefusedException(sprintf(gettext('%s is turned off. Turn it on in Important Date Types to record new dates.'), $type->getName()));
        }

        return $type;
    }

    /** Month and day are required; the year is optional, and the whole date must exist. */
    private function applyDate(PersonImportantDate|FamilyImportantDate $record, array $input): void
    {
        $month = filter_var($input['month'] ?? null, FILTER_VALIDATE_INT);
        $day = filter_var($input['day'] ?? null, FILTER_VALIDATE_INT);
        $yearRaw = $input['year'] ?? null;
        $year = ($yearRaw === null || $yearRaw === '') ? null : filter_var($yearRaw, FILTER_VALIDATE_INT);

        if ($month === false || $day === false || $month < 1 || $month > 12 || $day < 1 || $day > DateTimeUtils::getDaysInMonth($month, 2000)) {
            throw new ImportantDateRefusedException(gettext('Enter a valid month and day'));
        }
        if ($year === false || ($year !== null && ($year < self::MIN_YEAR || $year > DateTimeUtils::getCurrentYear()))) {
            throw new ImportantDateRefusedException(sprintf(gettext('Enter a year between %1$d and %2$d, or leave it blank'), self::MIN_YEAR, DateTimeUtils::getCurrentYear()));
        }
        if ($year !== null && !checkdate($month, $day, $year)) {
            throw new ImportantDateRefusedException(sprintf(gettext('%d was not a leap year, so it had no February 29'), $year));
        }

        $note = trim((string) ($input['note'] ?? ''));
        $record->setMonth($month);
        $record->setDay($day);
        $record->setYear($year);
        $record->setNote($note === '' ? null : mb_substr($note, 0, 255));
    }

    private function validName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100) {
            throw new ImportantDateRefusedException(gettext('Enter a name of up to 100 characters'));
        }

        return $name;
    }

    private function assertNameFree(string $name, string $appliesTo): void
    {
        foreach (ImportantDateTypeQuery::create()->filterByAppliesTo($appliesTo)->find() as $existing) {
            if (mb_strtolower((string) $existing->getName()) === mb_strtolower($name)) {
                throw new ImportantDateRefusedException(sprintf(gettext('A date type named %s already exists'), $existing->getName()), true);
            }
        }
    }

    private function uniqueKey(string $source): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($source)), '-');
        if ($base === '') {
            $base = 'date';
        }
        $base = substr($base, 0, 40);
        $key = $base;
        for ($n = 2; ImportantDateTypeQuery::create()->filterByKey($key)->exists(); $n++) {
            $key = $base . '-' . $n;
        }

        return $key;
    }

    private function hasOwnerWithSeveral(ImportantDateType $type): bool
    {
        $owners = $type->appliesToPerson()
            ? PersonImportantDateQuery::create()->filterByTypeId($type->getId())->select(['PersonId'])->find()->getData()
            : FamilyImportantDateQuery::create()->filterByTypeId($type->getId())->select(['FamilyId'])->find()->getData();

        return count($owners) !== count(array_unique($owners));
    }

    /** @return array<int, int> type id => records */
    private function usageCounts(PersonImportantDateQuery|FamilyImportantDateQuery $query): array
    {
        $counts = [];
        foreach ($query->select(['TypeId'])->find()->getData() as $typeId) {
            $counts[(int) $typeId] = ($counts[(int) $typeId] ?? 0) + 1;
        }

        return $counts;
    }
}
