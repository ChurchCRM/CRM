<?php

namespace ChurchCRM\Map;

use ChurchCRM\User;
use ChurchCRM\UserQuery;
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
 * This class defines the structure of the 'user_usr' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class UserTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'ChurchCRM.Map.UserTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'default';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'user_usr';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\ChurchCRM\\User';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'ChurchCRM.User';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 38;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 38;

    /**
     * the column name for the usr_per_ID field
     */
    const COL_USR_PER_ID = 'user_usr.usr_per_ID';

    /**
     * the column name for the usr_Password field
     */
    const COL_USR_PASSWORD = 'user_usr.usr_Password';

    /**
     * the column name for the usr_NeedPasswordChange field
     */
    const COL_USR_NEEDPASSWORDCHANGE = 'user_usr.usr_NeedPasswordChange';

    /**
     * the column name for the usr_LastLogin field
     */
    const COL_USR_LASTLOGIN = 'user_usr.usr_LastLogin';

    /**
     * the column name for the usr_LoginCount field
     */
    const COL_USR_LOGINCOUNT = 'user_usr.usr_LoginCount';

    /**
     * the column name for the usr_FailedLogins field
     */
    const COL_USR_FAILEDLOGINS = 'user_usr.usr_FailedLogins';

    /**
     * the column name for the usr_AddRecords field
     */
    const COL_USR_ADDRECORDS = 'user_usr.usr_AddRecords';

    /**
     * the column name for the usr_EditRecords field
     */
    const COL_USR_EDITRECORDS = 'user_usr.usr_EditRecords';

    /**
     * the column name for the usr_DeleteRecords field
     */
    const COL_USR_DELETERECORDS = 'user_usr.usr_DeleteRecords';

    /**
     * the column name for the usr_MenuOptions field
     */
    const COL_USR_MENUOPTIONS = 'user_usr.usr_MenuOptions';

    /**
     * the column name for the usr_ManageGroups field
     */
    const COL_USR_MANAGEGROUPS = 'user_usr.usr_ManageGroups';

    /**
     * the column name for the usr_Finance field
     */
    const COL_USR_FINANCE = 'user_usr.usr_Finance';

    /**
     * the column name for the usr_Communication field
     */
    const COL_USR_COMMUNICATION = 'user_usr.usr_Communication';

    /**
     * the column name for the usr_Notes field
     */
    const COL_USR_NOTES = 'user_usr.usr_Notes';

    /**
     * the column name for the usr_Admin field
     */
    const COL_USR_ADMIN = 'user_usr.usr_Admin';

    /**
     * the column name for the usr_Workspacewidth field
     */
    const COL_USR_WORKSPACEWIDTH = 'user_usr.usr_Workspacewidth';

    /**
     * the column name for the usr_BaseFontSize field
     */
    const COL_USR_BASEFONTSIZE = 'user_usr.usr_BaseFontSize';

    /**
     * the column name for the usr_SearchLimit field
     */
    const COL_USR_SEARCHLIMIT = 'user_usr.usr_SearchLimit';

    /**
     * the column name for the usr_Style field
     */
    const COL_USR_STYLE = 'user_usr.usr_Style';

    /**
     * the column name for the usr_showPledges field
     */
    const COL_USR_SHOWPLEDGES = 'user_usr.usr_showPledges';

    /**
     * the column name for the usr_showPayments field
     */
    const COL_USR_SHOWPAYMENTS = 'user_usr.usr_showPayments';

    /**
     * the column name for the usr_showSince field
     */
    const COL_USR_SHOWSINCE = 'user_usr.usr_showSince';

    /**
     * the column name for the usr_defaultFY field
     */
    const COL_USR_DEFAULTFY = 'user_usr.usr_defaultFY';

    /**
     * the column name for the usr_currentDeposit field
     */
    const COL_USR_CURRENTDEPOSIT = 'user_usr.usr_currentDeposit';

    /**
     * the column name for the usr_UserName field
     */
    const COL_USR_USERNAME = 'user_usr.usr_UserName';

    /**
     * the column name for the usr_EditSelf field
     */
    const COL_USR_EDITSELF = 'user_usr.usr_EditSelf';

    /**
     * the column name for the usr_CalStart field
     */
    const COL_USR_CALSTART = 'user_usr.usr_CalStart';

    /**
     * the column name for the usr_CalEnd field
     */
    const COL_USR_CALEND = 'user_usr.usr_CalEnd';

    /**
     * the column name for the usr_CalNoSchool1 field
     */
    const COL_USR_CALNOSCHOOL1 = 'user_usr.usr_CalNoSchool1';

    /**
     * the column name for the usr_CalNoSchool2 field
     */
    const COL_USR_CALNOSCHOOL2 = 'user_usr.usr_CalNoSchool2';

    /**
     * the column name for the usr_CalNoSchool3 field
     */
    const COL_USR_CALNOSCHOOL3 = 'user_usr.usr_CalNoSchool3';

    /**
     * the column name for the usr_CalNoSchool4 field
     */
    const COL_USR_CALNOSCHOOL4 = 'user_usr.usr_CalNoSchool4';

    /**
     * the column name for the usr_CalNoSchool5 field
     */
    const COL_USR_CALNOSCHOOL5 = 'user_usr.usr_CalNoSchool5';

    /**
     * the column name for the usr_CalNoSchool6 field
     */
    const COL_USR_CALNOSCHOOL6 = 'user_usr.usr_CalNoSchool6';

    /**
     * the column name for the usr_CalNoSchool7 field
     */
    const COL_USR_CALNOSCHOOL7 = 'user_usr.usr_CalNoSchool7';

    /**
     * the column name for the usr_CalNoSchool8 field
     */
    const COL_USR_CALNOSCHOOL8 = 'user_usr.usr_CalNoSchool8';

    /**
     * the column name for the usr_SearchFamily field
     */
    const COL_USR_SEARCHFAMILY = 'user_usr.usr_SearchFamily';

    /**
     * the column name for the usr_Canvasser field
     */
    const COL_USR_CANVASSER = 'user_usr.usr_Canvasser';

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
        self::TYPE_PHPNAME       => array('PersonId', 'Password', 'NeedPasswordChange', 'LastLogin', 'LoginCount', 'FailedLogins', 'AddRecords', 'EditRecords', 'DeleteRecords', 'MenuOptions', 'ManageGroups', 'Finance', 'Communication', 'Notes', 'Admin', 'WorkspaceWidth', 'BaseFontsize', 'SearchLimit', 'Style', 'ShowPledges', 'ShowPayments', 'ShowSince', 'DefaultFY', 'CurrentDeposit', 'UserName', 'EditSelf', 'CalStart', 'CalEnd', 'CalNoSchool1', 'CalNoSchool2', 'CalNoSchool3', 'CalNoSchool4', 'CalNoSchool5', 'CalNoSchool6', 'CalNoSchool7', 'CalNoSchool8', 'Searchfamily', 'Canvasser', ),
        self::TYPE_CAMELNAME     => array('personId', 'password', 'needPasswordChange', 'lastLogin', 'loginCount', 'failedLogins', 'addRecords', 'editRecords', 'deleteRecords', 'menuOptions', 'manageGroups', 'finance', 'communication', 'notes', 'admin', 'workspaceWidth', 'baseFontsize', 'searchLimit', 'style', 'showPledges', 'showPayments', 'showSince', 'defaultFY', 'currentDeposit', 'userName', 'editSelf', 'calStart', 'calEnd', 'calNoSchool1', 'calNoSchool2', 'calNoSchool3', 'calNoSchool4', 'calNoSchool5', 'calNoSchool6', 'calNoSchool7', 'calNoSchool8', 'searchfamily', 'canvasser', ),
        self::TYPE_COLNAME       => array(UserTableMap::COL_USR_PER_ID, UserTableMap::COL_USR_PASSWORD, UserTableMap::COL_USR_NEEDPASSWORDCHANGE, UserTableMap::COL_USR_LASTLOGIN, UserTableMap::COL_USR_LOGINCOUNT, UserTableMap::COL_USR_FAILEDLOGINS, UserTableMap::COL_USR_ADDRECORDS, UserTableMap::COL_USR_EDITRECORDS, UserTableMap::COL_USR_DELETERECORDS, UserTableMap::COL_USR_MENUOPTIONS, UserTableMap::COL_USR_MANAGEGROUPS, UserTableMap::COL_USR_FINANCE, UserTableMap::COL_USR_COMMUNICATION, UserTableMap::COL_USR_NOTES, UserTableMap::COL_USR_ADMIN, UserTableMap::COL_USR_WORKSPACEWIDTH, UserTableMap::COL_USR_BASEFONTSIZE, UserTableMap::COL_USR_SEARCHLIMIT, UserTableMap::COL_USR_STYLE, UserTableMap::COL_USR_SHOWPLEDGES, UserTableMap::COL_USR_SHOWPAYMENTS, UserTableMap::COL_USR_SHOWSINCE, UserTableMap::COL_USR_DEFAULTFY, UserTableMap::COL_USR_CURRENTDEPOSIT, UserTableMap::COL_USR_USERNAME, UserTableMap::COL_USR_EDITSELF, UserTableMap::COL_USR_CALSTART, UserTableMap::COL_USR_CALEND, UserTableMap::COL_USR_CALNOSCHOOL1, UserTableMap::COL_USR_CALNOSCHOOL2, UserTableMap::COL_USR_CALNOSCHOOL3, UserTableMap::COL_USR_CALNOSCHOOL4, UserTableMap::COL_USR_CALNOSCHOOL5, UserTableMap::COL_USR_CALNOSCHOOL6, UserTableMap::COL_USR_CALNOSCHOOL7, UserTableMap::COL_USR_CALNOSCHOOL8, UserTableMap::COL_USR_SEARCHFAMILY, UserTableMap::COL_USR_CANVASSER, ),
        self::TYPE_FIELDNAME     => array('usr_per_ID', 'usr_Password', 'usr_NeedPasswordChange', 'usr_LastLogin', 'usr_LoginCount', 'usr_FailedLogins', 'usr_AddRecords', 'usr_EditRecords', 'usr_DeleteRecords', 'usr_MenuOptions', 'usr_ManageGroups', 'usr_Finance', 'usr_Communication', 'usr_Notes', 'usr_Admin', 'usr_Workspacewidth', 'usr_BaseFontSize', 'usr_SearchLimit', 'usr_Style', 'usr_showPledges', 'usr_showPayments', 'usr_showSince', 'usr_defaultFY', 'usr_currentDeposit', 'usr_UserName', 'usr_EditSelf', 'usr_CalStart', 'usr_CalEnd', 'usr_CalNoSchool1', 'usr_CalNoSchool2', 'usr_CalNoSchool3', 'usr_CalNoSchool4', 'usr_CalNoSchool5', 'usr_CalNoSchool6', 'usr_CalNoSchool7', 'usr_CalNoSchool8', 'usr_SearchFamily', 'usr_Canvasser', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('PersonId' => 0, 'Password' => 1, 'NeedPasswordChange' => 2, 'LastLogin' => 3, 'LoginCount' => 4, 'FailedLogins' => 5, 'AddRecords' => 6, 'EditRecords' => 7, 'DeleteRecords' => 8, 'MenuOptions' => 9, 'ManageGroups' => 10, 'Finance' => 11, 'Communication' => 12, 'Notes' => 13, 'Admin' => 14, 'WorkspaceWidth' => 15, 'BaseFontsize' => 16, 'SearchLimit' => 17, 'Style' => 18, 'ShowPledges' => 19, 'ShowPayments' => 20, 'ShowSince' => 21, 'DefaultFY' => 22, 'CurrentDeposit' => 23, 'UserName' => 24, 'EditSelf' => 25, 'CalStart' => 26, 'CalEnd' => 27, 'CalNoSchool1' => 28, 'CalNoSchool2' => 29, 'CalNoSchool3' => 30, 'CalNoSchool4' => 31, 'CalNoSchool5' => 32, 'CalNoSchool6' => 33, 'CalNoSchool7' => 34, 'CalNoSchool8' => 35, 'Searchfamily' => 36, 'Canvasser' => 37, ),
        self::TYPE_CAMELNAME     => array('personId' => 0, 'password' => 1, 'needPasswordChange' => 2, 'lastLogin' => 3, 'loginCount' => 4, 'failedLogins' => 5, 'addRecords' => 6, 'editRecords' => 7, 'deleteRecords' => 8, 'menuOptions' => 9, 'manageGroups' => 10, 'finance' => 11, 'communication' => 12, 'notes' => 13, 'admin' => 14, 'workspaceWidth' => 15, 'baseFontsize' => 16, 'searchLimit' => 17, 'style' => 18, 'showPledges' => 19, 'showPayments' => 20, 'showSince' => 21, 'defaultFY' => 22, 'currentDeposit' => 23, 'userName' => 24, 'editSelf' => 25, 'calStart' => 26, 'calEnd' => 27, 'calNoSchool1' => 28, 'calNoSchool2' => 29, 'calNoSchool3' => 30, 'calNoSchool4' => 31, 'calNoSchool5' => 32, 'calNoSchool6' => 33, 'calNoSchool7' => 34, 'calNoSchool8' => 35, 'searchfamily' => 36, 'canvasser' => 37, ),
        self::TYPE_COLNAME       => array(UserTableMap::COL_USR_PER_ID => 0, UserTableMap::COL_USR_PASSWORD => 1, UserTableMap::COL_USR_NEEDPASSWORDCHANGE => 2, UserTableMap::COL_USR_LASTLOGIN => 3, UserTableMap::COL_USR_LOGINCOUNT => 4, UserTableMap::COL_USR_FAILEDLOGINS => 5, UserTableMap::COL_USR_ADDRECORDS => 6, UserTableMap::COL_USR_EDITRECORDS => 7, UserTableMap::COL_USR_DELETERECORDS => 8, UserTableMap::COL_USR_MENUOPTIONS => 9, UserTableMap::COL_USR_MANAGEGROUPS => 10, UserTableMap::COL_USR_FINANCE => 11, UserTableMap::COL_USR_COMMUNICATION => 12, UserTableMap::COL_USR_NOTES => 13, UserTableMap::COL_USR_ADMIN => 14, UserTableMap::COL_USR_WORKSPACEWIDTH => 15, UserTableMap::COL_USR_BASEFONTSIZE => 16, UserTableMap::COL_USR_SEARCHLIMIT => 17, UserTableMap::COL_USR_STYLE => 18, UserTableMap::COL_USR_SHOWPLEDGES => 19, UserTableMap::COL_USR_SHOWPAYMENTS => 20, UserTableMap::COL_USR_SHOWSINCE => 21, UserTableMap::COL_USR_DEFAULTFY => 22, UserTableMap::COL_USR_CURRENTDEPOSIT => 23, UserTableMap::COL_USR_USERNAME => 24, UserTableMap::COL_USR_EDITSELF => 25, UserTableMap::COL_USR_CALSTART => 26, UserTableMap::COL_USR_CALEND => 27, UserTableMap::COL_USR_CALNOSCHOOL1 => 28, UserTableMap::COL_USR_CALNOSCHOOL2 => 29, UserTableMap::COL_USR_CALNOSCHOOL3 => 30, UserTableMap::COL_USR_CALNOSCHOOL4 => 31, UserTableMap::COL_USR_CALNOSCHOOL5 => 32, UserTableMap::COL_USR_CALNOSCHOOL6 => 33, UserTableMap::COL_USR_CALNOSCHOOL7 => 34, UserTableMap::COL_USR_CALNOSCHOOL8 => 35, UserTableMap::COL_USR_SEARCHFAMILY => 36, UserTableMap::COL_USR_CANVASSER => 37, ),
        self::TYPE_FIELDNAME     => array('usr_per_ID' => 0, 'usr_Password' => 1, 'usr_NeedPasswordChange' => 2, 'usr_LastLogin' => 3, 'usr_LoginCount' => 4, 'usr_FailedLogins' => 5, 'usr_AddRecords' => 6, 'usr_EditRecords' => 7, 'usr_DeleteRecords' => 8, 'usr_MenuOptions' => 9, 'usr_ManageGroups' => 10, 'usr_Finance' => 11, 'usr_Communication' => 12, 'usr_Notes' => 13, 'usr_Admin' => 14, 'usr_Workspacewidth' => 15, 'usr_BaseFontSize' => 16, 'usr_SearchLimit' => 17, 'usr_Style' => 18, 'usr_showPledges' => 19, 'usr_showPayments' => 20, 'usr_showSince' => 21, 'usr_defaultFY' => 22, 'usr_currentDeposit' => 23, 'usr_UserName' => 24, 'usr_EditSelf' => 25, 'usr_CalStart' => 26, 'usr_CalEnd' => 27, 'usr_CalNoSchool1' => 28, 'usr_CalNoSchool2' => 29, 'usr_CalNoSchool3' => 30, 'usr_CalNoSchool4' => 31, 'usr_CalNoSchool5' => 32, 'usr_CalNoSchool6' => 33, 'usr_CalNoSchool7' => 34, 'usr_CalNoSchool8' => 35, 'usr_SearchFamily' => 36, 'usr_Canvasser' => 37, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, )
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
        $this->setName('user_usr');
        $this->setPhpName('User');
        $this->setIdentifierQuoting(false);
        $this->setClassName('\\ChurchCRM\\User');
        $this->setPackage('ChurchCRM');
        $this->setUseIdGenerator(false);
        // columns
        $this->addPrimaryKey('usr_per_ID', 'PersonId', 'SMALLINT', true, 9, 0);
        $this->addColumn('usr_Password', 'Password', 'VARCHAR', true, 500, '');
        $this->addColumn('usr_NeedPasswordChange', 'NeedPasswordChange', 'TINYINT', true, 3, 1);
        $this->addColumn('usr_LastLogin', 'LastLogin', 'TIMESTAMP', true, null, '0000-00-00 00:00:00');
        $this->addColumn('usr_LoginCount', 'LoginCount', 'SMALLINT', true, 5, 0);
        $this->addColumn('usr_FailedLogins', 'FailedLogins', 'TINYINT', true, 3, 0);
        $this->addColumn('usr_AddRecords', 'AddRecords', 'TINYINT', true, 3, 0);
        $this->addColumn('usr_EditRecords', 'EditRecords', 'TINYINT', true, 3, 0);
        $this->addColumn('usr_DeleteRecords', 'DeleteRecords', 'TINYINT', true, 3, 0);
        $this->addColumn('usr_MenuOptions', 'MenuOptions', 'TINYINT', true, 3, 0);
        $this->addColumn('usr_ManageGroups', 'ManageGroups', 'TINYINT', true, 3, 0);
        $this->addColumn('usr_Finance', 'Finance', 'TINYINT', true, 3, 0);
        $this->addColumn('usr_Communication', 'Communication', 'TINYINT', true, 3, 0);
        $this->addColumn('usr_Notes', 'Notes', 'TINYINT', true, 3, 0);
        $this->addColumn('usr_Admin', 'Admin', 'TINYINT', true, 3, 0);
        $this->addColumn('usr_Workspacewidth', 'WorkspaceWidth', 'SMALLINT', false, null, null);
        $this->addColumn('usr_BaseFontSize', 'BaseFontsize', 'TINYINT', false, null, null);
        $this->addColumn('usr_SearchLimit', 'SearchLimit', 'TINYINT', false, null, 10);
        $this->addColumn('usr_Style', 'Style', 'VARCHAR', false, 50, 'Style.css');
        $this->addColumn('usr_showPledges', 'ShowPledges', 'BOOLEAN', true, 1, false);
        $this->addColumn('usr_showPayments', 'ShowPayments', 'BOOLEAN', true, 1, false);
        $this->addColumn('usr_showSince', 'ShowSince', 'DATE', true, null, '0000-00-00');
        $this->addColumn('usr_defaultFY', 'DefaultFY', 'SMALLINT', true, 9, 10);
        $this->addColumn('usr_currentDeposit', 'CurrentDeposit', 'SMALLINT', true, 9, 0);
        $this->addColumn('usr_UserName', 'UserName', 'VARCHAR', false, 32, null);
        $this->addColumn('usr_EditSelf', 'EditSelf', 'TINYINT', true, 3, 0);
        $this->addColumn('usr_CalStart', 'CalStart', 'DATE', false, null, null);
        $this->addColumn('usr_CalEnd', 'CalEnd', 'DATE', false, null, null);
        $this->addColumn('usr_CalNoSchool1', 'CalNoSchool1', 'DATE', false, null, null);
        $this->addColumn('usr_CalNoSchool2', 'CalNoSchool2', 'DATE', false, null, null);
        $this->addColumn('usr_CalNoSchool3', 'CalNoSchool3', 'DATE', false, null, null);
        $this->addColumn('usr_CalNoSchool4', 'CalNoSchool4', 'DATE', false, null, null);
        $this->addColumn('usr_CalNoSchool5', 'CalNoSchool5', 'DATE', false, null, null);
        $this->addColumn('usr_CalNoSchool6', 'CalNoSchool6', 'DATE', false, null, null);
        $this->addColumn('usr_CalNoSchool7', 'CalNoSchool7', 'DATE', false, null, null);
        $this->addColumn('usr_CalNoSchool8', 'CalNoSchool8', 'DATE', false, null, null);
        $this->addColumn('usr_SearchFamily', 'Searchfamily', 'TINYINT', false, 3, null);
        $this->addColumn('usr_Canvasser', 'Canvasser', 'TINYINT', true, 3, 0);
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('UserConfig', '\\ChurchCRM\\UserConfig', RelationMap::ONE_TO_MANY, array (
  0 =>
  array (
    0 => ':ucfg_per_id',
    1 => ':usr_per_ID',
  ),
), null, null, 'UserConfigs', false);
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
        if ($row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('PersonId', TableMap::TYPE_PHPNAME, $indexType)] === null) {
            return null;
        }

        return null === $row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('PersonId', TableMap::TYPE_PHPNAME, $indexType)] || is_scalar($row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('PersonId', TableMap::TYPE_PHPNAME, $indexType)]) || is_callable([$row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('PersonId', TableMap::TYPE_PHPNAME, $indexType)], '__toString']) ? (string) $row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('PersonId', TableMap::TYPE_PHPNAME, $indexType)] : $row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('PersonId', TableMap::TYPE_PHPNAME, $indexType)];
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
                : self::translateFieldName('PersonId', TableMap::TYPE_PHPNAME, $indexType)
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
        return $withPrefix ? UserTableMap::CLASS_DEFAULT : UserTableMap::OM_CLASS;
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
     * @return array           (User object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = UserTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = UserTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + UserTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = UserTableMap::OM_CLASS;
            /** @var User $obj */
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            UserTableMap::addInstanceToPool($obj, $key);
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
            $key = UserTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = UserTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                /** @var User $obj */
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                UserTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(UserTableMap::COL_USR_PER_ID);
            $criteria->addSelectColumn(UserTableMap::COL_USR_PASSWORD);
            $criteria->addSelectColumn(UserTableMap::COL_USR_NEEDPASSWORDCHANGE);
            $criteria->addSelectColumn(UserTableMap::COL_USR_LASTLOGIN);
            $criteria->addSelectColumn(UserTableMap::COL_USR_LOGINCOUNT);
            $criteria->addSelectColumn(UserTableMap::COL_USR_FAILEDLOGINS);
            $criteria->addSelectColumn(UserTableMap::COL_USR_ADDRECORDS);
            $criteria->addSelectColumn(UserTableMap::COL_USR_EDITRECORDS);
            $criteria->addSelectColumn(UserTableMap::COL_USR_DELETERECORDS);
            $criteria->addSelectColumn(UserTableMap::COL_USR_MENUOPTIONS);
            $criteria->addSelectColumn(UserTableMap::COL_USR_MANAGEGROUPS);
            $criteria->addSelectColumn(UserTableMap::COL_USR_FINANCE);
            $criteria->addSelectColumn(UserTableMap::COL_USR_COMMUNICATION);
            $criteria->addSelectColumn(UserTableMap::COL_USR_NOTES);
            $criteria->addSelectColumn(UserTableMap::COL_USR_ADMIN);
            $criteria->addSelectColumn(UserTableMap::COL_USR_WORKSPACEWIDTH);
            $criteria->addSelectColumn(UserTableMap::COL_USR_BASEFONTSIZE);
            $criteria->addSelectColumn(UserTableMap::COL_USR_SEARCHLIMIT);
            $criteria->addSelectColumn(UserTableMap::COL_USR_STYLE);
            $criteria->addSelectColumn(UserTableMap::COL_USR_SHOWPLEDGES);
            $criteria->addSelectColumn(UserTableMap::COL_USR_SHOWPAYMENTS);
            $criteria->addSelectColumn(UserTableMap::COL_USR_SHOWSINCE);
            $criteria->addSelectColumn(UserTableMap::COL_USR_DEFAULTFY);
            $criteria->addSelectColumn(UserTableMap::COL_USR_CURRENTDEPOSIT);
            $criteria->addSelectColumn(UserTableMap::COL_USR_USERNAME);
            $criteria->addSelectColumn(UserTableMap::COL_USR_EDITSELF);
            $criteria->addSelectColumn(UserTableMap::COL_USR_CALSTART);
            $criteria->addSelectColumn(UserTableMap::COL_USR_CALEND);
            $criteria->addSelectColumn(UserTableMap::COL_USR_CALNOSCHOOL1);
            $criteria->addSelectColumn(UserTableMap::COL_USR_CALNOSCHOOL2);
            $criteria->addSelectColumn(UserTableMap::COL_USR_CALNOSCHOOL3);
            $criteria->addSelectColumn(UserTableMap::COL_USR_CALNOSCHOOL4);
            $criteria->addSelectColumn(UserTableMap::COL_USR_CALNOSCHOOL5);
            $criteria->addSelectColumn(UserTableMap::COL_USR_CALNOSCHOOL6);
            $criteria->addSelectColumn(UserTableMap::COL_USR_CALNOSCHOOL7);
            $criteria->addSelectColumn(UserTableMap::COL_USR_CALNOSCHOOL8);
            $criteria->addSelectColumn(UserTableMap::COL_USR_SEARCHFAMILY);
            $criteria->addSelectColumn(UserTableMap::COL_USR_CANVASSER);
        } else {
            $criteria->addSelectColumn($alias . '.usr_per_ID');
            $criteria->addSelectColumn($alias . '.usr_Password');
            $criteria->addSelectColumn($alias . '.usr_NeedPasswordChange');
            $criteria->addSelectColumn($alias . '.usr_LastLogin');
            $criteria->addSelectColumn($alias . '.usr_LoginCount');
            $criteria->addSelectColumn($alias . '.usr_FailedLogins');
            $criteria->addSelectColumn($alias . '.usr_AddRecords');
            $criteria->addSelectColumn($alias . '.usr_EditRecords');
            $criteria->addSelectColumn($alias . '.usr_DeleteRecords');
            $criteria->addSelectColumn($alias . '.usr_MenuOptions');
            $criteria->addSelectColumn($alias . '.usr_ManageGroups');
            $criteria->addSelectColumn($alias . '.usr_Finance');
            $criteria->addSelectColumn($alias . '.usr_Communication');
            $criteria->addSelectColumn($alias . '.usr_Notes');
            $criteria->addSelectColumn($alias . '.usr_Admin');
            $criteria->addSelectColumn($alias . '.usr_Workspacewidth');
            $criteria->addSelectColumn($alias . '.usr_BaseFontSize');
            $criteria->addSelectColumn($alias . '.usr_SearchLimit');
            $criteria->addSelectColumn($alias . '.usr_Style');
            $criteria->addSelectColumn($alias . '.usr_showPledges');
            $criteria->addSelectColumn($alias . '.usr_showPayments');
            $criteria->addSelectColumn($alias . '.usr_showSince');
            $criteria->addSelectColumn($alias . '.usr_defaultFY');
            $criteria->addSelectColumn($alias . '.usr_currentDeposit');
            $criteria->addSelectColumn($alias . '.usr_UserName');
            $criteria->addSelectColumn($alias . '.usr_EditSelf');
            $criteria->addSelectColumn($alias . '.usr_CalStart');
            $criteria->addSelectColumn($alias . '.usr_CalEnd');
            $criteria->addSelectColumn($alias . '.usr_CalNoSchool1');
            $criteria->addSelectColumn($alias . '.usr_CalNoSchool2');
            $criteria->addSelectColumn($alias . '.usr_CalNoSchool3');
            $criteria->addSelectColumn($alias . '.usr_CalNoSchool4');
            $criteria->addSelectColumn($alias . '.usr_CalNoSchool5');
            $criteria->addSelectColumn($alias . '.usr_CalNoSchool6');
            $criteria->addSelectColumn($alias . '.usr_CalNoSchool7');
            $criteria->addSelectColumn($alias . '.usr_CalNoSchool8');
            $criteria->addSelectColumn($alias . '.usr_SearchFamily');
            $criteria->addSelectColumn($alias . '.usr_Canvasser');
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
        return Propel::getServiceContainer()->getDatabaseMap(UserTableMap::DATABASE_NAME)->getTable(UserTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
        $dbMap = Propel::getServiceContainer()->getDatabaseMap(UserTableMap::DATABASE_NAME);
        if (!$dbMap->hasTable(UserTableMap::TABLE_NAME)) {
            $dbMap->addTableObject(new UserTableMap());
        }
    }

    /**
     * Performs a DELETE on the database, given a User or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or User object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(UserTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \ChurchCRM\User) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(UserTableMap::DATABASE_NAME);
            $criteria->add(UserTableMap::COL_USR_PER_ID, (array) $values, Criteria::IN);
        }

        $query = UserQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) {
            UserTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) {
                UserTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the user_usr table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return UserQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a User or Criteria object.
     *
     * @param mixed               $criteria Criteria or User object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(UserTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from User object
        }


        // Set the correct dbName
        $query = UserQuery::create()->mergeWith($criteria);

        // use transaction because $criteria could contain info
        // for more than one table (I guess, conceivably)
        return $con->transaction(function () use ($con, $query) {
            return $query->doInsert($con);
        });
    }

} // UserTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
UserTableMap::buildTableMap();
