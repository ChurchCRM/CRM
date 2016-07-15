<?php

namespace ChurchCRM\Base;

use \DateTime;
use \Exception;
use \PDO;
use ChurchCRM\Deposit as ChildDeposit;
use ChurchCRM\DepositQuery as ChildDepositQuery;
use ChurchCRM\DonationFund as ChildDonationFund;
use ChurchCRM\DonationFundQuery as ChildDonationFundQuery;
use ChurchCRM\Family as ChildFamily;
use ChurchCRM\FamilyQuery as ChildFamilyQuery;
use ChurchCRM\PledgeQuery as ChildPledgeQuery;
use ChurchCRM\Map\PledgeTableMap;
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
 * Base class that represents a row from the 'pledge_plg' table.
 *
 *
 *
 * @package    propel.generator.ChurchCRM.Base
 */
abstract class Pledge implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\ChurchCRM\\Map\\PledgeTableMap';


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
     * The value for the plg_plgid field.
     *
     * @var        int
     */
    protected $plg_plgid;

    /**
     * The value for the plg_famid field.
     *
     * @var        int
     */
    protected $plg_famid;

    /**
     * The value for the plg_fyid field.
     *
     * @var        int
     */
    protected $plg_fyid;

    /**
     * The value for the plg_date field.
     *
     * @var        DateTime
     */
    protected $plg_date;

    /**
     * The value for the plg_amount field.
     *
     * @var        string
     */
    protected $plg_amount;

    /**
     * The value for the plg_schedule field.
     *
     * @var        string
     */
    protected $plg_schedule;

    /**
     * The value for the plg_method field.
     *
     * @var        string
     */
    protected $plg_method;

    /**
     * The value for the plg_comment field.
     *
     * @var        string
     */
    protected $plg_comment;

    /**
     * The value for the plg_datelastedited field.
     *
     * Note: this column has a database default value of: NULL
     * @var        DateTime
     */
    protected $plg_datelastedited;

    /**
     * The value for the plg_editedby field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $plg_editedby;

    /**
     * The value for the plg_pledgeorpayment field.
     *
     * Note: this column has a database default value of: 'Pledge'
     * @var        string
     */
    protected $plg_pledgeorpayment;

    /**
     * The value for the plg_fundid field.
     *
     * @var        int
     */
    protected $plg_fundid;

    /**
     * The value for the plg_depid field.
     *
     * @var        int
     */
    protected $plg_depid;

    /**
     * The value for the plg_checkno field.
     *
     * @var        string
     */
    protected $plg_checkno;

    /**
     * The value for the plg_problem field.
     *
     * @var        boolean
     */
    protected $plg_problem;

    /**
     * The value for the plg_scanstring field.
     *
     * @var        string
     */
    protected $plg_scanstring;

    /**
     * The value for the plg_aut_id field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $plg_aut_id;

    /**
     * The value for the plg_aut_cleared field.
     *
     * Note: this column has a database default value of: false
     * @var        boolean
     */
    protected $plg_aut_cleared;

    /**
     * The value for the plg_aut_resultid field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $plg_aut_resultid;

    /**
     * The value for the plg_nondeductible field.
     *
     * @var        string
     */
    protected $plg_nondeductible;

    /**
     * The value for the plg_groupkey field.
     *
     * @var        string
     */
    protected $plg_groupkey;

    /**
     * @var        ChildDeposit
     */
    protected $aDeposit;

    /**
     * @var        ChildDonationFund
     */
    protected $aDonationFund;

    /**
     * @var        ChildFamily
     */
    protected $aFamily;

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
        $this->plg_datelastedited = PropelDateTime::newInstance(NULL, null, 'DateTime');
        $this->plg_editedby = 0;
        $this->plg_pledgeorpayment = 'Pledge';
        $this->plg_aut_id = 0;
        $this->plg_aut_cleared = false;
        $this->plg_aut_resultid = 0;
    }

    /**
     * Initializes internal state of ChurchCRM\Base\Pledge object.
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
     * Compares this with another <code>Pledge</code> instance.  If
     * <code>obj</code> is an instance of <code>Pledge</code>, delegates to
     * <code>equals(Pledge)</code>.  Otherwise, returns <code>false</code>.
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
     * @return $this|Pledge The current object, for fluid interface
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
     * Get the [plg_plgid] column value.
     *
     * @return int
     */
    public function getId()
    {
        return $this->plg_plgid;
    }

    /**
     * Get the [plg_famid] column value.
     *
     * @return int
     */
    public function getFamId()
    {
        return $this->plg_famid;
    }

    /**
     * Get the [plg_fyid] column value.
     *
     * @return int
     */
    public function getFyid()
    {
        return $this->plg_fyid;
    }

    /**
     * Get the [optionally formatted] temporal [plg_date] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw DateTime object will be returned.
     *
     * @return string|DateTime Formatted date/time value as string or DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getDate($format = NULL)
    {
        if ($format === null) {
            return $this->plg_date;
        } else {
            return $this->plg_date instanceof \DateTimeInterface ? $this->plg_date->format($format) : null;
        }
    }

    /**
     * Get the [plg_amount] column value.
     *
     * @return string
     */
    public function getAmount()
    {
        return $this->plg_amount;
    }

    /**
     * Get the [plg_schedule] column value.
     *
     * @return string
     */
    public function getSchedule()
    {
        return $this->plg_schedule;
    }

    /**
     * Get the [plg_method] column value.
     *
     * @return string
     */
    public function getMethod()
    {
        return $this->plg_method;
    }

    /**
     * Get the [plg_comment] column value.
     *
     * @return string
     */
    public function getComment()
    {
        return $this->plg_comment;
    }

    /**
     * Get the [optionally formatted] temporal [plg_datelastedited] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw DateTime object will be returned.
     *
     * @return string|DateTime Formatted date/time value as string or DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getDatelastedited($format = NULL)
    {
        if ($format === null) {
            return $this->plg_datelastedited;
        } else {
            return $this->plg_datelastedited instanceof \DateTimeInterface ? $this->plg_datelastedited->format($format) : null;
        }
    }

    /**
     * Get the [plg_editedby] column value.
     *
     * @return int
     */
    public function getEditedby()
    {
        return $this->plg_editedby;
    }

    /**
     * Get the [plg_pledgeorpayment] column value.
     *
     * @return string
     */
    public function getPledgeorpayment()
    {
        return $this->plg_pledgeorpayment;
    }

    /**
     * Get the [plg_fundid] column value.
     *
     * @return int
     */
    public function getFundid()
    {
        return $this->plg_fundid;
    }

    /**
     * Get the [plg_depid] column value.
     *
     * @return int
     */
    public function getDepid()
    {
        return $this->plg_depid;
    }

    /**
     * Get the [plg_checkno] column value.
     *
     * @return string
     */
    public function getCheckno()
    {
        return $this->plg_checkno;
    }

    /**
     * Get the [plg_problem] column value.
     *
     * @return boolean
     */
    public function getProblem()
    {
        return $this->plg_problem;
    }

    /**
     * Get the [plg_problem] column value.
     *
     * @return boolean
     */
    public function isProblem()
    {
        return $this->getProblem();
    }

    /**
     * Get the [plg_scanstring] column value.
     *
     * @return string
     */
    public function getScanstring()
    {
        return $this->plg_scanstring;
    }

    /**
     * Get the [plg_aut_id] column value.
     *
     * @return int
     */
    public function getAutId()
    {
        return $this->plg_aut_id;
    }

    /**
     * Get the [plg_aut_cleared] column value.
     *
     * @return boolean
     */
    public function getAutCleared()
    {
        return $this->plg_aut_cleared;
    }

    /**
     * Get the [plg_aut_cleared] column value.
     *
     * @return boolean
     */
    public function isAutCleared()
    {
        return $this->getAutCleared();
    }

    /**
     * Get the [plg_aut_resultid] column value.
     *
     * @return int
     */
    public function getAutResultid()
    {
        return $this->plg_aut_resultid;
    }

    /**
     * Get the [plg_nondeductible] column value.
     *
     * @return string
     */
    public function getNondeductible()
    {
        return $this->plg_nondeductible;
    }

    /**
     * Get the [plg_groupkey] column value.
     *
     * @return string
     */
    public function getGroupkey()
    {
        return $this->plg_groupkey;
    }

    /**
     * Set the value of [plg_plgid] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Pledge The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->plg_plgid !== $v) {
            $this->plg_plgid = $v;
            $this->modifiedColumns[PledgeTableMap::COL_PLG_PLGID] = true;
        }

        return $this;
    } // setId()

    /**
     * Set the value of [plg_famid] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Pledge The current object (for fluent API support)
     */
    public function setFamId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->plg_famid !== $v) {
            $this->plg_famid = $v;
            $this->modifiedColumns[PledgeTableMap::COL_PLG_FAMID] = true;
        }

        if ($this->aFamily !== null && $this->aFamily->getId() !== $v) {
            $this->aFamily = null;
        }

        return $this;
    } // setFamId()

    /**
     * Set the value of [plg_fyid] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Pledge The current object (for fluent API support)
     */
    public function setFyid($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->plg_fyid !== $v) {
            $this->plg_fyid = $v;
            $this->modifiedColumns[PledgeTableMap::COL_PLG_FYID] = true;
        }

        return $this;
    } // setFyid()

    /**
     * Sets the value of [plg_date] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTimeInterface value.
     *               Empty strings are treated as NULL.
     * @return $this|\ChurchCRM\Pledge The current object (for fluent API support)
     */
    public function setDate($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->plg_date !== null || $dt !== null) {
            if ($this->plg_date === null || $dt === null || $dt->format("Y-m-d") !== $this->plg_date->format("Y-m-d")) {
                $this->plg_date = $dt === null ? null : clone $dt;
                $this->modifiedColumns[PledgeTableMap::COL_PLG_DATE] = true;
            }
        } // if either are not null

        return $this;
    } // setDate()

    /**
     * Set the value of [plg_amount] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Pledge The current object (for fluent API support)
     */
    public function setAmount($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->plg_amount !== $v) {
            $this->plg_amount = $v;
            $this->modifiedColumns[PledgeTableMap::COL_PLG_AMOUNT] = true;
        }

        return $this;
    } // setAmount()

    /**
     * Set the value of [plg_schedule] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Pledge The current object (for fluent API support)
     */
    public function setSchedule($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->plg_schedule !== $v) {
            $this->plg_schedule = $v;
            $this->modifiedColumns[PledgeTableMap::COL_PLG_SCHEDULE] = true;
        }

        return $this;
    } // setSchedule()

    /**
     * Set the value of [plg_method] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Pledge The current object (for fluent API support)
     */
    public function setMethod($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->plg_method !== $v) {
            $this->plg_method = $v;
            $this->modifiedColumns[PledgeTableMap::COL_PLG_METHOD] = true;
        }

        return $this;
    } // setMethod()

    /**
     * Set the value of [plg_comment] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Pledge The current object (for fluent API support)
     */
    public function setComment($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->plg_comment !== $v) {
            $this->plg_comment = $v;
            $this->modifiedColumns[PledgeTableMap::COL_PLG_COMMENT] = true;
        }

        return $this;
    } // setComment()

    /**
     * Sets the value of [plg_datelastedited] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTimeInterface value.
     *               Empty strings are treated as NULL.
     * @return $this|\ChurchCRM\Pledge The current object (for fluent API support)
     */
    public function setDatelastedited($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->plg_datelastedited !== null || $dt !== null) {
            if ( ($dt != $this->plg_datelastedited) // normalized values don't match
                || ($dt->format('Y-m-d') === NULL) // or the entered value matches the default
                 ) {
                $this->plg_datelastedited = $dt === null ? null : clone $dt;
                $this->modifiedColumns[PledgeTableMap::COL_PLG_DATELASTEDITED] = true;
            }
        } // if either are not null

        return $this;
    } // setDatelastedited()

    /**
     * Set the value of [plg_editedby] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Pledge The current object (for fluent API support)
     */
    public function setEditedby($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->plg_editedby !== $v) {
            $this->plg_editedby = $v;
            $this->modifiedColumns[PledgeTableMap::COL_PLG_EDITEDBY] = true;
        }

        return $this;
    } // setEditedby()

    /**
     * Set the value of [plg_pledgeorpayment] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Pledge The current object (for fluent API support)
     */
    public function setPledgeorpayment($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->plg_pledgeorpayment !== $v) {
            $this->plg_pledgeorpayment = $v;
            $this->modifiedColumns[PledgeTableMap::COL_PLG_PLEDGEORPAYMENT] = true;
        }

        return $this;
    } // setPledgeorpayment()

    /**
     * Set the value of [plg_fundid] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Pledge The current object (for fluent API support)
     */
    public function setFundid($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->plg_fundid !== $v) {
            $this->plg_fundid = $v;
            $this->modifiedColumns[PledgeTableMap::COL_PLG_FUNDID] = true;
        }

        if ($this->aDonationFund !== null && $this->aDonationFund->getId() !== $v) {
            $this->aDonationFund = null;
        }

        return $this;
    } // setFundid()

    /**
     * Set the value of [plg_depid] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Pledge The current object (for fluent API support)
     */
    public function setDepid($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->plg_depid !== $v) {
            $this->plg_depid = $v;
            $this->modifiedColumns[PledgeTableMap::COL_PLG_DEPID] = true;
        }

        if ($this->aDeposit !== null && $this->aDeposit->getId() !== $v) {
            $this->aDeposit = null;
        }

        return $this;
    } // setDepid()

    /**
     * Set the value of [plg_checkno] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Pledge The current object (for fluent API support)
     */
    public function setCheckno($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->plg_checkno !== $v) {
            $this->plg_checkno = $v;
            $this->modifiedColumns[PledgeTableMap::COL_PLG_CHECKNO] = true;
        }

        return $this;
    } // setCheckno()

    /**
     * Sets the value of the [plg_problem] column.
     * Non-boolean arguments are converted using the following rules:
     *   * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *   * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     * Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     *
     * @param  boolean|integer|string $v The new value
     * @return $this|\ChurchCRM\Pledge The current object (for fluent API support)
     */
    public function setProblem($v)
    {
        if ($v !== null) {
            if (is_string($v)) {
                $v = in_array(strtolower($v), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
            } else {
                $v = (boolean) $v;
            }
        }

        if ($this->plg_problem !== $v) {
            $this->plg_problem = $v;
            $this->modifiedColumns[PledgeTableMap::COL_PLG_PROBLEM] = true;
        }

        return $this;
    } // setProblem()

    /**
     * Set the value of [plg_scanstring] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Pledge The current object (for fluent API support)
     */
    public function setScanstring($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->plg_scanstring !== $v) {
            $this->plg_scanstring = $v;
            $this->modifiedColumns[PledgeTableMap::COL_PLG_SCANSTRING] = true;
        }

        return $this;
    } // setScanstring()

    /**
     * Set the value of [plg_aut_id] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Pledge The current object (for fluent API support)
     */
    public function setAutId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->plg_aut_id !== $v) {
            $this->plg_aut_id = $v;
            $this->modifiedColumns[PledgeTableMap::COL_PLG_AUT_ID] = true;
        }

        return $this;
    } // setAutId()

    /**
     * Sets the value of the [plg_aut_cleared] column.
     * Non-boolean arguments are converted using the following rules:
     *   * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *   * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     * Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     *
     * @param  boolean|integer|string $v The new value
     * @return $this|\ChurchCRM\Pledge The current object (for fluent API support)
     */
    public function setAutCleared($v)
    {
        if ($v !== null) {
            if (is_string($v)) {
                $v = in_array(strtolower($v), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
            } else {
                $v = (boolean) $v;
            }
        }

        if ($this->plg_aut_cleared !== $v) {
            $this->plg_aut_cleared = $v;
            $this->modifiedColumns[PledgeTableMap::COL_PLG_AUT_CLEARED] = true;
        }

        return $this;
    } // setAutCleared()

    /**
     * Set the value of [plg_aut_resultid] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Pledge The current object (for fluent API support)
     */
    public function setAutResultid($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->plg_aut_resultid !== $v) {
            $this->plg_aut_resultid = $v;
            $this->modifiedColumns[PledgeTableMap::COL_PLG_AUT_RESULTID] = true;
        }

        return $this;
    } // setAutResultid()

    /**
     * Set the value of [plg_nondeductible] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Pledge The current object (for fluent API support)
     */
    public function setNondeductible($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->plg_nondeductible !== $v) {
            $this->plg_nondeductible = $v;
            $this->modifiedColumns[PledgeTableMap::COL_PLG_NONDEDUCTIBLE] = true;
        }

        return $this;
    } // setNondeductible()

    /**
     * Set the value of [plg_groupkey] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Pledge The current object (for fluent API support)
     */
    public function setGroupkey($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->plg_groupkey !== $v) {
            $this->plg_groupkey = $v;
            $this->modifiedColumns[PledgeTableMap::COL_PLG_GROUPKEY] = true;
        }

        return $this;
    } // setGroupkey()

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
            if ($this->plg_datelastedited && $this->plg_datelastedited->format('Y-m-d') !== NULL) {
                return false;
            }

            if ($this->plg_editedby !== 0) {
                return false;
            }

            if ($this->plg_pledgeorpayment !== 'Pledge') {
                return false;
            }

            if ($this->plg_aut_id !== 0) {
                return false;
            }

            if ($this->plg_aut_cleared !== false) {
                return false;
            }

            if ($this->plg_aut_resultid !== 0) {
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

            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : PledgeTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->plg_plgid = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : PledgeTableMap::translateFieldName('FamId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->plg_famid = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : PledgeTableMap::translateFieldName('Fyid', TableMap::TYPE_PHPNAME, $indexType)];
            $this->plg_fyid = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : PledgeTableMap::translateFieldName('Date', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00') {
                $col = null;
            }
            $this->plg_date = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : PledgeTableMap::translateFieldName('Amount', TableMap::TYPE_PHPNAME, $indexType)];
            $this->plg_amount = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : PledgeTableMap::translateFieldName('Schedule', TableMap::TYPE_PHPNAME, $indexType)];
            $this->plg_schedule = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : PledgeTableMap::translateFieldName('Method', TableMap::TYPE_PHPNAME, $indexType)];
            $this->plg_method = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : PledgeTableMap::translateFieldName('Comment', TableMap::TYPE_PHPNAME, $indexType)];
            $this->plg_comment = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : PledgeTableMap::translateFieldName('Datelastedited', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00') {
                $col = null;
            }
            $this->plg_datelastedited = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 9 + $startcol : PledgeTableMap::translateFieldName('Editedby', TableMap::TYPE_PHPNAME, $indexType)];
            $this->plg_editedby = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 10 + $startcol : PledgeTableMap::translateFieldName('Pledgeorpayment', TableMap::TYPE_PHPNAME, $indexType)];
            $this->plg_pledgeorpayment = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 11 + $startcol : PledgeTableMap::translateFieldName('Fundid', TableMap::TYPE_PHPNAME, $indexType)];
            $this->plg_fundid = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 12 + $startcol : PledgeTableMap::translateFieldName('Depid', TableMap::TYPE_PHPNAME, $indexType)];
            $this->plg_depid = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 13 + $startcol : PledgeTableMap::translateFieldName('Checkno', TableMap::TYPE_PHPNAME, $indexType)];
            $this->plg_checkno = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 14 + $startcol : PledgeTableMap::translateFieldName('Problem', TableMap::TYPE_PHPNAME, $indexType)];
            $this->plg_problem = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 15 + $startcol : PledgeTableMap::translateFieldName('Scanstring', TableMap::TYPE_PHPNAME, $indexType)];
            $this->plg_scanstring = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 16 + $startcol : PledgeTableMap::translateFieldName('AutId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->plg_aut_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 17 + $startcol : PledgeTableMap::translateFieldName('AutCleared', TableMap::TYPE_PHPNAME, $indexType)];
            $this->plg_aut_cleared = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 18 + $startcol : PledgeTableMap::translateFieldName('AutResultid', TableMap::TYPE_PHPNAME, $indexType)];
            $this->plg_aut_resultid = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 19 + $startcol : PledgeTableMap::translateFieldName('Nondeductible', TableMap::TYPE_PHPNAME, $indexType)];
            $this->plg_nondeductible = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 20 + $startcol : PledgeTableMap::translateFieldName('Groupkey', TableMap::TYPE_PHPNAME, $indexType)];
            $this->plg_groupkey = (null !== $col) ? (string) $col : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 21; // 21 = PledgeTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException(sprintf('Error populating %s object', '\\ChurchCRM\\Pledge'), 0, $e);
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
        if ($this->aFamily !== null && $this->plg_famid !== $this->aFamily->getId()) {
            $this->aFamily = null;
        }
        if ($this->aDonationFund !== null && $this->plg_fundid !== $this->aDonationFund->getId()) {
            $this->aDonationFund = null;
        }
        if ($this->aDeposit !== null && $this->plg_depid !== $this->aDeposit->getId()) {
            $this->aDeposit = null;
        }
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
            $con = Propel::getServiceContainer()->getReadConnection(PledgeTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildPledgeQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->aDeposit = null;
            $this->aDonationFund = null;
            $this->aFamily = null;
        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see Pledge::setDeleted()
     * @see Pledge::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(PledgeTableMap::DATABASE_NAME);
        }

        $con->transaction(function () use ($con) {
            $deleteQuery = ChildPledgeQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(PledgeTableMap::DATABASE_NAME);
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
                PledgeTableMap::addInstanceToPool($this);
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

            // We call the save method on the following object(s) if they
            // were passed to this object by their corresponding set
            // method.  This object relates to these object(s) by a
            // foreign key reference.

            if ($this->aDeposit !== null) {
                if ($this->aDeposit->isModified() || $this->aDeposit->isNew()) {
                    $affectedRows += $this->aDeposit->save($con);
                }
                $this->setDeposit($this->aDeposit);
            }

            if ($this->aDonationFund !== null) {
                if ($this->aDonationFund->isModified() || $this->aDonationFund->isNew()) {
                    $affectedRows += $this->aDonationFund->save($con);
                }
                $this->setDonationFund($this->aDonationFund);
            }

            if ($this->aFamily !== null) {
                if ($this->aFamily->isModified() || $this->aFamily->isNew()) {
                    $affectedRows += $this->aFamily->save($con);
                }
                $this->setFamily($this->aFamily);
            }

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

        $this->modifiedColumns[PledgeTableMap::COL_PLG_PLGID] = true;
        if (null !== $this->plg_plgid) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . PledgeTableMap::COL_PLG_PLGID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_PLGID)) {
            $modifiedColumns[':p' . $index++]  = 'plg_plgID';
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_FAMID)) {
            $modifiedColumns[':p' . $index++]  = 'plg_FamID';
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_FYID)) {
            $modifiedColumns[':p' . $index++]  = 'plg_FYID';
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_DATE)) {
            $modifiedColumns[':p' . $index++]  = 'plg_date';
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_AMOUNT)) {
            $modifiedColumns[':p' . $index++]  = 'plg_amount';
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_SCHEDULE)) {
            $modifiedColumns[':p' . $index++]  = 'plg_schedule';
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_METHOD)) {
            $modifiedColumns[':p' . $index++]  = 'plg_method';
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_COMMENT)) {
            $modifiedColumns[':p' . $index++]  = 'plg_comment';
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_DATELASTEDITED)) {
            $modifiedColumns[':p' . $index++]  = 'plg_DateLastEdited';
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_EDITEDBY)) {
            $modifiedColumns[':p' . $index++]  = 'plg_EditedBy';
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_PLEDGEORPAYMENT)) {
            $modifiedColumns[':p' . $index++]  = 'plg_PledgeOrPayment';
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_FUNDID)) {
            $modifiedColumns[':p' . $index++]  = 'plg_fundID';
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_DEPID)) {
            $modifiedColumns[':p' . $index++]  = 'plg_depID';
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_CHECKNO)) {
            $modifiedColumns[':p' . $index++]  = 'plg_CheckNo';
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_PROBLEM)) {
            $modifiedColumns[':p' . $index++]  = 'plg_Problem';
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_SCANSTRING)) {
            $modifiedColumns[':p' . $index++]  = 'plg_scanString';
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_AUT_ID)) {
            $modifiedColumns[':p' . $index++]  = 'plg_aut_ID';
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_AUT_CLEARED)) {
            $modifiedColumns[':p' . $index++]  = 'plg_aut_Cleared';
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_AUT_RESULTID)) {
            $modifiedColumns[':p' . $index++]  = 'plg_aut_ResultID';
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_NONDEDUCTIBLE)) {
            $modifiedColumns[':p' . $index++]  = 'plg_NonDeductible';
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_GROUPKEY)) {
            $modifiedColumns[':p' . $index++]  = 'plg_GroupKey';
        }

        $sql = sprintf(
            'INSERT INTO pledge_plg (%s) VALUES (%s)',
            implode(', ', $modifiedColumns),
            implode(', ', array_keys($modifiedColumns))
        );

        try {
            $stmt = $con->prepare($sql);
            foreach ($modifiedColumns as $identifier => $columnName) {
                switch ($columnName) {
                    case 'plg_plgID':
                        $stmt->bindValue($identifier, $this->plg_plgid, PDO::PARAM_INT);
                        break;
                    case 'plg_FamID':
                        $stmt->bindValue($identifier, $this->plg_famid, PDO::PARAM_INT);
                        break;
                    case 'plg_FYID':
                        $stmt->bindValue($identifier, $this->plg_fyid, PDO::PARAM_INT);
                        break;
                    case 'plg_date':
                        $stmt->bindValue($identifier, $this->plg_date ? $this->plg_date->format("Y-m-d H:i:s.u") : null, PDO::PARAM_STR);
                        break;
                    case 'plg_amount':
                        $stmt->bindValue($identifier, $this->plg_amount, PDO::PARAM_STR);
                        break;
                    case 'plg_schedule':
                        $stmt->bindValue($identifier, $this->plg_schedule, PDO::PARAM_STR);
                        break;
                    case 'plg_method':
                        $stmt->bindValue($identifier, $this->plg_method, PDO::PARAM_STR);
                        break;
                    case 'plg_comment':
                        $stmt->bindValue($identifier, $this->plg_comment, PDO::PARAM_STR);
                        break;
                    case 'plg_DateLastEdited':
                        $stmt->bindValue($identifier, $this->plg_datelastedited ? $this->plg_datelastedited->format("Y-m-d H:i:s.u") : null, PDO::PARAM_STR);
                        break;
                    case 'plg_EditedBy':
                        $stmt->bindValue($identifier, $this->plg_editedby, PDO::PARAM_INT);
                        break;
                    case 'plg_PledgeOrPayment':
                        $stmt->bindValue($identifier, $this->plg_pledgeorpayment, PDO::PARAM_STR);
                        break;
                    case 'plg_fundID':
                        $stmt->bindValue($identifier, $this->plg_fundid, PDO::PARAM_INT);
                        break;
                    case 'plg_depID':
                        $stmt->bindValue($identifier, $this->plg_depid, PDO::PARAM_INT);
                        break;
                    case 'plg_CheckNo':
                        $stmt->bindValue($identifier, $this->plg_checkno, PDO::PARAM_INT);
                        break;
                    case 'plg_Problem':
                        $stmt->bindValue($identifier, (int) $this->plg_problem, PDO::PARAM_INT);
                        break;
                    case 'plg_scanString':
                        $stmt->bindValue($identifier, $this->plg_scanstring, PDO::PARAM_STR);
                        break;
                    case 'plg_aut_ID':
                        $stmt->bindValue($identifier, $this->plg_aut_id, PDO::PARAM_INT);
                        break;
                    case 'plg_aut_Cleared':
                        $stmt->bindValue($identifier, (int) $this->plg_aut_cleared, PDO::PARAM_INT);
                        break;
                    case 'plg_aut_ResultID':
                        $stmt->bindValue($identifier, $this->plg_aut_resultid, PDO::PARAM_INT);
                        break;
                    case 'plg_NonDeductible':
                        $stmt->bindValue($identifier, $this->plg_nondeductible, PDO::PARAM_STR);
                        break;
                    case 'plg_GroupKey':
                        $stmt->bindValue($identifier, $this->plg_groupkey, PDO::PARAM_STR);
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
        $pos = PledgeTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getFamId();
                break;
            case 2:
                return $this->getFyid();
                break;
            case 3:
                return $this->getDate();
                break;
            case 4:
                return $this->getAmount();
                break;
            case 5:
                return $this->getSchedule();
                break;
            case 6:
                return $this->getMethod();
                break;
            case 7:
                return $this->getComment();
                break;
            case 8:
                return $this->getDatelastedited();
                break;
            case 9:
                return $this->getEditedby();
                break;
            case 10:
                return $this->getPledgeorpayment();
                break;
            case 11:
                return $this->getFundid();
                break;
            case 12:
                return $this->getDepid();
                break;
            case 13:
                return $this->getCheckno();
                break;
            case 14:
                return $this->getProblem();
                break;
            case 15:
                return $this->getScanstring();
                break;
            case 16:
                return $this->getAutId();
                break;
            case 17:
                return $this->getAutCleared();
                break;
            case 18:
                return $this->getAutResultid();
                break;
            case 19:
                return $this->getNondeductible();
                break;
            case 20:
                return $this->getGroupkey();
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

        if (isset($alreadyDumpedObjects['Pledge'][$this->hashCode()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Pledge'][$this->hashCode()] = true;
        $keys = PledgeTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getFamId(),
            $keys[2] => $this->getFyid(),
            $keys[3] => $this->getDate(),
            $keys[4] => $this->getAmount(),
            $keys[5] => $this->getSchedule(),
            $keys[6] => $this->getMethod(),
            $keys[7] => $this->getComment(),
            $keys[8] => $this->getDatelastedited(),
            $keys[9] => $this->getEditedby(),
            $keys[10] => $this->getPledgeorpayment(),
            $keys[11] => $this->getFundid(),
            $keys[12] => $this->getDepid(),
            $keys[13] => $this->getCheckno(),
            $keys[14] => $this->getProblem(),
            $keys[15] => $this->getScanstring(),
            $keys[16] => $this->getAutId(),
            $keys[17] => $this->getAutCleared(),
            $keys[18] => $this->getAutResultid(),
            $keys[19] => $this->getNondeductible(),
            $keys[20] => $this->getGroupkey(),
        );
        if ($result[$keys[3]] instanceof \DateTime) {
            $result[$keys[3]] = $result[$keys[3]]->format('c');
        }

        if ($result[$keys[8]] instanceof \DateTime) {
            $result[$keys[8]] = $result[$keys[8]]->format('c');
        }

        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->aDeposit) {

                switch ($keyType) {
                    case TableMap::TYPE_CAMELNAME:
                        $key = 'deposit';
                        break;
                    case TableMap::TYPE_FIELDNAME:
                        $key = 'deposit_dep';
                        break;
                    default:
                        $key = 'Deposit';
                }

                $result[$key] = $this->aDeposit->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aDonationFund) {

                switch ($keyType) {
                    case TableMap::TYPE_CAMELNAME:
                        $key = 'donationFund';
                        break;
                    case TableMap::TYPE_FIELDNAME:
                        $key = 'donationfund_fun';
                        break;
                    default:
                        $key = 'DonationFund';
                }

                $result[$key] = $this->aDonationFund->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->aFamily) {

                switch ($keyType) {
                    case TableMap::TYPE_CAMELNAME:
                        $key = 'family';
                        break;
                    case TableMap::TYPE_FIELDNAME:
                        $key = 'family_fam';
                        break;
                    default:
                        $key = 'Family';
                }

                $result[$key] = $this->aFamily->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
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
     * @return $this|\ChurchCRM\Pledge
     */
    public function setByName($name, $value, $type = TableMap::TYPE_PHPNAME)
    {
        $pos = PledgeTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

        return $this->setByPosition($pos, $value);
    }

    /**
     * Sets a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param  int $pos position in xml schema
     * @param  mixed $value field value
     * @return $this|\ChurchCRM\Pledge
     */
    public function setByPosition($pos, $value)
    {
        switch ($pos) {
            case 0:
                $this->setId($value);
                break;
            case 1:
                $this->setFamId($value);
                break;
            case 2:
                $this->setFyid($value);
                break;
            case 3:
                $this->setDate($value);
                break;
            case 4:
                $this->setAmount($value);
                break;
            case 5:
                $this->setSchedule($value);
                break;
            case 6:
                $this->setMethod($value);
                break;
            case 7:
                $this->setComment($value);
                break;
            case 8:
                $this->setDatelastedited($value);
                break;
            case 9:
                $this->setEditedby($value);
                break;
            case 10:
                $this->setPledgeorpayment($value);
                break;
            case 11:
                $this->setFundid($value);
                break;
            case 12:
                $this->setDepid($value);
                break;
            case 13:
                $this->setCheckno($value);
                break;
            case 14:
                $this->setProblem($value);
                break;
            case 15:
                $this->setScanstring($value);
                break;
            case 16:
                $this->setAutId($value);
                break;
            case 17:
                $this->setAutCleared($value);
                break;
            case 18:
                $this->setAutResultid($value);
                break;
            case 19:
                $this->setNondeductible($value);
                break;
            case 20:
                $this->setGroupkey($value);
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
        $keys = PledgeTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) {
            $this->setId($arr[$keys[0]]);
        }
        if (array_key_exists($keys[1], $arr)) {
            $this->setFamId($arr[$keys[1]]);
        }
        if (array_key_exists($keys[2], $arr)) {
            $this->setFyid($arr[$keys[2]]);
        }
        if (array_key_exists($keys[3], $arr)) {
            $this->setDate($arr[$keys[3]]);
        }
        if (array_key_exists($keys[4], $arr)) {
            $this->setAmount($arr[$keys[4]]);
        }
        if (array_key_exists($keys[5], $arr)) {
            $this->setSchedule($arr[$keys[5]]);
        }
        if (array_key_exists($keys[6], $arr)) {
            $this->setMethod($arr[$keys[6]]);
        }
        if (array_key_exists($keys[7], $arr)) {
            $this->setComment($arr[$keys[7]]);
        }
        if (array_key_exists($keys[8], $arr)) {
            $this->setDatelastedited($arr[$keys[8]]);
        }
        if (array_key_exists($keys[9], $arr)) {
            $this->setEditedby($arr[$keys[9]]);
        }
        if (array_key_exists($keys[10], $arr)) {
            $this->setPledgeorpayment($arr[$keys[10]]);
        }
        if (array_key_exists($keys[11], $arr)) {
            $this->setFundid($arr[$keys[11]]);
        }
        if (array_key_exists($keys[12], $arr)) {
            $this->setDepid($arr[$keys[12]]);
        }
        if (array_key_exists($keys[13], $arr)) {
            $this->setCheckno($arr[$keys[13]]);
        }
        if (array_key_exists($keys[14], $arr)) {
            $this->setProblem($arr[$keys[14]]);
        }
        if (array_key_exists($keys[15], $arr)) {
            $this->setScanstring($arr[$keys[15]]);
        }
        if (array_key_exists($keys[16], $arr)) {
            $this->setAutId($arr[$keys[16]]);
        }
        if (array_key_exists($keys[17], $arr)) {
            $this->setAutCleared($arr[$keys[17]]);
        }
        if (array_key_exists($keys[18], $arr)) {
            $this->setAutResultid($arr[$keys[18]]);
        }
        if (array_key_exists($keys[19], $arr)) {
            $this->setNondeductible($arr[$keys[19]]);
        }
        if (array_key_exists($keys[20], $arr)) {
            $this->setGroupkey($arr[$keys[20]]);
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
     * @return $this|\ChurchCRM\Pledge The current object, for fluid interface
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
        $criteria = new Criteria(PledgeTableMap::DATABASE_NAME);

        if ($this->isColumnModified(PledgeTableMap::COL_PLG_PLGID)) {
            $criteria->add(PledgeTableMap::COL_PLG_PLGID, $this->plg_plgid);
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_FAMID)) {
            $criteria->add(PledgeTableMap::COL_PLG_FAMID, $this->plg_famid);
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_FYID)) {
            $criteria->add(PledgeTableMap::COL_PLG_FYID, $this->plg_fyid);
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_DATE)) {
            $criteria->add(PledgeTableMap::COL_PLG_DATE, $this->plg_date);
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_AMOUNT)) {
            $criteria->add(PledgeTableMap::COL_PLG_AMOUNT, $this->plg_amount);
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_SCHEDULE)) {
            $criteria->add(PledgeTableMap::COL_PLG_SCHEDULE, $this->plg_schedule);
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_METHOD)) {
            $criteria->add(PledgeTableMap::COL_PLG_METHOD, $this->plg_method);
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_COMMENT)) {
            $criteria->add(PledgeTableMap::COL_PLG_COMMENT, $this->plg_comment);
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_DATELASTEDITED)) {
            $criteria->add(PledgeTableMap::COL_PLG_DATELASTEDITED, $this->plg_datelastedited);
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_EDITEDBY)) {
            $criteria->add(PledgeTableMap::COL_PLG_EDITEDBY, $this->plg_editedby);
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_PLEDGEORPAYMENT)) {
            $criteria->add(PledgeTableMap::COL_PLG_PLEDGEORPAYMENT, $this->plg_pledgeorpayment);
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_FUNDID)) {
            $criteria->add(PledgeTableMap::COL_PLG_FUNDID, $this->plg_fundid);
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_DEPID)) {
            $criteria->add(PledgeTableMap::COL_PLG_DEPID, $this->plg_depid);
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_CHECKNO)) {
            $criteria->add(PledgeTableMap::COL_PLG_CHECKNO, $this->plg_checkno);
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_PROBLEM)) {
            $criteria->add(PledgeTableMap::COL_PLG_PROBLEM, $this->plg_problem);
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_SCANSTRING)) {
            $criteria->add(PledgeTableMap::COL_PLG_SCANSTRING, $this->plg_scanstring);
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_AUT_ID)) {
            $criteria->add(PledgeTableMap::COL_PLG_AUT_ID, $this->plg_aut_id);
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_AUT_CLEARED)) {
            $criteria->add(PledgeTableMap::COL_PLG_AUT_CLEARED, $this->plg_aut_cleared);
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_AUT_RESULTID)) {
            $criteria->add(PledgeTableMap::COL_PLG_AUT_RESULTID, $this->plg_aut_resultid);
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_NONDEDUCTIBLE)) {
            $criteria->add(PledgeTableMap::COL_PLG_NONDEDUCTIBLE, $this->plg_nondeductible);
        }
        if ($this->isColumnModified(PledgeTableMap::COL_PLG_GROUPKEY)) {
            $criteria->add(PledgeTableMap::COL_PLG_GROUPKEY, $this->plg_groupkey);
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
        $criteria = ChildPledgeQuery::create();
        $criteria->add(PledgeTableMap::COL_PLG_PLGID, $this->plg_plgid);

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
     * Generic method to set the primary key (plg_plgid column).
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
     * @param      object $copyObj An object of \ChurchCRM\Pledge (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setFamId($this->getFamId());
        $copyObj->setFyid($this->getFyid());
        $copyObj->setDate($this->getDate());
        $copyObj->setAmount($this->getAmount());
        $copyObj->setSchedule($this->getSchedule());
        $copyObj->setMethod($this->getMethod());
        $copyObj->setComment($this->getComment());
        $copyObj->setDatelastedited($this->getDatelastedited());
        $copyObj->setEditedby($this->getEditedby());
        $copyObj->setPledgeorpayment($this->getPledgeorpayment());
        $copyObj->setFundid($this->getFundid());
        $copyObj->setDepid($this->getDepid());
        $copyObj->setCheckno($this->getCheckno());
        $copyObj->setProblem($this->getProblem());
        $copyObj->setScanstring($this->getScanstring());
        $copyObj->setAutId($this->getAutId());
        $copyObj->setAutCleared($this->getAutCleared());
        $copyObj->setAutResultid($this->getAutResultid());
        $copyObj->setNondeductible($this->getNondeductible());
        $copyObj->setGroupkey($this->getGroupkey());
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
     * @return \ChurchCRM\Pledge Clone of current object.
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
     * Declares an association between this object and a ChildDeposit object.
     *
     * @param  ChildDeposit $v
     * @return $this|\ChurchCRM\Pledge The current object (for fluent API support)
     * @throws PropelException
     */
    public function setDeposit(ChildDeposit $v = null)
    {
        if ($v === null) {
            $this->setDepid(NULL);
        } else {
            $this->setDepid($v->getId());
        }

        $this->aDeposit = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the ChildDeposit object, it will not be re-added.
        if ($v !== null) {
            $v->addPledge($this);
        }


        return $this;
    }


    /**
     * Get the associated ChildDeposit object
     *
     * @param  ConnectionInterface $con Optional Connection object.
     * @return ChildDeposit The associated ChildDeposit object.
     * @throws PropelException
     */
    public function getDeposit(ConnectionInterface $con = null)
    {
        if ($this->aDeposit === null && ($this->plg_depid !== null)) {
            $this->aDeposit = ChildDepositQuery::create()->findPk($this->plg_depid, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aDeposit->addPledges($this);
             */
        }

        return $this->aDeposit;
    }

    /**
     * Declares an association between this object and a ChildDonationFund object.
     *
     * @param  ChildDonationFund $v
     * @return $this|\ChurchCRM\Pledge The current object (for fluent API support)
     * @throws PropelException
     */
    public function setDonationFund(ChildDonationFund $v = null)
    {
        if ($v === null) {
            $this->setFundid(NULL);
        } else {
            $this->setFundid($v->getId());
        }

        $this->aDonationFund = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the ChildDonationFund object, it will not be re-added.
        if ($v !== null) {
            $v->addPledge($this);
        }


        return $this;
    }


    /**
     * Get the associated ChildDonationFund object
     *
     * @param  ConnectionInterface $con Optional Connection object.
     * @return ChildDonationFund The associated ChildDonationFund object.
     * @throws PropelException
     */
    public function getDonationFund(ConnectionInterface $con = null)
    {
        if ($this->aDonationFund === null && ($this->plg_fundid !== null)) {
            $this->aDonationFund = ChildDonationFundQuery::create()->findPk($this->plg_fundid, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aDonationFund->addPledges($this);
             */
        }

        return $this->aDonationFund;
    }

    /**
     * Declares an association between this object and a ChildFamily object.
     *
     * @param  ChildFamily $v
     * @return $this|\ChurchCRM\Pledge The current object (for fluent API support)
     * @throws PropelException
     */
    public function setFamily(ChildFamily $v = null)
    {
        if ($v === null) {
            $this->setFamId(NULL);
        } else {
            $this->setFamId($v->getId());
        }

        $this->aFamily = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the ChildFamily object, it will not be re-added.
        if ($v !== null) {
            $v->addPledge($this);
        }


        return $this;
    }


    /**
     * Get the associated ChildFamily object
     *
     * @param  ConnectionInterface $con Optional Connection object.
     * @return ChildFamily The associated ChildFamily object.
     * @throws PropelException
     */
    public function getFamily(ConnectionInterface $con = null)
    {
        if ($this->aFamily === null && ($this->plg_famid !== null)) {
            $this->aFamily = ChildFamilyQuery::create()->findPk($this->plg_famid, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aFamily->addPledges($this);
             */
        }

        return $this->aFamily;
    }

    /**
     * Clears the current object, sets all attributes to their default values and removes
     * outgoing references as well as back-references (from other objects to this one. Results probably in a database
     * change of those foreign objects when you call `save` there).
     */
    public function clear()
    {
        if (null !== $this->aDeposit) {
            $this->aDeposit->removePledge($this);
        }
        if (null !== $this->aDonationFund) {
            $this->aDonationFund->removePledge($this);
        }
        if (null !== $this->aFamily) {
            $this->aFamily->removePledge($this);
        }
        $this->plg_plgid = null;
        $this->plg_famid = null;
        $this->plg_fyid = null;
        $this->plg_date = null;
        $this->plg_amount = null;
        $this->plg_schedule = null;
        $this->plg_method = null;
        $this->plg_comment = null;
        $this->plg_datelastedited = null;
        $this->plg_editedby = null;
        $this->plg_pledgeorpayment = null;
        $this->plg_fundid = null;
        $this->plg_depid = null;
        $this->plg_checkno = null;
        $this->plg_problem = null;
        $this->plg_scanstring = null;
        $this->plg_aut_id = null;
        $this->plg_aut_cleared = null;
        $this->plg_aut_resultid = null;
        $this->plg_nondeductible = null;
        $this->plg_groupkey = null;
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

        $this->aDeposit = null;
        $this->aDonationFund = null;
        $this->aFamily = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(PledgeTableMap::DEFAULT_STRING_FORMAT);
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
