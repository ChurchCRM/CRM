<?php

namespace ChurchCRM\Map;

use ChurchCRM\GroupPropMaster;
use ChurchCRM\GroupPropMasterQuery;
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
 * This class defines the structure of the 'groupprop_master' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class GroupPropMasterTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'ChurchCRM.Map.GroupPropMasterTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'default';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'groupprop_master';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\ChurchCRM\\GroupPropMaster';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'ChurchCRM.GroupPropMaster';

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
     * the column name for the grp_ID field
     */
    const COL_GRP_ID = 'groupprop_master.grp_ID';

    /**
     * the column name for the prop_ID field
     */
    const COL_PROP_ID = 'groupprop_master.prop_ID';

    /**
     * the column name for the prop_Field field
     */
    const COL_PROP_FIELD = 'groupprop_master.prop_Field';

    /**
     * the column name for the prop_Name field
     */
    const COL_PROP_NAME = 'groupprop_master.prop_Name';

    /**
     * the column name for the prop_Description field
     */
    const COL_PROP_DESCRIPTION = 'groupprop_master.prop_Description';

    /**
     * the column name for the type_ID field
     */
    const COL_TYPE_ID = 'groupprop_master.type_ID';

    /**
     * the column name for the prop_Special field
     */
    const COL_PROP_SPECIAL = 'groupprop_master.prop_Special';

    /**
     * the column name for the prop_PersonDisplay field
     */
    const COL_PROP_PERSONDISPLAY = 'groupprop_master.prop_PersonDisplay';

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
        self::TYPE_PHPNAME       => array('Id', 'Id', 'Field', 'Name', 'Description', 'TypeId', 'Special', 'PersonDisplay', ),
        self::TYPE_CAMELNAME     => array('id', 'id', 'field', 'name', 'description', 'typeId', 'special', 'personDisplay', ),
        self::TYPE_COLNAME       => array(GroupPropMasterTableMap::COL_GRP_ID, GroupPropMasterTableMap::COL_PROP_ID, GroupPropMasterTableMap::COL_PROP_FIELD, GroupPropMasterTableMap::COL_PROP_NAME, GroupPropMasterTableMap::COL_PROP_DESCRIPTION, GroupPropMasterTableMap::COL_TYPE_ID, GroupPropMasterTableMap::COL_PROP_SPECIAL, GroupPropMasterTableMap::COL_PROP_PERSONDISPLAY, ),
        self::TYPE_FIELDNAME     => array('grp_ID', 'prop_ID', 'prop_Field', 'prop_Name', 'prop_Description', 'type_ID', 'prop_Special', 'prop_PersonDisplay', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'Id' => 1, 'Field' => 2, 'Name' => 3, 'Description' => 4, 'TypeId' => 5, 'Special' => 6, 'PersonDisplay' => 7, ),
        self::TYPE_CAMELNAME     => array('id' => 0, 'id' => 1, 'field' => 2, 'name' => 3, 'description' => 4, 'typeId' => 5, 'special' => 6, 'personDisplay' => 7, ),
        self::TYPE_COLNAME       => array(GroupPropMasterTableMap::COL_GRP_ID => 0, GroupPropMasterTableMap::COL_PROP_ID => 1, GroupPropMasterTableMap::COL_PROP_FIELD => 2, GroupPropMasterTableMap::COL_PROP_NAME => 3, GroupPropMasterTableMap::COL_PROP_DESCRIPTION => 4, GroupPropMasterTableMap::COL_TYPE_ID => 5, GroupPropMasterTableMap::COL_PROP_SPECIAL => 6, GroupPropMasterTableMap::COL_PROP_PERSONDISPLAY => 7, ),
        self::TYPE_FIELDNAME     => array('grp_ID' => 0, 'prop_ID' => 1, 'prop_Field' => 2, 'prop_Name' => 3, 'prop_Description' => 4, 'type_ID' => 5, 'prop_Special' => 6, 'prop_PersonDisplay' => 7, ),
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
        $this->setName('groupprop_master');
        $this->setPhpName('GroupPropMaster');
        $this->setIdentifierQuoting(false);
        $this->setClassName('\\ChurchCRM\\GroupPropMaster');
        $this->setPackage('ChurchCRM');
        $this->setUseIdGenerator(false);
        // columns
        $this->addColumn('grp_ID', 'Id', 'SMALLINT', true, 9, 0);
        $this->addColumn('prop_ID', 'Id', 'TINYINT', true, 3, 0);
        $this->addColumn('prop_Field', 'Field', 'VARCHAR', true, 5, '0');
        $this->addColumn('prop_Name', 'Name', 'VARCHAR', false, 40, null);
        $this->addColumn('prop_Description', 'Description', 'VARCHAR', false, 60, null);
        $this->addColumn('type_ID', 'TypeId', 'SMALLINT', true, 5, 0);
        $this->addColumn('prop_Special', 'Special', 'SMALLINT', false, 9, null);
        $this->addColumn('prop_PersonDisplay', 'PersonDisplay', 'CHAR', true, null, 'false');
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
        return $withPrefix ? GroupPropMasterTableMap::CLASS_DEFAULT : GroupPropMasterTableMap::OM_CLASS;
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
     * @return array           (GroupPropMaster object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = GroupPropMasterTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = GroupPropMasterTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + GroupPropMasterTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = GroupPropMasterTableMap::OM_CLASS;
            /** @var GroupPropMaster $obj */
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            GroupPropMasterTableMap::addInstanceToPool($obj, $key);
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
            $key = GroupPropMasterTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = GroupPropMasterTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                /** @var GroupPropMaster $obj */
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                GroupPropMasterTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(GroupPropMasterTableMap::COL_GRP_ID);
            $criteria->addSelectColumn(GroupPropMasterTableMap::COL_PROP_ID);
            $criteria->addSelectColumn(GroupPropMasterTableMap::COL_PROP_FIELD);
            $criteria->addSelectColumn(GroupPropMasterTableMap::COL_PROP_NAME);
            $criteria->addSelectColumn(GroupPropMasterTableMap::COL_PROP_DESCRIPTION);
            $criteria->addSelectColumn(GroupPropMasterTableMap::COL_TYPE_ID);
            $criteria->addSelectColumn(GroupPropMasterTableMap::COL_PROP_SPECIAL);
            $criteria->addSelectColumn(GroupPropMasterTableMap::COL_PROP_PERSONDISPLAY);
        } else {
            $criteria->addSelectColumn($alias . '.grp_ID');
            $criteria->addSelectColumn($alias . '.prop_ID');
            $criteria->addSelectColumn($alias . '.prop_Field');
            $criteria->addSelectColumn($alias . '.prop_Name');
            $criteria->addSelectColumn($alias . '.prop_Description');
            $criteria->addSelectColumn($alias . '.type_ID');
            $criteria->addSelectColumn($alias . '.prop_Special');
            $criteria->addSelectColumn($alias . '.prop_PersonDisplay');
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
        return Propel::getServiceContainer()->getDatabaseMap(GroupPropMasterTableMap::DATABASE_NAME)->getTable(GroupPropMasterTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
        $dbMap = Propel::getServiceContainer()->getDatabaseMap(GroupPropMasterTableMap::DATABASE_NAME);
        if (!$dbMap->hasTable(GroupPropMasterTableMap::TABLE_NAME)) {
            $dbMap->addTableObject(new GroupPropMasterTableMap());
        }
    }

    /**
     * Performs a DELETE on the database, given a GroupPropMaster or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or GroupPropMaster object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(GroupPropMasterTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \ChurchCRM\GroupPropMaster) { // it's a model object
            // create criteria based on pk value
            $criteria = $values->buildCriteria();
        } else { // it's a primary key, or an array of pks
            throw new LogicException('The GroupPropMaster object has no primary key');
        }

        $query = GroupPropMasterQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) {
            GroupPropMasterTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) {
                GroupPropMasterTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the groupprop_master table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return GroupPropMasterQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a GroupPropMaster or Criteria object.
     *
     * @param mixed               $criteria Criteria or GroupPropMaster object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(GroupPropMasterTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from GroupPropMaster object
        }


        // Set the correct dbName
        $query = GroupPropMasterQuery::create()->mergeWith($criteria);

        // use transaction because $criteria could contain info
        // for more than one table (I guess, conceivably)
        return $con->transaction(function () use ($con, $query) {
            return $query->doInsert($con);
        });
    }

} // GroupPropMasterTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
GroupPropMasterTableMap::buildTableMap();
