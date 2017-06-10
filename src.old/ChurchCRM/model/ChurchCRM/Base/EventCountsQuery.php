<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\EventCounts as ChildEventCounts;
use ChurchCRM\EventCountsQuery as ChildEventCountsQuery;
use ChurchCRM\Map\EventCountsTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'eventcounts_evtcnt' table.
 *
 *
 *
 * @method     ChildEventCountsQuery orderByEvtcntEventid($order = Criteria::ASC) Order by the evtcnt_eventid column
 * @method     ChildEventCountsQuery orderByEvtcntCountid($order = Criteria::ASC) Order by the evtcnt_countid column
 * @method     ChildEventCountsQuery orderByEvtcntCountname($order = Criteria::ASC) Order by the evtcnt_countname column
 * @method     ChildEventCountsQuery orderByEvtcntCountcount($order = Criteria::ASC) Order by the evtcnt_countcount column
 * @method     ChildEventCountsQuery orderByEvtcntNotes($order = Criteria::ASC) Order by the evtcnt_notes column
 *
 * @method     ChildEventCountsQuery groupByEvtcntEventid() Group by the evtcnt_eventid column
 * @method     ChildEventCountsQuery groupByEvtcntCountid() Group by the evtcnt_countid column
 * @method     ChildEventCountsQuery groupByEvtcntCountname() Group by the evtcnt_countname column
 * @method     ChildEventCountsQuery groupByEvtcntCountcount() Group by the evtcnt_countcount column
 * @method     ChildEventCountsQuery groupByEvtcntNotes() Group by the evtcnt_notes column
 *
 * @method     ChildEventCountsQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildEventCountsQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildEventCountsQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildEventCountsQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildEventCountsQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildEventCountsQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildEventCounts findOne(ConnectionInterface $con = null) Return the first ChildEventCounts matching the query
 * @method     ChildEventCounts findOneOrCreate(ConnectionInterface $con = null) Return the first ChildEventCounts matching the query, or a new ChildEventCounts object populated from the query conditions when no match is found
 *
 * @method     ChildEventCounts findOneByEvtcntEventid(int $evtcnt_eventid) Return the first ChildEventCounts filtered by the evtcnt_eventid column
 * @method     ChildEventCounts findOneByEvtcntCountid(int $evtcnt_countid) Return the first ChildEventCounts filtered by the evtcnt_countid column
 * @method     ChildEventCounts findOneByEvtcntCountname(string $evtcnt_countname) Return the first ChildEventCounts filtered by the evtcnt_countname column
 * @method     ChildEventCounts findOneByEvtcntCountcount(int $evtcnt_countcount) Return the first ChildEventCounts filtered by the evtcnt_countcount column
 * @method     ChildEventCounts findOneByEvtcntNotes(string $evtcnt_notes) Return the first ChildEventCounts filtered by the evtcnt_notes column *

 * @method     ChildEventCounts requirePk($key, ConnectionInterface $con = null) Return the ChildEventCounts by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEventCounts requireOne(ConnectionInterface $con = null) Return the first ChildEventCounts matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildEventCounts requireOneByEvtcntEventid(int $evtcnt_eventid) Return the first ChildEventCounts filtered by the evtcnt_eventid column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEventCounts requireOneByEvtcntCountid(int $evtcnt_countid) Return the first ChildEventCounts filtered by the evtcnt_countid column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEventCounts requireOneByEvtcntCountname(string $evtcnt_countname) Return the first ChildEventCounts filtered by the evtcnt_countname column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEventCounts requireOneByEvtcntCountcount(int $evtcnt_countcount) Return the first ChildEventCounts filtered by the evtcnt_countcount column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEventCounts requireOneByEvtcntNotes(string $evtcnt_notes) Return the first ChildEventCounts filtered by the evtcnt_notes column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildEventCounts[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildEventCounts objects based on current ModelCriteria
 * @method     ChildEventCounts[]|ObjectCollection findByEvtcntEventid(int $evtcnt_eventid) Return ChildEventCounts objects filtered by the evtcnt_eventid column
 * @method     ChildEventCounts[]|ObjectCollection findByEvtcntCountid(int $evtcnt_countid) Return ChildEventCounts objects filtered by the evtcnt_countid column
 * @method     ChildEventCounts[]|ObjectCollection findByEvtcntCountname(string $evtcnt_countname) Return ChildEventCounts objects filtered by the evtcnt_countname column
 * @method     ChildEventCounts[]|ObjectCollection findByEvtcntCountcount(int $evtcnt_countcount) Return ChildEventCounts objects filtered by the evtcnt_countcount column
 * @method     ChildEventCounts[]|ObjectCollection findByEvtcntNotes(string $evtcnt_notes) Return ChildEventCounts objects filtered by the evtcnt_notes column
 * @method     ChildEventCounts[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class EventCountsQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\EventCountsQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\EventCounts', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildEventCountsQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildEventCountsQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildEventCountsQuery) {
            return $criteria;
        }
        $query = new ChildEventCountsQuery();
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
     * @param array[$evtcnt_eventid, $evtcnt_countid] $key Primary key to use for the query
     * @param ConnectionInterface $con an optional connection object
     *
     * @return ChildEventCounts|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(EventCountsTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (
            $this->formatter || $this->modelAlias || $this->with || $this->select
            || $this->selectColumns || $this->asColumns || $this->selectModifiers
            || $this->map || $this->having || $this->joins
        ) {
            return $this->findPkComplex($key, $con);
        }

        if ((null !== ($obj = EventCountsTableMap::getInstanceFromPool(serialize([(null === $key[0] || is_scalar($key[0]) || is_callable([$key[0], '__toString']) ? (string) $key[0] : $key[0]), (null === $key[1] || is_scalar($key[1]) || is_callable([$key[1], '__toString']) ? (string) $key[1] : $key[1])]))))) {
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
     * @return ChildEventCounts A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT evtcnt_eventid, evtcnt_countid, evtcnt_countname, evtcnt_countcount, evtcnt_notes FROM eventcounts_evtcnt WHERE evtcnt_eventid = :p0 AND evtcnt_countid = :p1';
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
            /** @var ChildEventCounts $obj */
            $obj = new ChildEventCounts();
            $obj->hydrate($row);
            EventCountsTableMap::addInstanceToPool($obj, serialize([(null === $key[0] || is_scalar($key[0]) || is_callable([$key[0], '__toString']) ? (string) $key[0] : $key[0]), (null === $key[1] || is_scalar($key[1]) || is_callable([$key[1], '__toString']) ? (string) $key[1] : $key[1])]));
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
     * @return ChildEventCounts|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildEventCountsQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {
        $this->addUsingAlias(EventCountsTableMap::COL_EVTCNT_EVENTID, $key[0], Criteria::EQUAL);
        $this->addUsingAlias(EventCountsTableMap::COL_EVTCNT_COUNTID, $key[1], Criteria::EQUAL);

        return $this;
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildEventCountsQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {
        if (empty($keys)) {
            return $this->add(null, '1<>1', Criteria::CUSTOM);
        }
        foreach ($keys as $key) {
            $cton0 = $this->getNewCriterion(EventCountsTableMap::COL_EVTCNT_EVENTID, $key[0], Criteria::EQUAL);
            $cton1 = $this->getNewCriterion(EventCountsTableMap::COL_EVTCNT_COUNTID, $key[1], Criteria::EQUAL);
            $cton0->addAnd($cton1);
            $this->addOr($cton0);
        }

        return $this;
    }

    /**
     * Filter the query on the evtcnt_eventid column
     *
     * Example usage:
     * <code>
     * $query->filterByEvtcntEventid(1234); // WHERE evtcnt_eventid = 1234
     * $query->filterByEvtcntEventid(array(12, 34)); // WHERE evtcnt_eventid IN (12, 34)
     * $query->filterByEvtcntEventid(array('min' => 12)); // WHERE evtcnt_eventid > 12
     * </code>
     *
     * @param     mixed $evtcntEventid The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEventCountsQuery The current query, for fluid interface
     */
    public function filterByEvtcntEventid($evtcntEventid = null, $comparison = null)
    {
        if (is_array($evtcntEventid)) {
            $useMinMax = false;
            if (isset($evtcntEventid['min'])) {
                $this->addUsingAlias(EventCountsTableMap::COL_EVTCNT_EVENTID, $evtcntEventid['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($evtcntEventid['max'])) {
                $this->addUsingAlias(EventCountsTableMap::COL_EVTCNT_EVENTID, $evtcntEventid['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EventCountsTableMap::COL_EVTCNT_EVENTID, $evtcntEventid, $comparison);
    }

    /**
     * Filter the query on the evtcnt_countid column
     *
     * Example usage:
     * <code>
     * $query->filterByEvtcntCountid(1234); // WHERE evtcnt_countid = 1234
     * $query->filterByEvtcntCountid(array(12, 34)); // WHERE evtcnt_countid IN (12, 34)
     * $query->filterByEvtcntCountid(array('min' => 12)); // WHERE evtcnt_countid > 12
     * </code>
     *
     * @param     mixed $evtcntCountid The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEventCountsQuery The current query, for fluid interface
     */
    public function filterByEvtcntCountid($evtcntCountid = null, $comparison = null)
    {
        if (is_array($evtcntCountid)) {
            $useMinMax = false;
            if (isset($evtcntCountid['min'])) {
                $this->addUsingAlias(EventCountsTableMap::COL_EVTCNT_COUNTID, $evtcntCountid['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($evtcntCountid['max'])) {
                $this->addUsingAlias(EventCountsTableMap::COL_EVTCNT_COUNTID, $evtcntCountid['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EventCountsTableMap::COL_EVTCNT_COUNTID, $evtcntCountid, $comparison);
    }

    /**
     * Filter the query on the evtcnt_countname column
     *
     * Example usage:
     * <code>
     * $query->filterByEvtcntCountname('fooValue');   // WHERE evtcnt_countname = 'fooValue'
     * $query->filterByEvtcntCountname('%fooValue%', Criteria::LIKE); // WHERE evtcnt_countname LIKE '%fooValue%'
     * </code>
     *
     * @param     string $evtcntCountname The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEventCountsQuery The current query, for fluid interface
     */
    public function filterByEvtcntCountname($evtcntCountname = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($evtcntCountname)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EventCountsTableMap::COL_EVTCNT_COUNTNAME, $evtcntCountname, $comparison);
    }

    /**
     * Filter the query on the evtcnt_countcount column
     *
     * Example usage:
     * <code>
     * $query->filterByEvtcntCountcount(1234); // WHERE evtcnt_countcount = 1234
     * $query->filterByEvtcntCountcount(array(12, 34)); // WHERE evtcnt_countcount IN (12, 34)
     * $query->filterByEvtcntCountcount(array('min' => 12)); // WHERE evtcnt_countcount > 12
     * </code>
     *
     * @param     mixed $evtcntCountcount The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEventCountsQuery The current query, for fluid interface
     */
    public function filterByEvtcntCountcount($evtcntCountcount = null, $comparison = null)
    {
        if (is_array($evtcntCountcount)) {
            $useMinMax = false;
            if (isset($evtcntCountcount['min'])) {
                $this->addUsingAlias(EventCountsTableMap::COL_EVTCNT_COUNTCOUNT, $evtcntCountcount['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($evtcntCountcount['max'])) {
                $this->addUsingAlias(EventCountsTableMap::COL_EVTCNT_COUNTCOUNT, $evtcntCountcount['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EventCountsTableMap::COL_EVTCNT_COUNTCOUNT, $evtcntCountcount, $comparison);
    }

    /**
     * Filter the query on the evtcnt_notes column
     *
     * Example usage:
     * <code>
     * $query->filterByEvtcntNotes('fooValue');   // WHERE evtcnt_notes = 'fooValue'
     * $query->filterByEvtcntNotes('%fooValue%', Criteria::LIKE); // WHERE evtcnt_notes LIKE '%fooValue%'
     * </code>
     *
     * @param     string $evtcntNotes The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEventCountsQuery The current query, for fluid interface
     */
    public function filterByEvtcntNotes($evtcntNotes = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($evtcntNotes)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EventCountsTableMap::COL_EVTCNT_NOTES, $evtcntNotes, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildEventCounts $eventCounts Object to remove from the list of results
     *
     * @return $this|ChildEventCountsQuery The current query, for fluid interface
     */
    public function prune($eventCounts = null)
    {
        if ($eventCounts) {
            $this->addCond('pruneCond0', $this->getAliasedColName(EventCountsTableMap::COL_EVTCNT_EVENTID), $eventCounts->getEvtcntEventid(), Criteria::NOT_EQUAL);
            $this->addCond('pruneCond1', $this->getAliasedColName(EventCountsTableMap::COL_EVTCNT_COUNTID), $eventCounts->getEvtcntCountid(), Criteria::NOT_EQUAL);
            $this->combine(array('pruneCond0', 'pruneCond1'), Criteria::LOGICAL_OR);
        }

        return $this;
    }

    /**
     * Deletes all rows from the eventcounts_evtcnt table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(EventCountsTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            EventCountsTableMap::clearInstancePool();
            EventCountsTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(EventCountsTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(EventCountsTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            EventCountsTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            EventCountsTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // EventCountsQuery
