<?php

namespace ChurchCRM\Base;

use \DateTime;
use \Exception;
use \PDO;
use ChurchCRM\Family as ChildFamily;
use ChurchCRM\FamilyQuery as ChildFamilyQuery;
use ChurchCRM\NoteQuery as ChildNoteQuery;
use ChurchCRM\Person as ChildPerson;
use ChurchCRM\PersonQuery as ChildPersonQuery;
use ChurchCRM\Map\NoteTableMap;
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
 * Base class that represents a row from the 'note_nte' table.
 *
 *
 *
 * @package    propel.generator.ChurchCRM.Base
 */
abstract class Note implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\ChurchCRM\\Map\\NoteTableMap';


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
     * The value for the nte_id field.
     *
     * @var        int
     */
    protected $nte_id;

    /**
     * The value for the nte_per_id field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $nte_per_id;

    /**
     * The value for the nte_fam_id field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $nte_fam_id;

    /**
     * The value for the nte_private field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $nte_private;

    /**
     * The value for the nte_text field.
     *
     * @var        string
     */
    protected $nte_text;

    /**
     * The value for the nte_dateentered field.
     *
     * Note: this column has a database default value of: NULL
     * @var        DateTime
     */
    protected $nte_dateentered;

    /**
     * The value for the nte_datelastedited field.
     *
     * @var        DateTime
     */
    protected $nte_datelastedited;

    /**
     * The value for the nte_enteredby field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $nte_enteredby;

    /**
     * The value for the nte_editedby field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $nte_editedby;

    /**
     * The value for the nte_type field.
     *
     * @var        string
     */
    protected $nte_type;

    /**
     * @var        ChildPerson
     */
    protected $aPerson;

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
        $this->nte_per_id = 0;
        $this->nte_fam_id = 0;
        $this->nte_private = 0;
        $this->nte_dateentered = PropelDateTime::newInstance(NULL, null, 'DateTime');
        $this->nte_enteredby = 0;
        $this->nte_editedby = 0;
    }

    /**
     * Initializes internal state of ChurchCRM\Base\Note object.
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
     * Compares this with another <code>Note</code> instance.  If
     * <code>obj</code> is an instance of <code>Note</code>, delegates to
     * <code>equals(Note)</code>.  Otherwise, returns <code>false</code>.
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
     * @return $this|Note The current object, for fluid interface
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
     * Get the [nte_id] column value.
     *
     * @return int
     */
    public function getId()
    {
        return $this->nte_id;
    }

    /**
     * Get the [nte_per_id] column value.
     *
     * @return int
     */
    public function getPerId()
    {
        return $this->nte_per_id;
    }

    /**
     * Get the [nte_fam_id] column value.
     *
     * @return int
     */
    public function getFamId()
    {
        return $this->nte_fam_id;
    }

    /**
     * Get the [nte_private] column value.
     *
     * @return int
     */
    public function getPrivate()
    {
        return $this->nte_private;
    }

    /**
     * Get the [nte_text] column value.
     *
     * @return string
     */
    public function getText()
    {
        return $this->nte_text;
    }

    /**
     * Get the [optionally formatted] temporal [nte_dateentered] column value.
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
            return $this->nte_dateentered;
        } else {
            return $this->nte_dateentered instanceof \DateTimeInterface ? $this->nte_dateentered->format($format) : null;
        }
    }

    /**
     * Get the [optionally formatted] temporal [nte_datelastedited] column value.
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
            return $this->nte_datelastedited;
        } else {
            return $this->nte_datelastedited instanceof \DateTimeInterface ? $this->nte_datelastedited->format($format) : null;
        }
    }

    /**
     * Get the [nte_enteredby] column value.
     *
     * @return int
     */
    public function getEnteredBy()
    {
        return $this->nte_enteredby;
    }

    /**
     * Get the [nte_editedby] column value.
     *
     * @return int
     */
    public function getEditedBy()
    {
        return $this->nte_editedby;
    }

    /**
     * Get the [nte_type] column value.
     *
     * @return string
     */
    public function getType()
    {
        return $this->nte_type;
    }

    /**
     * Set the value of [nte_id] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Note The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->nte_id !== $v) {
            $this->nte_id = $v;
            $this->modifiedColumns[NoteTableMap::COL_NTE_ID] = true;
        }

        return $this;
    } // setId()

    /**
     * Set the value of [nte_per_id] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Note The current object (for fluent API support)
     */
    public function setPerId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->nte_per_id !== $v) {
            $this->nte_per_id = $v;
            $this->modifiedColumns[NoteTableMap::COL_NTE_PER_ID] = true;
        }

        if ($this->aPerson !== null && $this->aPerson->getId() !== $v) {
            $this->aPerson = null;
        }

        return $this;
    } // setPerId()

    /**
     * Set the value of [nte_fam_id] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Note The current object (for fluent API support)
     */
    public function setFamId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->nte_fam_id !== $v) {
            $this->nte_fam_id = $v;
            $this->modifiedColumns[NoteTableMap::COL_NTE_FAM_ID] = true;
        }

        if ($this->aFamily !== null && $this->aFamily->getId() !== $v) {
            $this->aFamily = null;
        }

        return $this;
    } // setFamId()

    /**
     * Set the value of [nte_private] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Note The current object (for fluent API support)
     */
    public function setPrivate($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->nte_private !== $v) {
            $this->nte_private = $v;
            $this->modifiedColumns[NoteTableMap::COL_NTE_PRIVATE] = true;
        }

        return $this;
    } // setPrivate()

    /**
     * Set the value of [nte_text] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Note The current object (for fluent API support)
     */
    public function setText($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->nte_text !== $v) {
            $this->nte_text = $v;
            $this->modifiedColumns[NoteTableMap::COL_NTE_TEXT] = true;
        }

        return $this;
    } // setText()

    /**
     * Sets the value of [nte_dateentered] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTimeInterface value.
     *               Empty strings are treated as NULL.
     * @return $this|\ChurchCRM\Note The current object (for fluent API support)
     */
    public function setDateEntered($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->nte_dateentered !== null || $dt !== null) {
            if ( ($dt != $this->nte_dateentered) // normalized values don't match
                || ($dt->format('Y-m-d H:i:s.u') === NULL) // or the entered value matches the default
                 ) {
                $this->nte_dateentered = $dt === null ? null : clone $dt;
                $this->modifiedColumns[NoteTableMap::COL_NTE_DATEENTERED] = true;
            }
        } // if either are not null

        return $this;
    } // setDateEntered()

    /**
     * Sets the value of [nte_datelastedited] column to a normalized version of the date/time value specified.
     *
     * @param  mixed $v string, integer (timestamp), or \DateTimeInterface value.
     *               Empty strings are treated as NULL.
     * @return $this|\ChurchCRM\Note The current object (for fluent API support)
     */
    public function setDateLastEdited($v)
    {
        $dt = PropelDateTime::newInstance($v, null, 'DateTime');
        if ($this->nte_datelastedited !== null || $dt !== null) {
            if ($this->nte_datelastedited === null || $dt === null || $dt->format("Y-m-d H:i:s.u") !== $this->nte_datelastedited->format("Y-m-d H:i:s.u")) {
                $this->nte_datelastedited = $dt === null ? null : clone $dt;
                $this->modifiedColumns[NoteTableMap::COL_NTE_DATELASTEDITED] = true;
            }
        } // if either are not null

        return $this;
    } // setDateLastEdited()

    /**
     * Set the value of [nte_enteredby] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Note The current object (for fluent API support)
     */
    public function setEnteredBy($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->nte_enteredby !== $v) {
            $this->nte_enteredby = $v;
            $this->modifiedColumns[NoteTableMap::COL_NTE_ENTEREDBY] = true;
        }

        return $this;
    } // setEnteredBy()

    /**
     * Set the value of [nte_editedby] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Note The current object (for fluent API support)
     */
    public function setEditedBy($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->nte_editedby !== $v) {
            $this->nte_editedby = $v;
            $this->modifiedColumns[NoteTableMap::COL_NTE_EDITEDBY] = true;
        }

        return $this;
    } // setEditedBy()

    /**
     * Set the value of [nte_type] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Note The current object (for fluent API support)
     */
    public function setType($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->nte_type !== $v) {
            $this->nte_type = $v;
            $this->modifiedColumns[NoteTableMap::COL_NTE_TYPE] = true;
        }

        return $this;
    } // setType()

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
            if ($this->nte_per_id !== 0) {
                return false;
            }

            if ($this->nte_fam_id !== 0) {
                return false;
            }

            if ($this->nte_private !== 0) {
                return false;
            }

            if ($this->nte_dateentered && $this->nte_dateentered->format('Y-m-d H:i:s.u') !== NULL) {
                return false;
            }

            if ($this->nte_enteredby !== 0) {
                return false;
            }

            if ($this->nte_editedby !== 0) {
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

            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : NoteTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->nte_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : NoteTableMap::translateFieldName('PerId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->nte_per_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : NoteTableMap::translateFieldName('FamId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->nte_fam_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : NoteTableMap::translateFieldName('Private', TableMap::TYPE_PHPNAME, $indexType)];
            $this->nte_private = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : NoteTableMap::translateFieldName('Text', TableMap::TYPE_PHPNAME, $indexType)];
            $this->nte_text = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : NoteTableMap::translateFieldName('DateEntered', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->nte_dateentered = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : NoteTableMap::translateFieldName('DateLastEdited', TableMap::TYPE_PHPNAME, $indexType)];
            if ($col === '0000-00-00 00:00:00') {
                $col = null;
            }
            $this->nte_datelastedited = (null !== $col) ? PropelDateTime::newInstance($col, null, 'DateTime') : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : NoteTableMap::translateFieldName('EnteredBy', TableMap::TYPE_PHPNAME, $indexType)];
            $this->nte_enteredby = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : NoteTableMap::translateFieldName('EditedBy', TableMap::TYPE_PHPNAME, $indexType)];
            $this->nte_editedby = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 9 + $startcol : NoteTableMap::translateFieldName('Type', TableMap::TYPE_PHPNAME, $indexType)];
            $this->nte_type = (null !== $col) ? (string) $col : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 10; // 10 = NoteTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException(sprintf('Error populating %s object', '\\ChurchCRM\\Note'), 0, $e);
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
        if ($this->aPerson !== null && $this->nte_per_id !== $this->aPerson->getId()) {
            $this->aPerson = null;
        }
        if ($this->aFamily !== null && $this->nte_fam_id !== $this->aFamily->getId()) {
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
            $con = Propel::getServiceContainer()->getReadConnection(NoteTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildNoteQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->aPerson = null;
            $this->aFamily = null;
        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see Note::setDeleted()
     * @see Note::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(NoteTableMap::DATABASE_NAME);
        }

        $con->transaction(function () use ($con) {
            $deleteQuery = ChildNoteQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(NoteTableMap::DATABASE_NAME);
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
                NoteTableMap::addInstanceToPool($this);
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

            if ($this->aPerson !== null) {
                if ($this->aPerson->isModified() || $this->aPerson->isNew()) {
                    $affectedRows += $this->aPerson->save($con);
                }
                $this->setPerson($this->aPerson);
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

        $this->modifiedColumns[NoteTableMap::COL_NTE_ID] = true;
        if (null !== $this->nte_id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . NoteTableMap::COL_NTE_ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(NoteTableMap::COL_NTE_ID)) {
            $modifiedColumns[':p' . $index++]  = 'nte_ID';
        }
        if ($this->isColumnModified(NoteTableMap::COL_NTE_PER_ID)) {
            $modifiedColumns[':p' . $index++]  = 'nte_per_ID';
        }
        if ($this->isColumnModified(NoteTableMap::COL_NTE_FAM_ID)) {
            $modifiedColumns[':p' . $index++]  = 'nte_fam_ID';
        }
        if ($this->isColumnModified(NoteTableMap::COL_NTE_PRIVATE)) {
            $modifiedColumns[':p' . $index++]  = 'nte_Private';
        }
        if ($this->isColumnModified(NoteTableMap::COL_NTE_TEXT)) {
            $modifiedColumns[':p' . $index++]  = 'nte_Text';
        }
        if ($this->isColumnModified(NoteTableMap::COL_NTE_DATEENTERED)) {
            $modifiedColumns[':p' . $index++]  = 'nte_DateEntered';
        }
        if ($this->isColumnModified(NoteTableMap::COL_NTE_DATELASTEDITED)) {
            $modifiedColumns[':p' . $index++]  = 'nte_DateLastEdited';
        }
        if ($this->isColumnModified(NoteTableMap::COL_NTE_ENTEREDBY)) {
            $modifiedColumns[':p' . $index++]  = 'nte_EnteredBy';
        }
        if ($this->isColumnModified(NoteTableMap::COL_NTE_EDITEDBY)) {
            $modifiedColumns[':p' . $index++]  = 'nte_EditedBy';
        }
        if ($this->isColumnModified(NoteTableMap::COL_NTE_TYPE)) {
            $modifiedColumns[':p' . $index++]  = 'nte_Type';
        }

        $sql = sprintf(
            'INSERT INTO note_nte (%s) VALUES (%s)',
            implode(', ', $modifiedColumns),
            implode(', ', array_keys($modifiedColumns))
        );

        try {
            $stmt = $con->prepare($sql);
            foreach ($modifiedColumns as $identifier => $columnName) {
                switch ($columnName) {
                    case 'nte_ID':
                        $stmt->bindValue($identifier, $this->nte_id, PDO::PARAM_INT);
                        break;
                    case 'nte_per_ID':
                        $stmt->bindValue($identifier, $this->nte_per_id, PDO::PARAM_INT);
                        break;
                    case 'nte_fam_ID':
                        $stmt->bindValue($identifier, $this->nte_fam_id, PDO::PARAM_INT);
                        break;
                    case 'nte_Private':
                        $stmt->bindValue($identifier, $this->nte_private, PDO::PARAM_INT);
                        break;
                    case 'nte_Text':
                        $stmt->bindValue($identifier, $this->nte_text, PDO::PARAM_STR);
                        break;
                    case 'nte_DateEntered':
                        $stmt->bindValue($identifier, $this->nte_dateentered ? $this->nte_dateentered->format("Y-m-d H:i:s.u") : null, PDO::PARAM_STR);
                        break;
                    case 'nte_DateLastEdited':
                        $stmt->bindValue($identifier, $this->nte_datelastedited ? $this->nte_datelastedited->format("Y-m-d H:i:s.u") : null, PDO::PARAM_STR);
                        break;
                    case 'nte_EnteredBy':
                        $stmt->bindValue($identifier, $this->nte_enteredby, PDO::PARAM_INT);
                        break;
                    case 'nte_EditedBy':
                        $stmt->bindValue($identifier, $this->nte_editedby, PDO::PARAM_INT);
                        break;
                    case 'nte_Type':
                        $stmt->bindValue($identifier, $this->nte_type, PDO::PARAM_STR);
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
        $pos = NoteTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getPerId();
                break;
            case 2:
                return $this->getFamId();
                break;
            case 3:
                return $this->getPrivate();
                break;
            case 4:
                return $this->getText();
                break;
            case 5:
                return $this->getDateEntered();
                break;
            case 6:
                return $this->getDateLastEdited();
                break;
            case 7:
                return $this->getEnteredBy();
                break;
            case 8:
                return $this->getEditedBy();
                break;
            case 9:
                return $this->getType();
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

        if (isset($alreadyDumpedObjects['Note'][$this->hashCode()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Note'][$this->hashCode()] = true;
        $keys = NoteTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getPerId(),
            $keys[2] => $this->getFamId(),
            $keys[3] => $this->getPrivate(),
            $keys[4] => $this->getText(),
            $keys[5] => $this->getDateEntered(),
            $keys[6] => $this->getDateLastEdited(),
            $keys[7] => $this->getEnteredBy(),
            $keys[8] => $this->getEditedBy(),
            $keys[9] => $this->getType(),
        );
        if ($result[$keys[5]] instanceof \DateTime) {
            $result[$keys[5]] = $result[$keys[5]]->format('c');
        }

        if ($result[$keys[6]] instanceof \DateTime) {
            $result[$keys[6]] = $result[$keys[6]]->format('c');
        }

        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->aPerson) {

                switch ($keyType) {
                    case TableMap::TYPE_CAMELNAME:
                        $key = 'person';
                        break;
                    case TableMap::TYPE_FIELDNAME:
                        $key = 'person_per';
                        break;
                    default:
                        $key = 'Person';
                }

                $result[$key] = $this->aPerson->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
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
     * @return $this|\ChurchCRM\Note
     */
    public function setByName($name, $value, $type = TableMap::TYPE_PHPNAME)
    {
        $pos = NoteTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

        return $this->setByPosition($pos, $value);
    }

    /**
     * Sets a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param  int $pos position in xml schema
     * @param  mixed $value field value
     * @return $this|\ChurchCRM\Note
     */
    public function setByPosition($pos, $value)
    {
        switch ($pos) {
            case 0:
                $this->setId($value);
                break;
            case 1:
                $this->setPerId($value);
                break;
            case 2:
                $this->setFamId($value);
                break;
            case 3:
                $this->setPrivate($value);
                break;
            case 4:
                $this->setText($value);
                break;
            case 5:
                $this->setDateEntered($value);
                break;
            case 6:
                $this->setDateLastEdited($value);
                break;
            case 7:
                $this->setEnteredBy($value);
                break;
            case 8:
                $this->setEditedBy($value);
                break;
            case 9:
                $this->setType($value);
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
        $keys = NoteTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) {
            $this->setId($arr[$keys[0]]);
        }
        if (array_key_exists($keys[1], $arr)) {
            $this->setPerId($arr[$keys[1]]);
        }
        if (array_key_exists($keys[2], $arr)) {
            $this->setFamId($arr[$keys[2]]);
        }
        if (array_key_exists($keys[3], $arr)) {
            $this->setPrivate($arr[$keys[3]]);
        }
        if (array_key_exists($keys[4], $arr)) {
            $this->setText($arr[$keys[4]]);
        }
        if (array_key_exists($keys[5], $arr)) {
            $this->setDateEntered($arr[$keys[5]]);
        }
        if (array_key_exists($keys[6], $arr)) {
            $this->setDateLastEdited($arr[$keys[6]]);
        }
        if (array_key_exists($keys[7], $arr)) {
            $this->setEnteredBy($arr[$keys[7]]);
        }
        if (array_key_exists($keys[8], $arr)) {
            $this->setEditedBy($arr[$keys[8]]);
        }
        if (array_key_exists($keys[9], $arr)) {
            $this->setType($arr[$keys[9]]);
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
     * @return $this|\ChurchCRM\Note The current object, for fluid interface
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
        $criteria = new Criteria(NoteTableMap::DATABASE_NAME);

        if ($this->isColumnModified(NoteTableMap::COL_NTE_ID)) {
            $criteria->add(NoteTableMap::COL_NTE_ID, $this->nte_id);
        }
        if ($this->isColumnModified(NoteTableMap::COL_NTE_PER_ID)) {
            $criteria->add(NoteTableMap::COL_NTE_PER_ID, $this->nte_per_id);
        }
        if ($this->isColumnModified(NoteTableMap::COL_NTE_FAM_ID)) {
            $criteria->add(NoteTableMap::COL_NTE_FAM_ID, $this->nte_fam_id);
        }
        if ($this->isColumnModified(NoteTableMap::COL_NTE_PRIVATE)) {
            $criteria->add(NoteTableMap::COL_NTE_PRIVATE, $this->nte_private);
        }
        if ($this->isColumnModified(NoteTableMap::COL_NTE_TEXT)) {
            $criteria->add(NoteTableMap::COL_NTE_TEXT, $this->nte_text);
        }
        if ($this->isColumnModified(NoteTableMap::COL_NTE_DATEENTERED)) {
            $criteria->add(NoteTableMap::COL_NTE_DATEENTERED, $this->nte_dateentered);
        }
        if ($this->isColumnModified(NoteTableMap::COL_NTE_DATELASTEDITED)) {
            $criteria->add(NoteTableMap::COL_NTE_DATELASTEDITED, $this->nte_datelastedited);
        }
        if ($this->isColumnModified(NoteTableMap::COL_NTE_ENTEREDBY)) {
            $criteria->add(NoteTableMap::COL_NTE_ENTEREDBY, $this->nte_enteredby);
        }
        if ($this->isColumnModified(NoteTableMap::COL_NTE_EDITEDBY)) {
            $criteria->add(NoteTableMap::COL_NTE_EDITEDBY, $this->nte_editedby);
        }
        if ($this->isColumnModified(NoteTableMap::COL_NTE_TYPE)) {
            $criteria->add(NoteTableMap::COL_NTE_TYPE, $this->nte_type);
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
        $criteria = ChildNoteQuery::create();
        $criteria->add(NoteTableMap::COL_NTE_ID, $this->nte_id);

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
     * Generic method to set the primary key (nte_id column).
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
     * @param      object $copyObj An object of \ChurchCRM\Note (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setPerId($this->getPerId());
        $copyObj->setFamId($this->getFamId());
        $copyObj->setPrivate($this->getPrivate());
        $copyObj->setText($this->getText());
        $copyObj->setDateEntered($this->getDateEntered());
        $copyObj->setDateLastEdited($this->getDateLastEdited());
        $copyObj->setEnteredBy($this->getEnteredBy());
        $copyObj->setEditedBy($this->getEditedBy());
        $copyObj->setType($this->getType());
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
     * @return \ChurchCRM\Note Clone of current object.
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
     * Declares an association between this object and a ChildPerson object.
     *
     * @param  ChildPerson $v
     * @return $this|\ChurchCRM\Note The current object (for fluent API support)
     * @throws PropelException
     */
    public function setPerson(ChildPerson $v = null)
    {
        if ($v === null) {
            $this->setPerId(0);
        } else {
            $this->setPerId($v->getId());
        }

        $this->aPerson = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the ChildPerson object, it will not be re-added.
        if ($v !== null) {
            $v->addNote($this);
        }


        return $this;
    }


    /**
     * Get the associated ChildPerson object
     *
     * @param  ConnectionInterface $con Optional Connection object.
     * @return ChildPerson The associated ChildPerson object.
     * @throws PropelException
     */
    public function getPerson(ConnectionInterface $con = null)
    {
        if ($this->aPerson === null && ($this->nte_per_id !== null)) {
            $this->aPerson = ChildPersonQuery::create()->findPk($this->nte_per_id, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aPerson->addNotes($this);
             */
        }

        return $this->aPerson;
    }

    /**
     * Declares an association between this object and a ChildFamily object.
     *
     * @param  ChildFamily $v
     * @return $this|\ChurchCRM\Note The current object (for fluent API support)
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
            $v->addNote($this);
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
        if ($this->aFamily === null && ($this->nte_fam_id !== null)) {
            $this->aFamily = ChildFamilyQuery::create()->findPk($this->nte_fam_id, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aFamily->addNotes($this);
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
        if (null !== $this->aPerson) {
            $this->aPerson->removeNote($this);
        }
        if (null !== $this->aFamily) {
            $this->aFamily->removeNote($this);
        }
        $this->nte_id = null;
        $this->nte_per_id = null;
        $this->nte_fam_id = null;
        $this->nte_private = null;
        $this->nte_text = null;
        $this->nte_dateentered = null;
        $this->nte_datelastedited = null;
        $this->nte_enteredby = null;
        $this->nte_editedby = null;
        $this->nte_type = null;
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

        $this->aPerson = null;
        $this->aFamily = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(NoteTableMap::DEFAULT_STRING_FORMAT);
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
