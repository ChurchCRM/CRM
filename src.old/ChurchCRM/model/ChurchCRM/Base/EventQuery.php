<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\Event as ChildEvent;
use ChurchCRM\EventQuery as ChildEventQuery;
use ChurchCRM\Map\EventTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\ModelJoin;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'events_event' table.
 *
 *
 *
 * @method     ChildEventQuery orderById($order = Criteria::ASC) Order by the event_id column
 * @method     ChildEventQuery orderByType($order = Criteria::ASC) Order by the event_type column
 * @method     ChildEventQuery orderByTitle($order = Criteria::ASC) Order by the event_title column
 * @method     ChildEventQuery orderByDesc($order = Criteria::ASC) Order by the event_desc column
 * @method     ChildEventQuery orderByText($order = Criteria::ASC) Order by the event_text column
 * @method     ChildEventQuery orderByStart($order = Criteria::ASC) Order by the event_start column
 * @method     ChildEventQuery orderByEnd($order = Criteria::ASC) Order by the event_end column
 * @method     ChildEventQuery orderByInActive($order = Criteria::ASC) Order by the inactive column
 * @method     ChildEventQuery orderByTypeName($order = Criteria::ASC) Order by the event_typename column
 *
 * @method     ChildEventQuery groupById() Group by the event_id column
 * @method     ChildEventQuery groupByType() Group by the event_type column
 * @method     ChildEventQuery groupByTitle() Group by the event_title column
 * @method     ChildEventQuery groupByDesc() Group by the event_desc column
 * @method     ChildEventQuery groupByText() Group by the event_text column
 * @method     ChildEventQuery groupByStart() Group by the event_start column
 * @method     ChildEventQuery groupByEnd() Group by the event_end column
 * @method     ChildEventQuery groupByInActive() Group by the inactive column
 * @method     ChildEventQuery groupByTypeName() Group by the event_typename column
 *
 * @method     ChildEventQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildEventQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildEventQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildEventQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildEventQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildEventQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildEventQuery leftJoinEventAttend($relationAlias = null) Adds a LEFT JOIN clause to the query using the EventAttend relation
 * @method     ChildEventQuery rightJoinEventAttend($relationAlias = null) Adds a RIGHT JOIN clause to the query using the EventAttend relation
 * @method     ChildEventQuery innerJoinEventAttend($relationAlias = null) Adds a INNER JOIN clause to the query using the EventAttend relation
 *
 * @method     ChildEventQuery joinWithEventAttend($joinType = Criteria::INNER_JOIN) Adds a join clause and with to the query using the EventAttend relation
 *
 * @method     ChildEventQuery leftJoinWithEventAttend() Adds a LEFT JOIN clause and with to the query using the EventAttend relation
 * @method     ChildEventQuery rightJoinWithEventAttend() Adds a RIGHT JOIN clause and with to the query using the EventAttend relation
 * @method     ChildEventQuery innerJoinWithEventAttend() Adds a INNER JOIN clause and with to the query using the EventAttend relation
 *
 * @method     \ChurchCRM\EventAttendQuery endUse() Finalizes a secondary criteria and merges it with its primary Criteria
 *
 * @method     ChildEvent findOne(ConnectionInterface $con = null) Return the first ChildEvent matching the query
 * @method     ChildEvent findOneOrCreate(ConnectionInterface $con = null) Return the first ChildEvent matching the query, or a new ChildEvent object populated from the query conditions when no match is found
 *
 * @method     ChildEvent findOneById(int $event_id) Return the first ChildEvent filtered by the event_id column
 * @method     ChildEvent findOneByType(int $event_type) Return the first ChildEvent filtered by the event_type column
 * @method     ChildEvent findOneByTitle(string $event_title) Return the first ChildEvent filtered by the event_title column
 * @method     ChildEvent findOneByDesc(string $event_desc) Return the first ChildEvent filtered by the event_desc column
 * @method     ChildEvent findOneByText(string $event_text) Return the first ChildEvent filtered by the event_text column
 * @method     ChildEvent findOneByStart(string $event_start) Return the first ChildEvent filtered by the event_start column
 * @method     ChildEvent findOneByEnd(string $event_end) Return the first ChildEvent filtered by the event_end column
 * @method     ChildEvent findOneByInActive(int $inactive) Return the first ChildEvent filtered by the inactive column
 * @method     ChildEvent findOneByTypeName(string $event_typename) Return the first ChildEvent filtered by the event_typename column *

 * @method     ChildEvent requirePk($key, ConnectionInterface $con = null) Return the ChildEvent by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEvent requireOne(ConnectionInterface $con = null) Return the first ChildEvent matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildEvent requireOneById(int $event_id) Return the first ChildEvent filtered by the event_id column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEvent requireOneByType(int $event_type) Return the first ChildEvent filtered by the event_type column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEvent requireOneByTitle(string $event_title) Return the first ChildEvent filtered by the event_title column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEvent requireOneByDesc(string $event_desc) Return the first ChildEvent filtered by the event_desc column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEvent requireOneByText(string $event_text) Return the first ChildEvent filtered by the event_text column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEvent requireOneByStart(string $event_start) Return the first ChildEvent filtered by the event_start column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEvent requireOneByEnd(string $event_end) Return the first ChildEvent filtered by the event_end column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEvent requireOneByInActive(int $inactive) Return the first ChildEvent filtered by the inactive column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEvent requireOneByTypeName(string $event_typename) Return the first ChildEvent filtered by the event_typename column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildEvent[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildEvent objects based on current ModelCriteria
 * @method     ChildEvent[]|ObjectCollection findById(int $event_id) Return ChildEvent objects filtered by the event_id column
 * @method     ChildEvent[]|ObjectCollection findByType(int $event_type) Return ChildEvent objects filtered by the event_type column
 * @method     ChildEvent[]|ObjectCollection findByTitle(string $event_title) Return ChildEvent objects filtered by the event_title column
 * @method     ChildEvent[]|ObjectCollection findByDesc(string $event_desc) Return ChildEvent objects filtered by the event_desc column
 * @method     ChildEvent[]|ObjectCollection findByText(string $event_text) Return ChildEvent objects filtered by the event_text column
 * @method     ChildEvent[]|ObjectCollection findByStart(string $event_start) Return ChildEvent objects filtered by the event_start column
 * @method     ChildEvent[]|ObjectCollection findByEnd(string $event_end) Return ChildEvent objects filtered by the event_end column
 * @method     ChildEvent[]|ObjectCollection findByInActive(int $inactive) Return ChildEvent objects filtered by the inactive column
 * @method     ChildEvent[]|ObjectCollection findByTypeName(string $event_typename) Return ChildEvent objects filtered by the event_typename column
 * @method     ChildEvent[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class EventQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\EventQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\Event', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildEventQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildEventQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildEventQuery) {
            return $criteria;
        }
        $query = new ChildEventQuery();
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
     * @return ChildEvent|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(EventTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (
            $this->formatter || $this->modelAlias || $this->with || $this->select
            || $this->selectColumns || $this->asColumns || $this->selectModifiers
            || $this->map || $this->having || $this->joins
        ) {
            return $this->findPkComplex($key, $con);
        }

        if ((null !== ($obj = EventTableMap::getInstanceFromPool(null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key)))) {
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
     * @return ChildEvent A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT event_id, event_type, event_title, event_desc, event_text, event_start, event_end, inactive, event_typename FROM events_event WHERE event_id = :p0';
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
            /** @var ChildEvent $obj */
            $obj = new ChildEvent();
            $obj->hydrate($row);
            EventTableMap::addInstanceToPool($obj, null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key);
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
     * @return ChildEvent|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildEventQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(EventTableMap::COL_EVENT_ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildEventQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(EventTableMap::COL_EVENT_ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the event_id column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE event_id = 1234
     * $query->filterById(array(12, 34)); // WHERE event_id IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE event_id > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEventQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(EventTableMap::COL_EVENT_ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(EventTableMap::COL_EVENT_ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EventTableMap::COL_EVENT_ID, $id, $comparison);
    }

    /**
     * Filter the query on the event_type column
     *
     * Example usage:
     * <code>
     * $query->filterByType(1234); // WHERE event_type = 1234
     * $query->filterByType(array(12, 34)); // WHERE event_type IN (12, 34)
     * $query->filterByType(array('min' => 12)); // WHERE event_type > 12
     * </code>
     *
     * @param     mixed $type The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEventQuery The current query, for fluid interface
     */
    public function filterByType($type = null, $comparison = null)
    {
        if (is_array($type)) {
            $useMinMax = false;
            if (isset($type['min'])) {
                $this->addUsingAlias(EventTableMap::COL_EVENT_TYPE, $type['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($type['max'])) {
                $this->addUsingAlias(EventTableMap::COL_EVENT_TYPE, $type['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EventTableMap::COL_EVENT_TYPE, $type, $comparison);
    }

    /**
     * Filter the query on the event_title column
     *
     * Example usage:
     * <code>
     * $query->filterByTitle('fooValue');   // WHERE event_title = 'fooValue'
     * $query->filterByTitle('%fooValue%', Criteria::LIKE); // WHERE event_title LIKE '%fooValue%'
     * </code>
     *
     * @param     string $title The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEventQuery The current query, for fluid interface
     */
    public function filterByTitle($title = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($title)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EventTableMap::COL_EVENT_TITLE, $title, $comparison);
    }

    /**
     * Filter the query on the event_desc column
     *
     * Example usage:
     * <code>
     * $query->filterByDesc('fooValue');   // WHERE event_desc = 'fooValue'
     * $query->filterByDesc('%fooValue%', Criteria::LIKE); // WHERE event_desc LIKE '%fooValue%'
     * </code>
     *
     * @param     string $desc The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEventQuery The current query, for fluid interface
     */
    public function filterByDesc($desc = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($desc)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EventTableMap::COL_EVENT_DESC, $desc, $comparison);
    }

    /**
     * Filter the query on the event_text column
     *
     * Example usage:
     * <code>
     * $query->filterByText('fooValue');   // WHERE event_text = 'fooValue'
     * $query->filterByText('%fooValue%', Criteria::LIKE); // WHERE event_text LIKE '%fooValue%'
     * </code>
     *
     * @param     string $text The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEventQuery The current query, for fluid interface
     */
    public function filterByText($text = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($text)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EventTableMap::COL_EVENT_TEXT, $text, $comparison);
    }

    /**
     * Filter the query on the event_start column
     *
     * Example usage:
     * <code>
     * $query->filterByStart('2011-03-14'); // WHERE event_start = '2011-03-14'
     * $query->filterByStart('now'); // WHERE event_start = '2011-03-14'
     * $query->filterByStart(array('max' => 'yesterday')); // WHERE event_start > '2011-03-13'
     * </code>
     *
     * @param     mixed $start The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEventQuery The current query, for fluid interface
     */
    public function filterByStart($start = null, $comparison = null)
    {
        if (is_array($start)) {
            $useMinMax = false;
            if (isset($start['min'])) {
                $this->addUsingAlias(EventTableMap::COL_EVENT_START, $start['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($start['max'])) {
                $this->addUsingAlias(EventTableMap::COL_EVENT_START, $start['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EventTableMap::COL_EVENT_START, $start, $comparison);
    }

    /**
     * Filter the query on the event_end column
     *
     * Example usage:
     * <code>
     * $query->filterByEnd('2011-03-14'); // WHERE event_end = '2011-03-14'
     * $query->filterByEnd('now'); // WHERE event_end = '2011-03-14'
     * $query->filterByEnd(array('max' => 'yesterday')); // WHERE event_end > '2011-03-13'
     * </code>
     *
     * @param     mixed $end The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEventQuery The current query, for fluid interface
     */
    public function filterByEnd($end = null, $comparison = null)
    {
        if (is_array($end)) {
            $useMinMax = false;
            if (isset($end['min'])) {
                $this->addUsingAlias(EventTableMap::COL_EVENT_END, $end['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($end['max'])) {
                $this->addUsingAlias(EventTableMap::COL_EVENT_END, $end['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EventTableMap::COL_EVENT_END, $end, $comparison);
    }

    /**
     * Filter the query on the inactive column
     *
     * Example usage:
     * <code>
     * $query->filterByInActive(1234); // WHERE inactive = 1234
     * $query->filterByInActive(array(12, 34)); // WHERE inactive IN (12, 34)
     * $query->filterByInActive(array('min' => 12)); // WHERE inactive > 12
     * </code>
     *
     * @param     mixed $inActive The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEventQuery The current query, for fluid interface
     */
    public function filterByInActive($inActive = null, $comparison = null)
    {
        if (is_array($inActive)) {
            $useMinMax = false;
            if (isset($inActive['min'])) {
                $this->addUsingAlias(EventTableMap::COL_INACTIVE, $inActive['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($inActive['max'])) {
                $this->addUsingAlias(EventTableMap::COL_INACTIVE, $inActive['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EventTableMap::COL_INACTIVE, $inActive, $comparison);
    }

    /**
     * Filter the query on the event_typename column
     *
     * Example usage:
     * <code>
     * $query->filterByTypeName('fooValue');   // WHERE event_typename = 'fooValue'
     * $query->filterByTypeName('%fooValue%', Criteria::LIKE); // WHERE event_typename LIKE '%fooValue%'
     * </code>
     *
     * @param     string $typeName The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEventQuery The current query, for fluid interface
     */
    public function filterByTypeName($typeName = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($typeName)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EventTableMap::COL_EVENT_TYPENAME, $typeName, $comparison);
    }

    /**
     * Filter the query by a related \ChurchCRM\EventAttend object
     *
     * @param \ChurchCRM\EventAttend|ObjectCollection $eventAttend the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildEventQuery The current query, for fluid interface
     */
    public function filterByEventAttend($eventAttend, $comparison = null)
    {
        if ($eventAttend instanceof \ChurchCRM\EventAttend) {
            return $this
                ->addUsingAlias(EventTableMap::COL_EVENT_ID, $eventAttend->getEventId(), $comparison);
        } elseif ($eventAttend instanceof ObjectCollection) {
            return $this
                ->useEventAttendQuery()
                ->filterByPrimaryKeys($eventAttend->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByEventAttend() only accepts arguments of type \ChurchCRM\EventAttend or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the EventAttend relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return $this|ChildEventQuery The current query, for fluid interface
     */
    public function joinEventAttend($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('EventAttend');

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
            $this->addJoinObject($join, 'EventAttend');
        }

        return $this;
    }

    /**
     * Use the EventAttend relation EventAttend object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return \ChurchCRM\EventAttendQuery A secondary query class using the current class as primary query
     */
    public function useEventAttendQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinEventAttend($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'EventAttend', '\ChurchCRM\EventAttendQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildEvent $event Object to remove from the list of results
     *
     * @return $this|ChildEventQuery The current query, for fluid interface
     */
    public function prune($event = null)
    {
        if ($event) {
            $this->addUsingAlias(EventTableMap::COL_EVENT_ID, $event->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the events_event table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(EventTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            EventTableMap::clearInstancePool();
            EventTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(EventTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(EventTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            EventTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            EventTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // EventQuery
