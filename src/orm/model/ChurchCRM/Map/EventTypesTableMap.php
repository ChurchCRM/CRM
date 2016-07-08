<?php

namespace ChurchCRM\Map;

use ChurchCRM\EventTypes;
use ChurchCRM\EventTypesQuery;
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
 * This class defines the structure of the 'event_types' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class EventTypesTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'ChurchCRM.Map.EventTypesTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'default';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'event_types';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\ChurchCRM\\EventTypes';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'ChurchCRM.EventTypes';

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
     * the column name for the type_id field
     */
    const COL_TYPE_ID = 'event_types.type_id';

    /**
     * the column name for the type_name field
     */
    const COL_TYPE_NAME = 'event_types.type_name';

    /**
     * the column name for the type_defstarttime field
     */
    const COL_TYPE_DEFSTARTTIME = 'event_types.type_defstarttime';

    /**
     * the column name for the type_defrecurtype field
     */
    const COL_TYPE_DEFRECURTYPE = 'event_types.type_defrecurtype';

    /**
     * the column name for the type_defrecurDOW field
     */
    const COL_TYPE_DEFRECURDOW = 'event_types.type_defrecurDOW';

    /**
     * the column name for the type_defrecurDOM field
     */
    const COL_TYPE_DEFRECURDOM = 'event_types.type_defrecurDOM';

    /**
     * the column name for the type_defrecurDOY field
     */
    const COL_TYPE_DEFRECURDOY = 'event_types.type_defrecurDOY';

    /**
     * the column name for the type_active field
     */
    const COL_TYPE_ACTIVE = 'event_types.type_active';

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
        self::TYPE_PHPNAME       => array('Id', 'Name', 'DefStartTime', 'DefRecurType', 'DefRecurDOW', 'DefRecurDOM', 'DefRecurDOY', 'Active', ),
        self::TYPE_CAMELNAME     => array('id', 'name', 'defStartTime', 'defRecurType', 'defRecurDOW', 'defRecurDOM', 'defRecurDOY', 'active', ),
        self::TYPE_COLNAME       => array(EventTypesTableMap::COL_TYPE_ID, EventTypesTableMap::COL_TYPE_NAME, EventTypesTableMap::COL_TYPE_DEFSTARTTIME, EventTypesTableMap::COL_TYPE_DEFRECURTYPE, EventTypesTableMap::COL_TYPE_DEFRECURDOW, EventTypesTableMap::COL_TYPE_DEFRECURDOM, EventTypesTableMap::COL_TYPE_DEFRECURDOY, EventTypesTableMap::COL_TYPE_ACTIVE, ),
        self::TYPE_FIELDNAME     => array('type_id', 'type_name', 'type_defstarttime', 'type_defrecurtype', 'type_defrecurDOW', 'type_defrecurDOM', 'type_defrecurDOY', 'type_active', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'Name' => 1, 'DefStartTime' => 2, 'DefRecurType' => 3, 'DefRecurDOW' => 4, 'DefRecurDOM' => 5, 'DefRecurDOY' => 6, 'Active' => 7, ),
        self::TYPE_CAMELNAME     => array('id' => 0, 'name' => 1, 'defStartTime' => 2, 'defRecurType' => 3, 'defRecurDOW' => 4, 'defRecurDOM' => 5, 'defRecurDOY' => 6, 'active' => 7, ),
        self::TYPE_COLNAME       => array(EventTypesTableMap::COL_TYPE_ID => 0, EventTypesTableMap::COL_TYPE_NAME => 1, EventTypesTableMap::COL_TYPE_DEFSTARTTIME => 2, EventTypesTableMap::COL_TYPE_DEFRECURTYPE => 3, EventTypesTableMap::COL_TYPE_DEFRECURDOW => 4, EventTypesTableMap::COL_TYPE_DEFRECURDOM => 5, EventTypesTableMap::COL_TYPE_DEFRECURDOY => 6, EventTypesTableMap::COL_TYPE_ACTIVE => 7, ),
        self::TYPE_FIELDNAME     => array('type_id' => 0, 'type_name' => 1, 'type_defstarttime' => 2, 'type_defrecurtype' => 3, 'type_defrecurDOW' => 4, 'type_defrecurDOM' => 5, 'type_defrecurDOY' => 6, 'type_active' => 7, ),
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
        $this->setName('event_types');
        $this->setPhpName('EventTypes');
        $this->setIdentifierQuoting(false);
        $this->setClassName('\\ChurchCRM\\EventTypes');
        $this->setPackage('ChurchCRM');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('type_id', 'Id', 'INTEGER', true, null, null);
        $this->addColumn('type_name', 'Name', 'VARCHAR', true, 255, '');
        $this->addColumn('type_defstarttime', 'DefStartTime', 'TIME', true, null, '00:00:00');
        $this->addColumn('type_defrecurtype', 'DefRecurType', 'CHAR', true, null, 'none');
        $this->addColumn('type_defrecurDOW', 'DefRecurDOW', 'CHAR', true, null, 'Sunday');
        $this->addColumn('type_defrecurDOM', 'DefRecurDOM', 'CHAR', true, 2, '0');
        $this->addColumn('type_defrecurDOY', 'DefRecurDOY', 'DATE', true, null, '0000-00-00');
        $this->addColumn('type_active', 'Active', 'INTEGER', true, 1, 1);
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
    } // buildRelations()

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
        if ($row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)] === null) {
            return null;
        }

        return null === $row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)] || is_scalar($row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)]) || is_callable([$row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)], '__toString']) ? (string) $row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)] : $row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
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
        return (int) $row[
            $indexType == TableMap::TYPE_NUM
                ? 0 + $offset
                : self::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)
        ];
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
        return $withPrefix ? EventTypesTableMap::CLASS_DEFAULT : EventTypesTableMap::OM_CLASS;
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
     * @return array           (EventTypes object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = EventTypesTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = EventTypesTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + EventTypesTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = EventTypesTableMap::OM_CLASS;
            /** @var EventTypes $obj */
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            EventTypesTableMap::addInstanceToPool($obj, $key);
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
            $key = EventTypesTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = EventTypesTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                /** @var EventTypes $obj */
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                EventTypesTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(EventTypesTableMap::COL_TYPE_ID);
            $criteria->addSelectColumn(EventTypesTableMap::COL_TYPE_NAME);
            $criteria->addSelectColumn(EventTypesTableMap::COL_TYPE_DEFSTARTTIME);
            $criteria->addSelectColumn(EventTypesTableMap::COL_TYPE_DEFRECURTYPE);
            $criteria->addSelectColumn(EventTypesTableMap::COL_TYPE_DEFRECURDOW);
            $criteria->addSelectColumn(EventTypesTableMap::COL_TYPE_DEFRECURDOM);
            $criteria->addSelectColumn(EventTypesTableMap::COL_TYPE_DEFRECURDOY);
            $criteria->addSelectColumn(EventTypesTableMap::COL_TYPE_ACTIVE);
        } else {
            $criteria->addSelectColumn($alias . '.type_id');
            $criteria->addSelectColumn($alias . '.type_name');
            $criteria->addSelectColumn($alias . '.type_defstarttime');
            $criteria->addSelectColumn($alias . '.type_defrecurtype');
            $criteria->addSelectColumn($alias . '.type_defrecurDOW');
            $criteria->addSelectColumn($alias . '.type_defrecurDOM');
            $criteria->addSelectColumn($alias . '.type_defrecurDOY');
            $criteria->addSelectColumn($alias . '.type_active');
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
        return Propel::getServiceContainer()->getDatabaseMap(EventTypesTableMap::DATABASE_NAME)->getTable(EventTypesTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
        $dbMap = Propel::getServiceContainer()->getDatabaseMap(EventTypesTableMap::DATABASE_NAME);
        if (!$dbMap->hasTable(EventTypesTableMap::TABLE_NAME)) {
            $dbMap->addTableObject(new EventTypesTableMap());
        }
    }

    /**
     * Performs a DELETE on the database, given a EventTypes or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or EventTypes object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(EventTypesTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \ChurchCRM\EventTypes) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(EventTypesTableMap::DATABASE_NAME);
            $criteria->add(EventTypesTableMap::COL_TYPE_ID, (array) $values, Criteria::IN);
        }

        $query = EventTypesQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) {
            EventTypesTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) {
                EventTypesTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the event_types table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return EventTypesQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a EventTypes or Criteria object.
     *
     * @param mixed               $criteria Criteria or EventTypes object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(EventTypesTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from EventTypes object
        }

        if ($criteria->containsKey(EventTypesTableMap::COL_TYPE_ID) && $criteria->keyContainsValue(EventTypesTableMap::COL_TYPE_ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.EventTypesTableMap::COL_TYPE_ID.')');
        }


        // Set the correct dbName
        $query = EventTypesQuery::create()->mergeWith($criteria);

        // use transaction because $criteria could contain info
        // for more than one table (I guess, conceivably)
        return $con->transaction(function () use ($con, $query) {
            return $query->doInsert($con);
        });
    }

} // EventTypesTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
EventTypesTableMap::buildTableMap();
