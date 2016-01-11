<?php

namespace Map;

use \Person;
use \PersonQuery;
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
 * This class defines the structure of the 'person_per' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class PersonTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = '.Map.PersonTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'default';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'person_per';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\Person';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'Person';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 32;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 32;

    /**
     * the column name for the per_ID field
     */
    const COL_PER_ID = 'person_per.per_ID';

    /**
     * the column name for the per_Title field
     */
    const COL_PER_TITLE = 'person_per.per_Title';

    /**
     * the column name for the per_FirstName field
     */
    const COL_PER_FIRSTNAME = 'person_per.per_FirstName';

    /**
     * the column name for the per_MiddleName field
     */
    const COL_PER_MIDDLENAME = 'person_per.per_MiddleName';

    /**
     * the column name for the per_LastName field
     */
    const COL_PER_LASTNAME = 'person_per.per_LastName';

    /**
     * the column name for the per_Suffix field
     */
    const COL_PER_SUFFIX = 'person_per.per_Suffix';

    /**
     * the column name for the per_Address1 field
     */
    const COL_PER_ADDRESS1 = 'person_per.per_Address1';

    /**
     * the column name for the per_Address2 field
     */
    const COL_PER_ADDRESS2 = 'person_per.per_Address2';

    /**
     * the column name for the per_City field
     */
    const COL_PER_CITY = 'person_per.per_City';

    /**
     * the column name for the per_State field
     */
    const COL_PER_STATE = 'person_per.per_State';

    /**
     * the column name for the per_Zip field
     */
    const COL_PER_ZIP = 'person_per.per_Zip';

    /**
     * the column name for the per_Country field
     */
    const COL_PER_COUNTRY = 'person_per.per_Country';

    /**
     * the column name for the per_HomePhone field
     */
    const COL_PER_HOMEPHONE = 'person_per.per_HomePhone';

    /**
     * the column name for the per_WorkPhone field
     */
    const COL_PER_WORKPHONE = 'person_per.per_WorkPhone';

    /**
     * the column name for the per_CellPhone field
     */
    const COL_PER_CELLPHONE = 'person_per.per_CellPhone';

    /**
     * the column name for the per_Email field
     */
    const COL_PER_EMAIL = 'person_per.per_Email';

    /**
     * the column name for the per_WorkEmail field
     */
    const COL_PER_WORKEMAIL = 'person_per.per_WorkEmail';

    /**
     * the column name for the per_BirthMonth field
     */
    const COL_PER_BIRTHMONTH = 'person_per.per_BirthMonth';

    /**
     * the column name for the per_BirthDay field
     */
    const COL_PER_BIRTHDAY = 'person_per.per_BirthDay';

    /**
     * the column name for the per_BirthYear field
     */
    const COL_PER_BIRTHYEAR = 'person_per.per_BirthYear';

    /**
     * the column name for the per_MembershipDate field
     */
    const COL_PER_MEMBERSHIPDATE = 'person_per.per_MembershipDate';

    /**
     * the column name for the per_Gender field
     */
    const COL_PER_GENDER = 'person_per.per_Gender';

    /**
     * the column name for the per_fmr_ID field
     */
    const COL_PER_FMR_ID = 'person_per.per_fmr_ID';

    /**
     * the column name for the per_cls_ID field
     */
    const COL_PER_CLS_ID = 'person_per.per_cls_ID';

    /**
     * the column name for the per_fam_ID field
     */
    const COL_PER_FAM_ID = 'person_per.per_fam_ID';

    /**
     * the column name for the per_Envelope field
     */
    const COL_PER_ENVELOPE = 'person_per.per_Envelope';

    /**
     * the column name for the per_DateLastEdited field
     */
    const COL_PER_DATELASTEDITED = 'person_per.per_DateLastEdited';

    /**
     * the column name for the per_DateEntered field
     */
    const COL_PER_DATEENTERED = 'person_per.per_DateEntered';

    /**
     * the column name for the per_EnteredBy field
     */
    const COL_PER_ENTEREDBY = 'person_per.per_EnteredBy';

    /**
     * the column name for the per_EditedBy field
     */
    const COL_PER_EDITEDBY = 'person_per.per_EditedBy';

    /**
     * the column name for the per_FriendDate field
     */
    const COL_PER_FRIENDDATE = 'person_per.per_FriendDate';

    /**
     * the column name for the per_Flags field
     */
    const COL_PER_FLAGS = 'person_per.per_Flags';

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
        self::TYPE_PHPNAME       => array('Id', 'Title', 'FirstName', 'MiddleName', 'LastName', 'Suffix', 'Address1', 'Address2', 'City', 'State', 'Zip', 'Country', 'HomePhone', 'WorkPhone', 'CellPhone', 'Email', 'WorkEmail', 'BirthMonth', 'BirthDay', 'BirthYear', 'MembershipDate', 'Gender', 'FmrId', 'ClsId', 'FamId', 'Envelope', 'DateLastEdited', 'DateEntered', 'EnteredBy', 'EditedBy', 'FriendDate', 'Flags', ),
        self::TYPE_CAMELNAME     => array('id', 'title', 'firstName', 'middleName', 'lastName', 'suffix', 'address1', 'address2', 'city', 'state', 'zip', 'country', 'homePhone', 'workPhone', 'cellPhone', 'email', 'workEmail', 'birthMonth', 'birthDay', 'birthYear', 'membershipDate', 'gender', 'fmrId', 'clsId', 'famId', 'envelope', 'dateLastEdited', 'dateEntered', 'enteredBy', 'editedBy', 'friendDate', 'flags', ),
        self::TYPE_COLNAME       => array(PersonTableMap::COL_PER_ID, PersonTableMap::COL_PER_TITLE, PersonTableMap::COL_PER_FIRSTNAME, PersonTableMap::COL_PER_MIDDLENAME, PersonTableMap::COL_PER_LASTNAME, PersonTableMap::COL_PER_SUFFIX, PersonTableMap::COL_PER_ADDRESS1, PersonTableMap::COL_PER_ADDRESS2, PersonTableMap::COL_PER_CITY, PersonTableMap::COL_PER_STATE, PersonTableMap::COL_PER_ZIP, PersonTableMap::COL_PER_COUNTRY, PersonTableMap::COL_PER_HOMEPHONE, PersonTableMap::COL_PER_WORKPHONE, PersonTableMap::COL_PER_CELLPHONE, PersonTableMap::COL_PER_EMAIL, PersonTableMap::COL_PER_WORKEMAIL, PersonTableMap::COL_PER_BIRTHMONTH, PersonTableMap::COL_PER_BIRTHDAY, PersonTableMap::COL_PER_BIRTHYEAR, PersonTableMap::COL_PER_MEMBERSHIPDATE, PersonTableMap::COL_PER_GENDER, PersonTableMap::COL_PER_FMR_ID, PersonTableMap::COL_PER_CLS_ID, PersonTableMap::COL_PER_FAM_ID, PersonTableMap::COL_PER_ENVELOPE, PersonTableMap::COL_PER_DATELASTEDITED, PersonTableMap::COL_PER_DATEENTERED, PersonTableMap::COL_PER_ENTEREDBY, PersonTableMap::COL_PER_EDITEDBY, PersonTableMap::COL_PER_FRIENDDATE, PersonTableMap::COL_PER_FLAGS, ),
        self::TYPE_FIELDNAME     => array('per_ID', 'per_Title', 'per_FirstName', 'per_MiddleName', 'per_LastName', 'per_Suffix', 'per_Address1', 'per_Address2', 'per_City', 'per_State', 'per_Zip', 'per_Country', 'per_HomePhone', 'per_WorkPhone', 'per_CellPhone', 'per_Email', 'per_WorkEmail', 'per_BirthMonth', 'per_BirthDay', 'per_BirthYear', 'per_MembershipDate', 'per_Gender', 'per_fmr_ID', 'per_cls_ID', 'per_fam_ID', 'per_Envelope', 'per_DateLastEdited', 'per_DateEntered', 'per_EnteredBy', 'per_EditedBy', 'per_FriendDate', 'per_Flags', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'Title' => 1, 'FirstName' => 2, 'MiddleName' => 3, 'LastName' => 4, 'Suffix' => 5, 'Address1' => 6, 'Address2' => 7, 'City' => 8, 'State' => 9, 'Zip' => 10, 'Country' => 11, 'HomePhone' => 12, 'WorkPhone' => 13, 'CellPhone' => 14, 'Email' => 15, 'WorkEmail' => 16, 'BirthMonth' => 17, 'BirthDay' => 18, 'BirthYear' => 19, 'MembershipDate' => 20, 'Gender' => 21, 'FmrId' => 22, 'ClsId' => 23, 'FamId' => 24, 'Envelope' => 25, 'DateLastEdited' => 26, 'DateEntered' => 27, 'EnteredBy' => 28, 'EditedBy' => 29, 'FriendDate' => 30, 'Flags' => 31, ),
        self::TYPE_CAMELNAME     => array('id' => 0, 'title' => 1, 'firstName' => 2, 'middleName' => 3, 'lastName' => 4, 'suffix' => 5, 'address1' => 6, 'address2' => 7, 'city' => 8, 'state' => 9, 'zip' => 10, 'country' => 11, 'homePhone' => 12, 'workPhone' => 13, 'cellPhone' => 14, 'email' => 15, 'workEmail' => 16, 'birthMonth' => 17, 'birthDay' => 18, 'birthYear' => 19, 'membershipDate' => 20, 'gender' => 21, 'fmrId' => 22, 'clsId' => 23, 'famId' => 24, 'envelope' => 25, 'dateLastEdited' => 26, 'dateEntered' => 27, 'enteredBy' => 28, 'editedBy' => 29, 'friendDate' => 30, 'flags' => 31, ),
        self::TYPE_COLNAME       => array(PersonTableMap::COL_PER_ID => 0, PersonTableMap::COL_PER_TITLE => 1, PersonTableMap::COL_PER_FIRSTNAME => 2, PersonTableMap::COL_PER_MIDDLENAME => 3, PersonTableMap::COL_PER_LASTNAME => 4, PersonTableMap::COL_PER_SUFFIX => 5, PersonTableMap::COL_PER_ADDRESS1 => 6, PersonTableMap::COL_PER_ADDRESS2 => 7, PersonTableMap::COL_PER_CITY => 8, PersonTableMap::COL_PER_STATE => 9, PersonTableMap::COL_PER_ZIP => 10, PersonTableMap::COL_PER_COUNTRY => 11, PersonTableMap::COL_PER_HOMEPHONE => 12, PersonTableMap::COL_PER_WORKPHONE => 13, PersonTableMap::COL_PER_CELLPHONE => 14, PersonTableMap::COL_PER_EMAIL => 15, PersonTableMap::COL_PER_WORKEMAIL => 16, PersonTableMap::COL_PER_BIRTHMONTH => 17, PersonTableMap::COL_PER_BIRTHDAY => 18, PersonTableMap::COL_PER_BIRTHYEAR => 19, PersonTableMap::COL_PER_MEMBERSHIPDATE => 20, PersonTableMap::COL_PER_GENDER => 21, PersonTableMap::COL_PER_FMR_ID => 22, PersonTableMap::COL_PER_CLS_ID => 23, PersonTableMap::COL_PER_FAM_ID => 24, PersonTableMap::COL_PER_ENVELOPE => 25, PersonTableMap::COL_PER_DATELASTEDITED => 26, PersonTableMap::COL_PER_DATEENTERED => 27, PersonTableMap::COL_PER_ENTEREDBY => 28, PersonTableMap::COL_PER_EDITEDBY => 29, PersonTableMap::COL_PER_FRIENDDATE => 30, PersonTableMap::COL_PER_FLAGS => 31, ),
        self::TYPE_FIELDNAME     => array('per_ID' => 0, 'per_Title' => 1, 'per_FirstName' => 2, 'per_MiddleName' => 3, 'per_LastName' => 4, 'per_Suffix' => 5, 'per_Address1' => 6, 'per_Address2' => 7, 'per_City' => 8, 'per_State' => 9, 'per_Zip' => 10, 'per_Country' => 11, 'per_HomePhone' => 12, 'per_WorkPhone' => 13, 'per_CellPhone' => 14, 'per_Email' => 15, 'per_WorkEmail' => 16, 'per_BirthMonth' => 17, 'per_BirthDay' => 18, 'per_BirthYear' => 19, 'per_MembershipDate' => 20, 'per_Gender' => 21, 'per_fmr_ID' => 22, 'per_cls_ID' => 23, 'per_fam_ID' => 24, 'per_Envelope' => 25, 'per_DateLastEdited' => 26, 'per_DateEntered' => 27, 'per_EnteredBy' => 28, 'per_EditedBy' => 29, 'per_FriendDate' => 30, 'per_Flags' => 31, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, )
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
        $this->setName('person_per');
        $this->setPhpName('Person');
        $this->setIdentifierQuoting(false);
        $this->setClassName('\\Person');
        $this->setPackage('');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('per_ID', 'Id', 'SMALLINT', true, 9, null);
        $this->addColumn('per_Title', 'Title', 'VARCHAR', false, 50, null);
        $this->addColumn('per_FirstName', 'FirstName', 'VARCHAR', false, 50, null);
        $this->addColumn('per_MiddleName', 'MiddleName', 'VARCHAR', false, 50, null);
        $this->addColumn('per_LastName', 'LastName', 'VARCHAR', false, 50, null);
        $this->addColumn('per_Suffix', 'Suffix', 'VARCHAR', false, 50, null);
        $this->addColumn('per_Address1', 'Address1', 'VARCHAR', false, 50, null);
        $this->addColumn('per_Address2', 'Address2', 'VARCHAR', false, 50, null);
        $this->addColumn('per_City', 'City', 'VARCHAR', false, 50, null);
        $this->addColumn('per_State', 'State', 'VARCHAR', false, 50, null);
        $this->addColumn('per_Zip', 'Zip', 'VARCHAR', false, 50, null);
        $this->addColumn('per_Country', 'Country', 'VARCHAR', false, 50, null);
        $this->addColumn('per_HomePhone', 'HomePhone', 'VARCHAR', false, 30, null);
        $this->addColumn('per_WorkPhone', 'WorkPhone', 'VARCHAR', false, 30, null);
        $this->addColumn('per_CellPhone', 'CellPhone', 'VARCHAR', false, 30, null);
        $this->addColumn('per_Email', 'Email', 'VARCHAR', false, 50, null);
        $this->addColumn('per_WorkEmail', 'WorkEmail', 'VARCHAR', false, 50, null);
        $this->addColumn('per_BirthMonth', 'BirthMonth', 'TINYINT', true, 3, 0);
        $this->addColumn('per_BirthDay', 'BirthDay', 'TINYINT', true, 3, 0);
        $this->addColumn('per_BirthYear', 'BirthYear', 'INTEGER', false, 4, null);
        $this->addColumn('per_MembershipDate', 'MembershipDate', 'DATE', false, null, null);
        $this->addColumn('per_Gender', 'Gender', 'BOOLEAN', true, 1, false);
        $this->addColumn('per_fmr_ID', 'FmrId', 'TINYINT', true, 3, 0);
        $this->addColumn('per_cls_ID', 'ClsId', 'TINYINT', true, 3, 0);
        $this->addColumn('per_fam_ID', 'FamId', 'SMALLINT', true, 5, 0);
        $this->addColumn('per_Envelope', 'Envelope', 'SMALLINT', false, 5, null);
        $this->addColumn('per_DateLastEdited', 'DateLastEdited', 'TIMESTAMP', false, null, null);
        $this->addColumn('per_DateEntered', 'DateEntered', 'TIMESTAMP', true, null, '0000-00-00 00:00:00');
        $this->addColumn('per_EnteredBy', 'EnteredBy', 'SMALLINT', true, 5, 0);
        $this->addColumn('per_EditedBy', 'EditedBy', 'SMALLINT', false, 5, 0);
        $this->addColumn('per_FriendDate', 'FriendDate', 'DATE', false, null, null);
        $this->addColumn('per_Flags', 'Flags', 'SMALLINT', true, 9, 0);
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
        return $withPrefix ? PersonTableMap::CLASS_DEFAULT : PersonTableMap::OM_CLASS;
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
     * @return array           (Person object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = PersonTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = PersonTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + PersonTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = PersonTableMap::OM_CLASS;
            /** @var Person $obj */
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            PersonTableMap::addInstanceToPool($obj, $key);
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
            $key = PersonTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = PersonTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                /** @var Person $obj */
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                PersonTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(PersonTableMap::COL_PER_ID);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_TITLE);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_FIRSTNAME);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_MIDDLENAME);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_LASTNAME);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_SUFFIX);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_ADDRESS1);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_ADDRESS2);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_CITY);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_STATE);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_ZIP);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_COUNTRY);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_HOMEPHONE);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_WORKPHONE);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_CELLPHONE);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_EMAIL);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_WORKEMAIL);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_BIRTHMONTH);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_BIRTHDAY);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_BIRTHYEAR);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_MEMBERSHIPDATE);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_GENDER);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_FMR_ID);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_CLS_ID);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_FAM_ID);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_ENVELOPE);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_DATELASTEDITED);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_DATEENTERED);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_ENTEREDBY);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_EDITEDBY);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_FRIENDDATE);
            $criteria->addSelectColumn(PersonTableMap::COL_PER_FLAGS);
        } else {
            $criteria->addSelectColumn($alias . '.per_ID');
            $criteria->addSelectColumn($alias . '.per_Title');
            $criteria->addSelectColumn($alias . '.per_FirstName');
            $criteria->addSelectColumn($alias . '.per_MiddleName');
            $criteria->addSelectColumn($alias . '.per_LastName');
            $criteria->addSelectColumn($alias . '.per_Suffix');
            $criteria->addSelectColumn($alias . '.per_Address1');
            $criteria->addSelectColumn($alias . '.per_Address2');
            $criteria->addSelectColumn($alias . '.per_City');
            $criteria->addSelectColumn($alias . '.per_State');
            $criteria->addSelectColumn($alias . '.per_Zip');
            $criteria->addSelectColumn($alias . '.per_Country');
            $criteria->addSelectColumn($alias . '.per_HomePhone');
            $criteria->addSelectColumn($alias . '.per_WorkPhone');
            $criteria->addSelectColumn($alias . '.per_CellPhone');
            $criteria->addSelectColumn($alias . '.per_Email');
            $criteria->addSelectColumn($alias . '.per_WorkEmail');
            $criteria->addSelectColumn($alias . '.per_BirthMonth');
            $criteria->addSelectColumn($alias . '.per_BirthDay');
            $criteria->addSelectColumn($alias . '.per_BirthYear');
            $criteria->addSelectColumn($alias . '.per_MembershipDate');
            $criteria->addSelectColumn($alias . '.per_Gender');
            $criteria->addSelectColumn($alias . '.per_fmr_ID');
            $criteria->addSelectColumn($alias . '.per_cls_ID');
            $criteria->addSelectColumn($alias . '.per_fam_ID');
            $criteria->addSelectColumn($alias . '.per_Envelope');
            $criteria->addSelectColumn($alias . '.per_DateLastEdited');
            $criteria->addSelectColumn($alias . '.per_DateEntered');
            $criteria->addSelectColumn($alias . '.per_EnteredBy');
            $criteria->addSelectColumn($alias . '.per_EditedBy');
            $criteria->addSelectColumn($alias . '.per_FriendDate');
            $criteria->addSelectColumn($alias . '.per_Flags');
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
        return Propel::getServiceContainer()->getDatabaseMap(PersonTableMap::DATABASE_NAME)->getTable(PersonTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
        $dbMap = Propel::getServiceContainer()->getDatabaseMap(PersonTableMap::DATABASE_NAME);
        if (!$dbMap->hasTable(PersonTableMap::TABLE_NAME)) {
            $dbMap->addTableObject(new PersonTableMap());
        }
    }

    /**
     * Performs a DELETE on the database, given a Person or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or Person object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(PersonTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \Person) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(PersonTableMap::DATABASE_NAME);
            $criteria->add(PersonTableMap::COL_PER_ID, (array) $values, Criteria::IN);
        }

        $query = PersonQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) {
            PersonTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) {
                PersonTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the person_per table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return PersonQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a Person or Criteria object.
     *
     * @param mixed               $criteria Criteria or Person object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(PersonTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from Person object
        }

        if ($criteria->containsKey(PersonTableMap::COL_PER_ID) && $criteria->keyContainsValue(PersonTableMap::COL_PER_ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.PersonTableMap::COL_PER_ID.')');
        }


        // Set the correct dbName
        $query = PersonQuery::create()->mergeWith($criteria);

        // use transaction because $criteria could contain info
        // for more than one table (I guess, conceivably)
        return $con->transaction(function () use ($con, $query) {
            return $query->doInsert($con);
        });
    }

} // PersonTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
PersonTableMap::buildTableMap();
