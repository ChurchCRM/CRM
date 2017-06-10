<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\VolunteerOpportunity as ChildVolunteerOpportunity;
use ChurchCRM\VolunteerOpportunityQuery as ChildVolunteerOpportunityQuery;
use ChurchCRM\Map\VolunteerOpportunityTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'volunteeropportunity_vol' table.
 *
 *
 *
 * @method     ChildVolunteerOpportunityQuery orderById($order = Criteria::ASC) Order by the vol_ID column
 * @method     ChildVolunteerOpportunityQuery orderByOrder($order = Criteria::ASC) Order by the vol_Order column
 * @method     ChildVolunteerOpportunityQuery orderByActive($order = Criteria::ASC) Order by the vol_Active column
 * @method     ChildVolunteerOpportunityQuery orderByName($order = Criteria::ASC) Order by the vol_Name column
 * @method     ChildVolunteerOpportunityQuery orderByDescription($order = Criteria::ASC) Order by the vol_Description column
 *
 * @method     ChildVolunteerOpportunityQuery groupById() Group by the vol_ID column
 * @method     ChildVolunteerOpportunityQuery groupByOrder() Group by the vol_Order column
 * @method     ChildVolunteerOpportunityQuery groupByActive() Group by the vol_Active column
 * @method     ChildVolunteerOpportunityQuery groupByName() Group by the vol_Name column
 * @method     ChildVolunteerOpportunityQuery groupByDescription() Group by the vol_Description column
 *
 * @method     ChildVolunteerOpportunityQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildVolunteerOpportunityQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildVolunteerOpportunityQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildVolunteerOpportunityQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildVolunteerOpportunityQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildVolunteerOpportunityQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildVolunteerOpportunity findOne(ConnectionInterface $con = null) Return the first ChildVolunteerOpportunity matching the query
 * @method     ChildVolunteerOpportunity findOneOrCreate(ConnectionInterface $con = null) Return the first ChildVolunteerOpportunity matching the query, or a new ChildVolunteerOpportunity object populated from the query conditions when no match is found
 *
 * @method     ChildVolunteerOpportunity findOneById(int $vol_ID) Return the first ChildVolunteerOpportunity filtered by the vol_ID column
 * @method     ChildVolunteerOpportunity findOneByOrder(int $vol_Order) Return the first ChildVolunteerOpportunity filtered by the vol_Order column
 * @method     ChildVolunteerOpportunity findOneByActive(string $vol_Active) Return the first ChildVolunteerOpportunity filtered by the vol_Active column
 * @method     ChildVolunteerOpportunity findOneByName(string $vol_Name) Return the first ChildVolunteerOpportunity filtered by the vol_Name column
 * @method     ChildVolunteerOpportunity findOneByDescription(string $vol_Description) Return the first ChildVolunteerOpportunity filtered by the vol_Description column *

 * @method     ChildVolunteerOpportunity requirePk($key, ConnectionInterface $con = null) Return the ChildVolunteerOpportunity by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildVolunteerOpportunity requireOne(ConnectionInterface $con = null) Return the first ChildVolunteerOpportunity matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildVolunteerOpportunity requireOneById(int $vol_ID) Return the first ChildVolunteerOpportunity filtered by the vol_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildVolunteerOpportunity requireOneByOrder(int $vol_Order) Return the first ChildVolunteerOpportunity filtered by the vol_Order column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildVolunteerOpportunity requireOneByActive(string $vol_Active) Return the first ChildVolunteerOpportunity filtered by the vol_Active column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildVolunteerOpportunity requireOneByName(string $vol_Name) Return the first ChildVolunteerOpportunity filtered by the vol_Name column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildVolunteerOpportunity requireOneByDescription(string $vol_Description) Return the first ChildVolunteerOpportunity filtered by the vol_Description column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildVolunteerOpportunity[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildVolunteerOpportunity objects based on current ModelCriteria
 * @method     ChildVolunteerOpportunity[]|ObjectCollection findById(int $vol_ID) Return ChildVolunteerOpportunity objects filtered by the vol_ID column
 * @method     ChildVolunteerOpportunity[]|ObjectCollection findByOrder(int $vol_Order) Return ChildVolunteerOpportunity objects filtered by the vol_Order column
 * @method     ChildVolunteerOpportunity[]|ObjectCollection findByActive(string $vol_Active) Return ChildVolunteerOpportunity objects filtered by the vol_Active column
 * @method     ChildVolunteerOpportunity[]|ObjectCollection findByName(string $vol_Name) Return ChildVolunteerOpportunity objects filtered by the vol_Name column
 * @method     ChildVolunteerOpportunity[]|ObjectCollection findByDescription(string $vol_Description) Return ChildVolunteerOpportunity objects filtered by the vol_Description column
 * @method     ChildVolunteerOpportunity[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class VolunteerOpportunityQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\VolunteerOpportunityQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\VolunteerOpportunity', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildVolunteerOpportunityQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildVolunteerOpportunityQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildVolunteerOpportunityQuery) {
            return $criteria;
        }
        $query = new ChildVolunteerOpportunityQuery();
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
     * @return ChildVolunteerOpportunity|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(VolunteerOpportunityTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (
            $this->formatter || $this->modelAlias || $this->with || $this->select
            || $this->selectColumns || $this->asColumns || $this->selectModifiers
            || $this->map || $this->having || $this->joins
        ) {
            return $this->findPkComplex($key, $con);
        }

        if ((null !== ($obj = VolunteerOpportunityTableMap::getInstanceFromPool(null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key)))) {
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
     * @return ChildVolunteerOpportunity A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT vol_ID, vol_Order, vol_Active, vol_Name, vol_Description FROM volunteeropportunity_vol WHERE vol_ID = :p0';
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
            /** @var ChildVolunteerOpportunity $obj */
            $obj = new ChildVolunteerOpportunity();
            $obj->hydrate($row);
            VolunteerOpportunityTableMap::addInstanceToPool($obj, null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key);
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
     * @return ChildVolunteerOpportunity|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildVolunteerOpportunityQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(VolunteerOpportunityTableMap::COL_VOL_ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildVolunteerOpportunityQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(VolunteerOpportunityTableMap::COL_VOL_ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the vol_ID column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE vol_ID = 1234
     * $query->filterById(array(12, 34)); // WHERE vol_ID IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE vol_ID > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildVolunteerOpportunityQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(VolunteerOpportunityTableMap::COL_VOL_ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(VolunteerOpportunityTableMap::COL_VOL_ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(VolunteerOpportunityTableMap::COL_VOL_ID, $id, $comparison);
    }

    /**
     * Filter the query on the vol_Order column
     *
     * Example usage:
     * <code>
     * $query->filterByOrder(1234); // WHERE vol_Order = 1234
     * $query->filterByOrder(array(12, 34)); // WHERE vol_Order IN (12, 34)
     * $query->filterByOrder(array('min' => 12)); // WHERE vol_Order > 12
     * </code>
     *
     * @param     mixed $order The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildVolunteerOpportunityQuery The current query, for fluid interface
     */
    public function filterByOrder($order = null, $comparison = null)
    {
        if (is_array($order)) {
            $useMinMax = false;
            if (isset($order['min'])) {
                $this->addUsingAlias(VolunteerOpportunityTableMap::COL_VOL_ORDER, $order['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($order['max'])) {
                $this->addUsingAlias(VolunteerOpportunityTableMap::COL_VOL_ORDER, $order['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(VolunteerOpportunityTableMap::COL_VOL_ORDER, $order, $comparison);
    }

    /**
     * Filter the query on the vol_Active column
     *
     * Example usage:
     * <code>
     * $query->filterByActive('fooValue');   // WHERE vol_Active = 'fooValue'
     * $query->filterByActive('%fooValue%', Criteria::LIKE); // WHERE vol_Active LIKE '%fooValue%'
     * </code>
     *
     * @param     string $active The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildVolunteerOpportunityQuery The current query, for fluid interface
     */
    public function filterByActive($active = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($active)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(VolunteerOpportunityTableMap::COL_VOL_ACTIVE, $active, $comparison);
    }

    /**
     * Filter the query on the vol_Name column
     *
     * Example usage:
     * <code>
     * $query->filterByName('fooValue');   // WHERE vol_Name = 'fooValue'
     * $query->filterByName('%fooValue%', Criteria::LIKE); // WHERE vol_Name LIKE '%fooValue%'
     * </code>
     *
     * @param     string $name The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildVolunteerOpportunityQuery The current query, for fluid interface
     */
    public function filterByName($name = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($name)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(VolunteerOpportunityTableMap::COL_VOL_NAME, $name, $comparison);
    }

    /**
     * Filter the query on the vol_Description column
     *
     * Example usage:
     * <code>
     * $query->filterByDescription('fooValue');   // WHERE vol_Description = 'fooValue'
     * $query->filterByDescription('%fooValue%', Criteria::LIKE); // WHERE vol_Description LIKE '%fooValue%'
     * </code>
     *
     * @param     string $description The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildVolunteerOpportunityQuery The current query, for fluid interface
     */
    public function filterByDescription($description = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($description)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(VolunteerOpportunityTableMap::COL_VOL_DESCRIPTION, $description, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildVolunteerOpportunity $volunteerOpportunity Object to remove from the list of results
     *
     * @return $this|ChildVolunteerOpportunityQuery The current query, for fluid interface
     */
    public function prune($volunteerOpportunity = null)
    {
        if ($volunteerOpportunity) {
            $this->addUsingAlias(VolunteerOpportunityTableMap::COL_VOL_ID, $volunteerOpportunity->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the volunteeropportunity_vol table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(VolunteerOpportunityTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            VolunteerOpportunityTableMap::clearInstancePool();
            VolunteerOpportunityTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(VolunteerOpportunityTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(VolunteerOpportunityTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            VolunteerOpportunityTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            VolunteerOpportunityTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // VolunteerOpportunityQuery
