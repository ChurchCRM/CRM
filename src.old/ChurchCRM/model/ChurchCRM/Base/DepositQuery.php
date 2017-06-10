<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\Deposit as ChildDeposit;
use ChurchCRM\DepositQuery as ChildDepositQuery;
use ChurchCRM\Map\DepositTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\ModelJoin;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'deposit_dep' table.
 *
 *
 *
 * @method     ChildDepositQuery orderById($order = Criteria::ASC) Order by the dep_ID column
 * @method     ChildDepositQuery orderByDate($order = Criteria::ASC) Order by the dep_Date column
 * @method     ChildDepositQuery orderByComment($order = Criteria::ASC) Order by the dep_Comment column
 * @method     ChildDepositQuery orderByEnteredby($order = Criteria::ASC) Order by the dep_EnteredBy column
 * @method     ChildDepositQuery orderByClosed($order = Criteria::ASC) Order by the dep_Closed column
 * @method     ChildDepositQuery orderByType($order = Criteria::ASC) Order by the dep_Type column
 *
 * @method     ChildDepositQuery groupById() Group by the dep_ID column
 * @method     ChildDepositQuery groupByDate() Group by the dep_Date column
 * @method     ChildDepositQuery groupByComment() Group by the dep_Comment column
 * @method     ChildDepositQuery groupByEnteredby() Group by the dep_EnteredBy column
 * @method     ChildDepositQuery groupByClosed() Group by the dep_Closed column
 * @method     ChildDepositQuery groupByType() Group by the dep_Type column
 *
 * @method     ChildDepositQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildDepositQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildDepositQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildDepositQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildDepositQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildDepositQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildDepositQuery leftJoinPledge($relationAlias = null) Adds a LEFT JOIN clause to the query using the Pledge relation
 * @method     ChildDepositQuery rightJoinPledge($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Pledge relation
 * @method     ChildDepositQuery innerJoinPledge($relationAlias = null) Adds a INNER JOIN clause to the query using the Pledge relation
 *
 * @method     ChildDepositQuery joinWithPledge($joinType = Criteria::INNER_JOIN) Adds a join clause and with to the query using the Pledge relation
 *
 * @method     ChildDepositQuery leftJoinWithPledge() Adds a LEFT JOIN clause and with to the query using the Pledge relation
 * @method     ChildDepositQuery rightJoinWithPledge() Adds a RIGHT JOIN clause and with to the query using the Pledge relation
 * @method     ChildDepositQuery innerJoinWithPledge() Adds a INNER JOIN clause and with to the query using the Pledge relation
 *
 * @method     \ChurchCRM\PledgeQuery endUse() Finalizes a secondary criteria and merges it with its primary Criteria
 *
 * @method     ChildDeposit findOne(ConnectionInterface $con = null) Return the first ChildDeposit matching the query
 * @method     ChildDeposit findOneOrCreate(ConnectionInterface $con = null) Return the first ChildDeposit matching the query, or a new ChildDeposit object populated from the query conditions when no match is found
 *
 * @method     ChildDeposit findOneById(int $dep_ID) Return the first ChildDeposit filtered by the dep_ID column
 * @method     ChildDeposit findOneByDate(string $dep_Date) Return the first ChildDeposit filtered by the dep_Date column
 * @method     ChildDeposit findOneByComment(string $dep_Comment) Return the first ChildDeposit filtered by the dep_Comment column
 * @method     ChildDeposit findOneByEnteredby(int $dep_EnteredBy) Return the first ChildDeposit filtered by the dep_EnteredBy column
 * @method     ChildDeposit findOneByClosed(boolean $dep_Closed) Return the first ChildDeposit filtered by the dep_Closed column
 * @method     ChildDeposit findOneByType(string $dep_Type) Return the first ChildDeposit filtered by the dep_Type column *

 * @method     ChildDeposit requirePk($key, ConnectionInterface $con = null) Return the ChildDeposit by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildDeposit requireOne(ConnectionInterface $con = null) Return the first ChildDeposit matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildDeposit requireOneById(int $dep_ID) Return the first ChildDeposit filtered by the dep_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildDeposit requireOneByDate(string $dep_Date) Return the first ChildDeposit filtered by the dep_Date column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildDeposit requireOneByComment(string $dep_Comment) Return the first ChildDeposit filtered by the dep_Comment column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildDeposit requireOneByEnteredby(int $dep_EnteredBy) Return the first ChildDeposit filtered by the dep_EnteredBy column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildDeposit requireOneByClosed(boolean $dep_Closed) Return the first ChildDeposit filtered by the dep_Closed column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildDeposit requireOneByType(string $dep_Type) Return the first ChildDeposit filtered by the dep_Type column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildDeposit[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildDeposit objects based on current ModelCriteria
 * @method     ChildDeposit[]|ObjectCollection findById(int $dep_ID) Return ChildDeposit objects filtered by the dep_ID column
 * @method     ChildDeposit[]|ObjectCollection findByDate(string $dep_Date) Return ChildDeposit objects filtered by the dep_Date column
 * @method     ChildDeposit[]|ObjectCollection findByComment(string $dep_Comment) Return ChildDeposit objects filtered by the dep_Comment column
 * @method     ChildDeposit[]|ObjectCollection findByEnteredby(int $dep_EnteredBy) Return ChildDeposit objects filtered by the dep_EnteredBy column
 * @method     ChildDeposit[]|ObjectCollection findByClosed(boolean $dep_Closed) Return ChildDeposit objects filtered by the dep_Closed column
 * @method     ChildDeposit[]|ObjectCollection findByType(string $dep_Type) Return ChildDeposit objects filtered by the dep_Type column
 * @method     ChildDeposit[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class DepositQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\DepositQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\Deposit', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildDepositQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildDepositQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildDepositQuery) {
            return $criteria;
        }
        $query = new ChildDepositQuery();
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
     * @return ChildDeposit|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(DepositTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (
            $this->formatter || $this->modelAlias || $this->with || $this->select
            || $this->selectColumns || $this->asColumns || $this->selectModifiers
            || $this->map || $this->having || $this->joins
        ) {
            return $this->findPkComplex($key, $con);
        }

        if ((null !== ($obj = DepositTableMap::getInstanceFromPool(null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key)))) {
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
     * @return ChildDeposit A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT dep_ID, dep_Date, dep_Comment, dep_EnteredBy, dep_Closed, dep_Type FROM deposit_dep WHERE dep_ID = :p0';
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
            /** @var ChildDeposit $obj */
            $obj = new ChildDeposit();
            $obj->hydrate($row);
            DepositTableMap::addInstanceToPool($obj, null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key);
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
     * @return ChildDeposit|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildDepositQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(DepositTableMap::COL_DEP_ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildDepositQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(DepositTableMap::COL_DEP_ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the dep_ID column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE dep_ID = 1234
     * $query->filterById(array(12, 34)); // WHERE dep_ID IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE dep_ID > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildDepositQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(DepositTableMap::COL_DEP_ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(DepositTableMap::COL_DEP_ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(DepositTableMap::COL_DEP_ID, $id, $comparison);
    }

    /**
     * Filter the query on the dep_Date column
     *
     * Example usage:
     * <code>
     * $query->filterByDate('2011-03-14'); // WHERE dep_Date = '2011-03-14'
     * $query->filterByDate('now'); // WHERE dep_Date = '2011-03-14'
     * $query->filterByDate(array('max' => 'yesterday')); // WHERE dep_Date > '2011-03-13'
     * </code>
     *
     * @param     mixed $date The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildDepositQuery The current query, for fluid interface
     */
    public function filterByDate($date = null, $comparison = null)
    {
        if (is_array($date)) {
            $useMinMax = false;
            if (isset($date['min'])) {
                $this->addUsingAlias(DepositTableMap::COL_DEP_DATE, $date['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($date['max'])) {
                $this->addUsingAlias(DepositTableMap::COL_DEP_DATE, $date['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(DepositTableMap::COL_DEP_DATE, $date, $comparison);
    }

    /**
     * Filter the query on the dep_Comment column
     *
     * Example usage:
     * <code>
     * $query->filterByComment('fooValue');   // WHERE dep_Comment = 'fooValue'
     * $query->filterByComment('%fooValue%', Criteria::LIKE); // WHERE dep_Comment LIKE '%fooValue%'
     * </code>
     *
     * @param     string $comment The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildDepositQuery The current query, for fluid interface
     */
    public function filterByComment($comment = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($comment)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(DepositTableMap::COL_DEP_COMMENT, $comment, $comparison);
    }

    /**
     * Filter the query on the dep_EnteredBy column
     *
     * Example usage:
     * <code>
     * $query->filterByEnteredby(1234); // WHERE dep_EnteredBy = 1234
     * $query->filterByEnteredby(array(12, 34)); // WHERE dep_EnteredBy IN (12, 34)
     * $query->filterByEnteredby(array('min' => 12)); // WHERE dep_EnteredBy > 12
     * </code>
     *
     * @param     mixed $enteredby The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildDepositQuery The current query, for fluid interface
     */
    public function filterByEnteredby($enteredby = null, $comparison = null)
    {
        if (is_array($enteredby)) {
            $useMinMax = false;
            if (isset($enteredby['min'])) {
                $this->addUsingAlias(DepositTableMap::COL_DEP_ENTEREDBY, $enteredby['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($enteredby['max'])) {
                $this->addUsingAlias(DepositTableMap::COL_DEP_ENTEREDBY, $enteredby['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(DepositTableMap::COL_DEP_ENTEREDBY, $enteredby, $comparison);
    }

    /**
     * Filter the query on the dep_Closed column
     *
     * Example usage:
     * <code>
     * $query->filterByClosed(true); // WHERE dep_Closed = true
     * $query->filterByClosed('yes'); // WHERE dep_Closed = true
     * </code>
     *
     * @param     boolean|string $closed The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildDepositQuery The current query, for fluid interface
     */
    public function filterByClosed($closed = null, $comparison = null)
    {
        if (is_string($closed)) {
            $closed = in_array(strtolower($closed), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(DepositTableMap::COL_DEP_CLOSED, $closed, $comparison);
    }

    /**
     * Filter the query on the dep_Type column
     *
     * Example usage:
     * <code>
     * $query->filterByType('fooValue');   // WHERE dep_Type = 'fooValue'
     * $query->filterByType('%fooValue%', Criteria::LIKE); // WHERE dep_Type LIKE '%fooValue%'
     * </code>
     *
     * @param     string $type The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildDepositQuery The current query, for fluid interface
     */
    public function filterByType($type = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($type)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(DepositTableMap::COL_DEP_TYPE, $type, $comparison);
    }

    /**
     * Filter the query by a related \ChurchCRM\Pledge object
     *
     * @param \ChurchCRM\Pledge|ObjectCollection $pledge the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildDepositQuery The current query, for fluid interface
     */
    public function filterByPledge($pledge, $comparison = null)
    {
        if ($pledge instanceof \ChurchCRM\Pledge) {
            return $this
                ->addUsingAlias(DepositTableMap::COL_DEP_ID, $pledge->getDepid(), $comparison);
        } elseif ($pledge instanceof ObjectCollection) {
            return $this
                ->usePledgeQuery()
                ->filterByPrimaryKeys($pledge->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByPledge() only accepts arguments of type \ChurchCRM\Pledge or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Pledge relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return $this|ChildDepositQuery The current query, for fluid interface
     */
    public function joinPledge($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Pledge');

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
            $this->addJoinObject($join, 'Pledge');
        }

        return $this;
    }

    /**
     * Use the Pledge relation Pledge object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return \ChurchCRM\PledgeQuery A secondary query class using the current class as primary query
     */
    public function usePledgeQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinPledge($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Pledge', '\ChurchCRM\PledgeQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildDeposit $deposit Object to remove from the list of results
     *
     * @return $this|ChildDepositQuery The current query, for fluid interface
     */
    public function prune($deposit = null)
    {
        if ($deposit) {
            $this->addUsingAlias(DepositTableMap::COL_DEP_ID, $deposit->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the deposit_dep table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(DepositTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            DepositTableMap::clearInstancePool();
            DepositTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(DepositTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(DepositTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            DepositTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            DepositTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // DepositQuery
