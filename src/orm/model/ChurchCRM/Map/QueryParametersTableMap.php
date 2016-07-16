<?php

namespace ChurchCRM\Map;

use ChurchCRM\QueryParameters;
use ChurchCRM\QueryParametersQuery;
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
 * This class defines the structure of the 'queryparameters_qrp' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class QueryParametersTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'ChurchCRM.Map.QueryParametersTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'default';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'queryparameters_qrp';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\ChurchCRM\\QueryParameters';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'ChurchCRM.QueryParameters';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 15;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 15;

    /**
     * the column name for the qrp_ID field
     */
    const COL_QRP_ID = 'queryparameters_qrp.qrp_ID';

    /**
     * the column name for the qrp_qry_ID field
     */
    const COL_QRP_QRY_ID = 'queryparameters_qrp.qrp_qry_ID';

    /**
     * the column name for the qrp_Type field
     */
    const COL_QRP_TYPE = 'queryparameters_qrp.qrp_Type';

    /**
     * the column name for the qrp_OptionSQL field
     */
    const COL_QRP_OPTIONSQL = 'queryparameters_qrp.qrp_OptionSQL';

    /**
     * the column name for the qrp_Name field
     */
    const COL_QRP_NAME = 'queryparameters_qrp.qrp_Name';

    /**
     * the column name for the qrp_Description field
     */
    const COL_QRP_DESCRIPTION = 'queryparameters_qrp.qrp_Description';

    /**
     * the column name for the qrp_Alias field
     */
    const COL_QRP_ALIAS = 'queryparameters_qrp.qrp_Alias';

    /**
     * the column name for the qrp_Default field
     */
    const COL_QRP_DEFAULT = 'queryparameters_qrp.qrp_Default';

    /**
     * the column name for the qrp_Required field
     */
    const COL_QRP_REQUIRED = 'queryparameters_qrp.qrp_Required';

    /**
     * the column name for the qrp_InputBoxSize field
     */
    const COL_QRP_INPUTBOXSIZE = 'queryparameters_qrp.qrp_InputBoxSize';

    /**
     * the column name for the qrp_Validation field
     */
    const COL_QRP_VALIDATION = 'queryparameters_qrp.qrp_Validation';

    /**
     * the column name for the qrp_NumericMax field
     */
    const COL_QRP_NUMERICMAX = 'queryparameters_qrp.qrp_NumericMax';

    /**
     * the column name for the qrp_NumericMin field
     */
    const COL_QRP_NUMERICMIN = 'queryparameters_qrp.qrp_NumericMin';

    /**
     * the column name for the qrp_AlphaMinLength field
     */
    const COL_QRP_ALPHAMINLENGTH = 'queryparameters_qrp.qrp_AlphaMinLength';

    /**
     * the column name for the qrp_AlphaMaxLength field
     */
    const COL_QRP_ALPHAMAXLENGTH = 'queryparameters_qrp.qrp_AlphaMaxLength';

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
        self::TYPE_PHPNAME       => array('Id', 'QryId', 'Type', 'OptionSQL', 'Name', 'Description', 'Alias', 'Default', 'Required', 'InputBoxSize', 'Validation', 'NumericMax', 'NumericMin', 'AlphaMinLength', 'AlphaMaxLength', ),
        self::TYPE_CAMELNAME     => array('id', 'qryId', 'type', 'optionSQL', 'name', 'description', 'alias', 'default', 'required', 'inputBoxSize', 'validation', 'numericMax', 'numericMin', 'alphaMinLength', 'alphaMaxLength', ),
        self::TYPE_COLNAME       => array(QueryParametersTableMap::COL_QRP_ID, QueryParametersTableMap::COL_QRP_QRY_ID, QueryParametersTableMap::COL_QRP_TYPE, QueryParametersTableMap::COL_QRP_OPTIONSQL, QueryParametersTableMap::COL_QRP_NAME, QueryParametersTableMap::COL_QRP_DESCRIPTION, QueryParametersTableMap::COL_QRP_ALIAS, QueryParametersTableMap::COL_QRP_DEFAULT, QueryParametersTableMap::COL_QRP_REQUIRED, QueryParametersTableMap::COL_QRP_INPUTBOXSIZE, QueryParametersTableMap::COL_QRP_VALIDATION, QueryParametersTableMap::COL_QRP_NUMERICMAX, QueryParametersTableMap::COL_QRP_NUMERICMIN, QueryParametersTableMap::COL_QRP_ALPHAMINLENGTH, QueryParametersTableMap::COL_QRP_ALPHAMAXLENGTH, ),
        self::TYPE_FIELDNAME     => array('qrp_ID', 'qrp_qry_ID', 'qrp_Type', 'qrp_OptionSQL', 'qrp_Name', 'qrp_Description', 'qrp_Alias', 'qrp_Default', 'qrp_Required', 'qrp_InputBoxSize', 'qrp_Validation', 'qrp_NumericMax', 'qrp_NumericMin', 'qrp_AlphaMinLength', 'qrp_AlphaMaxLength', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'QryId' => 1, 'Type' => 2, 'OptionSQL' => 3, 'Name' => 4, 'Description' => 5, 'Alias' => 6, 'Default' => 7, 'Required' => 8, 'InputBoxSize' => 9, 'Validation' => 10, 'NumericMax' => 11, 'NumericMin' => 12, 'AlphaMinLength' => 13, 'AlphaMaxLength' => 14, ),
        self::TYPE_CAMELNAME     => array('id' => 0, 'qryId' => 1, 'type' => 2, 'optionSQL' => 3, 'name' => 4, 'description' => 5, 'alias' => 6, 'default' => 7, 'required' => 8, 'inputBoxSize' => 9, 'validation' => 10, 'numericMax' => 11, 'numericMin' => 12, 'alphaMinLength' => 13, 'alphaMaxLength' => 14, ),
        self::TYPE_COLNAME       => array(QueryParametersTableMap::COL_QRP_ID => 0, QueryParametersTableMap::COL_QRP_QRY_ID => 1, QueryParametersTableMap::COL_QRP_TYPE => 2, QueryParametersTableMap::COL_QRP_OPTIONSQL => 3, QueryParametersTableMap::COL_QRP_NAME => 4, QueryParametersTableMap::COL_QRP_DESCRIPTION => 5, QueryParametersTableMap::COL_QRP_ALIAS => 6, QueryParametersTableMap::COL_QRP_DEFAULT => 7, QueryParametersTableMap::COL_QRP_REQUIRED => 8, QueryParametersTableMap::COL_QRP_INPUTBOXSIZE => 9, QueryParametersTableMap::COL_QRP_VALIDATION => 10, QueryParametersTableMap::COL_QRP_NUMERICMAX => 11, QueryParametersTableMap::COL_QRP_NUMERICMIN => 12, QueryParametersTableMap::COL_QRP_ALPHAMINLENGTH => 13, QueryParametersTableMap::COL_QRP_ALPHAMAXLENGTH => 14, ),
        self::TYPE_FIELDNAME     => array('qrp_ID' => 0, 'qrp_qry_ID' => 1, 'qrp_Type' => 2, 'qrp_OptionSQL' => 3, 'qrp_Name' => 4, 'qrp_Description' => 5, 'qrp_Alias' => 6, 'qrp_Default' => 7, 'qrp_Required' => 8, 'qrp_InputBoxSize' => 9, 'qrp_Validation' => 10, 'qrp_NumericMax' => 11, 'qrp_NumericMin' => 12, 'qrp_AlphaMinLength' => 13, 'qrp_AlphaMaxLength' => 14, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, )
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
        $this->setName('queryparameters_qrp');
        $this->setPhpName('QueryParameters');
        $this->setIdentifierQuoting(false);
        $this->setClassName('\\ChurchCRM\\QueryParameters');
        $this->setPackage('ChurchCRM');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('qrp_ID', 'Id', 'SMALLINT', true, 8, null);
        $this->addColumn('qrp_qry_ID', 'QryId', 'SMALLINT', true, 8, 0);
        $this->addColumn('qrp_Type', 'Type', 'TINYINT', true, 3, 0);
        $this->addColumn('qrp_OptionSQL', 'OptionSQL', 'LONGVARCHAR', false, null, null);
        $this->addColumn('qrp_Name', 'Name', 'VARCHAR', false, 25, null);
        $this->addColumn('qrp_Description', 'Description', 'LONGVARCHAR', false, null, null);
        $this->addColumn('qrp_Alias', 'Alias', 'VARCHAR', false, 25, null);
        $this->addColumn('qrp_Default', 'Default', 'VARCHAR', false, 25, null);
        $this->addColumn('qrp_Required', 'Required', 'TINYINT', true, 3, 0);
        $this->addColumn('qrp_InputBoxSize', 'InputBoxSize', 'TINYINT', true, 3, 0);
        $this->addColumn('qrp_Validation', 'Validation', 'VARCHAR', true, 5, '');
        $this->addColumn('qrp_NumericMax', 'NumericMax', 'INTEGER', false, null, null);
        $this->addColumn('qrp_NumericMin', 'NumericMin', 'INTEGER', false, null, null);
        $this->addColumn('qrp_AlphaMinLength', 'AlphaMinLength', 'INTEGER', false, null, null);
        $this->addColumn('qrp_AlphaMaxLength', 'AlphaMaxLength', 'INTEGER', false, null, null);
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
        return $withPrefix ? QueryParametersTableMap::CLASS_DEFAULT : QueryParametersTableMap::OM_CLASS;
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
     * @return array           (QueryParameters object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = QueryParametersTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = QueryParametersTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + QueryParametersTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = QueryParametersTableMap::OM_CLASS;
            /** @var QueryParameters $obj */
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            QueryParametersTableMap::addInstanceToPool($obj, $key);
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
            $key = QueryParametersTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = QueryParametersTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                /** @var QueryParameters $obj */
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                QueryParametersTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(QueryParametersTableMap::COL_QRP_ID);
            $criteria->addSelectColumn(QueryParametersTableMap::COL_QRP_QRY_ID);
            $criteria->addSelectColumn(QueryParametersTableMap::COL_QRP_TYPE);
            $criteria->addSelectColumn(QueryParametersTableMap::COL_QRP_OPTIONSQL);
            $criteria->addSelectColumn(QueryParametersTableMap::COL_QRP_NAME);
            $criteria->addSelectColumn(QueryParametersTableMap::COL_QRP_DESCRIPTION);
            $criteria->addSelectColumn(QueryParametersTableMap::COL_QRP_ALIAS);
            $criteria->addSelectColumn(QueryParametersTableMap::COL_QRP_DEFAULT);
            $criteria->addSelectColumn(QueryParametersTableMap::COL_QRP_REQUIRED);
            $criteria->addSelectColumn(QueryParametersTableMap::COL_QRP_INPUTBOXSIZE);
            $criteria->addSelectColumn(QueryParametersTableMap::COL_QRP_VALIDATION);
            $criteria->addSelectColumn(QueryParametersTableMap::COL_QRP_NUMERICMAX);
            $criteria->addSelectColumn(QueryParametersTableMap::COL_QRP_NUMERICMIN);
            $criteria->addSelectColumn(QueryParametersTableMap::COL_QRP_ALPHAMINLENGTH);
            $criteria->addSelectColumn(QueryParametersTableMap::COL_QRP_ALPHAMAXLENGTH);
        } else {
            $criteria->addSelectColumn($alias . '.qrp_ID');
            $criteria->addSelectColumn($alias . '.qrp_qry_ID');
            $criteria->addSelectColumn($alias . '.qrp_Type');
            $criteria->addSelectColumn($alias . '.qrp_OptionSQL');
            $criteria->addSelectColumn($alias . '.qrp_Name');
            $criteria->addSelectColumn($alias . '.qrp_Description');
            $criteria->addSelectColumn($alias . '.qrp_Alias');
            $criteria->addSelectColumn($alias . '.qrp_Default');
            $criteria->addSelectColumn($alias . '.qrp_Required');
            $criteria->addSelectColumn($alias . '.qrp_InputBoxSize');
            $criteria->addSelectColumn($alias . '.qrp_Validation');
            $criteria->addSelectColumn($alias . '.qrp_NumericMax');
            $criteria->addSelectColumn($alias . '.qrp_NumericMin');
            $criteria->addSelectColumn($alias . '.qrp_AlphaMinLength');
            $criteria->addSelectColumn($alias . '.qrp_AlphaMaxLength');
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
        return Propel::getServiceContainer()->getDatabaseMap(QueryParametersTableMap::DATABASE_NAME)->getTable(QueryParametersTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
        $dbMap = Propel::getServiceContainer()->getDatabaseMap(QueryParametersTableMap::DATABASE_NAME);
        if (!$dbMap->hasTable(QueryParametersTableMap::TABLE_NAME)) {
            $dbMap->addTableObject(new QueryParametersTableMap());
        }
    }

    /**
     * Performs a DELETE on the database, given a QueryParameters or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or QueryParameters object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(QueryParametersTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \ChurchCRM\QueryParameters) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(QueryParametersTableMap::DATABASE_NAME);
            $criteria->add(QueryParametersTableMap::COL_QRP_ID, (array) $values, Criteria::IN);
        }

        $query = QueryParametersQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) {
            QueryParametersTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) {
                QueryParametersTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the queryparameters_qrp table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return QueryParametersQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a QueryParameters or Criteria object.
     *
     * @param mixed               $criteria Criteria or QueryParameters object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(QueryParametersTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from QueryParameters object
        }

        if ($criteria->containsKey(QueryParametersTableMap::COL_QRP_ID) && $criteria->keyContainsValue(QueryParametersTableMap::COL_QRP_ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.QueryParametersTableMap::COL_QRP_ID.')');
        }


        // Set the correct dbName
        $query = QueryParametersQuery::create()->mergeWith($criteria);

        // use transaction because $criteria could contain info
        // for more than one table (I guess, conceivably)
        return $con->transaction(function () use ($con, $query) {
            return $query->doInsert($con);
        });
    }

} // QueryParametersTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
QueryParametersTableMap::buildTableMap();
