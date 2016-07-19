<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\MenuConfigQuery as ChildMenuConfigQuery;
use ChurchCRM\Map\MenuConfigTableMap;
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
 * Base class that represents a row from the 'menuconfig_mcf' table.
 *
 *
 *
 * @package    propel.generator.ChurchCRM.Base
 */
abstract class MenuConfig implements ActiveRecordInterface
{
    /**
     * TableMap class name
     */
    const TABLE_MAP = '\\ChurchCRM\\Map\\MenuConfigTableMap';


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
     * The value for the mid field.
     *
     * @var        int
     */
    protected $mid;

    /**
     * The value for the name field.
     *
     * @var        string
     */
    protected $name;

    /**
     * The value for the parent field.
     *
     * @var        string
     */
    protected $parent;

    /**
     * The value for the ismenu field.
     *
     * @var        boolean
     */
    protected $ismenu;

    /**
     * The value for the content_english field.
     *
     * @var        string
     */
    protected $content_english;

    /**
     * The value for the content field.
     *
     * @var        string
     */
    protected $content;

    /**
     * The value for the uri field.
     *
     * @var        string
     */
    protected $uri;

    /**
     * The value for the statustext field.
     *
     * @var        string
     */
    protected $statustext;

    /**
     * The value for the security_grp field.
     *
     * @var        string
     */
    protected $security_grp;

    /**
     * The value for the session_var field.
     *
     * @var        string
     */
    protected $session_var;

    /**
     * The value for the session_var_in_text field.
     *
     * @var        boolean
     */
    protected $session_var_in_text;

    /**
     * The value for the session_var_in_uri field.
     *
     * @var        boolean
     */
    protected $session_var_in_uri;

    /**
     * The value for the url_parm_name field.
     *
     * @var        string
     */
    protected $url_parm_name;

    /**
     * The value for the active field.
     *
     * @var        boolean
     */
    protected $active;

    /**
     * The value for the sortorder field.
     *
     * @var        int
     */
    protected $sortorder;

    /**
     * The value for the icon field.
     *
     * @var        string
     */
    protected $icon;

    /**
     * Flag to prevent endless save loop, if this object is referenced
     * by another object which falls in this transaction.
     *
     * @var boolean
     */
    protected $alreadyInSave = false;

    /**
     * Initializes internal state of ChurchCRM\Base\MenuConfig object.
     */
    public function __construct()
    {
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
     * Compares this with another <code>MenuConfig</code> instance.  If
     * <code>obj</code> is an instance of <code>MenuConfig</code>, delegates to
     * <code>equals(MenuConfig)</code>.  Otherwise, returns <code>false</code>.
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
     * @return $this|MenuConfig The current object, for fluid interface
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
     * Get the [mid] column value.
     *
     * @return int
     */
    public function getId()
    {
        return $this->mid;
    }

    /**
     * Get the [name] column value.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get the [parent] column value.
     *
     * @return string
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Get the [ismenu] column value.
     *
     * @return boolean
     */
    public function getMenu()
    {
        return $this->ismenu;
    }

    /**
     * Get the [ismenu] column value.
     *
     * @return boolean
     */
    public function isMenu()
    {
        return $this->getMenu();
    }

    /**
     * Get the [content_english] column value.
     *
     * @return string
     */
    public function getContentEnglish()
    {
        return $this->content_english;
    }

    /**
     * Get the [content] column value.
     *
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Get the [uri] column value.
     *
     * @return string
     */
    public function getURI()
    {
        return $this->uri;
    }

    /**
     * Get the [statustext] column value.
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->statustext;
    }

    /**
     * Get the [security_grp] column value.
     *
     * @return string
     */
    public function getSecurityGroup()
    {
        return $this->security_grp;
    }

    /**
     * Get the [session_var] column value.
     *
     * @return string
     */
    public function getSessionVar()
    {
        return $this->session_var;
    }

    /**
     * Get the [session_var_in_text] column value.
     *
     * @return boolean
     */
    public function getSessionVarInText()
    {
        return $this->session_var_in_text;
    }

    /**
     * Get the [session_var_in_text] column value.
     *
     * @return boolean
     */
    public function isSessionVarInText()
    {
        return $this->getSessionVarInText();
    }

    /**
     * Get the [session_var_in_uri] column value.
     *
     * @return boolean
     */
    public function getSessionVarInURI()
    {
        return $this->session_var_in_uri;
    }

    /**
     * Get the [session_var_in_uri] column value.
     *
     * @return boolean
     */
    public function isSessionVarInURI()
    {
        return $this->getSessionVarInURI();
    }

    /**
     * Get the [url_parm_name] column value.
     *
     * @return string
     */
    public function getURLParmName()
    {
        return $this->url_parm_name;
    }

    /**
     * Get the [active] column value.
     *
     * @return boolean
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * Get the [active] column value.
     *
     * @return boolean
     */
    public function isActive()
    {
        return $this->getActive();
    }

    /**
     * Get the [sortorder] column value.
     *
     * @return int
     */
    public function getSortOrder()
    {
        return $this->sortorder;
    }

    /**
     * Get the [icon] column value.
     *
     * @return string
     */
    public function getIcon()
    {
        return $this->icon;
    }

    /**
     * Set the value of [mid] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\MenuConfig The current object (for fluent API support)
     */
    public function setId($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->mid !== $v) {
            $this->mid = $v;
            $this->modifiedColumns[MenuConfigTableMap::COL_MID] = true;
        }

        return $this;
    } // setId()

    /**
     * Set the value of [name] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\MenuConfig The current object (for fluent API support)
     */
    public function setName($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->name !== $v) {
            $this->name = $v;
            $this->modifiedColumns[MenuConfigTableMap::COL_NAME] = true;
        }

        return $this;
    } // setName()

    /**
     * Set the value of [parent] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\MenuConfig The current object (for fluent API support)
     */
    public function setParent($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->parent !== $v) {
            $this->parent = $v;
            $this->modifiedColumns[MenuConfigTableMap::COL_PARENT] = true;
        }

        return $this;
    } // setParent()

    /**
     * Sets the value of the [ismenu] column.
     * Non-boolean arguments are converted using the following rules:
     *   * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *   * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     * Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     *
     * @param  boolean|integer|string $v The new value
     * @return $this|\ChurchCRM\MenuConfig The current object (for fluent API support)
     */
    public function setMenu($v)
    {
        if ($v !== null) {
            if (is_string($v)) {
                $v = in_array(strtolower($v), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
            } else {
                $v = (boolean) $v;
            }
        }

        if ($this->ismenu !== $v) {
            $this->ismenu = $v;
            $this->modifiedColumns[MenuConfigTableMap::COL_ISMENU] = true;
        }

        return $this;
    } // setMenu()

    /**
     * Set the value of [content_english] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\MenuConfig The current object (for fluent API support)
     */
    public function setContentEnglish($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->content_english !== $v) {
            $this->content_english = $v;
            $this->modifiedColumns[MenuConfigTableMap::COL_CONTENT_ENGLISH] = true;
        }

        return $this;
    } // setContentEnglish()

    /**
     * Set the value of [content] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\MenuConfig The current object (for fluent API support)
     */
    public function setContent($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->content !== $v) {
            $this->content = $v;
            $this->modifiedColumns[MenuConfigTableMap::COL_CONTENT] = true;
        }

        return $this;
    } // setContent()

    /**
     * Set the value of [uri] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\MenuConfig The current object (for fluent API support)
     */
    public function setURI($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->uri !== $v) {
            $this->uri = $v;
            $this->modifiedColumns[MenuConfigTableMap::COL_URI] = true;
        }

        return $this;
    } // setURI()

    /**
     * Set the value of [statustext] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\MenuConfig The current object (for fluent API support)
     */
    public function setStatus($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->statustext !== $v) {
            $this->statustext = $v;
            $this->modifiedColumns[MenuConfigTableMap::COL_STATUSTEXT] = true;
        }

        return $this;
    } // setStatus()

    /**
     * Set the value of [security_grp] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\MenuConfig The current object (for fluent API support)
     */
    public function setSecurityGroup($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->security_grp !== $v) {
            $this->security_grp = $v;
            $this->modifiedColumns[MenuConfigTableMap::COL_SECURITY_GRP] = true;
        }

        return $this;
    } // setSecurityGroup()

    /**
     * Set the value of [session_var] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\MenuConfig The current object (for fluent API support)
     */
    public function setSessionVar($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->session_var !== $v) {
            $this->session_var = $v;
            $this->modifiedColumns[MenuConfigTableMap::COL_SESSION_VAR] = true;
        }

        return $this;
    } // setSessionVar()

    /**
     * Sets the value of the [session_var_in_text] column.
     * Non-boolean arguments are converted using the following rules:
     *   * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *   * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     * Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     *
     * @param  boolean|integer|string $v The new value
     * @return $this|\ChurchCRM\MenuConfig The current object (for fluent API support)
     */
    public function setSessionVarInText($v)
    {
        if ($v !== null) {
            if (is_string($v)) {
                $v = in_array(strtolower($v), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
            } else {
                $v = (boolean) $v;
            }
        }

        if ($this->session_var_in_text !== $v) {
            $this->session_var_in_text = $v;
            $this->modifiedColumns[MenuConfigTableMap::COL_SESSION_VAR_IN_TEXT] = true;
        }

        return $this;
    } // setSessionVarInText()

    /**
     * Sets the value of the [session_var_in_uri] column.
     * Non-boolean arguments are converted using the following rules:
     *   * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *   * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     * Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     *
     * @param  boolean|integer|string $v The new value
     * @return $this|\ChurchCRM\MenuConfig The current object (for fluent API support)
     */
    public function setSessionVarInURI($v)
    {
        if ($v !== null) {
            if (is_string($v)) {
                $v = in_array(strtolower($v), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
            } else {
                $v = (boolean) $v;
            }
        }

        if ($this->session_var_in_uri !== $v) {
            $this->session_var_in_uri = $v;
            $this->modifiedColumns[MenuConfigTableMap::COL_SESSION_VAR_IN_URI] = true;
        }

        return $this;
    } // setSessionVarInURI()

    /**
     * Set the value of [url_parm_name] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\MenuConfig The current object (for fluent API support)
     */
    public function setURLParmName($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->url_parm_name !== $v) {
            $this->url_parm_name = $v;
            $this->modifiedColumns[MenuConfigTableMap::COL_URL_PARM_NAME] = true;
        }

        return $this;
    } // setURLParmName()

    /**
     * Sets the value of the [active] column.
     * Non-boolean arguments are converted using the following rules:
     *   * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *   * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     * Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     *
     * @param  boolean|integer|string $v The new value
     * @return $this|\ChurchCRM\MenuConfig The current object (for fluent API support)
     */
    public function setActive($v)
    {
        if ($v !== null) {
            if (is_string($v)) {
                $v = in_array(strtolower($v), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
            } else {
                $v = (boolean) $v;
            }
        }

        if ($this->active !== $v) {
            $this->active = $v;
            $this->modifiedColumns[MenuConfigTableMap::COL_ACTIVE] = true;
        }

        return $this;
    } // setActive()

    /**
     * Set the value of [sortorder] column.
     *
     * @param int $v new value
     * @return $this|\ChurchCRM\MenuConfig The current object (for fluent API support)
     */
    public function setSortOrder($v)
    {
        if ($v !== null) {
            $v = (int) $v;
        }

        if ($this->sortorder !== $v) {
            $this->sortorder = $v;
            $this->modifiedColumns[MenuConfigTableMap::COL_SORTORDER] = true;
        }

        return $this;
    } // setSortOrder()

    /**
     * Set the value of [icon] column.
     *
     * @param string $v new value
     * @return $this|\ChurchCRM\MenuConfig The current object (for fluent API support)
     */
    public function setIcon($v)
    {
        if ($v !== null) {
            $v = (string) $v;
        }

        if ($this->icon !== $v) {
            $this->icon = $v;
            $this->modifiedColumns[MenuConfigTableMap::COL_ICON] = true;
        }

        return $this;
    } // setIcon()

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

            $col = $row[TableMap::TYPE_NUM == $indexType ? 0 + $startcol : MenuConfigTableMap::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
            $this->mid = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 1 + $startcol : MenuConfigTableMap::translateFieldName('Name', TableMap::TYPE_PHPNAME, $indexType)];
            $this->name = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 2 + $startcol : MenuConfigTableMap::translateFieldName('Parent', TableMap::TYPE_PHPNAME, $indexType)];
            $this->parent = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 3 + $startcol : MenuConfigTableMap::translateFieldName('Menu', TableMap::TYPE_PHPNAME, $indexType)];
            $this->ismenu = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 4 + $startcol : MenuConfigTableMap::translateFieldName('ContentEnglish', TableMap::TYPE_PHPNAME, $indexType)];
            $this->content_english = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 5 + $startcol : MenuConfigTableMap::translateFieldName('Content', TableMap::TYPE_PHPNAME, $indexType)];
            $this->content = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 6 + $startcol : MenuConfigTableMap::translateFieldName('URI', TableMap::TYPE_PHPNAME, $indexType)];
            $this->uri = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 7 + $startcol : MenuConfigTableMap::translateFieldName('Status', TableMap::TYPE_PHPNAME, $indexType)];
            $this->statustext = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 8 + $startcol : MenuConfigTableMap::translateFieldName('SecurityGroup', TableMap::TYPE_PHPNAME, $indexType)];
            $this->security_grp = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 9 + $startcol : MenuConfigTableMap::translateFieldName('SessionVar', TableMap::TYPE_PHPNAME, $indexType)];
            $this->session_var = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 10 + $startcol : MenuConfigTableMap::translateFieldName('SessionVarInText', TableMap::TYPE_PHPNAME, $indexType)];
            $this->session_var_in_text = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 11 + $startcol : MenuConfigTableMap::translateFieldName('SessionVarInURI', TableMap::TYPE_PHPNAME, $indexType)];
            $this->session_var_in_uri = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 12 + $startcol : MenuConfigTableMap::translateFieldName('URLParmName', TableMap::TYPE_PHPNAME, $indexType)];
            $this->url_parm_name = (null !== $col) ? (string) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 13 + $startcol : MenuConfigTableMap::translateFieldName('Active', TableMap::TYPE_PHPNAME, $indexType)];
            $this->active = (null !== $col) ? (boolean) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 14 + $startcol : MenuConfigTableMap::translateFieldName('SortOrder', TableMap::TYPE_PHPNAME, $indexType)];
            $this->sortorder = (null !== $col) ? (int) $col : null;

            $col = $row[TableMap::TYPE_NUM == $indexType ? 15 + $startcol : MenuConfigTableMap::translateFieldName('Icon', TableMap::TYPE_PHPNAME, $indexType)];
            $this->icon = (null !== $col) ? (string) $col : null;
            $this->resetModified();

            $this->setNew(false);

            if ($rehydrate) {
                $this->ensureConsistency();
            }

            return $startcol + 16; // 16 = MenuConfigTableMap::NUM_HYDRATE_COLUMNS.

        } catch (Exception $e) {
            throw new PropelException(sprintf('Error populating %s object', '\\ChurchCRM\\MenuConfig'), 0, $e);
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
            $con = Propel::getServiceContainer()->getReadConnection(MenuConfigTableMap::DATABASE_NAME);
        }

        // We don't need to alter the object instance pool; we're just modifying this instance
        // already in the pool.

        $dataFetcher = ChildMenuConfigQuery::create(null, $this->buildPkeyCriteria())->setFormatter(ModelCriteria::FORMAT_STATEMENT)->find($con);
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
     * @see MenuConfig::setDeleted()
     * @see MenuConfig::isDeleted()
     */
    public function delete(ConnectionInterface $con = null)
    {
        if ($this->isDeleted()) {
            throw new PropelException("This object has already been deleted.");
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getWriteConnection(MenuConfigTableMap::DATABASE_NAME);
        }

        $con->transaction(function () use ($con) {
            $deleteQuery = ChildMenuConfigQuery::create()
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
            $con = Propel::getServiceContainer()->getWriteConnection(MenuConfigTableMap::DATABASE_NAME);
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
                MenuConfigTableMap::addInstanceToPool($this);
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

        $this->modifiedColumns[MenuConfigTableMap::COL_MID] = true;
        if (null !== $this->mid) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . MenuConfigTableMap::COL_MID . ')');
        }

         // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(MenuConfigTableMap::COL_MID)) {
            $modifiedColumns[':p' . $index++]  = 'mid';
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_NAME)) {
            $modifiedColumns[':p' . $index++]  = 'name';
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_PARENT)) {
            $modifiedColumns[':p' . $index++]  = 'parent';
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_ISMENU)) {
            $modifiedColumns[':p' . $index++]  = 'ismenu';
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_CONTENT_ENGLISH)) {
            $modifiedColumns[':p' . $index++]  = 'content_english';
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_CONTENT)) {
            $modifiedColumns[':p' . $index++]  = 'content';
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_URI)) {
            $modifiedColumns[':p' . $index++]  = 'uri';
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_STATUSTEXT)) {
            $modifiedColumns[':p' . $index++]  = 'statustext';
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_SECURITY_GRP)) {
            $modifiedColumns[':p' . $index++]  = 'security_grp';
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_SESSION_VAR)) {
            $modifiedColumns[':p' . $index++]  = 'session_var';
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_SESSION_VAR_IN_TEXT)) {
            $modifiedColumns[':p' . $index++]  = 'session_var_in_text';
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_SESSION_VAR_IN_URI)) {
            $modifiedColumns[':p' . $index++]  = 'session_var_in_uri';
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_URL_PARM_NAME)) {
            $modifiedColumns[':p' . $index++]  = 'url_parm_name';
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_ACTIVE)) {
            $modifiedColumns[':p' . $index++]  = 'active';
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_SORTORDER)) {
            $modifiedColumns[':p' . $index++]  = 'sortorder';
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_ICON)) {
            $modifiedColumns[':p' . $index++]  = 'icon';
        }

        $sql = sprintf(
            'INSERT INTO menuconfig_mcf (%s) VALUES (%s)',
            implode(', ', $modifiedColumns),
            implode(', ', array_keys($modifiedColumns))
        );

        try {
            $stmt = $con->prepare($sql);
            foreach ($modifiedColumns as $identifier => $columnName) {
                switch ($columnName) {
                    case 'mid':
                        $stmt->bindValue($identifier, $this->mid, PDO::PARAM_INT);
                        break;
                    case 'name':
                        $stmt->bindValue($identifier, $this->name, PDO::PARAM_STR);
                        break;
                    case 'parent':
                        $stmt->bindValue($identifier, $this->parent, PDO::PARAM_STR);
                        break;
                    case 'ismenu':
                        $stmt->bindValue($identifier, (int) $this->ismenu, PDO::PARAM_INT);
                        break;
                    case 'content_english':
                        $stmt->bindValue($identifier, $this->content_english, PDO::PARAM_STR);
                        break;
                    case 'content':
                        $stmt->bindValue($identifier, $this->content, PDO::PARAM_STR);
                        break;
                    case 'uri':
                        $stmt->bindValue($identifier, $this->uri, PDO::PARAM_STR);
                        break;
                    case 'statustext':
                        $stmt->bindValue($identifier, $this->statustext, PDO::PARAM_STR);
                        break;
                    case 'security_grp':
                        $stmt->bindValue($identifier, $this->security_grp, PDO::PARAM_STR);
                        break;
                    case 'session_var':
                        $stmt->bindValue($identifier, $this->session_var, PDO::PARAM_STR);
                        break;
                    case 'session_var_in_text':
                        $stmt->bindValue($identifier, (int) $this->session_var_in_text, PDO::PARAM_INT);
                        break;
                    case 'session_var_in_uri':
                        $stmt->bindValue($identifier, (int) $this->session_var_in_uri, PDO::PARAM_INT);
                        break;
                    case 'url_parm_name':
                        $stmt->bindValue($identifier, $this->url_parm_name, PDO::PARAM_STR);
                        break;
                    case 'active':
                        $stmt->bindValue($identifier, (int) $this->active, PDO::PARAM_INT);
                        break;
                    case 'sortorder':
                        $stmt->bindValue($identifier, $this->sortorder, PDO::PARAM_INT);
                        break;
                    case 'icon':
                        $stmt->bindValue($identifier, $this->icon, PDO::PARAM_STR);
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
        $pos = MenuConfigTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);
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
                return $this->getParent();
                break;
            case 3:
                return $this->getMenu();
                break;
            case 4:
                return $this->getContentEnglish();
                break;
            case 5:
                return $this->getContent();
                break;
            case 6:
                return $this->getURI();
                break;
            case 7:
                return $this->getStatus();
                break;
            case 8:
                return $this->getSecurityGroup();
                break;
            case 9:
                return $this->getSessionVar();
                break;
            case 10:
                return $this->getSessionVarInText();
                break;
            case 11:
                return $this->getSessionVarInURI();
                break;
            case 12:
                return $this->getURLParmName();
                break;
            case 13:
                return $this->getActive();
                break;
            case 14:
                return $this->getSortOrder();
                break;
            case 15:
                return $this->getIcon();
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

        if (isset($alreadyDumpedObjects['MenuConfig'][$this->hashCode()])) {
            return '*RECURSION*';
        }
        $alreadyDumpedObjects['MenuConfig'][$this->hashCode()] = true;
        $keys = MenuConfigTableMap::getFieldNames($keyType);
        $result = array(
            $keys[0] => $this->getId(),
            $keys[1] => $this->getName(),
            $keys[2] => $this->getParent(),
            $keys[3] => $this->getMenu(),
            $keys[4] => $this->getContentEnglish(),
            $keys[5] => $this->getContent(),
            $keys[6] => $this->getURI(),
            $keys[7] => $this->getStatus(),
            $keys[8] => $this->getSecurityGroup(),
            $keys[9] => $this->getSessionVar(),
            $keys[10] => $this->getSessionVarInText(),
            $keys[11] => $this->getSessionVarInURI(),
            $keys[12] => $this->getURLParmName(),
            $keys[13] => $this->getActive(),
            $keys[14] => $this->getSortOrder(),
            $keys[15] => $this->getIcon(),
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
     * @return $this|\ChurchCRM\MenuConfig
     */
    public function setByName($name, $value, $type = TableMap::TYPE_PHPNAME)
    {
        $pos = MenuConfigTableMap::translateFieldName($name, $type, TableMap::TYPE_NUM);

        return $this->setByPosition($pos, $value);
    }

    /**
     * Sets a field from the object by Position as specified in the xml schema.
     * Zero-based.
     *
     * @param  int $pos position in xml schema
     * @param  mixed $value field value
     * @return $this|\ChurchCRM\MenuConfig
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
                $this->setParent($value);
                break;
            case 3:
                $this->setMenu($value);
                break;
            case 4:
                $this->setContentEnglish($value);
                break;
            case 5:
                $this->setContent($value);
                break;
            case 6:
                $this->setURI($value);
                break;
            case 7:
                $this->setStatus($value);
                break;
            case 8:
                $this->setSecurityGroup($value);
                break;
            case 9:
                $this->setSessionVar($value);
                break;
            case 10:
                $this->setSessionVarInText($value);
                break;
            case 11:
                $this->setSessionVarInURI($value);
                break;
            case 12:
                $this->setURLParmName($value);
                break;
            case 13:
                $this->setActive($value);
                break;
            case 14:
                $this->setSortOrder($value);
                break;
            case 15:
                $this->setIcon($value);
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
        $keys = MenuConfigTableMap::getFieldNames($keyType);

        if (array_key_exists($keys[0], $arr)) {
            $this->setId($arr[$keys[0]]);
        }
        if (array_key_exists($keys[1], $arr)) {
            $this->setName($arr[$keys[1]]);
        }
        if (array_key_exists($keys[2], $arr)) {
            $this->setParent($arr[$keys[2]]);
        }
        if (array_key_exists($keys[3], $arr)) {
            $this->setMenu($arr[$keys[3]]);
        }
        if (array_key_exists($keys[4], $arr)) {
            $this->setContentEnglish($arr[$keys[4]]);
        }
        if (array_key_exists($keys[5], $arr)) {
            $this->setContent($arr[$keys[5]]);
        }
        if (array_key_exists($keys[6], $arr)) {
            $this->setURI($arr[$keys[6]]);
        }
        if (array_key_exists($keys[7], $arr)) {
            $this->setStatus($arr[$keys[7]]);
        }
        if (array_key_exists($keys[8], $arr)) {
            $this->setSecurityGroup($arr[$keys[8]]);
        }
        if (array_key_exists($keys[9], $arr)) {
            $this->setSessionVar($arr[$keys[9]]);
        }
        if (array_key_exists($keys[10], $arr)) {
            $this->setSessionVarInText($arr[$keys[10]]);
        }
        if (array_key_exists($keys[11], $arr)) {
            $this->setSessionVarInURI($arr[$keys[11]]);
        }
        if (array_key_exists($keys[12], $arr)) {
            $this->setURLParmName($arr[$keys[12]]);
        }
        if (array_key_exists($keys[13], $arr)) {
            $this->setActive($arr[$keys[13]]);
        }
        if (array_key_exists($keys[14], $arr)) {
            $this->setSortOrder($arr[$keys[14]]);
        }
        if (array_key_exists($keys[15], $arr)) {
            $this->setIcon($arr[$keys[15]]);
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
     * @return $this|\ChurchCRM\MenuConfig The current object, for fluid interface
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
        $criteria = new Criteria(MenuConfigTableMap::DATABASE_NAME);

        if ($this->isColumnModified(MenuConfigTableMap::COL_MID)) {
            $criteria->add(MenuConfigTableMap::COL_MID, $this->mid);
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_NAME)) {
            $criteria->add(MenuConfigTableMap::COL_NAME, $this->name);
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_PARENT)) {
            $criteria->add(MenuConfigTableMap::COL_PARENT, $this->parent);
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_ISMENU)) {
            $criteria->add(MenuConfigTableMap::COL_ISMENU, $this->ismenu);
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_CONTENT_ENGLISH)) {
            $criteria->add(MenuConfigTableMap::COL_CONTENT_ENGLISH, $this->content_english);
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_CONTENT)) {
            $criteria->add(MenuConfigTableMap::COL_CONTENT, $this->content);
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_URI)) {
            $criteria->add(MenuConfigTableMap::COL_URI, $this->uri);
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_STATUSTEXT)) {
            $criteria->add(MenuConfigTableMap::COL_STATUSTEXT, $this->statustext);
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_SECURITY_GRP)) {
            $criteria->add(MenuConfigTableMap::COL_SECURITY_GRP, $this->security_grp);
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_SESSION_VAR)) {
            $criteria->add(MenuConfigTableMap::COL_SESSION_VAR, $this->session_var);
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_SESSION_VAR_IN_TEXT)) {
            $criteria->add(MenuConfigTableMap::COL_SESSION_VAR_IN_TEXT, $this->session_var_in_text);
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_SESSION_VAR_IN_URI)) {
            $criteria->add(MenuConfigTableMap::COL_SESSION_VAR_IN_URI, $this->session_var_in_uri);
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_URL_PARM_NAME)) {
            $criteria->add(MenuConfigTableMap::COL_URL_PARM_NAME, $this->url_parm_name);
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_ACTIVE)) {
            $criteria->add(MenuConfigTableMap::COL_ACTIVE, $this->active);
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_SORTORDER)) {
            $criteria->add(MenuConfigTableMap::COL_SORTORDER, $this->sortorder);
        }
        if ($this->isColumnModified(MenuConfigTableMap::COL_ICON)) {
            $criteria->add(MenuConfigTableMap::COL_ICON, $this->icon);
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
        $criteria = ChildMenuConfigQuery::create();
        $criteria->add(MenuConfigTableMap::COL_MID, $this->mid);

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
     * Generic method to set the primary key (mid column).
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
     * @param      object $copyObj An object of \ChurchCRM\MenuConfig (or compatible) type.
     * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
     * @param      boolean $makeNew Whether to reset autoincrement PKs and make the object new.
     * @throws PropelException
     */
    public function copyInto($copyObj, $deepCopy = false, $makeNew = true)
    {
        $copyObj->setName($this->getName());
        $copyObj->setParent($this->getParent());
        $copyObj->setMenu($this->getMenu());
        $copyObj->setContentEnglish($this->getContentEnglish());
        $copyObj->setContent($this->getContent());
        $copyObj->setURI($this->getURI());
        $copyObj->setStatus($this->getStatus());
        $copyObj->setSecurityGroup($this->getSecurityGroup());
        $copyObj->setSessionVar($this->getSessionVar());
        $copyObj->setSessionVarInText($this->getSessionVarInText());
        $copyObj->setSessionVarInURI($this->getSessionVarInURI());
        $copyObj->setURLParmName($this->getURLParmName());
        $copyObj->setActive($this->getActive());
        $copyObj->setSortOrder($this->getSortOrder());
        $copyObj->setIcon($this->getIcon());
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
     * @return \ChurchCRM\MenuConfig Clone of current object.
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
        $this->mid = null;
        $this->name = null;
        $this->parent = null;
        $this->ismenu = null;
        $this->content_english = null;
        $this->content = null;
        $this->uri = null;
        $this->statustext = null;
        $this->security_grp = null;
        $this->session_var = null;
        $this->session_var_in_text = null;
        $this->session_var_in_uri = null;
        $this->url_parm_name = null;
        $this->active = null;
        $this->sortorder = null;
        $this->icon = null;
        $this->alreadyInSave = false;
        $this->clearAllReferences();
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
        return (string) $this->exportTo(MenuConfigTableMap::DEFAULT_STRING_FORMAT);
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
