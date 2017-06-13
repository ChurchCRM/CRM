<?php

namespace ChurchCRM\Map;

use ChurchCRM\Deposit;
use ChurchCRM\DepositQuery;
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
 * This class defines the structure of the 'deposit_dep' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class DepositTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'ChurchCRM.Map.DepositTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'default';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'deposit_dep';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\ChurchCRM\\Deposit';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'ChurchCRM.Deposit';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 6;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 6;

    /**
     * the column name for the dep_ID field
     */
    const COL_DEP_ID = 'deposit_dep.dep_ID';

    /**
     * the column name for the dep_Date field
     */
    const COL_DEP_DATE = 'deposit_dep.dep_Date';

    /**
     * the column name for the dep_Comment field
     */
    const COL_DEP_COMMENT = 'deposit_dep.dep_Comment';

    /**
     * the column name for the dep_EnteredBy field
     */
    const COL_DEP_ENTEREDBY = 'deposit_dep.dep_EnteredBy';

    /**
     * the column name for the dep_Closed field
     */
    const COL_DEP_CLOSED = 'deposit_dep.dep_Closed';

    /**
     * the column name for the dep_Type field
     */
    const COL_DEP_TYPE = 'deposit_dep.dep_Type';

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
        self::TYPE_PHPNAME       => array('Id', 'Date', 'Comment', 'Enteredby', 'Closed', 'Type', ),
        self::TYPE_CAMELNAME     => array('id', 'date', 'comment', 'enteredby', 'closed', 'type', ),
        self::TYPE_COLNAME       => array(DepositTableMap::COL_DEP_ID, DepositTableMap::COL_DEP_DATE, DepositTableMap::COL_DEP_COMMENT, DepositTableMap::COL_DEP_ENTEREDBY, DepositTableMap::COL_DEP_CLOSED, DepositTableMap::COL_DEP_TYPE, ),
        self::TYPE_FIELDNAME     => array('dep_ID', 'dep_Date', 'dep_Comment', 'dep_EnteredBy', 'dep_Closed', 'dep_Type', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'Date' => 1, 'Comment' => 2, 'Enteredby' => 3, 'Closed' => 4, 'Type' => 5, ),
        self::TYPE_CAMELNAME     => array('id' => 0, 'date' => 1, 'comment' => 2, 'enteredby' => 3, 'closed' => 4, 'type' => 5, ),
        self::TYPE_COLNAME       => array(DepositTableMap::COL_DEP_ID => 0, DepositTableMap::COL_DEP_DATE => 1, DepositTableMap::COL_DEP_COMMENT => 2, DepositTableMap::COL_DEP_ENTEREDBY => 3, DepositTableMap::COL_DEP_CLOSED => 4, DepositTableMap::COL_DEP_TYPE => 5, ),
        self::TYPE_FIELDNAME     => array('dep_ID' => 0, 'dep_Date' => 1, 'dep_Comment' => 2, 'dep_EnteredBy' => 3, 'dep_Closed' => 4, 'dep_Type' => 5, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, )
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
        $this->setName('deposit_dep');
        $this->setPhpName('Deposit');
        $this->setIdentifierQuoting(false);
        $this->setClassName('\\ChurchCRM\\Deposit');
        $this->setPackage('ChurchCRM');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('dep_ID', 'Id', 'SMALLINT', true, 9, null);
        $this->addColumn('dep_Date', 'Date', 'DATE', false, null, null);
        $this->addColumn('dep_Comment', 'Comment', 'LONGVARCHAR', false, null, null);
        $this->addColumn('dep_EnteredBy', 'Enteredby', 'SMALLINT', false, 9, null);
        $this->addColumn('dep_Closed', 'Closed', 'BOOLEAN', true, 1, false);
        $this->addColumn('dep_Type', 'Type', 'CHAR', true, null, 'Bank');
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('Pledge', '\\ChurchCRM\\Pledge', RelationMap::ONE_TO_MANY, array (
  0 =>
  array (
    0 => ':plg_depID',
    1 => ':dep_ID',
  ),
), null, null, 'Pledges', false);
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
        return $withPrefix ? DepositTableMap::CLASS_DEFAULT : DepositTableMap::OM_CLASS;
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
     * @return array           (Deposit object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = DepositTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = DepositTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + DepositTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = DepositTableMap::OM_CLASS;
            /** @var Deposit $obj */
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            DepositTableMap::addInstanceToPool($obj, $key);
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
            $key = DepositTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = DepositTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                /** @var Deposit $obj */
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                DepositTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(DepositTableMap::COL_DEP_ID);
            $criteria->addSelectColumn(DepositTableMap::COL_DEP_DATE);
            $criteria->addSelectColumn(DepositTableMap::COL_DEP_COMMENT);
            $criteria->addSelectColumn(DepositTableMap::COL_DEP_ENTEREDBY);
            $criteria->addSelectColumn(DepositTableMap::COL_DEP_CLOSED);
            $criteria->addSelectColumn(DepositTableMap::COL_DEP_TYPE);
        } else {
            $criteria->addSelectColumn($alias . '.dep_ID');
            $criteria->addSelectColumn($alias . '.dep_Date');
            $criteria->addSelectColumn($alias . '.dep_Comment');
            $criteria->addSelectColumn($alias . '.dep_EnteredBy');
            $criteria->addSelectColumn($alias . '.dep_Closed');
            $criteria->addSelectColumn($alias . '.dep_Type');
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
        return Propel::getServiceContainer()->getDatabaseMap(DepositTableMap::DATABASE_NAME)->getTable(DepositTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
        $dbMap = Propel::getServiceContainer()->getDatabaseMap(DepositTableMap::DATABASE_NAME);
        if (!$dbMap->hasTable(DepositTableMap::TABLE_NAME)) {
            $dbMap->addTableObject(new DepositTableMap());
        }
    }

    /**
     * Performs a DELETE on the database, given a Deposit or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or Deposit object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(DepositTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \ChurchCRM\Deposit) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(DepositTableMap::DATABASE_NAME);
            $criteria->add(DepositTableMap::COL_DEP_ID, (array) $values, Criteria::IN);
        }

        $query = DepositQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) {
            DepositTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) {
                DepositTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the deposit_dep table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return DepositQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a Deposit or Criteria object.
     *
     * @param mixed               $criteria Criteria or Deposit object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(DepositTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from Deposit object
        }

        if ($criteria->containsKey(DepositTableMap::COL_DEP_ID) && $criteria->keyContainsValue(DepositTableMap::COL_DEP_ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.DepositTableMap::COL_DEP_ID.')');
        }


        // Set the correct dbName
        $query = DepositQuery::create()->mergeWith($criteria);

        // use transaction because $criteria could contain info
        // for more than one table (I guess, conceivably)
        return $con->transaction(function () use ($con, $query) {
            return $query->doInsert($con);
        });
    }

} // DepositTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
DepositTableMap::buildTableMap();
