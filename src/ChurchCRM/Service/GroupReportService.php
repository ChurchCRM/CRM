<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\GroupPropMasterQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\Utils\CustomFieldUtils;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Group directory: the members of one group (optionally one role) with the fields the reader picked.
 */
class GroupReportService
{
    /** Fields a reader can include; the form posts each as "<name>Enable". */
    public const FIELDS = ['GroupRole', 'Address', 'HomePhone', 'WorkPhone', 'CellPhone', 'Email', 'OtherEmail', 'Birthday', 'Gender'];

    /**
     * @param string[]   $fields         FIELDS entries to print
     * @param string[]   $propertyIds    group property ids (c1, c2, ...) to print
     * @param int[]|null $onlyPersonIds  limit to these people (the cart); null for everyone
     * @return array{title: string, people: array<int, array{name: string, lines: array<int, array{label: string, value: string}>}>}|null null when the group does not exist
     */
    public function build(int $groupId, int $roleId, array $fields, array $propertyIds, ?array $onlyPersonIds = null): ?array
    {
        $group = GroupQuery::create()->findPk($groupId);
        if ($group === null) {
            return null;
        }

        $roleNames = [];
        foreach (ListOptionQuery::create()->filterById($group->getRoleListId())->find() as $option) {
            $roleNames[$option->getOptionId()] = $option->getOptionName();
        }

        $title = $group->getName() . ' - ' . gettext('Group Directory');
        if ($roleId > 0 && isset($roleNames[$roleId])) {
            $title .= ' (' . $roleNames[$roleId] . ')';
        }

        $properties = [];
        foreach (GroupPropMasterQuery::create()->filterByGrpId($groupId)->orderByPropId()->find() as $property) {
            if (in_array($property->getField(), $propertyIds, true)) {
                $properties[] = $property;
            }
        }

        $query = Person2group2roleP2g2rQuery::create()->filterByGroup($group);
        if ($roleId > 0) {
            $query->filterByRoleId($roleId);
        }
        $personQuery = $query->joinWithPerson()->usePersonQuery()
            ->filterByNeedsReview(false)
            ->orderByLastName()
            ->orderByFirstName();
        if (SystemConfig::getBooleanValue('bHideDeceasedFromDirectory')) {
            $personQuery->filterByDateDeceased(null, Criteria::ISNULL);
        }
        if ($onlyPersonIds !== null) {
            $personQuery->filterById($onlyPersonIds, Criteria::IN);
        }
        $memberships = $personQuery->endUse()->find();

        $propertyValues = [];
        if ($properties !== []) {
            $ids = [];
            foreach ($memberships as $membership) {
                $ids[] = $membership->getPersonId();
            }
            $propertyValues = (new CustomFieldService())->getGroupPropertyValues($groupId, $ids);
        }

        $people = [];
        foreach ($memberships as $membership) {
            $person = $membership->getPerson();
            $lines = [];

            if (in_array('GroupRole', $fields, true)) {
                $lines[] = ['label' => gettext('Role'), 'value' => (string) ($roleNames[$membership->getRoleId()] ?? '')];
            }
            if (in_array('Address', $fields, true)) {
                $lines = array_merge($lines, self::addressLines($person));
            }
            foreach ([
                'HomePhone' => [gettext('Phone'), $person->getHomePhone()],
                'WorkPhone' => [gettext('Work'), $person->getWorkPhone()],
                'CellPhone' => [gettext('Cell'), $person->getCellPhone()],
                'Email' => [gettext('Email'), $person->getEmail()],
                'OtherEmail' => [gettext('Other Email'), $person->getWorkEmail()],
            ] as $field => [$label, $value]) {
                if (in_array($field, $fields, true) && (string) $value !== '') {
                    $lines[] = ['label' => $label, 'value' => (string) $value];
                }
            }
            if (in_array('Birthday', $fields, true) && $person->getFormattedBirthDate()) {
                $lines[] = ['label' => gettext('Birthday'), 'value' => (string) $person->getFormattedBirthDate()];
            }
            if (in_array('Gender', $fields, true) && in_array((int) $person->getGender(), [1, 2], true)) {
                $lines[] = ['label' => gettext('Gender'), 'value' => $person->getGenderName()];
            }
            foreach ($properties as $property) {
                $stored = $propertyValues[$person->getId()][$property->getField()] ?? null;
                $lines[] = [
                    'label' => (string) $property->getName(),
                    'value' => (string) CustomFieldUtils::display($property->getTypeId(), trim((string) $stored), $property->getSpecial()),
                ];
            }

            $people[] = ['name' => $person->getFullName(), 'lines' => $lines];
        }

        return ['title' => $title, 'people' => $people];
    }

    /**
     * The person's own address only: each person enters their own information, so a family
     * address is never filled in for them.
     *
     * @return array<int, array{label: string, value: string}>
     */
    private static function addressLines(Person $person): array
    {
        $lines = [];
        foreach ([$person->getAddress1(), $person->getAddress2()] as $line) {
            if ((string) $line !== '') {
                $lines[] = ['label' => '', 'value' => (string) $line];
            }
        }
        if ((string) $person->getCity() !== '') {
            $lines[] = ['label' => '', 'value' => $person->getCity() . ', ' . $person->getState() . ' ' . $person->getZip()];
        }

        return $lines;
    }
}
