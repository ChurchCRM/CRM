<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\MenuConfig as ChildMenuConfig;
use ChurchCRM\MenuConfigQuery as ChildMenuConfigQuery;
use ChurchCRM\Map\MenuConfigTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'menuconfig_mcf' table.
 *
 *
 *
 * @method     ChildMenuConfigQuery orderById($order = Criteria::ASC) Order by the mid column
 * @method     ChildMenuConfigQuery orderByName($order = Criteria::ASC) Order by the name column
 * @method     ChildMenuConfigQuery orderByParent($order = Criteria::ASC) Order by the parent column
 * @method     ChildMenuConfigQuery orderByMenu($order = Criteria::ASC) Order by the ismenu column
 * @method     ChildMenuConfigQuery orderByContentEnglish($order = Criteria::ASC) Order by the content_english column
 * @method     ChildMenuConfigQuery orderByContent($order = Criteria::ASC) Order by the content column
 * @method     ChildMenuConfigQuery orderByURI($order = Criteria::ASC) Order by the uri column
 * @method     ChildMenuConfigQuery orderByStatus($order = Criteria::ASC) Order by the statustext column
 * @method     ChildMenuConfigQuery orderBySecurityGroup($order = Criteria::ASC) Order by the security_grp column
 * @method     ChildMenuConfigQuery orderBySessionVar($order = Criteria::ASC) Order by the session_var column
 * @method     ChildMenuConfigQuery orderBySessionVarInText($order = Criteria::ASC) Order by the session_var_in_text column
 * @method     ChildMenuConfigQuery orderBySessionVarInURI($order = Criteria::ASC) Order by the session_var_in_uri column
 * @method     ChildMenuConfigQuery orderByURLParmName($order = Criteria::ASC) Order by the url_parm_name column
 * @method     ChildMenuConfigQuery orderByActive($order = Criteria::ASC) Order by the active column
 * @method     ChildMenuConfigQuery orderBySortOrder($order = Criteria::ASC) Order by the sortorder column
 * @method     ChildMenuConfigQuery orderByIcon($order = Criteria::ASC) Order by the icon column
 *
 * @method     ChildMenuConfigQuery groupById() Group by the mid column
 * @method     ChildMenuConfigQuery groupByName() Group by the name column
 * @method     ChildMenuConfigQuery groupByParent() Group by the parent column
 * @method     ChildMenuConfigQuery groupByMenu() Group by the ismenu column
 * @method     ChildMenuConfigQuery groupByContentEnglish() Group by the content_english column
 * @method     ChildMenuConfigQuery groupByContent() Group by the content column
 * @method     ChildMenuConfigQuery groupByURI() Group by the uri column
 * @method     ChildMenuConfigQuery groupByStatus() Group by the statustext column
 * @method     ChildMenuConfigQuery groupBySecurityGroup() Group by the security_grp column
 * @method     ChildMenuConfigQuery groupBySessionVar() Group by the session_var column
 * @method     ChildMenuConfigQuery groupBySessionVarInText() Group by the session_var_in_text column
 * @method     ChildMenuConfigQuery groupBySessionVarInURI() Group by the session_var_in_uri column
 * @method     ChildMenuConfigQuery groupByURLParmName() Group by the url_parm_name column
 * @method     ChildMenuConfigQuery groupByActive() Group by the active column
 * @method     ChildMenuConfigQuery groupBySortOrder() Group by the sortorder column
 * @method     ChildMenuConfigQuery groupByIcon() Group by the icon column
 *
 * @method     ChildMenuConfigQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildMenuConfigQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildMenuConfigQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildMenuConfigQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildMenuConfigQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildMenuConfigQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildMenuConfig findOne(ConnectionInterface $con = null) Return the first ChildMenuConfig matching the query
 * @method     ChildMenuConfig findOneOrCreate(ConnectionInterface $con = null) Return the first ChildMenuConfig matching the query, or a new ChildMenuConfig object populated from the query conditions when no match is found
 *
 * @method     ChildMenuConfig findOneById(int $mid) Return the first ChildMenuConfig filtered by the mid column
 * @method     ChildMenuConfig findOneByName(string $name) Return the first ChildMenuConfig filtered by the name column
 * @method     ChildMenuConfig findOneByParent(string $parent) Return the first ChildMenuConfig filtered by the parent column
 * @method     ChildMenuConfig findOneByMenu(boolean $ismenu) Return the first ChildMenuConfig filtered by the ismenu column
 * @method     ChildMenuConfig findOneByContentEnglish(string $content_english) Return the first ChildMenuConfig filtered by the content_english column
 * @method     ChildMenuConfig findOneByContent(string $content) Return the first ChildMenuConfig filtered by the content column
 * @method     ChildMenuConfig findOneByURI(string $uri) Return the first ChildMenuConfig filtered by the uri column
 * @method     ChildMenuConfig findOneByStatus(string $statustext) Return the first ChildMenuConfig filtered by the statustext column
 * @method     ChildMenuConfig findOneBySecurityGroup(string $security_grp) Return the first ChildMenuConfig filtered by the security_grp column
 * @method     ChildMenuConfig findOneBySessionVar(string $session_var) Return the first ChildMenuConfig filtered by the session_var column
 * @method     ChildMenuConfig findOneBySessionVarInText(boolean $session_var_in_text) Return the first ChildMenuConfig filtered by the session_var_in_text column
 * @method     ChildMenuConfig findOneBySessionVarInURI(boolean $session_var_in_uri) Return the first ChildMenuConfig filtered by the session_var_in_uri column
 * @method     ChildMenuConfig findOneByURLParmName(string $url_parm_name) Return the first ChildMenuConfig filtered by the url_parm_name column
 * @method     ChildMenuConfig findOneByActive(boolean $active) Return the first ChildMenuConfig filtered by the active column
 * @method     ChildMenuConfig findOneBySortOrder(int $sortorder) Return the first ChildMenuConfig filtered by the sortorder column
 * @method     ChildMenuConfig findOneByIcon(string $icon) Return the first ChildMenuConfig filtered by the icon column *

 * @method     ChildMenuConfig requirePk($key, ConnectionInterface $con = null) Return the ChildMenuConfig by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildMenuConfig requireOne(ConnectionInterface $con = null) Return the first ChildMenuConfig matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildMenuConfig requireOneById(int $mid) Return the first ChildMenuConfig filtered by the mid column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildMenuConfig requireOneByName(string $name) Return the first ChildMenuConfig filtered by the name column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildMenuConfig requireOneByParent(string $parent) Return the first ChildMenuConfig filtered by the parent column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildMenuConfig requireOneByMenu(boolean $ismenu) Return the first ChildMenuConfig filtered by the ismenu column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildMenuConfig requireOneByContentEnglish(string $content_english) Return the first ChildMenuConfig filtered by the content_english column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildMenuConfig requireOneByContent(string $content) Return the first ChildMenuConfig filtered by the content column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildMenuConfig requireOneByURI(string $uri) Return the first ChildMenuConfig filtered by the uri column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildMenuConfig requireOneByStatus(string $statustext) Return the first ChildMenuConfig filtered by the statustext column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildMenuConfig requireOneBySecurityGroup(string $security_grp) Return the first ChildMenuConfig filtered by the security_grp column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildMenuConfig requireOneBySessionVar(string $session_var) Return the first ChildMenuConfig filtered by the session_var column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildMenuConfig requireOneBySessionVarInText(boolean $session_var_in_text) Return the first ChildMenuConfig filtered by the session_var_in_text column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildMenuConfig requireOneBySessionVarInURI(boolean $session_var_in_uri) Return the first ChildMenuConfig filtered by the session_var_in_uri column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildMenuConfig requireOneByURLParmName(string $url_parm_name) Return the first ChildMenuConfig filtered by the url_parm_name column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildMenuConfig requireOneByActive(boolean $active) Return the first ChildMenuConfig filtered by the active column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildMenuConfig requireOneBySortOrder(int $sortorder) Return the first ChildMenuConfig filtered by the sortorder column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildMenuConfig requireOneByIcon(string $icon) Return the first ChildMenuConfig filtered by the icon column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildMenuConfig[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildMenuConfig objects based on current ModelCriteria
 * @method     ChildMenuConfig[]|ObjectCollection findById(int $mid) Return ChildMenuConfig objects filtered by the mid column
 * @method     ChildMenuConfig[]|ObjectCollection findByName(string $name) Return ChildMenuConfig objects filtered by the name column
 * @method     ChildMenuConfig[]|ObjectCollection findByParent(string $parent) Return ChildMenuConfig objects filtered by the parent column
 * @method     ChildMenuConfig[]|ObjectCollection findByMenu(boolean $ismenu) Return ChildMenuConfig objects filtered by the ismenu column
 * @method     ChildMenuConfig[]|ObjectCollection findByContentEnglish(string $content_english) Return ChildMenuConfig objects filtered by the content_english column
 * @method     ChildMenuConfig[]|ObjectCollection findByContent(string $content) Return ChildMenuConfig objects filtered by the content column
 * @method     ChildMenuConfig[]|ObjectCollection findByURI(string $uri) Return ChildMenuConfig objects filtered by the uri column
 * @method     ChildMenuConfig[]|ObjectCollection findByStatus(string $statustext) Return ChildMenuConfig objects filtered by the statustext column
 * @method     ChildMenuConfig[]|ObjectCollection findBySecurityGroup(string $security_grp) Return ChildMenuConfig objects filtered by the security_grp column
 * @method     ChildMenuConfig[]|ObjectCollection findBySessionVar(string $session_var) Return ChildMenuConfig objects filtered by the session_var column
 * @method     ChildMenuConfig[]|ObjectCollection findBySessionVarInText(boolean $session_var_in_text) Return ChildMenuConfig objects filtered by the session_var_in_text column
 * @method     ChildMenuConfig[]|ObjectCollection findBySessionVarInURI(boolean $session_var_in_uri) Return ChildMenuConfig objects filtered by the session_var_in_uri column
 * @method     ChildMenuConfig[]|ObjectCollection findByURLParmName(string $url_parm_name) Return ChildMenuConfig objects filtered by the url_parm_name column
 * @method     ChildMenuConfig[]|ObjectCollection findByActive(boolean $active) Return ChildMenuConfig objects filtered by the active column
 * @method     ChildMenuConfig[]|ObjectCollection findBySortOrder(int $sortorder) Return ChildMenuConfig objects filtered by the sortorder column
 * @method     ChildMenuConfig[]|ObjectCollection findByIcon(string $icon) Return ChildMenuConfig objects filtered by the icon column
 * @method     ChildMenuConfig[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class MenuConfigQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\MenuConfigQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\MenuConfig', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildMenuConfigQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildMenuConfigQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildMenuConfigQuery) {
            return $criteria;
        }
        $query = new ChildMenuConfigQuery();
        if (null !== $modelAlias) {
            $query->setModelAlias($modelAlias);
        }
        if ($criteria instanceof Criteria) {
            $query->mergeWith($criteria);
        }

        return $query;
    }

    /**
     * Find object by primary key.
     * Propel uses the instance pool to skip the database if the object exists.
     * Go fast if the query is untouched.
     *
     * <code>
     * $obj  = $c->findPk(12, $con);
     * </code>
     *
     * @param mixed $key Primary key to use for the query
     * @param ConnectionInterface $con an optional connection object
     *
     * @return ChildMenuConfig|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(MenuConfigTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (
            $this->formatter || $this->modelAlias || $this->with || $this->select
            || $this->selectColumns || $this->asColumns || $this->selectModifiers
            || $this->map || $this->having || $this->joins
        ) {
            return $this->findPkComplex($key, $con);
        }

        if ((null !== ($obj = MenuConfigTableMap::getInstanceFromPool(null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key)))) {
            // the object is already in the instance pool
            return $obj;
        }

        return $this->findPkSimple($key, $con);
    }

    /**
     * Find object by primary key using raw SQL to go fast.
     * Bypass doSelect() and the object formatter by using generated code.
     *
     * @param     mixed $key Primary key to use for the query
     * @param     ConnectionInterface $con A connection object
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return ChildMenuConfig A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT mid, name, parent, ismenu, content_english, content, uri, statustext, security_grp, session_var, session_var_in_text, session_var_in_uri, url_parm_name, active, sortorder, icon FROM menuconfig_mcf WHERE mid = :p0';
        try {
            $stmt = $con->prepare($sql);
            $stmt->bindValue(':p0', $key, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', $sql), 0, $e);
        }
        $obj = null;
        if ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
            /** @var ChildMenuConfig $obj */
            $obj = new ChildMenuConfig();
            $obj->hydrate($row);
            MenuConfigTableMap::addInstanceToPool($obj, null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key);
        }
        $stmt->closeCursor();

        return $obj;
    }

    /**
     * Find object by primary key.
     *
     * @param     mixed $key Primary key to use for the query
     * @param     ConnectionInterface $con A connection object
     *
     * @return ChildMenuConfig|array|mixed the result, formatted by the current formatter
     */
    protected function findPkComplex($key, ConnectionInterface $con)
    {
        // As the query uses a PK condition, no limit(1) is necessary.
        $criteria = $this->isKeepQuery() ? clone $this : $this;
        $dataFetcher = $criteria
            ->filterByPrimaryKey($key)
            ->doSelect($con);

        return $criteria->getFormatter()->init($criteria)->formatOne($dataFetcher);
    }

    /**
     * Find objects by primary key
     * <code>
     * $objs = $c->findPks(array(12, 56, 832), $con);
     * </code>
     * @param     array $keys Primary keys to use for the query
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return ObjectCollection|array|mixed the list of results, formatted by the current formatter
     */
    public function findPks($keys, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getReadConnection($this->getDbName());
        }
        $this->basePreSelect($con);
        $criteria = $this->isKeepQuery() ? clone $this : $this;
        $dataFetcher = $criteria
            ->filterByPrimaryKeys($keys)
            ->doSelect($con);

        return $criteria->getFormatter()->init($criteria)->format($dataFetcher);
    }

    /**
     * Filter the query by primary key
     *
     * @param     mixed $key Primary key to use for the query
     *
     * @return $this|ChildMenuConfigQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(MenuConfigTableMap::COL_MID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildMenuConfigQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(MenuConfigTableMap::COL_MID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the mid column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE mid = 1234
     * $query->filterById(array(12, 34)); // WHERE mid IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE mid > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildMenuConfigQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(MenuConfigTableMap::COL_MID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(MenuConfigTableMap::COL_MID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MenuConfigTableMap::COL_MID, $id, $comparison);
    }

    /**
     * Filter the query on the name column
     *
     * Example usage:
     * <code>
     * $query->filterByName('fooValue');   // WHERE name = 'fooValue'
     * $query->filterByName('%fooValue%', Criteria::LIKE); // WHERE name LIKE '%fooValue%'
     * </code>
     *
     * @param     string $name The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildMenuConfigQuery The current query, for fluid interface
     */
    public function filterByName($name = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($name)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MenuConfigTableMap::COL_NAME, $name, $comparison);
    }

    /**
     * Filter the query on the parent column
     *
     * Example usage:
     * <code>
     * $query->filterByParent('fooValue');   // WHERE parent = 'fooValue'
     * $query->filterByParent('%fooValue%', Criteria::LIKE); // WHERE parent LIKE '%fooValue%'
     * </code>
     *
     * @param     string $parent The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildMenuConfigQuery The current query, for fluid interface
     */
    public function filterByParent($parent = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($parent)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MenuConfigTableMap::COL_PARENT, $parent, $comparison);
    }

    /**
     * Filter the query on the ismenu column
     *
     * Example usage:
     * <code>
     * $query->filterByMenu(true); // WHERE ismenu = true
     * $query->filterByMenu('yes'); // WHERE ismenu = true
     * </code>
     *
     * @param     boolean|string $menu The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildMenuConfigQuery The current query, for fluid interface
     */
    public function filterByMenu($menu = null, $comparison = null)
    {
        if (is_string($menu)) {
            $menu = in_array(strtolower($menu), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(MenuConfigTableMap::COL_ISMENU, $menu, $comparison);
    }

    /**
     * Filter the query on the content_english column
     *
     * Example usage:
     * <code>
     * $query->filterByContentEnglish('fooValue');   // WHERE content_english = 'fooValue'
     * $query->filterByContentEnglish('%fooValue%', Criteria::LIKE); // WHERE content_english LIKE '%fooValue%'
     * </code>
     *
     * @param     string $contentEnglish The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildMenuConfigQuery The current query, for fluid interface
     */
    public function filterByContentEnglish($contentEnglish = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($contentEnglish)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MenuConfigTableMap::COL_CONTENT_ENGLISH, $contentEnglish, $comparison);
    }

    /**
     * Filter the query on the content column
     *
     * Example usage:
     * <code>
     * $query->filterByContent('fooValue');   // WHERE content = 'fooValue'
     * $query->filterByContent('%fooValue%', Criteria::LIKE); // WHERE content LIKE '%fooValue%'
     * </code>
     *
     * @param     string $content The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildMenuConfigQuery The current query, for fluid interface
     */
    public function filterByContent($content = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($content)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MenuConfigTableMap::COL_CONTENT, $content, $comparison);
    }

    /**
     * Filter the query on the uri column
     *
     * Example usage:
     * <code>
     * $query->filterByURI('fooValue');   // WHERE uri = 'fooValue'
     * $query->filterByURI('%fooValue%', Criteria::LIKE); // WHERE uri LIKE '%fooValue%'
     * </code>
     *
     * @param     string $uRI The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildMenuConfigQuery The current query, for fluid interface
     */
    public function filterByURI($uRI = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($uRI)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MenuConfigTableMap::COL_URI, $uRI, $comparison);
    }

    /**
     * Filter the query on the statustext column
     *
     * Example usage:
     * <code>
     * $query->filterByStatus('fooValue');   // WHERE statustext = 'fooValue'
     * $query->filterByStatus('%fooValue%', Criteria::LIKE); // WHERE statustext LIKE '%fooValue%'
     * </code>
     *
     * @param     string $status The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildMenuConfigQuery The current query, for fluid interface
     */
    public function filterByStatus($status = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($status)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MenuConfigTableMap::COL_STATUSTEXT, $status, $comparison);
    }

    /**
     * Filter the query on the security_grp column
     *
     * Example usage:
     * <code>
     * $query->filterBySecurityGroup('fooValue');   // WHERE security_grp = 'fooValue'
     * $query->filterBySecurityGroup('%fooValue%', Criteria::LIKE); // WHERE security_grp LIKE '%fooValue%'
     * </code>
     *
     * @param     string $securityGroup The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildMenuConfigQuery The current query, for fluid interface
     */
    public function filterBySecurityGroup($securityGroup = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($securityGroup)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MenuConfigTableMap::COL_SECURITY_GRP, $securityGroup, $comparison);
    }

    /**
     * Filter the query on the session_var column
     *
     * Example usage:
     * <code>
     * $query->filterBySessionVar('fooValue');   // WHERE session_var = 'fooValue'
     * $query->filterBySessionVar('%fooValue%', Criteria::LIKE); // WHERE session_var LIKE '%fooValue%'
     * </code>
     *
     * @param     string $sessionVar The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildMenuConfigQuery The current query, for fluid interface
     */
    public function filterBySessionVar($sessionVar = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($sessionVar)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MenuConfigTableMap::COL_SESSION_VAR, $sessionVar, $comparison);
    }

    /**
     * Filter the query on the session_var_in_text column
     *
     * Example usage:
     * <code>
     * $query->filterBySessionVarInText(true); // WHERE session_var_in_text = true
     * $query->filterBySessionVarInText('yes'); // WHERE session_var_in_text = true
     * </code>
     *
     * @param     boolean|string $sessionVarInText The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildMenuConfigQuery The current query, for fluid interface
     */
    public function filterBySessionVarInText($sessionVarInText = null, $comparison = null)
    {
        if (is_string($sessionVarInText)) {
            $sessionVarInText = in_array(strtolower($sessionVarInText), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(MenuConfigTableMap::COL_SESSION_VAR_IN_TEXT, $sessionVarInText, $comparison);
    }

    /**
     * Filter the query on the session_var_in_uri column
     *
     * Example usage:
     * <code>
     * $query->filterBySessionVarInURI(true); // WHERE session_var_in_uri = true
     * $query->filterBySessionVarInURI('yes'); // WHERE session_var_in_uri = true
     * </code>
     *
     * @param     boolean|string $sessionVarInURI The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildMenuConfigQuery The current query, for fluid interface
     */
    public function filterBySessionVarInURI($sessionVarInURI = null, $comparison = null)
    {
        if (is_string($sessionVarInURI)) {
            $sessionVarInURI = in_array(strtolower($sessionVarInURI), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(MenuConfigTableMap::COL_SESSION_VAR_IN_URI, $sessionVarInURI, $comparison);
    }

    /**
     * Filter the query on the url_parm_name column
     *
     * Example usage:
     * <code>
     * $query->filterByURLParmName('fooValue');   // WHERE url_parm_name = 'fooValue'
     * $query->filterByURLParmName('%fooValue%', Criteria::LIKE); // WHERE url_parm_name LIKE '%fooValue%'
     * </code>
     *
     * @param     string $uRLParmName The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildMenuConfigQuery The current query, for fluid interface
     */
    public function filterByURLParmName($uRLParmName = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($uRLParmName)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MenuConfigTableMap::COL_URL_PARM_NAME, $uRLParmName, $comparison);
    }

    /**
     * Filter the query on the active column
     *
     * Example usage:
     * <code>
     * $query->filterByActive(true); // WHERE active = true
     * $query->filterByActive('yes'); // WHERE active = true
     * </code>
     *
     * @param     boolean|string $active The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildMenuConfigQuery The current query, for fluid interface
     */
    public function filterByActive($active = null, $comparison = null)
    {
        if (is_string($active)) {
            $active = in_array(strtolower($active), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(MenuConfigTableMap::COL_ACTIVE, $active, $comparison);
    }

    /**
     * Filter the query on the sortorder column
     *
     * Example usage:
     * <code>
     * $query->filterBySortOrder(1234); // WHERE sortorder = 1234
     * $query->filterBySortOrder(array(12, 34)); // WHERE sortorder IN (12, 34)
     * $query->filterBySortOrder(array('min' => 12)); // WHERE sortorder > 12
     * </code>
     *
     * @param     mixed $sortOrder The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildMenuConfigQuery The current query, for fluid interface
     */
    public function filterBySortOrder($sortOrder = null, $comparison = null)
    {
        if (is_array($sortOrder)) {
            $useMinMax = false;
            if (isset($sortOrder['min'])) {
                $this->addUsingAlias(MenuConfigTableMap::COL_SORTORDER, $sortOrder['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($sortOrder['max'])) {
                $this->addUsingAlias(MenuConfigTableMap::COL_SORTORDER, $sortOrder['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MenuConfigTableMap::COL_SORTORDER, $sortOrder, $comparison);
    }

    /**
     * Filter the query on the icon column
     *
     * Example usage:
     * <code>
     * $query->filterByIcon('fooValue');   // WHERE icon = 'fooValue'
     * $query->filterByIcon('%fooValue%', Criteria::LIKE); // WHERE icon LIKE '%fooValue%'
     * </code>
     *
     * @param     string $icon The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildMenuConfigQuery The current query, for fluid interface
     */
    public function filterByIcon($icon = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($icon)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MenuConfigTableMap::COL_ICON, $icon, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildMenuConfig $menuConfig Object to remove from the list of results
     *
     * @return $this|ChildMenuConfigQuery The current query, for fluid interface
     */
    public function prune($menuConfig = null)
    {
        if ($menuConfig) {
            $this->addUsingAlias(MenuConfigTableMap::COL_MID, $menuConfig->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the menuconfig_mcf table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(MenuConfigTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            MenuConfigTableMap::clearInstancePool();
            MenuConfigTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

    /**
     * Performs a DELETE on the database based on the current ModelCriteria
     *
     * @param ConnectionInterface $con the connection to use
     * @return int             The number of affected rows (if supported by underlying database driver).  This includes CASCADE-related rows
     *                         if supported by native driver or if emulated using Propel.
     * @throws PropelException Any exceptions caught during processing will be
     *                         rethrown wrapped into a PropelException.
     */
    public function delete(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(MenuConfigTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(MenuConfigTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            MenuConfigTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            MenuConfigTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // MenuConfigQuery
