<?php

namespace ChurchCRM\Service;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\FamilyCustom;
use ChurchCRM\model\ChurchCRM\FamilyCustomMaster;
use ChurchCRM\model\ChurchCRM\FamilyCustomMasterQuery;
use ChurchCRM\model\ChurchCRM\FamilyCustomQuery;
use ChurchCRM\model\ChurchCRM\GroupPropMasterQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Map\PersonCustomTableMap;
use ChurchCRM\model\ChurchCRM\PersonCustom;
use ChurchCRM\model\ChurchCRM\PersonCustomMaster;
use ChurchCRM\model\ChurchCRM\PersonCustomMasterQuery;
use ChurchCRM\model\ChurchCRM\PersonCustomQuery;
use ChurchCRM\Utils\CustomFieldUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;

/**
 * The one place that decides who may see and change a custom field, and the one place that
 * writes custom field values.
 *
 * A field's FieldSecurity is an option id of list 5 (`*_custom_master`); canView() maps it to
 * the permission a user needs. The `cN` value columns are added per install, so the generated
 * Propel models do not know them: they are read with virtual columns and written here through
 * Propel Criteria with fixed table and column patterns, never SQL built from request input.
 */
class CustomFieldService
{
    /** @var array<int, string>|null FieldSecurity option id => permission name */
    private static ?array $securityNames = null;

    /**
     * Whether the signed-in user may see a custom field with this FieldSecurity option id.
     * A missing or unknown option id is never visible to anyone but an administrator.
     */
    public function canView(?int $fieldSecurityOptionId): bool
    {
        if (!AuthenticationManager::isUserAuthenticated()) {
            return false;
        }

        return AuthenticationManager::getCurrentUser()
            ->isEnabledSecurity($this->getSecurityNames()[$fieldSecurityOptionId] ?? '');
    }

    /**
     * @return list<PersonCustomMaster> in display order
     */
    public function getVisiblePersonFields(): array
    {
        $visible = [];
        foreach (PersonCustomMasterQuery::create()->orderByOrder()->find() as $field) {
            if ($this->canView($field->getFieldSecurity())) {
                $visible[] = $field;
            }
        }

        return $visible;
    }

    /**
     * @return list<FamilyCustomMaster> in display order
     */
    public function getVisibleFamilyFields(): array
    {
        $visible = [];
        foreach (FamilyCustomMasterQuery::create()->orderByOrder()->find() as $field) {
            if ($this->canView($field->getFieldSecurity())) {
                $visible[] = $field;
            }
        }

        return $visible;
    }

    /**
     * Saves the submitted values of the person custom fields the signed-in user may see.
     * Every other column keeps its stored value.
     *
     * @param array<string, string> $submitted field id (c1, c2, ...) => cleaned form value
     */
    public function savePersonValues(int $personId, array $submitted): void
    {
        $values = [];
        foreach ($this->getVisiblePersonFields() as $field) {
            $id = $field->getId();
            if (array_key_exists($id, $submitted)) {
                $values[$id] = CustomFieldUtils::toColumnValue((int) $field->getTypeId(), trim((string) $submitted[$id]));
            }
        }
        if ($values === []) {
            return;
        }

        if (PersonCustomQuery::create()->findOneByPerId($personId) === null) {
            (new PersonCustom())->setPerId($personId)->save();
        }
        $this->updateColumns('person_custom', 'per_ID', $personId, $values);
    }

    /**
     * Saves the submitted values of the family custom fields the signed-in user may see.
     * Every other column keeps its stored value.
     *
     * @param array<string, string> $submitted field id (c1, c2, ...) => cleaned form value
     */
    public function saveFamilyValues(int $familyId, array $submitted): void
    {
        $values = [];
        foreach ($this->getVisibleFamilyFields() as $field) {
            $id = $field->getField();
            if (array_key_exists($id, $submitted)) {
                $values[$id] = CustomFieldUtils::toColumnValue((int) $field->getTypeId(), trim((string) $submitted[$id]));
            }
        }

        $this->ensureFamilyRow($familyId);
        if ($values !== []) {
            $this->updateColumns('family_custom', 'fam_ID', $familyId, $values);
        }
    }

    /**
     * Makes sure a family has a row for its custom field values.
     */
    public function ensureFamilyRow(int $familyId): void
    {
        if (FamilyCustomQuery::create()->findOneByFamId($familyId) === null) {
            (new FamilyCustom())->setFamId($familyId)->save();
        }
    }

    /**
     * Empties every "Person from Group" value (custom field type 9) that points at a person
     * who has left that group, in person custom fields and in group properties.
     */
    public function clearGroupMemberReferences(int $groupId, int $personId): void
    {
        foreach (GroupPropMasterQuery::create()->filterByTypeId(9)->filterBySpecial($groupId)->find() as $property) {
            $this->clearColumn('groupprop_' . $property->getGrpId(), $property->getField(), $personId);
        }
        foreach (PersonCustomMasterQuery::create()->filterByTypeId(9)->filterBySpecial($groupId)->find() as $field) {
            $this->clearColumn('person_custom', $field->getId(), $personId);
        }
    }

    /**
     * Saves a member's values for the properties of one group (`groupprop_N`). Group
     * properties have no field security; the caller has already required Edit Records.
     *
     * @param array<string, string> $submitted property id (c1, c2, ...) => cleaned form value
     */
    public function saveGroupPropertyValues(int $groupId, int $personId, array $submitted): void
    {
        $values = [];
        foreach (GroupPropMasterQuery::create()->filterByGrpId($groupId)->orderByPropId()->find() as $property) {
            $id = $property->getField();
            $values[$id] = CustomFieldUtils::toColumnValue((int) $property->getTypeId(), trim((string) ($submitted[$id] ?? '')));
        }
        if ($values !== []) {
            $this->updateColumns('groupprop_' . $groupId, 'per_ID', $personId, $values);
        }
    }

    /**
     * Reads the property values of one group's members (`groupprop_N`).
     * Group properties have no field security; the caller has already authorised the group.
     *
     * @param int[] $personIds
     * @return array<int, array<string, ?string>> person id => property id (c1, c2, ...) => stored value
     */
    public function getGroupPropertyValues(int $groupId, array $personIds): array
    {
        $table = 'groupprop_' . $groupId;
        $this->assertTable($table);

        $personIds = array_values(array_unique(array_map('intval', $personIds)));
        if ($personIds === []) {
            return [];
        }

        $statement = Propel::getConnection(PersonCustomTableMap::DATABASE_NAME)->prepare(
            'SELECT * FROM `' . $table . '` WHERE per_ID IN (' . implode(',', array_fill(0, count($personIds), '?')) . ')'
        );
        $statement->execute($personIds);

        $values = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $values[(int) $row['per_ID']] = $row;
        }

        return $values;
    }

    private function clearColumn(string $table, string $column, int $personId): void
    {
        $this->assertTable($table);
        $this->assertColumn($column);

        if ($table === 'person_custom') {
            // The generated person_custom table map lists only per_ID; the cN columns are added per
            // install. Register this one for the request so Criteria can bind a condition on it.
            $tableMap = Propel::getServiceContainer()
                ->getDatabaseMap(PersonCustomTableMap::DATABASE_NAME)
                ->getTable(PersonCustomTableMap::TABLE_NAME);
            if (!$tableMap->hasColumn($column)) {
                $tableMap->addColumn($column, strtoupper($column), 'INTEGER', false, null, null);
            }
        }

        $select = new Criteria(PersonCustomTableMap::DATABASE_NAME);
        $select->add($table . '.' . $column, $personId);
        $update = new Criteria(PersonCustomTableMap::DATABASE_NAME);
        $update->add($table . '.' . $column, null);

        $select->doUpdate($update, Propel::getWriteConnection(PersonCustomTableMap::DATABASE_NAME));
    }

    /**
     * @param array<string, ?string> $values column => value; NULL stores NULL
     */
    private function updateColumns(string $table, string $keyColumn, int $key, array $values): void
    {
        $this->assertTable($table);

        $select = new Criteria(PersonCustomTableMap::DATABASE_NAME);
        $select->add($table . '.' . $keyColumn, $key);

        $update = new Criteria(PersonCustomTableMap::DATABASE_NAME);
        foreach ($values as $column => $value) {
            $this->assertColumn((string) $column);
            $update->add($table . '.' . $column, $value);
        }

        $select->doUpdate($update, Propel::getWriteConnection(PersonCustomTableMap::DATABASE_NAME));
    }

    private function assertTable(string $table): void
    {
        if (preg_match('/^(person_custom|family_custom|groupprop_\d+)$/', $table) !== 1) {
            throw new \InvalidArgumentException('Unknown custom field table');
        }
    }

    private function assertColumn(string $column): void
    {
        if (preg_match('/^c\d+$/', $column) !== 1) {
            throw new \InvalidArgumentException('Invalid custom field column');
        }
    }

    /**
     * @return array<int, string>
     */
    private function getSecurityNames(): array
    {
        if (self::$securityNames === null) {
            self::$securityNames = [];
            foreach (ListOptionQuery::create()->filterById(5)->find() as $option) {
                self::$securityNames[$option->getOptionId()] = $option->getOptionName();
            }
        }

        return self::$securityNames;
    }
}
