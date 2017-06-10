<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\DonationFund as ChildDonationFund;
use ChurchCRM\DonationFundQuery as ChildDonationFundQuery;
use ChurchCRM\Map\DonationFundTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\ModelJoin;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'donationfund_fun' table.
 *
 *
 *
 * @method     ChildDonationFundQuery orderById($order = Criteria::ASC) Order by the fun_ID column
 * @method     ChildDonationFundQuery orderByActive($order = Criteria::ASC) Order by the fun_Active column
 * @method     ChildDonationFundQuery orderByName($order = Criteria::ASC) Order by the fun_Name column
 * @method     ChildDonationFundQuery orderByDescription($order = Criteria::ASC) Order by the fun_Description column
 *
 * @method     ChildDonationFundQuery groupById() Group by the fun_ID column
 * @method     ChildDonationFundQuery groupByActive() Group by the fun_Active column
 * @method     ChildDonationFundQuery groupByName() Group by the fun_Name column
 * @method     ChildDonationFundQuery groupByDescription() Group by the fun_Description column
 *
 * @method     ChildDonationFundQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildDonationFundQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildDonationFundQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildDonationFundQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildDonationFundQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildDonationFundQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildDonationFundQuery leftJoinPledge($relationAlias = null) Adds a LEFT JOIN clause to the query using the Pledge relation
 * @method     ChildDonationFundQuery rightJoinPledge($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Pledge relation
 * @method     ChildDonationFundQuery innerJoinPledge($relationAlias = null) Adds a INNER JOIN clause to the query using the Pledge relation
 *
 * @method     ChildDonationFundQuery joinWithPledge($joinType = Criteria::INNER_JOIN) Adds a join clause and with to the query using the Pledge relation
 *
 * @method     ChildDonationFundQuery leftJoinWithPledge() Adds a LEFT JOIN clause and with to the query using the Pledge relation
 * @method     ChildDonationFundQuery rightJoinWithPledge() Adds a RIGHT JOIN clause and with to the query using the Pledge relation
 * @method     ChildDonationFundQuery innerJoinWithPledge() Adds a INNER JOIN clause and with to the query using the Pledge relation
 *
 * @method     \ChurchCRM\PledgeQuery endUse() Finalizes a secondary criteria and merges it with its primary Criteria
 *
 * @method     ChildDonationFund findOne(ConnectionInterface $con = null) Return the first ChildDonationFund matching the query
 * @method     ChildDonationFund findOneOrCreate(ConnectionInterface $con = null) Return the first ChildDonationFund matching the query, or a new ChildDonationFund object populated from the query conditions when no match is found
 *
 * @method     ChildDonationFund findOneById(int $fun_ID) Return the first ChildDonationFund filtered by the fun_ID column
 * @method     ChildDonationFund findOneByActive(string $fun_Active) Return the first ChildDonationFund filtered by the fun_Active column
 * @method     ChildDonationFund findOneByName(string $fun_Name) Return the first ChildDonationFund filtered by the fun_Name column
 * @method     ChildDonationFund findOneByDescription(string $fun_Description) Return the first ChildDonationFund filtered by the fun_Description column *

 * @method     ChildDonationFund requirePk($key, ConnectionInterface $con = null) Return the ChildDonationFund by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildDonationFund requireOne(ConnectionInterface $con = null) Return the first ChildDonationFund matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildDonationFund requireOneById(int $fun_ID) Return the first ChildDonationFund filtered by the fun_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildDonationFund requireOneByActive(string $fun_Active) Return the first ChildDonationFund filtered by the fun_Active column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildDonationFund requireOneByName(string $fun_Name) Return the first ChildDonationFund filtered by the fun_Name column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildDonationFund requireOneByDescription(string $fun_Description) Return the first ChildDonationFund filtered by the fun_Description column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildDonationFund[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildDonationFund objects based on current ModelCriteria
 * @method     ChildDonationFund[]|ObjectCollection findById(int $fun_ID) Return ChildDonationFund objects filtered by the fun_ID column
 * @method     ChildDonationFund[]|ObjectCollection findByActive(string $fun_Active) Return ChildDonationFund objects filtered by the fun_Active column
 * @method     ChildDonationFund[]|ObjectCollection findByName(string $fun_Name) Return ChildDonationFund objects filtered by the fun_Name column
 * @method     ChildDonationFund[]|ObjectCollection findByDescription(string $fun_Description) Return ChildDonationFund objects filtered by the fun_Description column
 * @method     ChildDonationFund[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class DonationFundQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\DonationFundQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\DonationFund', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildDonationFundQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildDonationFundQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildDonationFundQuery) {
            return $criteria;
        }
        $query = new ChildDonationFundQuery();
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
     * @return ChildDonationFund|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(DonationFundTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (
            $this->formatter || $this->modelAlias || $this->with || $this->select
            || $this->selectColumns || $this->asColumns || $this->selectModifiers
            || $this->map || $this->having || $this->joins
        ) {
            return $this->findPkComplex($key, $con);
        }

        if ((null !== ($obj = DonationFundTableMap::getInstanceFromPool(null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key)))) {
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
     * @return ChildDonationFund A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT fun_ID, fun_Active, fun_Name, fun_Description FROM donationfund_fun WHERE fun_ID = :p0';
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
            /** @var ChildDonationFund $obj */
            $obj = new ChildDonationFund();
            $obj->hydrate($row);
            DonationFundTableMap::addInstanceToPool($obj, null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key);
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
     * @return ChildDonationFund|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildDonationFundQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(DonationFundTableMap::COL_FUN_ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildDonationFundQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(DonationFundTableMap::COL_FUN_ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the fun_ID column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE fun_ID = 1234
     * $query->filterById(array(12, 34)); // WHERE fun_ID IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE fun_ID > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildDonationFundQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(DonationFundTableMap::COL_FUN_ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(DonationFundTableMap::COL_FUN_ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(DonationFundTableMap::COL_FUN_ID, $id, $comparison);
    }

    /**
     * Filter the query on the fun_Active column
     *
     * Example usage:
     * <code>
     * $query->filterByActive('fooValue');   // WHERE fun_Active = 'fooValue'
     * $query->filterByActive('%fooValue%', Criteria::LIKE); // WHERE fun_Active LIKE '%fooValue%'
     * </code>
     *
     * @param     string $active The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildDonationFundQuery The current query, for fluid interface
     */
    public function filterByActive($active = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($active)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(DonationFundTableMap::COL_FUN_ACTIVE, $active, $comparison);
    }

    /**
     * Filter the query on the fun_Name column
     *
     * Example usage:
     * <code>
     * $query->filterByName('fooValue');   // WHERE fun_Name = 'fooValue'
     * $query->filterByName('%fooValue%', Criteria::LIKE); // WHERE fun_Name LIKE '%fooValue%'
     * </code>
     *
     * @param     string $name The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildDonationFundQuery The current query, for fluid interface
     */
    public function filterByName($name = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($name)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(DonationFundTableMap::COL_FUN_NAME, $name, $comparison);
    }

    /**
     * Filter the query on the fun_Description column
     *
     * Example usage:
     * <code>
     * $query->filterByDescription('fooValue');   // WHERE fun_Description = 'fooValue'
     * $query->filterByDescription('%fooValue%', Criteria::LIKE); // WHERE fun_Description LIKE '%fooValue%'
     * </code>
     *
     * @param     string $description The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildDonationFundQuery The current query, for fluid interface
     */
    public function filterByDescription($description = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($description)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(DonationFundTableMap::COL_FUN_DESCRIPTION, $description, $comparison);
    }

    /**
     * Filter the query by a related \ChurchCRM\Pledge object
     *
     * @param \ChurchCRM\Pledge|ObjectCollection $pledge the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildDonationFundQuery The current query, for fluid interface
     */
    public function filterByPledge($pledge, $comparison = null)
    {
        if ($pledge instanceof \ChurchCRM\Pledge) {
            return $this
                ->addUsingAlias(DonationFundTableMap::COL_FUN_ID, $pledge->getFundid(), $comparison);
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
     * @return $this|ChildDonationFundQuery The current query, for fluid interface
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
     * @param   ChildDonationFund $donationFund Object to remove from the list of results
     *
     * @return $this|ChildDonationFundQuery The current query, for fluid interface
     */
    public function prune($donationFund = null)
    {
        if ($donationFund) {
            $this->addUsingAlias(DonationFundTableMap::COL_FUN_ID, $donationFund->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the donationfund_fun table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(DonationFundTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            DonationFundTableMap::clearInstancePool();
            DonationFundTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(DonationFundTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(DonationFundTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            DonationFundTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            DonationFundTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // DonationFundQuery
