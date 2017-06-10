<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\Person as ChildPerson;
use ChurchCRM\PersonProperty as ChildPersonProperty;
use ChurchCRM\PersonPropertyQuery as ChildPersonPropertyQuery;
use ChurchCRM\PersonQuery as ChildPersonQuery;
use ChurchCRM\Property as ChildProperty;
use ChurchCRM\PropertyQuery as ChildPropertyQuery;
use ChurchCRM\PropertyType as ChildPropertyType;
use ChurchCRM\PropertyTypeQuery as ChildPropertyTypeQuery;
use ChurchCRM\Map\PersonPropertyTableMap;
use ChurchCRM\Map\PropertyTableMap;
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

/**
 * Base class that represents a row from the 'property_pro' table.
 *
 *
 *
 * @package    propel.generator.ChurchCRM.Base
 */
abstract class Property implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\ChurchCRM\\Map\\PropertyTableMap';


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
     * The value for the pro_id field.
     *
     * @var        int
     */
    protected $pro_id;

    /**
     * The value for the pro_class field.
     *
     * Note: this column has a database default value of: ''
     * @var        string
     */
    protected $pro_class;

    /**
     * The value for the pro_prt_id field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $pro_prt_id;

    /**
     * The value for the pro_name field.
     *
     * Note: this column has a database default value of: '0'
     * @var        string
     */
    protected $pro_name;

    /**
     * The value for the pro_description field.
     *
     * @var        string
     */
    protected $pro_description;

    /**
     * The value for the pro_prompt field.
     *
     * @var        string
     */
    protected $pro_prompt;

    /**
     * @var        ChildPropertyType
     */
    protected $aPropertyType;

    /**
     * @var        ObjectCollection|ChildPersonProperty[] Collection to store aggregation of ChildPersonProperty objects.
     */
    protected $collPersonProperties;
    protected $collPersonPropertiesPartial;

    /**
     * @var        ObjectCollection|ChildPerson[] Cross Collection to store aggregation of ChildPerson objects.
     */
    protected $collPeople;

    /**
     * @var bool
     */
    protected $collPeoplePartial;

    /**
     * Flag to prevent endless save loop, if this object is referenced
     * by another object which falls in this transaction.
     *
     * @var boolean
     */
    protected $alreadyInSave = false;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection|ChildPerson[]
     */
    protected $peopleScheduledForDeletion = null;

    /**
     * An array of objects scheduled for deletion.
     * @var ObjectCollection|ChildPersonProperty[]
     */
    protected $personPropertiesScheduledForDeletion = null;

    /**
     * Applies default values to this object.
     * This method should be called from the object's constructor (or
     * equivalent initialization method).
     * @see __construct()
     */
    public function applyDefaultValues()
    {
        $this->pro_class = '';
        $this->pro_prt_id = 0;
        $this->pro_name = '0';
    }

    /**
     * Initializes internal state of ChurchCRM\Base\Property object.
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
     * Compares this with another <code>Property</code> instance.  If
     * <code>obj</code> is an instance of <code>Property</code>, delegates to
     * <code>equals(Property)</code>.  Otherwise, returns <code>false</code>.
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
     * @return $this|Property The current object, for fluid interface
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
     * Get the [pro_id] column value.
     *
     * @return int
     */
    public function getProId()
    {
        return $this->pro_id;
    }

    /**
     * Get the [pro_class] column value.
     *
     * @return string
     */
    public function getProClass()
    {
        return $this->pro_class;
    }

    /**
     * Get the [pro_prt_id] column value.
     *
     * @return int
     */
    public function getProPrtId()
    {
        return $this->pro_prt_id;
    }

    /**
     * Get the [pro_name] column value.
     *
     * @return string
     */
    public function getProName()
    {
        return $this->pro_name;
    }

    /**
     * Get the [pro_description] column value.
     *
     * @return string
     */
    public function getProDescription()
    {
        return $this->pro_description;
    }

    /**
     * Get the [pro_prompt] column value.
     *
     * @return string
     */
    public function getProPrompt()
    {
        return $this->pro_prompt;
    }

    /**
     * Set the value of [pro_id] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Property The current object (for fluent API support)
     */
    public function setProId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->pro_id !== $v) {
            $this->pro_id = $v;
            $this->modifiedColumns[PropertyTableMap::COL_PRO_ID] = true;
        }

        return $this;
    } // setProId()

    /**
     * Set the value of [pro_class] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Property The current object (for fluent API support)
     */
    public function setProClass($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->pro_class !== $v) {
            $this->pro_class = $v;
            $this->modifiedColumns[PropertyTableMap::COL_PRO_CLASS] = true;
        }

        return $this;
    } // setProClass()

    /**
     * Set the value of [pro_prt_id] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Property The current object (for fluent API support)
     */
    public function setProPrtId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->pro_prt_id !== $v) {
            $this->pro_prt_id = $v;
            $this->modifiedColumns[PropertyTableMap::COL_PRO_PRT_ID] = true;
        }

        if ($this->aPropertyType !== null && $this->aPropertyType->getPrtId() !== $v) {
            $this->aPropertyType = null;
        }

        return $this;
    } // setProPrtId()

    /**
     * Set the value of [pro_name] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Property The current object (for fluent API support)
     */
    public function setProName($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->pro_name !== $v) {
            $this->pro_name = $v;
            $this->modifiedColumns[PropertyTableMap::COL_PRO_NAME] = true;
        }

        return $this;
    } // setProName()

    /**
     * Set the value of [pro_description] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Property The current object (for fluent API support)
     */
    public function setProDescription($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->pro_description !== $v) {
            $this->pro_description = $v;
            $this->modifiedColumns[PropertyTableMap::COL_PRO_DESCRIPTION] = true;
        }

        return $this;
    } // setProDescription()

    /**
     * Set the value of [pro_prompt] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Property The current object (for fluent API support)
     */
    public function setProPrompt($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->pro_prompt !== $v) {
            $this->pro_prompt = $v;
            $this->modifiedColumns[PropertyTableMap::COL_PRO_PROMPT] = true;
        }

        return $this;
    } // setProPrompt()

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
            if ($this->pro_class !== '') {
                return false;
            }

            if ($this->pro_prt_id !== 0) {
                return false;
            }

            if ($this->pro_name !== '0') {
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

            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : PropertyTableMap::translateFieldName('ProId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->pro_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : PropertyTableMap::translateFieldName('ProClass', TableMap::TYPE_PHPNAME, $indexType)];
            $this->pro_class = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : PropertyTableMap::translateFieldName('ProPrtId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->pro_prt_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : PropertyTableMap::translateFieldName('ProName', TableMap::TYPE_PHPNAME, $indexType)];
            $this->pro_name = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : PropertyTableMap::translateFieldName('ProDescription', TableMap::TYPE_PHPNAME, $indexType)];
            $this->pro_description = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : PropertyTableMap::translateFieldName('ProPrompt', TableMap::TYPE_PHPNAME, $indexType)];
            $this->pro_prompt = (null !== $col) ? (string) $col : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 6; // 6 = PropertyTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException(sprintf('Error populating %s object', '\\ChurchCRM\\Property'), 0, $e);
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
        if ($this->aPropertyType !== null && $this->pro_prt_id !== $this->aPropertyType->getPrtId()) {
            $this->aPropertyType = null;
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
            $con = Propel::getServiceContainer()->getReadConnection(PropertyTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildPropertyQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->aPropertyType = null;
            $this->collPersonProperties = null;

            $this->collPeople = null;
        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see Property::setDeleted()
     * @see Property::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(PropertyTableMap::DATABASE_NAME);
        }

        $con->transaction(function () use ($con) {
            $deleteQuery = ChildPropertyQuery::create()
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

        if ($this->alreadyInSave) {
            return 0;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(PropertyTableMap::DATABASE_NAME);
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
                PropertyTableMap::addInstanceToPool($this);
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

            if ($this->aPropertyType !== null) {
                if ($this->aPropertyType->isModified() || $this->aPropertyType->isNew()) {
                    $affectedRows += $this->aPropertyType->save($con);
                }
                $this->setPropertyType($this->aPropertyType);
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

            if ($this->peopleScheduledForDeletion !== null) {
                if (!$this->peopleScheduledForDeletion->isEmpty()) {
                    $pks = array();
                    foreach ($this->peopleScheduledForDeletion as $entry) {
                        $entryPk = [];

                        $entryPk[0] = $this->getProId();
                        $entryPk[1] = $entry->getId();
                        $pks[] = $entryPk;
                    }

                    \ChurchCRM\PersonPropertyQuery::create()
                        ->filterByPrimaryKeys($pks)
                        ->delete($con);

                    $this->peopleScheduledForDeletion = null;
                }

            }

            if ($this->collPeople) {
                foreach ($this->collPeople as $person) {
                    if (!$person->isDeleted() && ($person->isNew() || $person->isModified())) {
                        $person->save($con);
                    }
                }
            }


            if ($this->personPropertiesScheduledForDeletion !== null) {
                if (!$this->personPropertiesScheduledForDeletion->isEmpty()) {
                    \ChurchCRM\PersonPropertyQuery::create()
                        ->filterByPrimaryKeys($this->personPropertiesScheduledForDeletion->getPrimaryKeys(false))
                        ->delete($con);
                    $this->personPropertiesScheduledForDeletion = null;
                }
            }

            if ($this->collPersonProperties !== null) {
                foreach ($this->collPersonProperties as $referrerFK) {
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

        $this->modifiedColumns[PropertyTableMap::COL_PRO_ID] = true;
        if (null !== $this->pro_id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . PropertyTableMap::COL_PRO_ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(PropertyTableMap::COL_PRO_ID)) {
            $modifiedColumns[':p' . $index++]  = 'pro_ID';
        }
        if ($this->isColumnModified(PropertyTableMap::COL_PRO_CLASS)) {
            $modifiedColumns[':p' . $index++]  = 'pro_Class';
        }
        if ($this->isColumnModified(PropertyTableMap::COL_PRO_PRT_ID)) {
            $modifiedColumns[':p' . $index++]  = 'pro_prt_ID';
        }
        if ($this->isColumnModified(PropertyTableMap::COL_PRO_NAME)) {
            $modifiedColumns[':p' . $index++]  = 'pro_Name';
        }
        if ($this->isColumnModified(PropertyTableMap::COL_PRO_DESCRIPTION)) {
            $modifiedColumns[':p' . $index++]  = 'pro_Description';
        }
        if ($this->isColumnModified(PropertyTableMap::COL_PRO_PROMPT)) {
            $modifiedColumns[':p' . $index++]  = 'pro_Prompt';
        }

        $sql = sprintf(
            'INSERT INTO property_pro (%s) VALUES (%s)',
            implode(', ', $modifiedColumns),
            implode(', ', array_keys($modifiedColumns))
        );

        try {
            $stmt = $con->prepare($sql);
            foreach ($modifiedColumns as $identifier => $columnName) {
                switch ($columnName) {
                    case 'pro_ID':
                        $stmt->bindValue($identifier, $this->pro_id, PDO::PARAM_INT);
                        break;
                    case 'pro_Class':
                        $stmt->bindValue($identifier, $this->pro_class, PDO::PARAM_STR);
                        break;
                    case 'pro_prt_ID':
                        $stmt->bindValue($identifier, $this->pro_prt_id, PDO::PARAM_INT);
                        break;
                    case 'pro_Name':
                        $stmt->bindValue($identifier, $this->pro_name, PDO::PARAM_STR);
                        break;
                    case 'pro_Description':
                        $stmt->bindValue($identifier, $this->pro_description, PDO::PARAM_STR);
                        break;
                    case 'pro_Prompt':
                        $stmt->bindValue($identifier, $this->pro_prompt, PDO::PARAM_STR);
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
        $this->setProId($pk);

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
        $pos = PropertyTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getProId();
                break;
            case 1:
                return $this->getProClass();
                break;
            case 2:
                return $this->getProPrtId();
                break;
            case 3:
                return $this->getProName();
                break;
            case 4:
                return $this->getProDescription();
                break;
            case 5:
                return $this->getProPrompt();
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

        if (isset($alreadyDumpedObjects['Property'][$this->hashCode()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Property'][$this->hashCode()] = true;
        $keys = PropertyTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getProId(),
            $keys[1] => $this->getProClass(),
            $keys[2] => $this->getProPrtId(),
            $keys[3] => $this->getProName(),
            $keys[4] => $this->getProDescription(),
            $keys[5] => $this->getProPrompt(),
        );
        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
            if (null !== $this->aPropertyType) {

                switch ($keyType) {
                    case TableMap::TYPE_CAMELNAME:
                        $key = 'propertyType';
                        break;
                    case TableMap::TYPE_FIELDNAME:
                        $key = 'propertytype_prt';
                        break;
                    default:
                        $key = 'PropertyType';
                }

                $result[$key] = $this->aPropertyType->toArray($keyType, $includeLazyLoadColumns,  $alreadyDumpedObjects, true);
            }
            if (null !== $this->collPersonProperties) {

                switch ($keyType) {
                    case TableMap::TYPE_CAMELNAME:
                        $key = 'personProperties';
                        break;
                    case TableMap::TYPE_FIELDNAME:
                        $key = 'record2property_r2ps';
                        break;
                    default:
                        $key = 'PersonProperties';
                }

                $result[$key] = $this->collPersonProperties->toArray(null, false, $keyType, $includeLazyLoadColumns, $alreadyDumpedObjects);
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
     * @return $this|\ChurchCRM\Property
     */
    public function setByName($name, $value, $type = TableMap::TYPE_PHPNAME)
    {
        $pos = PropertyTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

        return $this->setByPosition($pos, $value);
    }

    /**
     * Sets a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param  int $pos position in xml schema
     * @param  mixed $value field value
     * @return $this|\ChurchCRM\Property
     */
    public function setByPosition($pos, $value)
    {
        switch ($pos) {
            case 0:
                $this->setProId($value);
                break;
            case 1:
                $this->setProClass($value);
                break;
            case 2:
                $this->setProPrtId($value);
                break;
            case 3:
                $this->setProName($value);
                break;
            case 4:
                $this->setProDescription($value);
                break;
            case 5:
                $this->setProPrompt($value);
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
        $keys = PropertyTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) {
            $this->setProId($arr[$keys[0]]);
        }
        if (array_key_exists($keys[1], $arr)) {
            $this->setProClass($arr[$keys[1]]);
        }
        if (array_key_exists($keys[2], $arr)) {
            $this->setProPrtId($arr[$keys[2]]);
        }
        if (array_key_exists($keys[3], $arr)) {
            $this->setProName($arr[$keys[3]]);
        }
        if (array_key_exists($keys[4], $arr)) {
            $this->setProDescription($arr[$keys[4]]);
        }
        if (array_key_exists($keys[5], $arr)) {
            $this->setProPrompt($arr[$keys[5]]);
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
     * @return $this|\ChurchCRM\Property The current object, for fluid interface
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
        $criteria = new Criteria(PropertyTableMap::DATABASE_NAME);

        if ($this->isColumnModified(PropertyTableMap::COL_PRO_ID)) {
            $criteria->add(PropertyTableMap::COL_PRO_ID, $this->pro_id);
        }
        if ($this->isColumnModified(PropertyTableMap::COL_PRO_CLASS)) {
            $criteria->add(PropertyTableMap::COL_PRO_CLASS, $this->pro_class);
        }
        if ($this->isColumnModified(PropertyTableMap::COL_PRO_PRT_ID)) {
            $criteria->add(PropertyTableMap::COL_PRO_PRT_ID, $this->pro_prt_id);
        }
        if ($this->isColumnModified(PropertyTableMap::COL_PRO_NAME)) {
            $criteria->add(PropertyTableMap::COL_PRO_NAME, $this->pro_name);
        }
        if ($this->isColumnModified(PropertyTableMap::COL_PRO_DESCRIPTION)) {
            $criteria->add(PropertyTableMap::COL_PRO_DESCRIPTION, $this->pro_description);
        }
        if ($this->isColumnModified(PropertyTableMap::COL_PRO_PROMPT)) {
            $criteria->add(PropertyTableMap::COL_PRO_PROMPT, $this->pro_prompt);
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
        $criteria = ChildPropertyQuery::create();
        $criteria->add(PropertyTableMap::COL_PRO_ID, $this->pro_id);

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
        $validPk = null !== $this->getProId();

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
        return $this->getProId();
    }

    /**
     * Generic method to set the primary key (pro_id column).
     *
     * @param       int $key Primary key.
     * @return void
     */
    public function setPrimaryKey($key)
    {
        $this->setProId($key);
    }

    /**
     * Returns true if the primary key for this object is null.
     * @return boolean
     */
    public function isPrimaryKeyNull()
    {
        return null === $this->getProId();
    }

    /**
     * Sets contents of passed object to values from current object.
     *
     * If desired, this method can also make copies of all associated (fkey referrers)
     * objects.
     *
     * @param      object $copyObj An object of \ChurchCRM\Property (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setProClass($this->getProClass());
        $copyObj->setProPrtId($this->getProPrtId());
        $copyObj->setProName($this->getProName());
        $copyObj->setProDescription($this->getProDescription());
        $copyObj->setProPrompt($this->getProPrompt());

        if ($deepCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);

            foreach ($this->getPersonProperties() as $relObj) {
                if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
                    $copyObj->addPersonProperty($relObj->copy($deepCopy));
                }
            }

        } // if ($deepCopy)

        if ($makeNew) {
            $copyObj->setNew(true);
            $copyObj->setProId(NULL); // this is a auto-increment column, so set to default value
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
     * @return \ChurchCRM\Property Clone of current object.
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
     * Declares an association between this object and a ChildPropertyType object.
     *
     * @param  ChildPropertyType $v
     * @return $this|\ChurchCRM\Property The current object (for fluent API support)
     * @throws PropelException
     */
    public function setPropertyType(ChildPropertyType $v = null)
    {
        if ($v === null) {
            $this->setProPrtId(0);
        } else {
            $this->setProPrtId($v->getPrtId());
        }

        $this->aPropertyType = $v;

        // Add binding for other direction of this n:n relationship.
        // If this object has already been added to the ChildPropertyType object, it will not be re-added.
        if ($v !== null) {
            $v->addProperty($this);
        }


        return $this;
    }


    /**
     * Get the associated ChildPropertyType object
     *
     * @param  ConnectionInterface $con Optional Connection object.
     * @return ChildPropertyType The associated ChildPropertyType object.
     * @throws PropelException
     */
    public function getPropertyType(ConnectionInterface $con = null)
    {
        if ($this->aPropertyType === null && ($this->pro_prt_id !== null)) {
            $this->aPropertyType = ChildPropertyTypeQuery::create()->findPk($this->pro_prt_id, $con);
            /* The following can be used additionally to
                guarantee the related object contains a reference
                to this object.  This level of coupling may, however, be
                undesirable since it could result in an only partially populated collection
                in the referenced object.
                $this->aPropertyType->addProperties($this);
             */
        }

        return $this->aPropertyType;
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
        if ('PersonProperty' == $relationName) {
            return $this->initPersonProperties();
        }
    }

    /**
     * Clears out the collPersonProperties collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addPersonProperties()
     */
    public function clearPersonProperties()
    {
        $this->collPersonProperties = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Reset is the collPersonProperties collection loaded partially.
     */
    public function resetPartialPersonProperties($v = true)
    {
        $this->collPersonPropertiesPartial = $v;
    }

    /**
     * Initializes the collPersonProperties collection.
     *
     * By default this just sets the collPersonProperties collection to an empty array (like clearcollPersonProperties());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @param      boolean $overrideExisting If set to true, the method call initializes
     *                                        the collection even if it is not empty
     *
     * @return void
     */
    public function initPersonProperties($overrideExisting = true)
    {
        if (null !== $this->collPersonProperties && !$overrideExisting) {
            return;
        }

        $collectionClassName = PersonPropertyTableMap::getTableMap()->getCollectionClassName();

        $this->collPersonProperties = new $collectionClassName;
        $this->collPersonProperties->setModel('\ChurchCRM\PersonProperty');
    }

    /**
     * Gets an array of ChildPersonProperty objects which contain a foreign key that references this object.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildProperty is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @return ObjectCollection|ChildPersonProperty[] List of ChildPersonProperty objects
     * @throws PropelException
     */
    public function getPersonProperties(Criteria $criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collPersonPropertiesPartial && !$this->isNew();
        if (null === $this->collPersonProperties || null !== $criteria  || $partial) {
            if ($this->isNew() && null === $this->collPersonProperties) {
                // return empty collection
                $this->initPersonProperties();
            } else {
                $collPersonProperties = ChildPersonPropertyQuery::create(null, $criteria)
                    ->filterByProperty($this)
                    ->find($con);

                if (null !== $criteria) {
                    if (false !== $this->collPersonPropertiesPartial && count($collPersonProperties)) {
                        $this->initPersonProperties(false);

                        foreach ($collPersonProperties as $obj) {
                            if (false == $this->collPersonProperties->contains($obj)) {
                                $this->collPersonProperties->append($obj);
                            }
                        }

                        $this->collPersonPropertiesPartial = true;
                    }

                    return $collPersonProperties;
                }

                if ($partial && $this->collPersonProperties) {
                    foreach ($this->collPersonProperties as $obj) {
                        if ($obj->isNew()) {
                            $collPersonProperties[] = $obj;
                        }
                    }
                }

                $this->collPersonProperties = $collPersonProperties;
                $this->collPersonPropertiesPartial = false;
            }
        }

        return $this->collPersonProperties;
    }

    /**
     * Sets a collection of ChildPersonProperty objects related by a one-to-many relationship
     * to the current object.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param      Collection $personProperties A Propel collection.
     * @param      ConnectionInterface $con Optional connection object
     * @return $this|ChildProperty The current object (for fluent API support)
     */
    public function setPersonProperties(Collection $personProperties, ConnectionInterface $con = null)
    {
        /** @var ChildPersonProperty[] $personPropertiesToDelete */
        $personPropertiesToDelete = $this->getPersonProperties(new Criteria(), $con)->diff($personProperties);


        //since at least one column in the foreign key is at the same time a PK
        //we can not just set a PK to NULL in the lines below. We have to store
        //a backup of all values, so we are able to manipulate these items based on the onDelete value later.
        $this->personPropertiesScheduledForDeletion = clone $personPropertiesToDelete;

        foreach ($personPropertiesToDelete as $personPropertyRemoved) {
            $personPropertyRemoved->setProperty(null);
        }

        $this->collPersonProperties = null;
        foreach ($personProperties as $personProperty) {
            $this->addPersonProperty($personProperty);
        }

        $this->collPersonProperties = $personProperties;
        $this->collPersonPropertiesPartial = false;

        return $this;
    }

    /**
     * Returns the number of related PersonProperty objects.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct
     * @param      ConnectionInterface $con
     * @return int             Count of related PersonProperty objects.
     * @throws PropelException
     */
    public function countPersonProperties(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collPersonPropertiesPartial && !$this->isNew();
        if (null === $this->collPersonProperties || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collPersonProperties) {
                return 0;
            }

            if ($partial && !$criteria) {
                return count($this->getPersonProperties());
            }

            $query = ChildPersonPropertyQuery::create(null, $criteria);
            if ($distinct) {
                $query->distinct();
            }

            return $query
                ->filterByProperty($this)
                ->count($con);
        }

        return count($this->collPersonProperties);
    }

    /**
     * Method called to associate a ChildPersonProperty object to this object
     * through the ChildPersonProperty foreign key attribute.
     *
     * @param  ChildPersonProperty $l ChildPersonProperty
     * @return $this|\ChurchCRM\Property The current object (for fluent API support)
     */
    public function addPersonProperty(ChildPersonProperty $l)
    {
        if ($this->collPersonProperties === null) {
            $this->initPersonProperties();
            $this->collPersonPropertiesPartial = true;
        }

        if (!$this->collPersonProperties->contains($l)) {
            $this->doAddPersonProperty($l);

            if ($this->personPropertiesScheduledForDeletion and $this->personPropertiesScheduledForDeletion->contains($l)) {
                $this->personPropertiesScheduledForDeletion->remove($this->personPropertiesScheduledForDeletion->search($l));
            }
        }

        return $this;
    }

    /**
     * @param ChildPersonProperty $personProperty The ChildPersonProperty object to add.
     */
    protected function doAddPersonProperty(ChildPersonProperty $personProperty)
    {
        $this->collPersonProperties[]= $personProperty;
        $personProperty->setProperty($this);
    }

    /**
     * @param  ChildPersonProperty $personProperty The ChildPersonProperty object to remove.
     * @return $this|ChildProperty The current object (for fluent API support)
     */
    public function removePersonProperty(ChildPersonProperty $personProperty)
    {
        if ($this->getPersonProperties()->contains($personProperty)) {
            $pos = $this->collPersonProperties->search($personProperty);
            $this->collPersonProperties->remove($pos);
            if (null === $this->personPropertiesScheduledForDeletion) {
                $this->personPropertiesScheduledForDeletion = clone $this->collPersonProperties;
                $this->personPropertiesScheduledForDeletion->clear();
            }
            $this->personPropertiesScheduledForDeletion[]= clone $personProperty;
            $personProperty->setProperty(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Property is new, it will return
     * an empty collection; or if this Property has previously
     * been saved, it will retrieve related PersonProperties from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Property.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return ObjectCollection|ChildPersonProperty[] List of ChildPersonProperty objects
     */
    public function getPersonPropertiesJoinPerson(Criteria $criteria = null, ConnectionInterface $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildPersonPropertyQuery::create(null, $criteria);
        $query->joinWith('Person', $joinBehavior);

        return $this->getPersonProperties($query, $con);
    }

    /**
     * Clears out the collPeople collection
     *
     * This does not modify the database; however, it will remove any associated objects, causing
     * them to be refetched by subsequent calls to accessor method.
     *
     * @return void
     * @see        addPeople()
     */
    public function clearPeople()
    {
        $this->collPeople = null; // important to set this to NULL since that means it is uninitialized
    }

    /**
     * Initializes the collPeople crossRef collection.
     *
     * By default this just sets the collPeople collection to an empty collection (like clearPeople());
     * however, you may wish to override this method in your stub class to provide setting appropriate
     * to your application -- for example, setting the initial array to the values stored in database.
     *
     * @return void
     */
    public function initPeople()
    {
        $collectionClassName = PersonPropertyTableMap::getTableMap()->getCollectionClassName();

        $this->collPeople = new $collectionClassName;
        $this->collPeoplePartial = true;
        $this->collPeople->setModel('\ChurchCRM\Person');
    }

    /**
     * Checks if the collPeople collection is loaded.
     *
     * @return bool
     */
    public function isPeopleLoaded()
    {
        return null !== $this->collPeople;
    }

    /**
     * Gets a collection of ChildPerson objects related by a many-to-many relationship
     * to the current object by way of the record2property_r2p cross-reference table.
     *
     * If the $criteria is not null, it is used to always fetch the results from the database.
     * Otherwise the results are fetched from the database the first time, then cached.
     * Next time the same method is called without $criteria, the cached collection is returned.
     * If this ChildProperty is new, it will return
     * an empty collection or the current collection; the criteria is ignored on a new object.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return ObjectCollection|ChildPerson[] List of ChildPerson objects
     */
    public function getPeople(Criteria $criteria = null, ConnectionInterface $con = null)
    {
        $partial = $this->collPeoplePartial && !$this->isNew();
        if (null === $this->collPeople || null !== $criteria || $partial) {
            if ($this->isNew()) {
                // return empty collection
                if (null === $this->collPeople) {
                    $this->initPeople();
                }
            } else {

                $query = ChildPersonQuery::create(null, $criteria)
                    ->filterByProperty($this);
                $collPeople = $query->find($con);
                if (null !== $criteria) {
                    return $collPeople;
                }

                if ($partial && $this->collPeople) {
                    //make sure that already added objects gets added to the list of the database.
                    foreach ($this->collPeople as $obj) {
                        if (!$collPeople->contains($obj)) {
                            $collPeople[] = $obj;
                        }
                    }
                }

                $this->collPeople = $collPeople;
                $this->collPeoplePartial = false;
            }
        }

        return $this->collPeople;
    }

    /**
     * Sets a collection of Person objects related by a many-to-many relationship
     * to the current object by way of the record2property_r2p cross-reference table.
     * It will also schedule objects for deletion based on a diff between old objects (aka persisted)
     * and new objects from the given Propel collection.
     *
     * @param  Collection $people A Propel collection.
     * @param  ConnectionInterface $con Optional connection object
     * @return $this|ChildProperty The current object (for fluent API support)
     */
    public function setPeople(Collection $people, ConnectionInterface $con = null)
    {
        $this->clearPeople();
        $currentPeople = $this->getPeople();

        $peopleScheduledForDeletion = $currentPeople->diff($people);

        foreach ($peopleScheduledForDeletion as $toDelete) {
            $this->removePerson($toDelete);
        }

        foreach ($people as $person) {
            if (!$currentPeople->contains($person)) {
                $this->doAddPerson($person);
            }
        }

        $this->collPeoplePartial = false;
        $this->collPeople = $people;

        return $this;
    }

    /**
     * Gets the number of Person objects related by a many-to-many relationship
     * to the current object by way of the record2property_r2p cross-reference table.
     *
     * @param      Criteria $criteria Optional query object to filter the query
     * @param      boolean $distinct Set to true to force count distinct
     * @param      ConnectionInterface $con Optional connection object
     *
     * @return int the number of related Person objects
     */
    public function countPeople(Criteria $criteria = null, $distinct = false, ConnectionInterface $con = null)
    {
        $partial = $this->collPeoplePartial && !$this->isNew();
        if (null === $this->collPeople || null !== $criteria || $partial) {
            if ($this->isNew() && null === $this->collPeople) {
                return 0;
            } else {

                if ($partial && !$criteria) {
                    return count($this->getPeople());
                }

                $query = ChildPersonQuery::create(null, $criteria);
                if ($distinct) {
                    $query->distinct();
                }

                return $query
                    ->filterByProperty($this)
                    ->count($con);
            }
        } else {
            return count($this->collPeople);
        }
    }

    /**
     * Associate a ChildPerson to this object
     * through the record2property_r2p cross reference table.
     *
     * @param ChildPerson $person
     * @return ChildProperty The current object (for fluent API support)
     */
    public function addPerson(ChildPerson $person)
    {
        if ($this->collPeople === null) {
            $this->initPeople();
        }

        if (!$this->getPeople()->contains($person)) {
            // only add it if the **same** object is not already associated
            $this->collPeople->push($person);
            $this->doAddPerson($person);
        }

        return $this;
    }

    /**
     *
     * @param ChildPerson $person
     */
    protected function doAddPerson(ChildPerson $person)
    {
        $personProperty = new ChildPersonProperty();

        $personProperty->setPerson($person);

        $personProperty->setProperty($this);

        $this->addPersonProperty($personProperty);

        // set the back reference to this object directly as using provided method either results
        // in endless loop or in multiple relations
        if (!$person->isPropertiesLoaded()) {
            $person->initProperties();
            $person->getProperties()->push($this);
        } elseif (!$person->getProperties()->contains($this)) {
            $person->getProperties()->push($this);
        }

    }

    /**
     * Remove person of this object
     * through the record2property_r2p cross reference table.
     *
     * @param ChildPerson $person
     * @return ChildProperty The current object (for fluent API support)
     */
    public function removePerson(ChildPerson $person)
    {
        if ($this->getPeople()->contains($person)) {
            $personProperty = new ChildPersonProperty();
            $personProperty->setPerson($person);
            if ($person->isPropertiesLoaded()) {
                //remove the back reference if available
                $person->getProperties()->removeObject($this);
            }

            $personProperty->setProperty($this);
            $this->removePersonProperty(clone $personProperty);
            $personProperty->clear();

            $this->collPeople->remove($this->collPeople->search($person));

            if (null === $this->peopleScheduledForDeletion) {
                $this->peopleScheduledForDeletion = clone $this->collPeople;
                $this->peopleScheduledForDeletion->clear();
            }

            $this->peopleScheduledForDeletion->push($person);
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
        if (null !== $this->aPropertyType) {
            $this->aPropertyType->removeProperty($this);
        }
        $this->pro_id = null;
        $this->pro_class = null;
        $this->pro_prt_id = null;
        $this->pro_name = null;
        $this->pro_description = null;
        $this->pro_prompt = null;
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
            if ($this->collPersonProperties) {
                foreach ($this->collPersonProperties as $o) {
                    $o->clearAllReferences($deep);
                }
            }
            if ($this->collPeople) {
                foreach ($this->collPeople as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        $this->collPersonProperties = null;
        $this->collPeople = null;
        $this->aPropertyType = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(PropertyTableMap::DEFAULT_STRING_FORMAT);
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
