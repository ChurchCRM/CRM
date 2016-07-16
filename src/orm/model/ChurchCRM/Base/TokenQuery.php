<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\Token as ChildToken;
use ChurchCRM\TokenQuery as ChildTokenQuery;
use ChurchCRM\Map\TokenTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'tokens' table.
 *
 *
 *
 * @method     ChildTokenQuery orderByguid($order = Criteria::ASC) Order by the guid column
 * @method     ChildTokenQuery orderByType($order = Criteria::ASC) Order by the type column
 * @method     ChildTokenQuery orderByDate($order = Criteria::ASC) Order by the active_until_date column
 * @method     ChildTokenQuery orderByremoteId($order = Criteria::ASC) Order by the remote_id column
 * @method     ChildTokenQuery orderByUseCount($order = Criteria::ASC) Order by the use_count column
 *
 * @method     ChildTokenQuery groupByguid() Group by the guid column
 * @method     ChildTokenQuery groupByType() Group by the type column
 * @method     ChildTokenQuery groupByDate() Group by the active_until_date column
 * @method     ChildTokenQuery groupByremoteId() Group by the remote_id column
 * @method     ChildTokenQuery groupByUseCount() Group by the use_count column
 *
 * @method     ChildTokenQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildTokenQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildTokenQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildTokenQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildTokenQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildTokenQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildToken findOne(ConnectionInterface $con = null) Return the first ChildToken matching the query
 * @method     ChildToken findOneOrCreate(ConnectionInterface $con = null) Return the first ChildToken matching the query, or a new ChildToken object populated from the query conditions when no match is found
 *
 * @method     ChildToken findOneByguid(string $guid) Return the first ChildToken filtered by the guid column
 * @method     ChildToken findOneByType(string $type) Return the first ChildToken filtered by the type column
 * @method     ChildToken findOneByDate(string $active_until_date) Return the first ChildToken filtered by the active_until_date column
 * @method     ChildToken findOneByremoteId(int $remote_id) Return the first ChildToken filtered by the remote_id column
 * @method     ChildToken findOneByUseCount(int $use_count) Return the first ChildToken filtered by the use_count column *

 * @method     ChildToken requirePk($key, ConnectionInterface $con = null) Return the ChildToken by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildToken requireOne(ConnectionInterface $con = null) Return the first ChildToken matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildToken requireOneByguid(string $guid) Return the first ChildToken filtered by the guid column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildToken requireOneByType(string $type) Return the first ChildToken filtered by the type column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildToken requireOneByDate(string $active_until_date) Return the first ChildToken filtered by the active_until_date column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildToken requireOneByremoteId(int $remote_id) Return the first ChildToken filtered by the remote_id column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildToken requireOneByUseCount(int $use_count) Return the first ChildToken filtered by the use_count column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildToken[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildToken objects based on current ModelCriteria
 * @method     ChildToken[]|ObjectCollection findByguid(string $guid) Return ChildToken objects filtered by the guid column
 * @method     ChildToken[]|ObjectCollection findByType(string $type) Return ChildToken objects filtered by the type column
 * @method     ChildToken[]|ObjectCollection findByDate(string $active_until_date) Return ChildToken objects filtered by the active_until_date column
 * @method     ChildToken[]|ObjectCollection findByremoteId(int $remote_id) Return ChildToken objects filtered by the remote_id column
 * @method     ChildToken[]|ObjectCollection findByUseCount(int $use_count) Return ChildToken objects filtered by the use_count column
 * @method     ChildToken[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class TokenQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\TokenQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\Token', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildTokenQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildTokenQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildTokenQuery) {
            return $criteria;
        }
        $query = new ChildTokenQuery();
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
     * @return ChildToken|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(TokenTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (
            $this->formatter || $this->modelAlias || $this->with || $this->select
            || $this->selectColumns || $this->asColumns || $this->selectModifiers
            || $this->map || $this->having || $this->joins
        ) {
            return $this->findPkComplex($key, $con);
        }

        if ((null !== ($obj = TokenTableMap::getInstanceFromPool(null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key)))) {
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
     * @return ChildToken A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT guid, type, active_until_date, remote_id, use_count FROM tokens WHERE guid = :p0';
        try {
            $stmt = $con->prepare($sql);
            $stmt->bindValue(':p0', $key, PDO::PARAM_STR);
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', $sql), 0, $e);
        }
        $obj = null;
        if ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
            /** @var ChildToken $obj */
            $obj = new ChildToken();
            $obj->hydrate($row);
            TokenTableMap::addInstanceToPool($obj, null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key);
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
     * @return ChildToken|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildTokenQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(TokenTableMap::COL_GUID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildTokenQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(TokenTableMap::COL_GUID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the guid column
     *
     * Example usage:
     * <code>
     * $query->filterByguid('fooValue');   // WHERE guid = 'fooValue'
     * $query->filterByguid('%fooValue%'); // WHERE guid LIKE '%fooValue%'
     * </code>
     *
     * @param     string $guid The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildTokenQuery The current query, for fluid interface
     */
    public function filterByguid($guid = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($guid)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(TokenTableMap::COL_GUID, $guid, $comparison);
    }

    /**
     * Filter the query on the type column
     *
     * Example usage:
     * <code>
     * $query->filterByType('fooValue');   // WHERE type = 'fooValue'
     * $query->filterByType('%fooValue%'); // WHERE type LIKE '%fooValue%'
     * </code>
     *
     * @param     string $type The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildTokenQuery The current query, for fluid interface
     */
    public function filterByType($type = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($type)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(TokenTableMap::COL_TYPE, $type, $comparison);
    }

    /**
     * Filter the query on the active_until_date column
     *
     * Example usage:
     * <code>
     * $query->filterByDate('2011-03-14'); // WHERE active_until_date = '2011-03-14'
     * $query->filterByDate('now'); // WHERE active_until_date = '2011-03-14'
     * $query->filterByDate(array('max' => 'yesterday')); // WHERE active_until_date > '2011-03-13'
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
     * @return $this|ChildTokenQuery The current query, for fluid interface
     */
    public function filterByDate($date = null, $comparison = null)
    {
        if (is_array($date)) {
            $useMinMax = false;
            if (isset($date['min'])) {
                $this->addUsingAlias(TokenTableMap::COL_ACTIVE_UNTIL_DATE, $date['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($date['max'])) {
                $this->addUsingAlias(TokenTableMap::COL_ACTIVE_UNTIL_DATE, $date['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(TokenTableMap::COL_ACTIVE_UNTIL_DATE, $date, $comparison);
    }

    /**
     * Filter the query on the remote_id column
     *
     * Example usage:
     * <code>
     * $query->filterByremoteId(1234); // WHERE remote_id = 1234
     * $query->filterByremoteId(array(12, 34)); // WHERE remote_id IN (12, 34)
     * $query->filterByremoteId(array('min' => 12)); // WHERE remote_id > 12
     * </code>
     *
     * @param     mixed $remoteId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildTokenQuery The current query, for fluid interface
     */
    public function filterByremoteId($remoteId = null, $comparison = null)
    {
        if (is_array($remoteId)) {
            $useMinMax = false;
            if (isset($remoteId['min'])) {
                $this->addUsingAlias(TokenTableMap::COL_REMOTE_ID, $remoteId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($remoteId['max'])) {
                $this->addUsingAlias(TokenTableMap::COL_REMOTE_ID, $remoteId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(TokenTableMap::COL_REMOTE_ID, $remoteId, $comparison);
    }

    /**
     * Filter the query on the use_count column
     *
     * Example usage:
     * <code>
     * $query->filterByUseCount(1234); // WHERE use_count = 1234
     * $query->filterByUseCount(array(12, 34)); // WHERE use_count IN (12, 34)
     * $query->filterByUseCount(array('min' => 12)); // WHERE use_count > 12
     * </code>
     *
     * @param     mixed $useCount The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildTokenQuery The current query, for fluid interface
     */
    public function filterByUseCount($useCount = null, $comparison = null)
    {
        if (is_array($useCount)) {
            $useMinMax = false;
            if (isset($useCount['min'])) {
                $this->addUsingAlias(TokenTableMap::COL_USE_COUNT, $useCount['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($useCount['max'])) {
                $this->addUsingAlias(TokenTableMap::COL_USE_COUNT, $useCount['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(TokenTableMap::COL_USE_COUNT, $useCount, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildToken $token Object to remove from the list of results
     *
     * @return $this|ChildTokenQuery The current query, for fluid interface
     */
    public function prune($token = null)
    {
        if ($token) {
            $this->addUsingAlias(TokenTableMap::COL_GUID, $token->getguid(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the tokens table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(TokenTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            TokenTableMap::clearInstancePool();
            TokenTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(TokenTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(TokenTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            TokenTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            TokenTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // TokenQuery
