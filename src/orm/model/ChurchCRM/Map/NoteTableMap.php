<?php

namespace ChurchCRM\Map;

use ChurchCRM\Note;
use ChurchCRM\NoteQuery;
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
 * This class defines the structure of the 'note_nte' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class NoteTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'ChurchCRM.Map.NoteTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'default';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'note_nte';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\ChurchCRM\\Note';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'ChurchCRM.Note';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 10;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 10;

    /**
     * the column name for the nte_ID field
     */
    const COL_NTE_ID = 'note_nte.nte_ID';

    /**
     * the column name for the nte_per_ID field
     */
    const COL_NTE_PER_ID = 'note_nte.nte_per_ID';

    /**
     * the column name for the nte_fam_ID field
     */
    const COL_NTE_FAM_ID = 'note_nte.nte_fam_ID';

    /**
     * the column name for the nte_Private field
     */
    const COL_NTE_PRIVATE = 'note_nte.nte_Private';

    /**
     * the column name for the nte_Text field
     */
    const COL_NTE_TEXT = 'note_nte.nte_Text';

    /**
     * the column name for the nte_DateEntered field
     */
    const COL_NTE_DATEENTERED = 'note_nte.nte_DateEntered';

    /**
     * the column name for the nte_DateLastEdited field
     */
    const COL_NTE_DATELASTEDITED = 'note_nte.nte_DateLastEdited';

    /**
     * the column name for the nte_EnteredBy field
     */
    const COL_NTE_ENTEREDBY = 'note_nte.nte_EnteredBy';

    /**
     * the column name for the nte_EditedBy field
     */
    const COL_NTE_EDITEDBY = 'note_nte.nte_EditedBy';

    /**
     * the column name for the nte_Type field
     */
    const COL_NTE_TYPE = 'note_nte.nte_Type';

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
        self::TYPE_PHPNAME       => array('Id', 'PerId', 'FamId', 'Private', 'Text', 'DateEntered', 'DateLastEdited', 'EnteredBy', 'EditedBy', 'Type', ),
        self::TYPE_CAMELNAME     => array('id', 'perId', 'famId', 'private', 'text', 'dateEntered', 'dateLastEdited', 'enteredBy', 'editedBy', 'type', ),
        self::TYPE_COLNAME       => array(NoteTableMap::COL_NTE_ID, NoteTableMap::COL_NTE_PER_ID, NoteTableMap::COL_NTE_FAM_ID, NoteTableMap::COL_NTE_PRIVATE, NoteTableMap::COL_NTE_TEXT, NoteTableMap::COL_NTE_DATEENTERED, NoteTableMap::COL_NTE_DATELASTEDITED, NoteTableMap::COL_NTE_ENTEREDBY, NoteTableMap::COL_NTE_EDITEDBY, NoteTableMap::COL_NTE_TYPE, ),
        self::TYPE_FIELDNAME     => array('nte_ID', 'nte_per_ID', 'nte_fam_ID', 'nte_Private', 'nte_Text', 'nte_DateEntered', 'nte_DateLastEdited', 'nte_EnteredBy', 'nte_EditedBy', 'nte_Type', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'PerId' => 1, 'FamId' => 2, 'Private' => 3, 'Text' => 4, 'DateEntered' => 5, 'DateLastEdited' => 6, 'EnteredBy' => 7, 'EditedBy' => 8, 'Type' => 9, ),
        self::TYPE_CAMELNAME     => array('id' => 0, 'perId' => 1, 'famId' => 2, 'private' => 3, 'text' => 4, 'dateEntered' => 5, 'dateLastEdited' => 6, 'enteredBy' => 7, 'editedBy' => 8, 'type' => 9, ),
        self::TYPE_COLNAME       => array(NoteTableMap::COL_NTE_ID => 0, NoteTableMap::COL_NTE_PER_ID => 1, NoteTableMap::COL_NTE_FAM_ID => 2, NoteTableMap::COL_NTE_PRIVATE => 3, NoteTableMap::COL_NTE_TEXT => 4, NoteTableMap::COL_NTE_DATEENTERED => 5, NoteTableMap::COL_NTE_DATELASTEDITED => 6, NoteTableMap::COL_NTE_ENTEREDBY => 7, NoteTableMap::COL_NTE_EDITEDBY => 8, NoteTableMap::COL_NTE_TYPE => 9, ),
        self::TYPE_FIELDNAME     => array('nte_ID' => 0, 'nte_per_ID' => 1, 'nte_fam_ID' => 2, 'nte_Private' => 3, 'nte_Text' => 4, 'nte_DateEntered' => 5, 'nte_DateLastEdited' => 6, 'nte_EnteredBy' => 7, 'nte_EditedBy' => 8, 'nte_Type' => 9, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, )
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
        $this->setName('note_nte');
        $this->setPhpName('Note');
        $this->setIdentifierQuoting(false);
        $this->setClassName('\\ChurchCRM\\Note');
        $this->setPackage('ChurchCRM');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('nte_ID', 'Id', 'SMALLINT', true, 8, null);
        $this->addForeignKey('nte_per_ID', 'PerId', 'SMALLINT', 'person_per', 'per_ID', true, 8, 0);
        $this->addForeignKey('nte_fam_ID', 'FamId', 'SMALLINT', 'family_fam', 'fam_ID', true, 8, 0);
        $this->addColumn('nte_Private', 'Private', 'SMALLINT', true, 8, 0);
        $this->addColumn('nte_Text', 'Text', 'LONGVARCHAR', false, null, null);
        $this->addColumn('nte_DateEntered', 'DateEntered', 'TIMESTAMP', true, null, '0000-00-00 00:00:00');
        $this->addColumn('nte_DateLastEdited', 'DateLastEdited', 'TIMESTAMP', false, null, null);
        $this->addColumn('nte_EnteredBy', 'EnteredBy', 'SMALLINT', true, 8, 0);
        $this->addColumn('nte_EditedBy', 'EditedBy', 'SMALLINT', true, 8, 0);
        $this->addColumn('nte_Type', 'Type', 'VARCHAR', false, 50, null);
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('Person', '\\ChurchCRM\\Person', RelationMap::MANY_TO_ONE, array (
  0 =>
  array (
    0 => ':nte_per_ID',
    1 => ':per_ID',
  ),
), null, null, null, false);
        $this->addRelation('Family', '\\ChurchCRM\\Family', RelationMap::MANY_TO_ONE, array (
  0 =>
  array (
    0 => ':nte_fam_ID',
    1 => ':fam_ID',
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
        return $withPrefix ? NoteTableMap::CLASS_DEFAULT : NoteTableMap::OM_CLASS;
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
     * @return array           (Note object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = NoteTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = NoteTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + NoteTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = NoteTableMap::OM_CLASS;
            /** @var Note $obj */
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            NoteTableMap::addInstanceToPool($obj, $key);
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
            $key = NoteTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = NoteTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                /** @var Note $obj */
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                NoteTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(NoteTableMap::COL_NTE_ID);
            $criteria->addSelectColumn(NoteTableMap::COL_NTE_PER_ID);
            $criteria->addSelectColumn(NoteTableMap::COL_NTE_FAM_ID);
            $criteria->addSelectColumn(NoteTableMap::COL_NTE_PRIVATE);
            $criteria->addSelectColumn(NoteTableMap::COL_NTE_TEXT);
            $criteria->addSelectColumn(NoteTableMap::COL_NTE_DATEENTERED);
            $criteria->addSelectColumn(NoteTableMap::COL_NTE_DATELASTEDITED);
            $criteria->addSelectColumn(NoteTableMap::COL_NTE_ENTEREDBY);
            $criteria->addSelectColumn(NoteTableMap::COL_NTE_EDITEDBY);
            $criteria->addSelectColumn(NoteTableMap::COL_NTE_TYPE);
        } else {
            $criteria->addSelectColumn($alias . '.nte_ID');
            $criteria->addSelectColumn($alias . '.nte_per_ID');
            $criteria->addSelectColumn($alias . '.nte_fam_ID');
            $criteria->addSelectColumn($alias . '.nte_Private');
            $criteria->addSelectColumn($alias . '.nte_Text');
            $criteria->addSelectColumn($alias . '.nte_DateEntered');
            $criteria->addSelectColumn($alias . '.nte_DateLastEdited');
            $criteria->addSelectColumn($alias . '.nte_EnteredBy');
            $criteria->addSelectColumn($alias . '.nte_EditedBy');
            $criteria->addSelectColumn($alias . '.nte_Type');
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
        return Propel::getServiceContainer()->getDatabaseMap(NoteTableMap::DATABASE_NAME)->getTable(NoteTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
        $dbMap = Propel::getServiceContainer()->getDatabaseMap(NoteTableMap::DATABASE_NAME);
        if (!$dbMap->hasTable(NoteTableMap::TABLE_NAME)) {
            $dbMap->addTableObject(new NoteTableMap());
        }
    }

    /**
     * Performs a DELETE on the database, given a Note or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or Note object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(NoteTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \ChurchCRM\Note) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(NoteTableMap::DATABASE_NAME);
            $criteria->add(NoteTableMap::COL_NTE_ID, (array) $values, Criteria::IN);
        }

        $query = NoteQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) {
            NoteTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) {
                NoteTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the note_nte table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return NoteQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a Note or Criteria object.
     *
     * @param mixed               $criteria Criteria or Note object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(NoteTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from Note object
        }

        if ($criteria->containsKey(NoteTableMap::COL_NTE_ID) && $criteria->keyContainsValue(NoteTableMap::COL_NTE_ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.NoteTableMap::COL_NTE_ID.')');
        }


        // Set the correct dbName
        $query = NoteQuery::create()->mergeWith($criteria);

        // use transaction because $criteria could contain info
        // for more than one table (I guess, conceivably)
        return $con->transaction(function () use ($con, $query) {
            return $query->doInsert($con);
        });
    }

} // NoteTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
NoteTableMap::buildTableMap();
