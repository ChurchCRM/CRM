<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\FamilyCustom as ChildFamilyCustom;
use ChurchCRM\FamilyCustomQuery as ChildFamilyCustomQuery;
use ChurchCRM\Map\FamilyCustomTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'family_custom' table.
 *
 *
 *
 * @method     ChildFamilyCustomQuery orderByFamId($order = Criteria::ASC) Order by the fam_ID column
 *
 * @method     ChildFamilyCustomQuery groupByFamId() Group by the fam_ID column
 *
 * @method     ChildFamilyCustomQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildFamilyCustomQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildFamilyCustomQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildFamilyCustomQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildFamilyCustomQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildFamilyCustomQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildFamilyCustom findOne(ConnectionInterface $con = null) Return the first ChildFamilyCustom matching the query
 * @method     ChildFamilyCustom findOneOrCreate(ConnectionInterface $con = null) Return the first ChildFamilyCustom matching the query, or a new ChildFamilyCustom object populated from the query conditions when no match is found
 *
 * @method     ChildFamilyCustom findOneByFamId(int $fam_ID) Return the first ChildFamilyCustom filtered by the fam_ID column *

 * @method     ChildFamilyCustom requirePk($key, ConnectionInterface $con = null) Return the ChildFamilyCustom by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFamilyCustom requireOne(ConnectionInterface $con = null) Return the first ChildFamilyCustom matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildFamilyCustom requireOneByFamId(int $fam_ID) Return the first ChildFamilyCustom filtered by the fam_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildFamilyCustom[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildFamilyCustom objects based on current ModelCriteria
 * @method     ChildFamilyCustom[]|ObjectCollection findByFamId(int $fam_ID) Return ChildFamilyCustom objects filtered by the fam_ID column
 * @method     ChildFamilyCustom[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class FamilyCustomQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\FamilyCustomQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\FamilyCustom', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildFamilyCustomQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildFamilyCustomQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildFamilyCustomQuery) {
            return $criteria;
        }
        $query = new ChildFamilyCustomQuery();
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
     * @return ChildFamilyCustom|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(FamilyCustomTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (
            $this->formatter || $this->modelAlias || $this->with || $this->select
            || $this->selectColumns || $this->asColumns || $this->selectModifiers
            || $this->map || $this->having || $this->joins
        ) {
            return $this->findPkComplex($key, $con);
        }

        if ((null !== ($obj = FamilyCustomTableMap::getInstanceFromPool(null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key)))) {
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
     * @return ChildFamilyCustom A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT fam_ID FROM family_custom WHERE fam_ID = :p0';
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
            /** @var ChildFamilyCustom $obj */
            $obj = new ChildFamilyCustom();
            $obj->hydrate($row);
            FamilyCustomTableMap::addInstanceToPool($obj, null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key);
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
     * @return ChildFamilyCustom|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildFamilyCustomQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(FamilyCustomTableMap::COL_FAM_ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildFamilyCustomQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(FamilyCustomTableMap::COL_FAM_ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the fam_ID column
     *
     * Example usage:
     * <code>
     * $query->filterByFamId(1234); // WHERE fam_ID = 1234
     * $query->filterByFamId(array(12, 34)); // WHERE fam_ID IN (12, 34)
     * $query->filterByFamId(array('min' => 12)); // WHERE fam_ID > 12
     * </code>
     *
     * @param     mixed $famId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFamilyCustomQuery The current query, for fluid interface
     */
    public function filterByFamId($famId = null, $comparison = null)
    {
        if (is_array($famId)) {
            $useMinMax = false;
            if (isset($famId['min'])) {
                $this->addUsingAlias(FamilyCustomTableMap::COL_FAM_ID, $famId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($famId['max'])) {
                $this->addUsingAlias(FamilyCustomTableMap::COL_FAM_ID, $famId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FamilyCustomTableMap::COL_FAM_ID, $famId, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildFamilyCustom $familyCustom Object to remove from the list of results
     *
     * @return $this|ChildFamilyCustomQuery The current query, for fluid interface
     */
    public function prune($familyCustom = null)
    {
        if ($familyCustom) {
            $this->addUsingAlias(FamilyCustomTableMap::COL_FAM_ID, $familyCustom->getFamId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the family_custom table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(FamilyCustomTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            FamilyCustomTableMap::clearInstancePool();
            FamilyCustomTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(FamilyCustomTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(FamilyCustomTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            FamilyCustomTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            FamilyCustomTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // FamilyCustomQuery
