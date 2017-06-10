<?php

namespace ChurchCRM\Map;

use ChurchCRM\AutoPayment;
use ChurchCRM\AutoPaymentQuery;
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
 * This class defines the structure of the 'autopayment_aut' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class AutoPaymentTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'ChurchCRM.Map.AutoPaymentTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'default';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'autopayment_aut';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\ChurchCRM\\AutoPayment';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'ChurchCRM.AutoPayment';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 30;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 30;

    /**
     * the column name for the aut_ID field
     */
    const COL_AUT_ID = 'autopayment_aut.aut_ID';

    /**
     * the column name for the aut_FamID field
     */
    const COL_AUT_FAMID = 'autopayment_aut.aut_FamID';

    /**
     * the column name for the aut_EnableBankDraft field
     */
    const COL_AUT_ENABLEBANKDRAFT = 'autopayment_aut.aut_EnableBankDraft';

    /**
     * the column name for the aut_EnableCreditCard field
     */
    const COL_AUT_ENABLECREDITCARD = 'autopayment_aut.aut_EnableCreditCard';

    /**
     * the column name for the aut_NextPayDate field
     */
    const COL_AUT_NEXTPAYDATE = 'autopayment_aut.aut_NextPayDate';

    /**
     * the column name for the aut_FYID field
     */
    const COL_AUT_FYID = 'autopayment_aut.aut_FYID';

    /**
     * the column name for the aut_Amount field
     */
    const COL_AUT_AMOUNT = 'autopayment_aut.aut_Amount';

    /**
     * the column name for the aut_Interval field
     */
    const COL_AUT_INTERVAL = 'autopayment_aut.aut_Interval';

    /**
     * the column name for the aut_Fund field
     */
    const COL_AUT_FUND = 'autopayment_aut.aut_Fund';

    /**
     * the column name for the aut_FirstName field
     */
    const COL_AUT_FIRSTNAME = 'autopayment_aut.aut_FirstName';

    /**
     * the column name for the aut_LastName field
     */
    const COL_AUT_LASTNAME = 'autopayment_aut.aut_LastName';

    /**
     * the column name for the aut_Address1 field
     */
    const COL_AUT_ADDRESS1 = 'autopayment_aut.aut_Address1';

    /**
     * the column name for the aut_Address2 field
     */
    const COL_AUT_ADDRESS2 = 'autopayment_aut.aut_Address2';

    /**
     * the column name for the aut_City field
     */
    const COL_AUT_CITY = 'autopayment_aut.aut_City';

    /**
     * the column name for the aut_State field
     */
    const COL_AUT_STATE = 'autopayment_aut.aut_State';

    /**
     * the column name for the aut_Zip field
     */
    const COL_AUT_ZIP = 'autopayment_aut.aut_Zip';

    /**
     * the column name for the aut_Country field
     */
    const COL_AUT_COUNTRY = 'autopayment_aut.aut_Country';

    /**
     * the column name for the aut_Phone field
     */
    const COL_AUT_PHONE = 'autopayment_aut.aut_Phone';

    /**
     * the column name for the aut_Email field
     */
    const COL_AUT_EMAIL = 'autopayment_aut.aut_Email';

    /**
     * the column name for the aut_CreditCard field
     */
    const COL_AUT_CREDITCARD = 'autopayment_aut.aut_CreditCard';

    /**
     * the column name for the aut_ExpMonth field
     */
    const COL_AUT_EXPMONTH = 'autopayment_aut.aut_ExpMonth';

    /**
     * the column name for the aut_ExpYear field
     */
    const COL_AUT_EXPYEAR = 'autopayment_aut.aut_ExpYear';

    /**
     * the column name for the aut_BankName field
     */
    const COL_AUT_BANKNAME = 'autopayment_aut.aut_BankName';

    /**
     * the column name for the aut_Route field
     */
    const COL_AUT_ROUTE = 'autopayment_aut.aut_Route';

    /**
     * the column name for the aut_Account field
     */
    const COL_AUT_ACCOUNT = 'autopayment_aut.aut_Account';

    /**
     * the column name for the aut_DateLastEdited field
     */
    const COL_AUT_DATELASTEDITED = 'autopayment_aut.aut_DateLastEdited';

    /**
     * the column name for the aut_EditedBy field
     */
    const COL_AUT_EDITEDBY = 'autopayment_aut.aut_EditedBy';

    /**
     * the column name for the aut_Serial field
     */
    const COL_AUT_SERIAL = 'autopayment_aut.aut_Serial';

    /**
     * the column name for the aut_CreditCardVanco field
     */
    const COL_AUT_CREDITCARDVANCO = 'autopayment_aut.aut_CreditCardVanco';

    /**
     * the column name for the aut_AccountVanco field
     */
    const COL_AUT_ACCOUNTVANCO = 'autopayment_aut.aut_AccountVanco';

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
        self::TYPE_PHPNAME       => array('Id', 'Familyid', 'EnableBankDraft', 'EnableCreditCard', 'NextPayDate', 'Fyid', 'Amount', 'Interval', 'Fund', 'FirstName', 'LastName', 'Address1', 'Address2', 'City', 'State', 'Zip', 'Country', 'Phone', 'Email', 'CreditCard', 'ExpMonth', 'ExpYear', 'BankName', 'Route', 'Account', 'DateLastEdited', 'Editedby', 'Serial', 'Creditcardvanco', 'AccountVanco', ),
        self::TYPE_CAMELNAME     => array('id', 'familyid', 'enableBankDraft', 'enableCreditCard', 'nextPayDate', 'fyid', 'amount', 'interval', 'fund', 'firstName', 'lastName', 'address1', 'address2', 'city', 'state', 'zip', 'country', 'phone', 'email', 'creditCard', 'expMonth', 'expYear', 'bankName', 'route', 'account', 'dateLastEdited', 'editedby', 'serial', 'creditcardvanco', 'accountVanco', ),
        self::TYPE_COLNAME       => array(AutoPaymentTableMap::COL_AUT_ID, AutoPaymentTableMap::COL_AUT_FAMID, AutoPaymentTableMap::COL_AUT_ENABLEBANKDRAFT, AutoPaymentTableMap::COL_AUT_ENABLECREDITCARD, AutoPaymentTableMap::COL_AUT_NEXTPAYDATE, AutoPaymentTableMap::COL_AUT_FYID, AutoPaymentTableMap::COL_AUT_AMOUNT, AutoPaymentTableMap::COL_AUT_INTERVAL, AutoPaymentTableMap::COL_AUT_FUND, AutoPaymentTableMap::COL_AUT_FIRSTNAME, AutoPaymentTableMap::COL_AUT_LASTNAME, AutoPaymentTableMap::COL_AUT_ADDRESS1, AutoPaymentTableMap::COL_AUT_ADDRESS2, AutoPaymentTableMap::COL_AUT_CITY, AutoPaymentTableMap::COL_AUT_STATE, AutoPaymentTableMap::COL_AUT_ZIP, AutoPaymentTableMap::COL_AUT_COUNTRY, AutoPaymentTableMap::COL_AUT_PHONE, AutoPaymentTableMap::COL_AUT_EMAIL, AutoPaymentTableMap::COL_AUT_CREDITCARD, AutoPaymentTableMap::COL_AUT_EXPMONTH, AutoPaymentTableMap::COL_AUT_EXPYEAR, AutoPaymentTableMap::COL_AUT_BANKNAME, AutoPaymentTableMap::COL_AUT_ROUTE, AutoPaymentTableMap::COL_AUT_ACCOUNT, AutoPaymentTableMap::COL_AUT_DATELASTEDITED, AutoPaymentTableMap::COL_AUT_EDITEDBY, AutoPaymentTableMap::COL_AUT_SERIAL, AutoPaymentTableMap::COL_AUT_CREDITCARDVANCO, AutoPaymentTableMap::COL_AUT_ACCOUNTVANCO, ),
        self::TYPE_FIELDNAME     => array('aut_ID', 'aut_FamID', 'aut_EnableBankDraft', 'aut_EnableCreditCard', 'aut_NextPayDate', 'aut_FYID', 'aut_Amount', 'aut_Interval', 'aut_Fund', 'aut_FirstName', 'aut_LastName', 'aut_Address1', 'aut_Address2', 'aut_City', 'aut_State', 'aut_Zip', 'aut_Country', 'aut_Phone', 'aut_Email', 'aut_CreditCard', 'aut_ExpMonth', 'aut_ExpYear', 'aut_BankName', 'aut_Route', 'aut_Account', 'aut_DateLastEdited', 'aut_EditedBy', 'aut_Serial', 'aut_CreditCardVanco', 'aut_AccountVanco', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'Familyid' => 1, 'EnableBankDraft' => 2, 'EnableCreditCard' => 3, 'NextPayDate' => 4, 'Fyid' => 5, 'Amount' => 6, 'Interval' => 7, 'Fund' => 8, 'FirstName' => 9, 'LastName' => 10, 'Address1' => 11, 'Address2' => 12, 'City' => 13, 'State' => 14, 'Zip' => 15, 'Country' => 16, 'Phone' => 17, 'Email' => 18, 'CreditCard' => 19, 'ExpMonth' => 20, 'ExpYear' => 21, 'BankName' => 22, 'Route' => 23, 'Account' => 24, 'DateLastEdited' => 25, 'Editedby' => 26, 'Serial' => 27, 'Creditcardvanco' => 28, 'AccountVanco' => 29, ),
        self::TYPE_CAMELNAME     => array('id' => 0, 'familyid' => 1, 'enableBankDraft' => 2, 'enableCreditCard' => 3, 'nextPayDate' => 4, 'fyid' => 5, 'amount' => 6, 'interval' => 7, 'fund' => 8, 'firstName' => 9, 'lastName' => 10, 'address1' => 11, 'address2' => 12, 'city' => 13, 'state' => 14, 'zip' => 15, 'country' => 16, 'phone' => 17, 'email' => 18, 'creditCard' => 19, 'expMonth' => 20, 'expYear' => 21, 'bankName' => 22, 'route' => 23, 'account' => 24, 'dateLastEdited' => 25, 'editedby' => 26, 'serial' => 27, 'creditcardvanco' => 28, 'accountVanco' => 29, ),
        self::TYPE_COLNAME       => array(AutoPaymentTableMap::COL_AUT_ID => 0, AutoPaymentTableMap::COL_AUT_FAMID => 1, AutoPaymentTableMap::COL_AUT_ENABLEBANKDRAFT => 2, AutoPaymentTableMap::COL_AUT_ENABLECREDITCARD => 3, AutoPaymentTableMap::COL_AUT_NEXTPAYDATE => 4, AutoPaymentTableMap::COL_AUT_FYID => 5, AutoPaymentTableMap::COL_AUT_AMOUNT => 6, AutoPaymentTableMap::COL_AUT_INTERVAL => 7, AutoPaymentTableMap::COL_AUT_FUND => 8, AutoPaymentTableMap::COL_AUT_FIRSTNAME => 9, AutoPaymentTableMap::COL_AUT_LASTNAME => 10, AutoPaymentTableMap::COL_AUT_ADDRESS1 => 11, AutoPaymentTableMap::COL_AUT_ADDRESS2 => 12, AutoPaymentTableMap::COL_AUT_CITY => 13, AutoPaymentTableMap::COL_AUT_STATE => 14, AutoPaymentTableMap::COL_AUT_ZIP => 15, AutoPaymentTableMap::COL_AUT_COUNTRY => 16, AutoPaymentTableMap::COL_AUT_PHONE => 17, AutoPaymentTableMap::COL_AUT_EMAIL => 18, AutoPaymentTableMap::COL_AUT_CREDITCARD => 19, AutoPaymentTableMap::COL_AUT_EXPMONTH => 20, AutoPaymentTableMap::COL_AUT_EXPYEAR => 21, AutoPaymentTableMap::COL_AUT_BANKNAME => 22, AutoPaymentTableMap::COL_AUT_ROUTE => 23, AutoPaymentTableMap::COL_AUT_ACCOUNT => 24, AutoPaymentTableMap::COL_AUT_DATELASTEDITED => 25, AutoPaymentTableMap::COL_AUT_EDITEDBY => 26, AutoPaymentTableMap::COL_AUT_SERIAL => 27, AutoPaymentTableMap::COL_AUT_CREDITCARDVANCO => 28, AutoPaymentTableMap::COL_AUT_ACCOUNTVANCO => 29, ),
        self::TYPE_FIELDNAME     => array('aut_ID' => 0, 'aut_FamID' => 1, 'aut_EnableBankDraft' => 2, 'aut_EnableCreditCard' => 3, 'aut_NextPayDate' => 4, 'aut_FYID' => 5, 'aut_Amount' => 6, 'aut_Interval' => 7, 'aut_Fund' => 8, 'aut_FirstName' => 9, 'aut_LastName' => 10, 'aut_Address1' => 11, 'aut_Address2' => 12, 'aut_City' => 13, 'aut_State' => 14, 'aut_Zip' => 15, 'aut_Country' => 16, 'aut_Phone' => 17, 'aut_Email' => 18, 'aut_CreditCard' => 19, 'aut_ExpMonth' => 20, 'aut_ExpYear' => 21, 'aut_BankName' => 22, 'aut_Route' => 23, 'aut_Account' => 24, 'aut_DateLastEdited' => 25, 'aut_EditedBy' => 26, 'aut_Serial' => 27, 'aut_CreditCardVanco' => 28, 'aut_AccountVanco' => 29, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, )
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
        $this->setName('autopayment_aut');
        $this->setPhpName('AutoPayment');
        $this->setIdentifierQuoting(false);
        $this->setClassName('\\ChurchCRM\\AutoPayment');
        $this->setPackage('ChurchCRM');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('aut_ID', 'Id', 'SMALLINT', true, 9, null);
        $this->addColumn('aut_FamID', 'Familyid', 'SMALLINT', true, 9, 0);
        $this->addColumn('aut_EnableBankDraft', 'EnableBankDraft', 'BOOLEAN', true, 1, false);
        $this->addColumn('aut_EnableCreditCard', 'EnableCreditCard', 'BOOLEAN', true, 1, false);
        $this->addColumn('aut_NextPayDate', 'NextPayDate', 'DATE', false, null, null);
        $this->addColumn('aut_FYID', 'Fyid', 'SMALLINT', true, 9, 9);
        $this->addColumn('aut_Amount', 'Amount', 'DECIMAL', true, 6, 0);
        $this->addColumn('aut_Interval', 'Interval', 'TINYINT', true, 3, 1);
        $this->addColumn('aut_Fund', 'Fund', 'SMALLINT', true, 6, 0);
        $this->addColumn('aut_FirstName', 'FirstName', 'VARCHAR', false, 50, null);
        $this->addColumn('aut_LastName', 'LastName', 'VARCHAR', false, 50, null);
        $this->addColumn('aut_Address1', 'Address1', 'VARCHAR', false, 255, null);
        $this->addColumn('aut_Address2', 'Address2', 'VARCHAR', false, 255, null);
        $this->addColumn('aut_City', 'City', 'VARCHAR', false, 50, null);
        $this->addColumn('aut_State', 'State', 'VARCHAR', false, 50, null);
        $this->addColumn('aut_Zip', 'Zip', 'VARCHAR', false, 50, null);
        $this->addColumn('aut_Country', 'Country', 'VARCHAR', false, 50, null);
        $this->addColumn('aut_Phone', 'Phone', 'VARCHAR', false, 30, null);
        $this->addColumn('aut_Email', 'Email', 'VARCHAR', false, 100, null);
        $this->addColumn('aut_CreditCard', 'CreditCard', 'VARCHAR', false, 50, null);
        $this->addColumn('aut_ExpMonth', 'ExpMonth', 'VARCHAR', false, 2, null);
        $this->addColumn('aut_ExpYear', 'ExpYear', 'VARCHAR', false, 4, null);
        $this->addColumn('aut_BankName', 'BankName', 'VARCHAR', false, 50, null);
        $this->addColumn('aut_Route', 'Route', 'VARCHAR', false, 30, null);
        $this->addColumn('aut_Account', 'Account', 'VARCHAR', false, 30, null);
        $this->addColumn('aut_DateLastEdited', 'DateLastEdited', 'TIMESTAMP', false, null, null);
        $this->addColumn('aut_EditedBy', 'Editedby', 'SMALLINT', false, 5, 0);
        $this->addColumn('aut_Serial', 'Serial', 'SMALLINT', true, 9, 1);
        $this->addColumn('aut_CreditCardVanco', 'Creditcardvanco', 'VARCHAR', false, 50, null);
        $this->addColumn('aut_AccountVanco', 'AccountVanco', 'VARCHAR', false, 50, null);
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
        return $withPrefix ? AutoPaymentTableMap::CLASS_DEFAULT : AutoPaymentTableMap::OM_CLASS;
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
     * @return array           (AutoPayment object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = AutoPaymentTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = AutoPaymentTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + AutoPaymentTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = AutoPaymentTableMap::OM_CLASS;
            /** @var AutoPayment $obj */
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            AutoPaymentTableMap::addInstanceToPool($obj, $key);
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
            $key = AutoPaymentTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = AutoPaymentTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                /** @var AutoPayment $obj */
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                AutoPaymentTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_ID);
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_FAMID);
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_ENABLEBANKDRAFT);
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_ENABLECREDITCARD);
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_NEXTPAYDATE);
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_FYID);
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_AMOUNT);
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_INTERVAL);
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_FUND);
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_FIRSTNAME);
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_LASTNAME);
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_ADDRESS1);
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_ADDRESS2);
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_CITY);
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_STATE);
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_ZIP);
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_COUNTRY);
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_PHONE);
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_EMAIL);
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_CREDITCARD);
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_EXPMONTH);
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_EXPYEAR);
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_BANKNAME);
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_ROUTE);
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_ACCOUNT);
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_DATELASTEDITED);
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_EDITEDBY);
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_SERIAL);
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_CREDITCARDVANCO);
            $criteria->addSelectColumn(AutoPaymentTableMap::COL_AUT_ACCOUNTVANCO);
        } else {
            $criteria->addSelectColumn($alias . '.aut_ID');
            $criteria->addSelectColumn($alias . '.aut_FamID');
            $criteria->addSelectColumn($alias . '.aut_EnableBankDraft');
            $criteria->addSelectColumn($alias . '.aut_EnableCreditCard');
            $criteria->addSelectColumn($alias . '.aut_NextPayDate');
            $criteria->addSelectColumn($alias . '.aut_FYID');
            $criteria->addSelectColumn($alias . '.aut_Amount');
            $criteria->addSelectColumn($alias . '.aut_Interval');
            $criteria->addSelectColumn($alias . '.aut_Fund');
            $criteria->addSelectColumn($alias . '.aut_FirstName');
            $criteria->addSelectColumn($alias . '.aut_LastName');
            $criteria->addSelectColumn($alias . '.aut_Address1');
            $criteria->addSelectColumn($alias . '.aut_Address2');
            $criteria->addSelectColumn($alias . '.aut_City');
            $criteria->addSelectColumn($alias . '.aut_State');
            $criteria->addSelectColumn($alias . '.aut_Zip');
            $criteria->addSelectColumn($alias . '.aut_Country');
            $criteria->addSelectColumn($alias . '.aut_Phone');
            $criteria->addSelectColumn($alias . '.aut_Email');
            $criteria->addSelectColumn($alias . '.aut_CreditCard');
            $criteria->addSelectColumn($alias . '.aut_ExpMonth');
            $criteria->addSelectColumn($alias . '.aut_ExpYear');
            $criteria->addSelectColumn($alias . '.aut_BankName');
            $criteria->addSelectColumn($alias . '.aut_Route');
            $criteria->addSelectColumn($alias . '.aut_Account');
            $criteria->addSelectColumn($alias . '.aut_DateLastEdited');
            $criteria->addSelectColumn($alias . '.aut_EditedBy');
            $criteria->addSelectColumn($alias . '.aut_Serial');
            $criteria->addSelectColumn($alias . '.aut_CreditCardVanco');
            $criteria->addSelectColumn($alias . '.aut_AccountVanco');
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
        return Propel::getServiceContainer()->getDatabaseMap(AutoPaymentTableMap::DATABASE_NAME)->getTable(AutoPaymentTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
        $dbMap = Propel::getServiceContainer()->getDatabaseMap(AutoPaymentTableMap::DATABASE_NAME);
        if (!$dbMap->hasTable(AutoPaymentTableMap::TABLE_NAME)) {
            $dbMap->addTableObject(new AutoPaymentTableMap());
        }
    }

    /**
     * Performs a DELETE on the database, given a AutoPayment or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or AutoPayment object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(AutoPaymentTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \ChurchCRM\AutoPayment) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(AutoPaymentTableMap::DATABASE_NAME);
            $criteria->add(AutoPaymentTableMap::COL_AUT_ID, (array) $values, Criteria::IN);
        }

        $query = AutoPaymentQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) {
            AutoPaymentTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) {
                AutoPaymentTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the autopayment_aut table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return AutoPaymentQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a AutoPayment or Criteria object.
     *
     * @param mixed               $criteria Criteria or AutoPayment object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(AutoPaymentTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from AutoPayment object
        }

        if ($criteria->containsKey(AutoPaymentTableMap::COL_AUT_ID) && $criteria->keyContainsValue(AutoPaymentTableMap::COL_AUT_ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.AutoPaymentTableMap::COL_AUT_ID.')');
        }


        // Set the correct dbName
        $query = AutoPaymentQuery::create()->mergeWith($criteria);

        // use transaction because $criteria could contain info
        // for more than one table (I guess, conceivably)
        return $con->transaction(function () use ($con, $query) {
            return $query->doInsert($con);
        });
    }

} // AutoPaymentTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
AutoPaymentTableMap::buildTableMap();
