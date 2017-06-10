<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\PersonProperty as ChildPersonProperty;
use ChurchCRM\PersonPropertyQuery as ChildPersonPropertyQuery;
use ChurchCRM\Map\PersonPropertyTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\ModelJoin;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'record2property_r2p' table.
 *
 *
 *
 * @method     ChildPersonPropertyQuery orderByPropertyId($order = Criteria::ASC) Order by the r2p_pro_ID column
 * @method     ChildPersonPropertyQuery orderByPersonId($order = Criteria::ASC) Order by the r2p_record_ID column
 * @method     ChildPersonPropertyQuery orderByPropertyValue($order = Criteria::ASC) Order by the r2p_Value column
 *
 * @method     ChildPersonPropertyQuery groupByPropertyId() Group by the r2p_pro_ID column
 * @method     ChildPersonPropertyQuery groupByPersonId() Group by the r2p_record_ID column
 * @method     ChildPersonPropertyQuery groupByPropertyValue() Group by the r2p_Value column
 *
 * @method     ChildPersonPropertyQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildPersonPropertyQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildPersonPropertyQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildPersonPropertyQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildPersonPropertyQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildPersonPropertyQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildPersonPropertyQuery leftJoinProperty($relationAlias = null) Adds a LEFT JOIN clause to the query using the Property relation
 * @method     ChildPersonPropertyQuery rightJoinProperty($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Property relation
 * @method     ChildPersonPropertyQuery innerJoinProperty($relationAlias = null) Adds a INNER JOIN clause to the query using the Property relation
 *
 * @method     ChildPersonPropertyQuery joinWithProperty($joinType = Criteria::INNER_JOIN) Adds a join clause and with to the query using the Property relation
 *
 * @method     ChildPersonPropertyQuery leftJoinWithProperty() Adds a LEFT JOIN clause and with to the query using the Property relation
 * @method     ChildPersonPropertyQuery rightJoinWithProperty() Adds a RIGHT JOIN clause and with to the query using the Property relation
 * @method     ChildPersonPropertyQuery innerJoinWithProperty() Adds a INNER JOIN clause and with to the query using the Property relation
 *
 * @method     ChildPersonPropertyQuery leftJoinPerson($relationAlias = null) Adds a LEFT JOIN clause to the query using the Person relation
 * @method     ChildPersonPropertyQuery rightJoinPerson($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Person relation
 * @method     ChildPersonPropertyQuery innerJoinPerson($relationAlias = null) Adds a INNER JOIN clause to the query using the Person relation
 *
 * @method     ChildPersonPropertyQuery joinWithPerson($joinType = Criteria::INNER_JOIN) Adds a join clause and with to the query using the Person relation
 *
 * @method     ChildPersonPropertyQuery leftJoinWithPerson() Adds a LEFT JOIN clause and with to the query using the Person relation
 * @method     ChildPersonPropertyQuery rightJoinWithPerson() Adds a RIGHT JOIN clause and with to the query using the Person relation
 * @method     ChildPersonPropertyQuery innerJoinWithPerson() Adds a INNER JOIN clause and with to the query using the Person relation
 *
 * @method     \ChurchCRM\PropertyQuery|\ChurchCRM\PersonQuery endUse() Finalizes a secondary criteria and merges it with its primary Criteria
 *
 * @method     ChildPersonProperty findOne(ConnectionInterface $con = null) Return the first ChildPersonProperty matching the query
 * @method     ChildPersonProperty findOneOrCreate(ConnectionInterface $con = null) Return the first ChildPersonProperty matching the query, or a new ChildPersonProperty object populated from the query conditions when no match is found
 *
 * @method     ChildPersonProperty findOneByPropertyId(int $r2p_pro_ID) Return the first ChildPersonProperty filtered by the r2p_pro_ID column
 * @method     ChildPersonProperty findOneByPersonId(int $r2p_record_ID) Return the first ChildPersonProperty filtered by the r2p_record_ID column
 * @method     ChildPersonProperty findOneByPropertyValue(string $r2p_Value) Return the first ChildPersonProperty filtered by the r2p_Value column *

 * @method     ChildPersonProperty requirePk($key, ConnectionInterface $con = null) Return the ChildPersonProperty by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPersonProperty requireOne(ConnectionInterface $con = null) Return the first ChildPersonProperty matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildPersonProperty requireOneByPropertyId(int $r2p_pro_ID) Return the first ChildPersonProperty filtered by the r2p_pro_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPersonProperty requireOneByPersonId(int $r2p_record_ID) Return the first ChildPersonProperty filtered by the r2p_record_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPersonProperty requireOneByPropertyValue(string $r2p_Value) Return the first ChildPersonProperty filtered by the r2p_Value column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildPersonProperty[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildPersonProperty objects based on current ModelCriteria
 * @method     ChildPersonProperty[]|ObjectCollection findByPropertyId(int $r2p_pro_ID) Return ChildPersonProperty objects filtered by the r2p_pro_ID column
 * @method     ChildPersonProperty[]|ObjectCollection findByPersonId(int $r2p_record_ID) Return ChildPersonProperty objects filtered by the r2p_record_ID column
 * @method     ChildPersonProperty[]|ObjectCollection findByPropertyValue(string $r2p_Value) Return ChildPersonProperty objects filtered by the r2p_Value column
 * @method     ChildPersonProperty[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class PersonPropertyQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\PersonPropertyQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\PersonProperty', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildPersonPropertyQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildPersonPropertyQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildPersonPropertyQuery) {
            return $criteria;
        }
        $query = new ChildPersonPropertyQuery();
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
     * $obj = $c->findPk(array(12, 34), $con);
     * </code>
     *
     * @param array[$r2p_pro_ID, $r2p_record_ID] $key Primary key to use for the query
     * @param ConnectionInterface $con an optional connection object
     *
     * @return ChildPersonProperty|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(PersonPropertyTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (
            $this->formatter || $this->modelAlias || $this->with || $this->select
            || $this->selectColumns || $this->asColumns || $this->selectModifiers
            || $this->map || $this->having || $this->joins
        ) {
            return $this->findPkComplex($key, $con);
        }

        if ((null !== ($obj = PersonPropertyTableMap::getInstanceFromPool(serialize([(null === $key[0] || is_scalar($key[0]) || is_callable([$key[0], '__toString']) ? (string) $key[0] : $key[0]), (null === $key[1] || is_scalar($key[1]) || is_callable([$key[1], '__toString']) ? (string) $key[1] : $key[1])]))))) {
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
     * @return ChildPersonProperty A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT r2p_pro_ID, r2p_record_ID, r2p_Value FROM record2property_r2p WHERE r2p_pro_ID = :p0 AND r2p_record_ID = :p1';
        try {
            $stmt = $con->prepare($sql);
            $stmt->bindValue(':p0', $key[0], PDO::PARAM_INT);
            $stmt->bindValue(':p1', $key[1], PDO::PARAM_INT);
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', $sql), 0, $e);
        }
        $obj = null;
        if ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
            /** @var ChildPersonProperty $obj */
            $obj = new ChildPersonProperty();
            $obj->hydrate($row);
            PersonPropertyTableMap::addInstanceToPool($obj, serialize([(null === $key[0] || is_scalar($key[0]) || is_callable([$key[0], '__toString']) ? (string) $key[0] : $key[0]), (null === $key[1] || is_scalar($key[1]) || is_callable([$key[1], '__toString']) ? (string) $key[1] : $key[1])]));
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
     * @return ChildPersonProperty|array|mixed the result, formatted by the current formatter
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
     * $objs = $c->findPks(array(array(12, 56), array(832, 123), array(123, 456)), $con);
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
     * @return $this|ChildPersonPropertyQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {
        $this->addUsingAlias(PersonPropertyTableMap::COL_R2P_PRO_ID, $key[0], Criteria::EQUAL);
        $this->addUsingAlias(PersonPropertyTableMap::COL_R2P_RECORD_ID, $key[1], Criteria::EQUAL);

        return $this;
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildPersonPropertyQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {
        if (empty($keys)) {
            return $this->add(null, '1<>1', Criteria::CUSTOM);
        }
        foreach ($keys as $key) {
            $cton0 = $this->getNewCriterion(PersonPropertyTableMap::COL_R2P_PRO_ID, $key[0], Criteria::EQUAL);
            $cton1 = $this->getNewCriterion(PersonPropertyTableMap::COL_R2P_RECORD_ID, $key[1], Criteria::EQUAL);
            $cton0->addAnd($cton1);
            $this->addOr($cton0);
        }

        return $this;
    }

    /**
     * Filter the query on the r2p_pro_ID column
     *
     * Example usage:
     * <code>
     * $query->filterByPropertyId(1234); // WHERE r2p_pro_ID = 1234
     * $query->filterByPropertyId(array(12, 34)); // WHERE r2p_pro_ID IN (12, 34)
     * $query->filterByPropertyId(array('min' => 12)); // WHERE r2p_pro_ID > 12
     * </code>
     *
     * @see       filterByProperty()
     *
     * @param     mixed $propertyId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonPropertyQuery The current query, for fluid interface
     */
    public function filterByPropertyId($propertyId = null, $comparison = null)
    {
        if (is_array($propertyId)) {
            $useMinMax = false;
            if (isset($propertyId['min'])) {
                $this->addUsingAlias(PersonPropertyTableMap::COL_R2P_PRO_ID, $propertyId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($propertyId['max'])) {
                $this->addUsingAlias(PersonPropertyTableMap::COL_R2P_PRO_ID, $propertyId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PersonPropertyTableMap::COL_R2P_PRO_ID, $propertyId, $comparison);
    }

    /**
     * Filter the query on the r2p_record_ID column
     *
     * Example usage:
     * <code>
     * $query->filterByPersonId(1234); // WHERE r2p_record_ID = 1234
     * $query->filterByPersonId(array(12, 34)); // WHERE r2p_record_ID IN (12, 34)
     * $query->filterByPersonId(array('min' => 12)); // WHERE r2p_record_ID > 12
     * </code>
     *
     * @see       filterByPerson()
     *
     * @param     mixed $personId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonPropertyQuery The current query, for fluid interface
     */
    public function filterByPersonId($personId = null, $comparison = null)
    {
        if (is_array($personId)) {
            $useMinMax = false;
            if (isset($personId['min'])) {
                $this->addUsingAlias(PersonPropertyTableMap::COL_R2P_RECORD_ID, $personId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($personId['max'])) {
                $this->addUsingAlias(PersonPropertyTableMap::COL_R2P_RECORD_ID, $personId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PersonPropertyTableMap::COL_R2P_RECORD_ID, $personId, $comparison);
    }

    /**
     * Filter the query on the r2p_Value column
     *
     * Example usage:
     * <code>
     * $query->filterByPropertyValue('fooValue');   // WHERE r2p_Value = 'fooValue'
     * $query->filterByPropertyValue('%fooValue%', Criteria::LIKE); // WHERE r2p_Value LIKE '%fooValue%'
     * </code>
     *
     * @param     string $propertyValue The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonPropertyQuery The current query, for fluid interface
     */
    public function filterByPropertyValue($propertyValue = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($propertyValue)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PersonPropertyTableMap::COL_R2P_VALUE, $propertyValue, $comparison);
    }

    /**
     * Filter the query by a related \ChurchCRM\Property object
     *
     * @param \ChurchCRM\Property|ObjectCollection $property The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return ChildPersonPropertyQuery The current query, for fluid interface
     */
    public function filterByProperty($property, $comparison = null)
    {
        if ($property instanceof \ChurchCRM\Property) {
            return $this
                ->addUsingAlias(PersonPropertyTableMap::COL_R2P_PRO_ID, $property->getProId(), $comparison);
        } elseif ($property instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(PersonPropertyTableMap::COL_R2P_PRO_ID, $property->toKeyValue('PrimaryKey', 'ProId'), $comparison);
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
     * @return $this|ChildPersonPropertyQuery The current query, for fluid interface
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
     * Filter the query by a related \ChurchCRM\Person object
     *
     * @param \ChurchCRM\Person|ObjectCollection $person The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return ChildPersonPropertyQuery The current query, for fluid interface
     */
    public function filterByPerson($person, $comparison = null)
    {
        if ($person instanceof \ChurchCRM\Person) {
            return $this
                ->addUsingAlias(PersonPropertyTableMap::COL_R2P_RECORD_ID, $person->getId(), $comparison);
        } elseif ($person instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(PersonPropertyTableMap::COL_R2P_RECORD_ID, $person->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByPerson() only accepts arguments of type \ChurchCRM\Person or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Person relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return $this|ChildPersonPropertyQuery The current query, for fluid interface
     */
    public function joinPerson($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Person');

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
            $this->addJoinObject($join, 'Person');
        }

        return $this;
    }

    /**
     * Use the Person relation Person object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return \ChurchCRM\PersonQuery A secondary query class using the current class as primary query
     */
    public function usePersonQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinPerson($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Person', '\ChurchCRM\PersonQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildPersonProperty $personProperty Object to remove from the list of results
     *
     * @return $this|ChildPersonPropertyQuery The current query, for fluid interface
     */
    public function prune($personProperty = null)
    {
        if ($personProperty) {
            $this->addCond('pruneCond0', $this->getAliasedColName(PersonPropertyTableMap::COL_R2P_PRO_ID), $personProperty->getPropertyId(), Criteria::NOT_EQUAL);
            $this->addCond('pruneCond1', $this->getAliasedColName(PersonPropertyTableMap::COL_R2P_RECORD_ID), $personProperty->getPersonId(), Criteria::NOT_EQUAL);
            $this->combine(array('pruneCond0', 'pruneCond1'), Criteria::LOGICAL_OR);
        }

        return $this;
    }

    /**
     * Deletes all rows from the record2property_r2p table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(PersonPropertyTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            PersonPropertyTableMap::clearInstancePool();
            PersonPropertyTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(PersonPropertyTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(PersonPropertyTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            PersonPropertyTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            PersonPropertyTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // PersonPropertyQuery
