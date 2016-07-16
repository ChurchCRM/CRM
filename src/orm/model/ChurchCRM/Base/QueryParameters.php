<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\QueryParametersQuery as ChildQueryParametersQuery;
use ChurchCRM\Map\QueryParametersTableMap;
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
 * Base class that represents a row from the 'queryparameters_qrp' table.
 *
 *
 *
 * @package    propel.generator.ChurchCRM.Base
 */
abstract class QueryParameters implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\ChurchCRM\\Map\\QueryParametersTableMap';


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
     * The value for the qrp_id field.
     *
     * @var        int
     */
    protected $qrp_id;

    /**
     * The value for the qrp_qry_id field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $qrp_qry_id;

    /**
     * The value for the qrp_type field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $qrp_type;

    /**
     * The value for the qrp_optionsql field.
     *
     * @var        string
     */
    protected $qrp_optionsql;

    /**
     * The value for the qrp_name field.
     *
     * @var        string
     */
    protected $qrp_name;

    /**
     * The value for the qrp_description field.
     *
     * @var        string
     */
    protected $qrp_description;

    /**
     * The value for the qrp_alias field.
     *
     * @var        string
     */
    protected $qrp_alias;

    /**
     * The value for the qrp_default field.
     *
     * @var        string
     */
    protected $qrp_default;

    /**
     * The value for the qrp_required field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $qrp_required;

    /**
     * The value for the qrp_inputboxsize field.
     *
     * Note: this column has a database default value of: 0
     * @var        int
     */
    protected $qrp_inputboxsize;

    /**
     * The value for the qrp_validation field.
     *
     * Note: this column has a database default value of: ''
     * @var        string
     */
    protected $qrp_validation;

    /**
     * The value for the qrp_numericmax field.
     *
     * @var        int
     */
    protected $qrp_numericmax;

    /**
     * The value for the qrp_numericmin field.
     *
     * @var        int
     */
    protected $qrp_numericmin;

    /**
     * The value for the qrp_alphaminlength field.
     *
     * @var        int
     */
    protected $qrp_alphaminlength;

    /**
     * The value for the qrp_alphamaxlength field.
     *
     * @var        int
     */
    protected $qrp_alphamaxlength;

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
        $this->qrp_qry_id = 0;
        $this->qrp_type = 0;
        $this->qrp_required = 0;
        $this->qrp_inputboxsize = 0;
        $this->qrp_validation = '';
    }

    /**
     * Initializes internal state of ChurchCRM\Base\QueryParameters object.
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
     * Compares this with another <code>QueryParameters</code> instance.  If
     * <code>obj</code> is an instance of <code>QueryParameters</code>, delegates to
     * <code>equals(QueryParameters)</code>.  Otherwise, returns <code>false</code>.
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
     * @return $this|QueryParameters The current object, for fluid interface
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
     * Get the [qrp_id] column value.
     *
     * @return int
     */
    public function getId()
    {
        return $this->qrp_id;
    }

    /**
     * Get the [qrp_qry_id] column value.
     *
     * @return int
     */
    public function getQryId()
    {
        return $this->qrp_qry_id;
    }

    /**
     * Get the [qrp_type] column value.
     *
     * @return int
     */
    public function getType()
    {
        return $this->qrp_type;
    }

    /**
     * Get the [qrp_optionsql] column value.
     *
     * @return string
     */
    public function getOptionSQL()
    {
        return $this->qrp_optionsql;
    }

    /**
     * Get the [qrp_name] column value.
     *
     * @return string
     */
    public function getName()
    {
        return $this->qrp_name;
    }

    /**
     * Get the [qrp_description] column value.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->qrp_description;
    }

    /**
     * Get the [qrp_alias] column value.
     *
     * @return string
     */
    public function getAlias()
    {
        return $this->qrp_alias;
    }

    /**
     * Get the [qrp_default] column value.
     *
     * @return string
     */
    public function getDefault()
    {
        return $this->qrp_default;
    }

    /**
     * Get the [qrp_required] column value.
     *
     * @return int
     */
    public function getRequired()
    {
        return $this->qrp_required;
    }

    /**
     * Get the [qrp_inputboxsize] column value.
     *
     * @return int
     */
    public function getInputBoxSize()
    {
        return $this->qrp_inputboxsize;
    }

    /**
     * Get the [qrp_validation] column value.
     *
     * @return string
     */
    public function getValidation()
    {
        return $this->qrp_validation;
    }

    /**
     * Get the [qrp_numericmax] column value.
     *
     * @return int
     */
    public function getNumericMax()
    {
        return $this->qrp_numericmax;
    }

    /**
     * Get the [qrp_numericmin] column value.
     *
     * @return int
     */
    public function getNumericMin()
    {
        return $this->qrp_numericmin;
    }

    /**
     * Get the [qrp_alphaminlength] column value.
     *
     * @return int
     */
    public function getAlphaMinLength()
    {
        return $this->qrp_alphaminlength;
    }

    /**
     * Get the [qrp_alphamaxlength] column value.
     *
     * @return int
     */
    public function getAlphaMaxLength()
    {
        return $this->qrp_alphamaxlength;
    }

    /**
     * Set the value of [qrp_id] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\QueryParameters The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->qrp_id !== $v) {
            $this->qrp_id = $v;
            $this->modifiedColumns[QueryParametersTableMap::COL_QRP_ID] = true;
        }

        return $this;
    } // setId()

    /**
     * Set the value of [qrp_qry_id] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\QueryParameters The current object (for fluent API support)
     */
    public function setQryId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->qrp_qry_id !== $v) {
            $this->qrp_qry_id = $v;
            $this->modifiedColumns[QueryParametersTableMap::COL_QRP_QRY_ID] = true;
        }

        return $this;
    } // setQryId()

    /**
     * Set the value of [qrp_type] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\QueryParameters The current object (for fluent API support)
     */
    public function setType($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->qrp_type !== $v) {
            $this->qrp_type = $v;
            $this->modifiedColumns[QueryParametersTableMap::COL_QRP_TYPE] = true;
        }

        return $this;
    } // setType()

    /**
     * Set the value of [qrp_optionsql] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\QueryParameters The current object (for fluent API support)
     */
    public function setOptionSQL($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->qrp_optionsql !== $v) {
            $this->qrp_optionsql = $v;
            $this->modifiedColumns[QueryParametersTableMap::COL_QRP_OPTIONSQL] = true;
        }

        return $this;
    } // setOptionSQL()

    /**
     * Set the value of [qrp_name] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\QueryParameters The current object (for fluent API support)
     */
    public function setName($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->qrp_name !== $v) {
            $this->qrp_name = $v;
            $this->modifiedColumns[QueryParametersTableMap::COL_QRP_NAME] = true;
        }

        return $this;
    } // setName()

    /**
     * Set the value of [qrp_description] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\QueryParameters The current object (for fluent API support)
     */
    public function setDescription($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->qrp_description !== $v) {
            $this->qrp_description = $v;
            $this->modifiedColumns[QueryParametersTableMap::COL_QRP_DESCRIPTION] = true;
        }

        return $this;
    } // setDescription()

    /**
     * Set the value of [qrp_alias] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\QueryParameters The current object (for fluent API support)
     */
    public function setAlias($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->qrp_alias !== $v) {
            $this->qrp_alias = $v;
            $this->modifiedColumns[QueryParametersTableMap::COL_QRP_ALIAS] = true;
        }

        return $this;
    } // setAlias()

    /**
     * Set the value of [qrp_default] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\QueryParameters The current object (for fluent API support)
     */
    public function setDefault($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->qrp_default !== $v) {
            $this->qrp_default = $v;
            $this->modifiedColumns[QueryParametersTableMap::COL_QRP_DEFAULT] = true;
        }

        return $this;
    } // setDefault()

    /**
     * Set the value of [qrp_required] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\QueryParameters The current object (for fluent API support)
     */
    public function setRequired($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->qrp_required !== $v) {
            $this->qrp_required = $v;
            $this->modifiedColumns[QueryParametersTableMap::COL_QRP_REQUIRED] = true;
        }

        return $this;
    } // setRequired()

    /**
     * Set the value of [qrp_inputboxsize] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\QueryParameters The current object (for fluent API support)
     */
    public function setInputBoxSize($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->qrp_inputboxsize !== $v) {
            $this->qrp_inputboxsize = $v;
            $this->modifiedColumns[QueryParametersTableMap::COL_QRP_INPUTBOXSIZE] = true;
        }

        return $this;
    } // setInputBoxSize()

    /**
     * Set the value of [qrp_validation] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\QueryParameters The current object (for fluent API support)
     */
    public function setValidation($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->qrp_validation !== $v) {
            $this->qrp_validation = $v;
            $this->modifiedColumns[QueryParametersTableMap::COL_QRP_VALIDATION] = true;
        }

        return $this;
    } // setValidation()

    /**
     * Set the value of [qrp_numericmax] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\QueryParameters The current object (for fluent API support)
     */
    public function setNumericMax($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->qrp_numericmax !== $v) {
            $this->qrp_numericmax = $v;
            $this->modifiedColumns[QueryParametersTableMap::COL_QRP_NUMERICMAX] = true;
        }

        return $this;
    } // setNumericMax()

    /**
     * Set the value of [qrp_numericmin] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\QueryParameters The current object (for fluent API support)
     */
    public function setNumericMin($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->qrp_numericmin !== $v) {
            $this->qrp_numericmin = $v;
            $this->modifiedColumns[QueryParametersTableMap::COL_QRP_NUMERICMIN] = true;
        }

        return $this;
    } // setNumericMin()

    /**
     * Set the value of [qrp_alphaminlength] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\QueryParameters The current object (for fluent API support)
     */
    public function setAlphaMinLength($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->qrp_alphaminlength !== $v) {
            $this->qrp_alphaminlength = $v;
            $this->modifiedColumns[QueryParametersTableMap::COL_QRP_ALPHAMINLENGTH] = true;
        }

        return $this;
    } // setAlphaMinLength()

    /**
     * Set the value of [qrp_alphamaxlength] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\QueryParameters The current object (for fluent API support)
     */
    public function setAlphaMaxLength($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->qrp_alphamaxlength !== $v) {
            $this->qrp_alphamaxlength = $v;
            $this->modifiedColumns[QueryParametersTableMap::COL_QRP_ALPHAMAXLENGTH] = true;
        }

        return $this;
    } // setAlphaMaxLength()

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
            if ($this->qrp_qry_id !== 0) {
                return false;
            }

            if ($this->qrp_type !== 0) {
                return false;
            }

            if ($this->qrp_required !== 0) {
                return false;
            }

            if ($this->qrp_inputboxsize !== 0) {
                return false;
            }

            if ($this->qrp_validation !== '') {
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

            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : QueryParametersTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->qrp_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : QueryParametersTableMap::translateFieldName('QryId', TableMap::TYPE_PHPNAME, $indexType)];
            $this->qrp_qry_id = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : QueryParametersTableMap::translateFieldName('Type', TableMap::TYPE_PHPNAME, $indexType)];
            $this->qrp_type = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : QueryParametersTableMap::translateFieldName('OptionSQL', TableMap::TYPE_PHPNAME, $indexType)];
            $this->qrp_optionsql = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : QueryParametersTableMap::translateFieldName('Name', TableMap::TYPE_PHPNAME, $indexType)];
            $this->qrp_name = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : QueryParametersTableMap::translateFieldName('Description', TableMap::TYPE_PHPNAME, $indexType)];
            $this->qrp_description = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : QueryParametersTableMap::translateFieldName('Alias', TableMap::TYPE_PHPNAME, $indexType)];
            $this->qrp_alias = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : QueryParametersTableMap::translateFieldName('Default', TableMap::TYPE_PHPNAME, $indexType)];
            $this->qrp_default = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : QueryParametersTableMap::translateFieldName('Required', TableMap::TYPE_PHPNAME, $indexType)];
            $this->qrp_required = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 9 + $startcol : QueryParametersTableMap::translateFieldName('InputBoxSize', TableMap::TYPE_PHPNAME, $indexType)];
            $this->qrp_inputboxsize = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 10 + $startcol : QueryParametersTableMap::translateFieldName('Validation', TableMap::TYPE_PHPNAME, $indexType)];
            $this->qrp_validation = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 11 + $startcol : QueryParametersTableMap::translateFieldName('NumericMax', TableMap::TYPE_PHPNAME, $indexType)];
            $this->qrp_numericmax = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 12 + $startcol : QueryParametersTableMap::translateFieldName('NumericMin', TableMap::TYPE_PHPNAME, $indexType)];
            $this->qrp_numericmin = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 13 + $startcol : QueryParametersTableMap::translateFieldName('AlphaMinLength', TableMap::TYPE_PHPNAME, $indexType)];
            $this->qrp_alphaminlength = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 14 + $startcol : QueryParametersTableMap::translateFieldName('AlphaMaxLength', TableMap::TYPE_PHPNAME, $indexType)];
            $this->qrp_alphamaxlength = (null !== $col) ? (int) $col : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 15; // 15 = QueryParametersTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException(sprintf('Error populating %s object', '\\ChurchCRM\\QueryParameters'), 0, $e);
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
            $con = Propel::getServiceContainer()->getReadConnection(QueryParametersTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildQueryParametersQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
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
     * @see QueryParameters::setDeleted()
     * @see QueryParameters::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(QueryParametersTableMap::DATABASE_NAME);
        }

        $con->transaction(function () use ($con) {
            $deleteQuery = ChildQueryParametersQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(QueryParametersTableMap::DATABASE_NAME);
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
                QueryParametersTableMap::addInstanceToPool($this);
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

        $this->modifiedColumns[QueryParametersTableMap::COL_QRP_ID] = true;
        if (null !== $this->qrp_id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . QueryParametersTableMap::COL_QRP_ID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_ID)) {
            $modifiedColumns[':p' . $index++]  = 'qrp_ID';
        }
        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_QRY_ID)) {
            $modifiedColumns[':p' . $index++]  = 'qrp_qry_ID';
        }
        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_TYPE)) {
            $modifiedColumns[':p' . $index++]  = 'qrp_Type';
        }
        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_OPTIONSQL)) {
            $modifiedColumns[':p' . $index++]  = 'qrp_OptionSQL';
        }
        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_NAME)) {
            $modifiedColumns[':p' . $index++]  = 'qrp_Name';
        }
        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_DESCRIPTION)) {
            $modifiedColumns[':p' . $index++]  = 'qrp_Description';
        }
        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_ALIAS)) {
            $modifiedColumns[':p' . $index++]  = 'qrp_Alias';
        }
        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_DEFAULT)) {
            $modifiedColumns[':p' . $index++]  = 'qrp_Default';
        }
        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_REQUIRED)) {
            $modifiedColumns[':p' . $index++]  = 'qrp_Required';
        }
        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_INPUTBOXSIZE)) {
            $modifiedColumns[':p' . $index++]  = 'qrp_InputBoxSize';
        }
        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_VALIDATION)) {
            $modifiedColumns[':p' . $index++]  = 'qrp_Validation';
        }
        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_NUMERICMAX)) {
            $modifiedColumns[':p' . $index++]  = 'qrp_NumericMax';
        }
        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_NUMERICMIN)) {
            $modifiedColumns[':p' . $index++]  = 'qrp_NumericMin';
        }
        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_ALPHAMINLENGTH)) {
            $modifiedColumns[':p' . $index++]  = 'qrp_AlphaMinLength';
        }
        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_ALPHAMAXLENGTH)) {
            $modifiedColumns[':p' . $index++]  = 'qrp_AlphaMaxLength';
        }

        $sql = sprintf(
            'INSERT INTO queryparameters_qrp (%s) VALUES (%s)',
            implode(', ', $modifiedColumns),
            implode(', ', array_keys($modifiedColumns))
        );

        try {
            $stmt = $con->prepare($sql);
            foreach ($modifiedColumns as $identifier => $columnName) {
                switch ($columnName) {
                    case 'qrp_ID':
                        $stmt->bindValue($identifier, $this->qrp_id, PDO::PARAM_INT);
                        break;
                    case 'qrp_qry_ID':
                        $stmt->bindValue($identifier, $this->qrp_qry_id, PDO::PARAM_INT);
                        break;
                    case 'qrp_Type':
                        $stmt->bindValue($identifier, $this->qrp_type, PDO::PARAM_INT);
                        break;
                    case 'qrp_OptionSQL':
                        $stmt->bindValue($identifier, $this->qrp_optionsql, PDO::PARAM_STR);
                        break;
                    case 'qrp_Name':
                        $stmt->bindValue($identifier, $this->qrp_name, PDO::PARAM_STR);
                        break;
                    case 'qrp_Description':
                        $stmt->bindValue($identifier, $this->qrp_description, PDO::PARAM_STR);
                        break;
                    case 'qrp_Alias':
                        $stmt->bindValue($identifier, $this->qrp_alias, PDO::PARAM_STR);
                        break;
                    case 'qrp_Default':
                        $stmt->bindValue($identifier, $this->qrp_default, PDO::PARAM_STR);
                        break;
                    case 'qrp_Required':
                        $stmt->bindValue($identifier, $this->qrp_required, PDO::PARAM_INT);
                        break;
                    case 'qrp_InputBoxSize':
                        $stmt->bindValue($identifier, $this->qrp_inputboxsize, PDO::PARAM_INT);
                        break;
                    case 'qrp_Validation':
                        $stmt->bindValue($identifier, $this->qrp_validation, PDO::PARAM_STR);
                        break;
                    case 'qrp_NumericMax':
                        $stmt->bindValue($identifier, $this->qrp_numericmax, PDO::PARAM_INT);
                        break;
                    case 'qrp_NumericMin':
                        $stmt->bindValue($identifier, $this->qrp_numericmin, PDO::PARAM_INT);
                        break;
                    case 'qrp_AlphaMinLength':
                        $stmt->bindValue($identifier, $this->qrp_alphaminlength, PDO::PARAM_INT);
                        break;
                    case 'qrp_AlphaMaxLength':
                        $stmt->bindValue($identifier, $this->qrp_alphamaxlength, PDO::PARAM_INT);
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
        $pos = QueryParametersTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getQryId();
                break;
            case 2:
                return $this->getType();
                break;
            case 3:
                return $this->getOptionSQL();
                break;
            case 4:
                return $this->getName();
                break;
            case 5:
                return $this->getDescription();
                break;
            case 6:
                return $this->getAlias();
                break;
            case 7:
                return $this->getDefault();
                break;
            case 8:
                return $this->getRequired();
                break;
            case 9:
                return $this->getInputBoxSize();
                break;
            case 10:
                return $this->getValidation();
                break;
            case 11:
                return $this->getNumericMax();
                break;
            case 12:
                return $this->getNumericMin();
                break;
            case 13:
                return $this->getAlphaMinLength();
                break;
            case 14:
                return $this->getAlphaMaxLength();
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

        if (isset($alreadyDumpedObjects['QueryParameters'][$this->hashCode()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['QueryParameters'][$this->hashCode()] = true;
        $keys = QueryParametersTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getQryId(),
            $keys[2] => $this->getType(),
            $keys[3] => $this->getOptionSQL(),
            $keys[4] => $this->getName(),
            $keys[5] => $this->getDescription(),
            $keys[6] => $this->getAlias(),
            $keys[7] => $this->getDefault(),
            $keys[8] => $this->getRequired(),
            $keys[9] => $this->getInputBoxSize(),
            $keys[10] => $this->getValidation(),
            $keys[11] => $this->getNumericMax(),
            $keys[12] => $this->getNumericMin(),
            $keys[13] => $this->getAlphaMinLength(),
            $keys[14] => $this->getAlphaMaxLength(),
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
     * @return $this|\ChurchCRM\QueryParameters
     */
    public function setByName($name, $value, $type = TableMap::TYPE_PHPNAME)
    {
        $pos = QueryParametersTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

        return $this->setByPosition($pos, $value);
    }

    /**
     * Sets a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param  int $pos position in xml schema
     * @param  mixed $value field value
     * @return $this|\ChurchCRM\QueryParameters
     */
    public function setByPosition($pos, $value)
    {
        switch ($pos) {
            case 0:
                $this->setId($value);
                break;
            case 1:
                $this->setQryId($value);
                break;
            case 2:
                $this->setType($value);
                break;
            case 3:
                $this->setOptionSQL($value);
                break;
            case 4:
                $this->setName($value);
                break;
            case 5:
                $this->setDescription($value);
                break;
            case 6:
                $this->setAlias($value);
                break;
            case 7:
                $this->setDefault($value);
                break;
            case 8:
                $this->setRequired($value);
                break;
            case 9:
                $this->setInputBoxSize($value);
                break;
            case 10:
                $this->setValidation($value);
                break;
            case 11:
                $this->setNumericMax($value);
                break;
            case 12:
                $this->setNumericMin($value);
                break;
            case 13:
                $this->setAlphaMinLength($value);
                break;
            case 14:
                $this->setAlphaMaxLength($value);
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
        $keys = QueryParametersTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) {
            $this->setId($arr[$keys[0]]);
        }
        if (array_key_exists($keys[1], $arr)) {
            $this->setQryId($arr[$keys[1]]);
        }
        if (array_key_exists($keys[2], $arr)) {
            $this->setType($arr[$keys[2]]);
        }
        if (array_key_exists($keys[3], $arr)) {
            $this->setOptionSQL($arr[$keys[3]]);
        }
        if (array_key_exists($keys[4], $arr)) {
            $this->setName($arr[$keys[4]]);
        }
        if (array_key_exists($keys[5], $arr)) {
            $this->setDescription($arr[$keys[5]]);
        }
        if (array_key_exists($keys[6], $arr)) {
            $this->setAlias($arr[$keys[6]]);
        }
        if (array_key_exists($keys[7], $arr)) {
            $this->setDefault($arr[$keys[7]]);
        }
        if (array_key_exists($keys[8], $arr)) {
            $this->setRequired($arr[$keys[8]]);
        }
        if (array_key_exists($keys[9], $arr)) {
            $this->setInputBoxSize($arr[$keys[9]]);
        }
        if (array_key_exists($keys[10], $arr)) {
            $this->setValidation($arr[$keys[10]]);
        }
        if (array_key_exists($keys[11], $arr)) {
            $this->setNumericMax($arr[$keys[11]]);
        }
        if (array_key_exists($keys[12], $arr)) {
            $this->setNumericMin($arr[$keys[12]]);
        }
        if (array_key_exists($keys[13], $arr)) {
            $this->setAlphaMinLength($arr[$keys[13]]);
        }
        if (array_key_exists($keys[14], $arr)) {
            $this->setAlphaMaxLength($arr[$keys[14]]);
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
     * @return $this|\ChurchCRM\QueryParameters The current object, for fluid interface
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
        $criteria = new Criteria(QueryParametersTableMap::DATABASE_NAME);

        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_ID)) {
            $criteria->add(QueryParametersTableMap::COL_QRP_ID, $this->qrp_id);
        }
        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_QRY_ID)) {
            $criteria->add(QueryParametersTableMap::COL_QRP_QRY_ID, $this->qrp_qry_id);
        }
        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_TYPE)) {
            $criteria->add(QueryParametersTableMap::COL_QRP_TYPE, $this->qrp_type);
        }
        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_OPTIONSQL)) {
            $criteria->add(QueryParametersTableMap::COL_QRP_OPTIONSQL, $this->qrp_optionsql);
        }
        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_NAME)) {
            $criteria->add(QueryParametersTableMap::COL_QRP_NAME, $this->qrp_name);
        }
        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_DESCRIPTION)) {
            $criteria->add(QueryParametersTableMap::COL_QRP_DESCRIPTION, $this->qrp_description);
        }
        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_ALIAS)) {
            $criteria->add(QueryParametersTableMap::COL_QRP_ALIAS, $this->qrp_alias);
        }
        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_DEFAULT)) {
            $criteria->add(QueryParametersTableMap::COL_QRP_DEFAULT, $this->qrp_default);
        }
        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_REQUIRED)) {
            $criteria->add(QueryParametersTableMap::COL_QRP_REQUIRED, $this->qrp_required);
        }
        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_INPUTBOXSIZE)) {
            $criteria->add(QueryParametersTableMap::COL_QRP_INPUTBOXSIZE, $this->qrp_inputboxsize);
        }
        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_VALIDATION)) {
            $criteria->add(QueryParametersTableMap::COL_QRP_VALIDATION, $this->qrp_validation);
        }
        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_NUMERICMAX)) {
            $criteria->add(QueryParametersTableMap::COL_QRP_NUMERICMAX, $this->qrp_numericmax);
        }
        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_NUMERICMIN)) {
            $criteria->add(QueryParametersTableMap::COL_QRP_NUMERICMIN, $this->qrp_numericmin);
        }
        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_ALPHAMINLENGTH)) {
            $criteria->add(QueryParametersTableMap::COL_QRP_ALPHAMINLENGTH, $this->qrp_alphaminlength);
        }
        if ($this->isColumnModified(QueryParametersTableMap::COL_QRP_ALPHAMAXLENGTH)) {
            $criteria->add(QueryParametersTableMap::COL_QRP_ALPHAMAXLENGTH, $this->qrp_alphamaxlength);
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
        $criteria = ChildQueryParametersQuery::create();
        $criteria->add(QueryParametersTableMap::COL_QRP_ID, $this->qrp_id);

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
     * Generic method to set the primary key (qrp_id column).
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
     * @param      object $copyObj An object of \ChurchCRM\QueryParameters (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setQryId($this->getQryId());
        $copyObj->setType($this->getType());
        $copyObj->setOptionSQL($this->getOptionSQL());
        $copyObj->setName($this->getName());
        $copyObj->setDescription($this->getDescription());
        $copyObj->setAlias($this->getAlias());
        $copyObj->setDefault($this->getDefault());
        $copyObj->setRequired($this->getRequired());
        $copyObj->setInputBoxSize($this->getInputBoxSize());
        $copyObj->setValidation($this->getValidation());
        $copyObj->setNumericMax($this->getNumericMax());
        $copyObj->setNumericMin($this->getNumericMin());
        $copyObj->setAlphaMinLength($this->getAlphaMinLength());
        $copyObj->setAlphaMaxLength($this->getAlphaMaxLength());
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
     * @return \ChurchCRM\QueryParameters Clone of current object.
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
        $this->qrp_id = null;
        $this->qrp_qry_id = null;
        $this->qrp_type = null;
        $this->qrp_optionsql = null;
        $this->qrp_name = null;
        $this->qrp_description = null;
        $this->qrp_alias = null;
        $this->qrp_default = null;
        $this->qrp_required = null;
        $this->qrp_inputboxsize = null;
        $this->qrp_validation = null;
        $this->qrp_numericmax = null;
        $this->qrp_numericmin = null;
        $this->qrp_alphaminlength = null;
        $this->qrp_alphamaxlength = null;
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
        return (string) $this->exportTo(QueryParametersTableMap::DEFAULT_STRING_FORMAT);
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
