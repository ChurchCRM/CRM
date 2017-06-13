<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\CanvassData as ChildCanvassData;
use ChurchCRM\CanvassDataQuery as ChildCanvassDataQuery;
use ChurchCRM\Map\CanvassDataTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'canvassdata_can' table.
 *
 *
 *
 * @method     ChildCanvassDataQuery orderById($order = Criteria::ASC) Order by the can_ID column
 * @method     ChildCanvassDataQuery orderByFamilyId($order = Criteria::ASC) Order by the can_famID column
 * @method     ChildCanvassDataQuery orderByCanvasser($order = Criteria::ASC) Order by the can_Canvasser column
 * @method     ChildCanvassDataQuery orderByFyid($order = Criteria::ASC) Order by the can_FYID column
 * @method     ChildCanvassDataQuery orderByDate($order = Criteria::ASC) Order by the can_date column
 * @method     ChildCanvassDataQuery orderByPositive($order = Criteria::ASC) Order by the can_Positive column
 * @method     ChildCanvassDataQuery orderByCritical($order = Criteria::ASC) Order by the can_Critical column
 * @method     ChildCanvassDataQuery orderByInsightful($order = Criteria::ASC) Order by the can_Insightful column
 * @method     ChildCanvassDataQuery orderByFinancial($order = Criteria::ASC) Order by the can_Financial column
 * @method     ChildCanvassDataQuery orderBySuggestion($order = Criteria::ASC) Order by the can_Suggestion column
 * @method     ChildCanvassDataQuery orderByNotInterested($order = Criteria::ASC) Order by the can_NotInterested column
 * @method     ChildCanvassDataQuery orderByWhyNotInterested($order = Criteria::ASC) Order by the can_WhyNotInterested column
 *
 * @method     ChildCanvassDataQuery groupById() Group by the can_ID column
 * @method     ChildCanvassDataQuery groupByFamilyId() Group by the can_famID column
 * @method     ChildCanvassDataQuery groupByCanvasser() Group by the can_Canvasser column
 * @method     ChildCanvassDataQuery groupByFyid() Group by the can_FYID column
 * @method     ChildCanvassDataQuery groupByDate() Group by the can_date column
 * @method     ChildCanvassDataQuery groupByPositive() Group by the can_Positive column
 * @method     ChildCanvassDataQuery groupByCritical() Group by the can_Critical column
 * @method     ChildCanvassDataQuery groupByInsightful() Group by the can_Insightful column
 * @method     ChildCanvassDataQuery groupByFinancial() Group by the can_Financial column
 * @method     ChildCanvassDataQuery groupBySuggestion() Group by the can_Suggestion column
 * @method     ChildCanvassDataQuery groupByNotInterested() Group by the can_NotInterested column
 * @method     ChildCanvassDataQuery groupByWhyNotInterested() Group by the can_WhyNotInterested column
 *
 * @method     ChildCanvassDataQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildCanvassDataQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildCanvassDataQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildCanvassDataQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildCanvassDataQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildCanvassDataQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildCanvassData findOne(ConnectionInterface $con = null) Return the first ChildCanvassData matching the query
 * @method     ChildCanvassData findOneOrCreate(ConnectionInterface $con = null) Return the first ChildCanvassData matching the query, or a new ChildCanvassData object populated from the query conditions when no match is found
 *
 * @method     ChildCanvassData findOneById(int $can_ID) Return the first ChildCanvassData filtered by the can_ID column
 * @method     ChildCanvassData findOneByFamilyId(int $can_famID) Return the first ChildCanvassData filtered by the can_famID column
 * @method     ChildCanvassData findOneByCanvasser(int $can_Canvasser) Return the first ChildCanvassData filtered by the can_Canvasser column
 * @method     ChildCanvassData findOneByFyid(int $can_FYID) Return the first ChildCanvassData filtered by the can_FYID column
 * @method     ChildCanvassData findOneByDate(string $can_date) Return the first ChildCanvassData filtered by the can_date column
 * @method     ChildCanvassData findOneByPositive(string $can_Positive) Return the first ChildCanvassData filtered by the can_Positive column
 * @method     ChildCanvassData findOneByCritical(string $can_Critical) Return the first ChildCanvassData filtered by the can_Critical column
 * @method     ChildCanvassData findOneByInsightful(string $can_Insightful) Return the first ChildCanvassData filtered by the can_Insightful column
 * @method     ChildCanvassData findOneByFinancial(string $can_Financial) Return the first ChildCanvassData filtered by the can_Financial column
 * @method     ChildCanvassData findOneBySuggestion(string $can_Suggestion) Return the first ChildCanvassData filtered by the can_Suggestion column
 * @method     ChildCanvassData findOneByNotInterested(boolean $can_NotInterested) Return the first ChildCanvassData filtered by the can_NotInterested column
 * @method     ChildCanvassData findOneByWhyNotInterested(string $can_WhyNotInterested) Return the first ChildCanvassData filtered by the can_WhyNotInterested column *

 * @method     ChildCanvassData requirePk($key, ConnectionInterface $con = null) Return the ChildCanvassData by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildCanvassData requireOne(ConnectionInterface $con = null) Return the first ChildCanvassData matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildCanvassData requireOneById(int $can_ID) Return the first ChildCanvassData filtered by the can_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildCanvassData requireOneByFamilyId(int $can_famID) Return the first ChildCanvassData filtered by the can_famID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildCanvassData requireOneByCanvasser(int $can_Canvasser) Return the first ChildCanvassData filtered by the can_Canvasser column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildCanvassData requireOneByFyid(int $can_FYID) Return the first ChildCanvassData filtered by the can_FYID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildCanvassData requireOneByDate(string $can_date) Return the first ChildCanvassData filtered by the can_date column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildCanvassData requireOneByPositive(string $can_Positive) Return the first ChildCanvassData filtered by the can_Positive column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildCanvassData requireOneByCritical(string $can_Critical) Return the first ChildCanvassData filtered by the can_Critical column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildCanvassData requireOneByInsightful(string $can_Insightful) Return the first ChildCanvassData filtered by the can_Insightful column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildCanvassData requireOneByFinancial(string $can_Financial) Return the first ChildCanvassData filtered by the can_Financial column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildCanvassData requireOneBySuggestion(string $can_Suggestion) Return the first ChildCanvassData filtered by the can_Suggestion column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildCanvassData requireOneByNotInterested(boolean $can_NotInterested) Return the first ChildCanvassData filtered by the can_NotInterested column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildCanvassData requireOneByWhyNotInterested(string $can_WhyNotInterested) Return the first ChildCanvassData filtered by the can_WhyNotInterested column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildCanvassData[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildCanvassData objects based on current ModelCriteria
 * @method     ChildCanvassData[]|ObjectCollection findById(int $can_ID) Return ChildCanvassData objects filtered by the can_ID column
 * @method     ChildCanvassData[]|ObjectCollection findByFamilyId(int $can_famID) Return ChildCanvassData objects filtered by the can_famID column
 * @method     ChildCanvassData[]|ObjectCollection findByCanvasser(int $can_Canvasser) Return ChildCanvassData objects filtered by the can_Canvasser column
 * @method     ChildCanvassData[]|ObjectCollection findByFyid(int $can_FYID) Return ChildCanvassData objects filtered by the can_FYID column
 * @method     ChildCanvassData[]|ObjectCollection findByDate(string $can_date) Return ChildCanvassData objects filtered by the can_date column
 * @method     ChildCanvassData[]|ObjectCollection findByPositive(string $can_Positive) Return ChildCanvassData objects filtered by the can_Positive column
 * @method     ChildCanvassData[]|ObjectCollection findByCritical(string $can_Critical) Return ChildCanvassData objects filtered by the can_Critical column
 * @method     ChildCanvassData[]|ObjectCollection findByInsightful(string $can_Insightful) Return ChildCanvassData objects filtered by the can_Insightful column
 * @method     ChildCanvassData[]|ObjectCollection findByFinancial(string $can_Financial) Return ChildCanvassData objects filtered by the can_Financial column
 * @method     ChildCanvassData[]|ObjectCollection findBySuggestion(string $can_Suggestion) Return ChildCanvassData objects filtered by the can_Suggestion column
 * @method     ChildCanvassData[]|ObjectCollection findByNotInterested(boolean $can_NotInterested) Return ChildCanvassData objects filtered by the can_NotInterested column
 * @method     ChildCanvassData[]|ObjectCollection findByWhyNotInterested(string $can_WhyNotInterested) Return ChildCanvassData objects filtered by the can_WhyNotInterested column
 * @method     ChildCanvassData[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class CanvassDataQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\CanvassDataQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\CanvassData', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildCanvassDataQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildCanvassDataQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildCanvassDataQuery) {
            return $criteria;
        }
        $query = new ChildCanvassDataQuery();
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
     * @return ChildCanvassData|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(CanvassDataTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (
            $this->formatter || $this->modelAlias || $this->with || $this->select
            || $this->selectColumns || $this->asColumns || $this->selectModifiers
            || $this->map || $this->having || $this->joins
        ) {
            return $this->findPkComplex($key, $con);
        }

        if ((null !== ($obj = CanvassDataTableMap::getInstanceFromPool(null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key)))) {
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
     * @return ChildCanvassData A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT can_ID, can_famID, can_Canvasser, can_FYID, can_date, can_Positive, can_Critical, can_Insightful, can_Financial, can_Suggestion, can_NotInterested, can_WhyNotInterested FROM canvassdata_can WHERE can_ID = :p0';
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
            /** @var ChildCanvassData $obj */
            $obj = new ChildCanvassData();
            $obj->hydrate($row);
            CanvassDataTableMap::addInstanceToPool($obj, null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key);
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
     * @return ChildCanvassData|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildCanvassDataQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(CanvassDataTableMap::COL_CAN_ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildCanvassDataQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(CanvassDataTableMap::COL_CAN_ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the can_ID column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE can_ID = 1234
     * $query->filterById(array(12, 34)); // WHERE can_ID IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE can_ID > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildCanvassDataQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(CanvassDataTableMap::COL_CAN_ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(CanvassDataTableMap::COL_CAN_ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CanvassDataTableMap::COL_CAN_ID, $id, $comparison);
    }

    /**
     * Filter the query on the can_famID column
     *
     * Example usage:
     * <code>
     * $query->filterByFamilyId(1234); // WHERE can_famID = 1234
     * $query->filterByFamilyId(array(12, 34)); // WHERE can_famID IN (12, 34)
     * $query->filterByFamilyId(array('min' => 12)); // WHERE can_famID > 12
     * </code>
     *
     * @param     mixed $familyId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildCanvassDataQuery The current query, for fluid interface
     */
    public function filterByFamilyId($familyId = null, $comparison = null)
    {
        if (is_array($familyId)) {
            $useMinMax = false;
            if (isset($familyId['min'])) {
                $this->addUsingAlias(CanvassDataTableMap::COL_CAN_FAMID, $familyId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($familyId['max'])) {
                $this->addUsingAlias(CanvassDataTableMap::COL_CAN_FAMID, $familyId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CanvassDataTableMap::COL_CAN_FAMID, $familyId, $comparison);
    }

    /**
     * Filter the query on the can_Canvasser column
     *
     * Example usage:
     * <code>
     * $query->filterByCanvasser(1234); // WHERE can_Canvasser = 1234
     * $query->filterByCanvasser(array(12, 34)); // WHERE can_Canvasser IN (12, 34)
     * $query->filterByCanvasser(array('min' => 12)); // WHERE can_Canvasser > 12
     * </code>
     *
     * @param     mixed $canvasser The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildCanvassDataQuery The current query, for fluid interface
     */
    public function filterByCanvasser($canvasser = null, $comparison = null)
    {
        if (is_array($canvasser)) {
            $useMinMax = false;
            if (isset($canvasser['min'])) {
                $this->addUsingAlias(CanvassDataTableMap::COL_CAN_CANVASSER, $canvasser['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($canvasser['max'])) {
                $this->addUsingAlias(CanvassDataTableMap::COL_CAN_CANVASSER, $canvasser['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CanvassDataTableMap::COL_CAN_CANVASSER, $canvasser, $comparison);
    }

    /**
     * Filter the query on the can_FYID column
     *
     * Example usage:
     * <code>
     * $query->filterByFyid(1234); // WHERE can_FYID = 1234
     * $query->filterByFyid(array(12, 34)); // WHERE can_FYID IN (12, 34)
     * $query->filterByFyid(array('min' => 12)); // WHERE can_FYID > 12
     * </code>
     *
     * @param     mixed $fyid The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildCanvassDataQuery The current query, for fluid interface
     */
    public function filterByFyid($fyid = null, $comparison = null)
    {
        if (is_array($fyid)) {
            $useMinMax = false;
            if (isset($fyid['min'])) {
                $this->addUsingAlias(CanvassDataTableMap::COL_CAN_FYID, $fyid['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($fyid['max'])) {
                $this->addUsingAlias(CanvassDataTableMap::COL_CAN_FYID, $fyid['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CanvassDataTableMap::COL_CAN_FYID, $fyid, $comparison);
    }

    /**
     * Filter the query on the can_date column
     *
     * Example usage:
     * <code>
     * $query->filterByDate('2011-03-14'); // WHERE can_date = '2011-03-14'
     * $query->filterByDate('now'); // WHERE can_date = '2011-03-14'
     * $query->filterByDate(array('max' => 'yesterday')); // WHERE can_date > '2011-03-13'
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
     * @return $this|ChildCanvassDataQuery The current query, for fluid interface
     */
    public function filterByDate($date = null, $comparison = null)
    {
        if (is_array($date)) {
            $useMinMax = false;
            if (isset($date['min'])) {
                $this->addUsingAlias(CanvassDataTableMap::COL_CAN_DATE, $date['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($date['max'])) {
                $this->addUsingAlias(CanvassDataTableMap::COL_CAN_DATE, $date['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CanvassDataTableMap::COL_CAN_DATE, $date, $comparison);
    }

    /**
     * Filter the query on the can_Positive column
     *
     * Example usage:
     * <code>
     * $query->filterByPositive('fooValue');   // WHERE can_Positive = 'fooValue'
     * $query->filterByPositive('%fooValue%', Criteria::LIKE); // WHERE can_Positive LIKE '%fooValue%'
     * </code>
     *
     * @param     string $positive The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildCanvassDataQuery The current query, for fluid interface
     */
    public function filterByPositive($positive = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($positive)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CanvassDataTableMap::COL_CAN_POSITIVE, $positive, $comparison);
    }

    /**
     * Filter the query on the can_Critical column
     *
     * Example usage:
     * <code>
     * $query->filterByCritical('fooValue');   // WHERE can_Critical = 'fooValue'
     * $query->filterByCritical('%fooValue%', Criteria::LIKE); // WHERE can_Critical LIKE '%fooValue%'
     * </code>
     *
     * @param     string $critical The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildCanvassDataQuery The current query, for fluid interface
     */
    public function filterByCritical($critical = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($critical)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CanvassDataTableMap::COL_CAN_CRITICAL, $critical, $comparison);
    }

    /**
     * Filter the query on the can_Insightful column
     *
     * Example usage:
     * <code>
     * $query->filterByInsightful('fooValue');   // WHERE can_Insightful = 'fooValue'
     * $query->filterByInsightful('%fooValue%', Criteria::LIKE); // WHERE can_Insightful LIKE '%fooValue%'
     * </code>
     *
     * @param     string $insightful The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildCanvassDataQuery The current query, for fluid interface
     */
    public function filterByInsightful($insightful = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($insightful)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CanvassDataTableMap::COL_CAN_INSIGHTFUL, $insightful, $comparison);
    }

    /**
     * Filter the query on the can_Financial column
     *
     * Example usage:
     * <code>
     * $query->filterByFinancial('fooValue');   // WHERE can_Financial = 'fooValue'
     * $query->filterByFinancial('%fooValue%', Criteria::LIKE); // WHERE can_Financial LIKE '%fooValue%'
     * </code>
     *
     * @param     string $financial The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildCanvassDataQuery The current query, for fluid interface
     */
    public function filterByFinancial($financial = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($financial)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CanvassDataTableMap::COL_CAN_FINANCIAL, $financial, $comparison);
    }

    /**
     * Filter the query on the can_Suggestion column
     *
     * Example usage:
     * <code>
     * $query->filterBySuggestion('fooValue');   // WHERE can_Suggestion = 'fooValue'
     * $query->filterBySuggestion('%fooValue%', Criteria::LIKE); // WHERE can_Suggestion LIKE '%fooValue%'
     * </code>
     *
     * @param     string $suggestion The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildCanvassDataQuery The current query, for fluid interface
     */
    public function filterBySuggestion($suggestion = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($suggestion)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CanvassDataTableMap::COL_CAN_SUGGESTION, $suggestion, $comparison);
    }

    /**
     * Filter the query on the can_NotInterested column
     *
     * Example usage:
     * <code>
     * $query->filterByNotInterested(true); // WHERE can_NotInterested = true
     * $query->filterByNotInterested('yes'); // WHERE can_NotInterested = true
     * </code>
     *
     * @param     boolean|string $notInterested The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildCanvassDataQuery The current query, for fluid interface
     */
    public function filterByNotInterested($notInterested = null, $comparison = null)
    {
        if (is_string($notInterested)) {
            $notInterested = in_array(strtolower($notInterested), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(CanvassDataTableMap::COL_CAN_NOTINTERESTED, $notInterested, $comparison);
    }

    /**
     * Filter the query on the can_WhyNotInterested column
     *
     * Example usage:
     * <code>
     * $query->filterByWhyNotInterested('fooValue');   // WHERE can_WhyNotInterested = 'fooValue'
     * $query->filterByWhyNotInterested('%fooValue%', Criteria::LIKE); // WHERE can_WhyNotInterested LIKE '%fooValue%'
     * </code>
     *
     * @param     string $whyNotInterested The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildCanvassDataQuery The current query, for fluid interface
     */
    public function filterByWhyNotInterested($whyNotInterested = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($whyNotInterested)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CanvassDataTableMap::COL_CAN_WHYNOTINTERESTED, $whyNotInterested, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildCanvassData $canvassData Object to remove from the list of results
     *
     * @return $this|ChildCanvassDataQuery The current query, for fluid interface
     */
    public function prune($canvassData = null)
    {
        if ($canvassData) {
            $this->addUsingAlias(CanvassDataTableMap::COL_CAN_ID, $canvassData->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the canvassdata_can table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(CanvassDataTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            CanvassDataTableMap::clearInstancePool();
            CanvassDataTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(CanvassDataTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(CanvassDataTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            CanvassDataTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            CanvassDataTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // CanvassDataQuery
