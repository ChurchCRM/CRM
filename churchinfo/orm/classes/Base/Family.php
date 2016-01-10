<?php

namespace Base;

use \FamilyQuery as ChildFamilyQuery;
use \DateTime;
use \Exception;
use \PDO;
use Map\FamilyTableMap;
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
 * Base class that represents a row from the 'family_fam' table.
 *
 *
 *
* @package    propel.generator..Base
*/
abstract class Family implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\Map\\FamilyTableMap';


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
     * The value for the fam_id field.
     *
     * @var        int
     */
    protected $fam_id;

    /**
     * The value for the fam_name field.
     *
     * @var        string
     */
    protected $fam_name;

    /**
     * The value for the fam_address1 field.
     *
     * @var        string
     */
    protected $fam_address1;

    /**
     * The value for the fam_address2 field.
     *
     * @var        string
     */
    protected $fam_address2;

    /**
     * The value for the fam_city field.
     *
     * @var        string
     */
    protected $fam_city;

    /**
     * The value for the fam_state field.
     *
     * @var        string
     */
    protected $fam_state;

    /**
     * The value for the fam_zip field.
     *
     * @var        string
     */
    protected $fam_zip;

    /**
     * The value for the fam_country field.
     *
     * @var        string
     */
    protected $fam_country;

    /**
     * The value for the fam_homephone field.
     *
     * @var        string
     */
    protected $fam_homephone;

    /**
     * The value for the fam_workphone field.
     *
     * @var        string
     */
    protected $fam_workphone;

    /**
     * The value for the fam_cellphone field.
     *
     * @var        string
     */
    protected $fam_cellphone;

    /**
     * The value for the fam_email field.
     *
     * @var        string
     */
    protected $fam_email;

    /**
     * The value for the fam_weddingdate field.
     *
     * @var        \DateTime
     */
    protected $fam_weddingdate;

    /**
     * The value for the fam_dateentered field.
     *
     * Note: this column has a database default value of: NULL
     * @var        \DateTime
     */
    protected $fam_dateentered;

    /**
     * The value for the fam_datelastedited field.
     *
     * @var        \DateTime
     */
    protected $fam_datelastedited;

    /**
     * The value for the fam_enteredby field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $fam_enteredby;

    /**
     * The value for the fam_editedby field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $fam_editedby;

    /**
     * The value for the fam_scancheck field.
     *
     * @var        string
     */
    protected $fam_scancheck;

    /**
     * The value for the fam_scancredit field.
     *
     * @var        string
     */
    protected $fam_scancredit;

    /**
     * The value for the fam_sendnewsletter field.
     *
     * Note: this column has a database default value of: 'FALSE'
     * @var        string
     */
    protected $fam_sendnewsletter;

    /**
     * The value for the fam_datedeactivated field.
     *
     * @var        \DateTime
     */
    protected $fam_datedeactivated;

    /**
     * The value for the fam_oktocanvass field.
     *
     * Note: this column has a database default value of: 'FALSE'
     * @var        string
     */
    protected $fam_oktocanvass;

    /**
     * The value for the fam_canvasser field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $fam_canvasser;

    /**
     * The value for the fam_latitude field.
     *
     * @var        double
     */
    protected $fam_latitude;

    /**
     * The value for the fam_longitude field.
     *
     * @var        double
     */
    protected $fam_longitude;

    /**
     * The value for the fam_envelope field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $fam_envelope;

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
        $this->fam_dateentered = PropelDateTime::newInstance(NULL, null, 'DateTime');
        $this->fam_enteredby = 0;
        $this->fam_editedby = 0;
        $this->fam_sendnewsletter = 'FALSE';
        $this->fam_oktocanvass = 'FALSE';
        $this->fam_canvasser = 0;
        $this->fam_envelope = 0;
    }

    /**
     * Initializes internal state of Base\Family object.
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
     * Compares this with another <code>Family</code> instance.  If
     * <code>obj</code> is an instance of <code>Family</code>, delegates to
     * <code>equals(Family)</code>.  Otherwise, returns <code>false</code>.
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
     * @return $this|Family The current object, for fluid interface
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
     * Get the [fam_id] column value.
     *
     * @return int
     */
    public function getId()
    {
        return $this->fam_id;
    }

    /**
     * Get the [fam_name] column value.
     *
     * @return string
     */
    public function getName()
    {
        return $this->fam_name;
    }

    /**
     * Get the [fam_address1] column value.
     *
     * @return string
     */
    public function getAddress1()
    {
        return $this->fam_address1;
    }

    /**
     * Get the [fam_address2] column value.
     *
     * @return string
     */
    public function getAddress2()
    {
        return $this->fam_address2;
    }

    /**
     * Get the [fam_city] column value.
     *
     * @return string
     */
    public function getCity()
    {
        return $this->fam_city;
    }

    /**
     * Get the [fam_state] column value.
     *
     * @return string
     */
    public function getState()
    {
        return $this->fam_state;
    }

    /**
     * Get the [fam_zip] column value.
     *
     * @return string
     */
    public function getZip()
    {
        return $this->fam_zip;
    }

    /**
     * Get the [fam_country] column value.
     *
     * @return string
     */
    public function getCountry()
    {
        return $this->fam_country;
    }

    /**
     * Get the [fam_homephone] column value.
     *
     * @return string
     */
    public function getHomephone()
    {
        return $this->fam_homephone;
    }

    /**
     * Get the [fam_workphone] column value.
     *
     * @return string
     */
    public function getWorkphone()
    {
        return $this->fam_workphone;
    }

    /**
     * Get the [fam_cellphone] column value.
     *
     * @return string
     */
    public function getCellphone()
    {
        return $this->fam_cellphone;
    }

    /**
     * Get the [fam_email] column value.
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->fam_email;
    }

    /**
     * Get the [optionally formatted] temporal [fam_weddingdate] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw DateTime object will be returned.
     *
     * @return string|DateTime Formatted date/time value as string or DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getWeddingdate($format = NULL)
    {
        if ($format === null) {
            return $this->fam_weddingdate;
        } else {
            return $this->fam_weddingdate instanceof \DateTime ? $this->fam_weddingdate->format($format) : null;
        }
    }

    /**
     * Get the [optionally formatted] temporal [fam_dateentered] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw DateTime object will be returned.
     *
     * @return string|DateTime Formatted date/time value as string or DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00 00:00:00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getDateEntered($format = NULL)
    {
        if ($format === null) {
            return $this->fam_dateentered;
        } else {
            return $this->fam_dateentered instanceof \DateTime ? $this->fam_dateentered->format($format) : null;
        }
    }

    /**
     * Get the [optionally formatted] temporal [fam_datelastedited] column value.
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
            return $this->fam_datelastedited;
        } else {
            return $this->fam_datelastedited instanceof \DateTime ? $this->fam_datelastedited->format($format) : null;
        }
    }

    /**
     * Get the [fam_enteredby] column value.
     *
     * @return int
     */
    public function getEnteredBy()
    {
        return $this->fam_enteredby;
    }

    /**
     * Get the [fam_editedby] column value.
     *
     * @return int
     */
    public function getEditedBy()
    {
        return $this->fam_editedby;
    }

    /**
     * Get the [fam_scancheck] column value.
     *
     * @return string
     */
    public function getScanCheck()
    {
        return $this->fam_scancheck;
    }

    /**
     * Get the [fam_scancredit] column value.
     *
     * @return string
     */
    public function getScanCredit()
    {
        return $this->fam_scancredit;
    }

    /**
     * Get the [fam_sendnewsletter] column value.
     *
     * @return string
     */
    public function getSendNewsletter()
    {
        return $this->fam_sendnewsletter;
    }

    /**
     * Get the [optionally formatted] temporal [fam_datedeactivated] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw DateTime object will be returned.
     *
     * @return string|DateTime Formatted date/time value as string or DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getDateDeactivated($format = NULL)
    {
        if ($format === null) {
            return $this->fam_datedeactivated;
        } else {
            return $this->fam_datedeactivated instanceof \DateTime ? $this->fam_datedeactivated->format($format) : null;
        }
    }

    /**
     * Get the [fam_oktocanvass] column value.
     *
     * @return string
     */
    public function getOkToCanvass()
    {
        return $this->fam_oktocanvass;
    }

    /**
     * Get the [fam_canvasser] column value.
     *
     * @return int
     */
    public function getCanvasser()
    {
        return $this->fam_canvasser;
    }

    /**
     * Get the [fam_latitude] column value.
     *
     * @return double
     */
    public function getLatitude()
    {
        return $this->fam_latitude;
    }

    /**
     * Get the [fam_longitude] column value.
     *
     * @return double
     */
    public function getLongitude()
    {
        return $this->fam_longitude;
    }

    /**
     * Get the [fam_envelope] column value.
     *
     * @return int
     */
    public function getEnvelope()
    {
        return $this->fam_envelope;
    }

    /**
     * Set the value of [fam_id] column.
     *
     * @param int $v new value
     * @return $this|\Family The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->fam_id !== $v) {
            $this->fam_id = $v;
            $this->modifiedColumns[FamilyTableMap::COL_FAM_ID] = true;
        }

        return $this;
    } // setId()

    /**
     * Set the value of [fam_name] column.
     *
     * @param string $v new value
     * @return $this|\Family The current object (for fluent API support)
     */
    public function setName($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->fam_name !== $v) {
            $this->fam_name = $v;
            $this->modifiedColumns[FamilyTableMap::COL_FAM_NAME] = true;
        }

        return $this;
    } // setName()

    /**
     * Set the value of [fam_address1] column.
     *
     * @param string $v new value
     * @return $this|\Family The current object (for fluent API support)
     */
    public function setAddress1($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->fam_address1 !== $v) {
            $this->fam_address1 = $v;
            $this->modifiedColumns[FamilyTableMap::COL_FAM_ADDRESS1] = true;
        }

        return $this;
    } // setAddress1()

    /**
     * Set the value of [fam_address2] column.
     *
     * @param string $v new value
     * @return $this|\Family The current object (for fluent API support)
     */
    public function setAddress2($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->fam_address2 !== $v) {
            $this->fam_address2 = $v;
            $this->modifiedColumns[FamilyTableMap::COL_FAM_ADDRESS2] = true;
        }

        return $this;
    } // setAddress2()

    /**
     * Set the value of [fam_city] column.
     *
     * @param string $v new value
     * @return $this|\Family The current object (for fluent API support)
     */
    public function setCity($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->fam_city !== $v) {
            $this->fam_city = $v;
            $this->modifiedColumns[FamilyTableMap::COL_FAM_CITY] = true;
        }

        return $this;
    } // setCity()

    /**
     * Set the value of [fam_state] column.
     *
     * @param string $v new value
     * @return $this|\Family The current object (for fluent API support)
     */
    public function setState($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->fam_state !== $v) {
            $this->fam_state = $v;
            $this->modifiedColumns[FamilyTableMap::COL_FAM_STATE] = true;
        }

        return $this;
    } // setState()

    /**
     * Set the value of [fam_zip] column.
     *
     * @param string $v new value
     * @return $this|\Family The current object (for fluent API support)
     */
    public function setZip($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->fam_zip !== $v) {
            $this->fam_zip = $v;
            $this->modifiedColumns[FamilyTableMap::COL_FAM_ZIP] = true;
        }

        return $this;
    } // setZip()

    /**
     * Set the value of [fam_country] column.
     *
     * @param string $v new value
     * @return $this|\Family The current object (for fluent API support)
     */
    public function setCountry($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->fam_country !== $v) {
            $this->fam_country = $v;
            $this->modifiedColumns[FamilyTableMap::COL_FAM_COUNTRY] = true;
        }

        return $this;
    } // setCountry()

    /**
     * Set the value of [fam_homephone] column.
     *
     * @param string $v new value
     * @return $this|\Family The current object (for fluent API support)
     */
    public function setHomephone($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->fam_homephone !== $v) {
            $this->fam_homephone = $v;
            $this->modifiedColumns[FamilyTableMap::COL_FAM_HOMEPHONE] = true;
        }

        return $this;
    } // setHomephone()

    /**
     * Set the value of [fam_workphone] column.
     *
     * @param string $v new value
     * @return $this|\Family The current object (for fluent API support)
     */
    public function setWorkphone($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->fam_workphone !== $v) {
            $this->fam_workphone = $v;
            $this->modifiedColumns[FamilyTableMap::COL_FAM_WORKPHONE] = true;
        }

        return $this;
    } // setWorkphone()

    /**
     * Set the value of [fam_cellphone] column.
     *
     * @param string $v new value
     * @return $this|\Family The current object (for fluent API support)
     */
    public function setCellphone($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->fam_cellphone !== $v) {
            $this->fam_cellphone = $v;
            $this->modifiedColumns[FamilyTableMap::COL_FAM_CELLPHONE] = true;
        }

        return $this;
    } // setCellphone()

    /**
     * Set the value of [fam_email] column.
     *
     * @param string $v new value
     * @return $this|\Family The current object (for fluent API support)
     */
    public function setEmail($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->fam_email !== $v) {
            $this->fam_email = $v;
            $this->modifiedColumns[FamilyTableMap::COL_FAM_EMAIL] = true;
        }

        return $this;
    } // setEmail()

    /**
     * Sets the value of [fam_weddingdate] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return $this|\Family The current object (for fluent API support)
     */
    public function setWeddingdate($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->fam_weddingdate !== null || $dt !== null) {
            if ($this->fam_weddingdate === null || $dt === null || $dt->format("Y-m-d") !== $this->fam_weddingdate->format("Y-m-d")) {
                $this->fam_weddingdate = $dt === null ? null : clone $dt;
                $this->modifiedColumns[FamilyTableMap::COL_FAM_WEDDINGDATE] = true;
            }
        } // if either are not null

        return $this;
    } // setWeddingdate()

    /**
     * Sets the value of [fam_dateentered] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return $this|\Family The current object (for fluent API support)
     */
    public function setDateEntered($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->fam_dateentered !== null || $dt !== null) {
            if ( ($dt != $this->fam_dateentered) // normalized values don't match
                || ($dt->format('Y-m-d H:i:s') === NULL) // or the entered value matches the default
                 ) {
                $this->fam_dateentered = $dt === null ? null : clone $dt;
                $this->modifiedColumns[FamilyTableMap::COL_FAM_DATEENTERED] = true;
            }
        } // if either are not null

        return $this;
    } // setDateEntered()

    /**
     * Sets the value of [fam_datelastedited] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return $this|\Family The current object (for fluent API support)
     */
    public function setDateLastEdited($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->fam_datelastedited !== null || $dt !== null) {
            if ($this->fam_datelastedited === null || $dt === null || $dt->format("Y-m-d H:i:s") !== $this->fam_datelastedited->format("Y-m-d H:i:s")) {
                $this->fam_datelastedited = $dt === null ? null : clone $dt;
                $this->modifiedColumns[FamilyTableMap::COL_FAM_DATELASTEDITED] = true;
            }
        } // if either are not null

        return $this;
    } // setDateLastEdited()

    /**
     * Set the value of [fam_enteredby] column.
     *
     * @param int $v new value
     * @return $this|\Family The current object (for fluent API support)
     */
    public function setEnteredBy($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->fam_enteredby !== $v) {
            $this->fam_enteredby = $v;
            $this->modifiedColumns[FamilyTableMap::COL_FAM_ENTEREDBY] = true;
        }

        return $this;
    } // setEnteredBy()

    /**
     * Set the value of [fam_editedby] column.
     *
     * @param int $v new value
     * @return $this|\Family The current object (for fluent API support)
     */
    public function setEditedBy($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->fam_editedby !== $v) {
            $this->fam_editedby = $v;
            $this->modifiedColumns[FamilyTableMap::COL_FAM_EDITEDBY] = true;
        }

        return $this;
    } // setEditedBy()

    /**
     * Set the value of [fam_scancheck] column.
     *
     * @param string $v new value
     * @return $this|\Family The current object (for fluent API support)
     */
    public function setScanCheck($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->fam_scancheck !== $v) {
            $this->fam_scancheck = $v;
            $this->modifiedColumns[FamilyTableMap::COL_FAM_SCANCHECK] = true;
        }

        return $this;
    } // setScanCheck()

    /**
     * Set the value of [fam_scancredit] column.
     *
     * @param string $v new value
     * @return $this|\Family The current object (for fluent API support)
     */
    public function setScanCredit($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->fam_scancredit !== $v) {
            $this->fam_scancredit = $v;
            $this->modifiedColumns[FamilyTableMap::COL_FAM_SCANCREDIT] = true;
        }

        return $this;
    } // setScanCredit()

    /**
     * Set the value of [fam_sendnewsletter] column.
     *
     * @param string $v new value
     * @return $this|\Family The current object (for fluent API support)
     */
    public function setSendNewsletter($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->fam_sendnewsletter !== $v) {
            $this->fam_sendnewsletter = $v;
            $this->modifiedColumns[FamilyTableMap::COL_FAM_SENDNEWSLETTER] = true;
        }

        return $this;
    } // setSendNewsletter()

    /**
     * Sets the value of [fam_datedeactivated] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTime value.
     *               Empty strings are treated as NULL.
     * @return $this|\Family The current object (for fluent API support)
     */
    public function setDateDeactivated($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->fam_datedeactivated !== null || $dt !== null) {
            if ($this->fam_datedeactivated === null || $dt === null || $dt->format("Y-m-d") !== $this->fam_datedeactivated->format("Y-m-d")) {
                $this->fam_datedeactivated = $dt === null ? null : clone $dt;
                $this->modifiedColumns[FamilyTableMap::COL_FAM_DATEDEACTIVATED] = true;
            }
        } // if either are not null

        return $this;
    } // setDateDeactivated()

    /**
     * Set the value of [fam_oktocanvass] column.
     *
     * @param string $v new value
     * @return $this|\Family The current object (for fluent API support)
     */
    public function setOkToCanvass($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->fam_oktocanvass !== $v) {
            $this->fam_oktocanvass = $v;
            $this->modifiedColumns[FamilyTableMap::COL_FAM_OKTOCANVASS] = true;
        }

        return $this;
    } // setOkToCanvass()

    /**
     * Set the value of [fam_canvasser] column.
     *
     * @param int $v new value
     * @return $this|\Family The current object (for fluent API support)
     */
    public function setCanvasser($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->fam_canvasser !== $v) {
            $this->fam_canvasser = $v;
            $this->modifiedColumns[FamilyTableMap::COL_FAM_CANVASSER] = true;
        }

        return $this;
    } // setCanvasser()

    /**
     * Set the value of [fam_latitude] column.
     *
     * @param double $v new value
     * @return $this|\Family The current object (for fluent API support)
     */
    public function setLatitude($v)
    {
        if ($v !== null) {
            $v = (double) $v;
        }

        if ($this->fam_latitude !== $v) {
            $this->fam_latitude = $v;
            $this->modifiedColumns[FamilyTableMap::COL_FAM_LATITUDE] = true;
        }

        return $this;
    } // setLatitude()

    /**
     * Set the value of [fam_longitude] column.
     *
     * @param double $v new value
     * @return $this|\Family The current object (for fluent API support)
     */
    public function setLongitude($v)
    {
        if ($v !== null) {
            $v = (double) $v;
        }

        if ($this->fam_longitude !== $v) {
            $this->fam_longitude = $v;
            $this->modifiedColumns[FamilyTableMap::COL_FAM_LONGITUDE] = true;
        }

        return $this;
    } // setLongitude()

    /**
     * Set the value of [fam_envelope] column.
     *
     * @param int $v new value
     * @return $this|\Family The current object (for fluent API support)
     */
    public function setEnvelope($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->fam_envelope !== $v) {
            $this->fam_envelope = $v;
            $this->modifiedColumns[FamilyTableMap::COL_FAM_ENVELOPE] = true;
        }

        return $this;
    } // setEnvelope()

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
            if ($this->fam_dateentered && $this->fam_dateentered->format('Y-m-d H:i:s') !== NULL) {
                return false;
            }

            if ($this->fam_enteredby !== 0) {
                return false;
            }

            if ($this->fam_editedby !== 0) {
                return false;
            }

            if ($this->fam_sendnewsletter !== 'FALSE') {
                return false;
            }

            if ($this->fam_oktocanvass !== 'FALSE') {
                return false;
            }

            if ($this->fam_canvasser !== 0) {
                return false;
            }

            if ($this->fam_envelope !== 0) {
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

            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : FamilyTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->fam_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : FamilyTableMap::translateFieldName('Name', TableMap::TYPE_PHPNAME, $indexType)];
            $this->fam_name = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : FamilyTableMap::translateFieldName('Address1', TableMap::TYPE_PHPNAME, $indexType)];
            $this->fam_address1 = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : FamilyTableMap::translateFieldName('Address2', TableMap::TYPE_PHPNAME, $indexType)];
            $this->fam_address2 = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : FamilyTableMap::translateFieldName('City', TableMap::TYPE_PHPNAME, $indexType)];
            $this->fam_city = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : FamilyTableMap::translateFieldName('State', TableMap::TYPE_PHPNAME, $indexType)];
            $this->fam_state = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : FamilyTableMap::translateFieldName('Zip', TableMap::TYPE_PHPNAME, $indexType)];
            $this->fam_zip = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : FamilyTableMap::translateFieldName('Country', TableMap::TYPE_PHPNAME, $indexType)];
            $this->fam_country = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : FamilyTableMap::translateFieldName('Homephone', TableMap::TYPE_PHPNAME, $indexType)];
            $this->fam_homephone = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 9 + $startcol : FamilyTableMap::translateFieldName('Workphone', TableMap::TYPE_PHPNAME, $indexType)];
            $this->fam_workphone = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 10 + $startcol : FamilyTableMap::translateFieldName('Cellphone', TableMap::TYPE_PHPNAME, $indexType)];
            $this->fam_cellphone = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 11 + $startcol : FamilyTableMap::translateFieldName('Email', TableMap::TYPE_PHPNAME, $indexType)];
            $this->fam_email = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 12 + $startcol : FamilyTableMap::translateFieldName('Weddingdate', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00') {
                $col = null;
            }
            $this->fam_weddingdate = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 13 + $startcol : FamilyTableMap::translateFieldName('DateEntered', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->fam_dateentered = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 14 + $startcol : FamilyTableMap::translateFieldName('DateLastEdited', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->fam_datelastedited = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 15 + $startcol : FamilyTableMap::translateFieldName('EnteredBy', TableMap::TYPE_PHPNAME, $indexType)];
            $this->fam_enteredby = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 16 + $startcol : FamilyTableMap::translateFieldName('EditedBy', TableMap::TYPE_PHPNAME, $indexType)];
            $this->fam_editedby = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 17 + $startcol : FamilyTableMap::translateFieldName('ScanCheck', TableMap::TYPE_PHPNAME, $indexType)];
            $this->fam_scancheck = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 18 + $startcol : FamilyTableMap::translateFieldName('ScanCredit', TableMap::TYPE_PHPNAME, $indexType)];
            $this->fam_scancredit = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 19 + $startcol : FamilyTableMap::translateFieldName('SendNewsletter', TableMap::TYPE_PHPNAME, $indexType)];
            $this->fam_sendnewsletter = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 20 + $startcol : FamilyTableMap::translateFieldName('DateDeactivated', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00') {
                $col = null;
            }
            $this->fam_datedeactivated = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 21 + $startcol : FamilyTableMap::translateFieldName('OkToCanvass', TableMap::TYPE_PHPNAME, $indexType)];
            $this->fam_oktocanvass = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 22 + $startcol : FamilyTableMap::translateFieldName('Canvasser', TableMap::TYPE_PHPNAME, $indexType)];
            $this->fam_canvasser = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 23 + $startcol : FamilyTableMap::translateFieldName('Latitude', TableMap::TYPE_PHPNAME, $indexType)];
            $this->fam_latitude = (null !== $col) ? (double) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 24 + $startcol : FamilyTableMap::translateFieldName('Longitude', TableMap::TYPE_PHPNAME, $indexType)];
            $this->fam_longitude = (null !== $col) ? (double) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 25 + $startcol : FamilyTableMap::translateFieldName('Envelope', TableMap::TYPE_PHPNAME, $indexType)];
            $this->fam_envelope = (null !== $col) ? (int) $col : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 26; // 26 = FamilyTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException(sprintf('Error populating %s object', '\\Family'), 0, $e);
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
            $con = Propel::getServiceContainer()->getReadConnection(FamilyTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildFamilyQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
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
     * @see Family::setDeleted()
     * @see Family::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(FamilyTableMap::DATABASE_NAME);
        }

        $con->transaction(function () use ($con) {
            $deleteQuery = ChildFamilyQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(FamilyTableMap::DATABASE_NAME);
        }

        return $con->transaction(function () use ($con) {
            $isInsert = $this->isNew();
            $ret = $this->preSave($con);
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
                FamilyTableMap::addInstanceToPool($this);
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

        $this->modifiedColumns[FamilyTableMap::COL_FAM_ID] = true;
        if (null !== $this->fam_id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . FamilyTableMap::COL_FAM_ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_ID)) {
            $modifiedColumns[':p' . $index++]  = 'fam_ID';
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_NAME)) {
            $modifiedColumns[':p' . $index++]  = 'fam_Name';
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_ADDRESS1)) {
            $modifiedColumns[':p' . $index++]  = 'fam_Address1';
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_ADDRESS2)) {
            $modifiedColumns[':p' . $index++]  = 'fam_Address2';
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_CITY)) {
            $modifiedColumns[':p' . $index++]  = 'fam_City';
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_STATE)) {
            $modifiedColumns[':p' . $index++]  = 'fam_State';
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_ZIP)) {
            $modifiedColumns[':p' . $index++]  = 'fam_Zip';
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_COUNTRY)) {
            $modifiedColumns[':p' . $index++]  = 'fam_Country';
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_HOMEPHONE)) {
            $modifiedColumns[':p' . $index++]  = 'fam_HomePhone';
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_WORKPHONE)) {
            $modifiedColumns[':p' . $index++]  = 'fam_WorkPhone';
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_CELLPHONE)) {
            $modifiedColumns[':p' . $index++]  = 'fam_CellPhone';
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_EMAIL)) {
            $modifiedColumns[':p' . $index++]  = 'fam_Email';
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_WEDDINGDATE)) {
            $modifiedColumns[':p' . $index++]  = 'fam_WeddingDate';
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_DATEENTERED)) {
            $modifiedColumns[':p' . $index++]  = 'fam_DateEntered';
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_DATELASTEDITED)) {
            $modifiedColumns[':p' . $index++]  = 'fam_DateLastEdited';
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_ENTEREDBY)) {
            $modifiedColumns[':p' . $index++]  = 'fam_EnteredBy';
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_EDITEDBY)) {
            $modifiedColumns[':p' . $index++]  = 'fam_EditedBy';
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_SCANCHECK)) {
            $modifiedColumns[':p' . $index++]  = 'fam_scanCheck';
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_SCANCREDIT)) {
            $modifiedColumns[':p' . $index++]  = 'fam_scanCredit';
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_SENDNEWSLETTER)) {
            $modifiedColumns[':p' . $index++]  = 'fam_SendNewsLetter';
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_DATEDEACTIVATED)) {
            $modifiedColumns[':p' . $index++]  = 'fam_DateDeactivated';
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_OKTOCANVASS)) {
            $modifiedColumns[':p' . $index++]  = 'fam_OkToCanvass';
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_CANVASSER)) {
            $modifiedColumns[':p' . $index++]  = 'fam_Canvasser';
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_LATITUDE)) {
            $modifiedColumns[':p' . $index++]  = 'fam_Latitude';
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_LONGITUDE)) {
            $modifiedColumns[':p' . $index++]  = 'fam_Longitude';
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_ENVELOPE)) {
            $modifiedColumns[':p' . $index++]  = 'fam_Envelope';
        }

        $sql = sprintf(
            'INSERT INTO family_fam (%s) VALUES (%s)',
            implode(', ', $modifiedColumns),
            implode(', ', array_keys($modifiedColumns))
        );

        try {
            $stmt = $con->prepare($sql);
            foreach ($modifiedColumns as $identifier => $columnName) {
                switch ($columnName) {
                    case 'fam_ID':
                        $stmt->bindValue($identifier, $this->fam_id, PDO::PARAM_INT);
                        break;
                    case 'fam_Name':
                        $stmt->bindValue($identifier, $this->fam_name, PDO::PARAM_STR);
                        break;
                    case 'fam_Address1':
                        $stmt->bindValue($identifier, $this->fam_address1, PDO::PARAM_STR);
                        break;
                    case 'fam_Address2':
                        $stmt->bindValue($identifier, $this->fam_address2, PDO::PARAM_STR);
                        break;
                    case 'fam_City':
                        $stmt->bindValue($identifier, $this->fam_city, PDO::PARAM_STR);
                        break;
                    case 'fam_State':
                        $stmt->bindValue($identifier, $this->fam_state, PDO::PARAM_STR);
                        break;
                    case 'fam_Zip':
                        $stmt->bindValue($identifier, $this->fam_zip, PDO::PARAM_STR);
                        break;
                    case 'fam_Country':
                        $stmt->bindValue($identifier, $this->fam_country, PDO::PARAM_STR);
                        break;
                    case 'fam_HomePhone':
                        $stmt->bindValue($identifier, $this->fam_homephone, PDO::PARAM_STR);
                        break;
                    case 'fam_WorkPhone':
                        $stmt->bindValue($identifier, $this->fam_workphone, PDO::PARAM_STR);
                        break;
                    case 'fam_CellPhone':
                        $stmt->bindValue($identifier, $this->fam_cellphone, PDO::PARAM_STR);
                        break;
                    case 'fam_Email':
                        $stmt->bindValue($identifier, $this->fam_email, PDO::PARAM_STR);
                        break;
                    case 'fam_WeddingDate':
                        $stmt->bindValue($identifier, $this->fam_weddingdate ? $this->fam_weddingdate->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case 'fam_DateEntered':
                        $stmt->bindValue($identifier, $this->fam_dateentered ? $this->fam_dateentered->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case 'fam_DateLastEdited':
                        $stmt->bindValue($identifier, $this->fam_datelastedited ? $this->fam_datelastedited->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case 'fam_EnteredBy':
                        $stmt->bindValue($identifier, $this->fam_enteredby, PDO::PARAM_INT);
                        break;
                    case 'fam_EditedBy':
                        $stmt->bindValue($identifier, $this->fam_editedby, PDO::PARAM_INT);
                        break;
                    case 'fam_scanCheck':
                        $stmt->bindValue($identifier, $this->fam_scancheck, PDO::PARAM_STR);
                        break;
                    case 'fam_scanCredit':
                        $stmt->bindValue($identifier, $this->fam_scancredit, PDO::PARAM_STR);
                        break;
                    case 'fam_SendNewsLetter':
                        $stmt->bindValue($identifier, $this->fam_sendnewsletter, PDO::PARAM_STR);
                        break;
                    case 'fam_DateDeactivated':
                        $stmt->bindValue($identifier, $this->fam_datedeactivated ? $this->fam_datedeactivated->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case 'fam_OkToCanvass':
                        $stmt->bindValue($identifier, $this->fam_oktocanvass, PDO::PARAM_STR);
                        break;
                    case 'fam_Canvasser':
                        $stmt->bindValue($identifier, $this->fam_canvasser, PDO::PARAM_INT);
                        break;
                    case 'fam_Latitude':
                        $stmt->bindValue($identifier, $this->fam_latitude, PDO::PARAM_STR);
                        break;
                    case 'fam_Longitude':
                        $stmt->bindValue($identifier, $this->fam_longitude, PDO::PARAM_STR);
                        break;
                    case 'fam_Envelope':
                        $stmt->bindValue($identifier, $this->fam_envelope, PDO::PARAM_INT);
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
        $pos = FamilyTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getName();
                break;
            case 2:
                return $this->getAddress1();
                break;
            case 3:
                return $this->getAddress2();
                break;
            case 4:
                return $this->getCity();
                break;
            case 5:
                return $this->getState();
                break;
            case 6:
                return $this->getZip();
                break;
            case 7:
                return $this->getCountry();
                break;
            case 8:
                return $this->getHomephone();
                break;
            case 9:
                return $this->getWorkphone();
                break;
            case 10:
                return $this->getCellphone();
                break;
            case 11:
                return $this->getEmail();
                break;
            case 12:
                return $this->getWeddingdate();
                break;
            case 13:
                return $this->getDateEntered();
                break;
            case 14:
                return $this->getDateLastEdited();
                break;
            case 15:
                return $this->getEnteredBy();
                break;
            case 16:
                return $this->getEditedBy();
                break;
            case 17:
                return $this->getScanCheck();
                break;
            case 18:
                return $this->getScanCredit();
                break;
            case 19:
                return $this->getSendNewsletter();
                break;
            case 20:
                return $this->getDateDeactivated();
                break;
            case 21:
                return $this->getOkToCanvass();
                break;
            case 22:
                return $this->getCanvasser();
                break;
            case 23:
                return $this->getLatitude();
                break;
            case 24:
                return $this->getLongitude();
                break;
            case 25:
                return $this->getEnvelope();
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

        if (isset($alreadyDumpedObjects['Family'][$this->hashCode()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Family'][$this->hashCode()] = true;
        $keys = FamilyTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getName(),
            $keys[2] => $this->getAddress1(),
            $keys[3] => $this->getAddress2(),
            $keys[4] => $this->getCity(),
            $keys[5] => $this->getState(),
            $keys[6] => $this->getZip(),
            $keys[7] => $this->getCountry(),
            $keys[8] => $this->getHomephone(),
            $keys[9] => $this->getWorkphone(),
            $keys[10] => $this->getCellphone(),
            $keys[11] => $this->getEmail(),
            $keys[12] => $this->getWeddingdate(),
            $keys[13] => $this->getDateEntered(),
            $keys[14] => $this->getDateLastEdited(),
            $keys[15] => $this->getEnteredBy(),
            $keys[16] => $this->getEditedBy(),
            $keys[17] => $this->getScanCheck(),
            $keys[18] => $this->getScanCredit(),
            $keys[19] => $this->getSendNewsletter(),
            $keys[20] => $this->getDateDeactivated(),
            $keys[21] => $this->getOkToCanvass(),
            $keys[22] => $this->getCanvasser(),
            $keys[23] => $this->getLatitude(),
            $keys[24] => $this->getLongitude(),
            $keys[25] => $this->getEnvelope(),
        );
        if ($result[$keys[12]] instanceof \DateTime) {
            $result[$keys[12]] = $result[$keys[12]]->format('c');
        }

        if ($result[$keys[13]] instanceof \DateTime) {
            $result[$keys[13]] = $result[$keys[13]]->format('c');
        }

        if ($result[$keys[14]] instanceof \DateTime) {
            $result[$keys[14]] = $result[$keys[14]]->format('c');
        }

        if ($result[$keys[20]] instanceof \DateTime) {
            $result[$keys[20]] = $result[$keys[20]]->format('c');
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
     * @return $this|\Family
     */
    public function setByName($name, $value, $type = TableMap::TYPE_PHPNAME)
    {
        $pos = FamilyTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

        return $this->setByPosition($pos, $value);
    }

    /**
     * Sets a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param  int $pos position in xml schema
     * @param  mixed $value field value
     * @return $this|\Family
     */
    public function setByPosition($pos, $value)
    {
        switch ($pos) {
            case 0:
                $this->setId($value);
                break;
            case 1:
                $this->setName($value);
                break;
            case 2:
                $this->setAddress1($value);
                break;
            case 3:
                $this->setAddress2($value);
                break;
            case 4:
                $this->setCity($value);
                break;
            case 5:
                $this->setState($value);
                break;
            case 6:
                $this->setZip($value);
                break;
            case 7:
                $this->setCountry($value);
                break;
            case 8:
                $this->setHomephone($value);
                break;
            case 9:
                $this->setWorkphone($value);
                break;
            case 10:
                $this->setCellphone($value);
                break;
            case 11:
                $this->setEmail($value);
                break;
            case 12:
                $this->setWeddingdate($value);
                break;
            case 13:
                $this->setDateEntered($value);
                break;
            case 14:
                $this->setDateLastEdited($value);
                break;
            case 15:
                $this->setEnteredBy($value);
                break;
            case 16:
                $this->setEditedBy($value);
                break;
            case 17:
                $this->setScanCheck($value);
                break;
            case 18:
                $this->setScanCredit($value);
                break;
            case 19:
                $this->setSendNewsletter($value);
                break;
            case 20:
                $this->setDateDeactivated($value);
                break;
            case 21:
                $this->setOkToCanvass($value);
                break;
            case 22:
                $this->setCanvasser($value);
                break;
            case 23:
                $this->setLatitude($value);
                break;
            case 24:
                $this->setLongitude($value);
                break;
            case 25:
                $this->setEnvelope($value);
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
        $keys = FamilyTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) {
            $this->setId($arr[$keys[0]]);
        }
        if (array_key_exists($keys[1], $arr)) {
            $this->setName($arr[$keys[1]]);
        }
        if (array_key_exists($keys[2], $arr)) {
            $this->setAddress1($arr[$keys[2]]);
        }
        if (array_key_exists($keys[3], $arr)) {
            $this->setAddress2($arr[$keys[3]]);
        }
        if (array_key_exists($keys[4], $arr)) {
            $this->setCity($arr[$keys[4]]);
        }
        if (array_key_exists($keys[5], $arr)) {
            $this->setState($arr[$keys[5]]);
        }
        if (array_key_exists($keys[6], $arr)) {
            $this->setZip($arr[$keys[6]]);
        }
        if (array_key_exists($keys[7], $arr)) {
            $this->setCountry($arr[$keys[7]]);
        }
        if (array_key_exists($keys[8], $arr)) {
            $this->setHomephone($arr[$keys[8]]);
        }
        if (array_key_exists($keys[9], $arr)) {
            $this->setWorkphone($arr[$keys[9]]);
        }
        if (array_key_exists($keys[10], $arr)) {
            $this->setCellphone($arr[$keys[10]]);
        }
        if (array_key_exists($keys[11], $arr)) {
            $this->setEmail($arr[$keys[11]]);
        }
        if (array_key_exists($keys[12], $arr)) {
            $this->setWeddingdate($arr[$keys[12]]);
        }
        if (array_key_exists($keys[13], $arr)) {
            $this->setDateEntered($arr[$keys[13]]);
        }
        if (array_key_exists($keys[14], $arr)) {
            $this->setDateLastEdited($arr[$keys[14]]);
        }
        if (array_key_exists($keys[15], $arr)) {
            $this->setEnteredBy($arr[$keys[15]]);
        }
        if (array_key_exists($keys[16], $arr)) {
            $this->setEditedBy($arr[$keys[16]]);
        }
        if (array_key_exists($keys[17], $arr)) {
            $this->setScanCheck($arr[$keys[17]]);
        }
        if (array_key_exists($keys[18], $arr)) {
            $this->setScanCredit($arr[$keys[18]]);
        }
        if (array_key_exists($keys[19], $arr)) {
            $this->setSendNewsletter($arr[$keys[19]]);
        }
        if (array_key_exists($keys[20], $arr)) {
            $this->setDateDeactivated($arr[$keys[20]]);
        }
        if (array_key_exists($keys[21], $arr)) {
            $this->setOkToCanvass($arr[$keys[21]]);
        }
        if (array_key_exists($keys[22], $arr)) {
            $this->setCanvasser($arr[$keys[22]]);
        }
        if (array_key_exists($keys[23], $arr)) {
            $this->setLatitude($arr[$keys[23]]);
        }
        if (array_key_exists($keys[24], $arr)) {
            $this->setLongitude($arr[$keys[24]]);
        }
        if (array_key_exists($keys[25], $arr)) {
            $this->setEnvelope($arr[$keys[25]]);
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
     * @return $this|\Family The current object, for fluid interface
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
        $criteria = new Criteria(FamilyTableMap::DATABASE_NAME);

        if ($this->isColumnModified(FamilyTableMap::COL_FAM_ID)) {
            $criteria->add(FamilyTableMap::COL_FAM_ID, $this->fam_id);
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_NAME)) {
            $criteria->add(FamilyTableMap::COL_FAM_NAME, $this->fam_name);
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_ADDRESS1)) {
            $criteria->add(FamilyTableMap::COL_FAM_ADDRESS1, $this->fam_address1);
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_ADDRESS2)) {
            $criteria->add(FamilyTableMap::COL_FAM_ADDRESS2, $this->fam_address2);
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_CITY)) {
            $criteria->add(FamilyTableMap::COL_FAM_CITY, $this->fam_city);
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_STATE)) {
            $criteria->add(FamilyTableMap::COL_FAM_STATE, $this->fam_state);
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_ZIP)) {
            $criteria->add(FamilyTableMap::COL_FAM_ZIP, $this->fam_zip);
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_COUNTRY)) {
            $criteria->add(FamilyTableMap::COL_FAM_COUNTRY, $this->fam_country);
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_HOMEPHONE)) {
            $criteria->add(FamilyTableMap::COL_FAM_HOMEPHONE, $this->fam_homephone);
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_WORKPHONE)) {
            $criteria->add(FamilyTableMap::COL_FAM_WORKPHONE, $this->fam_workphone);
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_CELLPHONE)) {
            $criteria->add(FamilyTableMap::COL_FAM_CELLPHONE, $this->fam_cellphone);
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_EMAIL)) {
            $criteria->add(FamilyTableMap::COL_FAM_EMAIL, $this->fam_email);
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_WEDDINGDATE)) {
            $criteria->add(FamilyTableMap::COL_FAM_WEDDINGDATE, $this->fam_weddingdate);
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_DATEENTERED)) {
            $criteria->add(FamilyTableMap::COL_FAM_DATEENTERED, $this->fam_dateentered);
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_DATELASTEDITED)) {
            $criteria->add(FamilyTableMap::COL_FAM_DATELASTEDITED, $this->fam_datelastedited);
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_ENTEREDBY)) {
            $criteria->add(FamilyTableMap::COL_FAM_ENTEREDBY, $this->fam_enteredby);
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_EDITEDBY)) {
            $criteria->add(FamilyTableMap::COL_FAM_EDITEDBY, $this->fam_editedby);
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_SCANCHECK)) {
            $criteria->add(FamilyTableMap::COL_FAM_SCANCHECK, $this->fam_scancheck);
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_SCANCREDIT)) {
            $criteria->add(FamilyTableMap::COL_FAM_SCANCREDIT, $this->fam_scancredit);
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_SENDNEWSLETTER)) {
            $criteria->add(FamilyTableMap::COL_FAM_SENDNEWSLETTER, $this->fam_sendnewsletter);
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_DATEDEACTIVATED)) {
            $criteria->add(FamilyTableMap::COL_FAM_DATEDEACTIVATED, $this->fam_datedeactivated);
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_OKTOCANVASS)) {
            $criteria->add(FamilyTableMap::COL_FAM_OKTOCANVASS, $this->fam_oktocanvass);
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_CANVASSER)) {
            $criteria->add(FamilyTableMap::COL_FAM_CANVASSER, $this->fam_canvasser);
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_LATITUDE)) {
            $criteria->add(FamilyTableMap::COL_FAM_LATITUDE, $this->fam_latitude);
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_LONGITUDE)) {
            $criteria->add(FamilyTableMap::COL_FAM_LONGITUDE, $this->fam_longitude);
        }
        if ($this->isColumnModified(FamilyTableMap::COL_FAM_ENVELOPE)) {
            $criteria->add(FamilyTableMap::COL_FAM_ENVELOPE, $this->fam_envelope);
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
        $criteria = ChildFamilyQuery::create();
        $criteria->add(FamilyTableMap::COL_FAM_ID, $this->fam_id);

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
     * Generic method to set the primary key (fam_id column).
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
     * @param      object $copyObj An object of \Family (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setName($this->getName());
        $copyObj->setAddress1($this->getAddress1());
        $copyObj->setAddress2($this->getAddress2());
        $copyObj->setCity($this->getCity());
        $copyObj->setState($this->getState());
        $copyObj->setZip($this->getZip());
        $copyObj->setCountry($this->getCountry());
        $copyObj->setHomephone($this->getHomephone());
        $copyObj->setWorkphone($this->getWorkphone());
        $copyObj->setCellphone($this->getCellphone());
        $copyObj->setEmail($this->getEmail());
        $copyObj->setWeddingdate($this->getWeddingdate());
        $copyObj->setDateEntered($this->getDateEntered());
        $copyObj->setDateLastEdited($this->getDateLastEdited());
        $copyObj->setEnteredBy($this->getEnteredBy());
        $copyObj->setEditedBy($this->getEditedBy());
        $copyObj->setScanCheck($this->getScanCheck());
        $copyObj->setScanCredit($this->getScanCredit());
        $copyObj->setSendNewsletter($this->getSendNewsletter());
        $copyObj->setDateDeactivated($this->getDateDeactivated());
        $copyObj->setOkToCanvass($this->getOkToCanvass());
        $copyObj->setCanvasser($this->getCanvasser());
        $copyObj->setLatitude($this->getLatitude());
        $copyObj->setLongitude($this->getLongitude());
        $copyObj->setEnvelope($this->getEnvelope());
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
     * @return \Family Clone of current object.
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
        $this->fam_id = null;
        $this->fam_name = null;
        $this->fam_address1 = null;
        $this->fam_address2 = null;
        $this->fam_city = null;
        $this->fam_state = null;
        $this->fam_zip = null;
        $this->fam_country = null;
        $this->fam_homephone = null;
        $this->fam_workphone = null;
        $this->fam_cellphone = null;
        $this->fam_email = null;
        $this->fam_weddingdate = null;
        $this->fam_dateentered = null;
        $this->fam_datelastedited = null;
        $this->fam_enteredby = null;
        $this->fam_editedby = null;
        $this->fam_scancheck = null;
        $this->fam_scancredit = null;
        $this->fam_sendnewsletter = null;
        $this->fam_datedeactivated = null;
        $this->fam_oktocanvass = null;
        $this->fam_canvasser = null;
        $this->fam_latitude = null;
        $this->fam_longitude = null;
        $this->fam_envelope = null;
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
        return (string) $this->exportTo(FamilyTableMap::DEFAULT_STRING_FORMAT);
    }

    /**
     * Code to be run before persisting the object
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preSave(ConnectionInterface $con = null)
    {
        return true;
    }

    /**
     * Code to be run after persisting the object
     * @param ConnectionInterface $con
     */
    public function postSave(ConnectionInterface $con = null)
    {

    }

    /**
     * Code to be run before inserting to database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        return true;
    }

    /**
     * Code to be run after inserting to database
     * @param ConnectionInterface $con
     */
    public function postInsert(ConnectionInterface $con = null)
    {

    }

    /**
     * Code to be run before updating the object in database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preUpdate(ConnectionInterface $con = null)
    {
        return true;
    }

    /**
     * Code to be run after updating the object in database
     * @param ConnectionInterface $con
     */
    public function postUpdate(ConnectionInterface $con = null)
    {

    }

    /**
     * Code to be run before deleting the object in database
     * @param  ConnectionInterface $con
     * @return boolean
     */
    public function preDelete(ConnectionInterface $con = null)
    {
        return true;
    }

    /**
     * Code to be run after deleting the object in database
     * @param ConnectionInterface $con
     */
    public function postDelete(ConnectionInterface $con = null)
    {

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
