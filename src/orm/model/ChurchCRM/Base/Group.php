<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\Group as ChildGroup;
use ChurchCRM\GroupQuery as ChildGroupQuery;
use ChurchCRM\Person2group2roleP2g2r as ChildPerson2group2roleP2g2r;
use ChurchCRM\Person2group2roleP2g2rQuery as ChildPerson2group2roleP2g2rQuery;
use ChurchCRM\Map\GroupTableMap;
use ChurchCRM\Map\Person2group2roleP2g2rTableMap;
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
 * Base class that represents a row from the 'group_grp' table.
 *
 *
 *
 * @package    propel.generator.ChurchCRM.Base
 */
abstract class Group implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\ChurchCRM\\Map\\GroupTableMap';


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
     * The value for the grp_id field.
     *
     * @var        int
     */
    protected $grp_id;

    /**
     * The value for the grp_type field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $grp_type;

    /**
     * The value for the grp_rolelistid field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $grp_rolelistid;

    /**
     * The value for the grp_defaultrole field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $grp_defaultrole;

    /**
     * The value for the grp_name field.
     *
     * Note: this column has a database default value of: ''
     * @var        string
     */
    protected $grp_name;

    /**
     * The value for the grp_description field.
     *
     * @var        string
     */
    protected $grp_description;

    /**
     * The value for the grp_hasspecialprops field.
     *
     * Note: this column has a database default value of: 'false'
     * @var        string
     */
    protected $grp_hasspecialprops;

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
        $this->grp_type = 0;
        $this->grp_rolelistid = 0;
        $this->grp_defaultrole = 0;
        $this->grp_name = '';
        $this->grp_hasspecialprops = 'false';
    }

    /**
     * Initializes internal state of ChurchCRM\Base\Group object.
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
     * Compares this with another <code>Group</code> instance.  If
     * <code>obj</code> is an instance of <code>Group</code>, delegates to
     * <code>equals(Group)</code>.  Otherwise, returns <code>false</code>.
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
     * @return $this|Group The current object, for fluid interface
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
     * Get the [grp_id] column value.
     *
     * @return int
     */
    public function getId()
    {
        return $this->grp_id;
    }

    /**
     * Get the [grp_type] column value.
     *
     * @return int
     */
    public function getType()
    {
        return $this->grp_type;
    }

    /**
     * Get the [grp_rolelistid] column value.
     *
     * @return int
     */
    public function getRoleListId()
    {
        return $this->grp_rolelistid;
    }

    /**
     * Get the [grp_defaultrole] column value.
     *
     * @return int
     */
    public function getDefaultRole()
    {
        return $this->grp_defaultrole;
    }

    /**
     * Get the [grp_name] column value.
     *
     * @return string
     */
    public function getName()
    {
        return $this->grp_name;
    }

    /**
     * Get the [grp_description] column value.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->grp_description;
    }

    /**
     * Get the [grp_hasspecialprops] column value.
     *
     * @return string
     */
    public function getHasSpecialProps()
    {
        return $this->grp_hasspecialprops;
    }

    /**
     * Set the value of [grp_id] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Group The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->grp_id !== $v) {
            $this->grp_id = $v;
            $this->modifiedColumns[GroupTableMap::COL_GRP_ID] = true;
        }

        return $this;
    } // setId()

    /**
     * Set the value of [grp_type] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Group The current object (for fluent API support)
     */
    public function setType($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->grp_type !== $v) {
            $this->grp_type = $v;
            $this->modifiedColumns[GroupTableMap::COL_GRP_TYPE] = true;
        }

        return $this;
    } // setType()

    /**
     * Set the value of [grp_rolelistid] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Group The current object (for fluent API support)
     */
    public function setRoleListId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->grp_rolelistid !== $v) {
            $this->grp_rolelistid = $v;
            $this->modifiedColumns[GroupTableMap::COL_GRP_ROLELISTID] = true;
        }

        return $this;
    } // setRoleListId()

    /**
     * Set the value of [grp_defaultrole] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\Group The current object (for fluent API support)
     */
    public function setDefaultRole($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->grp_defaultrole !== $v) {
            $this->grp_defaultrole = $v;
            $this->modifiedColumns[GroupTableMap::COL_GRP_DEFAULTROLE] = true;
        }

        return $this;
    } // setDefaultRole()

    /**
     * Set the value of [grp_name] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Group The current object (for fluent API support)
     */
    public function setName($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->grp_name !== $v) {
            $this->grp_name = $v;
            $this->modifiedColumns[GroupTableMap::COL_GRP_NAME] = true;
        }

        return $this;
    } // setName()

    /**
     * Set the value of [grp_description] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Group The current object (for fluent API support)
     */
    public function setDescription($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->grp_description !== $v) {
            $this->grp_description = $v;
            $this->modifiedColumns[GroupTableMap::COL_GRP_DESCRIPTION] = true;
        }

        return $this;
    } // setDescription()

    /**
     * Set the value of [grp_hasspecialprops] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\Group The current object (for fluent API support)
     */
    public function setHasSpecialProps($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->grp_hasspecialprops !== $v) {
            $this->grp_hasspecialprops = $v;
            $this->modifiedColumns[GroupTableMap::COL_GRP_HASSPECIALPROPS] = true;
        }

        return $this;
    } // setHasSpecialProps()

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
            if ($this->grp_type !== 0) {
                return false;
            }

            if ($this->grp_rolelistid !== 0) {
                return false;
            }

            if ($this->grp_defaultrole !== 0) {
                return false;
            }

            if ($this->grp_name !== '') {
                return false;
            }

            if ($this->grp_hasspecialprops !== 'false') {
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

            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : GroupTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->grp_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : GroupTableMap::translateFieldName('Type', TableMap::TYPE_PHPNAME, $indexType)];
            $this->grp_type = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : GroupTableMap::translateFieldName('RoleListId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->grp_rolelistid = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : GroupTableMap::translateFieldName('DefaultRole', TableMap::TYPE_PHPNAME, $indexType)];
            $this->grp_defaultrole = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : GroupTableMap::translateFieldName('Name', TableMap::TYPE_PHPNAME, $indexType)];
            $this->grp_name = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : GroupTableMap::translateFieldName('Description', TableMap::TYPE_PHPNAME, $indexType)];
            $this->grp_description = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : GroupTableMap::translateFieldName('HasSpecialProps', TableMap::TYPE_PHPNAME, $indexType)];
            $this->grp_hasspecialprops = (null !== $col) ? (string) $col : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 7; // 7 = GroupTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException(sprintf('Error populating %s object', '\\ChurchCRM\\Group'), 0, $e);
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
            $con = Propel::getServiceContainer()->getReadConnection(GroupTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildGroupQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
        $row = $dataFetcher->fetch();
        $dataFetcher->close();
        if (!$row) {
            throw new PropelException('Cannot find matching row in the database to reload object values.');
        }
        $this->hydrate($row, 0, true, $dataFetcher->getIndexType()); // rehydrate

        if ($deep) {  // also de-associate any related objects?

            $this->collPerson2group2roleP2g2rs = null;

        } // if (deep)
    }

    /**
     * Removes this object from datastore and sets delete attribute.
     *
     * @param      ConnectionInterface $con
     * @return void
     * @throws PropelException
     * @see Group::setDeleted()
     * @see Group::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(GroupTableMap::DATABASE_NAME);
        }

        $con->transaction(function () use ($con) {
            $deleteQuery = ChildGroupQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(GroupTableMap::DATABASE_NAME);
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
                GroupTableMap::addInstanceToPool($this);
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

        $this->modifiedColumns[GroupTableMap::COL_GRP_ID] = true;
        if (null !== $this->grp_id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . GroupTableMap::COL_GRP_ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(GroupTableMap::COL_GRP_ID)) {
            $modifiedColumns[':p' . $index++]  = 'grp_ID';
        }
        if ($this->isColumnModified(GroupTableMap::COL_GRP_TYPE)) {
            $modifiedColumns[':p' . $index++]  = 'grp_Type';
        }
        if ($this->isColumnModified(GroupTableMap::COL_GRP_ROLELISTID)) {
            $modifiedColumns[':p' . $index++]  = 'grp_RoleListID';
        }
        if ($this->isColumnModified(GroupTableMap::COL_GRP_DEFAULTROLE)) {
            $modifiedColumns[':p' . $index++]  = 'grp_DefaultRole';
        }
        if ($this->isColumnModified(GroupTableMap::COL_GRP_NAME)) {
            $modifiedColumns[':p' . $index++]  = 'grp_Name';
        }
        if ($this->isColumnModified(GroupTableMap::COL_GRP_DESCRIPTION)) {
            $modifiedColumns[':p' . $index++]  = 'grp_Description';
        }
        if ($this->isColumnModified(GroupTableMap::COL_GRP_HASSPECIALPROPS)) {
            $modifiedColumns[':p' . $index++]  = 'grp_hasSpecialProps';
        }

        $sql = sprintf(
            'INSERT INTO group_grp (%s) VALUES (%s)',
            implode(', ', $modifiedColumns),
            implode(', ', array_keys($modifiedColumns))
        );

        try {
            $stmt = $con->prepare($sql);
            foreach ($modifiedColumns as $identifier => $columnName) {
                switch ($columnName) {
                    case 'grp_ID':
                        $stmt->bindValue($identifier, $this->grp_id, PDO::PARAM_INT);
                        break;
                    case 'grp_Type':
                        $stmt->bindValue($identifier, $this->grp_type, PDO::PARAM_INT);
                        break;
                    case 'grp_RoleListID':
                        $stmt->bindValue($identifier, $this->grp_rolelistid, PDO::PARAM_INT);
                        break;
                    case 'grp_DefaultRole':
                        $stmt->bindValue($identifier, $this->grp_defaultrole, PDO::PARAM_INT);
                        break;
                    case 'grp_Name':
                        $stmt->bindValue($identifier, $this->grp_name, PDO::PARAM_STR);
                        break;
                    case 'grp_Description':
                        $stmt->bindValue($identifier, $this->grp_description, PDO::PARAM_STR);
                        break;
                    case 'grp_hasSpecialProps':
                        $stmt->bindValue($identifier, $this->grp_hasspecialprops, PDO::PARAM_STR);
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
        $pos = GroupTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getType();
                break;
            case 2:
                return $this->getRoleListId();
                break;
            case 3:
                return $this->getDefaultRole();
                break;
            case 4:
                return $this->getName();
                break;
            case 5:
                return $this->getDescription();
                break;
            case 6:
                return $this->getHasSpecialProps();
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

        if (isset($alreadyDumpedObjects['Group'][$this->hashCode()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['Group'][$this->hashCode()] = true;
        $keys = GroupTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getType(),
            $keys[2] => $this->getRoleListId(),
            $keys[3] => $this->getDefaultRole(),
            $keys[4] => $this->getName(),
            $keys[5] => $this->getDescription(),
            $keys[6] => $this->getHasSpecialProps(),
        );
        $virtualColumns = $this->virtualColumns;
        foreach ($virtualColumns as $key => $virtualColumn) {
            $result[$key] = $virtualColumn;
        }

        if ($includeForeignObjects) {
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
     * @return $this|\ChurchCRM\Group
     */
    public function setByName($name, $value, $type = TableMap::TYPE_PHPNAME)
    {
        $pos = GroupTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

        return $this->setByPosition($pos, $value);
    }

    /**
     * Sets a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param  int $pos position in xml schema
     * @param  mixed $value field value
     * @return $this|\ChurchCRM\Group
     */
    public function setByPosition($pos, $value)
    {
        switch ($pos) {
            case 0:
                $this->setId($value);
                break;
            case 1:
                $this->setType($value);
                break;
            case 2:
                $this->setRoleListId($value);
                break;
            case 3:
                $this->setDefaultRole($value);
                break;
            case 4:
                $this->setName($value);
                break;
            case 5:
                $this->setDescription($value);
                break;
            case 6:
                $this->setHasSpecialProps($value);
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
        $keys = GroupTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) {
            $this->setId($arr[$keys[0]]);
        }
        if (array_key_exists($keys[1], $arr)) {
            $this->setType($arr[$keys[1]]);
        }
        if (array_key_exists($keys[2], $arr)) {
            $this->setRoleListId($arr[$keys[2]]);
        }
        if (array_key_exists($keys[3], $arr)) {
            $this->setDefaultRole($arr[$keys[3]]);
        }
        if (array_key_exists($keys[4], $arr)) {
            $this->setName($arr[$keys[4]]);
        }
        if (array_key_exists($keys[5], $arr)) {
            $this->setDescription($arr[$keys[5]]);
        }
        if (array_key_exists($keys[6], $arr)) {
            $this->setHasSpecialProps($arr[$keys[6]]);
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
     * @return $this|\ChurchCRM\Group The current object, for fluid interface
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
        $criteria = new Criteria(GroupTableMap::DATABASE_NAME);

        if ($this->isColumnModified(GroupTableMap::COL_GRP_ID)) {
            $criteria->add(GroupTableMap::COL_GRP_ID, $this->grp_id);
        }
        if ($this->isColumnModified(GroupTableMap::COL_GRP_TYPE)) {
            $criteria->add(GroupTableMap::COL_GRP_TYPE, $this->grp_type);
        }
        if ($this->isColumnModified(GroupTableMap::COL_GRP_ROLELISTID)) {
            $criteria->add(GroupTableMap::COL_GRP_ROLELISTID, $this->grp_rolelistid);
        }
        if ($this->isColumnModified(GroupTableMap::COL_GRP_DEFAULTROLE)) {
            $criteria->add(GroupTableMap::COL_GRP_DEFAULTROLE, $this->grp_defaultrole);
        }
        if ($this->isColumnModified(GroupTableMap::COL_GRP_NAME)) {
            $criteria->add(GroupTableMap::COL_GRP_NAME, $this->grp_name);
        }
        if ($this->isColumnModified(GroupTableMap::COL_GRP_DESCRIPTION)) {
            $criteria->add(GroupTableMap::COL_GRP_DESCRIPTION, $this->grp_description);
        }
        if ($this->isColumnModified(GroupTableMap::COL_GRP_HASSPECIALPROPS)) {
            $criteria->add(GroupTableMap::COL_GRP_HASSPECIALPROPS, $this->grp_hasspecialprops);
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
        $criteria = ChildGroupQuery::create();
        $criteria->add(GroupTableMap::COL_GRP_ID, $this->grp_id);

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
     * Generic method to set the primary key (grp_id column).
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
     * @param      object $copyObj An object of \ChurchCRM\Group (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setType($this->getType());
        $copyObj->setRoleListId($this->getRoleListId());
        $copyObj->setDefaultRole($this->getDefaultRole());
        $copyObj->setName($this->getName());
        $copyObj->setDescription($this->getDescription());
        $copyObj->setHasSpecialProps($this->getHasSpecialProps());

        if ($deepCopy) {
            // important: temporarily setNew(false) because this affects the behavior of
            // the getter/setter methods for fkey referrer objects.
            $copyObj->setNew(false);

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
     * @return \ChurchCRM\Group Clone of current object.
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
        if ('Person2group2roleP2g2r' == $relationName) {
            return $this->initPerson2group2roleP2g2rs();
        }
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
     * If this ChildGroup is new, it will return
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
                    ->filterByGroup($this)
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
     * @return $this|ChildGroup The current object (for fluent API support)
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
            $person2group2roleP2g2rRemoved->setGroup(null);
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
                ->filterByGroup($this)
                ->count($con);
        }

        return count($this->collPerson2group2roleP2g2rs);
    }

    /**
     * Method called to associate a ChildPerson2group2roleP2g2r object to this object
     * through the ChildPerson2group2roleP2g2r foreign key attribute.
     *
     * @param  ChildPerson2group2roleP2g2r $l ChildPerson2group2roleP2g2r
     * @return $this|\ChurchCRM\Group The current object (for fluent API support)
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
        $person2group2roleP2g2r->setGroup($this);
    }

    /**
     * @param  ChildPerson2group2roleP2g2r $person2group2roleP2g2r The ChildPerson2group2roleP2g2r object to remove.
     * @return $this|ChildGroup The current object (for fluent API support)
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
            $person2group2roleP2g2r->setGroup(null);
        }

        return $this;
    }


    /**
     * If this collection has already been initialized with
     * an identical criteria, it returns the collection.
     * Otherwise if this Group is new, it will return
     * an empty collection; or if this Group has previously
     * been saved, it will retrieve related Person2group2roleP2g2rs from storage.
     *
     * This method is protected by default in order to keep the public
     * api reasonable.  You can provide public methods for those you
     * actually need in Group.
     *
     * @param      Criteria $criteria optional Criteria object to narrow the query
     * @param      ConnectionInterface $con optional connection object
     * @param      string $joinBehavior optional join type to use (defaults to Criteria::LEFT_JOIN)
     * @return ObjectCollection|ChildPerson2group2roleP2g2r[] List of ChildPerson2group2roleP2g2r objects
     */
    public function getPerson2group2roleP2g2rsJoinPerson(Criteria $criteria = null, ConnectionInterface $con = null, $joinBehavior = Criteria::LEFT_JOIN)
    {
        $query = ChildPerson2group2roleP2g2rQuery::create(null, $criteria);
        $query->joinWith('Person', $joinBehavior);

        return $this->getPerson2group2roleP2g2rs($query, $con);
    }

    /**
     * Clears the current object, sets all attributes to their default values and removes
     * outgoing references as well as back-references (from other objects to this one. Results probably in a database
     * change of those foreign objects when you call `save` there).
     */
    public function clear()
    {
        $this->grp_id = null;
        $this->grp_type = null;
        $this->grp_rolelistid = null;
        $this->grp_defaultrole = null;
        $this->grp_name = null;
        $this->grp_description = null;
        $this->grp_hasspecialprops = null;
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
            if ($this->collPerson2group2roleP2g2rs) {
                foreach ($this->collPerson2group2roleP2g2rs as $o) {
                    $o->clearAllReferences($deep);
                }
            }
        } // if ($deep)

        $this->collPerson2group2roleP2g2rs = null;
    }

    /**
     * Return the string representation of this object
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->exportTo(GroupTableMap::DEFAULT_STRING_FORMAT);
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
