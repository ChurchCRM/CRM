<?php

namespace ChurchCRM\Map;

use ChurchCRM\EmailMessagePending;
use ChurchCRM\EmailMessagePendingQuery;
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
 * This class defines the structure of the 'email_message_pending_emp' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class EmailMessagePendingTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'ChurchCRM.Map.EmailMessagePendingTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'default';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'email_message_pending_emp';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\ChurchCRM\\EmailMessagePending';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'ChurchCRM.EmailMessagePending';

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
     * the column name for the emp_usr_id field
     */
    const COL_EMP_USR_ID = 'email_message_pending_emp.emp_usr_id';

    /**
     * the column name for the emp_to_send field
     */
    const COL_EMP_TO_SEND = 'email_message_pending_emp.emp_to_send';

    /**
     * the column name for the emp_subject field
     */
    const COL_EMP_SUBJECT = 'email_message_pending_emp.emp_subject';

    /**
     * the column name for the emp_message field
     */
    const COL_EMP_MESSAGE = 'email_message_pending_emp.emp_message';

    /**
     * the column name for the emp_attach_name field
     */
    const COL_EMP_ATTACH_NAME = 'email_message_pending_emp.emp_attach_name';

    /**
     * the column name for the emp_attach field
     */
    const COL_EMP_ATTACH = 'email_message_pending_emp.emp_attach';

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
        self::TYPE_PHPNAME       => array('UsrId', 'ToSend', 'Subject', 'Message', 'AttachName', 'Attach', ),
        self::TYPE_CAMELNAME     => array('usrId', 'toSend', 'subject', 'message', 'attachName', 'attach', ),
        self::TYPE_COLNAME       => array(EmailMessagePendingTableMap::COL_EMP_USR_ID, EmailMessagePendingTableMap::COL_EMP_TO_SEND, EmailMessagePendingTableMap::COL_EMP_SUBJECT, EmailMessagePendingTableMap::COL_EMP_MESSAGE, EmailMessagePendingTableMap::COL_EMP_ATTACH_NAME, EmailMessagePendingTableMap::COL_EMP_ATTACH, ),
        self::TYPE_FIELDNAME     => array('emp_usr_id', 'emp_to_send', 'emp_subject', 'emp_message', 'emp_attach_name', 'emp_attach', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('UsrId' => 0, 'ToSend' => 1, 'Subject' => 2, 'Message' => 3, 'AttachName' => 4, 'Attach' => 5, ),
        self::TYPE_CAMELNAME     => array('usrId' => 0, 'toSend' => 1, 'subject' => 2, 'message' => 3, 'attachName' => 4, 'attach' => 5, ),
        self::TYPE_COLNAME       => array(EmailMessagePendingTableMap::COL_EMP_USR_ID => 0, EmailMessagePendingTableMap::COL_EMP_TO_SEND => 1, EmailMessagePendingTableMap::COL_EMP_SUBJECT => 2, EmailMessagePendingTableMap::COL_EMP_MESSAGE => 3, EmailMessagePendingTableMap::COL_EMP_ATTACH_NAME => 4, EmailMessagePendingTableMap::COL_EMP_ATTACH => 5, ),
        self::TYPE_FIELDNAME     => array('emp_usr_id' => 0, 'emp_to_send' => 1, 'emp_subject' => 2, 'emp_message' => 3, 'emp_attach_name' => 4, 'emp_attach' => 5, ),
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
        $this->setName('email_message_pending_emp');
        $this->setPhpName('EmailMessagePending');
        $this->setIdentifierQuoting(false);
        $this->setClassName('\\ChurchCRM\\EmailMessagePending');
        $this->setPackage('ChurchCRM');
        $this->setUseIdGenerator(false);
        // columns
        $this->addColumn('emp_usr_id', 'UsrId', 'SMALLINT', true, 9, 0);
        $this->addColumn('emp_to_send', 'ToSend', 'SMALLINT', true, 5, 0);
        $this->addColumn('emp_subject', 'Subject', 'VARCHAR', true, 128, null);
        $this->addColumn('emp_message', 'Message', 'LONGVARCHAR', true, null, null);
        $this->addColumn('emp_attach_name', 'AttachName', 'LONGVARCHAR', false, null, null);
        $this->addColumn('emp_attach', 'Attach', 'BOOLEAN', false, 1, null);
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
        return $withPrefix ? EmailMessagePendingTableMap::CLASS_DEFAULT : EmailMessagePendingTableMap::OM_CLASS;
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
     * @return array           (EmailMessagePending object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = EmailMessagePendingTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = EmailMessagePendingTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + EmailMessagePendingTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = EmailMessagePendingTableMap::OM_CLASS;
            /** @var EmailMessagePending $obj */
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            EmailMessagePendingTableMap::addInstanceToPool($obj, $key);
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
            $key = EmailMessagePendingTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = EmailMessagePendingTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                /** @var EmailMessagePending $obj */
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                EmailMessagePendingTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(EmailMessagePendingTableMap::COL_EMP_USR_ID);
            $criteria->addSelectColumn(EmailMessagePendingTableMap::COL_EMP_TO_SEND);
            $criteria->addSelectColumn(EmailMessagePendingTableMap::COL_EMP_SUBJECT);
            $criteria->addSelectColumn(EmailMessagePendingTableMap::COL_EMP_MESSAGE);
            $criteria->addSelectColumn(EmailMessagePendingTableMap::COL_EMP_ATTACH_NAME);
            $criteria->addSelectColumn(EmailMessagePendingTableMap::COL_EMP_ATTACH);
        } else {
            $criteria->addSelectColumn($alias . '.emp_usr_id');
            $criteria->addSelectColumn($alias . '.emp_to_send');
            $criteria->addSelectColumn($alias . '.emp_subject');
            $criteria->addSelectColumn($alias . '.emp_message');
            $criteria->addSelectColumn($alias . '.emp_attach_name');
            $criteria->addSelectColumn($alias . '.emp_attach');
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
        return Propel::getServiceContainer()->getDatabaseMap(EmailMessagePendingTableMap::DATABASE_NAME)->getTable(EmailMessagePendingTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
        $dbMap = Propel::getServiceContainer()->getDatabaseMap(EmailMessagePendingTableMap::DATABASE_NAME);
        if (!$dbMap->hasTable(EmailMessagePendingTableMap::TABLE_NAME)) {
            $dbMap->addTableObject(new EmailMessagePendingTableMap());
        }
    }

    /**
     * Performs a DELETE on the database, given a EmailMessagePending or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or EmailMessagePending object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(EmailMessagePendingTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \ChurchCRM\EmailMessagePending) { // it's a model object
            // create criteria based on pk value
            $criteria = $values->buildCriteria();
        } else { // it's a primary key, or an array of pks
            throw new LogicException('The EmailMessagePending object has no primary key');
        }

        $query = EmailMessagePendingQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) {
            EmailMessagePendingTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) {
                EmailMessagePendingTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the email_message_pending_emp table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return EmailMessagePendingQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a EmailMessagePending or Criteria object.
     *
     * @param mixed               $criteria Criteria or EmailMessagePending object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(EmailMessagePendingTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from EmailMessagePending object
        }


        // Set the correct dbName
        $query = EmailMessagePendingQuery::create()->mergeWith($criteria);

        // use transaction because $criteria could contain info
        // for more than one table (I guess, conceivably)
        return $con->transaction(function () use ($con, $query) {
            return $query->doInsert($con);
        });
    }

} // EmailMessagePendingTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
EmailMessagePendingTableMap::buildTableMap();
