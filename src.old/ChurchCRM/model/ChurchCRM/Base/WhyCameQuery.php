<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\WhyCame as ChildWhyCame;
use ChurchCRM\WhyCameQuery as ChildWhyCameQuery;
use ChurchCRM\Map\WhyCameTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\ModelJoin;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'whycame_why' table.
 *
 *
 *
 * @method     ChildWhyCameQuery orderById($order = Criteria::ASC) Order by the why_ID column
 * @method     ChildWhyCameQuery orderByPerId($order = Criteria::ASC) Order by the why_per_ID column
 * @method     ChildWhyCameQuery orderByJoin($order = Criteria::ASC) Order by the why_join column
 * @method     ChildWhyCameQuery orderByCome($order = Criteria::ASC) Order by the why_come column
 * @method     ChildWhyCameQuery orderBySuggest($order = Criteria::ASC) Order by the why_suggest column
 * @method     ChildWhyCameQuery orderByHearOfUs($order = Criteria::ASC) Order by the why_hearOfUs column
 *
 * @method     ChildWhyCameQuery groupById() Group by the why_ID column
 * @method     ChildWhyCameQuery groupByPerId() Group by the why_per_ID column
 * @method     ChildWhyCameQuery groupByJoin() Group by the why_join column
 * @method     ChildWhyCameQuery groupByCome() Group by the why_come column
 * @method     ChildWhyCameQuery groupBySuggest() Group by the why_suggest column
 * @method     ChildWhyCameQuery groupByHearOfUs() Group by the why_hearOfUs column
 *
 * @method     ChildWhyCameQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildWhyCameQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildWhyCameQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildWhyCameQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildWhyCameQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildWhyCameQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildWhyCameQuery leftJoinPerson($relationAlias = null) Adds a LEFT JOIN clause to the query using the Person relation
 * @method     ChildWhyCameQuery rightJoinPerson($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Person relation
 * @method     ChildWhyCameQuery innerJoinPerson($relationAlias = null) Adds a INNER JOIN clause to the query using the Person relation
 *
 * @method     ChildWhyCameQuery joinWithPerson($joinType = Criteria::INNER_JOIN) Adds a join clause and with to the query using the Person relation
 *
 * @method     ChildWhyCameQuery leftJoinWithPerson() Adds a LEFT JOIN clause and with to the query using the Person relation
 * @method     ChildWhyCameQuery rightJoinWithPerson() Adds a RIGHT JOIN clause and with to the query using the Person relation
 * @method     ChildWhyCameQuery innerJoinWithPerson() Adds a INNER JOIN clause and with to the query using the Person relation
 *
 * @method     \ChurchCRM\PersonQuery endUse() Finalizes a secondary criteria and merges it with its primary Criteria
 *
 * @method     ChildWhyCame findOne(ConnectionInterface $con = null) Return the first ChildWhyCame matching the query
 * @method     ChildWhyCame findOneOrCreate(ConnectionInterface $con = null) Return the first ChildWhyCame matching the query, or a new ChildWhyCame object populated from the query conditions when no match is found
 *
 * @method     ChildWhyCame findOneById(int $why_ID) Return the first ChildWhyCame filtered by the why_ID column
 * @method     ChildWhyCame findOneByPerId(int $why_per_ID) Return the first ChildWhyCame filtered by the why_per_ID column
 * @method     ChildWhyCame findOneByJoin(string $why_join) Return the first ChildWhyCame filtered by the why_join column
 * @method     ChildWhyCame findOneByCome(string $why_come) Return the first ChildWhyCame filtered by the why_come column
 * @method     ChildWhyCame findOneBySuggest(string $why_suggest) Return the first ChildWhyCame filtered by the why_suggest column
 * @method     ChildWhyCame findOneByHearOfUs(string $why_hearOfUs) Return the first ChildWhyCame filtered by the why_hearOfUs column *

 * @method     ChildWhyCame requirePk($key, ConnectionInterface $con = null) Return the ChildWhyCame by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildWhyCame requireOne(ConnectionInterface $con = null) Return the first ChildWhyCame matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildWhyCame requireOneById(int $why_ID) Return the first ChildWhyCame filtered by the why_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildWhyCame requireOneByPerId(int $why_per_ID) Return the first ChildWhyCame filtered by the why_per_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildWhyCame requireOneByJoin(string $why_join) Return the first ChildWhyCame filtered by the why_join column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildWhyCame requireOneByCome(string $why_come) Return the first ChildWhyCame filtered by the why_come column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildWhyCame requireOneBySuggest(string $why_suggest) Return the first ChildWhyCame filtered by the why_suggest column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildWhyCame requireOneByHearOfUs(string $why_hearOfUs) Return the first ChildWhyCame filtered by the why_hearOfUs column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildWhyCame[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildWhyCame objects based on current ModelCriteria
 * @method     ChildWhyCame[]|ObjectCollection findById(int $why_ID) Return ChildWhyCame objects filtered by the why_ID column
 * @method     ChildWhyCame[]|ObjectCollection findByPerId(int $why_per_ID) Return ChildWhyCame objects filtered by the why_per_ID column
 * @method     ChildWhyCame[]|ObjectCollection findByJoin(string $why_join) Return ChildWhyCame objects filtered by the why_join column
 * @method     ChildWhyCame[]|ObjectCollection findByCome(string $why_come) Return ChildWhyCame objects filtered by the why_come column
 * @method     ChildWhyCame[]|ObjectCollection findBySuggest(string $why_suggest) Return ChildWhyCame objects filtered by the why_suggest column
 * @method     ChildWhyCame[]|ObjectCollection findByHearOfUs(string $why_hearOfUs) Return ChildWhyCame objects filtered by the why_hearOfUs column
 * @method     ChildWhyCame[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class WhyCameQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\WhyCameQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\WhyCame', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildWhyCameQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildWhyCameQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildWhyCameQuery) {
            return $criteria;
        }
        $query = new ChildWhyCameQuery();
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
     * @return ChildWhyCame|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(WhyCameTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (
            $this->formatter || $this->modelAlias || $this->with || $this->select
            || $this->selectColumns || $this->asColumns || $this->selectModifiers
            || $this->map || $this->having || $this->joins
        ) {
            return $this->findPkComplex($key, $con);
        }

        if ((null !== ($obj = WhyCameTableMap::getInstanceFromPool(null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key)))) {
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
     * @return ChildWhyCame A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT why_ID, why_per_ID, why_join, why_come, why_suggest, why_hearOfUs FROM whycame_why WHERE why_ID = :p0';
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
            /** @var ChildWhyCame $obj */
            $obj = new ChildWhyCame();
            $obj->hydrate($row);
            WhyCameTableMap::addInstanceToPool($obj, null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key);
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
     * @return ChildWhyCame|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildWhyCameQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(WhyCameTableMap::COL_WHY_ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildWhyCameQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(WhyCameTableMap::COL_WHY_ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the why_ID column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE why_ID = 1234
     * $query->filterById(array(12, 34)); // WHERE why_ID IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE why_ID > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildWhyCameQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(WhyCameTableMap::COL_WHY_ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(WhyCameTableMap::COL_WHY_ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(WhyCameTableMap::COL_WHY_ID, $id, $comparison);
    }

    /**
     * Filter the query on the why_per_ID column
     *
     * Example usage:
     * <code>
     * $query->filterByPerId(1234); // WHERE why_per_ID = 1234
     * $query->filterByPerId(array(12, 34)); // WHERE why_per_ID IN (12, 34)
     * $query->filterByPerId(array('min' => 12)); // WHERE why_per_ID > 12
     * </code>
     *
     * @see       filterByPerson()
     *
     * @param     mixed $perId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildWhyCameQuery The current query, for fluid interface
     */
    public function filterByPerId($perId = null, $comparison = null)
    {
        if (is_array($perId)) {
            $useMinMax = false;
            if (isset($perId['min'])) {
                $this->addUsingAlias(WhyCameTableMap::COL_WHY_PER_ID, $perId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($perId['max'])) {
                $this->addUsingAlias(WhyCameTableMap::COL_WHY_PER_ID, $perId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(WhyCameTableMap::COL_WHY_PER_ID, $perId, $comparison);
    }

    /**
     * Filter the query on the why_join column
     *
     * Example usage:
     * <code>
     * $query->filterByJoin('fooValue');   // WHERE why_join = 'fooValue'
     * $query->filterByJoin('%fooValue%', Criteria::LIKE); // WHERE why_join LIKE '%fooValue%'
     * </code>
     *
     * @param     string $join The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildWhyCameQuery The current query, for fluid interface
     */
    public function filterByJoin($join = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($join)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(WhyCameTableMap::COL_WHY_JOIN, $join, $comparison);
    }

    /**
     * Filter the query on the why_come column
     *
     * Example usage:
     * <code>
     * $query->filterByCome('fooValue');   // WHERE why_come = 'fooValue'
     * $query->filterByCome('%fooValue%', Criteria::LIKE); // WHERE why_come LIKE '%fooValue%'
     * </code>
     *
     * @param     string $come The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildWhyCameQuery The current query, for fluid interface
     */
    public function filterByCome($come = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($come)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(WhyCameTableMap::COL_WHY_COME, $come, $comparison);
    }

    /**
     * Filter the query on the why_suggest column
     *
     * Example usage:
     * <code>
     * $query->filterBySuggest('fooValue');   // WHERE why_suggest = 'fooValue'
     * $query->filterBySuggest('%fooValue%', Criteria::LIKE); // WHERE why_suggest LIKE '%fooValue%'
     * </code>
     *
     * @param     string $suggest The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildWhyCameQuery The current query, for fluid interface
     */
    public function filterBySuggest($suggest = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($suggest)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(WhyCameTableMap::COL_WHY_SUGGEST, $suggest, $comparison);
    }

    /**
     * Filter the query on the why_hearOfUs column
     *
     * Example usage:
     * <code>
     * $query->filterByHearOfUs('fooValue');   // WHERE why_hearOfUs = 'fooValue'
     * $query->filterByHearOfUs('%fooValue%', Criteria::LIKE); // WHERE why_hearOfUs LIKE '%fooValue%'
     * </code>
     *
     * @param     string $hearOfUs The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildWhyCameQuery The current query, for fluid interface
     */
    public function filterByHearOfUs($hearOfUs = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($hearOfUs)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(WhyCameTableMap::COL_WHY_HEAROFUS, $hearOfUs, $comparison);
    }

    /**
     * Filter the query by a related \ChurchCRM\Person object
     *
     * @param \ChurchCRM\Person|ObjectCollection $person The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return ChildWhyCameQuery The current query, for fluid interface
     */
    public function filterByPerson($person, $comparison = null)
    {
        if ($person instanceof \ChurchCRM\Person) {
            return $this
                ->addUsingAlias(WhyCameTableMap::COL_WHY_PER_ID, $person->getId(), $comparison);
        } elseif ($person instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(WhyCameTableMap::COL_WHY_PER_ID, $person->toKeyValue('PrimaryKey', 'Id'), $comparison);
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
     * @return $this|ChildWhyCameQuery The current query, for fluid interface
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
     * Exclude object from result
     *
     * @param   ChildWhyCame $whyCame Object to remove from the list of results
     *
     * @return $this|ChildWhyCameQuery The current query, for fluid interface
     */
    public function prune($whyCame = null)
    {
        if ($whyCame) {
            $this->addUsingAlias(WhyCameTableMap::COL_WHY_ID, $whyCame->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the whycame_why table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(WhyCameTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            WhyCameTableMap::clearInstancePool();
            WhyCameTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(WhyCameTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(WhyCameTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            WhyCameTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            WhyCameTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // WhyCameQuery
