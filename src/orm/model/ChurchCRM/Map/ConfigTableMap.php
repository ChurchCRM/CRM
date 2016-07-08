<?php

namespace ChurchCRM\Map;

use ChurchCRM\Config;
use ChurchCRM\ConfigQuery;
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
 * This class defines the structure of the 'config_cfg' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class ConfigTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'ChurchCRM.Map.ConfigTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'default';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'config_cfg';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\ChurchCRM\\Config';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'ChurchCRM.Config';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 9;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 9;

    /**
     * the column name for the cfg_id field
     */
    const COL_CFG_ID = 'config_cfg.cfg_id';

    /**
     * the column name for the cfg_name field
     */
    const COL_CFG_NAME = 'config_cfg.cfg_name';

    /**
     * the column name for the cfg_value field
     */
    const COL_CFG_VALUE = 'config_cfg.cfg_value';

    /**
     * the column name for the cfg_type field
     */
    const COL_CFG_TYPE = 'config_cfg.cfg_type';

    /**
     * the column name for the cfg_default field
     */
    const COL_CFG_DEFAULT = 'config_cfg.cfg_default';

    /**
     * the column name for the cfg_tooltip field
     */
    const COL_CFG_TOOLTIP = 'config_cfg.cfg_tooltip';

    /**
     * the column name for the cfg_section field
     */
    const COL_CFG_SECTION = 'config_cfg.cfg_section';

    /**
     * the column name for the cfg_category field
     */
    const COL_CFG_CATEGORY = 'config_cfg.cfg_category';

    /**
     * the column name for the cfg_order field
     */
    const COL_CFG_ORDER = 'config_cfg.cfg_order';

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
        self::TYPE_PHPNAME       => array('Id', 'Name', 'Value', 'Type', 'Default', 'Tooltip', 'Section', 'Category', 'Order', ),
        self::TYPE_CAMELNAME     => array('id', 'name', 'value', 'type', 'default', 'tooltip', 'section', 'category', 'order', ),
        self::TYPE_COLNAME       => array(ConfigTableMap::COL_CFG_ID, ConfigTableMap::COL_CFG_NAME, ConfigTableMap::COL_CFG_VALUE, ConfigTableMap::COL_CFG_TYPE, ConfigTableMap::COL_CFG_DEFAULT, ConfigTableMap::COL_CFG_TOOLTIP, ConfigTableMap::COL_CFG_SECTION, ConfigTableMap::COL_CFG_CATEGORY, ConfigTableMap::COL_CFG_ORDER, ),
        self::TYPE_FIELDNAME     => array('cfg_id', 'cfg_name', 'cfg_value', 'cfg_type', 'cfg_default', 'cfg_tooltip', 'cfg_section', 'cfg_category', 'cfg_order', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'Name' => 1, 'Value' => 2, 'Type' => 3, 'Default' => 4, 'Tooltip' => 5, 'Section' => 6, 'Category' => 7, 'Order' => 8, ),
        self::TYPE_CAMELNAME     => array('id' => 0, 'name' => 1, 'value' => 2, 'type' => 3, 'default' => 4, 'tooltip' => 5, 'section' => 6, 'category' => 7, 'order' => 8, ),
        self::TYPE_COLNAME       => array(ConfigTableMap::COL_CFG_ID => 0, ConfigTableMap::COL_CFG_NAME => 1, ConfigTableMap::COL_CFG_VALUE => 2, ConfigTableMap::COL_CFG_TYPE => 3, ConfigTableMap::COL_CFG_DEFAULT => 4, ConfigTableMap::COL_CFG_TOOLTIP => 5, ConfigTableMap::COL_CFG_SECTION => 6, ConfigTableMap::COL_CFG_CATEGORY => 7, ConfigTableMap::COL_CFG_ORDER => 8, ),
        self::TYPE_FIELDNAME     => array('cfg_id' => 0, 'cfg_name' => 1, 'cfg_value' => 2, 'cfg_type' => 3, 'cfg_default' => 4, 'cfg_tooltip' => 5, 'cfg_section' => 6, 'cfg_category' => 7, 'cfg_order' => 8, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, )
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
        $this->setName('config_cfg');
        $this->setPhpName('Config');
        $this->setIdentifierQuoting(false);
        $this->setClassName('\\ChurchCRM\\Config');
        $this->setPackage('ChurchCRM');
        $this->setUseIdGenerator(false);
        // columns
        $this->addPrimaryKey('cfg_id', 'Id', 'INTEGER', true, null, 0);
        $this->addColumn('cfg_name', 'Name', 'VARCHAR', true, 50, '');
        $this->addColumn('cfg_value', 'Value', 'LONGVARCHAR', false, null, null);
        $this->addColumn('cfg_type', 'Type', 'CHAR', true, null, 'text');
        $this->addColumn('cfg_default', 'Default', 'LONGVARCHAR', true, null, null);
        $this->addColumn('cfg_tooltip', 'Tooltip', 'LONGVARCHAR', true, null, null);
        $this->addColumn('cfg_section', 'Section', 'VARCHAR', true, 50, '');
        $this->addColumn('cfg_category', 'Category', 'VARCHAR', false, 20, null);
        $this->addColumn('cfg_order', 'Order', 'INTEGER', false, null, null);
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
        return $withPrefix ? ConfigTableMap::CLASS_DEFAULT : ConfigTableMap::OM_CLASS;
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
     * @return array           (Config object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = ConfigTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = ConfigTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + ConfigTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = ConfigTableMap::OM_CLASS;
            /** @var Config $obj */
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            ConfigTableMap::addInstanceToPool($obj, $key);
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
            $key = ConfigTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = ConfigTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                /** @var Config $obj */
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                ConfigTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(ConfigTableMap::COL_CFG_ID);
            $criteria->addSelectColumn(ConfigTableMap::COL_CFG_NAME);
            $criteria->addSelectColumn(ConfigTableMap::COL_CFG_VALUE);
            $criteria->addSelectColumn(ConfigTableMap::COL_CFG_TYPE);
            $criteria->addSelectColumn(ConfigTableMap::COL_CFG_DEFAULT);
            $criteria->addSelectColumn(ConfigTableMap::COL_CFG_TOOLTIP);
            $criteria->addSelectColumn(ConfigTableMap::COL_CFG_SECTION);
            $criteria->addSelectColumn(ConfigTableMap::COL_CFG_CATEGORY);
            $criteria->addSelectColumn(ConfigTableMap::COL_CFG_ORDER);
        } else {
            $criteria->addSelectColumn($alias . '.cfg_id');
            $criteria->addSelectColumn($alias . '.cfg_name');
            $criteria->addSelectColumn($alias . '.cfg_value');
            $criteria->addSelectColumn($alias . '.cfg_type');
            $criteria->addSelectColumn($alias . '.cfg_default');
            $criteria->addSelectColumn($alias . '.cfg_tooltip');
            $criteria->addSelectColumn($alias . '.cfg_section');
            $criteria->addSelectColumn($alias . '.cfg_category');
            $criteria->addSelectColumn($alias . '.cfg_order');
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
        return Propel::getServiceContainer()->getDatabaseMap(ConfigTableMap::DATABASE_NAME)->getTable(ConfigTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
        $dbMap = Propel::getServiceContainer()->getDatabaseMap(ConfigTableMap::DATABASE_NAME);
        if (!$dbMap->hasTable(ConfigTableMap::TABLE_NAME)) {
            $dbMap->addTableObject(new ConfigTableMap());
        }
    }

    /**
     * Performs a DELETE on the database, given a Config or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or Config object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(ConfigTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \ChurchCRM\Config) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(ConfigTableMap::DATABASE_NAME);
            $criteria->add(ConfigTableMap::COL_CFG_ID, (array) $values, Criteria::IN);
        }

        $query = ConfigQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) {
            ConfigTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) {
                ConfigTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the config_cfg table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return ConfigQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a Config or Criteria object.
     *
     * @param mixed               $criteria Criteria or Config object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(ConfigTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from Config object
        }


        // Set the correct dbName
        $query = ConfigQuery::create()->mergeWith($criteria);

        // use transaction because $criteria could contain info
        // for more than one table (I guess, conceivably)
        return $con->transaction(function () use ($con, $query) {
            return $query->doInsert($con);
        });
    }

} // ConfigTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
ConfigTableMap::buildTableMap();
