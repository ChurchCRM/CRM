<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\EventTypes as ChildEventTypes;
use ChurchCRM\EventTypesQuery as ChildEventTypesQuery;
use ChurchCRM\Map\EventTypesTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'event_types' table.
 *
 *
 *
 * @method     ChildEventTypesQuery orderById($order = Criteria::ASC) Order by the type_id column
 * @method     ChildEventTypesQuery orderByName($order = Criteria::ASC) Order by the type_name column
 * @method     ChildEventTypesQuery orderByDefStartTime($order = Criteria::ASC) Order by the type_defstarttime column
 * @method     ChildEventTypesQuery orderByDefRecurType($order = Criteria::ASC) Order by the type_defrecurtype column
 * @method     ChildEventTypesQuery orderByDefRecurDOW($order = Criteria::ASC) Order by the type_defrecurDOW column
 * @method     ChildEventTypesQuery orderByDefRecurDOM($order = Criteria::ASC) Order by the type_defrecurDOM column
 * @method     ChildEventTypesQuery orderByDefRecurDOY($order = Criteria::ASC) Order by the type_defrecurDOY column
 * @method     ChildEventTypesQuery orderByActive($order = Criteria::ASC) Order by the type_active column
 *
 * @method     ChildEventTypesQuery groupById() Group by the type_id column
 * @method     ChildEventTypesQuery groupByName() Group by the type_name column
 * @method     ChildEventTypesQuery groupByDefStartTime() Group by the type_defstarttime column
 * @method     ChildEventTypesQuery groupByDefRecurType() Group by the type_defrecurtype column
 * @method     ChildEventTypesQuery groupByDefRecurDOW() Group by the type_defrecurDOW column
 * @method     ChildEventTypesQuery groupByDefRecurDOM() Group by the type_defrecurDOM column
 * @method     ChildEventTypesQuery groupByDefRecurDOY() Group by the type_defrecurDOY column
 * @method     ChildEventTypesQuery groupByActive() Group by the type_active column
 *
 * @method     ChildEventTypesQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildEventTypesQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildEventTypesQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildEventTypesQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildEventTypesQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildEventTypesQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildEventTypes findOne(ConnectionInterface $con = null) Return the first ChildEventTypes matching the query
 * @method     ChildEventTypes findOneOrCreate(ConnectionInterface $con = null) Return the first ChildEventTypes matching the query, or a new ChildEventTypes object populated from the query conditions when no match is found
 *
 * @method     ChildEventTypes findOneById(int $type_id) Return the first ChildEventTypes filtered by the type_id column
 * @method     ChildEventTypes findOneByName(string $type_name) Return the first ChildEventTypes filtered by the type_name column
 * @method     ChildEventTypes findOneByDefStartTime(string $type_defstarttime) Return the first ChildEventTypes filtered by the type_defstarttime column
 * @method     ChildEventTypes findOneByDefRecurType(string $type_defrecurtype) Return the first ChildEventTypes filtered by the type_defrecurtype column
 * @method     ChildEventTypes findOneByDefRecurDOW(string $type_defrecurDOW) Return the first ChildEventTypes filtered by the type_defrecurDOW column
 * @method     ChildEventTypes findOneByDefRecurDOM(string $type_defrecurDOM) Return the first ChildEventTypes filtered by the type_defrecurDOM column
 * @method     ChildEventTypes findOneByDefRecurDOY(string $type_defrecurDOY) Return the first ChildEventTypes filtered by the type_defrecurDOY column
 * @method     ChildEventTypes findOneByActive(int $type_active) Return the first ChildEventTypes filtered by the type_active column *

 * @method     ChildEventTypes requirePk($key, ConnectionInterface $con = null) Return the ChildEventTypes by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEventTypes requireOne(ConnectionInterface $con = null) Return the first ChildEventTypes matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildEventTypes requireOneById(int $type_id) Return the first ChildEventTypes filtered by the type_id column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEventTypes requireOneByName(string $type_name) Return the first ChildEventTypes filtered by the type_name column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEventTypes requireOneByDefStartTime(string $type_defstarttime) Return the first ChildEventTypes filtered by the type_defstarttime column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEventTypes requireOneByDefRecurType(string $type_defrecurtype) Return the first ChildEventTypes filtered by the type_defrecurtype column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEventTypes requireOneByDefRecurDOW(string $type_defrecurDOW) Return the first ChildEventTypes filtered by the type_defrecurDOW column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEventTypes requireOneByDefRecurDOM(string $type_defrecurDOM) Return the first ChildEventTypes filtered by the type_defrecurDOM column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEventTypes requireOneByDefRecurDOY(string $type_defrecurDOY) Return the first ChildEventTypes filtered by the type_defrecurDOY column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEventTypes requireOneByActive(int $type_active) Return the first ChildEventTypes filtered by the type_active column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildEventTypes[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildEventTypes objects based on current ModelCriteria
 * @method     ChildEventTypes[]|ObjectCollection findById(int $type_id) Return ChildEventTypes objects filtered by the type_id column
 * @method     ChildEventTypes[]|ObjectCollection findByName(string $type_name) Return ChildEventTypes objects filtered by the type_name column
 * @method     ChildEventTypes[]|ObjectCollection findByDefStartTime(string $type_defstarttime) Return ChildEventTypes objects filtered by the type_defstarttime column
 * @method     ChildEventTypes[]|ObjectCollection findByDefRecurType(string $type_defrecurtype) Return ChildEventTypes objects filtered by the type_defrecurtype column
 * @method     ChildEventTypes[]|ObjectCollection findByDefRecurDOW(string $type_defrecurDOW) Return ChildEventTypes objects filtered by the type_defrecurDOW column
 * @method     ChildEventTypes[]|ObjectCollection findByDefRecurDOM(string $type_defrecurDOM) Return ChildEventTypes objects filtered by the type_defrecurDOM column
 * @method     ChildEventTypes[]|ObjectCollection findByDefRecurDOY(string $type_defrecurDOY) Return ChildEventTypes objects filtered by the type_defrecurDOY column
 * @method     ChildEventTypes[]|ObjectCollection findByActive(int $type_active) Return ChildEventTypes objects filtered by the type_active column
 * @method     ChildEventTypes[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class EventTypesQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\EventTypesQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\EventTypes', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildEventTypesQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildEventTypesQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildEventTypesQuery) {
            return $criteria;
        }
        $query = new ChildEventTypesQuery();
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
     * @return ChildEventTypes|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(EventTypesTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (
            $this->formatter || $this->modelAlias || $this->with || $this->select
            || $this->selectColumns || $this->asColumns || $this->selectModifiers
            || $this->map || $this->having || $this->joins
        ) {
            return $this->findPkComplex($key, $con);
        }

        if ((null !== ($obj = EventTypesTableMap::getInstanceFromPool(null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key)))) {
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
     * @return ChildEventTypes A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT type_id, type_name, type_defstarttime, type_defrecurtype, type_defrecurDOW, type_defrecurDOM, type_defrecurDOY, type_active FROM event_types WHERE type_id = :p0';
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
            /** @var ChildEventTypes $obj */
            $obj = new ChildEventTypes();
            $obj->hydrate($row);
            EventTypesTableMap::addInstanceToPool($obj, null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key);
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
     * @return ChildEventTypes|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildEventTypesQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(EventTypesTableMap::COL_TYPE_ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildEventTypesQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(EventTypesTableMap::COL_TYPE_ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the type_id column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE type_id = 1234
     * $query->filterById(array(12, 34)); // WHERE type_id IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE type_id > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEventTypesQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(EventTypesTableMap::COL_TYPE_ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(EventTypesTableMap::COL_TYPE_ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EventTypesTableMap::COL_TYPE_ID, $id, $comparison);
    }

    /**
     * Filter the query on the type_name column
     *
     * Example usage:
     * <code>
     * $query->filterByName('fooValue');   // WHERE type_name = 'fooValue'
     * $query->filterByName('%fooValue%', Criteria::LIKE); // WHERE type_name LIKE '%fooValue%'
     * </code>
     *
     * @param     string $name The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEventTypesQuery The current query, for fluid interface
     */
    public function filterByName($name = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($name)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EventTypesTableMap::COL_TYPE_NAME, $name, $comparison);
    }

    /**
     * Filter the query on the type_defstarttime column
     *
     * Example usage:
     * <code>
     * $query->filterByDefStartTime('2011-03-14'); // WHERE type_defstarttime = '2011-03-14'
     * $query->filterByDefStartTime('now'); // WHERE type_defstarttime = '2011-03-14'
     * $query->filterByDefStartTime(array('max' => 'yesterday')); // WHERE type_defstarttime > '2011-03-13'
     * </code>
     *
     * @param     mixed $defStartTime The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEventTypesQuery The current query, for fluid interface
     */
    public function filterByDefStartTime($defStartTime = null, $comparison = null)
    {
        if (is_array($defStartTime)) {
            $useMinMax = false;
            if (isset($defStartTime['min'])) {
                $this->addUsingAlias(EventTypesTableMap::COL_TYPE_DEFSTARTTIME, $defStartTime['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($defStartTime['max'])) {
                $this->addUsingAlias(EventTypesTableMap::COL_TYPE_DEFSTARTTIME, $defStartTime['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EventTypesTableMap::COL_TYPE_DEFSTARTTIME, $defStartTime, $comparison);
    }

    /**
     * Filter the query on the type_defrecurtype column
     *
     * Example usage:
     * <code>
     * $query->filterByDefRecurType('fooValue');   // WHERE type_defrecurtype = 'fooValue'
     * $query->filterByDefRecurType('%fooValue%', Criteria::LIKE); // WHERE type_defrecurtype LIKE '%fooValue%'
     * </code>
     *
     * @param     string $defRecurType The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEventTypesQuery The current query, for fluid interface
     */
    public function filterByDefRecurType($defRecurType = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($defRecurType)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EventTypesTableMap::COL_TYPE_DEFRECURTYPE, $defRecurType, $comparison);
    }

    /**
     * Filter the query on the type_defrecurDOW column
     *
     * Example usage:
     * <code>
     * $query->filterByDefRecurDOW('fooValue');   // WHERE type_defrecurDOW = 'fooValue'
     * $query->filterByDefRecurDOW('%fooValue%', Criteria::LIKE); // WHERE type_defrecurDOW LIKE '%fooValue%'
     * </code>
     *
     * @param     string $defRecurDOW The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEventTypesQuery The current query, for fluid interface
     */
    public function filterByDefRecurDOW($defRecurDOW = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($defRecurDOW)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EventTypesTableMap::COL_TYPE_DEFRECURDOW, $defRecurDOW, $comparison);
    }

    /**
     * Filter the query on the type_defrecurDOM column
     *
     * Example usage:
     * <code>
     * $query->filterByDefRecurDOM('fooValue');   // WHERE type_defrecurDOM = 'fooValue'
     * $query->filterByDefRecurDOM('%fooValue%', Criteria::LIKE); // WHERE type_defrecurDOM LIKE '%fooValue%'
     * </code>
     *
     * @param     string $defRecurDOM The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEventTypesQuery The current query, for fluid interface
     */
    public function filterByDefRecurDOM($defRecurDOM = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($defRecurDOM)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EventTypesTableMap::COL_TYPE_DEFRECURDOM, $defRecurDOM, $comparison);
    }

    /**
     * Filter the query on the type_defrecurDOY column
     *
     * Example usage:
     * <code>
     * $query->filterByDefRecurDOY('2011-03-14'); // WHERE type_defrecurDOY = '2011-03-14'
     * $query->filterByDefRecurDOY('now'); // WHERE type_defrecurDOY = '2011-03-14'
     * $query->filterByDefRecurDOY(array('max' => 'yesterday')); // WHERE type_defrecurDOY > '2011-03-13'
     * </code>
     *
     * @param     mixed $defRecurDOY The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEventTypesQuery The current query, for fluid interface
     */
    public function filterByDefRecurDOY($defRecurDOY = null, $comparison = null)
    {
        if (is_array($defRecurDOY)) {
            $useMinMax = false;
            if (isset($defRecurDOY['min'])) {
                $this->addUsingAlias(EventTypesTableMap::COL_TYPE_DEFRECURDOY, $defRecurDOY['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($defRecurDOY['max'])) {
                $this->addUsingAlias(EventTypesTableMap::COL_TYPE_DEFRECURDOY, $defRecurDOY['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EventTypesTableMap::COL_TYPE_DEFRECURDOY, $defRecurDOY, $comparison);
    }

    /**
     * Filter the query on the type_active column
     *
     * Example usage:
     * <code>
     * $query->filterByActive(1234); // WHERE type_active = 1234
     * $query->filterByActive(array(12, 34)); // WHERE type_active IN (12, 34)
     * $query->filterByActive(array('min' => 12)); // WHERE type_active > 12
     * </code>
     *
     * @param     mixed $active The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEventTypesQuery The current query, for fluid interface
     */
    public function filterByActive($active = null, $comparison = null)
    {
        if (is_array($active)) {
            $useMinMax = false;
            if (isset($active['min'])) {
                $this->addUsingAlias(EventTypesTableMap::COL_TYPE_ACTIVE, $active['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($active['max'])) {
                $this->addUsingAlias(EventTypesTableMap::COL_TYPE_ACTIVE, $active['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EventTypesTableMap::COL_TYPE_ACTIVE, $active, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildEventTypes $eventTypes Object to remove from the list of results
     *
     * @return $this|ChildEventTypesQuery The current query, for fluid interface
     */
    public function prune($eventTypes = null)
    {
        if ($eventTypes) {
            $this->addUsingAlias(EventTypesTableMap::COL_TYPE_ID, $eventTypes->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the event_types table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(EventTypesTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            EventTypesTableMap::clearInstancePool();
            EventTypesTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(EventTypesTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(EventTypesTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            EventTypesTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            EventTypesTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // EventTypesQuery
