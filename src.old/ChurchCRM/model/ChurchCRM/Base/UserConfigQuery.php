<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\UserConfig as ChildUserConfig;
use ChurchCRM\UserConfigQuery as ChildUserConfigQuery;
use ChurchCRM\Map\UserConfigTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\ModelJoin;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'userconfig_ucfg' table.
 *
 *
 *
 * @method     ChildUserConfigQuery orderByPeronId($order = Criteria::ASC) Order by the ucfg_per_id column
 * @method     ChildUserConfigQuery orderById($order = Criteria::ASC) Order by the ucfg_id column
 * @method     ChildUserConfigQuery orderByName($order = Criteria::ASC) Order by the ucfg_name column
 * @method     ChildUserConfigQuery orderByValue($order = Criteria::ASC) Order by the ucfg_value column
 * @method     ChildUserConfigQuery orderByType($order = Criteria::ASC) Order by the ucfg_type column
 * @method     ChildUserConfigQuery orderByTooltip($order = Criteria::ASC) Order by the ucfg_tooltip column
 * @method     ChildUserConfigQuery orderByPermission($order = Criteria::ASC) Order by the ucfg_permission column
 * @method     ChildUserConfigQuery orderByCat($order = Criteria::ASC) Order by the ucfg_cat column
 *
 * @method     ChildUserConfigQuery groupByPeronId() Group by the ucfg_per_id column
 * @method     ChildUserConfigQuery groupById() Group by the ucfg_id column
 * @method     ChildUserConfigQuery groupByName() Group by the ucfg_name column
 * @method     ChildUserConfigQuery groupByValue() Group by the ucfg_value column
 * @method     ChildUserConfigQuery groupByType() Group by the ucfg_type column
 * @method     ChildUserConfigQuery groupByTooltip() Group by the ucfg_tooltip column
 * @method     ChildUserConfigQuery groupByPermission() Group by the ucfg_permission column
 * @method     ChildUserConfigQuery groupByCat() Group by the ucfg_cat column
 *
 * @method     ChildUserConfigQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildUserConfigQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildUserConfigQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildUserConfigQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildUserConfigQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildUserConfigQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildUserConfigQuery leftJoinUser($relationAlias = null) Adds a LEFT JOIN clause to the query using the User relation
 * @method     ChildUserConfigQuery rightJoinUser($relationAlias = null) Adds a RIGHT JOIN clause to the query using the User relation
 * @method     ChildUserConfigQuery innerJoinUser($relationAlias = null) Adds a INNER JOIN clause to the query using the User relation
 *
 * @method     ChildUserConfigQuery joinWithUser($joinType = Criteria::INNER_JOIN) Adds a join clause and with to the query using the User relation
 *
 * @method     ChildUserConfigQuery leftJoinWithUser() Adds a LEFT JOIN clause and with to the query using the User relation
 * @method     ChildUserConfigQuery rightJoinWithUser() Adds a RIGHT JOIN clause and with to the query using the User relation
 * @method     ChildUserConfigQuery innerJoinWithUser() Adds a INNER JOIN clause and with to the query using the User relation
 *
 * @method     \ChurchCRM\UserQuery endUse() Finalizes a secondary criteria and merges it with its primary Criteria
 *
 * @method     ChildUserConfig findOne(ConnectionInterface $con = null) Return the first ChildUserConfig matching the query
 * @method     ChildUserConfig findOneOrCreate(ConnectionInterface $con = null) Return the first ChildUserConfig matching the query, or a new ChildUserConfig object populated from the query conditions when no match is found
 *
 * @method     ChildUserConfig findOneByPeronId(int $ucfg_per_id) Return the first ChildUserConfig filtered by the ucfg_per_id column
 * @method     ChildUserConfig findOneById(int $ucfg_id) Return the first ChildUserConfig filtered by the ucfg_id column
 * @method     ChildUserConfig findOneByName(string $ucfg_name) Return the first ChildUserConfig filtered by the ucfg_name column
 * @method     ChildUserConfig findOneByValue(string $ucfg_value) Return the first ChildUserConfig filtered by the ucfg_value column
 * @method     ChildUserConfig findOneByType(string $ucfg_type) Return the first ChildUserConfig filtered by the ucfg_type column
 * @method     ChildUserConfig findOneByTooltip(string $ucfg_tooltip) Return the first ChildUserConfig filtered by the ucfg_tooltip column
 * @method     ChildUserConfig findOneByPermission(string $ucfg_permission) Return the first ChildUserConfig filtered by the ucfg_permission column
 * @method     ChildUserConfig findOneByCat(string $ucfg_cat) Return the first ChildUserConfig filtered by the ucfg_cat column *

 * @method     ChildUserConfig requirePk($key, ConnectionInterface $con = null) Return the ChildUserConfig by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUserConfig requireOne(ConnectionInterface $con = null) Return the first ChildUserConfig matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildUserConfig requireOneByPeronId(int $ucfg_per_id) Return the first ChildUserConfig filtered by the ucfg_per_id column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUserConfig requireOneById(int $ucfg_id) Return the first ChildUserConfig filtered by the ucfg_id column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUserConfig requireOneByName(string $ucfg_name) Return the first ChildUserConfig filtered by the ucfg_name column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUserConfig requireOneByValue(string $ucfg_value) Return the first ChildUserConfig filtered by the ucfg_value column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUserConfig requireOneByType(string $ucfg_type) Return the first ChildUserConfig filtered by the ucfg_type column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUserConfig requireOneByTooltip(string $ucfg_tooltip) Return the first ChildUserConfig filtered by the ucfg_tooltip column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUserConfig requireOneByPermission(string $ucfg_permission) Return the first ChildUserConfig filtered by the ucfg_permission column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildUserConfig requireOneByCat(string $ucfg_cat) Return the first ChildUserConfig filtered by the ucfg_cat column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildUserConfig[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildUserConfig objects based on current ModelCriteria
 * @method     ChildUserConfig[]|ObjectCollection findByPeronId(int $ucfg_per_id) Return ChildUserConfig objects filtered by the ucfg_per_id column
 * @method     ChildUserConfig[]|ObjectCollection findById(int $ucfg_id) Return ChildUserConfig objects filtered by the ucfg_id column
 * @method     ChildUserConfig[]|ObjectCollection findByName(string $ucfg_name) Return ChildUserConfig objects filtered by the ucfg_name column
 * @method     ChildUserConfig[]|ObjectCollection findByValue(string $ucfg_value) Return ChildUserConfig objects filtered by the ucfg_value column
 * @method     ChildUserConfig[]|ObjectCollection findByType(string $ucfg_type) Return ChildUserConfig objects filtered by the ucfg_type column
 * @method     ChildUserConfig[]|ObjectCollection findByTooltip(string $ucfg_tooltip) Return ChildUserConfig objects filtered by the ucfg_tooltip column
 * @method     ChildUserConfig[]|ObjectCollection findByPermission(string $ucfg_permission) Return ChildUserConfig objects filtered by the ucfg_permission column
 * @method     ChildUserConfig[]|ObjectCollection findByCat(string $ucfg_cat) Return ChildUserConfig objects filtered by the ucfg_cat column
 * @method     ChildUserConfig[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class UserConfigQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\UserConfigQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\UserConfig', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildUserConfigQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildUserConfigQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildUserConfigQuery) {
            return $criteria;
        }
        $query = new ChildUserConfigQuery();
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
     * $obj = $c->findPk(array(12, 34), $con);
     * </code>
     *
     * @param array[$ucfg_per_id, $ucfg_id] $key Primary key to use for the query
     * @param ConnectionInterface $con an optional connection object
     *
     * @return ChildUserConfig|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(UserConfigTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (
            $this->formatter || $this->modelAlias || $this->with || $this->select
            || $this->selectColumns || $this->asColumns || $this->selectModifiers
            || $this->map || $this->having || $this->joins
        ) {
            return $this->findPkComplex($key, $con);
        }

        if ((null !== ($obj = UserConfigTableMap::getInstanceFromPool(serialize([(null === $key[0] || is_scalar($key[0]) || is_callable([$key[0], '__toString']) ? (string) $key[0] : $key[0]), (null === $key[1] || is_scalar($key[1]) || is_callable([$key[1], '__toString']) ? (string) $key[1] : $key[1])]))))) {
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
     * @return ChildUserConfig A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT ucfg_per_id, ucfg_id, ucfg_name, ucfg_value, ucfg_type, ucfg_tooltip, ucfg_permission, ucfg_cat FROM userconfig_ucfg WHERE ucfg_per_id = :p0 AND ucfg_id = :p1';
        try {
            $stmt = $con->prepare($sql);
            $stmt->bindValue(':p0', $key[0], PDO::PARAM_INT);
            $stmt->bindValue(':p1', $key[1], PDO::PARAM_INT);
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', $sql), 0, $e);
        }
        $obj = null;
        if ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
            /** @var ChildUserConfig $obj */
            $obj = new ChildUserConfig();
            $obj->hydrate($row);
            UserConfigTableMap::addInstanceToPool($obj, serialize([(null === $key[0] || is_scalar($key[0]) || is_callable([$key[0], '__toString']) ? (string) $key[0] : $key[0]), (null === $key[1] || is_scalar($key[1]) || is_callable([$key[1], '__toString']) ? (string) $key[1] : $key[1])]));
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
     * @return ChildUserConfig|array|mixed the result, formatted by the current formatter
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
     * $objs = $c->findPks(array(array(12, 56), array(832, 123), array(123, 456)), $con);
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
     * @return $this|ChildUserConfigQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {
        $this->addUsingAlias(UserConfigTableMap::COL_UCFG_PER_ID, $key[0], Criteria::EQUAL);
        $this->addUsingAlias(UserConfigTableMap::COL_UCFG_ID, $key[1], Criteria::EQUAL);

        return $this;
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildUserConfigQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {
        if (empty($keys)) {
            return $this->add(null, '1<>1', Criteria::CUSTOM);
        }
        foreach ($keys as $key) {
            $cton0 = $this->getNewCriterion(UserConfigTableMap::COL_UCFG_PER_ID, $key[0], Criteria::EQUAL);
            $cton1 = $this->getNewCriterion(UserConfigTableMap::COL_UCFG_ID, $key[1], Criteria::EQUAL);
            $cton0->addAnd($cton1);
            $this->addOr($cton0);
        }

        return $this;
    }

    /**
     * Filter the query on the ucfg_per_id column
     *
     * Example usage:
     * <code>
     * $query->filterByPeronId(1234); // WHERE ucfg_per_id = 1234
     * $query->filterByPeronId(array(12, 34)); // WHERE ucfg_per_id IN (12, 34)
     * $query->filterByPeronId(array('min' => 12)); // WHERE ucfg_per_id > 12
     * </code>
     *
     * @see       filterByUser()
     *
     * @param     mixed $peronId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserConfigQuery The current query, for fluid interface
     */
    public function filterByPeronId($peronId = null, $comparison = null)
    {
        if (is_array($peronId)) {
            $useMinMax = false;
            if (isset($peronId['min'])) {
                $this->addUsingAlias(UserConfigTableMap::COL_UCFG_PER_ID, $peronId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($peronId['max'])) {
                $this->addUsingAlias(UserConfigTableMap::COL_UCFG_PER_ID, $peronId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserConfigTableMap::COL_UCFG_PER_ID, $peronId, $comparison);
    }

    /**
     * Filter the query on the ucfg_id column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE ucfg_id = 1234
     * $query->filterById(array(12, 34)); // WHERE ucfg_id IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE ucfg_id > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserConfigQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(UserConfigTableMap::COL_UCFG_ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(UserConfigTableMap::COL_UCFG_ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserConfigTableMap::COL_UCFG_ID, $id, $comparison);
    }

    /**
     * Filter the query on the ucfg_name column
     *
     * Example usage:
     * <code>
     * $query->filterByName('fooValue');   // WHERE ucfg_name = 'fooValue'
     * $query->filterByName('%fooValue%', Criteria::LIKE); // WHERE ucfg_name LIKE '%fooValue%'
     * </code>
     *
     * @param     string $name The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserConfigQuery The current query, for fluid interface
     */
    public function filterByName($name = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($name)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserConfigTableMap::COL_UCFG_NAME, $name, $comparison);
    }

    /**
     * Filter the query on the ucfg_value column
     *
     * Example usage:
     * <code>
     * $query->filterByValue('fooValue');   // WHERE ucfg_value = 'fooValue'
     * $query->filterByValue('%fooValue%', Criteria::LIKE); // WHERE ucfg_value LIKE '%fooValue%'
     * </code>
     *
     * @param     string $value The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserConfigQuery The current query, for fluid interface
     */
    public function filterByValue($value = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($value)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserConfigTableMap::COL_UCFG_VALUE, $value, $comparison);
    }

    /**
     * Filter the query on the ucfg_type column
     *
     * Example usage:
     * <code>
     * $query->filterByType('fooValue');   // WHERE ucfg_type = 'fooValue'
     * $query->filterByType('%fooValue%', Criteria::LIKE); // WHERE ucfg_type LIKE '%fooValue%'
     * </code>
     *
     * @param     string $type The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserConfigQuery The current query, for fluid interface
     */
    public function filterByType($type = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($type)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserConfigTableMap::COL_UCFG_TYPE, $type, $comparison);
    }

    /**
     * Filter the query on the ucfg_tooltip column
     *
     * Example usage:
     * <code>
     * $query->filterByTooltip('fooValue');   // WHERE ucfg_tooltip = 'fooValue'
     * $query->filterByTooltip('%fooValue%', Criteria::LIKE); // WHERE ucfg_tooltip LIKE '%fooValue%'
     * </code>
     *
     * @param     string $tooltip The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserConfigQuery The current query, for fluid interface
     */
    public function filterByTooltip($tooltip = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($tooltip)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserConfigTableMap::COL_UCFG_TOOLTIP, $tooltip, $comparison);
    }

    /**
     * Filter the query on the ucfg_permission column
     *
     * Example usage:
     * <code>
     * $query->filterByPermission('fooValue');   // WHERE ucfg_permission = 'fooValue'
     * $query->filterByPermission('%fooValue%', Criteria::LIKE); // WHERE ucfg_permission LIKE '%fooValue%'
     * </code>
     *
     * @param     string $permission The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserConfigQuery The current query, for fluid interface
     */
    public function filterByPermission($permission = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($permission)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserConfigTableMap::COL_UCFG_PERMISSION, $permission, $comparison);
    }

    /**
     * Filter the query on the ucfg_cat column
     *
     * Example usage:
     * <code>
     * $query->filterByCat('fooValue');   // WHERE ucfg_cat = 'fooValue'
     * $query->filterByCat('%fooValue%', Criteria::LIKE); // WHERE ucfg_cat LIKE '%fooValue%'
     * </code>
     *
     * @param     string $cat The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildUserConfigQuery The current query, for fluid interface
     */
    public function filterByCat($cat = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($cat)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(UserConfigTableMap::COL_UCFG_CAT, $cat, $comparison);
    }

    /**
     * Filter the query by a related \ChurchCRM\User object
     *
     * @param \ChurchCRM\User|ObjectCollection $user The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return ChildUserConfigQuery The current query, for fluid interface
     */
    public function filterByUser($user, $comparison = null)
    {
        if ($user instanceof \ChurchCRM\User) {
            return $this
                ->addUsingAlias(UserConfigTableMap::COL_UCFG_PER_ID, $user->getPersonId(), $comparison);
        } elseif ($user instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(UserConfigTableMap::COL_UCFG_PER_ID, $user->toKeyValue('PrimaryKey', 'PersonId'), $comparison);
        } else {
            throw new PropelException('filterByUser() only accepts arguments of type \ChurchCRM\User or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the User relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return $this|ChildUserConfigQuery The current query, for fluid interface
     */
    public function joinUser($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('User');

        // create a ModelJoin object for this join
        $join = new ModelJoin();
        $join->setJoinType($joinType);
        $join->setRelationMap($relationMap, $this->useAliasInSQL ? $this->getModelAlias() : null, $relationAlias);
        if ($previousJoin = $this->getPreviousJoin()) {
            $join->setPreviousJoin($previousJoin);
        }

        // add the ModelJoin to the current object
        if ($relationAlias) {
            $this->addAlias($relationAlias, $relationMap->getRightTable()->getName());
            $this->addJoinObject($join, $relationAlias);
        } else {
            $this->addJoinObject($join, 'User');
        }

        return $this;
    }

    /**
     * Use the User relation User object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return \ChurchCRM\UserQuery A secondary query class using the current class as primary query
     */
    public function useUserQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinUser($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'User', '\ChurchCRM\UserQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildUserConfig $userConfig Object to remove from the list of results
     *
     * @return $this|ChildUserConfigQuery The current query, for fluid interface
     */
    public function prune($userConfig = null)
    {
        if ($userConfig) {
            $this->addCond('pruneCond0', $this->getAliasedColName(UserConfigTableMap::COL_UCFG_PER_ID), $userConfig->getPeronId(), Criteria::NOT_EQUAL);
            $this->addCond('pruneCond1', $this->getAliasedColName(UserConfigTableMap::COL_UCFG_ID), $userConfig->getId(), Criteria::NOT_EQUAL);
            $this->combine(array('pruneCond0', 'pruneCond1'), Criteria::LOGICAL_OR);
        }

        return $this;
    }

    /**
     * Deletes all rows from the userconfig_ucfg table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(UserConfigTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            UserConfigTableMap::clearInstancePool();
            UserConfigTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(UserConfigTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(UserConfigTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            UserConfigTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            UserConfigTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // UserConfigQuery
