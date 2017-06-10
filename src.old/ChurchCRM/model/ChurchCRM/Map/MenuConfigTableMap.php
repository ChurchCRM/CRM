<?php

namespace ChurchCRM\Map;

use ChurchCRM\MenuConfig;
use ChurchCRM\MenuConfigQuery;
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
 * This class defines the structure of the 'menuconfig_mcf' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class MenuConfigTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'ChurchCRM.Map.MenuConfigTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'default';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'menuconfig_mcf';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\ChurchCRM\\MenuConfig';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'ChurchCRM.MenuConfig';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 16;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 16;

    /**
     * the column name for the mid field
     */
    const COL_MID = 'menuconfig_mcf.mid';

    /**
     * the column name for the name field
     */
    const COL_NAME = 'menuconfig_mcf.name';

    /**
     * the column name for the parent field
     */
    const COL_PARENT = 'menuconfig_mcf.parent';

    /**
     * the column name for the ismenu field
     */
    const COL_ISMENU = 'menuconfig_mcf.ismenu';

    /**
     * the column name for the content_english field
     */
    const COL_CONTENT_ENGLISH = 'menuconfig_mcf.content_english';

    /**
     * the column name for the content field
     */
    const COL_CONTENT = 'menuconfig_mcf.content';

    /**
     * the column name for the uri field
     */
    const COL_URI = 'menuconfig_mcf.uri';

    /**
     * the column name for the statustext field
     */
    const COL_STATUSTEXT = 'menuconfig_mcf.statustext';

    /**
     * the column name for the security_grp field
     */
    const COL_SECURITY_GRP = 'menuconfig_mcf.security_grp';

    /**
     * the column name for the session_var field
     */
    const COL_SESSION_VAR = 'menuconfig_mcf.session_var';

    /**
     * the column name for the session_var_in_text field
     */
    const COL_SESSION_VAR_IN_TEXT = 'menuconfig_mcf.session_var_in_text';

    /**
     * the column name for the session_var_in_uri field
     */
    const COL_SESSION_VAR_IN_URI = 'menuconfig_mcf.session_var_in_uri';

    /**
     * the column name for the url_parm_name field
     */
    const COL_URL_PARM_NAME = 'menuconfig_mcf.url_parm_name';

    /**
     * the column name for the active field
     */
    const COL_ACTIVE = 'menuconfig_mcf.active';

    /**
     * the column name for the sortorder field
     */
    const COL_SORTORDER = 'menuconfig_mcf.sortorder';

    /**
     * the column name for the icon field
     */
    const COL_ICON = 'menuconfig_mcf.icon';

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
        self::TYPE_PHPNAME       => array('Id', 'Name', 'Parent', 'Menu', 'ContentEnglish', 'Content', 'URI', 'Status', 'SecurityGroup', 'SessionVar', 'SessionVarInText', 'SessionVarInURI', 'URLParmName', 'Active', 'SortOrder', 'Icon', ),
        self::TYPE_CAMELNAME     => array('id', 'name', 'parent', 'menu', 'contentEnglish', 'content', 'uRI', 'status', 'securityGroup', 'sessionVar', 'sessionVarInText', 'sessionVarInURI', 'uRLParmName', 'active', 'sortOrder', 'icon', ),
        self::TYPE_COLNAME       => array(MenuConfigTableMap::COL_MID, MenuConfigTableMap::COL_NAME, MenuConfigTableMap::COL_PARENT, MenuConfigTableMap::COL_ISMENU, MenuConfigTableMap::COL_CONTENT_ENGLISH, MenuConfigTableMap::COL_CONTENT, MenuConfigTableMap::COL_URI, MenuConfigTableMap::COL_STATUSTEXT, MenuConfigTableMap::COL_SECURITY_GRP, MenuConfigTableMap::COL_SESSION_VAR, MenuConfigTableMap::COL_SESSION_VAR_IN_TEXT, MenuConfigTableMap::COL_SESSION_VAR_IN_URI, MenuConfigTableMap::COL_URL_PARM_NAME, MenuConfigTableMap::COL_ACTIVE, MenuConfigTableMap::COL_SORTORDER, MenuConfigTableMap::COL_ICON, ),
        self::TYPE_FIELDNAME     => array('mid', 'name', 'parent', 'ismenu', 'content_english', 'content', 'uri', 'statustext', 'security_grp', 'session_var', 'session_var_in_text', 'session_var_in_uri', 'url_parm_name', 'active', 'sortorder', 'icon', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'Name' => 1, 'Parent' => 2, 'Menu' => 3, 'ContentEnglish' => 4, 'Content' => 5, 'URI' => 6, 'Status' => 7, 'SecurityGroup' => 8, 'SessionVar' => 9, 'SessionVarInText' => 10, 'SessionVarInURI' => 11, 'URLParmName' => 12, 'Active' => 13, 'SortOrder' => 14, 'Icon' => 15, ),
        self::TYPE_CAMELNAME     => array('id' => 0, 'name' => 1, 'parent' => 2, 'menu' => 3, 'contentEnglish' => 4, 'content' => 5, 'uRI' => 6, 'status' => 7, 'securityGroup' => 8, 'sessionVar' => 9, 'sessionVarInText' => 10, 'sessionVarInURI' => 11, 'uRLParmName' => 12, 'active' => 13, 'sortOrder' => 14, 'icon' => 15, ),
        self::TYPE_COLNAME       => array(MenuConfigTableMap::COL_MID => 0, MenuConfigTableMap::COL_NAME => 1, MenuConfigTableMap::COL_PARENT => 2, MenuConfigTableMap::COL_ISMENU => 3, MenuConfigTableMap::COL_CONTENT_ENGLISH => 4, MenuConfigTableMap::COL_CONTENT => 5, MenuConfigTableMap::COL_URI => 6, MenuConfigTableMap::COL_STATUSTEXT => 7, MenuConfigTableMap::COL_SECURITY_GRP => 8, MenuConfigTableMap::COL_SESSION_VAR => 9, MenuConfigTableMap::COL_SESSION_VAR_IN_TEXT => 10, MenuConfigTableMap::COL_SESSION_VAR_IN_URI => 11, MenuConfigTableMap::COL_URL_PARM_NAME => 12, MenuConfigTableMap::COL_ACTIVE => 13, MenuConfigTableMap::COL_SORTORDER => 14, MenuConfigTableMap::COL_ICON => 15, ),
        self::TYPE_FIELDNAME     => array('mid' => 0, 'name' => 1, 'parent' => 2, 'ismenu' => 3, 'content_english' => 4, 'content' => 5, 'uri' => 6, 'statustext' => 7, 'security_grp' => 8, 'session_var' => 9, 'session_var_in_text' => 10, 'session_var_in_uri' => 11, 'url_parm_name' => 12, 'active' => 13, 'sortorder' => 14, 'icon' => 15, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, )
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
        $this->setName('menuconfig_mcf');
        $this->setPhpName('MenuConfig');
        $this->setIdentifierQuoting(false);
        $this->setClassName('\\ChurchCRM\\MenuConfig');
        $this->setPackage('ChurchCRM');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('mid', 'Id', 'INTEGER', true, null, null);
        $this->addColumn('name', 'Name', 'VARCHAR', true, 20, null);
        $this->addColumn('parent', 'Parent', 'VARCHAR', true, 20, null);
        $this->addColumn('ismenu', 'Menu', 'BOOLEAN', true, 1, null);
        $this->addColumn('content_english', 'ContentEnglish', 'VARCHAR', true, 100, null);
        $this->addColumn('content', 'Content', 'VARCHAR', false, 100, null);
        $this->addColumn('uri', 'URI', 'VARCHAR', true, 255, null);
        $this->addColumn('statustext', 'Status', 'VARCHAR', true, 255, null);
        $this->addColumn('security_grp', 'SecurityGroup', 'VARCHAR', true, 50, null);
        $this->addColumn('session_var', 'SessionVar', 'VARCHAR', false, 50, null);
        $this->addColumn('session_var_in_text', 'SessionVarInText', 'BOOLEAN', true, 1, null);
        $this->addColumn('session_var_in_uri', 'SessionVarInURI', 'BOOLEAN', true, 1, null);
        $this->addColumn('url_parm_name', 'URLParmName', 'VARCHAR', false, 50, null);
        $this->addColumn('active', 'Active', 'BOOLEAN', true, 1, null);
        $this->addColumn('sortorder', 'SortOrder', 'TINYINT', true, 3, null);
        $this->addColumn('icon', 'Icon', 'VARCHAR', false, 50, null);
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
        return $withPrefix ? MenuConfigTableMap::CLASS_DEFAULT : MenuConfigTableMap::OM_CLASS;
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
     * @return array           (MenuConfig object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = MenuConfigTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = MenuConfigTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + MenuConfigTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = MenuConfigTableMap::OM_CLASS;
            /** @var MenuConfig $obj */
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            MenuConfigTableMap::addInstanceToPool($obj, $key);
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
            $key = MenuConfigTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = MenuConfigTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                /** @var MenuConfig $obj */
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                MenuConfigTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(MenuConfigTableMap::COL_MID);
            $criteria->addSelectColumn(MenuConfigTableMap::COL_NAME);
            $criteria->addSelectColumn(MenuConfigTableMap::COL_PARENT);
            $criteria->addSelectColumn(MenuConfigTableMap::COL_ISMENU);
            $criteria->addSelectColumn(MenuConfigTableMap::COL_CONTENT_ENGLISH);
            $criteria->addSelectColumn(MenuConfigTableMap::COL_CONTENT);
            $criteria->addSelectColumn(MenuConfigTableMap::COL_URI);
            $criteria->addSelectColumn(MenuConfigTableMap::COL_STATUSTEXT);
            $criteria->addSelectColumn(MenuConfigTableMap::COL_SECURITY_GRP);
            $criteria->addSelectColumn(MenuConfigTableMap::COL_SESSION_VAR);
            $criteria->addSelectColumn(MenuConfigTableMap::COL_SESSION_VAR_IN_TEXT);
            $criteria->addSelectColumn(MenuConfigTableMap::COL_SESSION_VAR_IN_URI);
            $criteria->addSelectColumn(MenuConfigTableMap::COL_URL_PARM_NAME);
            $criteria->addSelectColumn(MenuConfigTableMap::COL_ACTIVE);
            $criteria->addSelectColumn(MenuConfigTableMap::COL_SORTORDER);
            $criteria->addSelectColumn(MenuConfigTableMap::COL_ICON);
        } else {
            $criteria->addSelectColumn($alias . '.mid');
            $criteria->addSelectColumn($alias . '.name');
            $criteria->addSelectColumn($alias . '.parent');
            $criteria->addSelectColumn($alias . '.ismenu');
            $criteria->addSelectColumn($alias . '.content_english');
            $criteria->addSelectColumn($alias . '.content');
            $criteria->addSelectColumn($alias . '.uri');
            $criteria->addSelectColumn($alias . '.statustext');
            $criteria->addSelectColumn($alias . '.security_grp');
            $criteria->addSelectColumn($alias . '.session_var');
            $criteria->addSelectColumn($alias . '.session_var_in_text');
            $criteria->addSelectColumn($alias . '.session_var_in_uri');
            $criteria->addSelectColumn($alias . '.url_parm_name');
            $criteria->addSelectColumn($alias . '.active');
            $criteria->addSelectColumn($alias . '.sortorder');
            $criteria->addSelectColumn($alias . '.icon');
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
        return Propel::getServiceContainer()->getDatabaseMap(MenuConfigTableMap::DATABASE_NAME)->getTable(MenuConfigTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
        $dbMap = Propel::getServiceContainer()->getDatabaseMap(MenuConfigTableMap::DATABASE_NAME);
        if (!$dbMap->hasTable(MenuConfigTableMap::TABLE_NAME)) {
            $dbMap->addTableObject(new MenuConfigTableMap());
        }
    }

    /**
     * Performs a DELETE on the database, given a MenuConfig or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or MenuConfig object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(MenuConfigTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \ChurchCRM\MenuConfig) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(MenuConfigTableMap::DATABASE_NAME);
            $criteria->add(MenuConfigTableMap::COL_MID, (array) $values, Criteria::IN);
        }

        $query = MenuConfigQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) {
            MenuConfigTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) {
                MenuConfigTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the menuconfig_mcf table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return MenuConfigQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a MenuConfig or Criteria object.
     *
     * @param mixed               $criteria Criteria or MenuConfig object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(MenuConfigTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from MenuConfig object
        }

        if ($criteria->containsKey(MenuConfigTableMap::COL_MID) && $criteria->keyContainsValue(MenuConfigTableMap::COL_MID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.MenuConfigTableMap::COL_MID.')');
        }


        // Set the correct dbName
        $query = MenuConfigQuery::create()->mergeWith($criteria);

        // use transaction because $criteria could contain info
        // for more than one table (I guess, conceivably)
        return $con->transaction(function () use ($con, $query) {
            return $query->doInsert($con);
        });
    }

} // MenuConfigTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
MenuConfigTableMap::buildTableMap();
