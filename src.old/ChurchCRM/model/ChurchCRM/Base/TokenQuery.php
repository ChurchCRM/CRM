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
 * @method     ChildTokenQuery orderByToken($order = Criteria::ASC) Order by the token column
 * @method     ChildTokenQuery orderByType($order = Criteria::ASC) Order by the type column
 * @method     ChildTokenQuery orderByValidUntilDate($order = Criteria::ASC) Order by the valid_until_date column
 * @method     ChildTokenQuery orderByReferenceId($order = Criteria::ASC) Order by the reference_id column
 * @method     ChildTokenQuery orderByRemainingUses($order = Criteria::ASC) Order by the remainingUses column
 *
 * @method     ChildTokenQuery groupByToken() Group by the token column
 * @method     ChildTokenQuery groupByType() Group by the type column
 * @method     ChildTokenQuery groupByValidUntilDate() Group by the valid_until_date column
 * @method     ChildTokenQuery groupByReferenceId() Group by the reference_id column
 * @method     ChildTokenQuery groupByRemainingUses() Group by the remainingUses column
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
 * @method     ChildToken findOneByToken(string $token) Return the first ChildToken filtered by the token column
 * @method     ChildToken findOneByType(string $type) Return the first ChildToken filtered by the type column
 * @method     ChildToken findOneByValidUntilDate(string $valid_until_date) Return the first ChildToken filtered by the valid_until_date column
 * @method     ChildToken findOneByReferenceId(int $reference_id) Return the first ChildToken filtered by the reference_id column
 * @method     ChildToken findOneByRemainingUses(int $remainingUses) Return the first ChildToken filtered by the remainingUses column *

 * @method     ChildToken requirePk($key, ConnectionInterface $con = null) Return the ChildToken by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildToken requireOne(ConnectionInterface $con = null) Return the first ChildToken matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildToken requireOneByToken(string $token) Return the first ChildToken filtered by the token column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildToken requireOneByType(string $type) Return the first ChildToken filtered by the type column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildToken requireOneByValidUntilDate(string $valid_until_date) Return the first ChildToken filtered by the valid_until_date column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildToken requireOneByReferenceId(int $reference_id) Return the first ChildToken filtered by the reference_id column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildToken requireOneByRemainingUses(int $remainingUses) Return the first ChildToken filtered by the remainingUses column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildToken[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildToken objects based on current ModelCriteria
 * @method     ChildToken[]|ObjectCollection findByToken(string $token) Return ChildToken objects filtered by the token column
 * @method     ChildToken[]|ObjectCollection findByType(string $type) Return ChildToken objects filtered by the type column
 * @method     ChildToken[]|ObjectCollection findByValidUntilDate(string $valid_until_date) Return ChildToken objects filtered by the valid_until_date column
 * @method     ChildToken[]|ObjectCollection findByReferenceId(int $reference_id) Return ChildToken objects filtered by the reference_id column
 * @method     ChildToken[]|ObjectCollection findByRemainingUses(int $remainingUses) Return ChildToken objects filtered by the remainingUses column
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
        $sql = 'SELECT token, type, valid_until_date, reference_id, remainingUses FROM tokens WHERE token = :p0';
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

        return $this->addUsingAlias(TokenTableMap::COL_TOKEN, $key, Criteria::EQUAL);
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

        return $this->addUsingAlias(TokenTableMap::COL_TOKEN, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the token column
     *
     * Example usage:
     * <code>
     * $query->filterByToken('fooValue');   // WHERE token = 'fooValue'
     * $query->filterByToken('%fooValue%', Criteria::LIKE); // WHERE token LIKE '%fooValue%'
     * </code>
     *
     * @param     string $token The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildTokenQuery The current query, for fluid interface
     */
    public function filterByToken($token = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($token)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(TokenTableMap::COL_TOKEN, $token, $comparison);
    }

    /**
     * Filter the query on the type column
     *
     * Example usage:
     * <code>
     * $query->filterByType('fooValue');   // WHERE type = 'fooValue'
     * $query->filterByType('%fooValue%', Criteria::LIKE); // WHERE type LIKE '%fooValue%'
     * </code>
     *
     * @param     string $type The value to use as filter.
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
     * Filter the query on the valid_until_date column
     *
     * Example usage:
     * <code>
     * $query->filterByValidUntilDate('2011-03-14'); // WHERE valid_until_date = '2011-03-14'
     * $query->filterByValidUntilDate('now'); // WHERE valid_until_date = '2011-03-14'
     * $query->filterByValidUntilDate(array('max' => 'yesterday')); // WHERE valid_until_date > '2011-03-13'
     * </code>
     *
     * @param     mixed $validUntilDate The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildTokenQuery The current query, for fluid interface
     */
    public function filterByValidUntilDate($validUntilDate = null, $comparison = null)
    {
        if (is_array($validUntilDate)) {
            $useMinMax = false;
            if (isset($validUntilDate['min'])) {
                $this->addUsingAlias(TokenTableMap::COL_VALID_UNTIL_DATE, $validUntilDate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($validUntilDate['max'])) {
                $this->addUsingAlias(TokenTableMap::COL_VALID_UNTIL_DATE, $validUntilDate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(TokenTableMap::COL_VALID_UNTIL_DATE, $validUntilDate, $comparison);
    }

    /**
     * Filter the query on the reference_id column
     *
     * Example usage:
     * <code>
     * $query->filterByReferenceId(1234); // WHERE reference_id = 1234
     * $query->filterByReferenceId(array(12, 34)); // WHERE reference_id IN (12, 34)
     * $query->filterByReferenceId(array('min' => 12)); // WHERE reference_id > 12
     * </code>
     *
     * @param     mixed $referenceId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildTokenQuery The current query, for fluid interface
     */
    public function filterByReferenceId($referenceId = null, $comparison = null)
    {
        if (is_array($referenceId)) {
            $useMinMax = false;
            if (isset($referenceId['min'])) {
                $this->addUsingAlias(TokenTableMap::COL_REFERENCE_ID, $referenceId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($referenceId['max'])) {
                $this->addUsingAlias(TokenTableMap::COL_REFERENCE_ID, $referenceId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(TokenTableMap::COL_REFERENCE_ID, $referenceId, $comparison);
    }

    /**
     * Filter the query on the remainingUses column
     *
     * Example usage:
     * <code>
     * $query->filterByRemainingUses(1234); // WHERE remainingUses = 1234
     * $query->filterByRemainingUses(array(12, 34)); // WHERE remainingUses IN (12, 34)
     * $query->filterByRemainingUses(array('min' => 12)); // WHERE remainingUses > 12
     * </code>
     *
     * @param     mixed $remainingUses The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildTokenQuery The current query, for fluid interface
     */
    public function filterByRemainingUses($remainingUses = null, $comparison = null)
    {
        if (is_array($remainingUses)) {
            $useMinMax = false;
            if (isset($remainingUses['min'])) {
                $this->addUsingAlias(TokenTableMap::COL_REMAININGUSES, $remainingUses['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($remainingUses['max'])) {
                $this->addUsingAlias(TokenTableMap::COL_REMAININGUSES, $remainingUses['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(TokenTableMap::COL_REMAININGUSES, $remainingUses, $comparison);
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
            $this->addUsingAlias(TokenTableMap::COL_TOKEN, $token->getToken(), Criteria::NOT_EQUAL);
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
