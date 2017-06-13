<?php

namespace ChurchCRM\Map;

use ChurchCRM\Egive;
use ChurchCRM\EgiveQuery;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\InstancePoolTrait;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\DataFetcher\DataFetcherInterface;
use Propel\Runtime\Exception\LogicException;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Map\RelationMap;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Map\TableMapTrait;


/**
 * This class defines the structure of the 'egive_egv' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class EgiveTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'ChurchCRM.Map.EgiveTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'default';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'egive_egv';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\ChurchCRM\\Egive';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'ChurchCRM.Egive';

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
     * the column name for the egv_egiveID field
     */
    const COL_EGV_EGIVEID = 'egive_egv.egv_egiveID';

    /**
     * the column name for the egv_famID field
     */
    const COL_EGV_FAMID = 'egive_egv.egv_famID';

    /**
     * the column name for the egv_DateEntered field
     */
    const COL_EGV_DATEENTERED = 'egive_egv.egv_DateEntered';

    /**
     * the column name for the egv_DateLastEdited field
     */
    const COL_EGV_DATELASTEDITED = 'egive_egv.egv_DateLastEdited';

    /**
     * the column name for the egv_EnteredBy field
     */
    const COL_EGV_ENTEREDBY = 'egive_egv.egv_EnteredBy';

    /**
     * the column name for the egv_EditedBy field
     */
    const COL_EGV_EDITEDBY = 'egive_egv.egv_EditedBy';

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
        self::TYPE_PHPNAME       => array('EgiveId', 'FamilyId', 'DateEntered', 'DateLastEdited', 'EnteredBy', 'EditedBy', ),
        self::TYPE_CAMELNAME     => array('egiveId', 'familyId', 'dateEntered', 'dateLastEdited', 'enteredBy', 'editedBy', ),
        self::TYPE_COLNAME       => array(EgiveTableMap::COL_EGV_EGIVEID, EgiveTableMap::COL_EGV_FAMID, EgiveTableMap::COL_EGV_DATEENTERED, EgiveTableMap::COL_EGV_DATELASTEDITED, EgiveTableMap::COL_EGV_ENTEREDBY, EgiveTableMap::COL_EGV_EDITEDBY, ),
        self::TYPE_FIELDNAME     => array('egv_egiveID', 'egv_famID', 'egv_DateEntered', 'egv_DateLastEdited', 'egv_EnteredBy', 'egv_EditedBy', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('EgiveId' => 0, 'FamilyId' => 1, 'DateEntered' => 2, 'DateLastEdited' => 3, 'EnteredBy' => 4, 'EditedBy' => 5, ),
        self::TYPE_CAMELNAME     => array('egiveId' => 0, 'familyId' => 1, 'dateEntered' => 2, 'dateLastEdited' => 3, 'enteredBy' => 4, 'editedBy' => 5, ),
        self::TYPE_COLNAME       => array(EgiveTableMap::COL_EGV_EGIVEID => 0, EgiveTableMap::COL_EGV_FAMID => 1, EgiveTableMap::COL_EGV_DATEENTERED => 2, EgiveTableMap::COL_EGV_DATELASTEDITED => 3, EgiveTableMap::COL_EGV_ENTEREDBY => 4, EgiveTableMap::COL_EGV_EDITEDBY => 5, ),
        self::TYPE_FIELDNAME     => array('egv_egiveID' => 0, 'egv_famID' => 1, 'egv_DateEntered' => 2, 'egv_DateLastEdited' => 3, 'egv_EnteredBy' => 4, 'egv_EditedBy' => 5, ),
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
        $this->setName('egive_egv');
        $this->setPhpName('Egive');
        $this->setIdentifierQuoting(false);
        $this->setClassName('\\ChurchCRM\\Egive');
        $this->setPackage('ChurchCRM');
        $this->setUseIdGenerator(false);
        // columns
        $this->addColumn('egv_egiveID', 'EgiveId', 'VARCHAR', true, 16, null);
        $this->addColumn('egv_famID', 'FamilyId', 'INTEGER', true, null, null);
        $this->addColumn('egv_DateEntered', 'DateEntered', 'TIMESTAMP', true, null, null);
        $this->addColumn('egv_DateLastEdited', 'DateLastEdited', 'TIMESTAMP', true, null, null);
        $this->addColumn('egv_EnteredBy', 'EnteredBy', 'SMALLINT', true, null, 0);
        $this->addColumn('egv_EditedBy', 'EditedBy', 'SMALLINT', true, null, 0);
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
        return null;
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
        return '';
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
        return $withPrefix ? EgiveTableMap::CLASS_DEFAULT : EgiveTableMap::OM_CLASS;
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
     * @return array           (Egive object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = EgiveTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = EgiveTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + EgiveTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = EgiveTableMap::OM_CLASS;
            /** @var Egive $obj */
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            EgiveTableMap::addInstanceToPool($obj, $key);
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
            $key = EgiveTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = EgiveTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                /** @var Egive $obj */
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                EgiveTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(EgiveTableMap::COL_EGV_EGIVEID);
            $criteria->addSelectColumn(EgiveTableMap::COL_EGV_FAMID);
            $criteria->addSelectColumn(EgiveTableMap::COL_EGV_DATEENTERED);
            $criteria->addSelectColumn(EgiveTableMap::COL_EGV_DATELASTEDITED);
            $criteria->addSelectColumn(EgiveTableMap::COL_EGV_ENTEREDBY);
            $criteria->addSelectColumn(EgiveTableMap::COL_EGV_EDITEDBY);
        } else {
            $criteria->addSelectColumn($alias . '.egv_egiveID');
            $criteria->addSelectColumn($alias . '.egv_famID');
            $criteria->addSelectColumn($alias . '.egv_DateEntered');
            $criteria->addSelectColumn($alias . '.egv_DateLastEdited');
            $criteria->addSelectColumn($alias . '.egv_EnteredBy');
            $criteria->addSelectColumn($alias . '.egv_EditedBy');
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
        return Propel::getServiceContainer()->getDatabaseMap(EgiveTableMap::DATABASE_NAME)->getTable(EgiveTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
        $dbMap = Propel::getServiceContainer()->getDatabaseMap(EgiveTableMap::DATABASE_NAME);
        if (!$dbMap->hasTable(EgiveTableMap::TABLE_NAME)) {
            $dbMap->addTableObject(new EgiveTableMap());
        }
    }

    /**
     * Performs a DELETE on the database, given a Egive or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or Egive object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(EgiveTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \ChurchCRM\Egive) { // it's a model object
            // create criteria based on pk value
            $criteria = $values->buildCriteria();
        } else { // it's a primary key, or an array of pks
            throw new LogicException('The Egive object has no primary key');
        }

        $query = EgiveQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) {
            EgiveTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) {
                EgiveTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the egive_egv table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return EgiveQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a Egive or Criteria object.
     *
     * @param mixed               $criteria Criteria or Egive object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(EgiveTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from Egive object
        }


        // Set the correct dbName
        $query = EgiveQuery::create()->mergeWith($criteria);

        // use transaction because $criteria could contain info
        // for more than one table (I guess, conceivably)
        return $con->transaction(function () use ($con, $query) {
            return $query->doInsert($con);
        });
    }

} // EgiveTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
EgiveTableMap::buildTableMap();
