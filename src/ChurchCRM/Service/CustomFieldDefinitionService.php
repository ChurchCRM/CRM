<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\FamilyCustomMaster;
use ChurchCRM\model\ChurchCRM\FamilyCustomMasterQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Map\FamilyCustomTableMap;
use ChurchCRM\model\ChurchCRM\Map\PersonCustomTableMap;
use ChurchCRM\model\ChurchCRM\PersonCustomMaster;
use ChurchCRM\model\ChurchCRM\PersonCustomMasterQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;

/**
 * Orders and removes person and family custom field definitions.
 *
 * A definition is a `person_custom_master` / `family_custom_master` row keyed by its value
 * column name (`c1`, `c2`, ...). Its values live in that column of `person_custom` /
 * `family_custom`, so removing a definition also drops the column. Display order is a
 * contiguous 1..N sequence.
 */
class CustomFieldDefinitionService
{
    public const DIRECTIONS = ['up', 'down'];

    private const CUSTOM_LIST_TYPE_ID = 12;

    /**
     * @param string $recordType 'person' or 'family'
     */
    public function find(string $recordType, string $field): PersonCustomMaster|FamilyCustomMaster|null
    {
        return match ($recordType) {
            'person' => PersonCustomMasterQuery::create()->findPk($field),
            'family' => FamilyCustomMasterQuery::create()->findPk($field),
            default => throw new \InvalidArgumentException('Unknown custom field record type: ' . $recordType),
        };
    }

    /**
     * Swaps a field with the one directly above ('up') or below ('down') it.
     *
     * @return bool false when no field sits in that direction
     */
    public function move(PersonCustomMaster|FamilyCustomMaster $definition, string $direction): bool
    {
        if (!in_array($direction, self::DIRECTIONS, true)) {
            throw new \InvalidArgumentException('Direction must be "up" or "down"');
        }

        $order = (int) $definition->getOrder();
        $neighbourOrder = $direction === 'up' ? $order - 1 : $order + 1;
        $neighbour = $this->createQuery($definition)->filterByOrder($neighbourOrder)->findOne();
        if ($neighbour === null) {
            return false;
        }

        $connection = Propel::getWriteConnection($this->valuesTableMap($definition)::DATABASE_NAME);
        $connection->beginTransaction();
        try {
            $neighbour->setOrder($order)->save($connection);
            $definition->setOrder($neighbourOrder)->save($connection);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * Removes a field: its list options (custom drop-down fields), its value column with all
     * stored values, and its definition. The fields after it move up one place.
     */
    public function delete(PersonCustomMaster|FamilyCustomMaster $definition): void
    {
        $column = (string) $definition->getPrimaryKey();
        if (preg_match('/^c\d+$/', $column) !== 1) {
            throw new \InvalidArgumentException('Invalid custom field column: ' . $column);
        }

        if ((int) $definition->getTypeId() === self::CUSTOM_LIST_TYPE_ID) {
            $listId = $definition instanceof PersonCustomMaster ? $definition->getSpecial() : $definition->getCustomSpecial();
            ListOptionQuery::create()->filterById((int) $listId)->delete();
        }

        // MySQL commits DDL implicitly, so the column goes first: a failed DROP leaves the
        // definition in place rather than a column of values no definition points to.
        $tableMap = $this->valuesTableMap($definition);
        $connection = Propel::getWriteConnection($tableMap::DATABASE_NAME);
        $connection->exec('ALTER TABLE `' . $tableMap::TABLE_NAME . '` DROP IF EXISTS `' . $column . '`'); // nosemgrep: php.lang.security.injection.tainted-sql-string.tainted-sql-string

        $order = (int) $definition->getOrder();
        $definition->delete($connection);

        $laterFields = $this->createQuery($definition)
            ->filterByOrder($order, Criteria::GREATER_THAN)
            ->orderByOrder()
            ->find($connection);
        foreach ($laterFields as $laterField) {
            $laterField->setOrder((int) $laterField->getOrder() - 1)->save($connection);
        }
    }

    private function createQuery(PersonCustomMaster|FamilyCustomMaster $definition): PersonCustomMasterQuery|FamilyCustomMasterQuery
    {
        return $definition instanceof PersonCustomMaster
            ? PersonCustomMasterQuery::create()
            : FamilyCustomMasterQuery::create();
    }

    /**
     * @return class-string<PersonCustomTableMap|FamilyCustomTableMap>
     */
    private function valuesTableMap(PersonCustomMaster|FamilyCustomMaster $definition): string
    {
        return $definition instanceof PersonCustomMaster ? PersonCustomTableMap::class : FamilyCustomTableMap::class;
    }
}
