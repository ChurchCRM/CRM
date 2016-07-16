<?php

namespace ChurchCRM\Map;

use ChurchCRM\UserConfig;
use ChurchCRM\UserConfigQuery;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\InstancePoolTrait;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\DataFetcher\DataFetcherInterface;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Map\RelationMap;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Map\TableMapTrait;


/**
 * This class defines the structure of the 'userconfig_ucfg' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class UserConfigTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'ChurchCRM.Map.UserConfigTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'default';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'userconfig_ucfg';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\ChurchCRM\\UserConfig';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'ChurchCRM.UserConfig';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 8;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 8;

    /**
     * the column name for the ucfg_per_id field
     */
    const COL_UCFG_PER_ID = 'userconfig_ucfg.ucfg_per_id';

    /**
     * the column name for the ucfg_id field
     */
    const COL_UCFG_ID = 'userconfig_ucfg.ucfg_id';

    /**
     * the column name for the ucfg_name field
     */
    const COL_UCFG_NAME = 'userconfig_ucfg.ucfg_name';

    /**
     * the column name for the ucfg_value field
     */
    const COL_UCFG_VALUE = 'userconfig_ucfg.ucfg_value';

    /**
     * the column name for the ucfg_type field
     */
    const COL_UCFG_TYPE = 'userconfig_ucfg.ucfg_type';

    /**
     * the column name for the ucfg_tooltip field
     */
    const COL_UCFG_TOOLTIP = 'userconfig_ucfg.ucfg_tooltip';

    /**
     * the column name for the ucfg_permission field
     */
    const COL_UCFG_PERMISSION = 'userconfig_ucfg.ucfg_permission';

    /**
     * the column name for the ucfg_cat field
     */
    const COL_UCFG_CAT = 'userconfig_ucfg.ucfg_cat';

    /**
     * The default string format for model objects of the related table
     */
    const DEFAULT_STRING_FORMAT = 'YAML';

    /**
     * holds an array of fieldnames
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldNames[self::TYPE_PHPNAME][0] = 'Id'
     */
    protected static $fieldNames = array (
        self::TYPE_PHPNAME       => array('PeronId', 'Id', 'Name', 'Value', 'Type', 'Tooltip', 'Permission', 'Cat', ),
        self::TYPE_CAMELNAME     => array('peronId', 'id', 'name', 'value', 'type', 'tooltip', 'permission', 'cat', ),
        self::TYPE_COLNAME       => array(UserConfigTableMap::COL_UCFG_PER_ID, UserConfigTableMap::COL_UCFG_ID, UserConfigTableMap::COL_UCFG_NAME, UserConfigTableMap::COL_UCFG_VALUE, UserConfigTableMap::COL_UCFG_TYPE, UserConfigTableMap::COL_UCFG_TOOLTIP, UserConfigTableMap::COL_UCFG_PERMISSION, UserConfigTableMap::COL_UCFG_CAT, ),
        self::TYPE_FIELDNAME     => array('ucfg_per_id', 'ucfg_id', 'ucfg_name', 'ucfg_value', 'ucfg_type', 'ucfg_tooltip', 'ucfg_permission', 'ucfg_cat', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('PeronId' => 0, 'Id' => 1, 'Name' => 2, 'Value' => 3, 'Type' => 4, 'Tooltip' => 5, 'Permission' => 6, 'Cat' => 7, ),
        self::TYPE_CAMELNAME     => array('peronId' => 0, 'id' => 1, 'name' => 2, 'value' => 3, 'type' => 4, 'tooltip' => 5, 'permission' => 6, 'cat' => 7, ),
        self::TYPE_COLNAME       => array(UserConfigTableMap::COL_UCFG_PER_ID => 0, UserConfigTableMap::COL_UCFG_ID => 1, UserConfigTableMap::COL_UCFG_NAME => 2, UserConfigTableMap::COL_UCFG_VALUE => 3, UserConfigTableMap::COL_UCFG_TYPE => 4, UserConfigTableMap::COL_UCFG_TOOLTIP => 5, UserConfigTableMap::COL_UCFG_PERMISSION => 6, UserConfigTableMap::COL_UCFG_CAT => 7, ),
        self::TYPE_FIELDNAME     => array('ucfg_per_id' => 0, 'ucfg_id' => 1, 'ucfg_name' => 2, 'ucfg_value' => 3, 'ucfg_type' => 4, 'ucfg_tooltip' => 5, 'ucfg_permission' => 6, 'ucfg_cat' => 7, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, )
    );

    /**
     * Initialize the table attributes and columns
     * Relations are not initialized by this method since they are lazy loaded
     *
     * @return void
     * @throws PropelException
     */
    public function initialize()
    {
        // attributes
        $this->setName('userconfig_ucfg');
        $this->setPhpName('UserConfig');
        $this->setIdentifierQuoting(false);
        $this->setClassName('\\ChurchCRM\\UserConfig');
        $this->setPackage('ChurchCRM');
        $this->setUseIdGenerator(false);
        // columns
        $this->addForeignPrimaryKey('ucfg_per_id', 'PeronId', 'SMALLINT' , 'user_usr', 'usr_per_ID', true, 9, null);
        $this->addPrimaryKey('ucfg_id', 'Id', 'INTEGER', true, null, 0);
        $this->addColumn('ucfg_name', 'Name', 'VARCHAR', true, 50, '');
        $this->addColumn('ucfg_value', 'Value', 'LONGVARCHAR', false, null, null);
        $this->addColumn('ucfg_type', 'Type', 'CHAR', true, null, 'text');
        $this->addColumn('ucfg_tooltip', 'Tooltip', 'LONGVARCHAR', true, null, null);
        $this->addColumn('ucfg_permission', 'Permission', 'CHAR', true, null, 'FALSE');
        $this->addColumn('ucfg_cat', 'Cat', 'VARCHAR', true, 20, null);
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('User', '\\ChurchCRM\\User', RelationMap::MANY_TO_ONE, array (
  0 =>
  array (
    0 => ':ucfg_per_id',
    1 => ':usr_per_ID',
  ),
), null, null, null, false);
    } // buildRelations()

    /**
     * Adds an object to the instance pool.
     *
     * Propel keeps cached copies of objects in an instance pool when they are retrieved
     * from the database. In some cases you may need to explicitly add objects
     * to the cache in order to ensure that the same objects are always returned by find*()
     * and findPk*() calls.
     *
     * @param \ChurchCRM\UserConfig $obj A \ChurchCRM\UserConfig object.
     * @param string $key             (optional) key to use for instance map (for performance boost if key was already calculated externally).
     */
    public static function addInstanceToPool($obj, $key = null)
    {
        if (Propel::isInstancePoolingEnabled()) {
            if (null === $key) {
                $key = serialize([(null === $obj->getPeronId() || is_scalar($obj->getPeronId()) || is_callable([$obj->getPeronId(), '__toString']) ? (string) $obj->getPeronId() : $obj->getPeronId()), (null === $obj->getId() || is_scalar($obj->getId()) || is_callable([$obj->getId(), '__toString']) ? (string) $obj->getId() : $obj->getId())]);
            } // if key === null
            self::$instances[$key] = $obj;
        }
    }

    /**
     * Removes an object from the instance pool.
     *
     * Propel keeps cached copies of objects in an instance pool when they are retrieved
     * from the database.  In some cases -- especially when you override doDelete
     * methods in your stub classes -- you may need to explicitly remove objects
     * from the cache in order to prevent returning objects that no longer exist.
     *
     * @param mixed $value A \ChurchCRM\UserConfig object or a primary key value.
     */
    public static function removeInstanceFromPool($value)
    {
        if (Propel::isInstancePoolingEnabled() && null !== $value) {
            if (is_object($value) && $value instanceof \ChurchCRM\UserConfig) {
                $key = serialize([(null === $value->getPeronId() || is_scalar($value->getPeronId()) || is_callable([$value->getPeronId(), '__toString']) ? (string) $value->getPeronId() : $value->getPeronId()), (null === $value->getId() || is_scalar($value->getId()) || is_callable([$value->getId(), '__toString']) ? (string) $value->getId() : $value->getId())]);

            } elseif (is_array($value) && count($value) === 2) {
                // assume we've been passed a primary key";
                $key = serialize([(null === $value[0] || is_scalar($value[0]) || is_callable([$value[0], '__toString']) ? (string) $value[0] : $value[0]), (null === $value[1] || is_scalar($value[1]) || is_callable([$value[1], '__toString']) ? (string) $value[1] : $value[1])]);
            } elseif ($value instanceof Criteria) {
                self::$instances = [];

                return;
            } else {
                $e = new PropelException("Invalid value passed to removeInstanceFromPool().  Expected primary key or \ChurchCRM\UserConfig object; got " . (is_object($value) ? get_class($value) . ' object.' : var_export($value, true)));
                throw $e;
            }

            unset(self::$instances[$key]);
        }
    }

    /**
     * Retrieves a string version of the primary key from the DB resultset row that can be used to uniquely identify a row in this table.
     *
     * For tables with a single-column primary key, that simple pkey value will be returned.  For tables with
     * a multi-column primary key, a serialize()d version of the primary key will be returned.
     *
     * @param array  $row       resultset row.
     * @param int    $offset    The 0-based offset for reading from the resultset row.
     * @param string $indexType One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME
     *                           TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM
     *
     * @return string The primary key hash of the row
     */
    public static function getPrimaryKeyHashFromRow($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        // If the PK cannot be derived from the row, return NULL.
        if ($row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('PeronId', TableMap::TYPE_PHPNAME, $indexType)] === null && $row[TableMap::TYPE_NUM == $indexType ? 1 + $offset : static::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)] === null) {
            return null;
        }

        return serialize([(null === $row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('PeronId', TableMap::TYPE_PHPNAME, $indexType)] || is_scalar($row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('PeronId', TableMap::TYPE_PHPNAME, $indexType)]) || is_callable([$row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('PeronId', TableMap::TYPE_PHPNAME, $indexType)], '__toString']) ? (string) $row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('PeronId', TableMap::TYPE_PHPNAME, $indexType)] : $row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('PeronId', TableMap::TYPE_PHPNAME, $indexType)]), (null === $row[TableMap::TYPE_NUM == $indexType ? 1 + $offset : static::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)] || is_scalar($row[TableMap::TYPE_NUM == $indexType ? 1 + $offset : static::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)]) || is_callable([$row[TableMap::TYPE_NUM == $indexType ? 1 + $offset : static::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)], '__toString']) ? (string) $row[TableMap::TYPE_NUM == $indexType ? 1 + $offset : static::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)] : $row[TableMap::TYPE_NUM == $indexType ? 1 + $offset : static::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)])]);
    }

    /**
     * Retrieves the primary key from the DB resultset row
     * For tables with a single-column primary key, that simple pkey value will be returned.  For tables with
     * a multi-column primary key, an array of the primary key columns will be returned.
     *
     * @param array  $row       resultset row.
     * @param int    $offset    The 0-based offset for reading from the resultset row.
     * @param string $indexType One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME
     *                           TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM
     *
     * @return mixed The primary key of the row
     */
    public static function getPrimaryKeyFromRow($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
            $pks = [];

        $pks[] = (int) $row[
            $indexType == TableMap::TYPE_NUM
                ? 0 + $offset
                : self::translateFieldName('PeronId', TableMap::TYPE_PHPNAME, $indexType)
        ];
        $pks[] = (int) $row[
            $indexType == TableMap::TYPE_NUM
                ? 1 + $offset
                : self::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)
        ];

        return $pks;
    }

    /**
     * The class that the tableMap will make instances of.
     *
     * If $withPrefix is true, the returned path
     * uses a dot-path notation which is translated into a path
     * relative to a location on the PHP include_path.
     * (e.g. path.to.MyClass -> 'path/to/MyClass.php')
     *
     * @param boolean $withPrefix Whether or not to return the path with the class name
     * @return string path.to.ClassName
     */
    public static function getOMClass($withPrefix = true)
    {
        return $withPrefix ? UserConfigTableMap::CLASS_DEFAULT : UserConfigTableMap::OM_CLASS;
    }

    /**
     * Populates an object of the default type or an object that inherit from the default.
     *
     * @param array  $row       row returned by DataFetcher->fetch().
     * @param int    $offset    The 0-based offset for reading from the resultset row.
     * @param string $indexType The index type of $row. Mostly DataFetcher->getIndexType().
                                 One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME
     *                           TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     * @return array           (UserConfig object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = UserConfigTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = UserConfigTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + UserConfigTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = UserConfigTableMap::OM_CLASS;
            /** @var UserConfig $obj */
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            UserConfigTableMap::addInstanceToPool($obj, $key);
        }

        return array($obj, $col);
    }

    /**
     * The returned array will contain objects of the default type or
     * objects that inherit from the default.
     *
     * @param DataFetcherInterface $dataFetcher
     * @return array
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function populateObjects(DataFetcherInterface $dataFetcher)
    {
        $results = array();

        // set the class once to avoid overhead in the loop
        $cls = static::getOMClass(false);
        // populate the object(s)
        while ($row = $dataFetcher->fetch()) {
            $key = UserConfigTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = UserConfigTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                /** @var UserConfig $obj */
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                UserConfigTableMap::addInstanceToPool($obj, $key);
            } // if key exists
        }

        return $results;
    }
    /**
     * Add all the columns needed to create a new object.
     *
     * Note: any columns that were marked with lazyLoad="true" in the
     * XML schema will not be added to the select list and only loaded
     * on demand.
     *
     * @param Criteria $criteria object containing the columns to add.
     * @param string   $alias    optional table alias
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function addSelectColumns(Criteria $criteria, $alias = null)
    {
        if (null === $alias) {
            $criteria->addSelectColumn(UserConfigTableMap::COL_UCFG_PER_ID);
            $criteria->addSelectColumn(UserConfigTableMap::COL_UCFG_ID);
            $criteria->addSelectColumn(UserConfigTableMap::COL_UCFG_NAME);
            $criteria->addSelectColumn(UserConfigTableMap::COL_UCFG_VALUE);
            $criteria->addSelectColumn(UserConfigTableMap::COL_UCFG_TYPE);
            $criteria->addSelectColumn(UserConfigTableMap::COL_UCFG_TOOLTIP);
            $criteria->addSelectColumn(UserConfigTableMap::COL_UCFG_PERMISSION);
            $criteria->addSelectColumn(UserConfigTableMap::COL_UCFG_CAT);
        } else {
            $criteria->addSelectColumn($alias . '.ucfg_per_id');
            $criteria->addSelectColumn($alias . '.ucfg_id');
            $criteria->addSelectColumn($alias . '.ucfg_name');
            $criteria->addSelectColumn($alias . '.ucfg_value');
            $criteria->addSelectColumn($alias . '.ucfg_type');
            $criteria->addSelectColumn($alias . '.ucfg_tooltip');
            $criteria->addSelectColumn($alias . '.ucfg_permission');
            $criteria->addSelectColumn($alias . '.ucfg_cat');
        }
    }

    /**
     * Returns the TableMap related to this object.
     * This method is not needed for general use but a specific application could have a need.
     * @return TableMap
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function getTableMap()
    {
        return Propel::getServiceContainer()->getDatabaseMap(UserConfigTableMap::DATABASE_NAME)->getTable(UserConfigTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
        $dbMap = Propel::getServiceContainer()->getDatabaseMap(UserConfigTableMap::DATABASE_NAME);
        if (!$dbMap->hasTable(UserConfigTableMap::TABLE_NAME)) {
            $dbMap->addTableObject(new UserConfigTableMap());
        }
    }

    /**
     * Performs a DELETE on the database, given a UserConfig or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or UserConfig object or primary key or array of primary keys
     *              which is used to create the DELETE statement
     * @param  ConnectionInterface $con the connection to use
     * @return int             The number of affected rows (if supported by underlying database driver).  This includes CASCADE-related rows
     *                         if supported by native driver or if emulated using Propel.
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
     public static function doDelete($values, ConnectionInterface $con = null)
     {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(UserConfigTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \ChurchCRM\UserConfig) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(UserConfigTableMap::DATABASE_NAME);
            // primary key is composite; we therefore, expect
            // the primary key passed to be an array of pkey values
            if (count($values) == count($values, COUNT_RECURSIVE)) {
                // array is not multi-dimensional
                $values = array($values);
            }
            foreach ($values as $value) {
                $criterion = $criteria->getNewCriterion(UserConfigTableMap::COL_UCFG_PER_ID, $value[0]);
                $criterion->addAnd($criteria->getNewCriterion(UserConfigTableMap::COL_UCFG_ID, $value[1]));
                $criteria->addOr($criterion);
            }
        }

        $query = UserConfigQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) {
            UserConfigTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) {
                UserConfigTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the userconfig_ucfg table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return UserConfigQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a UserConfig or Criteria object.
     *
     * @param mixed               $criteria Criteria or UserConfig object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(UserConfigTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from UserConfig object
        }


        // Set the correct dbName
        $query = UserConfigQuery::create()->mergeWith($criteria);

        // use transaction because $criteria could contain info
        // for more than one table (I guess, conceivably)
        return $con->transaction(function () use ($con, $query) {
            return $query->doInsert($con);
        });
    }

} // UserConfigTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
UserConfigTableMap::buildTableMap();
