<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\Config as ChildConfig;
use ChurchCRM\ConfigQuery as ChildConfigQuery;
use ChurchCRM\Map\ConfigTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'config_cfg' table.
 *
 *
 *
 * @method     ChildConfigQuery orderById($order = Criteria::ASC) Order by the cfg_id column
 * @method     ChildConfigQuery orderByName($order = Criteria::ASC) Order by the cfg_name column
 * @method     ChildConfigQuery orderByValue($order = Criteria::ASC) Order by the cfg_value column
 *
 * @method     ChildConfigQuery groupById() Group by the cfg_id column
 * @method     ChildConfigQuery groupByName() Group by the cfg_name column
 * @method     ChildConfigQuery groupByValue() Group by the cfg_value column
 *
 * @method     ChildConfigQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildConfigQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildConfigQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildConfigQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildConfigQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildConfigQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildConfig findOne(ConnectionInterface $con = null) Return the first ChildConfig matching the query
 * @method     ChildConfig findOneOrCreate(ConnectionInterface $con = null) Return the first ChildConfig matching the query, or a new ChildConfig object populated from the query conditions when no match is found
 *
 * @method     ChildConfig findOneById(int $cfg_id) Return the first ChildConfig filtered by the cfg_id column
 * @method     ChildConfig findOneByName(string $cfg_name) Return the first ChildConfig filtered by the cfg_name column
 * @method     ChildConfig findOneByValue(string $cfg_value) Return the first ChildConfig filtered by the cfg_value column *

 * @method     ChildConfig requirePk($key, ConnectionInterface $con = null) Return the ChildConfig by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildConfig requireOne(ConnectionInterface $con = null) Return the first ChildConfig matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildConfig requireOneById(int $cfg_id) Return the first ChildConfig filtered by the cfg_id column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildConfig requireOneByName(string $cfg_name) Return the first ChildConfig filtered by the cfg_name column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildConfig requireOneByValue(string $cfg_value) Return the first ChildConfig filtered by the cfg_value column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildConfig[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildConfig objects based on current ModelCriteria
 * @method     ChildConfig[]|ObjectCollection findById(int $cfg_id) Return ChildConfig objects filtered by the cfg_id column
 * @method     ChildConfig[]|ObjectCollection findByName(string $cfg_name) Return ChildConfig objects filtered by the cfg_name column
 * @method     ChildConfig[]|ObjectCollection findByValue(string $cfg_value) Return ChildConfig objects filtered by the cfg_value column
 * @method     ChildConfig[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class ConfigQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\ConfigQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\Config', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildConfigQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildConfigQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildConfigQuery) {
            return $criteria;
        }
        $query = new ChildConfigQuery();
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
     * @return ChildConfig|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(ConfigTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (
            $this->formatter || $this->modelAlias || $this->with || $this->select
            || $this->selectColumns || $this->asColumns || $this->selectModifiers
            || $this->map || $this->having || $this->joins
        ) {
            return $this->findPkComplex($key, $con);
        }

        if ((null !== ($obj = ConfigTableMap::getInstanceFromPool(null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key)))) {
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
     * @return ChildConfig A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT cfg_id, cfg_name, cfg_value FROM config_cfg WHERE cfg_id = :p0';
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
            /** @var ChildConfig $obj */
            $obj = new ChildConfig();
            $obj->hydrate($row);
            ConfigTableMap::addInstanceToPool($obj, null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key);
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
     * @return ChildConfig|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildConfigQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(ConfigTableMap::COL_CFG_ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildConfigQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(ConfigTableMap::COL_CFG_ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the cfg_id column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE cfg_id = 1234
     * $query->filterById(array(12, 34)); // WHERE cfg_id IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE cfg_id > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildConfigQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(ConfigTableMap::COL_CFG_ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(ConfigTableMap::COL_CFG_ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ConfigTableMap::COL_CFG_ID, $id, $comparison);
    }

    /**
     * Filter the query on the cfg_name column
     *
     * Example usage:
     * <code>
     * $query->filterByName('fooValue');   // WHERE cfg_name = 'fooValue'
     * $query->filterByName('%fooValue%', Criteria::LIKE); // WHERE cfg_name LIKE '%fooValue%'
     * </code>
     *
     * @param     string $name The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildConfigQuery The current query, for fluid interface
     */
    public function filterByName($name = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($name)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ConfigTableMap::COL_CFG_NAME, $name, $comparison);
    }

    /**
     * Filter the query on the cfg_value column
     *
     * Example usage:
     * <code>
     * $query->filterByValue('fooValue');   // WHERE cfg_value = 'fooValue'
     * $query->filterByValue('%fooValue%', Criteria::LIKE); // WHERE cfg_value LIKE '%fooValue%'
     * </code>
     *
     * @param     string $value The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildConfigQuery The current query, for fluid interface
     */
    public function filterByValue($value = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($value)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ConfigTableMap::COL_CFG_VALUE, $value, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildConfig $config Object to remove from the list of results
     *
     * @return $this|ChildConfigQuery The current query, for fluid interface
     */
    public function prune($config = null)
    {
        if ($config) {
            $this->addUsingAlias(ConfigTableMap::COL_CFG_ID, $config->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the config_cfg table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(ConfigTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            ConfigTableMap::clearInstancePool();
            ConfigTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(ConfigTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(ConfigTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            ConfigTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            ConfigTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // ConfigQuery
