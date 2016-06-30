<?php

namespace ChurchCRM\Base;

use \Exception;
use ChurchCRM\EventAttend as ChildEventAttend;
use ChurchCRM\EventAttendQuery as ChildEventAttendQuery;
use ChurchCRM\Map\EventAttendTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\LogicException;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'event_attend' table.
 *
 *
 *
 * @method     ChildEventAttendQuery orderByEventId($order = Criteria::ASC) Order by the event_id column
 * @method     ChildEventAttendQuery orderByPersonId($order = Criteria::ASC) Order by the person_id column
 * @method     ChildEventAttendQuery orderByCheckinDate($order = Criteria::ASC) Order by the checkin_date column
 * @method     ChildEventAttendQuery orderByCheckinId($order = Criteria::ASC) Order by the checkin_id column
 * @method     ChildEventAttendQuery orderByCheckoutDate($order = Criteria::ASC) Order by the checkout_date column
 * @method     ChildEventAttendQuery orderByCheckoutId($order = Criteria::ASC) Order by the checkout_id column
 *
 * @method     ChildEventAttendQuery groupByEventId() Group by the event_id column
 * @method     ChildEventAttendQuery groupByPersonId() Group by the person_id column
 * @method     ChildEventAttendQuery groupByCheckinDate() Group by the checkin_date column
 * @method     ChildEventAttendQuery groupByCheckinId() Group by the checkin_id column
 * @method     ChildEventAttendQuery groupByCheckoutDate() Group by the checkout_date column
 * @method     ChildEventAttendQuery groupByCheckoutId() Group by the checkout_id column
 *
 * @method     ChildEventAttendQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildEventAttendQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildEventAttendQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildEventAttendQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildEventAttendQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildEventAttendQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildEventAttend findOne(ConnectionInterface $con = null) Return the first ChildEventAttend matching the query
 * @method     ChildEventAttend findOneOrCreate(ConnectionInterface $con = null) Return the first ChildEventAttend matching the query, or a new ChildEventAttend object populated from the query conditions when no match is found
 *
 * @method     ChildEventAttend findOneByEventId(int $event_id) Return the first ChildEventAttend filtered by the event_id column
 * @method     ChildEventAttend findOneByPersonId(int $person_id) Return the first ChildEventAttend filtered by the person_id column
 * @method     ChildEventAttend findOneByCheckinDate(string $checkin_date) Return the first ChildEventAttend filtered by the checkin_date column
 * @method     ChildEventAttend findOneByCheckinId(int $checkin_id) Return the first ChildEventAttend filtered by the checkin_id column
 * @method     ChildEventAttend findOneByCheckoutDate(string $checkout_date) Return the first ChildEventAttend filtered by the checkout_date column
 * @method     ChildEventAttend findOneByCheckoutId(int $checkout_id) Return the first ChildEventAttend filtered by the checkout_id column *

 * @method     ChildEventAttend requirePk($key, ConnectionInterface $con = null) Return the ChildEventAttend by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEventAttend requireOne(ConnectionInterface $con = null) Return the first ChildEventAttend matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildEventAttend requireOneByEventId(int $event_id) Return the first ChildEventAttend filtered by the event_id column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEventAttend requireOneByPersonId(int $person_id) Return the first ChildEventAttend filtered by the person_id column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEventAttend requireOneByCheckinDate(string $checkin_date) Return the first ChildEventAttend filtered by the checkin_date column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEventAttend requireOneByCheckinId(int $checkin_id) Return the first ChildEventAttend filtered by the checkin_id column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEventAttend requireOneByCheckoutDate(string $checkout_date) Return the first ChildEventAttend filtered by the checkout_date column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEventAttend requireOneByCheckoutId(int $checkout_id) Return the first ChildEventAttend filtered by the checkout_id column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildEventAttend[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildEventAttend objects based on current ModelCriteria
 * @method     ChildEventAttend[]|ObjectCollection findByEventId(int $event_id) Return ChildEventAttend objects filtered by the event_id column
 * @method     ChildEventAttend[]|ObjectCollection findByPersonId(int $person_id) Return ChildEventAttend objects filtered by the person_id column
 * @method     ChildEventAttend[]|ObjectCollection findByCheckinDate(string $checkin_date) Return ChildEventAttend objects filtered by the checkin_date column
 * @method     ChildEventAttend[]|ObjectCollection findByCheckinId(int $checkin_id) Return ChildEventAttend objects filtered by the checkin_id column
 * @method     ChildEventAttend[]|ObjectCollection findByCheckoutDate(string $checkout_date) Return ChildEventAttend objects filtered by the checkout_date column
 * @method     ChildEventAttend[]|ObjectCollection findByCheckoutId(int $checkout_id) Return ChildEventAttend objects filtered by the checkout_id column
 * @method     ChildEventAttend[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class EventAttendQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\EventAttendQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\EventAttend', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildEventAttendQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildEventAttendQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildEventAttendQuery) {
            return $criteria;
        }
        $query = new ChildEventAttendQuery();
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
     * @return ChildEventAttend|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        throw new LogicException('The EventAttend object has no primary key');
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
        throw new LogicException('The EventAttend object has no primary key');
    }

    /**
     * Filter the query by primary key
     *
     * @param     mixed $key Primary key to use for the query
     *
     * @return $this|ChildEventAttendQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {
        throw new LogicException('The EventAttend object has no primary key');
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildEventAttendQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {
        throw new LogicException('The EventAttend object has no primary key');
    }

    /**
     * Filter the query on the event_id column
     *
     * Example usage:
     * <code>
     * $query->filterByEventId(1234); // WHERE event_id = 1234
     * $query->filterByEventId(array(12, 34)); // WHERE event_id IN (12, 34)
     * $query->filterByEventId(array('min' => 12)); // WHERE event_id > 12
     * </code>
     *
     * @param     mixed $eventId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEventAttendQuery The current query, for fluid interface
     */
    public function filterByEventId($eventId = null, $comparison = null)
    {
        if (is_array($eventId)) {
            $useMinMax = false;
            if (isset($eventId['min'])) {
                $this->addUsingAlias(EventAttendTableMap::COL_EVENT_ID, $eventId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($eventId['max'])) {
                $this->addUsingAlias(EventAttendTableMap::COL_EVENT_ID, $eventId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EventAttendTableMap::COL_EVENT_ID, $eventId, $comparison);
    }

    /**
     * Filter the query on the person_id column
     *
     * Example usage:
     * <code>
     * $query->filterByPersonId(1234); // WHERE person_id = 1234
     * $query->filterByPersonId(array(12, 34)); // WHERE person_id IN (12, 34)
     * $query->filterByPersonId(array('min' => 12)); // WHERE person_id > 12
     * </code>
     *
     * @param     mixed $personId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEventAttendQuery The current query, for fluid interface
     */
    public function filterByPersonId($personId = null, $comparison = null)
    {
        if (is_array($personId)) {
            $useMinMax = false;
            if (isset($personId['min'])) {
                $this->addUsingAlias(EventAttendTableMap::COL_PERSON_ID, $personId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($personId['max'])) {
                $this->addUsingAlias(EventAttendTableMap::COL_PERSON_ID, $personId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EventAttendTableMap::COL_PERSON_ID, $personId, $comparison);
    }

    /**
     * Filter the query on the checkin_date column
     *
     * Example usage:
     * <code>
     * $query->filterByCheckinDate('2011-03-14'); // WHERE checkin_date = '2011-03-14'
     * $query->filterByCheckinDate('now'); // WHERE checkin_date = '2011-03-14'
     * $query->filterByCheckinDate(array('max' => 'yesterday')); // WHERE checkin_date > '2011-03-13'
     * </code>
     *
     * @param     mixed $checkinDate The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEventAttendQuery The current query, for fluid interface
     */
    public function filterByCheckinDate($checkinDate = null, $comparison = null)
    {
        if (is_array($checkinDate)) {
            $useMinMax = false;
            if (isset($checkinDate['min'])) {
                $this->addUsingAlias(EventAttendTableMap::COL_CHECKIN_DATE, $checkinDate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($checkinDate['max'])) {
                $this->addUsingAlias(EventAttendTableMap::COL_CHECKIN_DATE, $checkinDate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EventAttendTableMap::COL_CHECKIN_DATE, $checkinDate, $comparison);
    }

    /**
     * Filter the query on the checkin_id column
     *
     * Example usage:
     * <code>
     * $query->filterByCheckinId(1234); // WHERE checkin_id = 1234
     * $query->filterByCheckinId(array(12, 34)); // WHERE checkin_id IN (12, 34)
     * $query->filterByCheckinId(array('min' => 12)); // WHERE checkin_id > 12
     * </code>
     *
     * @param     mixed $checkinId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEventAttendQuery The current query, for fluid interface
     */
    public function filterByCheckinId($checkinId = null, $comparison = null)
    {
        if (is_array($checkinId)) {
            $useMinMax = false;
            if (isset($checkinId['min'])) {
                $this->addUsingAlias(EventAttendTableMap::COL_CHECKIN_ID, $checkinId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($checkinId['max'])) {
                $this->addUsingAlias(EventAttendTableMap::COL_CHECKIN_ID, $checkinId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EventAttendTableMap::COL_CHECKIN_ID, $checkinId, $comparison);
    }

    /**
     * Filter the query on the checkout_date column
     *
     * Example usage:
     * <code>
     * $query->filterByCheckoutDate('2011-03-14'); // WHERE checkout_date = '2011-03-14'
     * $query->filterByCheckoutDate('now'); // WHERE checkout_date = '2011-03-14'
     * $query->filterByCheckoutDate(array('max' => 'yesterday')); // WHERE checkout_date > '2011-03-13'
     * </code>
     *
     * @param     mixed $checkoutDate The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEventAttendQuery The current query, for fluid interface
     */
    public function filterByCheckoutDate($checkoutDate = null, $comparison = null)
    {
        if (is_array($checkoutDate)) {
            $useMinMax = false;
            if (isset($checkoutDate['min'])) {
                $this->addUsingAlias(EventAttendTableMap::COL_CHECKOUT_DATE, $checkoutDate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($checkoutDate['max'])) {
                $this->addUsingAlias(EventAttendTableMap::COL_CHECKOUT_DATE, $checkoutDate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EventAttendTableMap::COL_CHECKOUT_DATE, $checkoutDate, $comparison);
    }

    /**
     * Filter the query on the checkout_id column
     *
     * Example usage:
     * <code>
     * $query->filterByCheckoutId(1234); // WHERE checkout_id = 1234
     * $query->filterByCheckoutId(array(12, 34)); // WHERE checkout_id IN (12, 34)
     * $query->filterByCheckoutId(array('min' => 12)); // WHERE checkout_id > 12
     * </code>
     *
     * @param     mixed $checkoutId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEventAttendQuery The current query, for fluid interface
     */
    public function filterByCheckoutId($checkoutId = null, $comparison = null)
    {
        if (is_array($checkoutId)) {
            $useMinMax = false;
            if (isset($checkoutId['min'])) {
                $this->addUsingAlias(EventAttendTableMap::COL_CHECKOUT_ID, $checkoutId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($checkoutId['max'])) {
                $this->addUsingAlias(EventAttendTableMap::COL_CHECKOUT_ID, $checkoutId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EventAttendTableMap::COL_CHECKOUT_ID, $checkoutId, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildEventAttend $eventAttend Object to remove from the list of results
     *
     * @return $this|ChildEventAttendQuery The current query, for fluid interface
     */
    public function prune($eventAttend = null)
    {
        if ($eventAttend) {
            throw new LogicException('EventAttend object has no primary key');

        }

        return $this;
    }

    /**
     * Deletes all rows from the event_attend table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(EventAttendTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            EventAttendTableMap::clearInstancePool();
            EventAttendTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(EventAttendTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(EventAttendTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            EventAttendTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            EventAttendTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // EventAttendQuery
