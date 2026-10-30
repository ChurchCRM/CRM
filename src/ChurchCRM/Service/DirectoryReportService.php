<?php

namespace ChurchCRM\Service;

use ChurchCRM\data\Countries;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\model\ChurchCRM\PersonCustomQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\CustomFieldUtils;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Church directory: one entry per household (two or more matching members) or per person
 * (everyone else), sorted by name, with only the details the reader switched on.
 *
 * Options:
 *  - classifications  ?int[]  classification ids to include; null for no classification filter (cart directory)
 *  - groups           int[]   only members of any of these groups; empty for everyone
 *  - cartIds          ?int[]  only these people (the cart); null for everyone
 *  - excludeInactive  bool    leave out people of deactivated families
 *  - headRoles, spouseRoles  int[]  family role ids
 *  - show             array<string, bool>  address, mailing, wedding, birthday, familyPhone, familyWork,
 *                     familyCell, familyEmail, personalPhone, personalWork, personalCell, personalEmail,
 *                     personalWorkEmail, photo
 *  - customFieldOrders  int[]  custom_Order of the person custom fields to print (only those the user may see are used)
 *
 * @phpstan-type Line array{text: string, level: int}
 * @phpstan-type Entry array{sort: string, name: string, lines: list<Line>, photo: string}
 */
class DirectoryReportService
{
    private const CUSTOM_FIELD_PATTERN = '/^c\d+$/';

    /** @var array<string, bool> */
    private array $show = [];

    /** @var list<\ChurchCRM\model\ChurchCRM\PersonCustomMaster> */
    private array $customFields = [];

    /** @var array<int, array<string, string>> person id => custom field id => stored value */
    private array $customValues = [];

    /**
     * @param array<string, mixed> $options
     * @return list<Entry>
     */
    public function entries(array $options): array
    {
        $this->show = (array) ($options['show'] ?? []);
        $people = $this->people($options);
        $this->loadCustomFields((array) ($options['customFieldOrders'] ?? []), $people);

        $headRoles = array_map('intval', (array) ($options['headRoles'] ?? []));
        $spouseRoles = array_map('intval', (array) ($options['spouseRoles'] ?? []));

        $households = [];
        $singles = [];
        foreach ($people as $person) {
            $familyId = (int) $person->getFamId();
            if ($familyId > 0 && $person->getFamily() !== null) {
                $households[$familyId][] = $person;
            } else {
                $singles[] = $person;
            }
        }

        $entries = [];
        foreach ($singles as $person) {
            $entries[] = $this->singleEntry($person, $person->getLastName());
        }
        foreach ($households as $members) {
            $entries[] = count($members) > 1
                ? $this->householdEntry($members, $headRoles, $spouseRoles)
                : $this->singleEntry($members[0], $members[0]->getFamily()->getName());
        }

        $entries = array_values(array_filter($entries, fn (array $entry): bool => $entry !== []));
        usort($entries, fn (array $a, array $b): int => strcmp(mb_strtolower($a['sort']), mb_strtolower($b['sort'])));

        return $entries;
    }

    /** Paper name => [mPDF format, width mm, height mm] of one portrait sheet. */
    private const PAPER = [
        'letter' => ['Letter', 215.9, 279.4],
        'legal' => ['Legal', 215.9, 355.6],
        'a4' => ['A4', 210.0, 297.0],
    ];

    /**
     * The finished PDF.
     *
     * Layout keys: paper (letter, legal, a4), booklet (bool), columns (1-3), fontSize (pt),
     * titlePage (bool) and title (name, address, city, state, zip, phone, disclaimer).
     *
     * @param array<string, mixed> $options see the class comment
     * @param array<string, mixed> $layout
     */
    public function pdf(array $options, array $layout): string
    {
        [$format, $sheetWidth, $sheetHeight] = self::PAPER[$layout['paper']] ?? self::PAPER['legal'];
        $booklet = !empty($layout['booklet']);
        // A booklet page is half a sheet turned landscape: the sheet's long edge halved by its short edge.
        $pageWidth = $booklet ? $sheetHeight / 2 : $sheetWidth;
        $pageHeight = $booklet ? $sheetWidth : $sheetHeight;
        $columns = max(1, min(3, (int) $layout['columns']));
        $fontSize = max(5, min(20, (int) $layout['fontSize']));

        $entries = $this->entries($options);
        $letter = '';
        foreach ($entries as &$entry) {
            $first = mb_strtoupper(mb_substr($entry['sort'], 0, 1));
            $entry['letter'] = $first !== $letter ? $first : '';
            $letter = $first;
        }
        unset($entry);

        $headerTitle = SystemConfig::getValue('sChurchName') . ' - ' . gettext('Directory');
        // Bold serif averages about 0.55 em per character; step the title down until it fits the page.
        $available = $pageWidth - 26 - 4;
        $headerPt = max(8, min(15, (int) floor($available / (max(1, mb_strlen($headerTitle)) * 0.55 * 0.3528))));

        $title = (array) ($layout['title'] ?? []);
        $titleData = [
            'name' => (string) ($title['name'] ?? ''),
            'address' => (string) ($title['address'] ?? ''),
            'city_line' => trim(trim((string) ($title['city'] ?? '') . ', ' . (string) ($title['state'] ?? ''), ', ') . '  ' . (string) ($title['zip'] ?? '')),
            'phone' => (string) ($title['phone'] ?? ''),
            'disclaimer' => (string) ($title['disclaimer'] ?? ''),
            'date' => date(SystemConfig::getValue('sDateFormatLong')),
        ];

        $pdf = (new MpdfRenderer())->renderToString('directory', [
            'entries' => $entries,
            'columns' => $columns,
            'font_pt' => $fontSize,
            'entry_gap_mm' => round($fontSize / 3, 1),
            'header_title' => $headerTitle,
            'header_pt' => $headerPt,
            'footer_date' => date(SystemConfig::getValue('sDateTimeFormat')),
            'title_page' => !empty($layout['titlePage']),
            'title' => $titleData,
            'letterhead' => $this->letterhead(),
        ], [
            'format' => $booklet ? [$pageWidth, $pageHeight] : $format,
            'margin_left' => 13,
            'margin_right' => 13,
            'margin_top' => 25,
            'margin_bottom' => 22,
            'margin_header' => 7,
            'margin_footer' => 10,
        ]);

        return $booklet ? PdfBookletImposer::impose($pdf, $format . '-L', $pageWidth, $pageHeight) : $pdf;
    }

    /** The configured letterhead image when it is a file inside the installation. */
    private function letterhead(): string
    {
        $configured = (string) SystemConfig::getValue('bDirLetterHead');
        if ($configured === '') {
            return '';
        }
        $root = realpath(SystemURLs::getDocumentRoot());
        $path = realpath(str_starts_with($configured, '/') ? $configured : SystemURLs::getDocumentRoot() . '/' . $configured);

        return $root !== false && $path !== false && str_starts_with($path, $root . DIRECTORY_SEPARATOR) && is_file($path) ? $path : '';
    }

    /**
     * @param array<string, mixed> $options
     * @return list<Person>
     */
    private function people(array $options): array
    {
        $query = PersonQuery::create()->leftJoinWithFamily()->filterByNeedsReview(false);

        if (SystemConfig::getBooleanValue('bHideDeceasedFromDirectory')) {
            $query->filterByLiving();
        }
        if (($options['classifications'] ?? null) !== null) {
            $query->filterByClsId(array_map('intval', $options['classifications']), Criteria::IN);
        }
        if (!empty($options['groups'])) {
            $memberIds = Person2group2roleP2g2rQuery::create()
                ->filterByGroupId(array_map('intval', $options['groups']), Criteria::IN)
                ->select('PersonId')
                ->distinct()
                ->find()
                ->toArray();
            $query->filterById($memberIds, Criteria::IN);
        }
        if (($options['cartIds'] ?? null) !== null) {
            $query->filterById(array_map('intval', $options['cartIds']), Criteria::IN);
        }
        if (!empty($options['excludeInactive'])) {
            $query->where('Family.DateDeactivated IS NULL');
        }

        return array_values($query->find()->getArrayCopy());
    }

    /**
     * @param int[]        $orders custom_Order values the reader ticked
     * @param list<Person> $people
     */
    private function loadCustomFields(array $orders, array $people): void
    {
        $this->customFields = [];
        $this->customValues = [];
        if ($orders === [] || $people === []) {
            return;
        }

        // Only fields the signed-in user may see; a posted order must not widen that.
        foreach ((new CustomFieldService())->getVisiblePersonFields() as $field) {
            if (in_array((int) $field->getOrder(), array_map('intval', $orders), true) && preg_match(self::CUSTOM_FIELD_PATTERN, (string) $field->getId()) === 1) {
                $this->customFields[] = $field;
            }
        }
        if ($this->customFields === []) {
            return;
        }

        $query = PersonCustomQuery::create()->filterByPerId(array_map(fn (Person $person): int => $person->getId(), $people), Criteria::IN);
        foreach ($this->customFields as $field) {
            $query->addAsColumn($field->getId(), $field->getId());
        }
        foreach ($query->find() as $row) {
            $this->customValues[$row->getPerId()] = array_map(fn ($value): string => trim((string) $value), $row->getVirtualColumns());
        }
    }

    private function on(string $option): bool
    {
        return !empty($this->show[$option]);
    }

    /**
     * @return list<array{text: string, level: int}>
     */
    private function customLines(Person $person, int $level): array
    {
        $lines = [];
        foreach ($this->customFields as $field) {
            $raw = $this->customValues[$person->getId()][$field->getId()] ?? '';
            if ($raw === '') {
                continue;
            }
            $value = (string) CustomFieldUtils::display($field->getTypeId(), $raw, $field->getSpecial());
            if ($value !== '') {
                $lines[] = ['text' => $field->getName() . ': ' . $value, 'level' => $level];
            }
        }

        return $lines;
    }

    private function photo(Person|Family $subject): string
    {
        if (!$this->on('photo')) {
            return '';
        }
        $photo = $subject->getPhoto();
        $path = $photo !== null && $photo->hasUploadedPhoto() ? (string) $photo->getPhotoURI() : '';

        return $path !== '' && is_file($path) ? $path : '';
    }

    /**
     * Phone, work, cell and email lines of one person.
     *
     * @return list<array{text: string, level: int}>
     */
    private function personalLines(Person $person): array
    {
        $lines = [];
        foreach ([
            ['personalPhone', gettext('Phone'), $person->getHomePhone()],
            ['personalWork', gettext('Work'), $person->getWorkPhone()],
            ['personalCell', gettext('Cell'), $person->getCellPhone()],
            ['personalEmail', gettext('Email'), $person->getEmail()],
            ['personalWorkEmail', gettext('Work/Other Email'), $person->getWorkEmail()],
        ] as [$option, $label, $value]) {
            if ($this->on($option) && (string) $value !== '') {
                $lines[] = ['text' => $label . ': ' . $value, 'level' => 1];
            }
        }

        return $lines;
    }

    private function birthday(Person $person): string
    {
        return $this->on('birthday') && $person->getFormattedBirthDate() ? (string) $person->getFormattedBirthDate() : '';
    }

    /**
     * @return array{text: string, level: int}[]
     */
    private function mailingLines(Family $family): array
    {
        $lines = [];
        if ($this->on('mailing') && $family->hasDistinctMailingAddress()) {
            foreach (explode("\n", $family->getMailingAddressLines()) as $index => $line) {
                $lines[] = ['text' => ($index === 0 ? gettext('Mailing Address') . ': ' : '') . $line, 'level' => 1];
            }
        }

        return $lines;
    }

    /**
     * A person on their own, or the only matching member of a household. Contact details fall back
     * to the household's when the person has none of their own.
     *
     * @return Entry|array{}
     */
    private function singleEntry(Person $person, ?string $sortName): array
    {
        $family = $person->getFamily();
        $name = (string) ($person->getLastName() !== '' && $person->getLastName() !== null ? $person->getLastName() : $family?->getName()) . ', ' . $person->getFirstName();
        if ((string) $person->getSuffix() !== '') {
            $name .= ' ' . $person->getSuffix();
        }
        if ($this->birthday($person) !== '') {
            $name .= ' ' . $this->birthday($person);
        }

        $lines = [];
        if ($this->on('address')) {
            $address1 = trim($person->getResolvedAddress1() . '  ' . $person->getResolvedAddress2());
            if ($address1 !== '') {
                $lines[] = ['text' => $address1, 'level' => 0];
            }
            if ($person->getResolvedCity() !== '') {
                $lines[] = ['text' => $person->getResolvedCity() . ', ' . $person->getResolvedState() . ' ' . $person->getResolvedZip(), 'level' => 0];
            }
            if ($family !== null) {
                $lines = array_merge($lines, $this->mailingLines($family));
            }
        }

        $email = (string) $person->getEmail() !== '' ? (string) $person->getEmail() : (string) $family?->getEmail();
        foreach ([
            [$this->on('familyPhone') || $this->on('personalPhone'), gettext('Phone'), $person->getResolvedHomePhone()],
            [$this->on('familyWork') || $this->on('personalWork'), gettext('Work'), (string) $person->getWorkPhone()],
            [$this->on('familyCell') || $this->on('personalCell'), gettext('Cell'), (string) $person->getCellPhone()],
            [$this->on('familyEmail') || $this->on('personalEmail'), gettext('Email'), $email],
            [$this->on('personalWorkEmail'), gettext('Work/Other Email'), (string) $person->getWorkEmail()],
        ] as [$enabled, $label, $value]) {
            if ($enabled && $value !== '') {
                $lines[] = ['text' => $label . ': ' . $value, 'level' => 1];
            }
        }
        $lines = array_merge($lines, $this->customLines($person, 1));

        // An entry with nothing to print is left out; the address option alone keeps the name.
        if ($lines === [] && !$this->on('address')) {
            return [];
        }

        return ['sort' => (string) $sortName, 'name' => $name, 'lines' => $lines, 'photo' => $this->photo($person)];
    }

    /**
     * @param list<Person> $members
     * @param int[]        $headRoles
     * @param int[]        $spouseRoles
     * @return Entry
     */
    private function householdEntry(array $members, array $headRoles, array $spouseRoles): array
    {
        $family = $members[0]->getFamily();
        $heads = array_values(array_filter($members, fn (Person $p): bool => in_array((int) $p->getFmrId(), $headRoles, true)));
        $spouses = array_values(array_filter($members, fn (Person $p): bool => in_array((int) $p->getFmrId(), $spouseRoles, true)));

        // The first head and the first spouse give the entry its name; every other matching member
        // is listed under them so nobody who passed the filters drops out of the printout.
        $head = $heads[0] ?? null;
        $spouse = $spouses[0] ?? null;
        $namedPeople = array_values(array_filter([$head, $spouse]));
        $others = array_values(array_filter($members, fn (Person $p): bool => !in_array($p, $namedPeople, true)));
        usort($others, fn (Person $a, Person $b): int => [(int) $a->getBirthYear(), $a->getFirstName()] <=> [(int) $b->getBirthYear(), $b->getFirstName()]);

        $lastName = $head?->getLastName() ?: $members[0]->getLastName();

        $name = '';
        foreach ($namedPeople as $person) {
            $name .= $this->namePart($person, (string) $lastName, $name === '' ? ', ' : ' ' . gettext('and') . ' ', $name === '');
        }
        if ($namedPeople === []) {
            $name = (string) $family->getName();
        }

        $lines = $this->householdLines($family);
        foreach (array_merge($namedPeople, $others) as $person) {
            $label = $this->memberLabel($person, (string) $lastName);
            $lines[] = ['text' => $label, 'level' => 0];
            $lines = array_merge($lines, $this->personalLines($person), $this->customLines($person, 1));
        }

        return ['sort' => (string) $family->getName(), 'name' => $name, 'lines' => $lines, 'photo' => $this->photo($family)];
    }

    /** "Last, First" for the first named person, "and First" for the second; the last name repeats only when it differs. */
    private function namePart(Person $person, string $lastName, string $joiner, bool $first): string
    {
        $part = $first ? $lastName . $joiner . $person->getFirstName() : $joiner . $person->getFirstName();
        if ((string) $person->getLastName() !== '' && mb_strtolower((string) $person->getLastName()) !== mb_strtolower($lastName)) {
            $part .= ' ' . $person->getLastName();
        }
        if ((string) $person->getSuffix() !== '') {
            $part .= ' ' . $person->getSuffix();
        }

        return $part;
    }

    private function memberLabel(Person $person, string $lastName): string
    {
        $label = (string) $person->getFirstName();
        if ((string) $person->getLastName() !== '' && mb_strtolower((string) $person->getLastName()) !== mb_strtolower($lastName)) {
            $label .= ' ' . $person->getLastName();
        }
        if ((string) $person->getSuffix() !== '') {
            $label .= ' ' . $person->getSuffix();
        }
        if ($this->birthday($person) !== '') {
            $label .= ' ' . $this->birthday($person);
        }

        return $label;
    }

    /**
     * @return list<array{text: string, level: int}>
     */
    private function householdLines(Family $family): array
    {
        $lines = [];
        if ($this->on('address')) {
            $street = trim($family->getAddress1() . '  ' . $family->getAddress2());
            if ($street !== '') {
                $lines[] = ['text' => $street, 'level' => 0];
            }
            if ((string) $family->getCity() !== '') {
                $lines[] = ['text' => $family->getCity() . ', ' . $family->getState() . ' ' . $family->getZip(), 'level' => 0];
            }
            if (Countries::isForeign((string) $family->getCountry())) {
                $lines[] = ['text' => Countries::toName($family->getCountry()), 'level' => 0];
            }
            $lines = array_merge($lines, $this->mailingLines($family));
        }
        if ($this->on('familyPhone') && (string) $family->getHomePhone() !== '') {
            $lines[] = ['text' => gettext('Phone') . ': ' . $family->getHomePhone(), 'level' => 1];
        }
        if ($this->on('familyEmail') && (string) $family->getEmail() !== '') {
            $lines[] = ['text' => gettext('Email') . ': ' . $family->getEmail(), 'level' => 1];
        }
        $wedding = $family->getWeddingdate();
        if ($this->on('wedding') && $wedding instanceof \DateTimeInterface && (int) $wedding->format('Y') > 0) {
            $lines[] = ['text' => gettext('Wedding') . ': ' . $wedding->format(SystemConfig::getValue('sDateFormatLong')), 'level' => 1];
        }

        return $lines;
    }
}
