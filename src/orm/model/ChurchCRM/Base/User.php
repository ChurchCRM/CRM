<?php

namespace ChurchCRM\Base;

use \DateTime;
use \Exception;
use \PDO;
use ChurchCRM\User as ChildUser;
use ChurchCRM\UserConfig as ChildUserConfig;
use ChurchCRM\UserConfigQuery as ChildUserConfigQuery;
use ChurchCRM\UserQuery as ChildUserQuery;
use ChurchCRM\Map\UserConfigTableMap;
use ChurchCRM\Map\UserTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Collection\Collection;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\BadMethodCallException;
use Propel\Runtime\Exception\LogicException;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Parser\AbstractParser;
use Propel\Runtime\Util\PropelDateTime;

/**
 * Base class that represents a row from the 'user_usr' table.
 *
 *
 *
 * @package    propel.generator.ChurchCRM.Base
 */
abstract class User implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\ChurchCRM\\Map\\UserTableMap';


    /**
     * attribute to determine if this object has previously been saved.
     * @var boolean
     */
    protected $new = true;

    /**
     * attribute to determine whether this object has been deleted.
     * @var boolean
     */
    protected $deleted = false;

    /**
     * The columns that have been modified in current object.
     * Tracking modified columns allows us to only update modified columns.
     * @var array
     */
    protected $modifiedColumns = array();

    /**
     * The (virtual) columns that are added at runtime
     * The formatters can add supplementary columns based on a resultset
     * @var array
     */
    protected $virtualColumns = array();

    /**
     * The value for the usr_per_id field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $usr_per_id;

    /**
     * The value for the usr_password field.
     *
     * Note: this column has a database default value of: ''
     * @var        string
     */
    protected $usr_password;

    /**
     * The value for the usr_needpasswordchange field.
     *
     * Note: this column has a database default value of: 1
     * @var        int
     */
    protected $usr_needpasswordchange;

    /**
     * The value for the usr_lastlogin field.
     *
     * Note: this column has a database default value of: NULL
     * @var        DateTime
     */
    protected $usr_lastlogin;

    /**
     * The value for the usr_logincount field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $usr_logincount;

    /**
     * The value for the usr_failedlogins field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $usr_failedlogins;

    /**
     * The value for the usr_addrecords field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $usr_addrecords;

    /**
     * The value for the usr_editrecords field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $usr_editrecords;

    /**
     * The value for the usr_deleterecords field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $usr_deleterecords;

    /**
     * The value for the usr_menuoptions field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $usr_menuoptions;

    /**
     * The value for the usr_managegroups field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $usr_managegroups;

    /**
     * The value for the usr_finance field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $usr_finance;

    /**
     * The value for the usr_communication field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $usr_communication;

    /**
     * The value for the usr_notes field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $usr_notes;

    /**
     * The value for the usr_admin field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $usr_admin;

    /**
     * The value for the usr_workspacewidth field.
     *
     * @var        int
     */
    protected $usr_workspacewidth;

    /**
     * The value for the usr_basefontsize field.
     *
     * @var        int
     */
    protected $usr_basefontsize;

    /**
     * The value for the usr_searchlimit field.
     *
     * Note: this column has a database default value of: 10
     * @var        int
     */
    protected $usr_searchlimit;

    /**
     * The value for the usr_style field.
     *
     * Note: this column has a database default value of: 'Style.css'
     * @var        string
     */
    protected $usr_style;

    /**
     * The value for the usr_showpledges field.
     *
     * Note: this column has a database default value of: false
     * @var        boolean
     */
    protected $usr_showpledges;

    /**
     * The value for the usr_showpayments field.
     *
     * Note: this column has a database default value of: false
     * @var        boolean
     */
    protected $usr_showpayments;

    /**
     * The value for the usr_showsince field.
     *
     * Note: this column has a database default value of: NULL
     * @var        DateTime
     */
    protected $usr_showsince;

    /**
     * The value for the usr_defaultfy field.
     *
     * Note: this column has a database default value of: 10
     * @var        int
     */
    protected $usr_defaultfy;

    /**
     * The value for the usr_currentdeposit field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $usr_currentdeposit;

    /**
     * The value for the usr_username field.
     *
     * @var        string
     */
    protected $usr_username;

    /**
     * The value for the usr_editself field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $usr_editself;

    /**
     * The value for the usr_calstart field.
     *
     * @var        DateTime
     */
    protected $usr_calstart;

    /**
     * The value for the usr_calend field.
     *
     * @var        DateTime
     */
    protected $usr_calend;

    /**
     * The value for the usr_calnoschool1 field.
     *
     * @var        DateTime
     */
    protected $usr_calnoschool1;

    /**
     * The value for the usr_calnoschool2 field.
     *
     * @var        DateTime
     */
    protected $usr_calnoschool2;

    /**
     * The value for the usr_calnoschool3 field.
     *
     * @var        DateTime
     */
    protected $usr_calnoschool3;

    /**
     * The value for the usr_calnoschool4 field.
     *
     * @var        DateTime
     */
    protected $usr_calnoschool4;

    /**
     * The value for the usr_calnoschool5 field.
     *
     * @var        DateTime
     */
    protected $usr_calnoschool5;

    /**
     * The value for the usr_calnoschool6 field.
     *
     * @var        DateTime
     */
    protected $usr_calnoschool6;

    /**
     * The value for the usr_calnoschool7 field.
     *
     * @var        DateTime
     */
    protected $usr_calnoschool7;

    /**
     * The value for the usr_calnoschool8 field.
     *
     * @var        DateTime
     */
    protected $usr_calnoschool8;

    /**
     * The value for the usr_searchfamily field.
     *
     * @var        int
     */
    protected $usr_searchfamily;

    /**
     * The value for the usr_canvasser field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $usr_canvasser;

    /**
     * @var        ObjectCollection|ChildUserConfig[] Collection to store aggregation of ChildUserConfig objects.
     */
    protected $collUserConfigs;
    protected $collUserConfigsPartial;

    /**
     * Flag to prevent endless save loop, if this object is referenced
     * by another object which falls in this transaction.
     *
     * @var boolean
     */
    protected $alreadyInSave = false;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection|ChildUserConfig[]
     */
    protected $userConfigsScheduledForDeletion = null;

    /**
     * Applies default values to this object.
     * This method should be called from the object's constructor (or
     * equivalent initialization method).
     * @see __construct()
     */
    public function applyDefaultValues()
    {
        $this->usr_per_id = 0;
        $this->usr_password = '';
        $this->usr_needpasswordchange = 1;
        $this->usr_lastlogin = PropelDateTime::newInstance(NULL, null, 'DateTime');
        $this->usr_logincount = 0;
        $this->usr_failedlogins = 0;
        $this->usr_addrecords = 0;
        $this->usr_editrecords = 0;
        $this->usr_deleterecords = 0;
        $this->usr_menuoptions = 0;
        $this->usr_managegroups = 0;
        $this->usr_finance = 0;
        $this->usr_communication = 0;
        $this->usr_notes = 0;
        $this->usr_admin = 0;
        $this->usr_searchlimit = 10;
        $this->usr_style = 'Style.css';
        $this->usr_showpledges = false;
        $this->usr_showpayments = false;
        $this->usr_showsince = PropelDateTime::newInstance(NULL, null, 'DateTime');
        $this->usr_defaultfy = 10;
        $this->usr_currentdeposit = 0;
        $this->usr_editself = 0;
        $this->usr_canvasser = 0;
    }

    /**
     * Initializes internal state of ChurchCRM\Base\User object.
     * @see applyDefaults()
     */
    public function __construct()
    {
        $this->applyDefaultValues();
    }

    /**
     * Returns whether the object has been modified.
     *
     * @return boolean True if the object has been modified.
     */
    public function isModified()
    {
        return !!$this->modifiedColumns;
    }

    /**
     * Has specified column been modified?
     *
     * @param  string  $col column fully qualified name (TableMap::TYPE_COLNAME), e.g. Book::AUTHOR_ID
     * @return boolean True if $col has been modified.
     */
    public function isColumnModified($col)
    {
        return $this->modifiedColumns && isset($this->modifiedColumns[$col]);
    }

    /**
     * Get the columns that have been modified in this object.
     * @return array A unique list of the modified column names for this object.
     */
    public function getModifiedColumns()
    {
        return $this->modifiedColumns ? array_keys($this->modifiedColumns) : [];
    }

    /**
     * Returns whether the object has ever been saved.  This will
     * be false, if the object was retrieved from storage or was created
     * and then saved.
     *
     * @return boolean true, if the object has never been persisted.
     */
    public function isNew()
    {
        return $this->new;
    }

    /**
     * Setter for the isNew attribute.  This method will be called
     * by Propel-generated children and objects.
     *
     * @param boolean $b the state of the object.
     */
    public function setNew($b)
    {
        $this->new = (boolean) $b;
    }

    /**
     * Whether this object has been deleted.
     * @return boolean The deleted state of this object.
     */
    public function isDeleted()
    {
        return $this->deleted;
    }

    /**
     * Specify whether this object has been deleted.
     * @param  boolean $b The deleted state of this object.
     * @return void
     */
    public function setDeleted($b)
    {
        $this->deleted = (boolean) $b;
    }

    /**
     * Sets the modified state for the object to be false.
     * @param  string $col If supplied, only the specified column is reset.
     * @return void
     */
    public function resetModified($col = null)
    {
        if (null !== $col) {
            if (isset($this->modifiedColumns[$col])) {
                unset($this->modifiedColumns[$col]);
            }
        } else {
            $this->modifiedColumns = array();
        }
    }

    /**
     * Compares this with another <code>User</code> instance.  If
     * <code>obj</code> is an instance of <code>User</code>, delegates to
     * <code>equals(User)</code>.  Otherwise, returns <code>false</code>.
     *
     * @param  mixed   $obj The object to compare to.
     * @return boolean Whether equal to the object specified.
     */
    public function equals($obj)
    {
        if (!$obj instanceof static) {
            return false;
        }

        if ($this === $obj) {
            return true;
        }

        if (null === $this->getPrimaryKey() || null === $obj->getPrimaryKey()) {
            return false;
        }

        return $this->getPrimaryKey() === $obj->getPrimaryKey();
    }

    /**
     * Get the associative array of the virtual columns in this object
     *
     * @return array
     */
    public function getVirtualColumns()
    {
        return $this->virtualColumns;
    }

    /**
     * Checks the existence of a virtual column in this object
     *
     * @param  string  $name The virtual column name
     * @return boolean
     */
    public function hasVirtualColumn($name)
    {
        return array_key_exists($name, $this->virtualColumns);
    }

    /**
     * Get the value of a virtual column in this object
     *
     * @param  string $name The virtual column name
     * @return mixed
     *
     * @throws PropelException
     */
    public function getVirtualColumn($name)
    {
        if (!$this->hasVirtualColumn($name)) {
            throw new PropelException(sprintf('Cannot get value of inexistent virtual column %s.', $name));
        }

        return $this->virtualColumns[$name];
    }

    /**
     * Set the value of a virtual column in this object
     *
     * @param string $name  The virtual column name
     * @param mixed  $value The value to give to the virtual column
     *
     * @return $this|User The current object, for fluid interface
     */
    public function setVirtualColumn($name, $value)
    {
        $this->virtualColumns[$name] = $value;

        return $this;
    }

    /**
     * Logs a message using Propel::log().
     *
     * @param  string  $msg
     * @param  int     $priority One of the Propel::LOG_* logging levels
     * @return boolean
     */
    protected function log($msg, $priority = Propel::LOG_INFO)
    {
        return Propel::log(get_class($this) . ': ' . $msg, $priority);
    }

    /**
     * Export the current object properties to a string, using a given parser format
     * <code>
     * $book = BookQuery::create()->findPk(9012);
     * echo $book->exportTo('JSON');
     *  => {"Id":9012,"Title":"Don Juan","ISBN":"0140422161","Price":12.99,"PublisherId":1234,"AuthorId":5678}');
     * </code>
     *
     * @param  mixed   $parser                 A AbstractParser instance, or a format name ('XML', 'YAML', 'JSON', 'CSV')
     * @param  boolean $includeLazyLoadColumns (optional) Whether to include lazy load(ed) columns. Defaults to TRUE.
     * @return string  The exported data
     */
    public function exportTo($parser, $includeLazyLoadColumns = true)
    {
        if (!$parser instanceof AbstractParser) {
            $parser = AbstractParser::getParser($parser);
        }

        return $parser->fromArray($this->toArray(TableMap::TYPE_PHPNAME, $includeLazyLoadColumns, array(), true));
    }

    /**
     * Clean up internal collections prior to serializing
     * Avoids recursive loops that turn into segmentation faults when serializing
     */
    public function __sleep()
    {
        $this->clearAllReferences();

        $cls = new \ReflectionClass($this);
        $propertyNames = [];
        $serializableProperties = array_diff($cls->getProperties(), $cls->getProperties(\ReflectionProperty::IS_STATIC));

        foreach($serializableProperties as $property) {
            $propertyNames[] = $property->getName();
        }

        return $propertyNames;
    }

    /**
     * Get the [usr_per_id] column value.
     *
     * @return int
     */
    public function getPersonId()
    {
        return $this->usr_per_id;
    }

    /**
     * Get the [usr_password] column value.
     *
     * @return string
     */
    public function getPassword()
    {
        return $this->usr_password;
    }

    /**
     * Get the [usr_needpasswordchange] column value.
     *
     * @return int
     */
    public function getNeedPasswordChange()
    {
        return $this->usr_needpasswordchange;
    }

    /**
     * Get the [optionally formatted] temporal [usr_lastlogin] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw DateTime object will be returned.
     *
     * @return string|DateTime Formatted date/time value as string or DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00 00:00:00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getLastLogin($format = NULL)
    {
        if ($format === null) {
            return $this->usr_lastlogin;
        } else {
            return $this->usr_lastlogin instanceof \DateTimeInterface ? $this->usr_lastlogin->format($format) : null;
        }
    }

    /**
     * Get the [usr_logincount] column value.
     *
     * @return int
     */
    public function getLoginCount()
    {
        return $this->usr_logincount;
    }

    /**
     * Get the [usr_failedlogins] column value.
     *
     * @return int
     */
    public function getFailedLogins()
    {
        return $this->usr_failedlogins;
    }

    /**
     * Get the [usr_addrecords] column value.
     *
     * @return int
     */
    public function getAddRecords()
    {
        return $this->usr_addrecords;
    }

    /**
     * Get the [usr_editrecords] column value.
     *
     * @return int
     */
    public function getEditRecords()
    {
        return $this->usr_editrecords;
    }

    /**
     * Get the [usr_deleterecords] column value.
     *
     * @return int
     */
    public function getDeleteRecords()
    {
        return $this->usr_deleterecords;
    }

    /**
     * Get the [usr_menuoptions] column value.
     *
     * @return int
     */
    public function getMenuOptions()
    {
        return $this->usr_menuoptions;
    }

    /**
     * Get the [usr_managegroups] column value.
     *
     * @return int
     */
    public function getManageGroups()
    {
        return $this->usr_managegroups;
    }

    /**
     * Get the [usr_finance] column value.
     *
     * @return int
     */
    public function getFinance()
    {
        return $this->usr_finance;
    }

    /**
     * Get the [usr_communication] column value.
     *
     * @return int
     */
    public function getCommunication()
    {
        return $this->usr_communication;
    }

    /**
     * Get the [usr_notes] column value.
     *
     * @return int
     */
    public function getNotes()
    {
        return $this->usr_notes;
    }

    /**
     * Get the [usr_admin] column value.
     *
     * @return int
     */
    public function getAdmin()
    {
        return $this->usr_admin;
    }

    /**
     * Get the [usr_workspacewidth] column value.
     *
     * @return int
     */
    public function getWorkspaceWidth()
    {
        return $this->usr_workspacewidth;
    }

    /**
     * Get the [usr_basefontsize] column value.
     *
     * @return int
     */
    public function getBaseFontsize()
    {
        return $this->usr_basefontsize;
    }

    /**
     * Get the [usr_searchlimit] column value.
     *
     * @return int
     */
    public function getSearchLimit()
    {
        return $this->usr_searchlimit;
    }

    /**
     * Get the [usr_style] column value.
     *
     * @return string
     */
    public function getStyle()
    {
        return $this->usr_style;
    }

    /**
     * Get the [usr_showpledges] column value.
     *
     * @return boolean
     */
    public function getShowPledges()
    {
        return $this->usr_showpledges;
    }

    /**
     * Get the [usr_showpledges] column value.
     *
     * @return boolean
     */
    public function isShowPledges()
    {
        return $this->getShowPledges();
    }

    /**
     * Get the [usr_showpayments] column value.
     *
     * @return boolean
     */
    public function getShowPayments()
    {
        return $this->usr_showpayments;
    }

    /**
     * Get the [usr_showpayments] column value.
     *
     * @return boolean
     */
    public function isShowPayments()
    {
        return $this->getShowPayments();
    }

    /**
     * Get the [optionally formatted] temporal [usr_showsince] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw DateTime object will be returned.
     *
     * @return string|DateTime Formatted date/time value as string or DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getShowSince($format = NULL)
    {
        if ($format === null) {
            return $this->usr_showsince;
        } else {
            return $this->usr_showsince instanceof \DateTimeInterface ? $this->usr_showsince->format($format) : null;
        }
    }

    /**
     * Get the [usr_defaultfy] column value.
     *
     * @return int
     */
    public function getDefaultFY()
    {
        return $this->usr_defaultfy;
    }

    /**
     * Get the [usr_currentdeposit] column value.
     *
     * @return int
     */
    public function getCurrentDeposit()
    {
        return $this->usr_currentdeposit;
    }

    /**
     * Get the [usr_username] column value.
     *
     * @return string
     */
    public function getUserName()
    {
        return $this->usr_username;
    }

    /**
     * Get the [usr_editself] column value.
     *
     * @return int
     */
    public function getEditSelf()
    {
        return $this->usr_editself;
    }

    /**
     * Get the [optionally formatted] temporal [usr_calstart] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw DateTime object will be returned.
     *
     * @return string|DateTime Formatted date/time value as string or DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getCalStart($format = NULL)
    {
        if ($format === null) {
            return $this->usr_calstart;
        } else {
            return $this->usr_calstart instanceof \DateTimeInterface ? $this->usr_calstart->format($format) : null;
        }
    }

    /**
     * Get the [optionally formatted] temporal [usr_calend] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw DateTime object will be returned.
     *
     * @return string|DateTime Formatted date/time value as string or DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getCalEnd($format = NULL)
    {
        if ($format === null) {
            return $this->usr_calend;
        } else {
            return $this->usr_calend instanceof \DateTimeInterface ? $this->usr_calend->format($format) : null;
        }
    }

    /**
     * Get the [optionally formatted] temporal [usr_calnoschool1] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw DateTime object will be returned.
     *
     * @return string|DateTime Formatted date/time value as string or DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getCalNoSchool1($format = NULL)
    {
        if ($format === null) {
            return $this->usr_calnoschool1;
        } else {
            return $this->usr_calnoschool1 instanceof \DateTimeInterface ? $this->usr_calnoschool1->format($format) : null;
        }
    }

    /**
     * Get the [optionally formatted] temporal [usr_calnoschool2] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw DateTime object will be returned.
     *
     * @return string|DateTime Formatted date/time value as string or DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getCalNoSchool2($format = NULL)
    {
        if ($format === null) {
            return $this->usr_calnoschool2;
        } else {
            return $this->usr_calnoschool2 instanceof \DateTimeInterface ? $this->usr_calnoschool2->format($format) : null;
        }
    }

    /**
     * Get the [optionally formatted] temporal [usr_calnoschool3] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw DateTime object will be returned.
     *
     * @return string|DateTime Formatted date/time value as string or DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getCalNoSchool3($format = NULL)
    {
        if ($format === null) {
            return $this->usr_calnoschool3;
        } else {
            return $this->usr_calnoschool3 instanceof \DateTimeInterface ? $this->usr_calnoschool3->format($format) : null;
        }
    }

    /**
     * Get the [optionally formatted] temporal [usr_calnoschool4] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw DateTime object will be returned.
     *
     * @return string|DateTime Formatted date/time value as string or DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getCalNoSchool4($format = NULL)
    {
        if ($format === null) {
            return $this->usr_calnoschool4;
        } else {
            return $this->usr_calnoschool4 instanceof \DateTimeInterface ? $this->usr_calnoschool4->format($format) : null;
        }
    }

    /**
     * Get the [optionally formatted] temporal [usr_calnoschool5] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw DateTime object will be returned.
     *
     * @return string|DateTime Formatted date/time value as string or DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getCalNoSchool5($format = NULL)
    {
        if ($format === null) {
            return $this->usr_calnoschool5;
        } else {
            return $this->usr_calnoschool5 instanceof \DateTimeInterface ? $this->usr_calnoschool5->format($format) : null;
        }
    }

    /**
     * Get the [optionally formatted] temporal [usr_calnoschool6] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw DateTime object will be returned.
     *
     * @return string|DateTime Formatted date/time value as string or DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getCalNoSchool6($format = NULL)
    {
        if ($format === null) {
            return $this->usr_calnoschool6;
        } else {
            return $this->usr_calnoschool6 instanceof \DateTimeInterface ? $this->usr_calnoschool6->format($format) : null;
        }
    }

    /**
     * Get the [optionally formatted] temporal [usr_calnoschool7] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw DateTime object will be returned.
     *
     * @return string|DateTime Formatted date/time value as string or DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getCalNoSchool7($format = NULL)
    {
        if ($format === null) {
            return $this->usr_calnoschool7;
        } else {
            return $this->usr_calnoschool7 instanceof \DateTimeInterface ? $this->usr_calnoschool7->format($format) : null;
        }
    }

    /**
     * Get the [optionally formatted] temporal [usr_calnoschool8] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw DateTime object will be returned.
     *
     * @return string|DateTime Formatted date/time value as string or DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getCalNoSchool8($format = NULL)
    {
        if ($format === null) {
            return $this->usr_calnoschool8;
        } else {
            return $this->usr_calnoschool8 instanceof \DateTimeInterface ? $this->usr_calnoschool8->format($format) : null;
        }
    }

    /**
     * Get the [usr_searchfamily] column value.
     *
     * @return int
     */
    public function getSearchfamily()
    {
        return $this->usr_searchfamily;
    }

    /**
     * Get the [usr_canvasser] column value.
     *
     * @return int
     */
    public function getCanvasser()
    {
        return $this->usr_canvasser;
    }

    /**
     * Set the value of [usr_per_id] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setPersonId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->usr_per_id !== $v) {
            $this->usr_per_id = $v;
            $this->modifiedColumns[UserTableMap::COL_USR_PER_ID] = true;
        }

        return $this;
    } // setPersonId()

    /**
     * Set the value of [usr_password] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setPassword($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->usr_password !== $v) {
            $this->usr_password = $v;
            $this->modifiedColumns[UserTableMap::COL_USR_PASSWORD] = true;
        }

        return $this;
    } // setPassword()

    /**
     * Set the value of [usr_needpasswordchange] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setNeedPasswordChange($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->usr_needpasswordchange !== $v) {
            $this->usr_needpasswordchange = $v;
            $this->modifiedColumns[UserTableMap::COL_USR_NEEDPASSWORDCHANGE] = true;
        }

        return $this;
    } // setNeedPasswordChange()

    /**
     * Sets the value of [usr_lastlogin] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTimeInterface value.
     *               Empty strings are treated as NULL.
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setLastLogin($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->usr_lastlogin !== null || $dt !== null) {
            if ( ($dt != $this->usr_lastlogin) // normalized values don't match
                || ($dt->format('Y-m-d H:i:s.u') === NULL) // or the entered value matches the default
                 ) {
                $this->usr_lastlogin = $dt === null ? null : clone $dt;
                $this->modifiedColumns[UserTableMap::COL_USR_LASTLOGIN] = true;
            }
        } // if either are not null

        return $this;
    } // setLastLogin()

    /**
     * Set the value of [usr_logincount] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setLoginCount($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->usr_logincount !== $v) {
            $this->usr_logincount = $v;
            $this->modifiedColumns[UserTableMap::COL_USR_LOGINCOUNT] = true;
        }

        return $this;
    } // setLoginCount()

    /**
     * Set the value of [usr_failedlogins] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setFailedLogins($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->usr_failedlogins !== $v) {
            $this->usr_failedlogins = $v;
            $this->modifiedColumns[UserTableMap::COL_USR_FAILEDLOGINS] = true;
        }

        return $this;
    } // setFailedLogins()

    /**
     * Set the value of [usr_addrecords] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setAddRecords($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->usr_addrecords !== $v) {
            $this->usr_addrecords = $v;
            $this->modifiedColumns[UserTableMap::COL_USR_ADDRECORDS] = true;
        }

        return $this;
    } // setAddRecords()

    /**
     * Set the value of [usr_editrecords] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setEditRecords($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->usr_editrecords !== $v) {
            $this->usr_editrecords = $v;
            $this->modifiedColumns[UserTableMap::COL_USR_EDITRECORDS] = true;
        }

        return $this;
    } // setEditRecords()

    /**
     * Set the value of [usr_deleterecords] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setDeleteRecords($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->usr_deleterecords !== $v) {
            $this->usr_deleterecords = $v;
            $this->modifiedColumns[UserTableMap::COL_USR_DELETERECORDS] = true;
        }

        return $this;
    } // setDeleteRecords()

    /**
     * Set the value of [usr_menuoptions] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setMenuOptions($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->usr_menuoptions !== $v) {
            $this->usr_menuoptions = $v;
            $this->modifiedColumns[UserTableMap::COL_USR_MENUOPTIONS] = true;
        }

        return $this;
    } // setMenuOptions()

    /**
     * Set the value of [usr_managegroups] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setManageGroups($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->usr_managegroups !== $v) {
            $this->usr_managegroups = $v;
            $this->modifiedColumns[UserTableMap::COL_USR_MANAGEGROUPS] = true;
        }

        return $this;
    } // setManageGroups()

    /**
     * Set the value of [usr_finance] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setFinance($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->usr_finance !== $v) {
            $this->usr_finance = $v;
            $this->modifiedColumns[UserTableMap::COL_USR_FINANCE] = true;
        }

        return $this;
    } // setFinance()

    /**
     * Set the value of [usr_communication] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setCommunication($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->usr_communication !== $v) {
            $this->usr_communication = $v;
            $this->modifiedColumns[UserTableMap::COL_USR_COMMUNICATION] = true;
        }

        return $this;
    } // setCommunication()

    /**
     * Set the value of [usr_notes] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setNotes($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->usr_notes !== $v) {
            $this->usr_notes = $v;
            $this->modifiedColumns[UserTableMap::COL_USR_NOTES] = true;
        }

        return $this;
    } // setNotes()

    /**
     * Set the value of [usr_admin] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setAdmin($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->usr_admin !== $v) {
            $this->usr_admin = $v;
            $this->modifiedColumns[UserTableMap::COL_USR_ADMIN] = true;
        }

        return $this;
    } // setAdmin()

    /**
     * Set the value of [usr_workspacewidth] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setWorkspaceWidth($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->usr_workspacewidth !== $v) {
            $this->usr_workspacewidth = $v;
            $this->modifiedColumns[UserTableMap::COL_USR_WORKSPACEWIDTH] = true;
        }

        return $this;
    } // setWorkspaceWidth()

    /**
     * Set the value of [usr_basefontsize] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setBaseFontsize($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->usr_basefontsize !== $v) {
            $this->usr_basefontsize = $v;
            $this->modifiedColumns[UserTableMap::COL_USR_BASEFONTSIZE] = true;
        }

        return $this;
    } // setBaseFontsize()

    /**
     * Set the value of [usr_searchlimit] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setSearchLimit($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->usr_searchlimit !== $v) {
            $this->usr_searchlimit = $v;
            $this->modifiedColumns[UserTableMap::COL_USR_SEARCHLIMIT] = true;
        }

        return $this;
    } // setSearchLimit()

    /**
     * Set the value of [usr_style] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setStyle($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->usr_style !== $v) {
            $this->usr_style = $v;
            $this->modifiedColumns[UserTableMap::COL_USR_STYLE] = true;
        }

        return $this;
    } // setStyle()

    /**
     * Sets the value of the [usr_showpledges] column.
     * Non-boolean arguments are converted using the following rules:
     *   * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *   * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     * Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     *
     * @param  boolean|integer|string $v The new value
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setShowPledges($v)
    {
        if ($v !== null) {
            if (is_string($v)) {
                $v = in_array(strtolower($v), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
            } else {
                $v = (boolean) $v;
            }
        }

        if ($this->usr_showpledges !== $v) {
            $this->usr_showpledges = $v;
            $this->modifiedColumns[UserTableMap::COL_USR_SHOWPLEDGES] = true;
        }

        return $this;
    } // setShowPledges()

    /**
     * Sets the value of the [usr_showpayments] column.
     * Non-boolean arguments are converted using the following rules:
     *   * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *   * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     * Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     *
     * @param  boolean|integer|string $v The new value
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setShowPayments($v)
    {
        if ($v !== null) {
            if (is_string($v)) {
                $v = in_array(strtolower($v), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
            } else {
                $v = (boolean) $v;
            }
        }

        if ($this->usr_showpayments !== $v) {
            $this->usr_showpayments = $v;
            $this->modifiedColumns[UserTableMap::COL_USR_SHOWPAYMENTS] = true;
        }

        return $this;
    } // setShowPayments()

    /**
     * Sets the value of [usr_showsince] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTimeInterface value.
     *               Empty strings are treated as NULL.
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setShowSince($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->usr_showsince !== null || $dt !== null) {
            if ( ($dt != $this->usr_showsince) // normalized values don't match
                || ($dt->format('Y-m-d') === NULL) // or the entered value matches the default
                 ) {
                $this->usr_showsince = $dt === null ? null : clone $dt;
                $this->modifiedColumns[UserTableMap::COL_USR_SHOWSINCE] = true;
            }
        } // if either are not null

        return $this;
    } // setShowSince()

    /**
     * Set the value of [usr_defaultfy] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setDefaultFY($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->usr_defaultfy !== $v) {
            $this->usr_defaultfy = $v;
            $this->modifiedColumns[UserTableMap::COL_USR_DEFAULTFY] = true;
        }

        return $this;
    } // setDefaultFY()

    /**
     * Set the value of [usr_currentdeposit] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setCurrentDeposit($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->usr_currentdeposit !== $v) {
            $this->usr_currentdeposit = $v;
            $this->modifiedColumns[UserTableMap::COL_USR_CURRENTDEPOSIT] = true;
        }

        return $this;
    } // setCurrentDeposit()

    /**
     * Set the value of [usr_username] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setUserName($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->usr_username !== $v) {
            $this->usr_username = $v;
            $this->modifiedColumns[UserTableMap::COL_USR_USERNAME] = true;
        }

        return $this;
    } // setUserName()

    /**
     * Set the value of [usr_editself] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setEditSelf($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->usr_editself !== $v) {
            $this->usr_editself = $v;
            $this->modifiedColumns[UserTableMap::COL_USR_EDITSELF] = true;
        }

        return $this;
    } // setEditSelf()

    /**
     * Sets the value of [usr_calstart] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTimeInterface value.
     *               Empty strings are treated as NULL.
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setCalStart($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->usr_calstart !== null || $dt !== null) {
            if ($this->usr_calstart === null || $dt === null || $dt->format("Y-m-d") !== $this->usr_calstart->format("Y-m-d")) {
                $this->usr_calstart = $dt === null ? null : clone $dt;
                $this->modifiedColumns[UserTableMap::COL_USR_CALSTART] = true;
            }
        } // if either are not null

        return $this;
    } // setCalStart()

    /**
     * Sets the value of [usr_calend] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTimeInterface value.
     *               Empty strings are treated as NULL.
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setCalEnd($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->usr_calend !== null || $dt !== null) {
            if ($this->usr_calend === null || $dt === null || $dt->format("Y-m-d") !== $this->usr_calend->format("Y-m-d")) {
                $this->usr_calend = $dt === null ? null : clone $dt;
                $this->modifiedColumns[UserTableMap::COL_USR_CALEND] = true;
            }
        } // if either are not null

        return $this;
    } // setCalEnd()

    /**
     * Sets the value of [usr_calnoschool1] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTimeInterface value.
     *               Empty strings are treated as NULL.
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setCalNoSchool1($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->usr_calnoschool1 !== null || $dt !== null) {
            if ($this->usr_calnoschool1 === null || $dt === null || $dt->format("Y-m-d") !== $this->usr_calnoschool1->format("Y-m-d")) {
                $this->usr_calnoschool1 = $dt === null ? null : clone $dt;
                $this->modifiedColumns[UserTableMap::COL_USR_CALNOSCHOOL1] = true;
            }
        } // if either are not null

        return $this;
    } // setCalNoSchool1()

    /**
     * Sets the value of [usr_calnoschool2] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTimeInterface value.
     *               Empty strings are treated as NULL.
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setCalNoSchool2($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->usr_calnoschool2 !== null || $dt !== null) {
            if ($this->usr_calnoschool2 === null || $dt === null || $dt->format("Y-m-d") !== $this->usr_calnoschool2->format("Y-m-d")) {
                $this->usr_calnoschool2 = $dt === null ? null : clone $dt;
                $this->modifiedColumns[UserTableMap::COL_USR_CALNOSCHOOL2] = true;
            }
        } // if either are not null

        return $this;
    } // setCalNoSchool2()

    /**
     * Sets the value of [usr_calnoschool3] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTimeInterface value.
     *               Empty strings are treated as NULL.
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setCalNoSchool3($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->usr_calnoschool3 !== null || $dt !== null) {
            if ($this->usr_calnoschool3 === null || $dt === null || $dt->format("Y-m-d") !== $this->usr_calnoschool3->format("Y-m-d")) {
                $this->usr_calnoschool3 = $dt === null ? null : clone $dt;
                $this->modifiedColumns[UserTableMap::COL_USR_CALNOSCHOOL3] = true;
            }
        } // if either are not null

        return $this;
    } // setCalNoSchool3()

    /**
     * Sets the value of [usr_calnoschool4] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTimeInterface value.
     *               Empty strings are treated as NULL.
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setCalNoSchool4($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->usr_calnoschool4 !== null || $dt !== null) {
            if ($this->usr_calnoschool4 === null || $dt === null || $dt->format("Y-m-d") !== $this->usr_calnoschool4->format("Y-m-d")) {
                $this->usr_calnoschool4 = $dt === null ? null : clone $dt;
                $this->modifiedColumns[UserTableMap::COL_USR_CALNOSCHOOL4] = true;
            }
        } // if either are not null

        return $this;
    } // setCalNoSchool4()

    /**
     * Sets the value of [usr_calnoschool5] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTimeInterface value.
     *               Empty strings are treated as NULL.
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setCalNoSchool5($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->usr_calnoschool5 !== null || $dt !== null) {
            if ($this->usr_calnoschool5 === null || $dt === null || $dt->format("Y-m-d") !== $this->usr_calnoschool5->format("Y-m-d")) {
                $this->usr_calnoschool5 = $dt === null ? null : clone $dt;
                $this->modifiedColumns[UserTableMap::COL_USR_CALNOSCHOOL5] = true;
            }
        } // if either are not null

        return $this;
    } // setCalNoSchool5()

    /**
     * Sets the value of [usr_calnoschool6] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTimeInterface value.
     *               Empty strings are treated as NULL.
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setCalNoSchool6($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->usr_calnoschool6 !== null || $dt !== null) {
            if ($this->usr_calnoschool6 === null || $dt === null || $dt->format("Y-m-d") !== $this->usr_calnoschool6->format("Y-m-d")) {
                $this->usr_calnoschool6 = $dt === null ? null : clone $dt;
                $this->modifiedColumns[UserTableMap::COL_USR_CALNOSCHOOL6] = true;
            }
        } // if either are not null

        return $this;
    } // setCalNoSchool6()

    /**
     * Sets the value of [usr_calnoschool7] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTimeInterface value.
     *               Empty strings are treated as NULL.
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setCalNoSchool7($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->usr_calnoschool7 !== null || $dt !== null) {
            if ($this->usr_calnoschool7 === null || $dt === null || $dt->format("Y-m-d") !== $this->usr_calnoschool7->format("Y-m-d")) {
                $this->usr_calnoschool7 = $dt === null ? null : clone $dt;
                $this->modifiedColumns[UserTableMap::COL_USR_CALNOSCHOOL7] = true;
            }
        } // if either are not null

        return $this;
    } // setCalNoSchool7()

    /**
     * Sets the value of [usr_calnoschool8] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTimeInterface value.
     *               Empty strings are treated as NULL.
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setCalNoSchool8($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->usr_calnoschool8 !== null || $dt !== null) {
            if ($this->usr_calnoschool8 === null || $dt === null || $dt->format("Y-m-d") !== $this->usr_calnoschool8->format("Y-m-d")) {
                $this->usr_calnoschool8 = $dt === null ? null : clone $dt;
                $this->modifiedColumns[UserTableMap::COL_USR_CALNOSCHOOL8] = true;
            }
        } // if either are not null

        return $this;
    } // setCalNoSchool8()

    /**
     * Set the value of [usr_searchfamily] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setSearchfamily($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->usr_searchfamily !== $v) {
            $this->usr_searchfamily = $v;
            $this->modifiedColumns[UserTableMap::COL_USR_SEARCHFAMILY] = true;
        }

        return $this;
    } // setSearchfamily()

    /**
     * Set the value of [usr_canvasser] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function setCanvasser($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->usr_canvasser !== $v) {
            $this->usr_canvasser = $v;
            $this->modifiedColumns[UserTableMap::COL_USR_CANVASSER] = true;
        }

        return $this;
    } // setCanvasser()

    /**
     * Indicates whether the columns in this object are only set to default values.
     *
     * This method can be used in conjunction with isModified() to indicate whether an object is both
     * modified _and_ has some values set which are non-default.
     *
     * @return boolean Whether the columns in this object are only been set with default values.
     */
    public function hasOnlyDefaultValues()
    {
            if ($this->usr_per_id !== 0) {
                return false;
            }

            if ($this->usr_password !== '') {
                return false;
            }

            if ($this->usr_needpasswordchange !== 1) {
                return false;
            }

            if ($this->usr_lastlogin && $this->usr_lastlogin->format('Y-m-d H:i:s.u') !== NULL) {
                return false;
            }

            if ($this->usr_logincount !== 0) {
                return false;
            }

            if ($this->usr_failedlogins !== 0) {
                return false;
            }

            if ($this->usr_addrecords !== 0) {
                return false;
            }

            if ($this->usr_editrecords !== 0) {
                return false;
            }

            if ($this->usr_deleterecords !== 0) {
                return false;
            }

            if ($this->usr_menuoptions !== 0) {
                return false;
            }

            if ($this->usr_managegroups !== 0) {
                return false;
            }

            if ($this->usr_finance !== 0) {
                return false;
            }

            if ($this->usr_communication !== 0) {
                return false;
            }

            if ($this->usr_notes !== 0) {
                return false;
            }

            if ($this->usr_admin !== 0) {
                return false;
            }

            if ($this->usr_searchlimit !== 10) {
                return false;
            }

            if ($this->usr_style !== 'Style.css') {
                return false;
            }

            if ($this->usr_showpledges !== false) {
                return false;
            }

            if ($this->usr_showpayments !== false) {
                return false;
            }

            if ($this->usr_showsince && $this->usr_showsince->format('Y-m-d') !== NULL) {
                return false;
            }

            if ($this->usr_defaultfy !== 10) {
                return false;
            }

            if ($this->usr_currentdeposit !== 0) {
                return false;
            }

            if ($this->usr_editself !== 0) {
                return false;
            }

            if ($this->usr_canvasser !== 0) {
                return false;
            }

        // otherwise, everything was equal, so return TRUE
        return true;
    } // hasOnlyDefaultValues()

    /**
     * Hydrates (populates) the object variables with values from the database resultset.
     *
     * An offset (0-based "start column") is specified so that objects can be hydrated
     * with a subset of the columns in the resultset rows.  This is needed, for example,
     * for results of JOIN queries where the resultset row includes columns from two or
     * more tables.
     *
     * @param array   $row       The row returned by DataFetcher->fetch().
     * @param int     $startcol  0-based offset column which indicates which restultset column to start with.
     * @param boolean $rehydrate Whether this object is being re-hydrated from the database.
     * @param string  $indexType The index type of $row. Mostly DataFetcher->getIndexType().
                                  One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME
     *                            TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *
     * @return int             next starting column
     * @throws PropelException - Any caught Exception will be rewrapped as a PropelException.
     */
    public function hydrate($row, $startcol = 0, $rehydrate = false, $indexType = TableMap::TYPE_NUM)
    {
        try {

            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : UserTableMap::translateFieldName('PersonId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->usr_per_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : UserTableMap::translateFieldName('Password', TableMap::TYPE_PHPNAME, $indexType)];
            $this->usr_password = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : UserTableMap::translateFieldName('NeedPasswordChange', TableMap::TYPE_PHPNAME, $indexType)];
            $this->usr_needpasswordchange = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : UserTableMap::translateFieldName('LastLogin', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->usr_lastlogin = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : UserTableMap::translateFieldName('LoginCount', TableMap::TYPE_PHPNAME, $indexType)];
            $this->usr_logincount = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : UserTableMap::translateFieldName('FailedLogins', TableMap::TYPE_PHPNAME, $indexType)];
            $this->usr_failedlogins = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : UserTableMap::translateFieldName('AddRecords', TableMap::TYPE_PHPNAME, $indexType)];
            $this->usr_addrecords = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : UserTableMap::translateFieldName('EditRecords', TableMap::TYPE_PHPNAME, $indexType)];
            $this->usr_editrecords = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : UserTableMap::translateFieldName('DeleteRecords', TableMap::TYPE_PHPNAME, $indexType)];
            $this->usr_deleterecords = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 9 + $startcol : UserTableMap::translateFieldName('MenuOptions', TableMap::TYPE_PHPNAME, $indexType)];
            $this->usr_menuoptions = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 10 + $startcol : UserTableMap::translateFieldName('ManageGroups', TableMap::TYPE_PHPNAME, $indexType)];
            $this->usr_managegroups = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 11 + $startcol : UserTableMap::translateFieldName('Finance', TableMap::TYPE_PHPNAME, $indexType)];
            $this->usr_finance = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 12 + $startcol : UserTableMap::translateFieldName('Communication', TableMap::TYPE_PHPNAME, $indexType)];
            $this->usr_communication = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 13 + $startcol : UserTableMap::translateFieldName('Notes', TableMap::TYPE_PHPNAME, $indexType)];
            $this->usr_notes = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 14 + $startcol : UserTableMap::translateFieldName('Admin', TableMap::TYPE_PHPNAME, $indexType)];
            $this->usr_admin = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 15 + $startcol : UserTableMap::translateFieldName('WorkspaceWidth', TableMap::TYPE_PHPNAME, $indexType)];
            $this->usr_workspacewidth = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 16 + $startcol : UserTableMap::translateFieldName('BaseFontsize', TableMap::TYPE_PHPNAME, $indexType)];
            $this->usr_basefontsize = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 17 + $startcol : UserTableMap::translateFieldName('SearchLimit', TableMap::TYPE_PHPNAME, $indexType)];
            $this->usr_searchlimit = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 18 + $startcol : UserTableMap::translateFieldName('Style', TableMap::TYPE_PHPNAME, $indexType)];
            $this->usr_style = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 19 + $startcol : UserTableMap::translateFieldName('ShowPledges', TableMap::TYPE_PHPNAME, $indexType)];
            $this->usr_showpledges = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 20 + $startcol : UserTableMap::translateFieldName('ShowPayments', TableMap::TYPE_PHPNAME, $indexType)];
            $this->usr_showpayments = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 21 + $startcol : UserTableMap::translateFieldName('ShowSince', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00') {
                $col = null;
            }
            $this->usr_showsince = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 22 + $startcol : UserTableMap::translateFieldName('DefaultFY', TableMap::TYPE_PHPNAME, $indexType)];
            $this->usr_defaultfy = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 23 + $startcol : UserTableMap::translateFieldName('CurrentDeposit', TableMap::TYPE_PHPNAME, $indexType)];
            $this->usr_currentdeposit = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 24 + $startcol : UserTableMap::translateFieldName('UserName', TableMap::TYPE_PHPNAME, $indexType)];
            $this->usr_username = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 25 + $startcol : UserTableMap::translateFieldName('EditSelf', TableMap::TYPE_PHPNAME, $indexType)];
            $this->usr_editself = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 26 + $startcol : UserTableMap::translateFieldName('CalStart', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00') {
                $col = null;
            }
            $this->usr_calstart = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 27 + $startcol : UserTableMap::translateFieldName('CalEnd', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00') {
                $col = null;
            }
            $this->usr_calend = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 28 + $startcol : UserTableMap::translateFieldName('CalNoSchool1', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00') {
                $col = null;
            }
            $this->usr_calnoschool1 = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 29 + $startcol : UserTableMap::translateFieldName('CalNoSchool2', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00') {
                $col = null;
            }
            $this->usr_calnoschool2 = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 30 + $startcol : UserTableMap::translateFieldName('CalNoSchool3', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00') {
                $col = null;
            }
            $this->usr_calnoschool3 = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 31 + $startcol : UserTableMap::translateFieldName('CalNoSchool4', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00') {
                $col = null;
            }
            $this->usr_calnoschool4 = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 32 + $startcol : UserTableMap::translateFieldName('CalNoSchool5', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00') {
                $col = null;
            }
            $this->usr_calnoschool5 = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 33 + $startcol : UserTableMap::translateFieldName('CalNoSchool6', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00') {
                $col = null;
            }
            $this->usr_calnoschool6 = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 34 + $startcol : UserTableMap::translateFieldName('CalNoSchool7', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00') {
                $col = null;
            }
            $this->usr_calnoschool7 = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 35 + $startcol : UserTableMap::translateFieldName('CalNoSchool8', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00') {
                $col = null;
            }
            $this->usr_calnoschool8 = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 36 + $startcol : UserTableMap::translateFieldName('Searchfamily', TableMap::TYPE_PHPNAME, $indexType)];
            $this->usr_searchfamily = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 37 + $startcol : UserTableMap::translateFieldName('Canvasser', TableMap::TYPE_PHPNAME, $indexType)];
            $this->usr_canvasser = (null !== $col) ? (int) $col : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 38; // 38 = UserTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException(sprintf('Error populating %s object', '\\ChurchCRM\\User'), 0, $e);
        }
    }

    /**
     * Checks and repairs the internal consistency of the object.
     *
     * This method is executed after an already-instantiated object is re-hydrated
     * from the database.  It exists to check any foreign keys to make sure that
     * the objects related to the current object are correct based on foreign key.
     *
     * You can override this method in the stub class, but you should always invoke
     * the base method from the overridden method (i.e. parent::ensureConsistency()),
     * in case your model changes.
     *
     * @throws PropelException
     */
    public function ensureConsistency()
    {
    } // ensureConsistency

    /**
     * Reloads this object from datastore based on primary key and (optionally) resets all associated objects.
     *
     * This will only work if the object has been saved and has a valid primary key set.
     *
     * @param      boolean $deep (optional) Whether to also de-associated any related objects.
     * @param      ConnectionInterface $con (optional) The ConnectionInterface connection to use.
     * @return void
     * @throws PropelException - if this object is deleted, unsaved or doesn't have pk match in db
     */
    public function reload($deep = false, ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("Cannot reload a deleted object.");
        }

        if ($this->isNew()) {
            throw new PropelException("Cannot reload an unsaved object.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(UserTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildUserQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->collUserConfigs = null;

        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see User::setDeleted()
     * @see User::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(UserTableMap::DATABASE_NAME);
        }

        $con->transaction(function () use ($con) {
            $deleteQuery = ChildUserQuery::create()
                ->filterByPrimaryKey($this->getPrimaryKey());
            $ret = $this->preDelete($con);
            if ($ret) {
                $deleteQuery->delete($con);
                $this->postDelete($con);
                $this->setDeleted(true);
            }
        });
    }

    /**
     * Persists this object to the database.
     *
     * If the object is new, it inserts it; otherwise an update is performed.
     * All modified related objects will also be persisted in the doSave()
     * method.  This method wraps all precipitate database operations in a
     * single transaction.
     *
     * @param      ConnectionInterface $con
     * @return int             The number of rows affected by this insert/update and any referring fk objects' save() operations.
     * @throws PropelException
     * @see doSave()
     */
    public function save(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("You cannot save an object that has been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(UserTableMap::DATABASE_NAME);
        }

        return $con->transaction(function () use ($con) {
            $ret = $this->preSave($con);
            $isInsert = $this->isNew();
            if ($isInsert) {
                $ret = $ret && $this->preInsert($con);
            } else {
                $ret = $ret && $this->preUpdate($con);
            }
            if ($ret) {
                $affectedRows = $this->doSave($con);
                if ($isInsert) {
                    $this->postInsert($con);
                } else {
                    $this->postUpdate($con);
                }
                $this->postSave($con);
                UserTableMap::addInstanceToPool($this);
            } else {
                $affectedRows = 0;
            }

            return $affectedRows;
        });
    }

    /**
     * Performs the work of inserting or updating the row in the database.
     *
     * If the object is new, it inserts it; otherwise an update is performed.
     * All related objects are also updated in this method.
     *
     * @param      ConnectionInterface $con
     * @return int             The number of rows affected by this insert/update and any referring fk objects' save() operations.
     * @throws PropelException
     * @see save()
     */
    protected function doSave(ConnectionInterface $con)
    {
        $affectedRows = 0; // initialize var to track total num of affected rows
        if (!$this->alreadyInSave) {
            $this->alreadyInSave = true;

            if ($this->isNew() || $this->isModified()) {
                // persist changes
                if ($this->isNew()) {
                    $this->doInsert($con);
                    $affectedRows += 1;
                } else {
                    $affectedRows += $this->doUpdate($con);
                }
                $this->resetModified();
            }

            if ($this->userConfigsScheduledForDeletion !== null) {
                if (!$this->userConfigsScheduledForDeletion->isEmpty()) {
                    \ChurchCRM\UserConfigQuery::create()
                        ->filterByPrimaryKeys($this->userConfigsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->userConfigsScheduledForDeletion = null;
                }
            }

            if ($this->collUserConfigs !== null) {
                foreach ($this->collUserConfigs as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            $this->alreadyInSave = false;

        }

        return $affectedRows;
    } // doSave()

    /**
     * Insert the row in the database.
     *
     * @param      ConnectionInterface $con
     *
     * @throws PropelException
     * @see doSave()
     */
    protected function doInsert(ConnectionInterface $con)
    {
        $modifiedColumns = array();
        $index = 0;


         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(UserTableMap::COL_USR_PER_ID)) {
            $modifiedColumns[':p' . $index++]  = 'usr_per_ID';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_PASSWORD)) {
            $modifiedColumns[':p' . $index++]  = 'usr_Password';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_NEEDPASSWORDCHANGE)) {
            $modifiedColumns[':p' . $index++]  = 'usr_NeedPasswordChange';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_LASTLOGIN)) {
            $modifiedColumns[':p' . $index++]  = 'usr_LastLogin';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_LOGINCOUNT)) {
            $modifiedColumns[':p' . $index++]  = 'usr_LoginCount';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_FAILEDLOGINS)) {
            $modifiedColumns[':p' . $index++]  = 'usr_FailedLogins';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_ADDRECORDS)) {
            $modifiedColumns[':p' . $index++]  = 'usr_AddRecords';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_EDITRECORDS)) {
            $modifiedColumns[':p' . $index++]  = 'usr_EditRecords';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_DELETERECORDS)) {
            $modifiedColumns[':p' . $index++]  = 'usr_DeleteRecords';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_MENUOPTIONS)) {
            $modifiedColumns[':p' . $index++]  = 'usr_MenuOptions';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_MANAGEGROUPS)) {
            $modifiedColumns[':p' . $index++]  = 'usr_ManageGroups';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_FINANCE)) {
            $modifiedColumns[':p' . $index++]  = 'usr_Finance';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_COMMUNICATION)) {
            $modifiedColumns[':p' . $index++]  = 'usr_Communication';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_NOTES)) {
            $modifiedColumns[':p' . $index++]  = 'usr_Notes';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_ADMIN)) {
            $modifiedColumns[':p' . $index++]  = 'usr_Admin';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_WORKSPACEWIDTH)) {
            $modifiedColumns[':p' . $index++]  = 'usr_Workspacewidth';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_BASEFONTSIZE)) {
            $modifiedColumns[':p' . $index++]  = 'usr_BaseFontSize';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_SEARCHLIMIT)) {
            $modifiedColumns[':p' . $index++]  = 'usr_SearchLimit';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_STYLE)) {
            $modifiedColumns[':p' . $index++]  = 'usr_Style';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_SHOWPLEDGES)) {
            $modifiedColumns[':p' . $index++]  = 'usr_showPledges';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_SHOWPAYMENTS)) {
            $modifiedColumns[':p' . $index++]  = 'usr_showPayments';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_SHOWSINCE)) {
            $modifiedColumns[':p' . $index++]  = 'usr_showSince';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_DEFAULTFY)) {
            $modifiedColumns[':p' . $index++]  = 'usr_defaultFY';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_CURRENTDEPOSIT)) {
            $modifiedColumns[':p' . $index++]  = 'usr_currentDeposit';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_USERNAME)) {
            $modifiedColumns[':p' . $index++]  = 'usr_UserName';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_EDITSELF)) {
            $modifiedColumns[':p' . $index++]  = 'usr_EditSelf';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_CALSTART)) {
            $modifiedColumns[':p' . $index++]  = 'usr_CalStart';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_CALEND)) {
            $modifiedColumns[':p' . $index++]  = 'usr_CalEnd';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_CALNOSCHOOL1)) {
            $modifiedColumns[':p' . $index++]  = 'usr_CalNoSchool1';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_CALNOSCHOOL2)) {
            $modifiedColumns[':p' . $index++]  = 'usr_CalNoSchool2';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_CALNOSCHOOL3)) {
            $modifiedColumns[':p' . $index++]  = 'usr_CalNoSchool3';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_CALNOSCHOOL4)) {
            $modifiedColumns[':p' . $index++]  = 'usr_CalNoSchool4';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_CALNOSCHOOL5)) {
            $modifiedColumns[':p' . $index++]  = 'usr_CalNoSchool5';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_CALNOSCHOOL6)) {
            $modifiedColumns[':p' . $index++]  = 'usr_CalNoSchool6';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_CALNOSCHOOL7)) {
            $modifiedColumns[':p' . $index++]  = 'usr_CalNoSchool7';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_CALNOSCHOOL8)) {
            $modifiedColumns[':p' . $index++]  = 'usr_CalNoSchool8';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_SEARCHFAMILY)) {
            $modifiedColumns[':p' . $index++]  = 'usr_SearchFamily';
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_CANVASSER)) {
            $modifiedColumns[':p' . $index++]  = 'usr_Canvasser';
        }

        $sql = sprintf(
            'INSERT INTO user_usr (%s) VALUES (%s)',
            implode(', ', $modifiedColumns),
            implode(', ', array_keys($modifiedColumns))
        );

        try {
            $stmt = $con->prepare($sql);
            foreach ($modifiedColumns as $identifier => $columnName) {
                switch ($columnName) {
                    case 'usr_per_ID':
                        $stmt->bindValue($identifier, $this->usr_per_id, PDO::PARAM_INT);
                        break;
                    case 'usr_Password':
                        $stmt->bindValue($identifier, $this->usr_password, PDO::PARAM_STR);
                        break;
                    case 'usr_NeedPasswordChange':
                        $stmt->bindValue($identifier, $this->usr_needpasswordchange, PDO::PARAM_INT);
                        break;
                    case 'usr_LastLogin':
                        $stmt->bindValue($identifier, $this->usr_lastlogin ? $this->usr_lastlogin->format("Y-m-d H:i:s.u") : null, PDO::PARAM_STR);
                        break;
                    case 'usr_LoginCount':
                        $stmt->bindValue($identifier, $this->usr_logincount, PDO::PARAM_INT);
                        break;
                    case 'usr_FailedLogins':
                        $stmt->bindValue($identifier, $this->usr_failedlogins, PDO::PARAM_INT);
                        break;
                    case 'usr_AddRecords':
                        $stmt->bindValue($identifier, $this->usr_addrecords, PDO::PARAM_INT);
                        break;
                    case 'usr_EditRecords':
                        $stmt->bindValue($identifier, $this->usr_editrecords, PDO::PARAM_INT);
                        break;
                    case 'usr_DeleteRecords':
                        $stmt->bindValue($identifier, $this->usr_deleterecords, PDO::PARAM_INT);
                        break;
                    case 'usr_MenuOptions':
                        $stmt->bindValue($identifier, $this->usr_menuoptions, PDO::PARAM_INT);
                        break;
                    case 'usr_ManageGroups':
                        $stmt->bindValue($identifier, $this->usr_managegroups, PDO::PARAM_INT);
                        break;
                    case 'usr_Finance':
                        $stmt->bindValue($identifier, $this->usr_finance, PDO::PARAM_INT);
                        break;
                    case 'usr_Communication':
                        $stmt->bindValue($identifier, $this->usr_communication, PDO::PARAM_INT);
                        break;
                    case 'usr_Notes':
                        $stmt->bindValue($identifier, $this->usr_notes, PDO::PARAM_INT);
                        break;
                    case 'usr_Admin':
                        $stmt->bindValue($identifier, $this->usr_admin, PDO::PARAM_INT);
                        break;
                    case 'usr_Workspacewidth':
                        $stmt->bindValue($identifier, $this->usr_workspacewidth, PDO::PARAM_INT);
                        break;
                    case 'usr_BaseFontSize':
                        $stmt->bindValue($identifier, $this->usr_basefontsize, PDO::PARAM_INT);
                        break;
                    case 'usr_SearchLimit':
                        $stmt->bindValue($identifier, $this->usr_searchlimit, PDO::PARAM_INT);
                        break;
                    case 'usr_Style':
                        $stmt->bindValue($identifier, $this->usr_style, PDO::PARAM_STR);
                        break;
                    case 'usr_showPledges':
                        $stmt->bindValue($identifier, (int) $this->usr_showpledges, PDO::PARAM_INT);
                        break;
                    case 'usr_showPayments':
                        $stmt->bindValue($identifier, (int) $this->usr_showpayments, PDO::PARAM_INT);
                        break;
                    case 'usr_showSince':
                        $stmt->bindValue($identifier, $this->usr_showsince ? $this->usr_showsince->format("Y-m-d H:i:s.u") : null, PDO::PARAM_STR);
                        break;
                    case 'usr_defaultFY':
                        $stmt->bindValue($identifier, $this->usr_defaultfy, PDO::PARAM_INT);
                        break;
                    case 'usr_currentDeposit':
                        $stmt->bindValue($identifier, $this->usr_currentdeposit, PDO::PARAM_INT);
                        break;
                    case 'usr_UserName':
                        $stmt->bindValue($identifier, $this->usr_username, PDO::PARAM_STR);
                        break;
                    case 'usr_EditSelf':
                        $stmt->bindValue($identifier, $this->usr_editself, PDO::PARAM_INT);
                        break;
                    case 'usr_CalStart':
                        $stmt->bindValue($identifier, $this->usr_calstart ? $this->usr_calstart->format("Y-m-d H:i:s.u") : null, PDO::PARAM_STR);
                        break;
                    case 'usr_CalEnd':
                        $stmt->bindValue($identifier, $this->usr_calend ? $this->usr_calend->format("Y-m-d H:i:s.u") : null, PDO::PARAM_STR);
                        break;
                    case 'usr_CalNoSchool1':
                        $stmt->bindValue($identifier, $this->usr_calnoschool1 ? $this->usr_calnoschool1->format("Y-m-d H:i:s.u") : null, PDO::PARAM_STR);
                        break;
                    case 'usr_CalNoSchool2':
                        $stmt->bindValue($identifier, $this->usr_calnoschool2 ? $this->usr_calnoschool2->format("Y-m-d H:i:s.u") : null, PDO::PARAM_STR);
                        break;
                    case 'usr_CalNoSchool3':
                        $stmt->bindValue($identifier, $this->usr_calnoschool3 ? $this->usr_calnoschool3->format("Y-m-d H:i:s.u") : null, PDO::PARAM_STR);
                        break;
                    case 'usr_CalNoSchool4':
                        $stmt->bindValue($identifier, $this->usr_calnoschool4 ? $this->usr_calnoschool4->format("Y-m-d H:i:s.u") : null, PDO::PARAM_STR);
                        break;
                    case 'usr_CalNoSchool5':
                        $stmt->bindValue($identifier, $this->usr_calnoschool5 ? $this->usr_calnoschool5->format("Y-m-d H:i:s.u") : null, PDO::PARAM_STR);
                        break;
                    case 'usr_CalNoSchool6':
                        $stmt->bindValue($identifier, $this->usr_calnoschool6 ? $this->usr_calnoschool6->format("Y-m-d H:i:s.u") : null, PDO::PARAM_STR);
                        break;
                    case 'usr_CalNoSchool7':
                        $stmt->bindValue($identifier, $this->usr_calnoschool7 ? $this->usr_calnoschool7->format("Y-m-d H:i:s.u") : null, PDO::PARAM_STR);
                        break;
                    case 'usr_CalNoSchool8':
                        $stmt->bindValue($identifier, $this->usr_calnoschool8 ? $this->usr_calnoschool8->format("Y-m-d H:i:s.u") : null, PDO::PARAM_STR);
                        break;
                    case 'usr_SearchFamily':
                        $stmt->bindValue($identifier, $this->usr_searchfamily, PDO::PARAM_INT);
                        break;
                    case 'usr_Canvasser':
                        $stmt->bindValue($identifier, $this->usr_canvasser, PDO::PARAM_INT);
                        break;
                }
            }
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute INSERT statement [%s]', $sql), 0, $e);
        }

        $this->setNew(false);
    }

    /**
     * Update the row in the database.
     *
     * @param      ConnectionInterface $con
     *
     * @return Integer Number of updated rows
     * @see doSave()
     */
    protected function doUpdate(ConnectionInterface $con)
    {
        $selectCriteria = $this->buildPkeyCriteria();
        $valuesCriteria = $this->buildCriteria();

        return $selectCriteria->doUpdate($valuesCriteria, $con);
    }

    /**
     * Retrieves a field from the object by name passed in as a string.
     *
     * @param      string $name name
     * @param      string $type The type of fieldname the $name is of:
     *                     one of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME
     *                     TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *                     Defaults to TableMap::TYPE_PHPNAME.
     * @return mixed Value of field.
     */
    public function getByName($name, $type = TableMap::TYPE_PHPNAME)
    {
        $pos = UserTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
        $field = $this->getByPosition($pos);

        return $field;
    }

    /**
     * Retrieves a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param      int $pos position in xml schema
     * @return mixed Value of field at $pos
     */
    public function getByPosition($pos)
    {
        switch ($pos) {
            case 0:
                return $this->getPersonId();
                break;
            case 1:
                return $this->getPassword();
                break;
            case 2:
                return $this->getNeedPasswordChange();
                break;
            case 3:
                return $this->getLastLogin();
                break;
            case 4:
                return $this->getLoginCount();
                break;
            case 5:
                return $this->getFailedLogins();
                break;
            case 6:
                return $this->getAddRecords();
                break;
            case 7:
                return $this->getEditRecords();
                break;
            case 8:
                return $this->getDeleteRecords();
                break;
            case 9:
                return $this->getMenuOptions();
                break;
            case 10:
                return $this->getManageGroups();
                break;
            case 11:
                return $this->getFinance();
                break;
            case 12:
                return $this->getCommunication();
                break;
            case 13:
                return $this->getNotes();
                break;
            case 14:
                return $this->getAdmin();
                break;
            case 15:
                return $this->getWorkspaceWidth();
                break;
            case 16:
                return $this->getBaseFontsize();
                break;
            case 17:
                return $this->getSearchLimit();
                break;
            case 18:
                return $this->getStyle();
                break;
            case 19:
                return $this->getShowPledges();
                break;
            case 20:
                return $this->getShowPayments();
                break;
            case 21:
                return $this->getShowSince();
                break;
            case 22:
                return $this->getDefaultFY();
                break;
            case 23:
                return $this->getCurrentDeposit();
                break;
            case 24:
                return $this->getUserName();
                break;
            case 25:
                return $this->getEditSelf();
                break;
            case 26:
                return $this->getCalStart();
                break;
            case 27:
                return $this->getCalEnd();
                break;
            case 28:
                return $this->getCalNoSchool1();
                break;
            case 29:
                return $this->getCalNoSchool2();
                break;
            case 30:
                return $this->getCalNoSchool3();
                break;
            case 31:
                return $this->getCalNoSchool4();
                break;
            case 32:
                return $this->getCalNoSchool5();
                break;
            case 33:
                return $this->getCalNoSchool6();
                break;
            case 34:
                return $this->getCalNoSchool7();
                break;
            case 35:
                return $this->getCalNoSchool8();
                break;
            case 36:
                return $this->getSearchfamily();
                break;
            case 37:
                return $this->getCanvasser();
                break;
            default:
                return null;
                break;
        } // switch()
    }

    /**
     * Exports the object as an array.
     *
     * You can specify the key type of the array by passing one of the class
     * type constants.
     *
     * @param     string  $keyType (optional) One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME,
     *                    TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *                    Defaults to TableMap::TYPE_PHPNAME.
     * @param     boolean $includeLazyLoadColumns (optional) Whether to include lazy loaded columns. Defaults to TRUE.
     * @param     array $alreadyDumpedObjects List of objects to skip to avoid recursion
     * @param     boolean $includeForeignObjects (optional) Whether to include hydrated related objects. Default to FALSE.
     *
     * @return array an associative array containing the field names (as keys) and field values
     */
    public function toArray($keyType = TableMap::TYPE_PHPNAME, $includeLazyLoadColumns = true, $alreadyDumpedObjects = array(), $includeForeignObjects = false)
    {

        if (isset($alreadyDumpedObjects['User'][$this->hashCode()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['User'][$this->hashCode()] = true;
        $keys = UserTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getPersonId(),
            $keys[1] => $this->getPassword(),
            $keys[2] => $this->getNeedPasswordChange(),
            $keys[3] => $this->getLastLogin(),
            $keys[4] => $this->getLoginCount(),
            $keys[5] => $this->getFailedLogins(),
            $keys[6] => $this->getAddRecords(),
            $keys[7] => $this->getEditRecords(),
            $keys[8] => $this->getDeleteRecords(),
            $keys[9] => $this->getMenuOptions(),
            $keys[10] => $this->getManageGroups(),
            $keys[11] => $this->getFinance(),
            $keys[12] => $this->getCommunication(),
            $keys[13] => $this->getNotes(),
            $keys[14] => $this->getAdmin(),
            $keys[15] => $this->getWorkspaceWidth(),
            $keys[16] => $this->getBaseFontsize(),
            $keys[17] => $this->getSearchLimit(),
            $keys[18] => $this->getStyle(),
            $keys[19] => $this->getShowPledges(),
            $keys[20] => $this->getShowPayments(),
            $keys[21] => $this->getShowSince(),
            $keys[22] => $this->getDefaultFY(),
            $keys[23] => $this->getCurrentDeposit(),
            $keys[24] => $this->getUserName(),
            $keys[25] => $this->getEditSelf(),
            $keys[26] => $this->getCalStart(),
            $keys[27] => $this->getCalEnd(),
            $keys[28] => $this->getCalNoSchool1(),
            $keys[29] => $this->getCalNoSchool2(),
            $keys[30] => $this->getCalNoSchool3(),
            $keys[31] => $this->getCalNoSchool4(),
            $keys[32] => $this->getCalNoSchool5(),
            $keys[33] => $this->getCalNoSchool6(),
            $keys[34] => $this->getCalNoSchool7(),
            $keys[35] => $this->getCalNoSchool8(),
            $keys[36] => $this->getSearchfamily(),
            $keys[37] => $this->getCanvasser(),
        );
        if ($result[$keys[3]] instanceof \DateTime) {
            $result[$keys[3]] = $result[$keys[3]]->format('c');
        }

        if ($result[$keys[21]] instanceof \DateTime) {
            $result[$keys[21]] = $result[$keys[21]]->format('c');
        }

        if ($result[$keys[26]] instanceof \DateTime) {
            $result[$keys[26]] = $result[$keys[26]]->format('c');
        }

        if ($result[$keys[27]] instanceof \DateTime) {
            $result[$keys[27]] = $result[$keys[27]]->format('c');
        }

        if ($result[$keys[28]] instanceof \DateTime) {
            $result[$keys[28]] = $result[$keys[28]]->format('c');
        }

        if ($result[$keys[29]] instanceof \DateTime) {
            $result[$keys[29]] = $result[$keys[29]]->format('c');
        }

        if ($result[$keys[30]] instanceof \DateTime) {
            $result[$keys[30]] = $result[$keys[30]]->format('c');
        }

        if ($result[$keys[31]] instanceof \DateTime) {
            $result[$keys[31]] = $result[$keys[31]]->format('c');
        }

        if ($result[$keys[32]] instanceof \DateTime) {
            $result[$keys[32]] = $result[$keys[32]]->format('c');
        }

        if ($result[$keys[33]] instanceof \DateTime) {
            $result[$keys[33]] = $result[$keys[33]]->format('c');
        }

        if ($result[$keys[34]] instanceof \DateTime) {
            $result[$keys[34]] = $result[$keys[34]]->format('c');
        }

        if ($result[$keys[35]] instanceof \DateTime) {
            $result[$keys[35]] = $result[$keys[35]]->format('c');
        }

        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->collUserConfigs) {

                switch ($keyType) {
                    case TableMap::TYPE_CAMELNAME:
                        $key = 'userConfigs';
                        break;
                    case TableMap::TYPE_FIELDNAME:
                        $key = 'userconfig_ucfgs';
                        break;
                    default:
                        $key = 'UserConfigs';
                }

                $result[$key] = $this->collUserConfigs->toArray(null, false, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
        }

        return $result;
    }

    /**
     * Sets a field from the object by name passed in as a string.
     *
     * @param  string $name
     * @param  mixed  $value field value
     * @param  string $type The type of fieldname the $name is of:
     *                one of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME
     *                TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *                Defaults to TableMap::TYPE_PHPNAME.
     * @return $this|\ChurchCRM\User
     */
    public function setByName($name, $value, $type = TableMap::TYPE_PHPNAME)
    {
        $pos = UserTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

        return $this->setByPosition($pos, $value);
    }

    /**
     * Sets a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param  int $pos position in xml schema
     * @param  mixed $value field value
     * @return $this|\ChurchCRM\User
     */
    public function setByPosition($pos, $value)
    {
        switch ($pos) {
            case 0:
                $this->setPersonId($value);
                break;
            case 1:
                $this->setPassword($value);
                break;
            case 2:
                $this->setNeedPasswordChange($value);
                break;
            case 3:
                $this->setLastLogin($value);
                break;
            case 4:
                $this->setLoginCount($value);
                break;
            case 5:
                $this->setFailedLogins($value);
                break;
            case 6:
                $this->setAddRecords($value);
                break;
            case 7:
                $this->setEditRecords($value);
                break;
            case 8:
                $this->setDeleteRecords($value);
                break;
            case 9:
                $this->setMenuOptions($value);
                break;
            case 10:
                $this->setManageGroups($value);
                break;
            case 11:
                $this->setFinance($value);
                break;
            case 12:
                $this->setCommunication($value);
                break;
            case 13:
                $this->setNotes($value);
                break;
            case 14:
                $this->setAdmin($value);
                break;
            case 15:
                $this->setWorkspaceWidth($value);
                break;
            case 16:
                $this->setBaseFontsize($value);
                break;
            case 17:
                $this->setSearchLimit($value);
                break;
            case 18:
                $this->setStyle($value);
                break;
            case 19:
                $this->setShowPledges($value);
                break;
            case 20:
                $this->setShowPayments($value);
                break;
            case 21:
                $this->setShowSince($value);
                break;
            case 22:
                $this->setDefaultFY($value);
                break;
            case 23:
                $this->setCurrentDeposit($value);
                break;
            case 24:
                $this->setUserName($value);
                break;
            case 25:
                $this->setEditSelf($value);
                break;
            case 26:
                $this->setCalStart($value);
                break;
            case 27:
                $this->setCalEnd($value);
                break;
            case 28:
                $this->setCalNoSchool1($value);
                break;
            case 29:
                $this->setCalNoSchool2($value);
                break;
            case 30:
                $this->setCalNoSchool3($value);
                break;
            case 31:
                $this->setCalNoSchool4($value);
                break;
            case 32:
                $this->setCalNoSchool5($value);
                break;
            case 33:
                $this->setCalNoSchool6($value);
                break;
            case 34:
                $this->setCalNoSchool7($value);
                break;
            case 35:
                $this->setCalNoSchool8($value);
                break;
            case 36:
                $this->setSearchfamily($value);
                break;
            case 37:
                $this->setCanvasser($value);
                break;
        } // switch()

        return $this;
    }

    /**
     * Populates the object using an array.
     *
     * This is particularly useful when populating an object from one of the
     * request arrays (e.g. $_POST).  This method goes through the column
     * names, checking to see whether a matching key exists in populated
     * array. If so the setByName() method is called for that column.
     *
     * You can specify the key type of the array by additionally passing one
     * of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME,
     * TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     * The default key type is the column's TableMap::TYPE_PHPNAME.
     *
     * @param      array  $arr     An array to populate the object from.
     * @param      string $keyType The type of keys the array uses.
     * @return void
     */
    public function fromArray($arr, $keyType = TableMap::TYPE_PHPNAME)
    {
        $keys = UserTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) {
            $this->setPersonId($arr[$keys[0]]);
        }
        if (array_key_exists($keys[1], $arr)) {
            $this->setPassword($arr[$keys[1]]);
        }
        if (array_key_exists($keys[2], $arr)) {
            $this->setNeedPasswordChange($arr[$keys[2]]);
        }
        if (array_key_exists($keys[3], $arr)) {
            $this->setLastLogin($arr[$keys[3]]);
        }
        if (array_key_exists($keys[4], $arr)) {
            $this->setLoginCount($arr[$keys[4]]);
        }
        if (array_key_exists($keys[5], $arr)) {
            $this->setFailedLogins($arr[$keys[5]]);
        }
        if (array_key_exists($keys[6], $arr)) {
            $this->setAddRecords($arr[$keys[6]]);
        }
        if (array_key_exists($keys[7], $arr)) {
            $this->setEditRecords($arr[$keys[7]]);
        }
        if (array_key_exists($keys[8], $arr)) {
            $this->setDeleteRecords($arr[$keys[8]]);
        }
        if (array_key_exists($keys[9], $arr)) {
            $this->setMenuOptions($arr[$keys[9]]);
        }
        if (array_key_exists($keys[10], $arr)) {
            $this->setManageGroups($arr[$keys[10]]);
        }
        if (array_key_exists($keys[11], $arr)) {
            $this->setFinance($arr[$keys[11]]);
        }
        if (array_key_exists($keys[12], $arr)) {
            $this->setCommunication($arr[$keys[12]]);
        }
        if (array_key_exists($keys[13], $arr)) {
            $this->setNotes($arr[$keys[13]]);
        }
        if (array_key_exists($keys[14], $arr)) {
            $this->setAdmin($arr[$keys[14]]);
        }
        if (array_key_exists($keys[15], $arr)) {
            $this->setWorkspaceWidth($arr[$keys[15]]);
        }
        if (array_key_exists($keys[16], $arr)) {
            $this->setBaseFontsize($arr[$keys[16]]);
        }
        if (array_key_exists($keys[17], $arr)) {
            $this->setSearchLimit($arr[$keys[17]]);
        }
        if (array_key_exists($keys[18], $arr)) {
            $this->setStyle($arr[$keys[18]]);
        }
        if (array_key_exists($keys[19], $arr)) {
            $this->setShowPledges($arr[$keys[19]]);
        }
        if (array_key_exists($keys[20], $arr)) {
            $this->setShowPayments($arr[$keys[20]]);
        }
        if (array_key_exists($keys[21], $arr)) {
            $this->setShowSince($arr[$keys[21]]);
        }
        if (array_key_exists($keys[22], $arr)) {
            $this->setDefaultFY($arr[$keys[22]]);
        }
        if (array_key_exists($keys[23], $arr)) {
            $this->setCurrentDeposit($arr[$keys[23]]);
        }
        if (array_key_exists($keys[24], $arr)) {
            $this->setUserName($arr[$keys[24]]);
        }
        if (array_key_exists($keys[25], $arr)) {
            $this->setEditSelf($arr[$keys[25]]);
        }
        if (array_key_exists($keys[26], $arr)) {
            $this->setCalStart($arr[$keys[26]]);
        }
        if (array_key_exists($keys[27], $arr)) {
            $this->setCalEnd($arr[$keys[27]]);
        }
        if (array_key_exists($keys[28], $arr)) {
            $this->setCalNoSchool1($arr[$keys[28]]);
        }
        if (array_key_exists($keys[29], $arr)) {
            $this->setCalNoSchool2($arr[$keys[29]]);
        }
        if (array_key_exists($keys[30], $arr)) {
            $this->setCalNoSchool3($arr[$keys[30]]);
        }
        if (array_key_exists($keys[31], $arr)) {
            $this->setCalNoSchool4($arr[$keys[31]]);
        }
        if (array_key_exists($keys[32], $arr)) {
            $this->setCalNoSchool5($arr[$keys[32]]);
        }
        if (array_key_exists($keys[33], $arr)) {
            $this->setCalNoSchool6($arr[$keys[33]]);
        }
        if (array_key_exists($keys[34], $arr)) {
            $this->setCalNoSchool7($arr[$keys[34]]);
        }
        if (array_key_exists($keys[35], $arr)) {
            $this->setCalNoSchool8($arr[$keys[35]]);
        }
        if (array_key_exists($keys[36], $arr)) {
            $this->setSearchfamily($arr[$keys[36]]);
        }
        if (array_key_exists($keys[37], $arr)) {
            $this->setCanvasser($arr[$keys[37]]);
        }
    }

     /**
     * Populate the current object from a string, using a given parser format
     * <code>
     * $book = new Book();
     * $book->importFrom('JSON', '{"Id":9012,"Title":"Don Juan","ISBN":"0140422161","Price":12.99,"PublisherId":1234,"AuthorId":5678}');
     * </code>
     *
     * You can specify the key type of the array by additionally passing one
     * of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_CAMELNAME,
     * TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     * The default key type is the column's TableMap::TYPE_PHPNAME.
     *
     * @param mixed $parser A AbstractParser instance,
     *                       or a format name ('XML', 'YAML', 'JSON', 'CSV')
     * @param string $data The source data to import from
     * @param string $keyType The type of keys the array uses.
     *
     * @return $this|\ChurchCRM\User The current object, for fluid interface
     */
    public function importFrom($parser, $data, $keyType = TableMap::TYPE_PHPNAME)
    {
        if (!$parser instanceof AbstractParser) {
            $parser = AbstractParser::getParser($parser);
        }

        $this->fromArray($parser->toArray($data), $keyType);

        return $this;
    }

    /**
     * Build a Criteria object containing the values of all modified columns in this object.
     *
     * @return Criteria The Criteria object containing all modified values.
     */
    public function buildCriteria()
    {
        $criteria = new Criteria(UserTableMap::DATABASE_NAME);

        if ($this->isColumnModified(UserTableMap::COL_USR_PER_ID)) {
            $criteria->add(UserTableMap::COL_USR_PER_ID, $this->usr_per_id);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_PASSWORD)) {
            $criteria->add(UserTableMap::COL_USR_PASSWORD, $this->usr_password);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_NEEDPASSWORDCHANGE)) {
            $criteria->add(UserTableMap::COL_USR_NEEDPASSWORDCHANGE, $this->usr_needpasswordchange);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_LASTLOGIN)) {
            $criteria->add(UserTableMap::COL_USR_LASTLOGIN, $this->usr_lastlogin);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_LOGINCOUNT)) {
            $criteria->add(UserTableMap::COL_USR_LOGINCOUNT, $this->usr_logincount);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_FAILEDLOGINS)) {
            $criteria->add(UserTableMap::COL_USR_FAILEDLOGINS, $this->usr_failedlogins);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_ADDRECORDS)) {
            $criteria->add(UserTableMap::COL_USR_ADDRECORDS, $this->usr_addrecords);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_EDITRECORDS)) {
            $criteria->add(UserTableMap::COL_USR_EDITRECORDS, $this->usr_editrecords);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_DELETERECORDS)) {
            $criteria->add(UserTableMap::COL_USR_DELETERECORDS, $this->usr_deleterecords);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_MENUOPTIONS)) {
            $criteria->add(UserTableMap::COL_USR_MENUOPTIONS, $this->usr_menuoptions);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_MANAGEGROUPS)) {
            $criteria->add(UserTableMap::COL_USR_MANAGEGROUPS, $this->usr_managegroups);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_FINANCE)) {
            $criteria->add(UserTableMap::COL_USR_FINANCE, $this->usr_finance);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_COMMUNICATION)) {
            $criteria->add(UserTableMap::COL_USR_COMMUNICATION, $this->usr_communication);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_NOTES)) {
            $criteria->add(UserTableMap::COL_USR_NOTES, $this->usr_notes);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_ADMIN)) {
            $criteria->add(UserTableMap::COL_USR_ADMIN, $this->usr_admin);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_WORKSPACEWIDTH)) {
            $criteria->add(UserTableMap::COL_USR_WORKSPACEWIDTH, $this->usr_workspacewidth);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_BASEFONTSIZE)) {
            $criteria->add(UserTableMap::COL_USR_BASEFONTSIZE, $this->usr_basefontsize);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_SEARCHLIMIT)) {
            $criteria->add(UserTableMap::COL_USR_SEARCHLIMIT, $this->usr_searchlimit);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_STYLE)) {
            $criteria->add(UserTableMap::COL_USR_STYLE, $this->usr_style);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_SHOWPLEDGES)) {
            $criteria->add(UserTableMap::COL_USR_SHOWPLEDGES, $this->usr_showpledges);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_SHOWPAYMENTS)) {
            $criteria->add(UserTableMap::COL_USR_SHOWPAYMENTS, $this->usr_showpayments);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_SHOWSINCE)) {
            $criteria->add(UserTableMap::COL_USR_SHOWSINCE, $this->usr_showsince);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_DEFAULTFY)) {
            $criteria->add(UserTableMap::COL_USR_DEFAULTFY, $this->usr_defaultfy);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_CURRENTDEPOSIT)) {
            $criteria->add(UserTableMap::COL_USR_CURRENTDEPOSIT, $this->usr_currentdeposit);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_USERNAME)) {
            $criteria->add(UserTableMap::COL_USR_USERNAME, $this->usr_username);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_EDITSELF)) {
            $criteria->add(UserTableMap::COL_USR_EDITSELF, $this->usr_editself);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_CALSTART)) {
            $criteria->add(UserTableMap::COL_USR_CALSTART, $this->usr_calstart);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_CALEND)) {
            $criteria->add(UserTableMap::COL_USR_CALEND, $this->usr_calend);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_CALNOSCHOOL1)) {
            $criteria->add(UserTableMap::COL_USR_CALNOSCHOOL1, $this->usr_calnoschool1);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_CALNOSCHOOL2)) {
            $criteria->add(UserTableMap::COL_USR_CALNOSCHOOL2, $this->usr_calnoschool2);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_CALNOSCHOOL3)) {
            $criteria->add(UserTableMap::COL_USR_CALNOSCHOOL3, $this->usr_calnoschool3);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_CALNOSCHOOL4)) {
            $criteria->add(UserTableMap::COL_USR_CALNOSCHOOL4, $this->usr_calnoschool4);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_CALNOSCHOOL5)) {
            $criteria->add(UserTableMap::COL_USR_CALNOSCHOOL5, $this->usr_calnoschool5);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_CALNOSCHOOL6)) {
            $criteria->add(UserTableMap::COL_USR_CALNOSCHOOL6, $this->usr_calnoschool6);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_CALNOSCHOOL7)) {
            $criteria->add(UserTableMap::COL_USR_CALNOSCHOOL7, $this->usr_calnoschool7);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_CALNOSCHOOL8)) {
            $criteria->add(UserTableMap::COL_USR_CALNOSCHOOL8, $this->usr_calnoschool8);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_SEARCHFAMILY)) {
            $criteria->add(UserTableMap::COL_USR_SEARCHFAMILY, $this->usr_searchfamily);
        }
        if ($this->isColumnModified(UserTableMap::COL_USR_CANVASSER)) {
            $criteria->add(UserTableMap::COL_USR_CANVASSER, $this->usr_canvasser);
        }

        return $criteria;
    }

    /**
     * Builds a Criteria object containing the primary key for this object.
     *
     * Unlike buildCriteria() this method includes the primary key values regardless
     * of whether or not they have been modified.
     *
     * @throws LogicException if no primary key is defined
     *
     * @return Criteria The Criteria object containing value(s) for primary key(s).
     */
    public function buildPkeyCriteria()
    {
        $criteria = ChildUserQuery::create();
        $criteria->add(UserTableMap::COL_USR_PER_ID, $this->usr_per_id);

        return $criteria;
    }

    /**
     * If the primary key is not null, return the hashcode of the
     * primary key. Otherwise, return the hash code of the object.
     *
     * @return int Hashcode
     */
    public function hashCode()
    {
        $validPk = null !== $this->getPersonId();

        $validPrimaryKeyFKs = 0;
        $primaryKeyFKs = [];

        if ($validPk) {
            return crc32(json_encode($this->getPrimaryKey(), JSON_UNESCAPED_UNICODE));
        } elseif ($validPrimaryKeyFKs) {
            return crc32(json_encode($primaryKeyFKs, JSON_UNESCAPED_UNICODE));
        }

        return spl_object_hash($this);
    }

    /**
     * Returns the primary key for this object (row).
     * @return int
     */
    public function getPrimaryKey()
    {
        return $this->getPersonId();
    }

    /**
     * Generic method to set the primary key (usr_per_id column).
     *
     * @param       int $key Primary key.
     * @return void
     */
    public function setPrimaryKey($key)
    {
        $this->setPersonId($key);
    }

    /**
     * Returns true if the primary key for this object is null.
     * @return boolean
     */
    public function isPrimaryKeyNull()
    {
        return null === $this->getPersonId();
    }

    /**
     * Sets contents of passed object to values from current object.
     *
     * If desired, this method can also make copies of all associated (fkey referrers)
     * objects.
     *
     * @param      object $copyObj An object of \ChurchCRM\User (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setPersonId($this->getPersonId());
        $copyObj->setPassword($this->getPassword());
        $copyObj->setNeedPasswordChange($this->getNeedPasswordChange());
        $copyObj->setLastLogin($this->getLastLogin());
        $copyObj->setLoginCount($this->getLoginCount());
        $copyObj->setFailedLogins($this->getFailedLogins());
        $copyObj->setAddRecords($this->getAddRecords());
        $copyObj->setEditRecords($this->getEditRecords());
        $copyObj->setDeleteRecords($this->getDeleteRecords());
        $copyObj->setMenuOptions($this->getMenuOptions());
        $copyObj->setManageGroups($this->getManageGroups());
        $copyObj->setFinance($this->getFinance());
        $copyObj->setCommunication($this->getCommunication());
        $copyObj->setNotes($this->getNotes());
        $copyObj->setAdmin($this->getAdmin());
        $copyObj->setWorkspaceWidth($this->getWorkspaceWidth());
        $copyObj->setBaseFontsize($this->getBaseFontsize());
        $copyObj->setSearchLimit($this->getSearchLimit());
        $copyObj->setStyle($this->getStyle());
        $copyObj->setShowPledges($this->getShowPledges());
        $copyObj->setShowPayments($this->getShowPayments());
        $copyObj->setShowSince($this->getShowSince());
        $copyObj->setDefaultFY($this->getDefaultFY());
        $copyObj->setCurrentDeposit($this->getCurrentDeposit());
        $copyObj->setUserName($this->getUserName());
        $copyObj->setEditSelf($this->getEditSelf());
        $copyObj->setCalStart($this->getCalStart());
        $copyObj->setCalEnd($this->getCalEnd());
        $copyObj->setCalNoSchool1($this->getCalNoSchool1());
        $copyObj->setCalNoSchool2($this->getCalNoSchool2());
        $copyObj->setCalNoSchool3($this->getCalNoSchool3());
        $copyObj->setCalNoSchool4($this->getCalNoSchool4());
        $copyObj->setCalNoSchool5($this->getCalNoSchool5());
        $copyObj->setCalNoSchool6($this->getCalNoSchool6());
        $copyObj->setCalNoSchool7($this->getCalNoSchool7());
        $copyObj->setCalNoSchool8($this->getCalNoSchool8());
        $copyObj->setSearchfamily($this->getSearchfamily());
        $copyObj->setCanvasser($this->getCanvasser());

        if ($deepCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);

            foreach ($this->getUserConfigs() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addUserConfig($relObj->copy($deepCopy));
                }
            }

        } // if ($deepCopy)

        if ($makeNew) {
            $copyObj->setNew(true);
        }
    }

    /**
     * Makes a copy of this object that will be inserted as a new row in table when saved.
     * It creates a new object filling in the simple attributes, but skipping any primary
     * keys that are defined for the table.
     *
     * If desired, this method can also make copies of all associated (fkey referrers)
     * objects.
     *
     * @param  boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @return \ChurchCRM\User Clone of current object.
     * @throws PropelException
     */
    public function copy($deepCopy = false)
    {
        // we use get_class(), because this might be a subclass
        $clazz = get_class($this);
        $copyObj = new $clazz();
        $this->copyInto($copyObj, $deepCopy);

        return $copyObj;
    }


    /**
     * Initializes a collection based on the name of a relation.
     * Avoids crafting an 'init[$relationName]s' method name
     * that wouldn't work when StandardEnglishPluralizer is used.
     *
     * @param      string $relationName The name of the relation to initialize
     * @return void
     */
    public function initRelation($relationName)
    {
        if ('UserConfig' == $relationName) {
            return $this->initUserConfigs();
        }
    }

    /**
     * Clears out the collUserConfigs collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addUserConfigs()
     */
    public function clearUserConfigs()
    {
        $this->collUserConfigs = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collUserConfigs collection loaded partially.
     */
    public function resetPartialUserConfigs($v = true)
    {
        $this->collUserConfigsPartial = $v;
    }

    /**
     * Initializes the collUserConfigs collection.
     *
     * By default this just sets the collUserConfigs collection to an empty array (like clearcollUserConfigs());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initUserConfigs($overrideExisting = true)
    {
        if (null !== $this->collUserConfigs && !$overrideExisting) {
            return;
        }

        $collectionClassName = UserConfigTableMap::getTableMap()->getCollectionClassName();

        $this->collUserConfigs = new $collectionClassName;
        $this->collUserConfigs->setModel('\ChurchCRM\UserConfig');
    }

    /**
     * Gets an array of ChildUserConfig objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildUser is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return ObjectCollection|ChildUserConfig[] List of ChildUserConfig objects
     * @throws PropelException
     */
    public function getUserConfigs(Criteria $criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collUserConfigsPartial && !$this->isNew();
        if (null === $this->collUserConfigs || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collUserConfigs) {
                // return empty collection
                $this->initUserConfigs();
            } else {
                $collUserConfigs = ChildUserConfigQuery::create(null, $criteria)
                    ->filterByUser($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collUserConfigsPartial && count($collUserConfigs)) {
                        $this->initUserConfigs(false);

                        foreach ($collUserConfigs as $obj) {
                            if (false == $this->collUserConfigs->contains($obj)) {
                                $this->collUserConfigs->append($obj);
                            }
                        }

                        $this->collUserConfigsPartial = true;
                    }

                    return $collUserConfigs;
                }

                if ($partial && $this->collUserConfigs) {
                    foreach ($this->collUserConfigs as $obj) {
                        if ($obj->isNew()) {
                            $collUserConfigs[] = $obj;
                        }
                    }
                }

                $this->collUserConfigs = $collUserConfigs;
                $this->collUserConfigsPartial = false;
            }
        }

        return $this->collUserConfigs;
    }

    /**
     * Sets a collection of ChildUserConfig objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $userConfigs A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return $this|ChildUser The current object (for fluent API support)
     */
    public function setUserConfigs(Collection $userConfigs, ConnectionInterface $con = null)
    {
        /** @var ChildUserConfig[] $userConfigsToDelete */
        $userConfigsToDelete = $this->getUserConfigs(new Criteria(), $con)->diff($userConfigs);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->userConfigsScheduledForDeletion = clone $userConfigsToDelete;

        foreach ($userConfigsToDelete as $userConfigRemoved) {
            $userConfigRemoved->setUser(null);
        }

        $this->collUserConfigs = null;
        foreach ($userConfigs as $userConfig) {
            $this->addUserConfig($userConfig);
        }

        $this->collUserConfigs = $userConfigs;
        $this->collUserConfigsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related UserConfig objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related UserConfig objects.
     * @throws PropelException
     */
    public function countUserConfigs(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collUserConfigsPartial && !$this->isNew();
        if (null === $this->collUserConfigs || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collUserConfigs) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getUserConfigs());
            }

            $query = ChildUserConfigQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByUser($this)
                ->count($con);
        }

        return count($this->collUserConfigs);
    }

    /**
     * Method called to associate a ChildUserConfig object to this object
     * through the ChildUserConfig foreign key attribute.
     *
     * @param  ChildUserConfig $l ChildUserConfig
     * @return $this|\ChurchCRM\User The current object (for fluent API support)
     */
    public function addUserConfig(ChildUserConfig $l)
    {
        if ($this->collUserConfigs === null) {
            $this->initUserConfigs();
            $this->collUserConfigsPartial = true;
        }

        if (!$this->collUserConfigs->contains($l)) {
            $this->doAddUserConfig($l);

            if ($this->userConfigsScheduledForDeletion and $this->userConfigsScheduledForDeletion->contains($l)) {
                $this->userConfigsScheduledForDeletion->remove($this->userConfigsScheduledForDeletion->search($l));
            }
        }

        return $this;
    }

    /**
     * @param ChildUserConfig $userConfig The ChildUserConfig object to add.
     */
    protected function doAddUserConfig(ChildUserConfig $userConfig)
    {
        $this->collUserConfigs[]= $userConfig;
        $userConfig->setUser($this);
    }

    /**
     * @param  ChildUserConfig $userConfig The ChildUserConfig object to remove.
     * @return $this|ChildUser The current object (for fluent API support)
     */
    public function removeUserConfig(ChildUserConfig $userConfig)
    {
        if ($this->getUserConfigs()->contains($userConfig)) {
            $pos = $this->collUserConfigs->search($userConfig);
            $this->collUserConfigs->remove($pos);
            if (null === $this->userConfigsScheduledForDeletion) {
                $this->userConfigsScheduledForDeletion = clone $this->collUserConfigs;
                $this->userConfigsScheduledForDeletion->clear();
            }
            $this->userConfigsScheduledForDeletion[]= clone $userConfig;
            $userConfig->setUser(null);
        }

        return $this;
    }

    /**
     * Clears the current object, sets all attributes to their default values and removes
     * outgoing references as well as back-references (from other objects to this one. Results probably in a database
     * change of those foreign objects when you call `save` there).
     */
    public function clear()
    {
        $this->usr_per_id = null;
        $this->usr_password = null;
        $this->usr_needpasswordchange = null;
        $this->usr_lastlogin = null;
        $this->usr_logincount = null;
        $this->usr_failedlogins = null;
        $this->usr_addrecords = null;
        $this->usr_editrecords = null;
        $this->usr_deleterecords = null;
        $this->usr_menuoptions = null;
        $this->usr_managegroups = null;
        $this->usr_finance = null;
        $this->usr_communication = null;
        $this->usr_notes = null;
        $this->usr_admin = null;
        $this->usr_workspacewidth = null;
        $this->usr_basefontsize = null;
        $this->usr_searchlimit = null;
        $this->usr_style = null;
        $this->usr_showpledges = null;
        $this->usr_showpayments = null;
        $this->usr_showsince = null;
        $this->usr_defaultfy = null;
        $this->usr_currentdeposit = null;
        $this->usr_username = null;
        $this->usr_editself = null;
        $this->usr_calstart = null;
        $this->usr_calend = null;
        $this->usr_calnoschool1 = null;
        $this->usr_calnoschool2 = null;
        $this->usr_calnoschool3 = null;
        $this->usr_calnoschool4 = null;
        $this->usr_calnoschool5 = null;
        $this->usr_calnoschool6 = null;
        $this->usr_calnoschool7 = null;
        $this->usr_calnoschool8 = null;
        $this->usr_searchfamily = null;
        $this->usr_canvasser = null;
        $this->alreadyInSave = false;
        $this->clearAllReferences();
        $this->applyDefaultValues();
        $this->resetModified();
        $this->setNew(true);
        $this->setDeleted(false);
    }

    /**
     * Resets all references and back-references to other model objects or collections of model objects.
     *
     * This method is used to reset all php object references (not the actual reference in the database).
     * Necessary for object serialisation.
     *
     * @param      boolean $deep Whether to also clear the references on all referrer objects.
     */
    public function clearAllReferences($deep = false)
    {
        if ($deep) {
            if ($this->collUserConfigs) {
                foreach ($this->collUserConfigs as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        $this->collUserConfigs = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(UserTableMap::DEFAULT_STRING_FORMAT);
    }

    /**
     * Code to be run before persisting the object
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preSave(ConnectionInterface $con = null)
    {
        if (is_callable('parent::preSave')) {
            return parent::preSave($con);
        }
        return true;
    }

    /**
     * Code to be run after persisting the object
     * @param ConnectionInterface $con
     */
    public function postSave(ConnectionInterface $con = null)
    {
        if (is_callable('parent::postSave')) {
            parent::postSave($con);
        }
    }

    /**
     * Code to be run before inserting to database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        if (is_callable('parent::preInsert')) {
            return parent::preInsert($con);
        }
        return true;
    }

    /**
     * Code to be run after inserting to database
     * @param ConnectionInterface $con
     */
    public function postInsert(ConnectionInterface $con = null)
    {
        if (is_callable('parent::postInsert')) {
            parent::postInsert($con);
        }
    }

    /**
     * Code to be run before updating the object in database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preUpdate(ConnectionInterface $con = null)
    {
        if (is_callable('parent::preUpdate')) {
            return parent::preUpdate($con);
        }
        return true;
    }

    /**
     * Code to be run after updating the object in database
     * @param ConnectionInterface $con
     */
    public function postUpdate(ConnectionInterface $con = null)
    {
        if (is_callable('parent::postUpdate')) {
            parent::postUpdate($con);
        }
    }

    /**
     * Code to be run before deleting the object in database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preDelete(ConnectionInterface $con = null)
    {
        if (is_callable('parent::preDelete')) {
            return parent::preDelete($con);
        }
        return true;
    }

    /**
     * Code to be run after deleting the object in database
     * @param ConnectionInterface $con
     */
    public function postDelete(ConnectionInterface $con = null)
    {
        if (is_callable('parent::postDelete')) {
            parent::postDelete($con);
        }
    }


    /**
     * Derived method to catches calls to undefined methods.
     *
     * Provides magic import/export method support (fromXML()/toXML(), fromYAML()/toYAML(), etc.).
     * Allows to define default __call() behavior if you overwrite __call()
     *
     * @param string $name
     * @param mixed  $params
     *
     * @return array|string
     */
    public function __call($name, $params)
    {
        if (0 === strpos($name, 'get')) {
            $virtualColumn = substr($name, 3);
            if ($this->hasVirtualColumn($virtualColumn)) {
                return $this->getVirtualColumn($virtualColumn);
            }

            $virtualColumn = lcfirst($virtualColumn);
            if ($this->hasVirtualColumn($virtualColumn)) {
                return $this->getVirtualColumn($virtualColumn);
            }
        }

        if (0 === strpos($name, 'from')) {
            $format = substr($name, 4);

            return $this->importFrom($format, reset($params));
        }

        if (0 === strpos($name, 'to')) {
            $format = substr($name, 2);
            $includeLazyLoadColumns = isset($params[0]) ? $params[0] : true;

            return $this->exportTo($format, $includeLazyLoadColumns);
        }

        throw new BadMethodCallException(sprintf('Call to undefined method: %s.', $name));
    }

}
