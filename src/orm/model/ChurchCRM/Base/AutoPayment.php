<?php

namespace ChurchCRM\Base;

use \DateTime;
use \Exception;
use \PDO;
use ChurchCRM\AutoPaymentQuery as ChildAutoPaymentQuery;
use ChurchCRM\Map\AutoPaymentTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Collection\Collection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\BadMethodCallException;
use Propel\Runtime\Exception\LogicException;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Parser\AbstractParser;
use Propel\Runtime\Util\PropelDateTime;

/**
 * Base class that represents a row from the 'autopayment_aut' table.
 *
 *
 *
 * @package    propel.generator.ChurchCRM.Base
 */
abstract class AutoPayment implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\ChurchCRM\\Map\\AutoPaymentTableMap';


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
     * The value for the aut_id field.
     *
     * @var        int
     */
    protected $aut_id;

    /**
     * The value for the aut_famid field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $aut_famid;

    /**
     * The value for the aut_enablebankdraft field.
     *
     * Note: this column has a database default value of: false
     * @var        boolean
     */
    protected $aut_enablebankdraft;

    /**
     * The value for the aut_enablecreditcard field.
     *
     * Note: this column has a database default value of: false
     * @var        boolean
     */
    protected $aut_enablecreditcard;

    /**
     * The value for the aut_nextpaydate field.
     *
     * @var        DateTime
     */
    protected $aut_nextpaydate;

    /**
     * The value for the aut_fyid field.
     *
     * Note: this column has a database default value of: 9
     * @var        int
     */
    protected $aut_fyid;

    /**
     * The value for the aut_amount field.
     *
     * Note: this column has a database default value of: '0.00'
     * @var        string
     */
    protected $aut_amount;

    /**
     * The value for the aut_interval field.
     *
     * Note: this column has a database default value of: 1
     * @var        int
     */
    protected $aut_interval;

    /**
     * The value for the aut_fund field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $aut_fund;

    /**
     * The value for the aut_firstname field.
     *
     * @var        string
     */
    protected $aut_firstname;

    /**
     * The value for the aut_lastname field.
     *
     * @var        string
     */
    protected $aut_lastname;

    /**
     * The value for the aut_address1 field.
     *
     * @var        string
     */
    protected $aut_address1;

    /**
     * The value for the aut_address2 field.
     *
     * @var        string
     */
    protected $aut_address2;

    /**
     * The value for the aut_city field.
     *
     * @var        string
     */
    protected $aut_city;

    /**
     * The value for the aut_state field.
     *
     * @var        string
     */
    protected $aut_state;

    /**
     * The value for the aut_zip field.
     *
     * @var        string
     */
    protected $aut_zip;

    /**
     * The value for the aut_country field.
     *
     * @var        string
     */
    protected $aut_country;

    /**
     * The value for the aut_phone field.
     *
     * @var        string
     */
    protected $aut_phone;

    /**
     * The value for the aut_email field.
     *
     * @var        string
     */
    protected $aut_email;

    /**
     * The value for the aut_creditcard field.
     *
     * @var        string
     */
    protected $aut_creditcard;

    /**
     * The value for the aut_expmonth field.
     *
     * @var        string
     */
    protected $aut_expmonth;

    /**
     * The value for the aut_expyear field.
     *
     * @var        string
     */
    protected $aut_expyear;

    /**
     * The value for the aut_bankname field.
     *
     * @var        string
     */
    protected $aut_bankname;

    /**
     * The value for the aut_route field.
     *
     * @var        string
     */
    protected $aut_route;

    /**
     * The value for the aut_account field.
     *
     * @var        string
     */
    protected $aut_account;

    /**
     * The value for the aut_datelastedited field.
     *
     * @var        DateTime
     */
    protected $aut_datelastedited;

    /**
     * The value for the aut_editedby field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $aut_editedby;

    /**
     * The value for the aut_serial field.
     *
     * Note: this column has a database default value of: 1
     * @var        int
     */
    protected $aut_serial;

    /**
     * The value for the aut_creditcardvanco field.
     *
     * @var        string
     */
    protected $aut_creditcardvanco;

    /**
     * The value for the aut_accountvanco field.
     *
     * @var        string
     */
    protected $aut_accountvanco;

    /**
     * Flag to prevent endless save loop, if this object is referenced
     * by another object which falls in this transaction.
     *
     * @var boolean
     */
    protected $alreadyInSave = false;

    /**
     * Applies default values to this object.
     * This method should be called from the object's constructor (or
     * equivalent initialization method).
     * @see __construct()
     */
    public function applyDefaultValues()
    {
        $this->aut_famid = 0;
        $this->aut_enablebankdraft = false;
        $this->aut_enablecreditcard = false;
        $this->aut_fyid = 9;
        $this->aut_amount = '0.00';
        $this->aut_interval = 1;
        $this->aut_fund = 0;
        $this->aut_editedby = 0;
        $this->aut_serial = 1;
    }

    /**
     * Initializes internal state of ChurchCRM\Base\AutoPayment object.
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
     * Compares this with another <code>AutoPayment</code> instance.  If
     * <code>obj</code> is an instance of <code>AutoPayment</code>, delegates to
     * <code>equals(AutoPayment)</code>.  Otherwise, returns <code>false</code>.
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
     * @return $this|AutoPayment The current object, for fluid interface
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
     * Get the [aut_id] column value.
     *
     * @return int
     */
    public function getId()
    {
        return $this->aut_id;
    }

    /**
     * Get the [aut_famid] column value.
     *
     * @return int
     */
    public function getFamilyid()
    {
        return $this->aut_famid;
    }

    /**
     * Get the [aut_enablebankdraft] column value.
     *
     * @return boolean
     */
    public function getEnableBankDraft()
    {
        return $this->aut_enablebankdraft;
    }

    /**
     * Get the [aut_enablebankdraft] column value.
     *
     * @return boolean
     */
    public function isEnableBankDraft()
    {
        return $this->getEnableBankDraft();
    }

    /**
     * Get the [aut_enablecreditcard] column value.
     *
     * @return boolean
     */
    public function getEnableCreditCard()
    {
        return $this->aut_enablecreditcard;
    }

    /**
     * Get the [aut_enablecreditcard] column value.
     *
     * @return boolean
     */
    public function isEnableCreditCard()
    {
        return $this->getEnableCreditCard();
    }

    /**
     * Get the [optionally formatted] temporal [aut_nextpaydate] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw DateTime object will be returned.
     *
     * @return string|DateTime Formatted date/time value as string or DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getNextPayDate($format = NULL)
    {
        if ($format === null) {
            return $this->aut_nextpaydate;
        } else {
            return $this->aut_nextpaydate instanceof \DateTimeInterface ? $this->aut_nextpaydate->format($format) : null;
        }
    }

    /**
     * Get the [aut_fyid] column value.
     *
     * @return int
     */
    public function getFyid()
    {
        return $this->aut_fyid;
    }

    /**
     * Get the [aut_amount] column value.
     *
     * @return string
     */
    public function getAmount()
    {
        return $this->aut_amount;
    }

    /**
     * Get the [aut_interval] column value.
     *
     * @return int
     */
    public function getInterval()
    {
        return $this->aut_interval;
    }

    /**
     * Get the [aut_fund] column value.
     *
     * @return int
     */
    public function getFund()
    {
        return $this->aut_fund;
    }

    /**
     * Get the [aut_firstname] column value.
     *
     * @return string
     */
    public function getFirstName()
    {
        return $this->aut_firstname;
    }

    /**
     * Get the [aut_lastname] column value.
     *
     * @return string
     */
    public function getLastName()
    {
        return $this->aut_lastname;
    }

    /**
     * Get the [aut_address1] column value.
     *
     * @return string
     */
    public function getAddress1()
    {
        return $this->aut_address1;
    }

    /**
     * Get the [aut_address2] column value.
     *
     * @return string
     */
    public function getAddress2()
    {
        return $this->aut_address2;
    }

    /**
     * Get the [aut_city] column value.
     *
     * @return string
     */
    public function getCity()
    {
        return $this->aut_city;
    }

    /**
     * Get the [aut_state] column value.
     *
     * @return string
     */
    public function getState()
    {
        return $this->aut_state;
    }

    /**
     * Get the [aut_zip] column value.
     *
     * @return string
     */
    public function getZip()
    {
        return $this->aut_zip;
    }

    /**
     * Get the [aut_country] column value.
     *
     * @return string
     */
    public function getCountry()
    {
        return $this->aut_country;
    }

    /**
     * Get the [aut_phone] column value.
     *
     * @return string
     */
    public function getPhone()
    {
        return $this->aut_phone;
    }

    /**
     * Get the [aut_email] column value.
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->aut_email;
    }

    /**
     * Get the [aut_creditcard] column value.
     *
     * @return string
     */
    public function getCreditCard()
    {
        return $this->aut_creditcard;
    }

    /**
     * Get the [aut_expmonth] column value.
     *
     * @return string
     */
    public function getExpMonth()
    {
        return $this->aut_expmonth;
    }

    /**
     * Get the [aut_expyear] column value.
     *
     * @return string
     */
    public function getExpYear()
    {
        return $this->aut_expyear;
    }

    /**
     * Get the [aut_bankname] column value.
     *
     * @return string
     */
    public function getBankName()
    {
        return $this->aut_bankname;
    }

    /**
     * Get the [aut_route] column value.
     *
     * @return string
     */
    public function getRoute()
    {
        return $this->aut_route;
    }

    /**
     * Get the [aut_account] column value.
     *
     * @return string
     */
    public function getAccount()
    {
        return $this->aut_account;
    }

    /**
     * Get the [optionally formatted] temporal [aut_datelastedited] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw DateTime object will be returned.
     *
     * @return string|DateTime Formatted date/time value as string or DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00 00:00:00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getDateLastEdited($format = NULL)
    {
        if ($format === null) {
            return $this->aut_datelastedited;
        } else {
            return $this->aut_datelastedited instanceof \DateTimeInterface ? $this->aut_datelastedited->format($format) : null;
        }
    }

    /**
     * Get the [aut_editedby] column value.
     *
     * @return int
     */
    public function getEditedby()
    {
        return $this->aut_editedby;
    }

    /**
     * Get the [aut_serial] column value.
     *
     * @return int
     */
    public function getSerial()
    {
        return $this->aut_serial;
    }

    /**
     * Get the [aut_creditcardvanco] column value.
     *
     * @return string
     */
    public function getCreditcardvanco()
    {
        return $this->aut_creditcardvanco;
    }

    /**
     * Get the [aut_accountvanco] column value.
     *
     * @return string
     */
    public function getAccountVanco()
    {
        return $this->aut_accountvanco;
    }

    /**
     * Set the value of [aut_id] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->aut_id !== $v) {
            $this->aut_id = $v;
            $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_ID] = true;
        }

        return $this;
    } // setId()

    /**
     * Set the value of [aut_famid] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setFamilyid($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->aut_famid !== $v) {
            $this->aut_famid = $v;
            $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_FAMID] = true;
        }

        return $this;
    } // setFamilyid()

    /**
     * Sets the value of the [aut_enablebankdraft] column.
     * Non-boolean arguments are converted using the following rules:
     *   * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *   * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     * Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     *
     * @param  boolean|integer|string $v The new value
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setEnableBankDraft($v)
    {
        if ($v !== null) {
            if (is_string($v)) {
                $v = in_array(strtolower($v), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
            } else {
                $v = (boolean) $v;
            }
        }

        if ($this->aut_enablebankdraft !== $v) {
            $this->aut_enablebankdraft = $v;
            $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_ENABLEBANKDRAFT] = true;
        }

        return $this;
    } // setEnableBankDraft()

    /**
     * Sets the value of the [aut_enablecreditcard] column.
     * Non-boolean arguments are converted using the following rules:
     *   * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *   * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     * Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     *
     * @param  boolean|integer|string $v The new value
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setEnableCreditCard($v)
    {
        if ($v !== null) {
            if (is_string($v)) {
                $v = in_array(strtolower($v), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
            } else {
                $v = (boolean) $v;
            }
        }

        if ($this->aut_enablecreditcard !== $v) {
            $this->aut_enablecreditcard = $v;
            $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_ENABLECREDITCARD] = true;
        }

        return $this;
    } // setEnableCreditCard()

    /**
     * Sets the value of [aut_nextpaydate] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTimeInterface value.
     *               Empty strings are treated as NULL.
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setNextPayDate($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->aut_nextpaydate !== null || $dt !== null) {
            if ($this->aut_nextpaydate === null || $dt === null || $dt->format("Y-m-d") !== $this->aut_nextpaydate->format("Y-m-d")) {
                $this->aut_nextpaydate = $dt === null ? null : clone $dt;
                $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_NEXTPAYDATE] = true;
            }
        } // if either are not null

        return $this;
    } // setNextPayDate()

    /**
     * Set the value of [aut_fyid] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setFyid($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->aut_fyid !== $v) {
            $this->aut_fyid = $v;
            $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_FYID] = true;
        }

        return $this;
    } // setFyid()

    /**
     * Set the value of [aut_amount] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setAmount($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->aut_amount !== $v) {
            $this->aut_amount = $v;
            $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_AMOUNT] = true;
        }

        return $this;
    } // setAmount()

    /**
     * Set the value of [aut_interval] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setInterval($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->aut_interval !== $v) {
            $this->aut_interval = $v;
            $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_INTERVAL] = true;
        }

        return $this;
    } // setInterval()

    /**
     * Set the value of [aut_fund] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setFund($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->aut_fund !== $v) {
            $this->aut_fund = $v;
            $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_FUND] = true;
        }

        return $this;
    } // setFund()

    /**
     * Set the value of [aut_firstname] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setFirstName($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->aut_firstname !== $v) {
            $this->aut_firstname = $v;
            $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_FIRSTNAME] = true;
        }

        return $this;
    } // setFirstName()

    /**
     * Set the value of [aut_lastname] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setLastName($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->aut_lastname !== $v) {
            $this->aut_lastname = $v;
            $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_LASTNAME] = true;
        }

        return $this;
    } // setLastName()

    /**
     * Set the value of [aut_address1] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setAddress1($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->aut_address1 !== $v) {
            $this->aut_address1 = $v;
            $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_ADDRESS1] = true;
        }

        return $this;
    } // setAddress1()

    /**
     * Set the value of [aut_address2] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setAddress2($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->aut_address2 !== $v) {
            $this->aut_address2 = $v;
            $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_ADDRESS2] = true;
        }

        return $this;
    } // setAddress2()

    /**
     * Set the value of [aut_city] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setCity($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->aut_city !== $v) {
            $this->aut_city = $v;
            $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_CITY] = true;
        }

        return $this;
    } // setCity()

    /**
     * Set the value of [aut_state] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setState($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->aut_state !== $v) {
            $this->aut_state = $v;
            $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_STATE] = true;
        }

        return $this;
    } // setState()

    /**
     * Set the value of [aut_zip] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setZip($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->aut_zip !== $v) {
            $this->aut_zip = $v;
            $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_ZIP] = true;
        }

        return $this;
    } // setZip()

    /**
     * Set the value of [aut_country] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setCountry($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->aut_country !== $v) {
            $this->aut_country = $v;
            $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_COUNTRY] = true;
        }

        return $this;
    } // setCountry()

    /**
     * Set the value of [aut_phone] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setPhone($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->aut_phone !== $v) {
            $this->aut_phone = $v;
            $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_PHONE] = true;
        }

        return $this;
    } // setPhone()

    /**
     * Set the value of [aut_email] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setEmail($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->aut_email !== $v) {
            $this->aut_email = $v;
            $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_EMAIL] = true;
        }

        return $this;
    } // setEmail()

    /**
     * Set the value of [aut_creditcard] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setCreditCard($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->aut_creditcard !== $v) {
            $this->aut_creditcard = $v;
            $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_CREDITCARD] = true;
        }

        return $this;
    } // setCreditCard()

    /**
     * Set the value of [aut_expmonth] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setExpMonth($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->aut_expmonth !== $v) {
            $this->aut_expmonth = $v;
            $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_EXPMONTH] = true;
        }

        return $this;
    } // setExpMonth()

    /**
     * Set the value of [aut_expyear] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setExpYear($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->aut_expyear !== $v) {
            $this->aut_expyear = $v;
            $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_EXPYEAR] = true;
        }

        return $this;
    } // setExpYear()

    /**
     * Set the value of [aut_bankname] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setBankName($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->aut_bankname !== $v) {
            $this->aut_bankname = $v;
            $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_BANKNAME] = true;
        }

        return $this;
    } // setBankName()

    /**
     * Set the value of [aut_route] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setRoute($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->aut_route !== $v) {
            $this->aut_route = $v;
            $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_ROUTE] = true;
        }

        return $this;
    } // setRoute()

    /**
     * Set the value of [aut_account] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setAccount($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->aut_account !== $v) {
            $this->aut_account = $v;
            $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_ACCOUNT] = true;
        }

        return $this;
    } // setAccount()

    /**
     * Sets the value of [aut_datelastedited] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTimeInterface value.
     *               Empty strings are treated as NULL.
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setDateLastEdited($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->aut_datelastedited !== null || $dt !== null) {
            if ($this->aut_datelastedited === null || $dt === null || $dt->format("Y-m-d H:i:s.u") !== $this->aut_datelastedited->format("Y-m-d H:i:s.u")) {
                $this->aut_datelastedited = $dt === null ? null : clone $dt;
                $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_DATELASTEDITED] = true;
            }
        } // if either are not null

        return $this;
    } // setDateLastEdited()

    /**
     * Set the value of [aut_editedby] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setEditedby($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->aut_editedby !== $v) {
            $this->aut_editedby = $v;
            $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_EDITEDBY] = true;
        }

        return $this;
    } // setEditedby()

    /**
     * Set the value of [aut_serial] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setSerial($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->aut_serial !== $v) {
            $this->aut_serial = $v;
            $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_SERIAL] = true;
        }

        return $this;
    } // setSerial()

    /**
     * Set the value of [aut_creditcardvanco] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setCreditcardvanco($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->aut_creditcardvanco !== $v) {
            $this->aut_creditcardvanco = $v;
            $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_CREDITCARDVANCO] = true;
        }

        return $this;
    } // setCreditcardvanco()

    /**
     * Set the value of [aut_accountvanco] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\AutoPayment The current object (for fluent API support)
     */
    public function setAccountVanco($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->aut_accountvanco !== $v) {
            $this->aut_accountvanco = $v;
            $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_ACCOUNTVANCO] = true;
        }

        return $this;
    } // setAccountVanco()

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
            if ($this->aut_famid !== 0) {
                return false;
            }

            if ($this->aut_enablebankdraft !== false) {
                return false;
            }

            if ($this->aut_enablecreditcard !== false) {
                return false;
            }

            if ($this->aut_fyid !== 9) {
                return false;
            }

            if ($this->aut_amount !== '0.00') {
                return false;
            }

            if ($this->aut_interval !== 1) {
                return false;
            }

            if ($this->aut_fund !== 0) {
                return false;
            }

            if ($this->aut_editedby !== 0) {
                return false;
            }

            if ($this->aut_serial !== 1) {
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

            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : AutoPaymentTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->aut_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : AutoPaymentTableMap::translateFieldName('Familyid', TableMap::TYPE_PHPNAME, $indexType)];
            $this->aut_famid = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : AutoPaymentTableMap::translateFieldName('EnableBankDraft', TableMap::TYPE_PHPNAME, $indexType)];
            $this->aut_enablebankdraft = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : AutoPaymentTableMap::translateFieldName('EnableCreditCard', TableMap::TYPE_PHPNAME, $indexType)];
            $this->aut_enablecreditcard = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : AutoPaymentTableMap::translateFieldName('NextPayDate', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00') {
                $col = null;
            }
            $this->aut_nextpaydate = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : AutoPaymentTableMap::translateFieldName('Fyid', TableMap::TYPE_PHPNAME, $indexType)];
            $this->aut_fyid = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : AutoPaymentTableMap::translateFieldName('Amount', TableMap::TYPE_PHPNAME, $indexType)];
            $this->aut_amount = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : AutoPaymentTableMap::translateFieldName('Interval', TableMap::TYPE_PHPNAME, $indexType)];
            $this->aut_interval = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : AutoPaymentTableMap::translateFieldName('Fund', TableMap::TYPE_PHPNAME, $indexType)];
            $this->aut_fund = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 9 + $startcol : AutoPaymentTableMap::translateFieldName('FirstName', TableMap::TYPE_PHPNAME, $indexType)];
            $this->aut_firstname = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 10 + $startcol : AutoPaymentTableMap::translateFieldName('LastName', TableMap::TYPE_PHPNAME, $indexType)];
            $this->aut_lastname = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 11 + $startcol : AutoPaymentTableMap::translateFieldName('Address1', TableMap::TYPE_PHPNAME, $indexType)];
            $this->aut_address1 = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 12 + $startcol : AutoPaymentTableMap::translateFieldName('Address2', TableMap::TYPE_PHPNAME, $indexType)];
            $this->aut_address2 = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 13 + $startcol : AutoPaymentTableMap::translateFieldName('City', TableMap::TYPE_PHPNAME, $indexType)];
            $this->aut_city = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 14 + $startcol : AutoPaymentTableMap::translateFieldName('State', TableMap::TYPE_PHPNAME, $indexType)];
            $this->aut_state = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 15 + $startcol : AutoPaymentTableMap::translateFieldName('Zip', TableMap::TYPE_PHPNAME, $indexType)];
            $this->aut_zip = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 16 + $startcol : AutoPaymentTableMap::translateFieldName('Country', TableMap::TYPE_PHPNAME, $indexType)];
            $this->aut_country = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 17 + $startcol : AutoPaymentTableMap::translateFieldName('Phone', TableMap::TYPE_PHPNAME, $indexType)];
            $this->aut_phone = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 18 + $startcol : AutoPaymentTableMap::translateFieldName('Email', TableMap::TYPE_PHPNAME, $indexType)];
            $this->aut_email = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 19 + $startcol : AutoPaymentTableMap::translateFieldName('CreditCard', TableMap::TYPE_PHPNAME, $indexType)];
            $this->aut_creditcard = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 20 + $startcol : AutoPaymentTableMap::translateFieldName('ExpMonth', TableMap::TYPE_PHPNAME, $indexType)];
            $this->aut_expmonth = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 21 + $startcol : AutoPaymentTableMap::translateFieldName('ExpYear', TableMap::TYPE_PHPNAME, $indexType)];
            $this->aut_expyear = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 22 + $startcol : AutoPaymentTableMap::translateFieldName('BankName', TableMap::TYPE_PHPNAME, $indexType)];
            $this->aut_bankname = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 23 + $startcol : AutoPaymentTableMap::translateFieldName('Route', TableMap::TYPE_PHPNAME, $indexType)];
            $this->aut_route = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 24 + $startcol : AutoPaymentTableMap::translateFieldName('Account', TableMap::TYPE_PHPNAME, $indexType)];
            $this->aut_account = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 25 + $startcol : AutoPaymentTableMap::translateFieldName('DateLastEdited', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->aut_datelastedited = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 26 + $startcol : AutoPaymentTableMap::translateFieldName('Editedby', TableMap::TYPE_PHPNAME, $indexType)];
            $this->aut_editedby = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 27 + $startcol : AutoPaymentTableMap::translateFieldName('Serial', TableMap::TYPE_PHPNAME, $indexType)];
            $this->aut_serial = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 28 + $startcol : AutoPaymentTableMap::translateFieldName('Creditcardvanco', TableMap::TYPE_PHPNAME, $indexType)];
            $this->aut_creditcardvanco = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 29 + $startcol : AutoPaymentTableMap::translateFieldName('AccountVanco', TableMap::TYPE_PHPNAME, $indexType)];
            $this->aut_accountvanco = (null !== $col) ? (string) $col : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 30; // 30 = AutoPaymentTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException(sprintf('Error populating %s object', '\\ChurchCRM\\AutoPayment'), 0, $e);
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
            $con = Propel::getServiceContainer()->getReadConnection(AutoPaymentTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildAutoPaymentQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see AutoPayment::setDeleted()
     * @see AutoPayment::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(AutoPaymentTableMap::DATABASE_NAME);
        }

        $con->transaction(function () use ($con) {
            $deleteQuery = ChildAutoPaymentQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(AutoPaymentTableMap::DATABASE_NAME);
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
                AutoPaymentTableMap::addInstanceToPool($this);
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

        $this->modifiedColumns[AutoPaymentTableMap::COL_AUT_ID] = true;
        if (null !== $this->aut_id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . AutoPaymentTableMap::COL_AUT_ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_ID)) {
            $modifiedColumns[':p' . $index++]  = 'aut_ID';
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_FAMID)) {
            $modifiedColumns[':p' . $index++]  = 'aut_FamID';
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_ENABLEBANKDRAFT)) {
            $modifiedColumns[':p' . $index++]  = 'aut_EnableBankDraft';
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_ENABLECREDITCARD)) {
            $modifiedColumns[':p' . $index++]  = 'aut_EnableCreditCard';
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_NEXTPAYDATE)) {
            $modifiedColumns[':p' . $index++]  = 'aut_NextPayDate';
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_FYID)) {
            $modifiedColumns[':p' . $index++]  = 'aut_FYID';
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_AMOUNT)) {
            $modifiedColumns[':p' . $index++]  = 'aut_Amount';
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_INTERVAL)) {
            $modifiedColumns[':p' . $index++]  = 'aut_Interval';
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_FUND)) {
            $modifiedColumns[':p' . $index++]  = 'aut_Fund';
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_FIRSTNAME)) {
            $modifiedColumns[':p' . $index++]  = 'aut_FirstName';
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_LASTNAME)) {
            $modifiedColumns[':p' . $index++]  = 'aut_LastName';
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_ADDRESS1)) {
            $modifiedColumns[':p' . $index++]  = 'aut_Address1';
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_ADDRESS2)) {
            $modifiedColumns[':p' . $index++]  = 'aut_Address2';
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_CITY)) {
            $modifiedColumns[':p' . $index++]  = 'aut_City';
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_STATE)) {
            $modifiedColumns[':p' . $index++]  = 'aut_State';
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_ZIP)) {
            $modifiedColumns[':p' . $index++]  = 'aut_Zip';
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_COUNTRY)) {
            $modifiedColumns[':p' . $index++]  = 'aut_Country';
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_PHONE)) {
            $modifiedColumns[':p' . $index++]  = 'aut_Phone';
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_EMAIL)) {
            $modifiedColumns[':p' . $index++]  = 'aut_Email';
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_CREDITCARD)) {
            $modifiedColumns[':p' . $index++]  = 'aut_CreditCard';
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_EXPMONTH)) {
            $modifiedColumns[':p' . $index++]  = 'aut_ExpMonth';
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_EXPYEAR)) {
            $modifiedColumns[':p' . $index++]  = 'aut_ExpYear';
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_BANKNAME)) {
            $modifiedColumns[':p' . $index++]  = 'aut_BankName';
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_ROUTE)) {
            $modifiedColumns[':p' . $index++]  = 'aut_Route';
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_ACCOUNT)) {
            $modifiedColumns[':p' . $index++]  = 'aut_Account';
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_DATELASTEDITED)) {
            $modifiedColumns[':p' . $index++]  = 'aut_DateLastEdited';
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_EDITEDBY)) {
            $modifiedColumns[':p' . $index++]  = 'aut_EditedBy';
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_SERIAL)) {
            $modifiedColumns[':p' . $index++]  = 'aut_Serial';
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_CREDITCARDVANCO)) {
            $modifiedColumns[':p' . $index++]  = 'aut_CreditCardVanco';
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_ACCOUNTVANCO)) {
            $modifiedColumns[':p' . $index++]  = 'aut_AccountVanco';
        }

        $sql = sprintf(
            'INSERT INTO autopayment_aut (%s) VALUES (%s)',
            implode(', ', $modifiedColumns),
            implode(', ', array_keys($modifiedColumns))
        );

        try {
            $stmt = $con->prepare($sql);
            foreach ($modifiedColumns as $identifier => $columnName) {
                switch ($columnName) {
                    case 'aut_ID':
                        $stmt->bindValue($identifier, $this->aut_id, PDO::PARAM_INT);
                        break;
                    case 'aut_FamID':
                        $stmt->bindValue($identifier, $this->aut_famid, PDO::PARAM_INT);
                        break;
                    case 'aut_EnableBankDraft':
                        $stmt->bindValue($identifier, (int) $this->aut_enablebankdraft, PDO::PARAM_INT);
                        break;
                    case 'aut_EnableCreditCard':
                        $stmt->bindValue($identifier, (int) $this->aut_enablecreditcard, PDO::PARAM_INT);
                        break;
                    case 'aut_NextPayDate':
                        $stmt->bindValue($identifier, $this->aut_nextpaydate ? $this->aut_nextpaydate->format("Y-m-d H:i:s.u") : null, PDO::PARAM_STR);
                        break;
                    case 'aut_FYID':
                        $stmt->bindValue($identifier, $this->aut_fyid, PDO::PARAM_INT);
                        break;
                    case 'aut_Amount':
                        $stmt->bindValue($identifier, $this->aut_amount, PDO::PARAM_STR);
                        break;
                    case 'aut_Interval':
                        $stmt->bindValue($identifier, $this->aut_interval, PDO::PARAM_INT);
                        break;
                    case 'aut_Fund':
                        $stmt->bindValue($identifier, $this->aut_fund, PDO::PARAM_INT);
                        break;
                    case 'aut_FirstName':
                        $stmt->bindValue($identifier, $this->aut_firstname, PDO::PARAM_STR);
                        break;
                    case 'aut_LastName':
                        $stmt->bindValue($identifier, $this->aut_lastname, PDO::PARAM_STR);
                        break;
                    case 'aut_Address1':
                        $stmt->bindValue($identifier, $this->aut_address1, PDO::PARAM_STR);
                        break;
                    case 'aut_Address2':
                        $stmt->bindValue($identifier, $this->aut_address2, PDO::PARAM_STR);
                        break;
                    case 'aut_City':
                        $stmt->bindValue($identifier, $this->aut_city, PDO::PARAM_STR);
                        break;
                    case 'aut_State':
                        $stmt->bindValue($identifier, $this->aut_state, PDO::PARAM_STR);
                        break;
                    case 'aut_Zip':
                        $stmt->bindValue($identifier, $this->aut_zip, PDO::PARAM_STR);
                        break;
                    case 'aut_Country':
                        $stmt->bindValue($identifier, $this->aut_country, PDO::PARAM_STR);
                        break;
                    case 'aut_Phone':
                        $stmt->bindValue($identifier, $this->aut_phone, PDO::PARAM_STR);
                        break;
                    case 'aut_Email':
                        $stmt->bindValue($identifier, $this->aut_email, PDO::PARAM_STR);
                        break;
                    case 'aut_CreditCard':
                        $stmt->bindValue($identifier, $this->aut_creditcard, PDO::PARAM_STR);
                        break;
                    case 'aut_ExpMonth':
                        $stmt->bindValue($identifier, $this->aut_expmonth, PDO::PARAM_STR);
                        break;
                    case 'aut_ExpYear':
                        $stmt->bindValue($identifier, $this->aut_expyear, PDO::PARAM_STR);
                        break;
                    case 'aut_BankName':
                        $stmt->bindValue($identifier, $this->aut_bankname, PDO::PARAM_STR);
                        break;
                    case 'aut_Route':
                        $stmt->bindValue($identifier, $this->aut_route, PDO::PARAM_STR);
                        break;
                    case 'aut_Account':
                        $stmt->bindValue($identifier, $this->aut_account, PDO::PARAM_STR);
                        break;
                    case 'aut_DateLastEdited':
                        $stmt->bindValue($identifier, $this->aut_datelastedited ? $this->aut_datelastedited->format("Y-m-d H:i:s.u") : null, PDO::PARAM_STR);
                        break;
                    case 'aut_EditedBy':
                        $stmt->bindValue($identifier, $this->aut_editedby, PDO::PARAM_INT);
                        break;
                    case 'aut_Serial':
                        $stmt->bindValue($identifier, $this->aut_serial, PDO::PARAM_INT);
                        break;
                    case 'aut_CreditCardVanco':
                        $stmt->bindValue($identifier, $this->aut_creditcardvanco, PDO::PARAM_STR);
                        break;
                    case 'aut_AccountVanco':
                        $stmt->bindValue($identifier, $this->aut_accountvanco, PDO::PARAM_STR);
                        break;
                }
            }
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute INSERT statement [%s]', $sql), 0, $e);
        }

        try {
            $pk = $con->lastInsertId();
        } catch (Exception $e) {
            throw new PropelException('Unable to get autoincrement id.', 0, $e);
        }
        $this->setId($pk);

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
        $pos = AutoPaymentTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getId();
                break;
            case 1:
                return $this->getFamilyid();
                break;
            case 2:
                return $this->getEnableBankDraft();
                break;
            case 3:
                return $this->getEnableCreditCard();
                break;
            case 4:
                return $this->getNextPayDate();
                break;
            case 5:
                return $this->getFyid();
                break;
            case 6:
                return $this->getAmount();
                break;
            case 7:
                return $this->getInterval();
                break;
            case 8:
                return $this->getFund();
                break;
            case 9:
                return $this->getFirstName();
                break;
            case 10:
                return $this->getLastName();
                break;
            case 11:
                return $this->getAddress1();
                break;
            case 12:
                return $this->getAddress2();
                break;
            case 13:
                return $this->getCity();
                break;
            case 14:
                return $this->getState();
                break;
            case 15:
                return $this->getZip();
                break;
            case 16:
                return $this->getCountry();
                break;
            case 17:
                return $this->getPhone();
                break;
            case 18:
                return $this->getEmail();
                break;
            case 19:
                return $this->getCreditCard();
                break;
            case 20:
                return $this->getExpMonth();
                break;
            case 21:
                return $this->getExpYear();
                break;
            case 22:
                return $this->getBankName();
                break;
            case 23:
                return $this->getRoute();
                break;
            case 24:
                return $this->getAccount();
                break;
            case 25:
                return $this->getDateLastEdited();
                break;
            case 26:
                return $this->getEditedby();
                break;
            case 27:
                return $this->getSerial();
                break;
            case 28:
                return $this->getCreditcardvanco();
                break;
            case 29:
                return $this->getAccountVanco();
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
     *
     * @return array an associative array containing the field names (as keys) and field values
     */
    public function toArray($keyType = TableMap::TYPE_PHPNAME, $includeLazyLoadColumns = true, $alreadyDumpedObjects = array())
    {

        if (isset($alreadyDumpedObjects['AutoPayment'][$this->hashCode()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['AutoPayment'][$this->hashCode()] = true;
        $keys = AutoPaymentTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getFamilyid(),
            $keys[2] => $this->getEnableBankDraft(),
            $keys[3] => $this->getEnableCreditCard(),
            $keys[4] => $this->getNextPayDate(),
            $keys[5] => $this->getFyid(),
            $keys[6] => $this->getAmount(),
            $keys[7] => $this->getInterval(),
            $keys[8] => $this->getFund(),
            $keys[9] => $this->getFirstName(),
            $keys[10] => $this->getLastName(),
            $keys[11] => $this->getAddress1(),
            $keys[12] => $this->getAddress2(),
            $keys[13] => $this->getCity(),
            $keys[14] => $this->getState(),
            $keys[15] => $this->getZip(),
            $keys[16] => $this->getCountry(),
            $keys[17] => $this->getPhone(),
            $keys[18] => $this->getEmail(),
            $keys[19] => $this->getCreditCard(),
            $keys[20] => $this->getExpMonth(),
            $keys[21] => $this->getExpYear(),
            $keys[22] => $this->getBankName(),
            $keys[23] => $this->getRoute(),
            $keys[24] => $this->getAccount(),
            $keys[25] => $this->getDateLastEdited(),
            $keys[26] => $this->getEditedby(),
            $keys[27] => $this->getSerial(),
            $keys[28] => $this->getCreditcardvanco(),
            $keys[29] => $this->getAccountVanco(),
        );
        if ($result[$keys[4]] instanceof \DateTime) {
            $result[$keys[4]] = $result[$keys[4]]->format('c');
        }

        if ($result[$keys[25]] instanceof \DateTime) {
            $result[$keys[25]] = $result[$keys[25]]->format('c');
        }

        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
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
     * @return $this|\ChurchCRM\AutoPayment
     */
    public function setByName($name, $value, $type = TableMap::TYPE_PHPNAME)
    {
        $pos = AutoPaymentTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

        return $this->setByPosition($pos, $value);
    }

    /**
     * Sets a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param  int $pos position in xml schema
     * @param  mixed $value field value
     * @return $this|\ChurchCRM\AutoPayment
     */
    public function setByPosition($pos, $value)
    {
        switch ($pos) {
            case 0:
                $this->setId($value);
                break;
            case 1:
                $this->setFamilyid($value);
                break;
            case 2:
                $this->setEnableBankDraft($value);
                break;
            case 3:
                $this->setEnableCreditCard($value);
                break;
            case 4:
                $this->setNextPayDate($value);
                break;
            case 5:
                $this->setFyid($value);
                break;
            case 6:
                $this->setAmount($value);
                break;
            case 7:
                $this->setInterval($value);
                break;
            case 8:
                $this->setFund($value);
                break;
            case 9:
                $this->setFirstName($value);
                break;
            case 10:
                $this->setLastName($value);
                break;
            case 11:
                $this->setAddress1($value);
                break;
            case 12:
                $this->setAddress2($value);
                break;
            case 13:
                $this->setCity($value);
                break;
            case 14:
                $this->setState($value);
                break;
            case 15:
                $this->setZip($value);
                break;
            case 16:
                $this->setCountry($value);
                break;
            case 17:
                $this->setPhone($value);
                break;
            case 18:
                $this->setEmail($value);
                break;
            case 19:
                $this->setCreditCard($value);
                break;
            case 20:
                $this->setExpMonth($value);
                break;
            case 21:
                $this->setExpYear($value);
                break;
            case 22:
                $this->setBankName($value);
                break;
            case 23:
                $this->setRoute($value);
                break;
            case 24:
                $this->setAccount($value);
                break;
            case 25:
                $this->setDateLastEdited($value);
                break;
            case 26:
                $this->setEditedby($value);
                break;
            case 27:
                $this->setSerial($value);
                break;
            case 28:
                $this->setCreditcardvanco($value);
                break;
            case 29:
                $this->setAccountVanco($value);
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
        $keys = AutoPaymentTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) {
            $this->setId($arr[$keys[0]]);
        }
        if (array_key_exists($keys[1], $arr)) {
            $this->setFamilyid($arr[$keys[1]]);
        }
        if (array_key_exists($keys[2], $arr)) {
            $this->setEnableBankDraft($arr[$keys[2]]);
        }
        if (array_key_exists($keys[3], $arr)) {
            $this->setEnableCreditCard($arr[$keys[3]]);
        }
        if (array_key_exists($keys[4], $arr)) {
            $this->setNextPayDate($arr[$keys[4]]);
        }
        if (array_key_exists($keys[5], $arr)) {
            $this->setFyid($arr[$keys[5]]);
        }
        if (array_key_exists($keys[6], $arr)) {
            $this->setAmount($arr[$keys[6]]);
        }
        if (array_key_exists($keys[7], $arr)) {
            $this->setInterval($arr[$keys[7]]);
        }
        if (array_key_exists($keys[8], $arr)) {
            $this->setFund($arr[$keys[8]]);
        }
        if (array_key_exists($keys[9], $arr)) {
            $this->setFirstName($arr[$keys[9]]);
        }
        if (array_key_exists($keys[10], $arr)) {
            $this->setLastName($arr[$keys[10]]);
        }
        if (array_key_exists($keys[11], $arr)) {
            $this->setAddress1($arr[$keys[11]]);
        }
        if (array_key_exists($keys[12], $arr)) {
            $this->setAddress2($arr[$keys[12]]);
        }
        if (array_key_exists($keys[13], $arr)) {
            $this->setCity($arr[$keys[13]]);
        }
        if (array_key_exists($keys[14], $arr)) {
            $this->setState($arr[$keys[14]]);
        }
        if (array_key_exists($keys[15], $arr)) {
            $this->setZip($arr[$keys[15]]);
        }
        if (array_key_exists($keys[16], $arr)) {
            $this->setCountry($arr[$keys[16]]);
        }
        if (array_key_exists($keys[17], $arr)) {
            $this->setPhone($arr[$keys[17]]);
        }
        if (array_key_exists($keys[18], $arr)) {
            $this->setEmail($arr[$keys[18]]);
        }
        if (array_key_exists($keys[19], $arr)) {
            $this->setCreditCard($arr[$keys[19]]);
        }
        if (array_key_exists($keys[20], $arr)) {
            $this->setExpMonth($arr[$keys[20]]);
        }
        if (array_key_exists($keys[21], $arr)) {
            $this->setExpYear($arr[$keys[21]]);
        }
        if (array_key_exists($keys[22], $arr)) {
            $this->setBankName($arr[$keys[22]]);
        }
        if (array_key_exists($keys[23], $arr)) {
            $this->setRoute($arr[$keys[23]]);
        }
        if (array_key_exists($keys[24], $arr)) {
            $this->setAccount($arr[$keys[24]]);
        }
        if (array_key_exists($keys[25], $arr)) {
            $this->setDateLastEdited($arr[$keys[25]]);
        }
        if (array_key_exists($keys[26], $arr)) {
            $this->setEditedby($arr[$keys[26]]);
        }
        if (array_key_exists($keys[27], $arr)) {
            $this->setSerial($arr[$keys[27]]);
        }
        if (array_key_exists($keys[28], $arr)) {
            $this->setCreditcardvanco($arr[$keys[28]]);
        }
        if (array_key_exists($keys[29], $arr)) {
            $this->setAccountVanco($arr[$keys[29]]);
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
     * @return $this|\ChurchCRM\AutoPayment The current object, for fluid interface
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
        $criteria = new Criteria(AutoPaymentTableMap::DATABASE_NAME);

        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_ID)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_ID, $this->aut_id);
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_FAMID)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_FAMID, $this->aut_famid);
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_ENABLEBANKDRAFT)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_ENABLEBANKDRAFT, $this->aut_enablebankdraft);
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_ENABLECREDITCARD)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_ENABLECREDITCARD, $this->aut_enablecreditcard);
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_NEXTPAYDATE)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_NEXTPAYDATE, $this->aut_nextpaydate);
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_FYID)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_FYID, $this->aut_fyid);
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_AMOUNT)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_AMOUNT, $this->aut_amount);
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_INTERVAL)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_INTERVAL, $this->aut_interval);
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_FUND)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_FUND, $this->aut_fund);
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_FIRSTNAME)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_FIRSTNAME, $this->aut_firstname);
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_LASTNAME)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_LASTNAME, $this->aut_lastname);
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_ADDRESS1)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_ADDRESS1, $this->aut_address1);
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_ADDRESS2)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_ADDRESS2, $this->aut_address2);
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_CITY)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_CITY, $this->aut_city);
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_STATE)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_STATE, $this->aut_state);
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_ZIP)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_ZIP, $this->aut_zip);
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_COUNTRY)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_COUNTRY, $this->aut_country);
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_PHONE)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_PHONE, $this->aut_phone);
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_EMAIL)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_EMAIL, $this->aut_email);
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_CREDITCARD)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_CREDITCARD, $this->aut_creditcard);
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_EXPMONTH)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_EXPMONTH, $this->aut_expmonth);
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_EXPYEAR)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_EXPYEAR, $this->aut_expyear);
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_BANKNAME)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_BANKNAME, $this->aut_bankname);
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_ROUTE)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_ROUTE, $this->aut_route);
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_ACCOUNT)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_ACCOUNT, $this->aut_account);
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_DATELASTEDITED)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_DATELASTEDITED, $this->aut_datelastedited);
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_EDITEDBY)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_EDITEDBY, $this->aut_editedby);
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_SERIAL)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_SERIAL, $this->aut_serial);
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_CREDITCARDVANCO)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_CREDITCARDVANCO, $this->aut_creditcardvanco);
        }
        if ($this->isColumnModified(AutoPaymentTableMap::COL_AUT_ACCOUNTVANCO)) {
            $criteria->add(AutoPaymentTableMap::COL_AUT_ACCOUNTVANCO, $this->aut_accountvanco);
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
        $criteria = ChildAutoPaymentQuery::create();
        $criteria->add(AutoPaymentTableMap::COL_AUT_ID, $this->aut_id);

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
        $validPk = null !== $this->getId();

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
        return $this->getId();
    }

    /**
     * Generic method to set the primary key (aut_id column).
     *
     * @param       int $key Primary key.
     * @return void
     */
    public function setPrimaryKey($key)
    {
        $this->setId($key);
    }

    /**
     * Returns true if the primary key for this object is null.
     * @return boolean
     */
    public function isPrimaryKeyNull()
    {
        return null === $this->getId();
    }

    /**
     * Sets contents of passed object to values from current object.
     *
     * If desired, this method can also make copies of all associated (fkey referrers)
     * objects.
     *
     * @param      object $copyObj An object of \ChurchCRM\AutoPayment (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setFamilyid($this->getFamilyid());
        $copyObj->setEnableBankDraft($this->getEnableBankDraft());
        $copyObj->setEnableCreditCard($this->getEnableCreditCard());
        $copyObj->setNextPayDate($this->getNextPayDate());
        $copyObj->setFyid($this->getFyid());
        $copyObj->setAmount($this->getAmount());
        $copyObj->setInterval($this->getInterval());
        $copyObj->setFund($this->getFund());
        $copyObj->setFirstName($this->getFirstName());
        $copyObj->setLastName($this->getLastName());
        $copyObj->setAddress1($this->getAddress1());
        $copyObj->setAddress2($this->getAddress2());
        $copyObj->setCity($this->getCity());
        $copyObj->setState($this->getState());
        $copyObj->setZip($this->getZip());
        $copyObj->setCountry($this->getCountry());
        $copyObj->setPhone($this->getPhone());
        $copyObj->setEmail($this->getEmail());
        $copyObj->setCreditCard($this->getCreditCard());
        $copyObj->setExpMonth($this->getExpMonth());
        $copyObj->setExpYear($this->getExpYear());
        $copyObj->setBankName($this->getBankName());
        $copyObj->setRoute($this->getRoute());
        $copyObj->setAccount($this->getAccount());
        $copyObj->setDateLastEdited($this->getDateLastEdited());
        $copyObj->setEditedby($this->getEditedby());
        $copyObj->setSerial($this->getSerial());
        $copyObj->setCreditcardvanco($this->getCreditcardvanco());
        $copyObj->setAccountVanco($this->getAccountVanco());
        if ($makeNew) {
            $copyObj->setNew(true);
            $copyObj->setId(NULL); // this is a auto-increment column, so set to default value
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
     * @return \ChurchCRM\AutoPayment Clone of current object.
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
     * Clears the current object, sets all attributes to their default values and removes
     * outgoing references as well as back-references (from other objects to this one. Results probably in a database
     * change of those foreign objects when you call `save` there).
     */
    public function clear()
    {
        $this->aut_id = null;
        $this->aut_famid = null;
        $this->aut_enablebankdraft = null;
        $this->aut_enablecreditcard = null;
        $this->aut_nextpaydate = null;
        $this->aut_fyid = null;
        $this->aut_amount = null;
        $this->aut_interval = null;
        $this->aut_fund = null;
        $this->aut_firstname = null;
        $this->aut_lastname = null;
        $this->aut_address1 = null;
        $this->aut_address2 = null;
        $this->aut_city = null;
        $this->aut_state = null;
        $this->aut_zip = null;
        $this->aut_country = null;
        $this->aut_phone = null;
        $this->aut_email = null;
        $this->aut_creditcard = null;
        $this->aut_expmonth = null;
        $this->aut_expyear = null;
        $this->aut_bankname = null;
        $this->aut_route = null;
        $this->aut_account = null;
        $this->aut_datelastedited = null;
        $this->aut_editedby = null;
        $this->aut_serial = null;
        $this->aut_creditcardvanco = null;
        $this->aut_accountvanco = null;
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
        } // if ($deep)

    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(AutoPaymentTableMap::DEFAULT_STRING_FORMAT);
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
