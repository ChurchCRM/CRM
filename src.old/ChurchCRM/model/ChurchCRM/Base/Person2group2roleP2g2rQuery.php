<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\Person2group2roleP2g2r as ChildPerson2group2roleP2g2r;
use ChurchCRM\Person2group2roleP2g2rQuery as ChildPerson2group2roleP2g2rQuery;
use ChurchCRM\Map\Person2group2roleP2g2rTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\ModelJoin;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'person2group2role_p2g2r' table.
 *
 *
 *
 * @method     ChildPerson2group2roleP2g2rQuery orderByPersonId($order = Criteria::ASC) Order by the p2g2r_per_ID column
 * @method     ChildPerson2group2roleP2g2rQuery orderByGroupId($order = Criteria::ASC) Order by the p2g2r_grp_ID column
 * @method     ChildPerson2group2roleP2g2rQuery orderByRoleId($order = Criteria::ASC) Order by the p2g2r_rle_ID column
 *
 * @method     ChildPerson2group2roleP2g2rQuery groupByPersonId() Group by the p2g2r_per_ID column
 * @method     ChildPerson2group2roleP2g2rQuery groupByGroupId() Group by the p2g2r_grp_ID column
 * @method     ChildPerson2group2roleP2g2rQuery groupByRoleId() Group by the p2g2r_rle_ID column
 *
 * @method     ChildPerson2group2roleP2g2rQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildPerson2group2roleP2g2rQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildPerson2group2roleP2g2rQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildPerson2group2roleP2g2rQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildPerson2group2roleP2g2rQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildPerson2group2roleP2g2rQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildPerson2group2roleP2g2rQuery leftJoinPerson($relationAlias = null) Adds a LEFT JOIN clause to the query using the Person relation
 * @method     ChildPerson2group2roleP2g2rQuery rightJoinPerson($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Person relation
 * @method     ChildPerson2group2roleP2g2rQuery innerJoinPerson($relationAlias = null) Adds a INNER JOIN clause to the query using the Person relation
 *
 * @method     ChildPerson2group2roleP2g2rQuery joinWithPerson($joinType = Criteria::INNER_JOIN) Adds a join clause and with to the query using the Person relation
 *
 * @method     ChildPerson2group2roleP2g2rQuery leftJoinWithPerson() Adds a LEFT JOIN clause and with to the query using the Person relation
 * @method     ChildPerson2group2roleP2g2rQuery rightJoinWithPerson() Adds a RIGHT JOIN clause and with to the query using the Person relation
 * @method     ChildPerson2group2roleP2g2rQuery innerJoinWithPerson() Adds a INNER JOIN clause and with to the query using the Person relation
 *
 * @method     ChildPerson2group2roleP2g2rQuery leftJoinGroup($relationAlias = null) Adds a LEFT JOIN clause to the query using the Group relation
 * @method     ChildPerson2group2roleP2g2rQuery rightJoinGroup($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Group relation
 * @method     ChildPerson2group2roleP2g2rQuery innerJoinGroup($relationAlias = null) Adds a INNER JOIN clause to the query using the Group relation
 *
 * @method     ChildPerson2group2roleP2g2rQuery joinWithGroup($joinType = Criteria::INNER_JOIN) Adds a join clause and with to the query using the Group relation
 *
 * @method     ChildPerson2group2roleP2g2rQuery leftJoinWithGroup() Adds a LEFT JOIN clause and with to the query using the Group relation
 * @method     ChildPerson2group2roleP2g2rQuery rightJoinWithGroup() Adds a RIGHT JOIN clause and with to the query using the Group relation
 * @method     ChildPerson2group2roleP2g2rQuery innerJoinWithGroup() Adds a INNER JOIN clause and with to the query using the Group relation
 *
 * @method     \ChurchCRM\PersonQuery|\ChurchCRM\GroupQuery endUse() Finalizes a secondary criteria and merges it with its primary Criteria
 *
 * @method     ChildPerson2group2roleP2g2r findOne(ConnectionInterface $con = null) Return the first ChildPerson2group2roleP2g2r matching the query
 * @method     ChildPerson2group2roleP2g2r findOneOrCreate(ConnectionInterface $con = null) Return the first ChildPerson2group2roleP2g2r matching the query, or a new ChildPerson2group2roleP2g2r object populated from the query conditions when no match is found
 *
 * @method     ChildPerson2group2roleP2g2r findOneByPersonId(int $p2g2r_per_ID) Return the first ChildPerson2group2roleP2g2r filtered by the p2g2r_per_ID column
 * @method     ChildPerson2group2roleP2g2r findOneByGroupId(int $p2g2r_grp_ID) Return the first ChildPerson2group2roleP2g2r filtered by the p2g2r_grp_ID column
 * @method     ChildPerson2group2roleP2g2r findOneByRoleId(int $p2g2r_rle_ID) Return the first ChildPerson2group2roleP2g2r filtered by the p2g2r_rle_ID column *

 * @method     ChildPerson2group2roleP2g2r requirePk($key, ConnectionInterface $con = null) Return the ChildPerson2group2roleP2g2r by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson2group2roleP2g2r requireOne(ConnectionInterface $con = null) Return the first ChildPerson2group2roleP2g2r matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildPerson2group2roleP2g2r requireOneByPersonId(int $p2g2r_per_ID) Return the first ChildPerson2group2roleP2g2r filtered by the p2g2r_per_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson2group2roleP2g2r requireOneByGroupId(int $p2g2r_grp_ID) Return the first ChildPerson2group2roleP2g2r filtered by the p2g2r_grp_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson2group2roleP2g2r requireOneByRoleId(int $p2g2r_rle_ID) Return the first ChildPerson2group2roleP2g2r filtered by the p2g2r_rle_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildPerson2group2roleP2g2r[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildPerson2group2roleP2g2r objects based on current ModelCriteria
 * @method     ChildPerson2group2roleP2g2r[]|ObjectCollection findByPersonId(int $p2g2r_per_ID) Return ChildPerson2group2roleP2g2r objects filtered by the p2g2r_per_ID column
 * @method     ChildPerson2group2roleP2g2r[]|ObjectCollection findByGroupId(int $p2g2r_grp_ID) Return ChildPerson2group2roleP2g2r objects filtered by the p2g2r_grp_ID column
 * @method     ChildPerson2group2roleP2g2r[]|ObjectCollection findByRoleId(int $p2g2r_rle_ID) Return ChildPerson2group2roleP2g2r objects filtered by the p2g2r_rle_ID column
 * @method     ChildPerson2group2roleP2g2r[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class Person2group2roleP2g2rQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\Person2group2roleP2g2rQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\Person2group2roleP2g2r', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildPerson2group2roleP2g2rQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildPerson2group2roleP2g2rQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildPerson2group2roleP2g2rQuery) {
            return $criteria;
        }
        $query = new ChildPerson2group2roleP2g2rQuery();
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
     * @param array[$p2g2r_per_ID, $p2g2r_grp_ID] $key Primary key to use for the query
     * @param ConnectionInterface $con an optional connection object
     *
     * @return ChildPerson2group2roleP2g2r|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(Person2group2roleP2g2rTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (
            $this->formatter || $this->modelAlias || $this->with || $this->select
            || $this->selectColumns || $this->asColumns || $this->selectModifiers
            || $this->map || $this->having || $this->joins
        ) {
            return $this->findPkComplex($key, $con);
        }

        if ((null !== ($obj = Person2group2roleP2g2rTableMap::getInstanceFromPool(serialize([(null === $key[0] || is_scalar($key[0]) || is_callable([$key[0], '__toString']) ? (string) $key[0] : $key[0]), (null === $key[1] || is_scalar($key[1]) || is_callable([$key[1], '__toString']) ? (string) $key[1] : $key[1])]))))) {
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
     * @return ChildPerson2group2roleP2g2r A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT p2g2r_per_ID, p2g2r_grp_ID, p2g2r_rle_ID FROM person2group2role_p2g2r WHERE p2g2r_per_ID = :p0 AND p2g2r_grp_ID = :p1';
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
            /** @var ChildPerson2group2roleP2g2r $obj */
            $obj = new ChildPerson2group2roleP2g2r();
            $obj->hydrate($row);
            Person2group2roleP2g2rTableMap::addInstanceToPool($obj, serialize([(null === $key[0] || is_scalar($key[0]) || is_callable([$key[0], '__toString']) ? (string) $key[0] : $key[0]), (null === $key[1] || is_scalar($key[1]) || is_callable([$key[1], '__toString']) ? (string) $key[1] : $key[1])]));
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
     * @return ChildPerson2group2roleP2g2r|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildPerson2group2roleP2g2rQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {
        $this->addUsingAlias(Person2group2roleP2g2rTableMap::COL_P2G2R_PER_ID, $key[0], Criteria::EQUAL);
        $this->addUsingAlias(Person2group2roleP2g2rTableMap::COL_P2G2R_GRP_ID, $key[1], Criteria::EQUAL);

        return $this;
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildPerson2group2roleP2g2rQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {
        if (empty($keys)) {
            return $this->add(null, '1<>1', Criteria::CUSTOM);
        }
        foreach ($keys as $key) {
            $cton0 = $this->getNewCriterion(Person2group2roleP2g2rTableMap::COL_P2G2R_PER_ID, $key[0], Criteria::EQUAL);
            $cton1 = $this->getNewCriterion(Person2group2roleP2g2rTableMap::COL_P2G2R_GRP_ID, $key[1], Criteria::EQUAL);
            $cton0->addAnd($cton1);
            $this->addOr($cton0);
        }

        return $this;
    }

    /**
     * Filter the query on the p2g2r_per_ID column
     *
     * Example usage:
     * <code>
     * $query->filterByPersonId(1234); // WHERE p2g2r_per_ID = 1234
     * $query->filterByPersonId(array(12, 34)); // WHERE p2g2r_per_ID IN (12, 34)
     * $query->filterByPersonId(array('min' => 12)); // WHERE p2g2r_per_ID > 12
     * </code>
     *
     * @see       filterByPerson()
     *
     * @param     mixed $personId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPerson2group2roleP2g2rQuery The current query, for fluid interface
     */
    public function filterByPersonId($personId = null, $comparison = null)
    {
        if (is_array($personId)) {
            $useMinMax = false;
            if (isset($personId['min'])) {
                $this->addUsingAlias(Person2group2roleP2g2rTableMap::COL_P2G2R_PER_ID, $personId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($personId['max'])) {
                $this->addUsingAlias(Person2group2roleP2g2rTableMap::COL_P2G2R_PER_ID, $personId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(Person2group2roleP2g2rTableMap::COL_P2G2R_PER_ID, $personId, $comparison);
    }

    /**
     * Filter the query on the p2g2r_grp_ID column
     *
     * Example usage:
     * <code>
     * $query->filterByGroupId(1234); // WHERE p2g2r_grp_ID = 1234
     * $query->filterByGroupId(array(12, 34)); // WHERE p2g2r_grp_ID IN (12, 34)
     * $query->filterByGroupId(array('min' => 12)); // WHERE p2g2r_grp_ID > 12
     * </code>
     *
     * @see       filterByGroup()
     *
     * @param     mixed $groupId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPerson2group2roleP2g2rQuery The current query, for fluid interface
     */
    public function filterByGroupId($groupId = null, $comparison = null)
    {
        if (is_array($groupId)) {
            $useMinMax = false;
            if (isset($groupId['min'])) {
                $this->addUsingAlias(Person2group2roleP2g2rTableMap::COL_P2G2R_GRP_ID, $groupId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($groupId['max'])) {
                $this->addUsingAlias(Person2group2roleP2g2rTableMap::COL_P2G2R_GRP_ID, $groupId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(Person2group2roleP2g2rTableMap::COL_P2G2R_GRP_ID, $groupId, $comparison);
    }

    /**
     * Filter the query on the p2g2r_rle_ID column
     *
     * Example usage:
     * <code>
     * $query->filterByRoleId(1234); // WHERE p2g2r_rle_ID = 1234
     * $query->filterByRoleId(array(12, 34)); // WHERE p2g2r_rle_ID IN (12, 34)
     * $query->filterByRoleId(array('min' => 12)); // WHERE p2g2r_rle_ID > 12
     * </code>
     *
     * @param     mixed $roleId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPerson2group2roleP2g2rQuery The current query, for fluid interface
     */
    public function filterByRoleId($roleId = null, $comparison = null)
    {
        if (is_array($roleId)) {
            $useMinMax = false;
            if (isset($roleId['min'])) {
                $this->addUsingAlias(Person2group2roleP2g2rTableMap::COL_P2G2R_RLE_ID, $roleId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($roleId['max'])) {
                $this->addUsingAlias(Person2group2roleP2g2rTableMap::COL_P2G2R_RLE_ID, $roleId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(Person2group2roleP2g2rTableMap::COL_P2G2R_RLE_ID, $roleId, $comparison);
    }

    /**
     * Filter the query by a related \ChurchCRM\Person object
     *
     * @param \ChurchCRM\Person|ObjectCollection $person The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return ChildPerson2group2roleP2g2rQuery The current query, for fluid interface
     */
    public function filterByPerson($person, $comparison = null)
    {
        if ($person instanceof \ChurchCRM\Person) {
            return $this
                ->addUsingAlias(Person2group2roleP2g2rTableMap::COL_P2G2R_PER_ID, $person->getId(), $comparison);
        } elseif ($person instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(Person2group2roleP2g2rTableMap::COL_P2G2R_PER_ID, $person->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByPerson() only accepts arguments of type \ChurchCRM\Person or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Person relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return $this|ChildPerson2group2roleP2g2rQuery The current query, for fluid interface
     */
    public function joinPerson($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Person');

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
            $this->addJoinObject($join, 'Person');
        }

        return $this;
    }

    /**
     * Use the Person relation Person object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return \ChurchCRM\PersonQuery A secondary query class using the current class as primary query
     */
    public function usePersonQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinPerson($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Person', '\ChurchCRM\PersonQuery');
    }

    /**
     * Filter the query by a related \ChurchCRM\Group object
     *
     * @param \ChurchCRM\Group|ObjectCollection $group The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return ChildPerson2group2roleP2g2rQuery The current query, for fluid interface
     */
    public function filterByGroup($group, $comparison = null)
    {
        if ($group instanceof \ChurchCRM\Group) {
            return $this
                ->addUsingAlias(Person2group2roleP2g2rTableMap::COL_P2G2R_GRP_ID, $group->getId(), $comparison);
        } elseif ($group instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(Person2group2roleP2g2rTableMap::COL_P2G2R_GRP_ID, $group->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByGroup() only accepts arguments of type \ChurchCRM\Group or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Group relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return $this|ChildPerson2group2roleP2g2rQuery The current query, for fluid interface
     */
    public function joinGroup($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Group');

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
            $this->addJoinObject($join, 'Group');
        }

        return $this;
    }

    /**
     * Use the Group relation Group object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return \ChurchCRM\GroupQuery A secondary query class using the current class as primary query
     */
    public function useGroupQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinGroup($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Group', '\ChurchCRM\GroupQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildPerson2group2roleP2g2r $person2group2roleP2g2r Object to remove from the list of results
     *
     * @return $this|ChildPerson2group2roleP2g2rQuery The current query, for fluid interface
     */
    public function prune($person2group2roleP2g2r = null)
    {
        if ($person2group2roleP2g2r) {
            $this->addCond('pruneCond0', $this->getAliasedColName(Person2group2roleP2g2rTableMap::COL_P2G2R_PER_ID), $person2group2roleP2g2r->getPersonId(), Criteria::NOT_EQUAL);
            $this->addCond('pruneCond1', $this->getAliasedColName(Person2group2roleP2g2rTableMap::COL_P2G2R_GRP_ID), $person2group2roleP2g2r->getGroupId(), Criteria::NOT_EQUAL);
            $this->combine(array('pruneCond0', 'pruneCond1'), Criteria::LOGICAL_OR);
        }

        return $this;
    }

    /**
     * Deletes all rows from the person2group2role_p2g2r table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(Person2group2roleP2g2rTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            Person2group2roleP2g2rTableMap::clearInstancePool();
            Person2group2roleP2g2rTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(Person2group2roleP2g2rTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(Person2group2roleP2g2rTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            Person2group2roleP2g2rTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            Person2group2roleP2g2rTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // Person2group2roleP2g2rQuery
