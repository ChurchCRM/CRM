<?php

namespace ChurchCRM\Map;

use ChurchCRM\EventAttend;
use ChurchCRM\EventAttendQuery;
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
 * This class defines the structure of the 'event_attend' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class EventAttendTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'ChurchCRM.Map.EventAttendTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'default';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'event_attend';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\ChurchCRM\\EventAttend';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'ChurchCRM.EventAttend';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 7;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 7;

    /**
     * the column name for the attend_id field
     */
    const COL_ATTEND_ID = 'event_attend.attend_id';

    /**
     * the column name for the event_id field
     */
    const COL_EVENT_ID = 'event_attend.event_id';

    /**
     * the column name for the person_id field
     */
    const COL_PERSON_ID = 'event_attend.person_id';

    /**
     * the column name for the checkin_date field
     */
    const COL_CHECKIN_DATE = 'event_attend.checkin_date';

    /**
     * the column name for the checkin_id field
     */
    const COL_CHECKIN_ID = 'event_attend.checkin_id';

    /**
     * the column name for the checkout_date field
     */
    const COL_CHECKOUT_DATE = 'event_attend.checkout_date';

    /**
     * the column name for the checkout_id field
     */
    const COL_CHECKOUT_ID = 'event_attend.checkout_id';

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
        self::TYPE_PHPNAME       => array('AttendId', 'EventId', 'PersonId', 'CheckinDate', 'CheckinId', 'CheckoutDate', 'CheckoutId', ),
        self::TYPE_CAMELNAME     => array('attendId', 'eventId', 'personId', 'checkinDate', 'checkinId', 'checkoutDate', 'checkoutId', ),
        self::TYPE_COLNAME       => array(EventAttendTableMap::COL_ATTEND_ID, EventAttendTableMap::COL_EVENT_ID, EventAttendTableMap::COL_PERSON_ID, EventAttendTableMap::COL_CHECKIN_DATE, EventAttendTableMap::COL_CHECKIN_ID, EventAttendTableMap::COL_CHECKOUT_DATE, EventAttendTableMap::COL_CHECKOUT_ID, ),
        self::TYPE_FIELDNAME     => array('attend_id', 'event_id', 'person_id', 'checkin_date', 'checkin_id', 'checkout_date', 'checkout_id', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('AttendId' => 0, 'EventId' => 1, 'PersonId' => 2, 'CheckinDate' => 3, 'CheckinId' => 4, 'CheckoutDate' => 5, 'CheckoutId' => 6, ),
        self::TYPE_CAMELNAME     => array('attendId' => 0, 'eventId' => 1, 'personId' => 2, 'checkinDate' => 3, 'checkinId' => 4, 'checkoutDate' => 5, 'checkoutId' => 6, ),
        self::TYPE_COLNAME       => array(EventAttendTableMap::COL_ATTEND_ID => 0, EventAttendTableMap::COL_EVENT_ID => 1, EventAttendTableMap::COL_PERSON_ID => 2, EventAttendTableMap::COL_CHECKIN_DATE => 3, EventAttendTableMap::COL_CHECKIN_ID => 4, EventAttendTableMap::COL_CHECKOUT_DATE => 5, EventAttendTableMap::COL_CHECKOUT_ID => 6, ),
        self::TYPE_FIELDNAME     => array('attend_id' => 0, 'event_id' => 1, 'person_id' => 2, 'checkin_date' => 3, 'checkin_id' => 4, 'checkout_date' => 5, 'checkout_id' => 6, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, )
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
        $this->setName('event_attend');
        $this->setPhpName('EventAttend');
        $this->setIdentifierQuoting(false);
        $this->setClassName('\\ChurchCRM\\EventAttend');
        $this->setPackage('ChurchCRM');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('attend_id', 'AttendId', 'INTEGER', true, null, null);
        $this->addForeignKey('event_id', 'EventId', 'INTEGER', 'events_event', 'event_id', true, null, 0);
        $this->addColumn('person_id', 'PersonId', 'INTEGER', true, null, 0);
        $this->addColumn('checkin_date', 'CheckinDate', 'TIMESTAMP', false, null, null);
        $this->addColumn('checkin_id', 'CheckinId', 'INTEGER', false, null, null);
        $this->addColumn('checkout_date', 'CheckoutDate', 'TIMESTAMP', false, null, null);
        $this->addColumn('checkout_id', 'CheckoutId', 'INTEGER', false, null, null);
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('Event', '\\ChurchCRM\\Event', RelationMap::MANY_TO_ONE, array (
  0 =>
  array (
    0 => ':event_id',
    1 => ':event_id',
  ),
), null, null, null, false);
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
        if ($row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('AttendId', TableMap::TYPE_PHPNAME, $indexType)] === null) {
            return null;
        }

        return null === $row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('AttendId', TableMap::TYPE_PHPNAME, $indexType)] || is_scalar($row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('AttendId', TableMap::TYPE_PHPNAME, $indexType)]) || is_callable([$row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('AttendId', TableMap::TYPE_PHPNAME, $indexType)], '__toString']) ? (string) $row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('AttendId', TableMap::TYPE_PHPNAME, $indexType)] : $row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('AttendId', TableMap::TYPE_PHPNAME, $indexType)];
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
                : self::translateFieldName('AttendId', TableMap::TYPE_PHPNAME, $indexType)
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
        return $withPrefix ? EventAttendTableMap::CLASS_DEFAULT : EventAttendTableMap::OM_CLASS;
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
     * @return array           (EventAttend object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = EventAttendTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = EventAttendTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + EventAttendTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = EventAttendTableMap::OM_CLASS;
            /** @var EventAttend $obj */
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            EventAttendTableMap::addInstanceToPool($obj, $key);
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
            $key = EventAttendTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = EventAttendTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                /** @var EventAttend $obj */
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                EventAttendTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(EventAttendTableMap::COL_ATTEND_ID);
            $criteria->addSelectColumn(EventAttendTableMap::COL_EVENT_ID);
            $criteria->addSelectColumn(EventAttendTableMap::COL_PERSON_ID);
            $criteria->addSelectColumn(EventAttendTableMap::COL_CHECKIN_DATE);
            $criteria->addSelectColumn(EventAttendTableMap::COL_CHECKIN_ID);
            $criteria->addSelectColumn(EventAttendTableMap::COL_CHECKOUT_DATE);
            $criteria->addSelectColumn(EventAttendTableMap::COL_CHECKOUT_ID);
        } else {
            $criteria->addSelectColumn($alias . '.attend_id');
            $criteria->addSelectColumn($alias . '.event_id');
            $criteria->addSelectColumn($alias . '.person_id');
            $criteria->addSelectColumn($alias . '.checkin_date');
            $criteria->addSelectColumn($alias . '.checkin_id');
            $criteria->addSelectColumn($alias . '.checkout_date');
            $criteria->addSelectColumn($alias . '.checkout_id');
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
        return Propel::getServiceContainer()->getDatabaseMap(EventAttendTableMap::DATABASE_NAME)->getTable(EventAttendTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
        $dbMap = Propel::getServiceContainer()->getDatabaseMap(EventAttendTableMap::DATABASE_NAME);
        if (!$dbMap->hasTable(EventAttendTableMap::TABLE_NAME)) {
            $dbMap->addTableObject(new EventAttendTableMap());
        }
    }

    /**
     * Performs a DELETE on the database, given a EventAttend or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or EventAttend object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(EventAttendTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \ChurchCRM\EventAttend) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(EventAttendTableMap::DATABASE_NAME);
            $criteria->add(EventAttendTableMap::COL_ATTEND_ID, (array) $values, Criteria::IN);
        }

        $query = EventAttendQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) {
            EventAttendTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) {
                EventAttendTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the event_attend table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return EventAttendQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a EventAttend or Criteria object.
     *
     * @param mixed               $criteria Criteria or EventAttend object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(EventAttendTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from EventAttend object
        }

        if ($criteria->containsKey(EventAttendTableMap::COL_ATTEND_ID) && $criteria->keyContainsValue(EventAttendTableMap::COL_ATTEND_ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.EventAttendTableMap::COL_ATTEND_ID.')');
        }


        // Set the correct dbName
        $query = EventAttendQuery::create()->mergeWith($criteria);

        // use transaction because $criteria could contain info
        // for more than one table (I guess, conceivably)
        return $con->transaction(function () use ($con, $query) {
            return $query->doInsert($con);
        });
    }

} // EventAttendTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
EventAttendTableMap::buildTableMap();
