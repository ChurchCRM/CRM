<?php

namespace ChurchCRM\Map;

use ChurchCRM\CanvassData;
use ChurchCRM\CanvassDataQuery;
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
 * This class defines the structure of the 'canvassdata_can' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class CanvassDataTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'ChurchCRM.Map.CanvassDataTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'default';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'canvassdata_can';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\ChurchCRM\\CanvassData';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'ChurchCRM.CanvassData';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 12;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 12;

    /**
     * the column name for the can_ID field
     */
    const COL_CAN_ID = 'canvassdata_can.can_ID';

    /**
     * the column name for the can_famID field
     */
    const COL_CAN_FAMID = 'canvassdata_can.can_famID';

    /**
     * the column name for the can_Canvasser field
     */
    const COL_CAN_CANVASSER = 'canvassdata_can.can_Canvasser';

    /**
     * the column name for the can_FYID field
     */
    const COL_CAN_FYID = 'canvassdata_can.can_FYID';

    /**
     * the column name for the can_date field
     */
    const COL_CAN_DATE = 'canvassdata_can.can_date';

    /**
     * the column name for the can_Positive field
     */
    const COL_CAN_POSITIVE = 'canvassdata_can.can_Positive';

    /**
     * the column name for the can_Critical field
     */
    const COL_CAN_CRITICAL = 'canvassdata_can.can_Critical';

    /**
     * the column name for the can_Insightful field
     */
    const COL_CAN_INSIGHTFUL = 'canvassdata_can.can_Insightful';

    /**
     * the column name for the can_Financial field
     */
    const COL_CAN_FINANCIAL = 'canvassdata_can.can_Financial';

    /**
     * the column name for the can_Suggestion field
     */
    const COL_CAN_SUGGESTION = 'canvassdata_can.can_Suggestion';

    /**
     * the column name for the can_NotInterested field
     */
    const COL_CAN_NOTINTERESTED = 'canvassdata_can.can_NotInterested';

    /**
     * the column name for the can_WhyNotInterested field
     */
    const COL_CAN_WHYNOTINTERESTED = 'canvassdata_can.can_WhyNotInterested';

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
        self::TYPE_PHPNAME       => array('Id', 'FamilyId', 'Canvasser', 'Fyid', 'Date', 'Positive', 'Critical', 'Insightful', 'Financial', 'Suggestion', 'NotInterested', 'WhyNotInterested', ),
        self::TYPE_CAMELNAME     => array('id', 'familyId', 'canvasser', 'fyid', 'date', 'positive', 'critical', 'insightful', 'financial', 'suggestion', 'notInterested', 'whyNotInterested', ),
        self::TYPE_COLNAME       => array(CanvassDataTableMap::COL_CAN_ID, CanvassDataTableMap::COL_CAN_FAMID, CanvassDataTableMap::COL_CAN_CANVASSER, CanvassDataTableMap::COL_CAN_FYID, CanvassDataTableMap::COL_CAN_DATE, CanvassDataTableMap::COL_CAN_POSITIVE, CanvassDataTableMap::COL_CAN_CRITICAL, CanvassDataTableMap::COL_CAN_INSIGHTFUL, CanvassDataTableMap::COL_CAN_FINANCIAL, CanvassDataTableMap::COL_CAN_SUGGESTION, CanvassDataTableMap::COL_CAN_NOTINTERESTED, CanvassDataTableMap::COL_CAN_WHYNOTINTERESTED, ),
        self::TYPE_FIELDNAME     => array('can_ID', 'can_famID', 'can_Canvasser', 'can_FYID', 'can_date', 'can_Positive', 'can_Critical', 'can_Insightful', 'can_Financial', 'can_Suggestion', 'can_NotInterested', 'can_WhyNotInterested', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'FamilyId' => 1, 'Canvasser' => 2, 'Fyid' => 3, 'Date' => 4, 'Positive' => 5, 'Critical' => 6, 'Insightful' => 7, 'Financial' => 8, 'Suggestion' => 9, 'NotInterested' => 10, 'WhyNotInterested' => 11, ),
        self::TYPE_CAMELNAME     => array('id' => 0, 'familyId' => 1, 'canvasser' => 2, 'fyid' => 3, 'date' => 4, 'positive' => 5, 'critical' => 6, 'insightful' => 7, 'financial' => 8, 'suggestion' => 9, 'notInterested' => 10, 'whyNotInterested' => 11, ),
        self::TYPE_COLNAME       => array(CanvassDataTableMap::COL_CAN_ID => 0, CanvassDataTableMap::COL_CAN_FAMID => 1, CanvassDataTableMap::COL_CAN_CANVASSER => 2, CanvassDataTableMap::COL_CAN_FYID => 3, CanvassDataTableMap::COL_CAN_DATE => 4, CanvassDataTableMap::COL_CAN_POSITIVE => 5, CanvassDataTableMap::COL_CAN_CRITICAL => 6, CanvassDataTableMap::COL_CAN_INSIGHTFUL => 7, CanvassDataTableMap::COL_CAN_FINANCIAL => 8, CanvassDataTableMap::COL_CAN_SUGGESTION => 9, CanvassDataTableMap::COL_CAN_NOTINTERESTED => 10, CanvassDataTableMap::COL_CAN_WHYNOTINTERESTED => 11, ),
        self::TYPE_FIELDNAME     => array('can_ID' => 0, 'can_famID' => 1, 'can_Canvasser' => 2, 'can_FYID' => 3, 'can_date' => 4, 'can_Positive' => 5, 'can_Critical' => 6, 'can_Insightful' => 7, 'can_Financial' => 8, 'can_Suggestion' => 9, 'can_NotInterested' => 10, 'can_WhyNotInterested' => 11, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, )
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
        $this->setName('canvassdata_can');
        $this->setPhpName('CanvassData');
        $this->setIdentifierQuoting(false);
        $this->setClassName('\\ChurchCRM\\CanvassData');
        $this->setPackage('ChurchCRM');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('can_ID', 'Id', 'SMALLINT', true, 9, null);
        $this->addColumn('can_famID', 'FamilyId', 'SMALLINT', true, 9, 0);
        $this->addColumn('can_Canvasser', 'Canvasser', 'SMALLINT', true, 9, 0);
        $this->addColumn('can_FYID', 'Fyid', 'SMALLINT', false, 9, null);
        $this->addColumn('can_date', 'Date', 'DATE', false, null, null);
        $this->addColumn('can_Positive', 'Positive', 'LONGVARCHAR', false, null, null);
        $this->addColumn('can_Critical', 'Critical', 'LONGVARCHAR', false, null, null);
        $this->addColumn('can_Insightful', 'Insightful', 'LONGVARCHAR', false, null, null);
        $this->addColumn('can_Financial', 'Financial', 'LONGVARCHAR', false, null, null);
        $this->addColumn('can_Suggestion', 'Suggestion', 'LONGVARCHAR', false, null, null);
        $this->addColumn('can_NotInterested', 'NotInterested', 'BOOLEAN', true, 1, false);
        $this->addColumn('can_WhyNotInterested', 'WhyNotInterested', 'LONGVARCHAR', false, null, null);
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
        return $withPrefix ? CanvassDataTableMap::CLASS_DEFAULT : CanvassDataTableMap::OM_CLASS;
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
     * @return array           (CanvassData object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = CanvassDataTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = CanvassDataTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + CanvassDataTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = CanvassDataTableMap::OM_CLASS;
            /** @var CanvassData $obj */
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            CanvassDataTableMap::addInstanceToPool($obj, $key);
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
            $key = CanvassDataTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = CanvassDataTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                /** @var CanvassData $obj */
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                CanvassDataTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(CanvassDataTableMap::COL_CAN_ID);
            $criteria->addSelectColumn(CanvassDataTableMap::COL_CAN_FAMID);
            $criteria->addSelectColumn(CanvassDataTableMap::COL_CAN_CANVASSER);
            $criteria->addSelectColumn(CanvassDataTableMap::COL_CAN_FYID);
            $criteria->addSelectColumn(CanvassDataTableMap::COL_CAN_DATE);
            $criteria->addSelectColumn(CanvassDataTableMap::COL_CAN_POSITIVE);
            $criteria->addSelectColumn(CanvassDataTableMap::COL_CAN_CRITICAL);
            $criteria->addSelectColumn(CanvassDataTableMap::COL_CAN_INSIGHTFUL);
            $criteria->addSelectColumn(CanvassDataTableMap::COL_CAN_FINANCIAL);
            $criteria->addSelectColumn(CanvassDataTableMap::COL_CAN_SUGGESTION);
            $criteria->addSelectColumn(CanvassDataTableMap::COL_CAN_NOTINTERESTED);
            $criteria->addSelectColumn(CanvassDataTableMap::COL_CAN_WHYNOTINTERESTED);
        } else {
            $criteria->addSelectColumn($alias . '.can_ID');
            $criteria->addSelectColumn($alias . '.can_famID');
            $criteria->addSelectColumn($alias . '.can_Canvasser');
            $criteria->addSelectColumn($alias . '.can_FYID');
            $criteria->addSelectColumn($alias . '.can_date');
            $criteria->addSelectColumn($alias . '.can_Positive');
            $criteria->addSelectColumn($alias . '.can_Critical');
            $criteria->addSelectColumn($alias . '.can_Insightful');
            $criteria->addSelectColumn($alias . '.can_Financial');
            $criteria->addSelectColumn($alias . '.can_Suggestion');
            $criteria->addSelectColumn($alias . '.can_NotInterested');
            $criteria->addSelectColumn($alias . '.can_WhyNotInterested');
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
        return Propel::getServiceContainer()->getDatabaseMap(CanvassDataTableMap::DATABASE_NAME)->getTable(CanvassDataTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
        $dbMap = Propel::getServiceContainer()->getDatabaseMap(CanvassDataTableMap::DATABASE_NAME);
        if (!$dbMap->hasTable(CanvassDataTableMap::TABLE_NAME)) {
            $dbMap->addTableObject(new CanvassDataTableMap());
        }
    }

    /**
     * Performs a DELETE on the database, given a CanvassData or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or CanvassData object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(CanvassDataTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \ChurchCRM\CanvassData) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(CanvassDataTableMap::DATABASE_NAME);
            $criteria->add(CanvassDataTableMap::COL_CAN_ID, (array) $values, Criteria::IN);
        }

        $query = CanvassDataQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) {
            CanvassDataTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) {
                CanvassDataTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the canvassdata_can table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return CanvassDataQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a CanvassData or Criteria object.
     *
     * @param mixed               $criteria Criteria or CanvassData object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(CanvassDataTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from CanvassData object
        }

        if ($criteria->containsKey(CanvassDataTableMap::COL_CAN_ID) && $criteria->keyContainsValue(CanvassDataTableMap::COL_CAN_ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.CanvassDataTableMap::COL_CAN_ID.')');
        }


        // Set the correct dbName
        $query = CanvassDataQuery::create()->mergeWith($criteria);

        // use transaction because $criteria could contain info
        // for more than one table (I guess, conceivably)
        return $con->transaction(function () use ($con, $query) {
            return $query->doInsert($con);
        });
    }

} // CanvassDataTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
CanvassDataTableMap::buildTableMap();
