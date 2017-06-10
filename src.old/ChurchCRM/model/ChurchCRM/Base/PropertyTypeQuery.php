<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\PropertyType as ChildPropertyType;
use ChurchCRM\PropertyTypeQuery as ChildPropertyTypeQuery;
use ChurchCRM\Map\PropertyTypeTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\ModelJoin;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'propertytype_prt' table.
 *
 *
 *
 * @method     ChildPropertyTypeQuery orderByPrtId($order = Criteria::ASC) Order by the prt_ID column
 * @method     ChildPropertyTypeQuery orderByPrtClass($order = Criteria::ASC) Order by the prt_Class column
 * @method     ChildPropertyTypeQuery orderByPrtName($order = Criteria::ASC) Order by the prt_Name column
 * @method     ChildPropertyTypeQuery orderByPrtDescription($order = Criteria::ASC) Order by the prt_Description column
 *
 * @method     ChildPropertyTypeQuery groupByPrtId() Group by the prt_ID column
 * @method     ChildPropertyTypeQuery groupByPrtClass() Group by the prt_Class column
 * @method     ChildPropertyTypeQuery groupByPrtName() Group by the prt_Name column
 * @method     ChildPropertyTypeQuery groupByPrtDescription() Group by the prt_Description column
 *
 * @method     ChildPropertyTypeQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildPropertyTypeQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildPropertyTypeQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildPropertyTypeQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildPropertyTypeQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildPropertyTypeQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildPropertyTypeQuery leftJoinProperty($relationAlias = null) Adds a LEFT JOIN clause to the query using the Property relation
 * @method     ChildPropertyTypeQuery rightJoinProperty($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Property relation
 * @method     ChildPropertyTypeQuery innerJoinProperty($relationAlias = null) Adds a INNER JOIN clause to the query using the Property relation
 *
 * @method     ChildPropertyTypeQuery joinWithProperty($joinType = Criteria::INNER_JOIN) Adds a join clause and with to the query using the Property relation
 *
 * @method     ChildPropertyTypeQuery leftJoinWithProperty() Adds a LEFT JOIN clause and with to the query using the Property relation
 * @method     ChildPropertyTypeQuery rightJoinWithProperty() Adds a RIGHT JOIN clause and with to the query using the Property relation
 * @method     ChildPropertyTypeQuery innerJoinWithProperty() Adds a INNER JOIN clause and with to the query using the Property relation
 *
 * @method     \ChurchCRM\PropertyQuery endUse() Finalizes a secondary criteria and merges it with its primary Criteria
 *
 * @method     ChildPropertyType findOne(ConnectionInterface $con = null) Return the first ChildPropertyType matching the query
 * @method     ChildPropertyType findOneOrCreate(ConnectionInterface $con = null) Return the first ChildPropertyType matching the query, or a new ChildPropertyType object populated from the query conditions when no match is found
 *
 * @method     ChildPropertyType findOneByPrtId(int $prt_ID) Return the first ChildPropertyType filtered by the prt_ID column
 * @method     ChildPropertyType findOneByPrtClass(string $prt_Class) Return the first ChildPropertyType filtered by the prt_Class column
 * @method     ChildPropertyType findOneByPrtName(string $prt_Name) Return the first ChildPropertyType filtered by the prt_Name column
 * @method     ChildPropertyType findOneByPrtDescription(string $prt_Description) Return the first ChildPropertyType filtered by the prt_Description column *

 * @method     ChildPropertyType requirePk($key, ConnectionInterface $con = null) Return the ChildPropertyType by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPropertyType requireOne(ConnectionInterface $con = null) Return the first ChildPropertyType matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildPropertyType requireOneByPrtId(int $prt_ID) Return the first ChildPropertyType filtered by the prt_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPropertyType requireOneByPrtClass(string $prt_Class) Return the first ChildPropertyType filtered by the prt_Class column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPropertyType requireOneByPrtName(string $prt_Name) Return the first ChildPropertyType filtered by the prt_Name column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPropertyType requireOneByPrtDescription(string $prt_Description) Return the first ChildPropertyType filtered by the prt_Description column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildPropertyType[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildPropertyType objects based on current ModelCriteria
 * @method     ChildPropertyType[]|ObjectCollection findByPrtId(int $prt_ID) Return ChildPropertyType objects filtered by the prt_ID column
 * @method     ChildPropertyType[]|ObjectCollection findByPrtClass(string $prt_Class) Return ChildPropertyType objects filtered by the prt_Class column
 * @method     ChildPropertyType[]|ObjectCollection findByPrtName(string $prt_Name) Return ChildPropertyType objects filtered by the prt_Name column
 * @method     ChildPropertyType[]|ObjectCollection findByPrtDescription(string $prt_Description) Return ChildPropertyType objects filtered by the prt_Description column
 * @method     ChildPropertyType[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class PropertyTypeQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\PropertyTypeQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\PropertyType', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildPropertyTypeQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildPropertyTypeQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildPropertyTypeQuery) {
            return $criteria;
        }
        $query = new ChildPropertyTypeQuery();
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
     * @return ChildPropertyType|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(PropertyTypeTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (
            $this->formatter || $this->modelAlias || $this->with || $this->select
            || $this->selectColumns || $this->asColumns || $this->selectModifiers
            || $this->map || $this->having || $this->joins
        ) {
            return $this->findPkComplex($key, $con);
        }

        if ((null !== ($obj = PropertyTypeTableMap::getInstanceFromPool(null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key)))) {
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
     * @return ChildPropertyType A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT prt_ID, prt_Class, prt_Name, prt_Description FROM propertytype_prt WHERE prt_ID = :p0';
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
            /** @var ChildPropertyType $obj */
            $obj = new ChildPropertyType();
            $obj->hydrate($row);
            PropertyTypeTableMap::addInstanceToPool($obj, null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key);
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
     * @return ChildPropertyType|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildPropertyTypeQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(PropertyTypeTableMap::COL_PRT_ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildPropertyTypeQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(PropertyTypeTableMap::COL_PRT_ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the prt_ID column
     *
     * Example usage:
     * <code>
     * $query->filterByPrtId(1234); // WHERE prt_ID = 1234
     * $query->filterByPrtId(array(12, 34)); // WHERE prt_ID IN (12, 34)
     * $query->filterByPrtId(array('min' => 12)); // WHERE prt_ID > 12
     * </code>
     *
     * @param     mixed $prtId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPropertyTypeQuery The current query, for fluid interface
     */
    public function filterByPrtId($prtId = null, $comparison = null)
    {
        if (is_array($prtId)) {
            $useMinMax = false;
            if (isset($prtId['min'])) {
                $this->addUsingAlias(PropertyTypeTableMap::COL_PRT_ID, $prtId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($prtId['max'])) {
                $this->addUsingAlias(PropertyTypeTableMap::COL_PRT_ID, $prtId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PropertyTypeTableMap::COL_PRT_ID, $prtId, $comparison);
    }

    /**
     * Filter the query on the prt_Class column
     *
     * Example usage:
     * <code>
     * $query->filterByPrtClass('fooValue');   // WHERE prt_Class = 'fooValue'
     * $query->filterByPrtClass('%fooValue%', Criteria::LIKE); // WHERE prt_Class LIKE '%fooValue%'
     * </code>
     *
     * @param     string $prtClass The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPropertyTypeQuery The current query, for fluid interface
     */
    public function filterByPrtClass($prtClass = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($prtClass)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PropertyTypeTableMap::COL_PRT_CLASS, $prtClass, $comparison);
    }

    /**
     * Filter the query on the prt_Name column
     *
     * Example usage:
     * <code>
     * $query->filterByPrtName('fooValue');   // WHERE prt_Name = 'fooValue'
     * $query->filterByPrtName('%fooValue%', Criteria::LIKE); // WHERE prt_Name LIKE '%fooValue%'
     * </code>
     *
     * @param     string $prtName The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPropertyTypeQuery The current query, for fluid interface
     */
    public function filterByPrtName($prtName = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($prtName)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PropertyTypeTableMap::COL_PRT_NAME, $prtName, $comparison);
    }

    /**
     * Filter the query on the prt_Description column
     *
     * Example usage:
     * <code>
     * $query->filterByPrtDescription('fooValue');   // WHERE prt_Description = 'fooValue'
     * $query->filterByPrtDescription('%fooValue%', Criteria::LIKE); // WHERE prt_Description LIKE '%fooValue%'
     * </code>
     *
     * @param     string $prtDescription The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPropertyTypeQuery The current query, for fluid interface
     */
    public function filterByPrtDescription($prtDescription = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($prtDescription)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PropertyTypeTableMap::COL_PRT_DESCRIPTION, $prtDescription, $comparison);
    }

    /**
     * Filter the query by a related \ChurchCRM\Property object
     *
     * @param \ChurchCRM\Property|ObjectCollection $property the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildPropertyTypeQuery The current query, for fluid interface
     */
    public function filterByProperty($property, $comparison = null)
    {
        if ($property instanceof \ChurchCRM\Property) {
            return $this
                ->addUsingAlias(PropertyTypeTableMap::COL_PRT_ID, $property->getProPrtId(), $comparison);
        } elseif ($property instanceof ObjectCollection) {
            return $this
                ->usePropertyQuery()
                ->filterByPrimaryKeys($property->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByProperty() only accepts arguments of type \ChurchCRM\Property or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Property relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return $this|ChildPropertyTypeQuery The current query, for fluid interface
     */
    public function joinProperty($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Property');

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
            $this->addJoinObject($join, 'Property');
        }

        return $this;
    }

    /**
     * Use the Property relation Property object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return \ChurchCRM\PropertyQuery A secondary query class using the current class as primary query
     */
    public function usePropertyQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinProperty($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Property', '\ChurchCRM\PropertyQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildPropertyType $propertyType Object to remove from the list of results
     *
     * @return $this|ChildPropertyTypeQuery The current query, for fluid interface
     */
    public function prune($propertyType = null)
    {
        if ($propertyType) {
            $this->addUsingAlias(PropertyTypeTableMap::COL_PRT_ID, $propertyType->getPrtId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the propertytype_prt table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(PropertyTypeTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            PropertyTypeTableMap::clearInstancePool();
            PropertyTypeTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(PropertyTypeTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(PropertyTypeTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            PropertyTypeTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            PropertyTypeTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // PropertyTypeQuery
