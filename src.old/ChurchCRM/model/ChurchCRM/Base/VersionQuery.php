<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\Version as ChildVersion;
use ChurchCRM\VersionQuery as ChildVersionQuery;
use ChurchCRM\Map\VersionTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'version_ver' table.
 *
 *
 *
 * @method     ChildVersionQuery orderById($order = Criteria::ASC) Order by the ver_ID column
 * @method     ChildVersionQuery orderByVersion($order = Criteria::ASC) Order by the ver_version column
 * @method     ChildVersionQuery orderByUpdateStart($order = Criteria::ASC) Order by the ver_update_start column
 * @method     ChildVersionQuery orderByUpdateEnd($order = Criteria::ASC) Order by the ver_update_end column
 *
 * @method     ChildVersionQuery groupById() Group by the ver_ID column
 * @method     ChildVersionQuery groupByVersion() Group by the ver_version column
 * @method     ChildVersionQuery groupByUpdateStart() Group by the ver_update_start column
 * @method     ChildVersionQuery groupByUpdateEnd() Group by the ver_update_end column
 *
 * @method     ChildVersionQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildVersionQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildVersionQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildVersionQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildVersionQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildVersionQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildVersion findOne(ConnectionInterface $con = null) Return the first ChildVersion matching the query
 * @method     ChildVersion findOneOrCreate(ConnectionInterface $con = null) Return the first ChildVersion matching the query, or a new ChildVersion object populated from the query conditions when no match is found
 *
 * @method     ChildVersion findOneById(int $ver_ID) Return the first ChildVersion filtered by the ver_ID column
 * @method     ChildVersion findOneByVersion(string $ver_version) Return the first ChildVersion filtered by the ver_version column
 * @method     ChildVersion findOneByUpdateStart(string $ver_update_start) Return the first ChildVersion filtered by the ver_update_start column
 * @method     ChildVersion findOneByUpdateEnd(string $ver_update_end) Return the first ChildVersion filtered by the ver_update_end column *

 * @method     ChildVersion requirePk($key, ConnectionInterface $con = null) Return the ChildVersion by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildVersion requireOne(ConnectionInterface $con = null) Return the first ChildVersion matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildVersion requireOneById(int $ver_ID) Return the first ChildVersion filtered by the ver_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildVersion requireOneByVersion(string $ver_version) Return the first ChildVersion filtered by the ver_version column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildVersion requireOneByUpdateStart(string $ver_update_start) Return the first ChildVersion filtered by the ver_update_start column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildVersion requireOneByUpdateEnd(string $ver_update_end) Return the first ChildVersion filtered by the ver_update_end column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildVersion[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildVersion objects based on current ModelCriteria
 * @method     ChildVersion[]|ObjectCollection findById(int $ver_ID) Return ChildVersion objects filtered by the ver_ID column
 * @method     ChildVersion[]|ObjectCollection findByVersion(string $ver_version) Return ChildVersion objects filtered by the ver_version column
 * @method     ChildVersion[]|ObjectCollection findByUpdateStart(string $ver_update_start) Return ChildVersion objects filtered by the ver_update_start column
 * @method     ChildVersion[]|ObjectCollection findByUpdateEnd(string $ver_update_end) Return ChildVersion objects filtered by the ver_update_end column
 * @method     ChildVersion[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class VersionQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\VersionQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\Version', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildVersionQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildVersionQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildVersionQuery) {
            return $criteria;
        }
        $query = new ChildVersionQuery();
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
     * @return ChildVersion|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(VersionTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (
            $this->formatter || $this->modelAlias || $this->with || $this->select
            || $this->selectColumns || $this->asColumns || $this->selectModifiers
            || $this->map || $this->having || $this->joins
        ) {
            return $this->findPkComplex($key, $con);
        }

        if ((null !== ($obj = VersionTableMap::getInstanceFromPool(null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key)))) {
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
     * @return ChildVersion A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT ver_ID, ver_version, ver_update_start, ver_update_end FROM version_ver WHERE ver_ID = :p0';
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
            /** @var ChildVersion $obj */
            $obj = new ChildVersion();
            $obj->hydrate($row);
            VersionTableMap::addInstanceToPool($obj, null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key);
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
     * @return ChildVersion|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildVersionQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(VersionTableMap::COL_VER_ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildVersionQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(VersionTableMap::COL_VER_ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the ver_ID column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE ver_ID = 1234
     * $query->filterById(array(12, 34)); // WHERE ver_ID IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE ver_ID > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildVersionQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(VersionTableMap::COL_VER_ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(VersionTableMap::COL_VER_ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(VersionTableMap::COL_VER_ID, $id, $comparison);
    }

    /**
     * Filter the query on the ver_version column
     *
     * Example usage:
     * <code>
     * $query->filterByVersion('fooValue');   // WHERE ver_version = 'fooValue'
     * $query->filterByVersion('%fooValue%', Criteria::LIKE); // WHERE ver_version LIKE '%fooValue%'
     * </code>
     *
     * @param     string $version The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildVersionQuery The current query, for fluid interface
     */
    public function filterByVersion($version = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($version)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(VersionTableMap::COL_VER_VERSION, $version, $comparison);
    }

    /**
     * Filter the query on the ver_update_start column
     *
     * Example usage:
     * <code>
     * $query->filterByUpdateStart('2011-03-14'); // WHERE ver_update_start = '2011-03-14'
     * $query->filterByUpdateStart('now'); // WHERE ver_update_start = '2011-03-14'
     * $query->filterByUpdateStart(array('max' => 'yesterday')); // WHERE ver_update_start > '2011-03-13'
     * </code>
     *
     * @param     mixed $updateStart The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildVersionQuery The current query, for fluid interface
     */
    public function filterByUpdateStart($updateStart = null, $comparison = null)
    {
        if (is_array($updateStart)) {
            $useMinMax = false;
            if (isset($updateStart['min'])) {
                $this->addUsingAlias(VersionTableMap::COL_VER_UPDATE_START, $updateStart['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updateStart['max'])) {
                $this->addUsingAlias(VersionTableMap::COL_VER_UPDATE_START, $updateStart['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(VersionTableMap::COL_VER_UPDATE_START, $updateStart, $comparison);
    }

    /**
     * Filter the query on the ver_update_end column
     *
     * Example usage:
     * <code>
     * $query->filterByUpdateEnd('2011-03-14'); // WHERE ver_update_end = '2011-03-14'
     * $query->filterByUpdateEnd('now'); // WHERE ver_update_end = '2011-03-14'
     * $query->filterByUpdateEnd(array('max' => 'yesterday')); // WHERE ver_update_end > '2011-03-13'
     * </code>
     *
     * @param     mixed $updateEnd The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildVersionQuery The current query, for fluid interface
     */
    public function filterByUpdateEnd($updateEnd = null, $comparison = null)
    {
        if (is_array($updateEnd)) {
            $useMinMax = false;
            if (isset($updateEnd['min'])) {
                $this->addUsingAlias(VersionTableMap::COL_VER_UPDATE_END, $updateEnd['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updateEnd['max'])) {
                $this->addUsingAlias(VersionTableMap::COL_VER_UPDATE_END, $updateEnd['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(VersionTableMap::COL_VER_UPDATE_END, $updateEnd, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildVersion $version Object to remove from the list of results
     *
     * @return $this|ChildVersionQuery The current query, for fluid interface
     */
    public function prune($version = null)
    {
        if ($version) {
            $this->addUsingAlias(VersionTableMap::COL_VER_ID, $version->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the version_ver table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(VersionTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            VersionTableMap::clearInstancePool();
            VersionTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(VersionTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(VersionTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            VersionTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            VersionTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // VersionQuery
