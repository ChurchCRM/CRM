<?php

namespace ChurchCRM\Map;

use ChurchCRM\FundRaiser;
use ChurchCRM\FundRaiserQuery;
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
 * This class defines the structure of the 'fundraiser_fr' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class FundRaiserTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'ChurchCRM.Map.FundRaiserTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'default';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'fundraiser_fr';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\ChurchCRM\\FundRaiser';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'ChurchCRM.FundRaiser';

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
     * the column name for the fr_ID field
     */
    const COL_FR_ID = 'fundraiser_fr.fr_ID';

    /**
     * the column name for the fr_date field
     */
    const COL_FR_DATE = 'fundraiser_fr.fr_date';

    /**
     * the column name for the fr_title field
     */
    const COL_FR_TITLE = 'fundraiser_fr.fr_title';

    /**
     * the column name for the fr_description field
     */
    const COL_FR_DESCRIPTION = 'fundraiser_fr.fr_description';

    /**
     * the column name for the fr_EnteredBy field
     */
    const COL_FR_ENTEREDBY = 'fundraiser_fr.fr_EnteredBy';

    /**
     * the column name for the fr_EnteredDate field
     */
    const COL_FR_ENTEREDDATE = 'fundraiser_fr.fr_EnteredDate';

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
        self::TYPE_PHPNAME       => array('Id', 'Date', 'Title', 'Description', 'EnteredBy', 'EnteredDate', ),
        self::TYPE_CAMELNAME     => array('id', 'date', 'title', 'description', 'enteredBy', 'enteredDate', ),
        self::TYPE_COLNAME       => array(FundRaiserTableMap::COL_FR_ID, FundRaiserTableMap::COL_FR_DATE, FundRaiserTableMap::COL_FR_TITLE, FundRaiserTableMap::COL_FR_DESCRIPTION, FundRaiserTableMap::COL_FR_ENTEREDBY, FundRaiserTableMap::COL_FR_ENTEREDDATE, ),
        self::TYPE_FIELDNAME     => array('fr_ID', 'fr_date', 'fr_title', 'fr_description', 'fr_EnteredBy', 'fr_EnteredDate', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'Date' => 1, 'Title' => 2, 'Description' => 3, 'EnteredBy' => 4, 'EnteredDate' => 5, ),
        self::TYPE_CAMELNAME     => array('id' => 0, 'date' => 1, 'title' => 2, 'description' => 3, 'enteredBy' => 4, 'enteredDate' => 5, ),
        self::TYPE_COLNAME       => array(FundRaiserTableMap::COL_FR_ID => 0, FundRaiserTableMap::COL_FR_DATE => 1, FundRaiserTableMap::COL_FR_TITLE => 2, FundRaiserTableMap::COL_FR_DESCRIPTION => 3, FundRaiserTableMap::COL_FR_ENTEREDBY => 4, FundRaiserTableMap::COL_FR_ENTEREDDATE => 5, ),
        self::TYPE_FIELDNAME     => array('fr_ID' => 0, 'fr_date' => 1, 'fr_title' => 2, 'fr_description' => 3, 'fr_EnteredBy' => 4, 'fr_EnteredDate' => 5, ),
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
        $this->setName('fundraiser_fr');
        $this->setPhpName('FundRaiser');
        $this->setIdentifierQuoting(false);
        $this->setClassName('\\ChurchCRM\\FundRaiser');
        $this->setPackage('ChurchCRM');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('fr_ID', 'Id', 'SMALLINT', true, 9, null);
        $this->addColumn('fr_date', 'Date', 'DATE', false, null, null);
        $this->addColumn('fr_title', 'Title', 'VARCHAR', true, 128, null);
        $this->addColumn('fr_description', 'Description', 'LONGVARCHAR', false, null, null);
        $this->addColumn('fr_EnteredBy', 'EnteredBy', 'SMALLINT', true, 5, 0);
        $this->addColumn('fr_EnteredDate', 'EnteredDate', 'DATE', true, null, null);
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
        return $withPrefix ? FundRaiserTableMap::CLASS_DEFAULT : FundRaiserTableMap::OM_CLASS;
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
     * @return array           (FundRaiser object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = FundRaiserTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = FundRaiserTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + FundRaiserTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = FundRaiserTableMap::OM_CLASS;
            /** @var FundRaiser $obj */
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            FundRaiserTableMap::addInstanceToPool($obj, $key);
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
            $key = FundRaiserTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = FundRaiserTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                /** @var FundRaiser $obj */
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                FundRaiserTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(FundRaiserTableMap::COL_FR_ID);
            $criteria->addSelectColumn(FundRaiserTableMap::COL_FR_DATE);
            $criteria->addSelectColumn(FundRaiserTableMap::COL_FR_TITLE);
            $criteria->addSelectColumn(FundRaiserTableMap::COL_FR_DESCRIPTION);
            $criteria->addSelectColumn(FundRaiserTableMap::COL_FR_ENTEREDBY);
            $criteria->addSelectColumn(FundRaiserTableMap::COL_FR_ENTEREDDATE);
        } else {
            $criteria->addSelectColumn($alias . '.fr_ID');
            $criteria->addSelectColumn($alias . '.fr_date');
            $criteria->addSelectColumn($alias . '.fr_title');
            $criteria->addSelectColumn($alias . '.fr_description');
            $criteria->addSelectColumn($alias . '.fr_EnteredBy');
            $criteria->addSelectColumn($alias . '.fr_EnteredDate');
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
        return Propel::getServiceContainer()->getDatabaseMap(FundRaiserTableMap::DATABASE_NAME)->getTable(FundRaiserTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
        $dbMap = Propel::getServiceContainer()->getDatabaseMap(FundRaiserTableMap::DATABASE_NAME);
        if (!$dbMap->hasTable(FundRaiserTableMap::TABLE_NAME)) {
            $dbMap->addTableObject(new FundRaiserTableMap());
        }
    }

    /**
     * Performs a DELETE on the database, given a FundRaiser or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or FundRaiser object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(FundRaiserTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \ChurchCRM\FundRaiser) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(FundRaiserTableMap::DATABASE_NAME);
            $criteria->add(FundRaiserTableMap::COL_FR_ID, (array) $values, Criteria::IN);
        }

        $query = FundRaiserQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) {
            FundRaiserTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) {
                FundRaiserTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the fundraiser_fr table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return FundRaiserQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a FundRaiser or Criteria object.
     *
     * @param mixed               $criteria Criteria or FundRaiser object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(FundRaiserTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from FundRaiser object
        }

        if ($criteria->containsKey(FundRaiserTableMap::COL_FR_ID) && $criteria->keyContainsValue(FundRaiserTableMap::COL_FR_ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.FundRaiserTableMap::COL_FR_ID.')');
        }


        // Set the correct dbName
        $query = FundRaiserQuery::create()->mergeWith($criteria);

        // use transaction because $criteria could contain info
        // for more than one table (I guess, conceivably)
        return $con->transaction(function () use ($con, $query) {
            return $query->doInsert($con);
        });
    }

} // FundRaiserTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
FundRaiserTableMap::buildTableMap();
