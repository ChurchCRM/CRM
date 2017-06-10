<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\ListOption as ChildListOption;
use ChurchCRM\ListOptionQuery as ChildListOptionQuery;
use ChurchCRM\Map\ListOptionTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\ModelJoin;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'list_lst' table.
 *
 *
 *
 * @method     ChildListOptionQuery orderById($order = Criteria::ASC) Order by the lst_ID column
 * @method     ChildListOptionQuery orderByOptionId($order = Criteria::ASC) Order by the lst_OptionID column
 * @method     ChildListOptionQuery orderByOptionSequence($order = Criteria::ASC) Order by the lst_OptionSequence column
 * @method     ChildListOptionQuery orderByOptionName($order = Criteria::ASC) Order by the lst_OptionName column
 *
 * @method     ChildListOptionQuery groupById() Group by the lst_ID column
 * @method     ChildListOptionQuery groupByOptionId() Group by the lst_OptionID column
 * @method     ChildListOptionQuery groupByOptionSequence() Group by the lst_OptionSequence column
 * @method     ChildListOptionQuery groupByOptionName() Group by the lst_OptionName column
 *
 * @method     ChildListOptionQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildListOptionQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildListOptionQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildListOptionQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildListOptionQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildListOptionQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildListOptionQuery leftJoinGroup($relationAlias = null) Adds a LEFT JOIN clause to the query using the Group relation
 * @method     ChildListOptionQuery rightJoinGroup($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Group relation
 * @method     ChildListOptionQuery innerJoinGroup($relationAlias = null) Adds a INNER JOIN clause to the query using the Group relation
 *
 * @method     ChildListOptionQuery joinWithGroup($joinType = Criteria::INNER_JOIN) Adds a join clause and with to the query using the Group relation
 *
 * @method     ChildListOptionQuery leftJoinWithGroup() Adds a LEFT JOIN clause and with to the query using the Group relation
 * @method     ChildListOptionQuery rightJoinWithGroup() Adds a RIGHT JOIN clause and with to the query using the Group relation
 * @method     ChildListOptionQuery innerJoinWithGroup() Adds a INNER JOIN clause and with to the query using the Group relation
 *
 * @method     \ChurchCRM\GroupQuery endUse() Finalizes a secondary criteria and merges it with its primary Criteria
 *
 * @method     ChildListOption findOne(ConnectionInterface $con = null) Return the first ChildListOption matching the query
 * @method     ChildListOption findOneOrCreate(ConnectionInterface $con = null) Return the first ChildListOption matching the query, or a new ChildListOption object populated from the query conditions when no match is found
 *
 * @method     ChildListOption findOneById(int $lst_ID) Return the first ChildListOption filtered by the lst_ID column
 * @method     ChildListOption findOneByOptionId(int $lst_OptionID) Return the first ChildListOption filtered by the lst_OptionID column
 * @method     ChildListOption findOneByOptionSequence(int $lst_OptionSequence) Return the first ChildListOption filtered by the lst_OptionSequence column
 * @method     ChildListOption findOneByOptionName(string $lst_OptionName) Return the first ChildListOption filtered by the lst_OptionName column *

 * @method     ChildListOption requirePk($key, ConnectionInterface $con = null) Return the ChildListOption by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildListOption requireOne(ConnectionInterface $con = null) Return the first ChildListOption matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildListOption requireOneById(int $lst_ID) Return the first ChildListOption filtered by the lst_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildListOption requireOneByOptionId(int $lst_OptionID) Return the first ChildListOption filtered by the lst_OptionID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildListOption requireOneByOptionSequence(int $lst_OptionSequence) Return the first ChildListOption filtered by the lst_OptionSequence column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildListOption requireOneByOptionName(string $lst_OptionName) Return the first ChildListOption filtered by the lst_OptionName column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildListOption[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildListOption objects based on current ModelCriteria
 * @method     ChildListOption[]|ObjectCollection findById(int $lst_ID) Return ChildListOption objects filtered by the lst_ID column
 * @method     ChildListOption[]|ObjectCollection findByOptionId(int $lst_OptionID) Return ChildListOption objects filtered by the lst_OptionID column
 * @method     ChildListOption[]|ObjectCollection findByOptionSequence(int $lst_OptionSequence) Return ChildListOption objects filtered by the lst_OptionSequence column
 * @method     ChildListOption[]|ObjectCollection findByOptionName(string $lst_OptionName) Return ChildListOption objects filtered by the lst_OptionName column
 * @method     ChildListOption[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class ListOptionQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\ListOptionQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\ListOption', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildListOptionQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildListOptionQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildListOptionQuery) {
            return $criteria;
        }
        $query = new ChildListOptionQuery();
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
     * $obj = $c->findPk(array(12, 34, 56), $con);
     * </code>
     *
     * @param array[$lst_ID, $lst_OptionID, $lst_OptionSequence] $key Primary key to use for the query
     * @param ConnectionInterface $con an optional connection object
     *
     * @return ChildListOption|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(ListOptionTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (
            $this->formatter || $this->modelAlias || $this->with || $this->select
            || $this->selectColumns || $this->asColumns || $this->selectModifiers
            || $this->map || $this->having || $this->joins
        ) {
            return $this->findPkComplex($key, $con);
        }

        if ((null !== ($obj = ListOptionTableMap::getInstanceFromPool(serialize([(null === $key[0] || is_scalar($key[0]) || is_callable([$key[0], '__toString']) ? (string) $key[0] : $key[0]), (null === $key[1] || is_scalar($key[1]) || is_callable([$key[1], '__toString']) ? (string) $key[1] : $key[1]), (null === $key[2] || is_scalar($key[2]) || is_callable([$key[2], '__toString']) ? (string) $key[2] : $key[2])]))))) {
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
     * @return ChildListOption A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT lst_ID, lst_OptionID, lst_OptionSequence, lst_OptionName FROM list_lst WHERE lst_ID = :p0 AND lst_OptionID = :p1 AND lst_OptionSequence = :p2';
        try {
            $stmt = $con->prepare($sql);
            $stmt->bindValue(':p0', $key[0], PDO::PARAM_INT);
            $stmt->bindValue(':p1', $key[1], PDO::PARAM_INT);
            $stmt->bindValue(':p2', $key[2], PDO::PARAM_INT);
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', $sql), 0, $e);
        }
        $obj = null;
        if ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
            /** @var ChildListOption $obj */
            $obj = new ChildListOption();
            $obj->hydrate($row);
            ListOptionTableMap::addInstanceToPool($obj, serialize([(null === $key[0] || is_scalar($key[0]) || is_callable([$key[0], '__toString']) ? (string) $key[0] : $key[0]), (null === $key[1] || is_scalar($key[1]) || is_callable([$key[1], '__toString']) ? (string) $key[1] : $key[1]), (null === $key[2] || is_scalar($key[2]) || is_callable([$key[2], '__toString']) ? (string) $key[2] : $key[2])]));
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
     * @return ChildListOption|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildListOptionQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {
        $this->addUsingAlias(ListOptionTableMap::COL_LST_ID, $key[0], Criteria::EQUAL);
        $this->addUsingAlias(ListOptionTableMap::COL_LST_OPTIONID, $key[1], Criteria::EQUAL);
        $this->addUsingAlias(ListOptionTableMap::COL_LST_OPTIONSEQUENCE, $key[2], Criteria::EQUAL);

        return $this;
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildListOptionQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {
        if (empty($keys)) {
            return $this->add(null, '1<>1', Criteria::CUSTOM);
        }
        foreach ($keys as $key) {
            $cton0 = $this->getNewCriterion(ListOptionTableMap::COL_LST_ID, $key[0], Criteria::EQUAL);
            $cton1 = $this->getNewCriterion(ListOptionTableMap::COL_LST_OPTIONID, $key[1], Criteria::EQUAL);
            $cton0->addAnd($cton1);
            $cton2 = $this->getNewCriterion(ListOptionTableMap::COL_LST_OPTIONSEQUENCE, $key[2], Criteria::EQUAL);
            $cton0->addAnd($cton2);
            $this->addOr($cton0);
        }

        return $this;
    }

    /**
     * Filter the query on the lst_ID column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE lst_ID = 1234
     * $query->filterById(array(12, 34)); // WHERE lst_ID IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE lst_ID > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildListOptionQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(ListOptionTableMap::COL_LST_ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(ListOptionTableMap::COL_LST_ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ListOptionTableMap::COL_LST_ID, $id, $comparison);
    }

    /**
     * Filter the query on the lst_OptionID column
     *
     * Example usage:
     * <code>
     * $query->filterByOptionId(1234); // WHERE lst_OptionID = 1234
     * $query->filterByOptionId(array(12, 34)); // WHERE lst_OptionID IN (12, 34)
     * $query->filterByOptionId(array('min' => 12)); // WHERE lst_OptionID > 12
     * </code>
     *
     * @param     mixed $optionId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildListOptionQuery The current query, for fluid interface
     */
    public function filterByOptionId($optionId = null, $comparison = null)
    {
        if (is_array($optionId)) {
            $useMinMax = false;
            if (isset($optionId['min'])) {
                $this->addUsingAlias(ListOptionTableMap::COL_LST_OPTIONID, $optionId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($optionId['max'])) {
                $this->addUsingAlias(ListOptionTableMap::COL_LST_OPTIONID, $optionId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ListOptionTableMap::COL_LST_OPTIONID, $optionId, $comparison);
    }

    /**
     * Filter the query on the lst_OptionSequence column
     *
     * Example usage:
     * <code>
     * $query->filterByOptionSequence(1234); // WHERE lst_OptionSequence = 1234
     * $query->filterByOptionSequence(array(12, 34)); // WHERE lst_OptionSequence IN (12, 34)
     * $query->filterByOptionSequence(array('min' => 12)); // WHERE lst_OptionSequence > 12
     * </code>
     *
     * @param     mixed $optionSequence The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildListOptionQuery The current query, for fluid interface
     */
    public function filterByOptionSequence($optionSequence = null, $comparison = null)
    {
        if (is_array($optionSequence)) {
            $useMinMax = false;
            if (isset($optionSequence['min'])) {
                $this->addUsingAlias(ListOptionTableMap::COL_LST_OPTIONSEQUENCE, $optionSequence['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($optionSequence['max'])) {
                $this->addUsingAlias(ListOptionTableMap::COL_LST_OPTIONSEQUENCE, $optionSequence['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ListOptionTableMap::COL_LST_OPTIONSEQUENCE, $optionSequence, $comparison);
    }

    /**
     * Filter the query on the lst_OptionName column
     *
     * Example usage:
     * <code>
     * $query->filterByOptionName('fooValue');   // WHERE lst_OptionName = 'fooValue'
     * $query->filterByOptionName('%fooValue%', Criteria::LIKE); // WHERE lst_OptionName LIKE '%fooValue%'
     * </code>
     *
     * @param     string $optionName The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildListOptionQuery The current query, for fluid interface
     */
    public function filterByOptionName($optionName = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($optionName)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ListOptionTableMap::COL_LST_OPTIONNAME, $optionName, $comparison);
    }

    /**
     * Filter the query by a related \ChurchCRM\Group object
     *
     * @param \ChurchCRM\Group|ObjectCollection $group the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildListOptionQuery The current query, for fluid interface
     */
    public function filterByGroup($group, $comparison = null)
    {
        if ($group instanceof \ChurchCRM\Group) {
            return $this
                ->addUsingAlias(ListOptionTableMap::COL_LST_OPTIONID, $group->getType(), $comparison);
        } elseif ($group instanceof ObjectCollection) {
            return $this
                ->useGroupQuery()
                ->filterByPrimaryKeys($group->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByGroup() only accepts arguments of type \ChurchCRM\Group or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Group relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return $this|ChildListOptionQuery The current query, for fluid interface
     */
    public function joinGroup($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Group');

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
            $this->addJoinObject($join, 'Group');
        }

        return $this;
    }

    /**
     * Use the Group relation Group object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return \ChurchCRM\GroupQuery A secondary query class using the current class as primary query
     */
    public function useGroupQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinGroup($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Group', '\ChurchCRM\GroupQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildListOption $listOption Object to remove from the list of results
     *
     * @return $this|ChildListOptionQuery The current query, for fluid interface
     */
    public function prune($listOption = null)
    {
        if ($listOption) {
            $this->addCond('pruneCond0', $this->getAliasedColName(ListOptionTableMap::COL_LST_ID), $listOption->getId(), Criteria::NOT_EQUAL);
            $this->addCond('pruneCond1', $this->getAliasedColName(ListOptionTableMap::COL_LST_OPTIONID), $listOption->getOptionId(), Criteria::NOT_EQUAL);
            $this->addCond('pruneCond2', $this->getAliasedColName(ListOptionTableMap::COL_LST_OPTIONSEQUENCE), $listOption->getOptionSequence(), Criteria::NOT_EQUAL);
            $this->combine(array('pruneCond0', 'pruneCond1', 'pruneCond2'), Criteria::LOGICAL_OR);
        }

        return $this;
    }

    /**
     * Deletes all rows from the list_lst table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(ListOptionTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            ListOptionTableMap::clearInstancePool();
            ListOptionTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(ListOptionTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(ListOptionTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            ListOptionTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            ListOptionTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // ListOptionQuery
