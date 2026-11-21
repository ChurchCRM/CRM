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
     * The definition row is re-read under a write lock inside the transaction:
     * the object passed in may have been fetched before a concurrent move
     * changed its order, and the neighbour lookup must use the current order,
     * not the stale in-memory one. Locking both rows serializes concurrent
     * moves on the same pair, so two swaps cannot interleave and duplicate
     * an order (#11602).
     *
     * @return bool false when no field sits in that direction
     */
    public function move(PersonCustomMaster|FamilyCustomMaster $definition, string $direction): bool
    {
        if (!in_array($direction, self::DIRECTIONS, true)) {
            throw new \InvalidArgumentException('Direction must be "up" or "down"');
        }

        $connection = Propel::getWriteConnection($this->valuesTableMap($definition)::DATABASE_NAME);
        $connection->beginTransaction();
        try {
            $current = $this->createQuery($definition)
                ->filterByPrimaryKey($definition->getPrimaryKey())
                ->lockForUpdate()
                ->findOne($connection);
            if ($current === null) {
                $connection->rollBack();

                return false;
            }

            $order = (int) $current->getOrder();
            $neighbourOrder = $direction === 'up' ? $order - 1 : $order + 1;
            $neighbour = $this->createQuery($definition)
                ->filterByOrder($neighbourOrder)
                ->lockForUpdate()
                ->findOne($connection);
            if ($neighbour === null) {
                $connection->rollBack();

                return false;
            }

            $neighbour->setOrder($order)->save($connection);
            $current->setOrder($neighbourOrder)->save($connection);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        // Keep the object the caller holds in step with the row just moved,
        // as the previous implementation did by saving through it.
        $definition->setOrder($neighbourOrder);

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
        // `DROP ... IF EXISTS` for a column is MariaDB-only syntax: MySQL rejects it
        // with a 1064 syntax error (nightly run 44435504066, both MySQL regression
        // jobs; the MariaDB jobs passed). Check information_schema first, then issue
        // a plain DROP COLUMN, which both servers accept. The table name is a class
        // constant and $column is validated against /^c\d+$/ above.
        $tableExists = (int) $connection->query(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $tableMap::TABLE_NAME . "'" // nosemgrep: php.lang.security.injection.tainted-sql-string.tainted-sql-string
        )->fetchColumn();
        if ($tableExists === 0) {
            // A missing value table is a failure, not an already-dropped column:
            // throwing here leaves the definition in place (the DROP-first ordering
            // above), instead of silently deleting a definition whose values were
            // never dropped. A missing column on an existing table stays a no-op.
            throw new \RuntimeException('Custom field value table does not exist: ' . $tableMap::TABLE_NAME);
        }
        $columnExists = (int) $connection->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $tableMap::TABLE_NAME . "' AND COLUMN_NAME = '" . $column . "'" // nosemgrep: php.lang.security.injection.tainted-sql-string.tainted-sql-string
        )->fetchColumn();
        if ($columnExists > 0) {
            $connection->exec('ALTER TABLE `' . $tableMap::TABLE_NAME . '` DROP COLUMN `' . $column . '`'); // nosemgrep: php.lang.security.injection.tainted-sql-string.tainted-sql-string
        }

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
