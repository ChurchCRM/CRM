<?php

namespace ChurchCRM\Base;

use \DateTime;
use \Exception;
use \PDO;
use ChurchCRM\Family as ChildFamily;
use ChurchCRM\FamilyQuery as ChildFamilyQuery;
use ChurchCRM\Note as ChildNote;
use ChurchCRM\NoteQuery as ChildNoteQuery;
use ChurchCRM\Person as ChildPerson;
use ChurchCRM\Person2group2roleP2g2r as ChildPerson2group2roleP2g2r;
use ChurchCRM\Person2group2roleP2g2rQuery as ChildPerson2group2roleP2g2rQuery;
use ChurchCRM\PersonQuery as ChildPersonQuery;
use ChurchCRM\WhyCame as ChildWhyCame;
use ChurchCRM\WhyCameQuery as ChildWhyCameQuery;
use ChurchCRM\Map\NoteTableMap;
use ChurchCRM\Map\Person2group2roleP2g2rTableMap;
use ChurchCRM\Map\PersonTableMap;
use ChurchCRM\Map\WhyCameTableMap;
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
 * Base class that represents a row from the 'person_per' table.
 *
 *
 *
 * @package    propel.generator.ChurchCRM.Base
 */
abstract class Person implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\ChurchCRM\\Map\\PersonTableMap';


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
     * The value for the per_id field.
     *
     * @var        int
     */
    protected $per_id;

    /**
     * The value for the per_title field.
     *
     * @var        string
     */
    protected $per_title;

    /**
     * The value for the per_firstname field.
     *
     * @var        string
     */
    protected $per_firstname;

    /**
     * The value for the per_middlename field.
     *
     * @var        string
     */
    protected $per_middlename;

    /**
     * The value for the per_lastname field.
     *
     * @var        string
     */
    protected $per_lastname;

    /**
     * The value for the per_suffix field.
     *
     * @var        string
     */
    protected $per_suffix;

    /**
     * The value for the per_address1 field.
     *
     * @var        string
     */
    protected $per_address1;

    /**
     * The value for the per_address2 field.
     *
     * @var        string
     */
    protected $per_address2;

    /**
     * The value for the per_city field.
     *
     * @var        string
     */
    protected $per_city;

    /**
     * The value for the per_state field.
     *
     * @var        string
     */
    protected $per_state;

    /**
     * The value for the per_zip field.
     *
     * @var        string
     */
    protected $per_zip;

    /**
     * The value for the per_country field.
     *
     * @var        string
     */
    protected $per_country;

    /**
     * The value for the per_homephone field.
     *
     * @var        string
     */
    protected $per_homephone;

    /**
     * The value for the per_workphone field.
     *
     * @var        string
     */
    protected $per_workphone;

    /**
     * The value for the per_cellphone field.
     *
     * @var        string
     */
    protected $per_cellphone;

    /**
     * The value for the per_email field.
     *
     * @var        string
     */
    protected $per_email;

    /**
     * The value for the per_workemail field.
     *
     * @var        string
     */
    protected $per_workemail;

    /**
     * The value for the per_birthmonth field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $per_birthmonth;

    /**
     * The value for the per_birthday field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $per_birthday;

    /**
     * The value for the per_birthyear field.
     *
     * @var        int
     */
    protected $per_birthyear;

    /**
     * The value for the per_membershipdate field.
     *
     * @var        DateTime
     */
    protected $per_membershipdate;

    /**
     * The value for the per_gender field.
     *
     * Note: this column has a database default value of: false
     * @var        boolean
     */
    protected $per_gender;

    /**
     * The value for the per_fmr_id field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $per_fmr_id;

    /**
     * The value for the per_cls_id field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $per_cls_id;

    /**
     * The value for the per_fam_id field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $per_fam_id;

    /**
     * The value for the per_envelope field.
     *
     * @var        int
     */
    protected $per_envelope;

    /**
     * The value for the per_datelastedited field.
     *
     * @var        DateTime
     */
    protected $per_datelastedited;

    /**
     * The value for the per_dateentered field.
     *
     * Note: this column has a database default value of: NULL
     * @var        DateTime
     */
    protected $per_dateentered;

    /**
     * The value for the per_enteredby field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $per_enteredby;

    /**
     * The value for the per_editedby field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $per_editedby;

    /**
     * The value for the per_frienddate field.
     *
     * @var        DateTime
     */
    protected $per_frienddate;

    /**
     * The value for the per_flags field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $per_flags;

    /**
     * @var        ChildFamily
     */
    protected $aFamily;

    /**
     * @var        ObjectCollection|ChildWhyCame[] Collection to store aggregation of ChildWhyCame objects.
     */
    protected $collWhyCames;
    protected $collWhyCamesPartial;

    /**
     * @var        ObjectCollection|ChildNote[] Collection to store aggregation of ChildNote objects.
     */
    protected $collNotes;
    protected $collNotesPartial;

    /**
     * @var        ObjectCollection|ChildPerson2group2roleP2g2r[] Collection to store aggregation of ChildPerson2group2roleP2g2r objects.
     */
    protected $collPerson2group2roleP2g2rs;
    protected $collPerson2group2roleP2g2rsPartial;

    /**
     * Flag to prevent endless save loop, if this object is referenced
     * by another object which falls in this transaction.
     *
     * @var boolean
     */
    protected $alreadyInSave = false;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection|ChildWhyCame[]
     */
    protected $whyCamesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection|ChildNote[]
     */
    protected $notesScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection|ChildPerson2group2roleP2g2r[]
     */
    protected $person2group2roleP2g2rsScheduledForDeletion = null;

    /**
     * Applies default values to this object.
     * This method should be called from the object's constructor (or
     * equivalent initialization method).
     * @see __construct()
     */
    public function applyDefaultValues()
    {
        $this->per_birthmonth = 0;
        $this->per_birthday = 0;
        $this->per_gender = false;
        $this->per_fmr_id = 0;
        $this->per_cls_id = 0;
        $this->per_fam_id = 0;
        $this->per_dateentered = PropelDateTime::newInstance(NULL, null, 'DateTime');
        $this->per_enteredby = 0;
        $this->per_editedby = 0;
        $this->per_flags = 0;
    }

    /**
     * Initializes internal state of ChurchCRM\Base\Person object.
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
     * Compares this with another <code>Person</code> instance.  If
     * <code>obj</code> is an instance of <code>Person</code>, delegates to
     * <code>equals(Person)</code>.  Otherwise, returns <code>false</code>.
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
     * @return $this|Person The current object, for fluid interface
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
     * Get the [per_id] column value.
     *
     * @return int
     */
    public function getId()
    {
        return $this->per_id;
    }

    /**
     * Get the [per_title] column value.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->per_title;
    }

    /**
     * Get the [per_firstname] column value.
     *
     * @return string
     */
    public function getFirstName()
    {
        return $this->per_firstname;
    }

    /**
     * Get the [per_middlename] column value.
     *
     * @return string
     */
    public function getMiddleName()
    {
        return $this->per_middlename;
    }

    /**
     * Get the [per_lastname] column value.
     *
     * @return string
     */
    public function getLastName()
    {
        return $this->per_lastname;
    }

    /**
     * Get the [per_suffix] column value.
     *
     * @return string
     */
    public function getSuffix()
    {
        return $this->per_suffix;
    }

    /**
     * Get the [per_address1] column value.
     *
     * @return string
     */
    public function getAddress1()
    {
        return $this->per_address1;
    }

    /**
     * Get the [per_address2] column value.
     *
     * @return string
     */
    public function getAddress2()
    {
        return $this->per_address2;
    }

    /**
     * Get the [per_city] column value.
     *
     * @return string
     */
    public function getCity()
    {
        return $this->per_city;
    }

    /**
     * Get the [per_state] column value.
     *
     * @return string
     */
    public function getState()
    {
        return $this->per_state;
    }

    /**
     * Get the [per_zip] column value.
     *
     * @return string
     */
    public function getZip()
    {
        return $this->per_zip;
    }

    /**
     * Get the [per_country] column value.
     *
     * @return string
     */
    public function getCountry()
    {
        return $this->per_country;
    }

    /**
     * Get the [per_homephone] column value.
     *
     * @return string
     */
    public function getHomePhone()
    {
        return $this->per_homephone;
    }

    /**
     * Get the [per_workphone] column value.
     *
     * @return string
     */
    public function getWorkPhone()
    {
        return $this->per_workphone;
    }

    /**
     * Get the [per_cellphone] column value.
     *
     * @return string
     */
    public function getCellPhone()
    {
        return $this->per_cellphone;
    }

    /**
     * Get the [per_email] column value.
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->per_email;
    }

    /**
     * Get the [per_workemail] column value.
     *
     * @return string
     */
    public function getWorkEmail()
    {
        return $this->per_workemail;
    }

    /**
     * Get the [per_birthmonth] column value.
     *
     * @return int
     */
    public function getBirthMonth()
    {
        return $this->per_birthmonth;
    }

    /**
     * Get the [per_birthday] column value.
     *
     * @return int
     */
    public function getBirthDay()
    {
        return $this->per_birthday;
    }

    /**
     * Get the [per_birthyear] column value.
     *
     * @return int
     */
    public function getBirthYear()
    {
        return $this->per_birthyear;
    }

    /**
     * Get the [optionally formatted] temporal [per_membershipdate] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw DateTime object will be returned.
     *
     * @return string|DateTime Formatted date/time value as string or DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getMembershipDate($format = NULL)
    {
        if ($format === null) {
            return $this->per_membershipdate;
        } else {
            return $this->per_membershipdate instanceof \DateTimeInterface ? $this->per_membershipdate->format($format) : null;
        }
    }

    /**
     * Get the [per_gender] column value.
     *
     * @return boolean
     */
    public function getGender()
    {
        return $this->per_gender;
    }

    /**
     * Get the [per_gender] column value.
     *
     * @return boolean
     */
    public function isGender()
    {
        return $this->getGender();
    }

    /**
     * Get the [per_fmr_id] column value.
     *
     * @return int
     */
    public function getFmrId()
    {
        return $this->per_fmr_id;
    }

    /**
     * Get the [per_cls_id] column value.
     *
     * @return int
     */
    public function getClsId()
    {
        return $this->per_cls_id;
    }

    /**
     * Get the [per_fam_id] column value.
     *
     * @return int
     */
    public function getFamId()
    {
        return $this->per_fam_id;
    }

    /**
     * Get the [per_envelope] column value.
     *
     * @return int
     */
    public function getEnvelope()
    {
        return $this->per_envelope;
    }

    /**
     * Get the [optionally formatted] temporal [per_datelastedited] column value.
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
            return $this->per_datelastedited;
        } else {
            return $this->per_datelastedited instanceof \DateTimeInterface ? $this->per_datelastedited->format($format) : null;
        }
    }

    /**
     * Get the [optionally formatted] temporal [per_dateentered] column value.
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
            return $this->per_dateentered;
        } else {
            return $this->per_dateentered instanceof \DateTimeInterface ? $this->per_dateentered->format($format) : null;
        }
    }

    /**
     * Get the [per_enteredby] column value.
     *
     * @return int
     */
    public function getEnteredBy()
    {
        return $this->per_enteredby;
    }

    /**
     * Get the [per_editedby] column value.
     *
     * @return int
     */
    public function getEditedBy()
    {
        return $this->per_editedby;
    }

    /**
     * Get the [optionally formatted] temporal [per_frienddate] column value.
     *
     *
     * @param      string $format The date/time format string (either date()-style or strftime()-style).
     *                            If format is NULL, then the raw DateTime object will be returned.
     *
     * @return string|DateTime Formatted date/time value as string or DateTime object (if format is NULL), NULL if column is NULL, and 0 if column value is 0000-00-00
     *
     * @throws PropelException - if unable to parse/validate the date/time value.
     */
    public function getFriendDate($format = NULL)
    {
        if ($format === null) {
            return $this->per_frienddate;
        } else {
            return $this->per_frienddate instanceof \DateTimeInterface ? $this->per_frienddate->format($format) : null;
        }
    }

    /**
     * Get the [per_flags] column value.
     *
     * @return int
     */
    public function getFlags()
    {
        return $this->per_flags;
    }

    /**
     * Set the value of [per_id] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->per_id !== $v) {
            $this->per_id = $v;
            $this->modifiedColumns[PersonTableMap::COL_PER_ID] = true;
        }

        return $this;
    } // setId()

    /**
     * Set the value of [per_title] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setTitle($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->per_title !== $v) {
            $this->per_title = $v;
            $this->modifiedColumns[PersonTableMap::COL_PER_TITLE] = true;
        }

        return $this;
    } // setTitle()

    /**
     * Set the value of [per_firstname] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setFirstName($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->per_firstname !== $v) {
            $this->per_firstname = $v;
            $this->modifiedColumns[PersonTableMap::COL_PER_FIRSTNAME] = true;
        }

        return $this;
    } // setFirstName()

    /**
     * Set the value of [per_middlename] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setMiddleName($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->per_middlename !== $v) {
            $this->per_middlename = $v;
            $this->modifiedColumns[PersonTableMap::COL_PER_MIDDLENAME] = true;
        }

        return $this;
    } // setMiddleName()

    /**
     * Set the value of [per_lastname] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setLastName($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->per_lastname !== $v) {
            $this->per_lastname = $v;
            $this->modifiedColumns[PersonTableMap::COL_PER_LASTNAME] = true;
        }

        return $this;
    } // setLastName()

    /**
     * Set the value of [per_suffix] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setSuffix($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->per_suffix !== $v) {
            $this->per_suffix = $v;
            $this->modifiedColumns[PersonTableMap::COL_PER_SUFFIX] = true;
        }

        return $this;
    } // setSuffix()

    /**
     * Set the value of [per_address1] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setAddress1($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->per_address1 !== $v) {
            $this->per_address1 = $v;
            $this->modifiedColumns[PersonTableMap::COL_PER_ADDRESS1] = true;
        }

        return $this;
    } // setAddress1()

    /**
     * Set the value of [per_address2] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setAddress2($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->per_address2 !== $v) {
            $this->per_address2 = $v;
            $this->modifiedColumns[PersonTableMap::COL_PER_ADDRESS2] = true;
        }

        return $this;
    } // setAddress2()

    /**
     * Set the value of [per_city] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setCity($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->per_city !== $v) {
            $this->per_city = $v;
            $this->modifiedColumns[PersonTableMap::COL_PER_CITY] = true;
        }

        return $this;
    } // setCity()

    /**
     * Set the value of [per_state] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setState($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->per_state !== $v) {
            $this->per_state = $v;
            $this->modifiedColumns[PersonTableMap::COL_PER_STATE] = true;
        }

        return $this;
    } // setState()

    /**
     * Set the value of [per_zip] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setZip($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->per_zip !== $v) {
            $this->per_zip = $v;
            $this->modifiedColumns[PersonTableMap::COL_PER_ZIP] = true;
        }

        return $this;
    } // setZip()

    /**
     * Set the value of [per_country] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setCountry($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->per_country !== $v) {
            $this->per_country = $v;
            $this->modifiedColumns[PersonTableMap::COL_PER_COUNTRY] = true;
        }

        return $this;
    } // setCountry()

    /**
     * Set the value of [per_homephone] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setHomePhone($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->per_homephone !== $v) {
            $this->per_homephone = $v;
            $this->modifiedColumns[PersonTableMap::COL_PER_HOMEPHONE] = true;
        }

        return $this;
    } // setHomePhone()

    /**
     * Set the value of [per_workphone] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setWorkPhone($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->per_workphone !== $v) {
            $this->per_workphone = $v;
            $this->modifiedColumns[PersonTableMap::COL_PER_WORKPHONE] = true;
        }

        return $this;
    } // setWorkPhone()

    /**
     * Set the value of [per_cellphone] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setCellPhone($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->per_cellphone !== $v) {
            $this->per_cellphone = $v;
            $this->modifiedColumns[PersonTableMap::COL_PER_CELLPHONE] = true;
        }

        return $this;
    } // setCellPhone()

    /**
     * Set the value of [per_email] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setEmail($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->per_email !== $v) {
            $this->per_email = $v;
            $this->modifiedColumns[PersonTableMap::COL_PER_EMAIL] = true;
        }

        return $this;
    } // setEmail()

    /**
     * Set the value of [per_workemail] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setWorkEmail($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->per_workemail !== $v) {
            $this->per_workemail = $v;
            $this->modifiedColumns[PersonTableMap::COL_PER_WORKEMAIL] = true;
        }

        return $this;
    } // setWorkEmail()

    /**
     * Set the value of [per_birthmonth] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setBirthMonth($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->per_birthmonth !== $v) {
            $this->per_birthmonth = $v;
            $this->modifiedColumns[PersonTableMap::COL_PER_BIRTHMONTH] = true;
        }

        return $this;
    } // setBirthMonth()

    /**
     * Set the value of [per_birthday] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setBirthDay($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->per_birthday !== $v) {
            $this->per_birthday = $v;
            $this->modifiedColumns[PersonTableMap::COL_PER_BIRTHDAY] = true;
        }

        return $this;
    } // setBirthDay()

    /**
     * Set the value of [per_birthyear] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setBirthYear($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->per_birthyear !== $v) {
            $this->per_birthyear = $v;
            $this->modifiedColumns[PersonTableMap::COL_PER_BIRTHYEAR] = true;
        }

        return $this;
    } // setBirthYear()

    /**
     * Sets the value of [per_membershipdate] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTimeInterface value.
     *               Empty strings are treated as NULL.
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setMembershipDate($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->per_membershipdate !== null || $dt !== null) {
            if ($this->per_membershipdate === null || $dt === null || $dt->format("Y-m-d") !== $this->per_membershipdate->format("Y-m-d")) {
                $this->per_membershipdate = $dt === null ? null : clone $dt;
                $this->modifiedColumns[PersonTableMap::COL_PER_MEMBERSHIPDATE] = true;
            }
        } // if either are not null

        return $this;
    } // setMembershipDate()

    /**
     * Sets the value of the [per_gender] column.
     * Non-boolean arguments are converted using the following rules:
     *   * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *   * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     * Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     *
     * @param  boolean|integer|string $v The new value
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setGender($v)
    {
        if ($v !== null) {
            if (is_string($v)) {
                $v = in_array(strtolower($v), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
            } else {
                $v = (boolean) $v;
            }
        }

        if ($this->per_gender !== $v) {
            $this->per_gender = $v;
            $this->modifiedColumns[PersonTableMap::COL_PER_GENDER] = true;
        }

        return $this;
    } // setGender()

    /**
     * Set the value of [per_fmr_id] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setFmrId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->per_fmr_id !== $v) {
            $this->per_fmr_id = $v;
            $this->modifiedColumns[PersonTableMap::COL_PER_FMR_ID] = true;
        }

        return $this;
    } // setFmrId()

    /**
     * Set the value of [per_cls_id] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setClsId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->per_cls_id !== $v) {
            $this->per_cls_id = $v;
            $this->modifiedColumns[PersonTableMap::COL_PER_CLS_ID] = true;
        }

        return $this;
    } // setClsId()

    /**
     * Set the value of [per_fam_id] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setFamId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->per_fam_id !== $v) {
            $this->per_fam_id = $v;
            $this->modifiedColumns[PersonTableMap::COL_PER_FAM_ID] = true;
        }

        if ($this->aFamily !== null && $this->aFamily->getId() !== $v) {
            $this->aFamily = null;
        }

        return $this;
    } // setFamId()

    /**
     * Set the value of [per_envelope] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setEnvelope($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->per_envelope !== $v) {
            $this->per_envelope = $v;
            $this->modifiedColumns[PersonTableMap::COL_PER_ENVELOPE] = true;
        }

        return $this;
    } // setEnvelope()

    /**
     * Sets the value of [per_datelastedited] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTimeInterface value.
     *               Empty strings are treated as NULL.
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setDateLastEdited($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->per_datelastedited !== null || $dt !== null) {
            if ($this->per_datelastedited === null || $dt === null || $dt->format("Y-m-d H:i:s.u") !== $this->per_datelastedited->format("Y-m-d H:i:s.u")) {
                $this->per_datelastedited = $dt === null ? null : clone $dt;
                $this->modifiedColumns[PersonTableMap::COL_PER_DATELASTEDITED] = true;
            }
        } // if either are not null

        return $this;
    } // setDateLastEdited()

    /**
     * Sets the value of [per_dateentered] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTimeInterface value.
     *               Empty strings are treated as NULL.
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setDateEntered($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->per_dateentered !== null || $dt !== null) {
            if ( ($dt != $this->per_dateentered) // normalized values don't match
                || ($dt->format('Y-m-d H:i:s.u') === NULL) // or the entered value matches the default
                 ) {
                $this->per_dateentered = $dt === null ? null : clone $dt;
                $this->modifiedColumns[PersonTableMap::COL_PER_DATEENTERED] = true;
            }
        } // if either are not null

        return $this;
    } // setDateEntered()

    /**
     * Set the value of [per_enteredby] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setEnteredBy($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->per_enteredby !== $v) {
            $this->per_enteredby = $v;
            $this->modifiedColumns[PersonTableMap::COL_PER_ENTEREDBY] = true;
        }

        return $this;
    } // setEnteredBy()

    /**
     * Set the value of [per_editedby] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setEditedBy($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->per_editedby !== $v) {
            $this->per_editedby = $v;
            $this->modifiedColumns[PersonTableMap::COL_PER_EDITEDBY] = true;
        }

        return $this;
    } // setEditedBy()

    /**
     * Sets the value of [per_frienddate] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTimeInterface value.
     *               Empty strings are treated as NULL.
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setFriendDate($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->per_frienddate !== null || $dt !== null) {
            if ($this->per_frienddate === null || $dt === null || $dt->format("Y-m-d") !== $this->per_frienddate->format("Y-m-d")) {
                $this->per_frienddate = $dt === null ? null : clone $dt;
                $this->modifiedColumns[PersonTableMap::COL_PER_FRIENDDATE] = true;
            }
        } // if either are not null

        return $this;
    } // setFriendDate()

    /**
     * Set the value of [per_flags] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function setFlags($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->per_flags !== $v) {
            $this->per_flags = $v;
            $this->modifiedColumns[PersonTableMap::COL_PER_FLAGS] = true;
        }

        return $this;
    } // setFlags()

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
            if ($this->per_birthmonth !== 0) {
                return false;
            }

            if ($this->per_birthday !== 0) {
                return false;
            }

            if ($this->per_gender !== false) {
                return false;
            }

            if ($this->per_fmr_id !== 0) {
                return false;
            }

            if ($this->per_cls_id !== 0) {
                return false;
            }

            if ($this->per_fam_id !== 0) {
                return false;
            }

            if ($this->per_dateentered && $this->per_dateentered->format('Y-m-d H:i:s.u') !== NULL) {
                return false;
            }

            if ($this->per_enteredby !== 0) {
                return false;
            }

            if ($this->per_editedby !== 0) {
                return false;
            }

            if ($this->per_flags !== 0) {
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

            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : PersonTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : PersonTableMap::translateFieldName('Title', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_title = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : PersonTableMap::translateFieldName('FirstName', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_firstname = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : PersonTableMap::translateFieldName('MiddleName', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_middlename = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : PersonTableMap::translateFieldName('LastName', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_lastname = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : PersonTableMap::translateFieldName('Suffix', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_suffix = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : PersonTableMap::translateFieldName('Address1', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_address1 = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : PersonTableMap::translateFieldName('Address2', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_address2 = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : PersonTableMap::translateFieldName('City', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_city = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 9 + $startcol : PersonTableMap::translateFieldName('State', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_state = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 10 + $startcol : PersonTableMap::translateFieldName('Zip', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_zip = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 11 + $startcol : PersonTableMap::translateFieldName('Country', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_country = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 12 + $startcol : PersonTableMap::translateFieldName('HomePhone', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_homephone = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 13 + $startcol : PersonTableMap::translateFieldName('WorkPhone', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_workphone = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 14 + $startcol : PersonTableMap::translateFieldName('CellPhone', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_cellphone = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 15 + $startcol : PersonTableMap::translateFieldName('Email', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_email = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 16 + $startcol : PersonTableMap::translateFieldName('WorkEmail', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_workemail = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 17 + $startcol : PersonTableMap::translateFieldName('BirthMonth', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_birthmonth = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 18 + $startcol : PersonTableMap::translateFieldName('BirthDay', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_birthday = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 19 + $startcol : PersonTableMap::translateFieldName('BirthYear', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_birthyear = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 20 + $startcol : PersonTableMap::translateFieldName('MembershipDate', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00') {
                $col = null;
            }
            $this->per_membershipdate = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 21 + $startcol : PersonTableMap::translateFieldName('Gender', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_gender = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 22 + $startcol : PersonTableMap::translateFieldName('FmrId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_fmr_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 23 + $startcol : PersonTableMap::translateFieldName('ClsId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_cls_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 24 + $startcol : PersonTableMap::translateFieldName('FamId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_fam_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 25 + $startcol : PersonTableMap::translateFieldName('Envelope', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_envelope = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 26 + $startcol : PersonTableMap::translateFieldName('DateLastEdited', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->per_datelastedited = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 27 + $startcol : PersonTableMap::translateFieldName('DateEntered', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->per_dateentered = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 28 + $startcol : PersonTableMap::translateFieldName('EnteredBy', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_enteredby = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 29 + $startcol : PersonTableMap::translateFieldName('EditedBy', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_editedby = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 30 + $startcol : PersonTableMap::translateFieldName('FriendDate', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00') {
                $col = null;
            }
            $this->per_frienddate = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 31 + $startcol : PersonTableMap::translateFieldName('Flags', TableMap::TYPE_PHPNAME, $indexType)];
            $this->per_flags = (null !== $col) ? (int) $col : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 32; // 32 = PersonTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException(sprintf('Error populating %s object', '\\ChurchCRM\\Person'), 0, $e);
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
        if ($this->aFamily !== null && $this->per_fam_id !== $this->aFamily->getId()) {
            $this->aFamily = null;
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
            $con = Propel::getServiceContainer()->getReadConnection(PersonTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildPersonQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->aFamily = null;
            $this->collWhyCames = null;

            $this->collNotes = null;

            $this->collPerson2group2roleP2g2rs = null;

        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see Person::setDeleted()
     * @see Person::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(PersonTableMap::DATABASE_NAME);
        }

        $con->transaction(function () use ($con) {
            $deleteQuery = ChildPersonQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(PersonTableMap::DATABASE_NAME);
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
                PersonTableMap::addInstanceToPool($this);
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

            if ($this->whyCamesScheduledForDeletion !== null) {
                if (!$this->whyCamesScheduledForDeletion->isEmpty()) {
                    \ChurchCRM\WhyCameQuery::create()
                        ->filterByPrimaryKeys($this->whyCamesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->whyCamesScheduledForDeletion = null;
                }
            }

            if ($this->collWhyCames !== null) {
                foreach ($this->collWhyCames as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->notesScheduledForDeletion !== null) {
                if (!$this->notesScheduledForDeletion->isEmpty()) {
                    \ChurchCRM\NoteQuery::create()
                        ->filterByPrimaryKeys($this->notesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->notesScheduledForDeletion = null;
                }
            }

            if ($this->collNotes !== null) {
                foreach ($this->collNotes as $referrerFK) {
                    if (!$referrerFK->isDeleted() && ($referrerFK->isNew() || $referrerFK->isModified())) {
                        $affectedRows += $referrerFK->save($con);
                    }
                }
            }

            if ($this->person2group2roleP2g2rsScheduledForDeletion !== null) {
                if (!$this->person2group2roleP2g2rsScheduledForDeletion->isEmpty()) {
                    \ChurchCRM\Person2group2roleP2g2rQuery::create()
                        ->filterByPrimaryKeys($this->person2group2roleP2g2rsScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->person2group2roleP2g2rsScheduledForDeletion = null;
                }
            }

            if ($this->collPerson2group2roleP2g2rs !== null) {
                foreach ($this->collPerson2group2roleP2g2rs as $referrerFK) {
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

        $this->modifiedColumns[PersonTableMap::COL_PER_ID] = true;
        if (null !== $this->per_id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . PersonTableMap::COL_PER_ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(PersonTableMap::COL_PER_ID)) {
            $modifiedColumns[':p' . $index++]  = 'per_ID';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_TITLE)) {
            $modifiedColumns[':p' . $index++]  = 'per_Title';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_FIRSTNAME)) {
            $modifiedColumns[':p' . $index++]  = 'per_FirstName';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_MIDDLENAME)) {
            $modifiedColumns[':p' . $index++]  = 'per_MiddleName';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_LASTNAME)) {
            $modifiedColumns[':p' . $index++]  = 'per_LastName';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_SUFFIX)) {
            $modifiedColumns[':p' . $index++]  = 'per_Suffix';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_ADDRESS1)) {
            $modifiedColumns[':p' . $index++]  = 'per_Address1';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_ADDRESS2)) {
            $modifiedColumns[':p' . $index++]  = 'per_Address2';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_CITY)) {
            $modifiedColumns[':p' . $index++]  = 'per_City';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_STATE)) {
            $modifiedColumns[':p' . $index++]  = 'per_State';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_ZIP)) {
            $modifiedColumns[':p' . $index++]  = 'per_Zip';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_COUNTRY)) {
            $modifiedColumns[':p' . $index++]  = 'per_Country';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_HOMEPHONE)) {
            $modifiedColumns[':p' . $index++]  = 'per_HomePhone';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_WORKPHONE)) {
            $modifiedColumns[':p' . $index++]  = 'per_WorkPhone';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_CELLPHONE)) {
            $modifiedColumns[':p' . $index++]  = 'per_CellPhone';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_EMAIL)) {
            $modifiedColumns[':p' . $index++]  = 'per_Email';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_WORKEMAIL)) {
            $modifiedColumns[':p' . $index++]  = 'per_WorkEmail';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_BIRTHMONTH)) {
            $modifiedColumns[':p' . $index++]  = 'per_BirthMonth';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_BIRTHDAY)) {
            $modifiedColumns[':p' . $index++]  = 'per_BirthDay';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_BIRTHYEAR)) {
            $modifiedColumns[':p' . $index++]  = 'per_BirthYear';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_MEMBERSHIPDATE)) {
            $modifiedColumns[':p' . $index++]  = 'per_MembershipDate';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_GENDER)) {
            $modifiedColumns[':p' . $index++]  = 'per_Gender';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_FMR_ID)) {
            $modifiedColumns[':p' . $index++]  = 'per_fmr_ID';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_CLS_ID)) {
            $modifiedColumns[':p' . $index++]  = 'per_cls_ID';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_FAM_ID)) {
            $modifiedColumns[':p' . $index++]  = 'per_fam_ID';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_ENVELOPE)) {
            $modifiedColumns[':p' . $index++]  = 'per_Envelope';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_DATELASTEDITED)) {
            $modifiedColumns[':p' . $index++]  = 'per_DateLastEdited';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_DATEENTERED)) {
            $modifiedColumns[':p' . $index++]  = 'per_DateEntered';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_ENTEREDBY)) {
            $modifiedColumns[':p' . $index++]  = 'per_EnteredBy';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_EDITEDBY)) {
            $modifiedColumns[':p' . $index++]  = 'per_EditedBy';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_FRIENDDATE)) {
            $modifiedColumns[':p' . $index++]  = 'per_FriendDate';
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_FLAGS)) {
            $modifiedColumns[':p' . $index++]  = 'per_Flags';
        }

        $sql = sprintf(
            'INSERT INTO person_per (%s) VALUES (%s)',
            implode(', ', $modifiedColumns),
            implode(', ', array_keys($modifiedColumns))
        );

        try {
            $stmt = $con->prepare($sql);
            foreach ($modifiedColumns as $identifier => $columnName) {
                switch ($columnName) {
                    case 'per_ID':
                        $stmt->bindValue($identifier, $this->per_id, PDO::PARAM_INT);
                        break;
                    case 'per_Title':
                        $stmt->bindValue($identifier, $this->per_title, PDO::PARAM_STR);
                        break;
                    case 'per_FirstName':
                        $stmt->bindValue($identifier, $this->per_firstname, PDO::PARAM_STR);
                        break;
                    case 'per_MiddleName':
                        $stmt->bindValue($identifier, $this->per_middlename, PDO::PARAM_STR);
                        break;
                    case 'per_LastName':
                        $stmt->bindValue($identifier, $this->per_lastname, PDO::PARAM_STR);
                        break;
                    case 'per_Suffix':
                        $stmt->bindValue($identifier, $this->per_suffix, PDO::PARAM_STR);
                        break;
                    case 'per_Address1':
                        $stmt->bindValue($identifier, $this->per_address1, PDO::PARAM_STR);
                        break;
                    case 'per_Address2':
                        $stmt->bindValue($identifier, $this->per_address2, PDO::PARAM_STR);
                        break;
                    case 'per_City':
                        $stmt->bindValue($identifier, $this->per_city, PDO::PARAM_STR);
                        break;
                    case 'per_State':
                        $stmt->bindValue($identifier, $this->per_state, PDO::PARAM_STR);
                        break;
                    case 'per_Zip':
                        $stmt->bindValue($identifier, $this->per_zip, PDO::PARAM_STR);
                        break;
                    case 'per_Country':
                        $stmt->bindValue($identifier, $this->per_country, PDO::PARAM_STR);
                        break;
                    case 'per_HomePhone':
                        $stmt->bindValue($identifier, $this->per_homephone, PDO::PARAM_STR);
                        break;
                    case 'per_WorkPhone':
                        $stmt->bindValue($identifier, $this->per_workphone, PDO::PARAM_STR);
                        break;
                    case 'per_CellPhone':
                        $stmt->bindValue($identifier, $this->per_cellphone, PDO::PARAM_STR);
                        break;
                    case 'per_Email':
                        $stmt->bindValue($identifier, $this->per_email, PDO::PARAM_STR);
                        break;
                    case 'per_WorkEmail':
                        $stmt->bindValue($identifier, $this->per_workemail, PDO::PARAM_STR);
                        break;
                    case 'per_BirthMonth':
                        $stmt->bindValue($identifier, $this->per_birthmonth, PDO::PARAM_INT);
                        break;
                    case 'per_BirthDay':
                        $stmt->bindValue($identifier, $this->per_birthday, PDO::PARAM_INT);
                        break;
                    case 'per_BirthYear':
                        $stmt->bindValue($identifier, $this->per_birthyear, PDO::PARAM_INT);
                        break;
                    case 'per_MembershipDate':
                        $stmt->bindValue($identifier, $this->per_membershipdate ? $this->per_membershipdate->format("Y-m-d H:i:s.u") : null, PDO::PARAM_STR);
                        break;
                    case 'per_Gender':
                        $stmt->bindValue($identifier, (int) $this->per_gender, PDO::PARAM_INT);
                        break;
                    case 'per_fmr_ID':
                        $stmt->bindValue($identifier, $this->per_fmr_id, PDO::PARAM_INT);
                        break;
                    case 'per_cls_ID':
                        $stmt->bindValue($identifier, $this->per_cls_id, PDO::PARAM_INT);
                        break;
                    case 'per_fam_ID':
                        $stmt->bindValue($identifier, $this->per_fam_id, PDO::PARAM_INT);
                        break;
                    case 'per_Envelope':
                        $stmt->bindValue($identifier, $this->per_envelope, PDO::PARAM_INT);
                        break;
                    case 'per_DateLastEdited':
                        $stmt->bindValue($identifier, $this->per_datelastedited ? $this->per_datelastedited->format("Y-m-d H:i:s.u") : null, PDO::PARAM_STR);
                        break;
                    case 'per_DateEntered':
                        $stmt->bindValue($identifier, $this->per_dateentered ? $this->per_dateentered->format("Y-m-d H:i:s.u") : null, PDO::PARAM_STR);
                        break;
                    case 'per_EnteredBy':
                        $stmt->bindValue($identifier, $this->per_enteredby, PDO::PARAM_INT);
                        break;
                    case 'per_EditedBy':
                        $stmt->bindValue($identifier, $this->per_editedby, PDO::PARAM_INT);
                        break;
                    case 'per_FriendDate':
                        $stmt->bindValue($identifier, $this->per_frienddate ? $this->per_frienddate->format("Y-m-d H:i:s.u") : null, PDO::PARAM_STR);
                        break;
                    case 'per_Flags':
                        $stmt->bindValue($identifier, $this->per_flags, PDO::PARAM_INT);
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
        $pos = PersonTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getTitle();
                break;
            case 2:
                return $this->getFirstName();
                break;
            case 3:
                return $this->getMiddleName();
                break;
            case 4:
                return $this->getLastName();
                break;
            case 5:
                return $this->getSuffix();
                break;
            case 6:
                return $this->getAddress1();
                break;
            case 7:
                return $this->getAddress2();
                break;
            case 8:
                return $this->getCity();
                break;
            case 9:
                return $this->getState();
                break;
            case 10:
                return $this->getZip();
                break;
            case 11:
                return $this->getCountry();
                break;
            case 12:
                return $this->getHomePhone();
                break;
            case 13:
                return $this->getWorkPhone();
                break;
            case 14:
                return $this->getCellPhone();
                break;
            case 15:
                return $this->getEmail();
                break;
            case 16:
                return $this->getWorkEmail();
                break;
            case 17:
                return $this->getBirthMonth();
                break;
            case 18:
                return $this->getBirthDay();
                break;
            case 19:
                return $this->getBirthYear();
                break;
            case 20:
                return $this->getMembershipDate();
                break;
            case 21:
                return $this->getGender();
                break;
            case 22:
                return $this->getFmrId();
                break;
            case 23:
                return $this->getClsId();
                break;
            case 24:
                return $this->getFamId();
                break;
            case 25:
                return $this->getEnvelope();
                break;
            case 26:
                return $this->getDateLastEdited();
                break;
            case 27:
                return $this->getDateEntered();
                break;
            case 28:
                return $this->getEnteredBy();
                break;
            case 29:
                return $this->getEditedBy();
                break;
            case 30:
                return $this->getFriendDate();
                break;
            case 31:
                return $this->getFlags();
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

        if (isset($alreadyDumpedObjects['Person'][$this->hashCode()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Person'][$this->hashCode()] = true;
        $keys = PersonTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getTitle(),
            $keys[2] => $this->getFirstName(),
            $keys[3] => $this->getMiddleName(),
            $keys[4] => $this->getLastName(),
            $keys[5] => $this->getSuffix(),
            $keys[6] => $this->getAddress1(),
            $keys[7] => $this->getAddress2(),
            $keys[8] => $this->getCity(),
            $keys[9] => $this->getState(),
            $keys[10] => $this->getZip(),
            $keys[11] => $this->getCountry(),
            $keys[12] => $this->getHomePhone(),
            $keys[13] => $this->getWorkPhone(),
            $keys[14] => $this->getCellPhone(),
            $keys[15] => $this->getEmail(),
            $keys[16] => $this->getWorkEmail(),
            $keys[17] => $this->getBirthMonth(),
            $keys[18] => $this->getBirthDay(),
            $keys[19] => $this->getBirthYear(),
            $keys[20] => $this->getMembershipDate(),
            $keys[21] => $this->getGender(),
            $keys[22] => $this->getFmrId(),
            $keys[23] => $this->getClsId(),
            $keys[24] => $this->getFamId(),
            $keys[25] => $this->getEnvelope(),
            $keys[26] => $this->getDateLastEdited(),
            $keys[27] => $this->getDateEntered(),
            $keys[28] => $this->getEnteredBy(),
            $keys[29] => $this->getEditedBy(),
            $keys[30] => $this->getFriendDate(),
            $keys[31] => $this->getFlags(),
        );
        if ($result[$keys[20]] instanceof \DateTime) {
            $result[$keys[20]] = $result[$keys[20]]->format('c');
        }

        if ($result[$keys[26]] instanceof \DateTime) {
            $result[$keys[26]] = $result[$keys[26]]->format('c');
        }

        if ($result[$keys[27]] instanceof \DateTime) {
            $result[$keys[27]] = $result[$keys[27]]->format('c');
        }

        if ($result[$keys[30]] instanceof \DateTime) {
            $result[$keys[30]] = $result[$keys[30]]->format('c');
        }

        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
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
            if (null !== $this->collWhyCames) {

                switch ($keyType) {
                    case TableMap::TYPE_CAMELNAME:
                        $key = 'whyCames';
                        break;
                    case TableMap::TYPE_FIELDNAME:
                        $key = 'whycame_whies';
                        break;
                    default:
                        $key = 'WhyCames';
                }

                $result[$key] = $this->collWhyCames->toArray(null, false, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collNotes) {

                switch ($keyType) {
                    case TableMap::TYPE_CAMELNAME:
                        $key = 'notes';
                        break;
                    case TableMap::TYPE_FIELDNAME:
                        $key = 'note_ntes';
                        break;
                    default:
                        $key = 'Notes';
                }

                $result[$key] = $this->collNotes->toArray(null, false, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
            }
            if (null !== $this->collPerson2group2roleP2g2rs) {

                switch ($keyType) {
                    case TableMap::TYPE_CAMELNAME:
                        $key = 'person2group2roleP2g2rs';
                        break;
                    case TableMap::TYPE_FIELDNAME:
                        $key = 'person2group2role_p2g2rs';
                        break;
                    default:
                        $key = 'Person2group2roleP2g2rs';
                }

                $result[$key] = $this->collPerson2group2roleP2g2rs->toArray(null, false, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
     * @return $this|\ChurchCRM\Person
     */
    public function setByName($name, $value, $type = TableMap::TYPE_PHPNAME)
    {
        $pos = PersonTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

        return $this->setByPosition($pos, $value);
    }

    /**
     * Sets a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param  int $pos position in xml schema
     * @param  mixed $value field value
     * @return $this|\ChurchCRM\Person
     */
    public function setByPosition($pos, $value)
    {
        switch ($pos) {
            case 0:
                $this->setId($value);
                break;
            case 1:
                $this->setTitle($value);
                break;
            case 2:
                $this->setFirstName($value);
                break;
            case 3:
                $this->setMiddleName($value);
                break;
            case 4:
                $this->setLastName($value);
                break;
            case 5:
                $this->setSuffix($value);
                break;
            case 6:
                $this->setAddress1($value);
                break;
            case 7:
                $this->setAddress2($value);
                break;
            case 8:
                $this->setCity($value);
                break;
            case 9:
                $this->setState($value);
                break;
            case 10:
                $this->setZip($value);
                break;
            case 11:
                $this->setCountry($value);
                break;
            case 12:
                $this->setHomePhone($value);
                break;
            case 13:
                $this->setWorkPhone($value);
                break;
            case 14:
                $this->setCellPhone($value);
                break;
            case 15:
                $this->setEmail($value);
                break;
            case 16:
                $this->setWorkEmail($value);
                break;
            case 17:
                $this->setBirthMonth($value);
                break;
            case 18:
                $this->setBirthDay($value);
                break;
            case 19:
                $this->setBirthYear($value);
                break;
            case 20:
                $this->setMembershipDate($value);
                break;
            case 21:
                $this->setGender($value);
                break;
            case 22:
                $this->setFmrId($value);
                break;
            case 23:
                $this->setClsId($value);
                break;
            case 24:
                $this->setFamId($value);
                break;
            case 25:
                $this->setEnvelope($value);
                break;
            case 26:
                $this->setDateLastEdited($value);
                break;
            case 27:
                $this->setDateEntered($value);
                break;
            case 28:
                $this->setEnteredBy($value);
                break;
            case 29:
                $this->setEditedBy($value);
                break;
            case 30:
                $this->setFriendDate($value);
                break;
            case 31:
                $this->setFlags($value);
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
        $keys = PersonTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) {
            $this->setId($arr[$keys[0]]);
        }
        if (array_key_exists($keys[1], $arr)) {
            $this->setTitle($arr[$keys[1]]);
        }
        if (array_key_exists($keys[2], $arr)) {
            $this->setFirstName($arr[$keys[2]]);
        }
        if (array_key_exists($keys[3], $arr)) {
            $this->setMiddleName($arr[$keys[3]]);
        }
        if (array_key_exists($keys[4], $arr)) {
            $this->setLastName($arr[$keys[4]]);
        }
        if (array_key_exists($keys[5], $arr)) {
            $this->setSuffix($arr[$keys[5]]);
        }
        if (array_key_exists($keys[6], $arr)) {
            $this->setAddress1($arr[$keys[6]]);
        }
        if (array_key_exists($keys[7], $arr)) {
            $this->setAddress2($arr[$keys[7]]);
        }
        if (array_key_exists($keys[8], $arr)) {
            $this->setCity($arr[$keys[8]]);
        }
        if (array_key_exists($keys[9], $arr)) {
            $this->setState($arr[$keys[9]]);
        }
        if (array_key_exists($keys[10], $arr)) {
            $this->setZip($arr[$keys[10]]);
        }
        if (array_key_exists($keys[11], $arr)) {
            $this->setCountry($arr[$keys[11]]);
        }
        if (array_key_exists($keys[12], $arr)) {
            $this->setHomePhone($arr[$keys[12]]);
        }
        if (array_key_exists($keys[13], $arr)) {
            $this->setWorkPhone($arr[$keys[13]]);
        }
        if (array_key_exists($keys[14], $arr)) {
            $this->setCellPhone($arr[$keys[14]]);
        }
        if (array_key_exists($keys[15], $arr)) {
            $this->setEmail($arr[$keys[15]]);
        }
        if (array_key_exists($keys[16], $arr)) {
            $this->setWorkEmail($arr[$keys[16]]);
        }
        if (array_key_exists($keys[17], $arr)) {
            $this->setBirthMonth($arr[$keys[17]]);
        }
        if (array_key_exists($keys[18], $arr)) {
            $this->setBirthDay($arr[$keys[18]]);
        }
        if (array_key_exists($keys[19], $arr)) {
            $this->setBirthYear($arr[$keys[19]]);
        }
        if (array_key_exists($keys[20], $arr)) {
            $this->setMembershipDate($arr[$keys[20]]);
        }
        if (array_key_exists($keys[21], $arr)) {
            $this->setGender($arr[$keys[21]]);
        }
        if (array_key_exists($keys[22], $arr)) {
            $this->setFmrId($arr[$keys[22]]);
        }
        if (array_key_exists($keys[23], $arr)) {
            $this->setClsId($arr[$keys[23]]);
        }
        if (array_key_exists($keys[24], $arr)) {
            $this->setFamId($arr[$keys[24]]);
        }
        if (array_key_exists($keys[25], $arr)) {
            $this->setEnvelope($arr[$keys[25]]);
        }
        if (array_key_exists($keys[26], $arr)) {
            $this->setDateLastEdited($arr[$keys[26]]);
        }
        if (array_key_exists($keys[27], $arr)) {
            $this->setDateEntered($arr[$keys[27]]);
        }
        if (array_key_exists($keys[28], $arr)) {
            $this->setEnteredBy($arr[$keys[28]]);
        }
        if (array_key_exists($keys[29], $arr)) {
            $this->setEditedBy($arr[$keys[29]]);
        }
        if (array_key_exists($keys[30], $arr)) {
            $this->setFriendDate($arr[$keys[30]]);
        }
        if (array_key_exists($keys[31], $arr)) {
            $this->setFlags($arr[$keys[31]]);
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
     * @return $this|\ChurchCRM\Person The current object, for fluid interface
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
        $criteria = new Criteria(PersonTableMap::DATABASE_NAME);

        if ($this->isColumnModified(PersonTableMap::COL_PER_ID)) {
            $criteria->add(PersonTableMap::COL_PER_ID, $this->per_id);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_TITLE)) {
            $criteria->add(PersonTableMap::COL_PER_TITLE, $this->per_title);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_FIRSTNAME)) {
            $criteria->add(PersonTableMap::COL_PER_FIRSTNAME, $this->per_firstname);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_MIDDLENAME)) {
            $criteria->add(PersonTableMap::COL_PER_MIDDLENAME, $this->per_middlename);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_LASTNAME)) {
            $criteria->add(PersonTableMap::COL_PER_LASTNAME, $this->per_lastname);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_SUFFIX)) {
            $criteria->add(PersonTableMap::COL_PER_SUFFIX, $this->per_suffix);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_ADDRESS1)) {
            $criteria->add(PersonTableMap::COL_PER_ADDRESS1, $this->per_address1);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_ADDRESS2)) {
            $criteria->add(PersonTableMap::COL_PER_ADDRESS2, $this->per_address2);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_CITY)) {
            $criteria->add(PersonTableMap::COL_PER_CITY, $this->per_city);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_STATE)) {
            $criteria->add(PersonTableMap::COL_PER_STATE, $this->per_state);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_ZIP)) {
            $criteria->add(PersonTableMap::COL_PER_ZIP, $this->per_zip);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_COUNTRY)) {
            $criteria->add(PersonTableMap::COL_PER_COUNTRY, $this->per_country);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_HOMEPHONE)) {
            $criteria->add(PersonTableMap::COL_PER_HOMEPHONE, $this->per_homephone);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_WORKPHONE)) {
            $criteria->add(PersonTableMap::COL_PER_WORKPHONE, $this->per_workphone);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_CELLPHONE)) {
            $criteria->add(PersonTableMap::COL_PER_CELLPHONE, $this->per_cellphone);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_EMAIL)) {
            $criteria->add(PersonTableMap::COL_PER_EMAIL, $this->per_email);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_WORKEMAIL)) {
            $criteria->add(PersonTableMap::COL_PER_WORKEMAIL, $this->per_workemail);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_BIRTHMONTH)) {
            $criteria->add(PersonTableMap::COL_PER_BIRTHMONTH, $this->per_birthmonth);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_BIRTHDAY)) {
            $criteria->add(PersonTableMap::COL_PER_BIRTHDAY, $this->per_birthday);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_BIRTHYEAR)) {
            $criteria->add(PersonTableMap::COL_PER_BIRTHYEAR, $this->per_birthyear);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_MEMBERSHIPDATE)) {
            $criteria->add(PersonTableMap::COL_PER_MEMBERSHIPDATE, $this->per_membershipdate);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_GENDER)) {
            $criteria->add(PersonTableMap::COL_PER_GENDER, $this->per_gender);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_FMR_ID)) {
            $criteria->add(PersonTableMap::COL_PER_FMR_ID, $this->per_fmr_id);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_CLS_ID)) {
            $criteria->add(PersonTableMap::COL_PER_CLS_ID, $this->per_cls_id);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_FAM_ID)) {
            $criteria->add(PersonTableMap::COL_PER_FAM_ID, $this->per_fam_id);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_ENVELOPE)) {
            $criteria->add(PersonTableMap::COL_PER_ENVELOPE, $this->per_envelope);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_DATELASTEDITED)) {
            $criteria->add(PersonTableMap::COL_PER_DATELASTEDITED, $this->per_datelastedited);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_DATEENTERED)) {
            $criteria->add(PersonTableMap::COL_PER_DATEENTERED, $this->per_dateentered);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_ENTEREDBY)) {
            $criteria->add(PersonTableMap::COL_PER_ENTEREDBY, $this->per_enteredby);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_EDITEDBY)) {
            $criteria->add(PersonTableMap::COL_PER_EDITEDBY, $this->per_editedby);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_FRIENDDATE)) {
            $criteria->add(PersonTableMap::COL_PER_FRIENDDATE, $this->per_frienddate);
        }
        if ($this->isColumnModified(PersonTableMap::COL_PER_FLAGS)) {
            $criteria->add(PersonTableMap::COL_PER_FLAGS, $this->per_flags);
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
        $criteria = ChildPersonQuery::create();
        $criteria->add(PersonTableMap::COL_PER_ID, $this->per_id);

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
     * Generic method to set the primary key (per_id column).
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
     * @param      object $copyObj An object of \ChurchCRM\Person (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setTitle($this->getTitle());
        $copyObj->setFirstName($this->getFirstName());
        $copyObj->setMiddleName($this->getMiddleName());
        $copyObj->setLastName($this->getLastName());
        $copyObj->setSuffix($this->getSuffix());
        $copyObj->setAddress1($this->getAddress1());
        $copyObj->setAddress2($this->getAddress2());
        $copyObj->setCity($this->getCity());
        $copyObj->setState($this->getState());
        $copyObj->setZip($this->getZip());
        $copyObj->setCountry($this->getCountry());
        $copyObj->setHomePhone($this->getHomePhone());
        $copyObj->setWorkPhone($this->getWorkPhone());
        $copyObj->setCellPhone($this->getCellPhone());
        $copyObj->setEmail($this->getEmail());
        $copyObj->setWorkEmail($this->getWorkEmail());
        $copyObj->setBirthMonth($this->getBirthMonth());
        $copyObj->setBirthDay($this->getBirthDay());
        $copyObj->setBirthYear($this->getBirthYear());
        $copyObj->setMembershipDate($this->getMembershipDate());
        $copyObj->setGender($this->getGender());
        $copyObj->setFmrId($this->getFmrId());
        $copyObj->setClsId($this->getClsId());
        $copyObj->setFamId($this->getFamId());
        $copyObj->setEnvelope($this->getEnvelope());
        $copyObj->setDateLastEdited($this->getDateLastEdited());
        $copyObj->setDateEntered($this->getDateEntered());
        $copyObj->setEnteredBy($this->getEnteredBy());
        $copyObj->setEditedBy($this->getEditedBy());
        $copyObj->setFriendDate($this->getFriendDate());
        $copyObj->setFlags($this->getFlags());

        if ($deepCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);

            foreach ($this->getWhyCames() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addWhyCame($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getNotes() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addNote($relObj->copy($deepCopy));
                }
            }

            foreach ($this->getPerson2group2roleP2g2rs() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addPerson2group2roleP2g2r($relObj->copy($deepCopy));
                }
            }

        } // if ($deepCopy)

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
     * @return \ChurchCRM\Person Clone of current object.
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
     * Declares an association between this object and a ChildFamily object.
     *
     * @param  ChildFamily $v
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     * @throws PropelException
     */
    public function setFamily(ChildFamily $v = null)
    {
        if ($v === null) {
            $this->setFamId(0);
        } else {
            $this->setFamId($v->getId());
        }

        $this->aFamily = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the ChildFamily object, it will not be re-added.
        if ($v !== null) {
            $v->addPerson($this);
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
        if ($this->aFamily === null && ($this->per_fam_id !== null)) {
            $this->aFamily = ChildFamilyQuery::create()->findPk($this->per_fam_id, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aFamily->addPeople($this);
             */
        }

        return $this->aFamily;
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
        if ('WhyCame' == $relationName) {
            return $this->initWhyCames();
        }
        if ('Note' == $relationName) {
            return $this->initNotes();
        }
        if ('Person2group2roleP2g2r' == $relationName) {
            return $this->initPerson2group2roleP2g2rs();
        }
    }

    /**
     * Clears out the collWhyCames collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addWhyCames()
     */
    public function clearWhyCames()
    {
        $this->collWhyCames = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collWhyCames collection loaded partially.
     */
    public function resetPartialWhyCames($v = true)
    {
        $this->collWhyCamesPartial = $v;
    }

    /**
     * Initializes the collWhyCames collection.
     *
     * By default this just sets the collWhyCames collection to an empty array (like clearcollWhyCames());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initWhyCames($overrideExisting = true)
    {
        if (null !== $this->collWhyCames && !$overrideExisting) {
            return;
        }

        $collectionClassName = WhyCameTableMap::getTableMap()->getCollectionClassName();

        $this->collWhyCames = new $collectionClassName;
        $this->collWhyCames->setModel('\ChurchCRM\WhyCame');
    }

    /**
     * Gets an array of ChildWhyCame objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildPerson is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return ObjectCollection|ChildWhyCame[] List of ChildWhyCame objects
     * @throws PropelException
     */
    public function getWhyCames(Criteria $criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collWhyCamesPartial && !$this->isNew();
        if (null === $this->collWhyCames || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collWhyCames) {
                // return empty collection
                $this->initWhyCames();
            } else {
                $collWhyCames = ChildWhyCameQuery::create(null, $criteria)
                    ->filterByPerson($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collWhyCamesPartial && count($collWhyCames)) {
                        $this->initWhyCames(false);

                        foreach ($collWhyCames as $obj) {
                            if (false == $this->collWhyCames->contains($obj)) {
                                $this->collWhyCames->append($obj);
                            }
                        }

                        $this->collWhyCamesPartial = true;
                    }

                    return $collWhyCames;
                }

                if ($partial && $this->collWhyCames) {
                    foreach ($this->collWhyCames as $obj) {
                        if ($obj->isNew()) {
                            $collWhyCames[] = $obj;
                        }
                    }
                }

                $this->collWhyCames = $collWhyCames;
                $this->collWhyCamesPartial = false;
            }
        }

        return $this->collWhyCames;
    }

    /**
     * Sets a collection of ChildWhyCame objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $whyCames A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return $this|ChildPerson The current object (for fluent API support)
     */
    public function setWhyCames(Collection $whyCames, ConnectionInterface $con = null)
    {
        /** @var ChildWhyCame[] $whyCamesToDelete */
        $whyCamesToDelete = $this->getWhyCames(new Criteria(), $con)->diff($whyCames);


        $this->whyCamesScheduledForDeletion = $whyCamesToDelete;

        foreach ($whyCamesToDelete as $whyCameRemoved) {
            $whyCameRemoved->setPerson(null);
        }

        $this->collWhyCames = null;
        foreach ($whyCames as $whyCame) {
            $this->addWhyCame($whyCame);
        }

        $this->collWhyCames = $whyCames;
        $this->collWhyCamesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related WhyCame objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related WhyCame objects.
     * @throws PropelException
     */
    public function countWhyCames(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collWhyCamesPartial && !$this->isNew();
        if (null === $this->collWhyCames || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collWhyCames) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getWhyCames());
            }

            $query = ChildWhyCameQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByPerson($this)
                ->count($con);
        }

        return count($this->collWhyCames);
    }

    /**
     * Method called to associate a ChildWhyCame object to this object
     * through the ChildWhyCame foreign key attribute.
     *
     * @param  ChildWhyCame $l ChildWhyCame
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function addWhyCame(ChildWhyCame $l)
    {
        if ($this->collWhyCames === null) {
            $this->initWhyCames();
            $this->collWhyCamesPartial = true;
        }

        if (!$this->collWhyCames->contains($l)) {
            $this->doAddWhyCame($l);

            if ($this->whyCamesScheduledForDeletion and $this->whyCamesScheduledForDeletion->contains($l)) {
                $this->whyCamesScheduledForDeletion->remove($this->whyCamesScheduledForDeletion->search($l));
            }
        }

        return $this;
    }

    /**
     * @param ChildWhyCame $whyCame The ChildWhyCame object to add.
     */
    protected function doAddWhyCame(ChildWhyCame $whyCame)
    {
        $this->collWhyCames[]= $whyCame;
        $whyCame->setPerson($this);
    }

    /**
     * @param  ChildWhyCame $whyCame The ChildWhyCame object to remove.
     * @return $this|ChildPerson The current object (for fluent API support)
     */
    public function removeWhyCame(ChildWhyCame $whyCame)
    {
        if ($this->getWhyCames()->contains($whyCame)) {
            $pos = $this->collWhyCames->search($whyCame);
            $this->collWhyCames->remove($pos);
            if (null === $this->whyCamesScheduledForDeletion) {
                $this->whyCamesScheduledForDeletion = clone $this->collWhyCames;
                $this->whyCamesScheduledForDeletion->clear();
            }
            $this->whyCamesScheduledForDeletion[]= clone $whyCame;
            $whyCame->setPerson(null);
        }

        return $this;
    }

    /**
     * Clears out the collNotes collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addNotes()
     */
    public function clearNotes()
    {
        $this->collNotes = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collNotes collection loaded partially.
     */
    public function resetPartialNotes($v = true)
    {
        $this->collNotesPartial = $v;
    }

    /**
     * Initializes the collNotes collection.
     *
     * By default this just sets the collNotes collection to an empty array (like clearcollNotes());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initNotes($overrideExisting = true)
    {
        if (null !== $this->collNotes && !$overrideExisting) {
            return;
        }

        $collectionClassName = NoteTableMap::getTableMap()->getCollectionClassName();

        $this->collNotes = new $collectionClassName;
        $this->collNotes->setModel('\ChurchCRM\Note');
    }

    /**
     * Gets an array of ChildNote objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildPerson is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return ObjectCollection|ChildNote[] List of ChildNote objects
     * @throws PropelException
     */
    public function getNotes(Criteria $criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collNotesPartial && !$this->isNew();
        if (null === $this->collNotes || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collNotes) {
                // return empty collection
                $this->initNotes();
            } else {
                $collNotes = ChildNoteQuery::create(null, $criteria)
                    ->filterByPerson($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collNotesPartial && count($collNotes)) {
                        $this->initNotes(false);

                        foreach ($collNotes as $obj) {
                            if (false == $this->collNotes->contains($obj)) {
                                $this->collNotes->append($obj);
                            }
                        }

                        $this->collNotesPartial = true;
                    }

                    return $collNotes;
                }

                if ($partial && $this->collNotes) {
                    foreach ($this->collNotes as $obj) {
                        if ($obj->isNew()) {
                            $collNotes[] = $obj;
                        }
                    }
                }

                $this->collNotes = $collNotes;
                $this->collNotesPartial = false;
            }
        }

        return $this->collNotes;
    }

    /**
     * Sets a collection of ChildNote objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $notes A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return $this|ChildPerson The current object (for fluent API support)
     */
    public function setNotes(Collection $notes, ConnectionInterface $con = null)
    {
        /** @var ChildNote[] $notesToDelete */
        $notesToDelete = $this->getNotes(new Criteria(), $con)->diff($notes);


        $this->notesScheduledForDeletion = $notesToDelete;

        foreach ($notesToDelete as $noteRemoved) {
            $noteRemoved->setPerson(null);
        }

        $this->collNotes = null;
        foreach ($notes as $note) {
            $this->addNote($note);
        }

        $this->collNotes = $notes;
        $this->collNotesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related Note objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related Note objects.
     * @throws PropelException
     */
    public function countNotes(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collNotesPartial && !$this->isNew();
        if (null === $this->collNotes || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collNotes) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getNotes());
            }

            $query = ChildNoteQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByPerson($this)
                ->count($con);
        }

        return count($this->collNotes);
    }

    /**
     * Method called to associate a ChildNote object to this object
     * through the ChildNote foreign key attribute.
     *
     * @param  ChildNote $l ChildNote
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function addNote(ChildNote $l)
    {
        if ($this->collNotes === null) {
            $this->initNotes();
            $this->collNotesPartial = true;
        }

        if (!$this->collNotes->contains($l)) {
            $this->doAddNote($l);

            if ($this->notesScheduledForDeletion and $this->notesScheduledForDeletion->contains($l)) {
                $this->notesScheduledForDeletion->remove($this->notesScheduledForDeletion->search($l));
            }
        }

        return $this;
    }

    /**
     * @param ChildNote $note The ChildNote object to add.
     */
    protected function doAddNote(ChildNote $note)
    {
        $this->collNotes[]= $note;
        $note->setPerson($this);
    }

    /**
     * @param  ChildNote $note The ChildNote object to remove.
     * @return $this|ChildPerson The current object (for fluent API support)
     */
    public function removeNote(ChildNote $note)
    {
        if ($this->getNotes()->contains($note)) {
            $pos = $this->collNotes->search($note);
            $this->collNotes->remove($pos);
            if (null === $this->notesScheduledForDeletion) {
                $this->notesScheduledForDeletion = clone $this->collNotes;
                $this->notesScheduledForDeletion->clear();
            }
            $this->notesScheduledForDeletion[]= clone $note;
            $note->setPerson(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Person is new, it will return
     * an empty collection; or if this Person has previously
     * been saved, it will retrieve related Notes from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Person.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return ObjectCollection|ChildNote[] List of ChildNote objects
     */
    public function getNotesJoinFamily(Criteria $criteria = null, ConnectionInterface $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildNoteQuery::create(null, $criteria);
        $query->joinWith('Family', $joinBehavior);

        return $this->getNotes($query, $con);
    }

    /**
     * Clears out the collPerson2group2roleP2g2rs collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addPerson2group2roleP2g2rs()
     */
    public function clearPerson2group2roleP2g2rs()
    {
        $this->collPerson2group2roleP2g2rs = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collPerson2group2roleP2g2rs collection loaded partially.
     */
    public function resetPartialPerson2group2roleP2g2rs($v = true)
    {
        $this->collPerson2group2roleP2g2rsPartial = $v;
    }

    /**
     * Initializes the collPerson2group2roleP2g2rs collection.
     *
     * By default this just sets the collPerson2group2roleP2g2rs collection to an empty array (like clearcollPerson2group2roleP2g2rs());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initPerson2group2roleP2g2rs($overrideExisting = true)
    {
        if (null !== $this->collPerson2group2roleP2g2rs && !$overrideExisting) {
            return;
        }

        $collectionClassName = Person2group2roleP2g2rTableMap::getTableMap()->getCollectionClassName();

        $this->collPerson2group2roleP2g2rs = new $collectionClassName;
        $this->collPerson2group2roleP2g2rs->setModel('\ChurchCRM\Person2group2roleP2g2r');
    }

    /**
     * Gets an array of ChildPerson2group2roleP2g2r objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildPerson is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return ObjectCollection|ChildPerson2group2roleP2g2r[] List of ChildPerson2group2roleP2g2r objects
     * @throws PropelException
     */
    public function getPerson2group2roleP2g2rs(Criteria $criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collPerson2group2roleP2g2rsPartial && !$this->isNew();
        if (null === $this->collPerson2group2roleP2g2rs || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collPerson2group2roleP2g2rs) {
                // return empty collection
                $this->initPerson2group2roleP2g2rs();
            } else {
                $collPerson2group2roleP2g2rs = ChildPerson2group2roleP2g2rQuery::create(null, $criteria)
                    ->filterByPerson($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collPerson2group2roleP2g2rsPartial && count($collPerson2group2roleP2g2rs)) {
                        $this->initPerson2group2roleP2g2rs(false);

                        foreach ($collPerson2group2roleP2g2rs as $obj) {
                            if (false == $this->collPerson2group2roleP2g2rs->contains($obj)) {
                                $this->collPerson2group2roleP2g2rs->append($obj);
                            }
                        }

                        $this->collPerson2group2roleP2g2rsPartial = true;
                    }

                    return $collPerson2group2roleP2g2rs;
                }

                if ($partial && $this->collPerson2group2roleP2g2rs) {
                    foreach ($this->collPerson2group2roleP2g2rs as $obj) {
                        if ($obj->isNew()) {
                            $collPerson2group2roleP2g2rs[] = $obj;
                        }
                    }
                }

                $this->collPerson2group2roleP2g2rs = $collPerson2group2roleP2g2rs;
                $this->collPerson2group2roleP2g2rsPartial = false;
            }
        }

        return $this->collPerson2group2roleP2g2rs;
    }

    /**
     * Sets a collection of ChildPerson2group2roleP2g2r objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $person2group2roleP2g2rs A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return $this|ChildPerson The current object (for fluent API support)
     */
    public function setPerson2group2roleP2g2rs(Collection $person2group2roleP2g2rs, ConnectionInterface $con = null)
    {
        /** @var ChildPerson2group2roleP2g2r[] $person2group2roleP2g2rsToDelete */
        $person2group2roleP2g2rsToDelete = $this->getPerson2group2roleP2g2rs(new Criteria(), $con)->diff($person2group2roleP2g2rs);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->person2group2roleP2g2rsScheduledForDeletion = clone $person2group2roleP2g2rsToDelete;

        foreach ($person2group2roleP2g2rsToDelete as $person2group2roleP2g2rRemoved) {
            $person2group2roleP2g2rRemoved->setPerson(null);
        }

        $this->collPerson2group2roleP2g2rs = null;
        foreach ($person2group2roleP2g2rs as $person2group2roleP2g2r) {
            $this->addPerson2group2roleP2g2r($person2group2roleP2g2r);
        }

        $this->collPerson2group2roleP2g2rs = $person2group2roleP2g2rs;
        $this->collPerson2group2roleP2g2rsPartial = false;

        return $this;
    }

    /**
     * Returns the number of related Person2group2roleP2g2r objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related Person2group2roleP2g2r objects.
     * @throws PropelException
     */
    public function countPerson2group2roleP2g2rs(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collPerson2group2roleP2g2rsPartial && !$this->isNew();
        if (null === $this->collPerson2group2roleP2g2rs || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collPerson2group2roleP2g2rs) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getPerson2group2roleP2g2rs());
            }

            $query = ChildPerson2group2roleP2g2rQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByPerson($this)
                ->count($con);
        }

        return count($this->collPerson2group2roleP2g2rs);
    }

    /**
     * Method called to associate a ChildPerson2group2roleP2g2r object to this object
     * through the ChildPerson2group2roleP2g2r foreign key attribute.
     *
     * @param  ChildPerson2group2roleP2g2r $l ChildPerson2group2roleP2g2r
     * @return $this|\ChurchCRM\Person The current object (for fluent API support)
     */
    public function addPerson2group2roleP2g2r(ChildPerson2group2roleP2g2r $l)
    {
        if ($this->collPerson2group2roleP2g2rs === null) {
            $this->initPerson2group2roleP2g2rs();
            $this->collPerson2group2roleP2g2rsPartial = true;
        }

        if (!$this->collPerson2group2roleP2g2rs->contains($l)) {
            $this->doAddPerson2group2roleP2g2r($l);

            if ($this->person2group2roleP2g2rsScheduledForDeletion and $this->person2group2roleP2g2rsScheduledForDeletion->contains($l)) {
                $this->person2group2roleP2g2rsScheduledForDeletion->remove($this->person2group2roleP2g2rsScheduledForDeletion->search($l));
            }
        }

        return $this;
    }

    /**
     * @param ChildPerson2group2roleP2g2r $person2group2roleP2g2r The ChildPerson2group2roleP2g2r object to add.
     */
    protected function doAddPerson2group2roleP2g2r(ChildPerson2group2roleP2g2r $person2group2roleP2g2r)
    {
        $this->collPerson2group2roleP2g2rs[]= $person2group2roleP2g2r;
        $person2group2roleP2g2r->setPerson($this);
    }

    /**
     * @param  ChildPerson2group2roleP2g2r $person2group2roleP2g2r The ChildPerson2group2roleP2g2r object to remove.
     * @return $this|ChildPerson The current object (for fluent API support)
     */
    public function removePerson2group2roleP2g2r(ChildPerson2group2roleP2g2r $person2group2roleP2g2r)
    {
        if ($this->getPerson2group2roleP2g2rs()->contains($person2group2roleP2g2r)) {
            $pos = $this->collPerson2group2roleP2g2rs->search($person2group2roleP2g2r);
            $this->collPerson2group2roleP2g2rs->remove($pos);
            if (null === $this->person2group2roleP2g2rsScheduledForDeletion) {
                $this->person2group2roleP2g2rsScheduledForDeletion = clone $this->collPerson2group2roleP2g2rs;
                $this->person2group2roleP2g2rsScheduledForDeletion->clear();
            }
            $this->person2group2roleP2g2rsScheduledForDeletion[]= clone $person2group2roleP2g2r;
            $person2group2roleP2g2r->setPerson(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Person is new, it will return
     * an empty collection; or if this Person has previously
     * been saved, it will retrieve related Person2group2roleP2g2rs from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Person.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return ObjectCollection|ChildPerson2group2roleP2g2r[] List of ChildPerson2group2roleP2g2r objects
     */
    public function getPerson2group2roleP2g2rsJoinGroup(Criteria $criteria = null, ConnectionInterface $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildPerson2group2roleP2g2rQuery::create(null, $criteria);
        $query->joinWith('Group', $joinBehavior);

        return $this->getPerson2group2roleP2g2rs($query, $con);
    }

    /**
     * Clears the current object, sets all attributes to their default values and removes
     * outgoing references as well as back-references (from other objects to this one. Results probably in a database
     * change of those foreign objects when you call `save` there).
     */
    public function clear()
    {
        if (null !== $this->aFamily) {
            $this->aFamily->removePerson($this);
        }
        $this->per_id = null;
        $this->per_title = null;
        $this->per_firstname = null;
        $this->per_middlename = null;
        $this->per_lastname = null;
        $this->per_suffix = null;
        $this->per_address1 = null;
        $this->per_address2 = null;
        $this->per_city = null;
        $this->per_state = null;
        $this->per_zip = null;
        $this->per_country = null;
        $this->per_homephone = null;
        $this->per_workphone = null;
        $this->per_cellphone = null;
        $this->per_email = null;
        $this->per_workemail = null;
        $this->per_birthmonth = null;
        $this->per_birthday = null;
        $this->per_birthyear = null;
        $this->per_membershipdate = null;
        $this->per_gender = null;
        $this->per_fmr_id = null;
        $this->per_cls_id = null;
        $this->per_fam_id = null;
        $this->per_envelope = null;
        $this->per_datelastedited = null;
        $this->per_dateentered = null;
        $this->per_enteredby = null;
        $this->per_editedby = null;
        $this->per_frienddate = null;
        $this->per_flags = null;
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
            if ($this->collWhyCames) {
                foreach ($this->collWhyCames as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collNotes) {
                foreach ($this->collNotes as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collPerson2group2roleP2g2rs) {
                foreach ($this->collPerson2group2roleP2g2rs as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        $this->collWhyCames = null;
        $this->collNotes = null;
        $this->collPerson2group2roleP2g2rs = null;
        $this->aFamily = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(PersonTableMap::DEFAULT_STRING_FORMAT);
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
