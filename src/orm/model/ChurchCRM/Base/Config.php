<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\ConfigQuery as ChildConfigQuery;
use ChurchCRM\Map\ConfigTableMap;
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

/**
 * Base class that represents a row from the 'config_cfg' table.
 *
 *
 *
 * @package    propel.generator.ChurchCRM.Base
 */
abstract class Config implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\ChurchCRM\\Map\\ConfigTableMap';


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
     * The value for the cfg_id field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $cfg_id;

    /**
     * The value for the cfg_name field.
     *
     * Note: this column has a database default value of: ''
     * @var        string
     */
    protected $cfg_name;

    /**
     * The value for the cfg_value field.
     *
     * @var        string
     */
    protected $cfg_value;

    /**
     * The value for the cfg_type field.
     *
     * Note: this column has a database default value of: 'text'
     * @var        string
     */
    protected $cfg_type;

    /**
     * The value for the cfg_default field.
     *
     * @var        string
     */
    protected $cfg_default;

    /**
     * The value for the cfg_tooltip field.
     *
     * @var        string
     */
    protected $cfg_tooltip;

    /**
     * The value for the cfg_section field.
     *
     * Note: this column has a database default value of: ''
     * @var        string
     */
    protected $cfg_section;

    /**
     * The value for the cfg_category field.
     *
     * @var        string
     */
    protected $cfg_category;

    /**
     * The value for the cfg_order field.
     *
     * @var        int
     */
    protected $cfg_order;

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
        $this->cfg_id = 0;
        $this->cfg_name = '';
        $this->cfg_type = 'text';
        $this->cfg_section = '';
    }

    /**
     * Initializes internal state of ChurchCRM\Base\Config object.
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
     * Compares this with another <code>Config</code> instance.  If
     * <code>obj</code> is an instance of <code>Config</code>, delegates to
     * <code>equals(Config)</code>.  Otherwise, returns <code>false</code>.
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
     * @return $this|Config The current object, for fluid interface
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
     * Get the [cfg_id] column value.
     *
     * @return int
     */
    public function getId()
    {
        return $this->cfg_id;
    }

    /**
     * Get the [cfg_name] column value.
     *
     * @return string
     */
    public function getName()
    {
        return $this->cfg_name;
    }

    /**
     * Get the [cfg_value] column value.
     *
     * @return string
     */
    public function getValue()
    {
        return $this->cfg_value;
    }

    /**
     * Get the [cfg_type] column value.
     *
     * @return string
     */
    public function getType()
    {
        return $this->cfg_type;
    }

    /**
     * Get the [cfg_default] column value.
     *
     * @return string
     */
    public function getDefault()
    {
        return $this->cfg_default;
    }

    /**
     * Get the [cfg_tooltip] column value.
     *
     * @return string
     */
    public function getTooltip()
    {
        return $this->cfg_tooltip;
    }

    /**
     * Get the [cfg_section] column value.
     *
     * @return string
     */
    public function getSection()
    {
        return $this->cfg_section;
    }

    /**
     * Get the [cfg_category] column value.
     *
     * @return string
     */
    public function getCategory()
    {
        return $this->cfg_category;
    }

    /**
     * Get the [cfg_order] column value.
     *
     * @return int
     */
    public function getOrder()
    {
        return $this->cfg_order;
    }

    /**
     * Set the value of [cfg_id] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Config The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->cfg_id !== $v) {
            $this->cfg_id = $v;
            $this->modifiedColumns[ConfigTableMap::COL_CFG_ID] = true;
        }

        return $this;
    } // setId()

    /**
     * Set the value of [cfg_name] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Config The current object (for fluent API support)
     */
    public function setName($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->cfg_name !== $v) {
            $this->cfg_name = $v;
            $this->modifiedColumns[ConfigTableMap::COL_CFG_NAME] = true;
        }

        return $this;
    } // setName()

    /**
     * Set the value of [cfg_value] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Config The current object (for fluent API support)
     */
    public function setValue($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->cfg_value !== $v) {
            $this->cfg_value = $v;
            $this->modifiedColumns[ConfigTableMap::COL_CFG_VALUE] = true;
        }

        return $this;
    } // setValue()

    /**
     * Set the value of [cfg_type] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Config The current object (for fluent API support)
     */
    public function setType($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->cfg_type !== $v) {
            $this->cfg_type = $v;
            $this->modifiedColumns[ConfigTableMap::COL_CFG_TYPE] = true;
        }

        return $this;
    } // setType()

    /**
     * Set the value of [cfg_default] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Config The current object (for fluent API support)
     */
    public function setDefault($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->cfg_default !== $v) {
            $this->cfg_default = $v;
            $this->modifiedColumns[ConfigTableMap::COL_CFG_DEFAULT] = true;
        }

        return $this;
    } // setDefault()

    /**
     * Set the value of [cfg_tooltip] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Config The current object (for fluent API support)
     */
    public function setTooltip($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->cfg_tooltip !== $v) {
            $this->cfg_tooltip = $v;
            $this->modifiedColumns[ConfigTableMap::COL_CFG_TOOLTIP] = true;
        }

        return $this;
    } // setTooltip()

    /**
     * Set the value of [cfg_section] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Config The current object (for fluent API support)
     */
    public function setSection($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->cfg_section !== $v) {
            $this->cfg_section = $v;
            $this->modifiedColumns[ConfigTableMap::COL_CFG_SECTION] = true;
        }

        return $this;
    } // setSection()

    /**
     * Set the value of [cfg_category] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Config The current object (for fluent API support)
     */
    public function setCategory($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->cfg_category !== $v) {
            $this->cfg_category = $v;
            $this->modifiedColumns[ConfigTableMap::COL_CFG_CATEGORY] = true;
        }

        return $this;
    } // setCategory()

    /**
     * Set the value of [cfg_order] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Config The current object (for fluent API support)
     */
    public function setOrder($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->cfg_order !== $v) {
            $this->cfg_order = $v;
            $this->modifiedColumns[ConfigTableMap::COL_CFG_ORDER] = true;
        }

        return $this;
    } // setOrder()

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
            if ($this->cfg_id !== 0) {
                return false;
            }

            if ($this->cfg_name !== '') {
                return false;
            }

            if ($this->cfg_type !== 'text') {
                return false;
            }

            if ($this->cfg_section !== '') {
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

            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : ConfigTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->cfg_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : ConfigTableMap::translateFieldName('Name', TableMap::TYPE_PHPNAME, $indexType)];
            $this->cfg_name = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : ConfigTableMap::translateFieldName('Value', TableMap::TYPE_PHPNAME, $indexType)];
            $this->cfg_value = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : ConfigTableMap::translateFieldName('Type', TableMap::TYPE_PHPNAME, $indexType)];
            $this->cfg_type = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : ConfigTableMap::translateFieldName('Default', TableMap::TYPE_PHPNAME, $indexType)];
            $this->cfg_default = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : ConfigTableMap::translateFieldName('Tooltip', TableMap::TYPE_PHPNAME, $indexType)];
            $this->cfg_tooltip = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : ConfigTableMap::translateFieldName('Section', TableMap::TYPE_PHPNAME, $indexType)];
            $this->cfg_section = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : ConfigTableMap::translateFieldName('Category', TableMap::TYPE_PHPNAME, $indexType)];
            $this->cfg_category = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : ConfigTableMap::translateFieldName('Order', TableMap::TYPE_PHPNAME, $indexType)];
            $this->cfg_order = (null !== $col) ? (int) $col : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 9; // 9 = ConfigTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException(sprintf('Error populating %s object', '\\ChurchCRM\\Config'), 0, $e);
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
            $con = Propel::getServiceContainer()->getReadConnection(ConfigTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildConfigQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
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
     * @see Config::setDeleted()
     * @see Config::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(ConfigTableMap::DATABASE_NAME);
        }

        $con->transaction(function () use ($con) {
            $deleteQuery = ChildConfigQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(ConfigTableMap::DATABASE_NAME);
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
                ConfigTableMap::addInstanceToPool($this);
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


         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(ConfigTableMap::COL_CFG_ID)) {
            $modifiedColumns[':p' . $index++]  = 'cfg_id';
        }
        if ($this->isColumnModified(ConfigTableMap::COL_CFG_NAME)) {
            $modifiedColumns[':p' . $index++]  = 'cfg_name';
        }
        if ($this->isColumnModified(ConfigTableMap::COL_CFG_VALUE)) {
            $modifiedColumns[':p' . $index++]  = 'cfg_value';
        }
        if ($this->isColumnModified(ConfigTableMap::COL_CFG_TYPE)) {
            $modifiedColumns[':p' . $index++]  = 'cfg_type';
        }
        if ($this->isColumnModified(ConfigTableMap::COL_CFG_DEFAULT)) {
            $modifiedColumns[':p' . $index++]  = 'cfg_default';
        }
        if ($this->isColumnModified(ConfigTableMap::COL_CFG_TOOLTIP)) {
            $modifiedColumns[':p' . $index++]  = 'cfg_tooltip';
        }
        if ($this->isColumnModified(ConfigTableMap::COL_CFG_SECTION)) {
            $modifiedColumns[':p' . $index++]  = 'cfg_section';
        }
        if ($this->isColumnModified(ConfigTableMap::COL_CFG_CATEGORY)) {
            $modifiedColumns[':p' . $index++]  = 'cfg_category';
        }
        if ($this->isColumnModified(ConfigTableMap::COL_CFG_ORDER)) {
            $modifiedColumns[':p' . $index++]  = 'cfg_order';
        }

        $sql = sprintf(
            'INSERT INTO config_cfg (%s) VALUES (%s)',
            implode(', ', $modifiedColumns),
            implode(', ', array_keys($modifiedColumns))
        );

        try {
            $stmt = $con->prepare($sql);
            foreach ($modifiedColumns as $identifier => $columnName) {
                switch ($columnName) {
                    case 'cfg_id':
                        $stmt->bindValue($identifier, $this->cfg_id, PDO::PARAM_INT);
                        break;
                    case 'cfg_name':
                        $stmt->bindValue($identifier, $this->cfg_name, PDO::PARAM_STR);
                        break;
                    case 'cfg_value':
                        $stmt->bindValue($identifier, $this->cfg_value, PDO::PARAM_STR);
                        break;
                    case 'cfg_type':
                        $stmt->bindValue($identifier, $this->cfg_type, PDO::PARAM_STR);
                        break;
                    case 'cfg_default':
                        $stmt->bindValue($identifier, $this->cfg_default, PDO::PARAM_STR);
                        break;
                    case 'cfg_tooltip':
                        $stmt->bindValue($identifier, $this->cfg_tooltip, PDO::PARAM_STR);
                        break;
                    case 'cfg_section':
                        $stmt->bindValue($identifier, $this->cfg_section, PDO::PARAM_STR);
                        break;
                    case 'cfg_category':
                        $stmt->bindValue($identifier, $this->cfg_category, PDO::PARAM_STR);
                        break;
                    case 'cfg_order':
                        $stmt->bindValue($identifier, $this->cfg_order, PDO::PARAM_INT);
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
        $pos = ConfigTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getValue();
                break;
            case 3:
                return $this->getType();
                break;
            case 4:
                return $this->getDefault();
                break;
            case 5:
                return $this->getTooltip();
                break;
            case 6:
                return $this->getSection();
                break;
            case 7:
                return $this->getCategory();
                break;
            case 8:
                return $this->getOrder();
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

        if (isset($alreadyDumpedObjects['Config'][$this->hashCode()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Config'][$this->hashCode()] = true;
        $keys = ConfigTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getName(),
            $keys[2] => $this->getValue(),
            $keys[3] => $this->getType(),
            $keys[4] => $this->getDefault(),
            $keys[5] => $this->getTooltip(),
            $keys[6] => $this->getSection(),
            $keys[7] => $this->getCategory(),
            $keys[8] => $this->getOrder(),
        );
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
     * @return $this|\ChurchCRM\Config
     */
    public function setByName($name, $value, $type = TableMap::TYPE_PHPNAME)
    {
        $pos = ConfigTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

        return $this->setByPosition($pos, $value);
    }

    /**
     * Sets a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param  int $pos position in xml schema
     * @param  mixed $value field value
     * @return $this|\ChurchCRM\Config
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
                $this->setValue($value);
                break;
            case 3:
                $this->setType($value);
                break;
            case 4:
                $this->setDefault($value);
                break;
            case 5:
                $this->setTooltip($value);
                break;
            case 6:
                $this->setSection($value);
                break;
            case 7:
                $this->setCategory($value);
                break;
            case 8:
                $this->setOrder($value);
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
        $keys = ConfigTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) {
            $this->setId($arr[$keys[0]]);
        }
        if (array_key_exists($keys[1], $arr)) {
            $this->setName($arr[$keys[1]]);
        }
        if (array_key_exists($keys[2], $arr)) {
            $this->setValue($arr[$keys[2]]);
        }
        if (array_key_exists($keys[3], $arr)) {
            $this->setType($arr[$keys[3]]);
        }
        if (array_key_exists($keys[4], $arr)) {
            $this->setDefault($arr[$keys[4]]);
        }
        if (array_key_exists($keys[5], $arr)) {
            $this->setTooltip($arr[$keys[5]]);
        }
        if (array_key_exists($keys[6], $arr)) {
            $this->setSection($arr[$keys[6]]);
        }
        if (array_key_exists($keys[7], $arr)) {
            $this->setCategory($arr[$keys[7]]);
        }
        if (array_key_exists($keys[8], $arr)) {
            $this->setOrder($arr[$keys[8]]);
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
     * @return $this|\ChurchCRM\Config The current object, for fluid interface
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
        $criteria = new Criteria(ConfigTableMap::DATABASE_NAME);

        if ($this->isColumnModified(ConfigTableMap::COL_CFG_ID)) {
            $criteria->add(ConfigTableMap::COL_CFG_ID, $this->cfg_id);
        }
        if ($this->isColumnModified(ConfigTableMap::COL_CFG_NAME)) {
            $criteria->add(ConfigTableMap::COL_CFG_NAME, $this->cfg_name);
        }
        if ($this->isColumnModified(ConfigTableMap::COL_CFG_VALUE)) {
            $criteria->add(ConfigTableMap::COL_CFG_VALUE, $this->cfg_value);
        }
        if ($this->isColumnModified(ConfigTableMap::COL_CFG_TYPE)) {
            $criteria->add(ConfigTableMap::COL_CFG_TYPE, $this->cfg_type);
        }
        if ($this->isColumnModified(ConfigTableMap::COL_CFG_DEFAULT)) {
            $criteria->add(ConfigTableMap::COL_CFG_DEFAULT, $this->cfg_default);
        }
        if ($this->isColumnModified(ConfigTableMap::COL_CFG_TOOLTIP)) {
            $criteria->add(ConfigTableMap::COL_CFG_TOOLTIP, $this->cfg_tooltip);
        }
        if ($this->isColumnModified(ConfigTableMap::COL_CFG_SECTION)) {
            $criteria->add(ConfigTableMap::COL_CFG_SECTION, $this->cfg_section);
        }
        if ($this->isColumnModified(ConfigTableMap::COL_CFG_CATEGORY)) {
            $criteria->add(ConfigTableMap::COL_CFG_CATEGORY, $this->cfg_category);
        }
        if ($this->isColumnModified(ConfigTableMap::COL_CFG_ORDER)) {
            $criteria->add(ConfigTableMap::COL_CFG_ORDER, $this->cfg_order);
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
        $criteria = ChildConfigQuery::create();
        $criteria->add(ConfigTableMap::COL_CFG_ID, $this->cfg_id);

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
     * Generic method to set the primary key (cfg_id column).
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
     * @param      object $copyObj An object of \ChurchCRM\Config (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setId($this->getId());
        $copyObj->setName($this->getName());
        $copyObj->setValue($this->getValue());
        $copyObj->setType($this->getType());
        $copyObj->setDefault($this->getDefault());
        $copyObj->setTooltip($this->getTooltip());
        $copyObj->setSection($this->getSection());
        $copyObj->setCategory($this->getCategory());
        $copyObj->setOrder($this->getOrder());
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
     * @return \ChurchCRM\Config Clone of current object.
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
        $this->cfg_id = null;
        $this->cfg_name = null;
        $this->cfg_value = null;
        $this->cfg_type = null;
        $this->cfg_default = null;
        $this->cfg_tooltip = null;
        $this->cfg_section = null;
        $this->cfg_category = null;
        $this->cfg_order = null;
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
        return (string) $this->exportTo(ConfigTableMap::DEFAULT_STRING_FORMAT);
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
