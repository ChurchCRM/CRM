<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\PersonVolunteerOpportunity as ChildPersonVolunteerOpportunity;
use ChurchCRM\PersonVolunteerOpportunityQuery as ChildPersonVolunteerOpportunityQuery;
use ChurchCRM\Map\PersonVolunteerOpportunityTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'person2volunteeropp_p2vo' table.
 *
 *
 *
 * @method     ChildPersonVolunteerOpportunityQuery orderById($order = Criteria::ASC) Order by the p2vo_ID column
 * @method     ChildPersonVolunteerOpportunityQuery orderByPersonId($order = Criteria::ASC) Order by the p2vo_per_ID column
 * @method     ChildPersonVolunteerOpportunityQuery orderByVolunteerOpportunityId($order = Criteria::ASC) Order by the p2vo_vol_ID column
 *
 * @method     ChildPersonVolunteerOpportunityQuery groupById() Group by the p2vo_ID column
 * @method     ChildPersonVolunteerOpportunityQuery groupByPersonId() Group by the p2vo_per_ID column
 * @method     ChildPersonVolunteerOpportunityQuery groupByVolunteerOpportunityId() Group by the p2vo_vol_ID column
 *
 * @method     ChildPersonVolunteerOpportunityQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildPersonVolunteerOpportunityQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildPersonVolunteerOpportunityQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildPersonVolunteerOpportunityQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildPersonVolunteerOpportunityQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildPersonVolunteerOpportunityQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildPersonVolunteerOpportunity findOne(ConnectionInterface $con = null) Return the first ChildPersonVolunteerOpportunity matching the query
 * @method     ChildPersonVolunteerOpportunity findOneOrCreate(ConnectionInterface $con = null) Return the first ChildPersonVolunteerOpportunity matching the query, or a new ChildPersonVolunteerOpportunity object populated from the query conditions when no match is found
 *
 * @method     ChildPersonVolunteerOpportunity findOneById(int $p2vo_ID) Return the first ChildPersonVolunteerOpportunity filtered by the p2vo_ID column
 * @method     ChildPersonVolunteerOpportunity findOneByPersonId(int $p2vo_per_ID) Return the first ChildPersonVolunteerOpportunity filtered by the p2vo_per_ID column
 * @method     ChildPersonVolunteerOpportunity findOneByVolunteerOpportunityId(int $p2vo_vol_ID) Return the first ChildPersonVolunteerOpportunity filtered by the p2vo_vol_ID column *

 * @method     ChildPersonVolunteerOpportunity requirePk($key, ConnectionInterface $con = null) Return the ChildPersonVolunteerOpportunity by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPersonVolunteerOpportunity requireOne(ConnectionInterface $con = null) Return the first ChildPersonVolunteerOpportunity matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildPersonVolunteerOpportunity requireOneById(int $p2vo_ID) Return the first ChildPersonVolunteerOpportunity filtered by the p2vo_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPersonVolunteerOpportunity requireOneByPersonId(int $p2vo_per_ID) Return the first ChildPersonVolunteerOpportunity filtered by the p2vo_per_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPersonVolunteerOpportunity requireOneByVolunteerOpportunityId(int $p2vo_vol_ID) Return the first ChildPersonVolunteerOpportunity filtered by the p2vo_vol_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildPersonVolunteerOpportunity[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildPersonVolunteerOpportunity objects based on current ModelCriteria
 * @method     ChildPersonVolunteerOpportunity[]|ObjectCollection findById(int $p2vo_ID) Return ChildPersonVolunteerOpportunity objects filtered by the p2vo_ID column
 * @method     ChildPersonVolunteerOpportunity[]|ObjectCollection findByPersonId(int $p2vo_per_ID) Return ChildPersonVolunteerOpportunity objects filtered by the p2vo_per_ID column
 * @method     ChildPersonVolunteerOpportunity[]|ObjectCollection findByVolunteerOpportunityId(int $p2vo_vol_ID) Return ChildPersonVolunteerOpportunity objects filtered by the p2vo_vol_ID column
 * @method     ChildPersonVolunteerOpportunity[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class PersonVolunteerOpportunityQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\PersonVolunteerOpportunityQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\PersonVolunteerOpportunity', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildPersonVolunteerOpportunityQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildPersonVolunteerOpportunityQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildPersonVolunteerOpportunityQuery) {
            return $criteria;
        }
        $query = new ChildPersonVolunteerOpportunityQuery();
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
     * @return ChildPersonVolunteerOpportunity|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(PersonVolunteerOpportunityTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (
            $this->formatter || $this->modelAlias || $this->with || $this->select
            || $this->selectColumns || $this->asColumns || $this->selectModifiers
            || $this->map || $this->having || $this->joins
        ) {
            return $this->findPkComplex($key, $con);
        }

        if ((null !== ($obj = PersonVolunteerOpportunityTableMap::getInstanceFromPool(null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key)))) {
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
     * @return ChildPersonVolunteerOpportunity A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT p2vo_ID, p2vo_per_ID, p2vo_vol_ID FROM person2volunteeropp_p2vo WHERE p2vo_ID = :p0';
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
            /** @var ChildPersonVolunteerOpportunity $obj */
            $obj = new ChildPersonVolunteerOpportunity();
            $obj->hydrate($row);
            PersonVolunteerOpportunityTableMap::addInstanceToPool($obj, null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key);
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
     * @return ChildPersonVolunteerOpportunity|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildPersonVolunteerOpportunityQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(PersonVolunteerOpportunityTableMap::COL_P2VO_ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildPersonVolunteerOpportunityQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(PersonVolunteerOpportunityTableMap::COL_P2VO_ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the p2vo_ID column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE p2vo_ID = 1234
     * $query->filterById(array(12, 34)); // WHERE p2vo_ID IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE p2vo_ID > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonVolunteerOpportunityQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(PersonVolunteerOpportunityTableMap::COL_P2VO_ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(PersonVolunteerOpportunityTableMap::COL_P2VO_ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PersonVolunteerOpportunityTableMap::COL_P2VO_ID, $id, $comparison);
    }

    /**
     * Filter the query on the p2vo_per_ID column
     *
     * Example usage:
     * <code>
     * $query->filterByPersonId(1234); // WHERE p2vo_per_ID = 1234
     * $query->filterByPersonId(array(12, 34)); // WHERE p2vo_per_ID IN (12, 34)
     * $query->filterByPersonId(array('min' => 12)); // WHERE p2vo_per_ID > 12
     * </code>
     *
     * @param     mixed $personId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonVolunteerOpportunityQuery The current query, for fluid interface
     */
    public function filterByPersonId($personId = null, $comparison = null)
    {
        if (is_array($personId)) {
            $useMinMax = false;
            if (isset($personId['min'])) {
                $this->addUsingAlias(PersonVolunteerOpportunityTableMap::COL_P2VO_PER_ID, $personId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($personId['max'])) {
                $this->addUsingAlias(PersonVolunteerOpportunityTableMap::COL_P2VO_PER_ID, $personId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PersonVolunteerOpportunityTableMap::COL_P2VO_PER_ID, $personId, $comparison);
    }

    /**
     * Filter the query on the p2vo_vol_ID column
     *
     * Example usage:
     * <code>
     * $query->filterByVolunteerOpportunityId(1234); // WHERE p2vo_vol_ID = 1234
     * $query->filterByVolunteerOpportunityId(array(12, 34)); // WHERE p2vo_vol_ID IN (12, 34)
     * $query->filterByVolunteerOpportunityId(array('min' => 12)); // WHERE p2vo_vol_ID > 12
     * </code>
     *
     * @param     mixed $volunteerOpportunityId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonVolunteerOpportunityQuery The current query, for fluid interface
     */
    public function filterByVolunteerOpportunityId($volunteerOpportunityId = null, $comparison = null)
    {
        if (is_array($volunteerOpportunityId)) {
            $useMinMax = false;
            if (isset($volunteerOpportunityId['min'])) {
                $this->addUsingAlias(PersonVolunteerOpportunityTableMap::COL_P2VO_VOL_ID, $volunteerOpportunityId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($volunteerOpportunityId['max'])) {
                $this->addUsingAlias(PersonVolunteerOpportunityTableMap::COL_P2VO_VOL_ID, $volunteerOpportunityId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PersonVolunteerOpportunityTableMap::COL_P2VO_VOL_ID, $volunteerOpportunityId, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildPersonVolunteerOpportunity $personVolunteerOpportunity Object to remove from the list of results
     *
     * @return $this|ChildPersonVolunteerOpportunityQuery The current query, for fluid interface
     */
    public function prune($personVolunteerOpportunity = null)
    {
        if ($personVolunteerOpportunity) {
            $this->addUsingAlias(PersonVolunteerOpportunityTableMap::COL_P2VO_ID, $personVolunteerOpportunity->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the person2volunteeropp_p2vo table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(PersonVolunteerOpportunityTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            PersonVolunteerOpportunityTableMap::clearInstancePool();
            PersonVolunteerOpportunityTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(PersonVolunteerOpportunityTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(PersonVolunteerOpportunityTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            PersonVolunteerOpportunityTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            PersonVolunteerOpportunityTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // PersonVolunteerOpportunityQuery
