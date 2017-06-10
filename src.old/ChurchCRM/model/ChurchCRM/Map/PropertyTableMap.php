<?php

namespace ChurchCRM\Map;

use ChurchCRM\Property;
use ChurchCRM\PropertyQuery;
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
 * This class defines the structure of the 'property_pro' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class PropertyTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'ChurchCRM.Map.PropertyTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'default';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'property_pro';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\ChurchCRM\\Property';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'ChurchCRM.Property';

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
     * the column name for the pro_ID field
     */
    const COL_PRO_ID = 'property_pro.pro_ID';

    /**
     * the column name for the pro_Class field
     */
    const COL_PRO_CLASS = 'property_pro.pro_Class';

    /**
     * the column name for the pro_prt_ID field
     */
    const COL_PRO_PRT_ID = 'property_pro.pro_prt_ID';

    /**
     * the column name for the pro_Name field
     */
    const COL_PRO_NAME = 'property_pro.pro_Name';

    /**
     * the column name for the pro_Description field
     */
    const COL_PRO_DESCRIPTION = 'property_pro.pro_Description';

    /**
     * the column name for the pro_Prompt field
     */
    const COL_PRO_PROMPT = 'property_pro.pro_Prompt';

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
        self::TYPE_PHPNAME       => array('ProId', 'ProClass', 'ProPrtId', 'ProName', 'ProDescription', 'ProPrompt', ),
        self::TYPE_CAMELNAME     => array('proId', 'proClass', 'proPrtId', 'proName', 'proDescription', 'proPrompt', ),
        self::TYPE_COLNAME       => array(PropertyTableMap::COL_PRO_ID, PropertyTableMap::COL_PRO_CLASS, PropertyTableMap::COL_PRO_PRT_ID, PropertyTableMap::COL_PRO_NAME, PropertyTableMap::COL_PRO_DESCRIPTION, PropertyTableMap::COL_PRO_PROMPT, ),
        self::TYPE_FIELDNAME     => array('pro_ID', 'pro_Class', 'pro_prt_ID', 'pro_Name', 'pro_Description', 'pro_Prompt', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('ProId' => 0, 'ProClass' => 1, 'ProPrtId' => 2, 'ProName' => 3, 'ProDescription' => 4, 'ProPrompt' => 5, ),
        self::TYPE_CAMELNAME     => array('proId' => 0, 'proClass' => 1, 'proPrtId' => 2, 'proName' => 3, 'proDescription' => 4, 'proPrompt' => 5, ),
        self::TYPE_COLNAME       => array(PropertyTableMap::COL_PRO_ID => 0, PropertyTableMap::COL_PRO_CLASS => 1, PropertyTableMap::COL_PRO_PRT_ID => 2, PropertyTableMap::COL_PRO_NAME => 3, PropertyTableMap::COL_PRO_DESCRIPTION => 4, PropertyTableMap::COL_PRO_PROMPT => 5, ),
        self::TYPE_FIELDNAME     => array('pro_ID' => 0, 'pro_Class' => 1, 'pro_prt_ID' => 2, 'pro_Name' => 3, 'pro_Description' => 4, 'pro_Prompt' => 5, ),
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
        $this->setName('property_pro');
        $this->setPhpName('Property');
        $this->setIdentifierQuoting(false);
        $this->setClassName('\\ChurchCRM\\Property');
        $this->setPackage('ChurchCRM');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('pro_ID', 'ProId', 'SMALLINT', true, 8, null);
        $this->addColumn('pro_Class', 'ProClass', 'VARCHAR', true, 10, '');
        $this->addForeignKey('pro_prt_ID', 'ProPrtId', 'SMALLINT', 'propertytype_prt', 'prt_ID', true, 8, 0);
        $this->addColumn('pro_Name', 'ProName', 'VARCHAR', true, 200, '0');
        $this->addColumn('pro_Description', 'ProDescription', 'LONGVARCHAR', true, null, null);
        $this->addColumn('pro_Prompt', 'ProPrompt', 'VARCHAR', false, 255, null);
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('PropertyType', '\\ChurchCRM\\PropertyType', RelationMap::MANY_TO_ONE, array (
  0 =>
  array (
    0 => ':pro_prt_ID',
    1 => ':prt_ID',
  ),
), null, null, null, false);
        $this->addRelation('PersonProperty', '\\ChurchCRM\\PersonProperty', RelationMap::ONE_TO_MANY, array (
  0 =>
  array (
    0 => ':r2p_pro_ID',
    1 => ':pro_ID',
  ),
), null, null, 'PersonProperties', false);
        $this->addRelation('Person', '\\ChurchCRM\\Person', RelationMap::MANY_TO_MANY, array(), null, null, 'People');
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
        if ($row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('ProId', TableMap::TYPE_PHPNAME, $indexType)] === null) {
            return null;
        }

        return null === $row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('ProId', TableMap::TYPE_PHPNAME, $indexType)] || is_scalar($row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('ProId', TableMap::TYPE_PHPNAME, $indexType)]) || is_callable([$row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('ProId', TableMap::TYPE_PHPNAME, $indexType)], '__toString']) ? (string) $row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('ProId', TableMap::TYPE_PHPNAME, $indexType)] : $row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('ProId', TableMap::TYPE_PHPNAME, $indexType)];
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
                : self::translateFieldName('ProId', TableMap::TYPE_PHPNAME, $indexType)
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
        return $withPrefix ? PropertyTableMap::CLASS_DEFAULT : PropertyTableMap::OM_CLASS;
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
     * @return array           (Property object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = PropertyTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = PropertyTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + PropertyTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = PropertyTableMap::OM_CLASS;
            /** @var Property $obj */
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            PropertyTableMap::addInstanceToPool($obj, $key);
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
            $key = PropertyTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = PropertyTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                /** @var Property $obj */
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                PropertyTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(PropertyTableMap::COL_PRO_ID);
            $criteria->addSelectColumn(PropertyTableMap::COL_PRO_CLASS);
            $criteria->addSelectColumn(PropertyTableMap::COL_PRO_PRT_ID);
            $criteria->addSelectColumn(PropertyTableMap::COL_PRO_NAME);
            $criteria->addSelectColumn(PropertyTableMap::COL_PRO_DESCRIPTION);
            $criteria->addSelectColumn(PropertyTableMap::COL_PRO_PROMPT);
        } else {
            $criteria->addSelectColumn($alias . '.pro_ID');
            $criteria->addSelectColumn($alias . '.pro_Class');
            $criteria->addSelectColumn($alias . '.pro_prt_ID');
            $criteria->addSelectColumn($alias . '.pro_Name');
            $criteria->addSelectColumn($alias . '.pro_Description');
            $criteria->addSelectColumn($alias . '.pro_Prompt');
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
        return Propel::getServiceContainer()->getDatabaseMap(PropertyTableMap::DATABASE_NAME)->getTable(PropertyTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
        $dbMap = Propel::getServiceContainer()->getDatabaseMap(PropertyTableMap::DATABASE_NAME);
        if (!$dbMap->hasTable(PropertyTableMap::TABLE_NAME)) {
            $dbMap->addTableObject(new PropertyTableMap());
        }
    }

    /**
     * Performs a DELETE on the database, given a Property or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or Property object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(PropertyTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \ChurchCRM\Property) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(PropertyTableMap::DATABASE_NAME);
            $criteria->add(PropertyTableMap::COL_PRO_ID, (array) $values, Criteria::IN);
        }

        $query = PropertyQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) {
            PropertyTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) {
                PropertyTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the property_pro table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return PropertyQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a Property or Criteria object.
     *
     * @param mixed               $criteria Criteria or Property object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(PropertyTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from Property object
        }

        if ($criteria->containsKey(PropertyTableMap::COL_PRO_ID) && $criteria->keyContainsValue(PropertyTableMap::COL_PRO_ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.PropertyTableMap::COL_PRO_ID.')');
        }


        // Set the correct dbName
        $query = PropertyQuery::create()->mergeWith($criteria);

        // use transaction because $criteria could contain info
        // for more than one table (I guess, conceivably)
        return $con->transaction(function () use ($con, $query) {
            return $query->doInsert($con);
        });
    }

} // PropertyTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
PropertyTableMap::buildTableMap();
