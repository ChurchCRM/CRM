<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\PledgeDenomination as ChildPledgeDenomination;
use ChurchCRM\PledgeDenominationQuery as ChildPledgeDenominationQuery;
use ChurchCRM\Map\PledgeDenominationTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'pledge_denominations_pdem' table.
 *
 *
 *
 * @method     ChildPledgeDenominationQuery orderByPdemPdemid($order = Criteria::ASC) Order by the pdem_pdemID column
 * @method     ChildPledgeDenominationQuery orderByPdemPlgGroupkey($order = Criteria::ASC) Order by the pdem_plg_GroupKey column
 * @method     ChildPledgeDenominationQuery orderByPlgDepid($order = Criteria::ASC) Order by the plg_depID column
 * @method     ChildPledgeDenominationQuery orderByPdemDenominationid($order = Criteria::ASC) Order by the pdem_denominationID column
 * @method     ChildPledgeDenominationQuery orderByPdemDenominationquantity($order = Criteria::ASC) Order by the pdem_denominationQuantity column
 *
 * @method     ChildPledgeDenominationQuery groupByPdemPdemid() Group by the pdem_pdemID column
 * @method     ChildPledgeDenominationQuery groupByPdemPlgGroupkey() Group by the pdem_plg_GroupKey column
 * @method     ChildPledgeDenominationQuery groupByPlgDepid() Group by the plg_depID column
 * @method     ChildPledgeDenominationQuery groupByPdemDenominationid() Group by the pdem_denominationID column
 * @method     ChildPledgeDenominationQuery groupByPdemDenominationquantity() Group by the pdem_denominationQuantity column
 *
 * @method     ChildPledgeDenominationQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildPledgeDenominationQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildPledgeDenominationQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildPledgeDenominationQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildPledgeDenominationQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildPledgeDenominationQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildPledgeDenomination findOne(ConnectionInterface $con = null) Return the first ChildPledgeDenomination matching the query
 * @method     ChildPledgeDenomination findOneOrCreate(ConnectionInterface $con = null) Return the first ChildPledgeDenomination matching the query, or a new ChildPledgeDenomination object populated from the query conditions when no match is found
 *
 * @method     ChildPledgeDenomination findOneByPdemPdemid(int $pdem_pdemID) Return the first ChildPledgeDenomination filtered by the pdem_pdemID column
 * @method     ChildPledgeDenomination findOneByPdemPlgGroupkey(string $pdem_plg_GroupKey) Return the first ChildPledgeDenomination filtered by the pdem_plg_GroupKey column
 * @method     ChildPledgeDenomination findOneByPlgDepid(int $plg_depID) Return the first ChildPledgeDenomination filtered by the plg_depID column
 * @method     ChildPledgeDenomination findOneByPdemDenominationid(string $pdem_denominationID) Return the first ChildPledgeDenomination filtered by the pdem_denominationID column
 * @method     ChildPledgeDenomination findOneByPdemDenominationquantity(int $pdem_denominationQuantity) Return the first ChildPledgeDenomination filtered by the pdem_denominationQuantity column *

 * @method     ChildPledgeDenomination requirePk($key, ConnectionInterface $con = null) Return the ChildPledgeDenomination by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPledgeDenomination requireOne(ConnectionInterface $con = null) Return the first ChildPledgeDenomination matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildPledgeDenomination requireOneByPdemPdemid(int $pdem_pdemID) Return the first ChildPledgeDenomination filtered by the pdem_pdemID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPledgeDenomination requireOneByPdemPlgGroupkey(string $pdem_plg_GroupKey) Return the first ChildPledgeDenomination filtered by the pdem_plg_GroupKey column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPledgeDenomination requireOneByPlgDepid(int $plg_depID) Return the first ChildPledgeDenomination filtered by the plg_depID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPledgeDenomination requireOneByPdemDenominationid(string $pdem_denominationID) Return the first ChildPledgeDenomination filtered by the pdem_denominationID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPledgeDenomination requireOneByPdemDenominationquantity(int $pdem_denominationQuantity) Return the first ChildPledgeDenomination filtered by the pdem_denominationQuantity column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildPledgeDenomination[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildPledgeDenomination objects based on current ModelCriteria
 * @method     ChildPledgeDenomination[]|ObjectCollection findByPdemPdemid(int $pdem_pdemID) Return ChildPledgeDenomination objects filtered by the pdem_pdemID column
 * @method     ChildPledgeDenomination[]|ObjectCollection findByPdemPlgGroupkey(string $pdem_plg_GroupKey) Return ChildPledgeDenomination objects filtered by the pdem_plg_GroupKey column
 * @method     ChildPledgeDenomination[]|ObjectCollection findByPlgDepid(int $plg_depID) Return ChildPledgeDenomination objects filtered by the plg_depID column
 * @method     ChildPledgeDenomination[]|ObjectCollection findByPdemDenominationid(string $pdem_denominationID) Return ChildPledgeDenomination objects filtered by the pdem_denominationID column
 * @method     ChildPledgeDenomination[]|ObjectCollection findByPdemDenominationquantity(int $pdem_denominationQuantity) Return ChildPledgeDenomination objects filtered by the pdem_denominationQuantity column
 * @method     ChildPledgeDenomination[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class PledgeDenominationQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\PledgeDenominationQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\PledgeDenomination', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildPledgeDenominationQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildPledgeDenominationQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildPledgeDenominationQuery) {
            return $criteria;
        }
        $query = new ChildPledgeDenominationQuery();
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
     * @return ChildPledgeDenomination|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(PledgeDenominationTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (
            $this->formatter || $this->modelAlias || $this->with || $this->select
            || $this->selectColumns || $this->asColumns || $this->selectModifiers
            || $this->map || $this->having || $this->joins
        ) {
            return $this->findPkComplex($key, $con);
        }

        if ((null !== ($obj = PledgeDenominationTableMap::getInstanceFromPool(null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key)))) {
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
     * @return ChildPledgeDenomination A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT pdem_pdemID, pdem_plg_GroupKey, plg_depID, pdem_denominationID, pdem_denominationQuantity FROM pledge_denominations_pdem WHERE pdem_pdemID = :p0';
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
            /** @var ChildPledgeDenomination $obj */
            $obj = new ChildPledgeDenomination();
            $obj->hydrate($row);
            PledgeDenominationTableMap::addInstanceToPool($obj, null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key);
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
     * @return ChildPledgeDenomination|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildPledgeDenominationQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(PledgeDenominationTableMap::COL_PDEM_PDEMID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildPledgeDenominationQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(PledgeDenominationTableMap::COL_PDEM_PDEMID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the pdem_pdemID column
     *
     * Example usage:
     * <code>
     * $query->filterByPdemPdemid(1234); // WHERE pdem_pdemID = 1234
     * $query->filterByPdemPdemid(array(12, 34)); // WHERE pdem_pdemID IN (12, 34)
     * $query->filterByPdemPdemid(array('min' => 12)); // WHERE pdem_pdemID > 12
     * </code>
     *
     * @param     mixed $pdemPdemid The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPledgeDenominationQuery The current query, for fluid interface
     */
    public function filterByPdemPdemid($pdemPdemid = null, $comparison = null)
    {
        if (is_array($pdemPdemid)) {
            $useMinMax = false;
            if (isset($pdemPdemid['min'])) {
                $this->addUsingAlias(PledgeDenominationTableMap::COL_PDEM_PDEMID, $pdemPdemid['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($pdemPdemid['max'])) {
                $this->addUsingAlias(PledgeDenominationTableMap::COL_PDEM_PDEMID, $pdemPdemid['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PledgeDenominationTableMap::COL_PDEM_PDEMID, $pdemPdemid, $comparison);
    }

    /**
     * Filter the query on the pdem_plg_GroupKey column
     *
     * Example usage:
     * <code>
     * $query->filterByPdemPlgGroupkey('fooValue');   // WHERE pdem_plg_GroupKey = 'fooValue'
     * $query->filterByPdemPlgGroupkey('%fooValue%'); // WHERE pdem_plg_GroupKey LIKE '%fooValue%'
     * </code>
     *
     * @param     string $pdemPlgGroupkey The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPledgeDenominationQuery The current query, for fluid interface
     */
    public function filterByPdemPlgGroupkey($pdemPlgGroupkey = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($pdemPlgGroupkey)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PledgeDenominationTableMap::COL_PDEM_PLG_GROUPKEY, $pdemPlgGroupkey, $comparison);
    }

    /**
     * Filter the query on the plg_depID column
     *
     * Example usage:
     * <code>
     * $query->filterByPlgDepid(1234); // WHERE plg_depID = 1234
     * $query->filterByPlgDepid(array(12, 34)); // WHERE plg_depID IN (12, 34)
     * $query->filterByPlgDepid(array('min' => 12)); // WHERE plg_depID > 12
     * </code>
     *
     * @param     mixed $plgDepid The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPledgeDenominationQuery The current query, for fluid interface
     */
    public function filterByPlgDepid($plgDepid = null, $comparison = null)
    {
        if (is_array($plgDepid)) {
            $useMinMax = false;
            if (isset($plgDepid['min'])) {
                $this->addUsingAlias(PledgeDenominationTableMap::COL_PLG_DEPID, $plgDepid['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($plgDepid['max'])) {
                $this->addUsingAlias(PledgeDenominationTableMap::COL_PLG_DEPID, $plgDepid['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PledgeDenominationTableMap::COL_PLG_DEPID, $plgDepid, $comparison);
    }

    /**
     * Filter the query on the pdem_denominationID column
     *
     * Example usage:
     * <code>
     * $query->filterByPdemDenominationid('fooValue');   // WHERE pdem_denominationID = 'fooValue'
     * $query->filterByPdemDenominationid('%fooValue%'); // WHERE pdem_denominationID LIKE '%fooValue%'
     * </code>
     *
     * @param     string $pdemDenominationid The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPledgeDenominationQuery The current query, for fluid interface
     */
    public function filterByPdemDenominationid($pdemDenominationid = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($pdemDenominationid)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PledgeDenominationTableMap::COL_PDEM_DENOMINATIONID, $pdemDenominationid, $comparison);
    }

    /**
     * Filter the query on the pdem_denominationQuantity column
     *
     * Example usage:
     * <code>
     * $query->filterByPdemDenominationquantity(1234); // WHERE pdem_denominationQuantity = 1234
     * $query->filterByPdemDenominationquantity(array(12, 34)); // WHERE pdem_denominationQuantity IN (12, 34)
     * $query->filterByPdemDenominationquantity(array('min' => 12)); // WHERE pdem_denominationQuantity > 12
     * </code>
     *
     * @param     mixed $pdemDenominationquantity The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPledgeDenominationQuery The current query, for fluid interface
     */
    public function filterByPdemDenominationquantity($pdemDenominationquantity = null, $comparison = null)
    {
        if (is_array($pdemDenominationquantity)) {
            $useMinMax = false;
            if (isset($pdemDenominationquantity['min'])) {
                $this->addUsingAlias(PledgeDenominationTableMap::COL_PDEM_DENOMINATIONQUANTITY, $pdemDenominationquantity['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($pdemDenominationquantity['max'])) {
                $this->addUsingAlias(PledgeDenominationTableMap::COL_PDEM_DENOMINATIONQUANTITY, $pdemDenominationquantity['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PledgeDenominationTableMap::COL_PDEM_DENOMINATIONQUANTITY, $pdemDenominationquantity, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildPledgeDenomination $pledgeDenomination Object to remove from the list of results
     *
     * @return $this|ChildPledgeDenominationQuery The current query, for fluid interface
     */
    public function prune($pledgeDenomination = null)
    {
        if ($pledgeDenomination) {
            $this->addUsingAlias(PledgeDenominationTableMap::COL_PDEM_PDEMID, $pledgeDenomination->getPdemPdemid(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the pledge_denominations_pdem table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(PledgeDenominationTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            PledgeDenominationTableMap::clearInstancePool();
            PledgeDenominationTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(PledgeDenominationTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(PledgeDenominationTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            PledgeDenominationTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            PledgeDenominationTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // PledgeDenominationQuery
