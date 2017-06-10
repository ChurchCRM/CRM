<?php

namespace ChurchCRM\Base;

use \Exception;
use ChurchCRM\GroupPropMaster as ChildGroupPropMaster;
use ChurchCRM\GroupPropMasterQuery as ChildGroupPropMasterQuery;
use ChurchCRM\Map\GroupPropMasterTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\LogicException;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'groupprop_master' table.
 *
 *
 *
 * @method     ChildGroupPropMasterQuery orderById($order = Criteria::ASC) Order by the grp_ID column
 * @method     ChildGroupPropMasterQuery orderById($order = Criteria::ASC) Order by the prop_ID column
 * @method     ChildGroupPropMasterQuery orderByField($order = Criteria::ASC) Order by the prop_Field column
 * @method     ChildGroupPropMasterQuery orderByName($order = Criteria::ASC) Order by the prop_Name column
 * @method     ChildGroupPropMasterQuery orderByDescription($order = Criteria::ASC) Order by the prop_Description column
 * @method     ChildGroupPropMasterQuery orderByTypeId($order = Criteria::ASC) Order by the type_ID column
 * @method     ChildGroupPropMasterQuery orderBySpecial($order = Criteria::ASC) Order by the prop_Special column
 * @method     ChildGroupPropMasterQuery orderByPersonDisplay($order = Criteria::ASC) Order by the prop_PersonDisplay column
 *
 * @method     ChildGroupPropMasterQuery groupById() Group by the grp_ID column
 * @method     ChildGroupPropMasterQuery groupById() Group by the prop_ID column
 * @method     ChildGroupPropMasterQuery groupByField() Group by the prop_Field column
 * @method     ChildGroupPropMasterQuery groupByName() Group by the prop_Name column
 * @method     ChildGroupPropMasterQuery groupByDescription() Group by the prop_Description column
 * @method     ChildGroupPropMasterQuery groupByTypeId() Group by the type_ID column
 * @method     ChildGroupPropMasterQuery groupBySpecial() Group by the prop_Special column
 * @method     ChildGroupPropMasterQuery groupByPersonDisplay() Group by the prop_PersonDisplay column
 *
 * @method     ChildGroupPropMasterQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildGroupPropMasterQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildGroupPropMasterQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildGroupPropMasterQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildGroupPropMasterQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildGroupPropMasterQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildGroupPropMaster findOne(ConnectionInterface $con = null) Return the first ChildGroupPropMaster matching the query
 * @method     ChildGroupPropMaster findOneOrCreate(ConnectionInterface $con = null) Return the first ChildGroupPropMaster matching the query, or a new ChildGroupPropMaster object populated from the query conditions when no match is found
 *
 * @method     ChildGroupPropMaster findOneById(int $grp_ID) Return the first ChildGroupPropMaster filtered by the grp_ID column
 * @method     ChildGroupPropMaster findOneById(int $prop_ID) Return the first ChildGroupPropMaster filtered by the prop_ID column
 * @method     ChildGroupPropMaster findOneByField(string $prop_Field) Return the first ChildGroupPropMaster filtered by the prop_Field column
 * @method     ChildGroupPropMaster findOneByName(string $prop_Name) Return the first ChildGroupPropMaster filtered by the prop_Name column
 * @method     ChildGroupPropMaster findOneByDescription(string $prop_Description) Return the first ChildGroupPropMaster filtered by the prop_Description column
 * @method     ChildGroupPropMaster findOneByTypeId(int $type_ID) Return the first ChildGroupPropMaster filtered by the type_ID column
 * @method     ChildGroupPropMaster findOneBySpecial(int $prop_Special) Return the first ChildGroupPropMaster filtered by the prop_Special column
 * @method     ChildGroupPropMaster findOneByPersonDisplay(string $prop_PersonDisplay) Return the first ChildGroupPropMaster filtered by the prop_PersonDisplay column *

 * @method     ChildGroupPropMaster requirePk($key, ConnectionInterface $con = null) Return the ChildGroupPropMaster by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildGroupPropMaster requireOne(ConnectionInterface $con = null) Return the first ChildGroupPropMaster matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildGroupPropMaster requireOneById(int $grp_ID) Return the first ChildGroupPropMaster filtered by the grp_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildGroupPropMaster requireOneById(int $prop_ID) Return the first ChildGroupPropMaster filtered by the prop_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildGroupPropMaster requireOneByField(string $prop_Field) Return the first ChildGroupPropMaster filtered by the prop_Field column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildGroupPropMaster requireOneByName(string $prop_Name) Return the first ChildGroupPropMaster filtered by the prop_Name column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildGroupPropMaster requireOneByDescription(string $prop_Description) Return the first ChildGroupPropMaster filtered by the prop_Description column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildGroupPropMaster requireOneByTypeId(int $type_ID) Return the first ChildGroupPropMaster filtered by the type_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildGroupPropMaster requireOneBySpecial(int $prop_Special) Return the first ChildGroupPropMaster filtered by the prop_Special column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildGroupPropMaster requireOneByPersonDisplay(string $prop_PersonDisplay) Return the first ChildGroupPropMaster filtered by the prop_PersonDisplay column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildGroupPropMaster[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildGroupPropMaster objects based on current ModelCriteria
 * @method     ChildGroupPropMaster[]|ObjectCollection findById(int $grp_ID) Return ChildGroupPropMaster objects filtered by the grp_ID column
 * @method     ChildGroupPropMaster[]|ObjectCollection findById(int $prop_ID) Return ChildGroupPropMaster objects filtered by the prop_ID column
 * @method     ChildGroupPropMaster[]|ObjectCollection findByField(string $prop_Field) Return ChildGroupPropMaster objects filtered by the prop_Field column
 * @method     ChildGroupPropMaster[]|ObjectCollection findByName(string $prop_Name) Return ChildGroupPropMaster objects filtered by the prop_Name column
 * @method     ChildGroupPropMaster[]|ObjectCollection findByDescription(string $prop_Description) Return ChildGroupPropMaster objects filtered by the prop_Description column
 * @method     ChildGroupPropMaster[]|ObjectCollection findByTypeId(int $type_ID) Return ChildGroupPropMaster objects filtered by the type_ID column
 * @method     ChildGroupPropMaster[]|ObjectCollection findBySpecial(int $prop_Special) Return ChildGroupPropMaster objects filtered by the prop_Special column
 * @method     ChildGroupPropMaster[]|ObjectCollection findByPersonDisplay(string $prop_PersonDisplay) Return ChildGroupPropMaster objects filtered by the prop_PersonDisplay column
 * @method     ChildGroupPropMaster[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class GroupPropMasterQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\GroupPropMasterQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\GroupPropMaster', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildGroupPropMasterQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildGroupPropMasterQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildGroupPropMasterQuery) {
            return $criteria;
        }
        $query = new ChildGroupPropMasterQuery();
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
     * @return ChildGroupPropMaster|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        throw new LogicException('The GroupPropMaster object has no primary key');
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
        throw new LogicException('The GroupPropMaster object has no primary key');
    }

    /**
     * Filter the query by primary key
     *
     * @param     mixed $key Primary key to use for the query
     *
     * @return $this|ChildGroupPropMasterQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {
        throw new LogicException('The GroupPropMaster object has no primary key');
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildGroupPropMasterQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {
        throw new LogicException('The GroupPropMaster object has no primary key');
    }

    /**
     * Filter the query on the grp_ID column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE grp_ID = 1234
     * $query->filterById(array(12, 34)); // WHERE grp_ID IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE grp_ID > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildGroupPropMasterQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(GroupPropMasterTableMap::COL_GRP_ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(GroupPropMasterTableMap::COL_GRP_ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(GroupPropMasterTableMap::COL_GRP_ID, $id, $comparison);
    }

    /**
     * Filter the query on the prop_ID column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE prop_ID = 1234
     * $query->filterById(array(12, 34)); // WHERE prop_ID IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE prop_ID > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildGroupPropMasterQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(GroupPropMasterTableMap::COL_PROP_ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(GroupPropMasterTableMap::COL_PROP_ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(GroupPropMasterTableMap::COL_PROP_ID, $id, $comparison);
    }

    /**
     * Filter the query on the prop_Field column
     *
     * Example usage:
     * <code>
     * $query->filterByField('fooValue');   // WHERE prop_Field = 'fooValue'
     * $query->filterByField('%fooValue%', Criteria::LIKE); // WHERE prop_Field LIKE '%fooValue%'
     * </code>
     *
     * @param     string $field The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildGroupPropMasterQuery The current query, for fluid interface
     */
    public function filterByField($field = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($field)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(GroupPropMasterTableMap::COL_PROP_FIELD, $field, $comparison);
    }

    /**
     * Filter the query on the prop_Name column
     *
     * Example usage:
     * <code>
     * $query->filterByName('fooValue');   // WHERE prop_Name = 'fooValue'
     * $query->filterByName('%fooValue%', Criteria::LIKE); // WHERE prop_Name LIKE '%fooValue%'
     * </code>
     *
     * @param     string $name The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildGroupPropMasterQuery The current query, for fluid interface
     */
    public function filterByName($name = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($name)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(GroupPropMasterTableMap::COL_PROP_NAME, $name, $comparison);
    }

    /**
     * Filter the query on the prop_Description column
     *
     * Example usage:
     * <code>
     * $query->filterByDescription('fooValue');   // WHERE prop_Description = 'fooValue'
     * $query->filterByDescription('%fooValue%', Criteria::LIKE); // WHERE prop_Description LIKE '%fooValue%'
     * </code>
     *
     * @param     string $description The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildGroupPropMasterQuery The current query, for fluid interface
     */
    public function filterByDescription($description = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($description)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(GroupPropMasterTableMap::COL_PROP_DESCRIPTION, $description, $comparison);
    }

    /**
     * Filter the query on the type_ID column
     *
     * Example usage:
     * <code>
     * $query->filterByTypeId(1234); // WHERE type_ID = 1234
     * $query->filterByTypeId(array(12, 34)); // WHERE type_ID IN (12, 34)
     * $query->filterByTypeId(array('min' => 12)); // WHERE type_ID > 12
     * </code>
     *
     * @param     mixed $typeId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildGroupPropMasterQuery The current query, for fluid interface
     */
    public function filterByTypeId($typeId = null, $comparison = null)
    {
        if (is_array($typeId)) {
            $useMinMax = false;
            if (isset($typeId['min'])) {
                $this->addUsingAlias(GroupPropMasterTableMap::COL_TYPE_ID, $typeId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($typeId['max'])) {
                $this->addUsingAlias(GroupPropMasterTableMap::COL_TYPE_ID, $typeId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(GroupPropMasterTableMap::COL_TYPE_ID, $typeId, $comparison);
    }

    /**
     * Filter the query on the prop_Special column
     *
     * Example usage:
     * <code>
     * $query->filterBySpecial(1234); // WHERE prop_Special = 1234
     * $query->filterBySpecial(array(12, 34)); // WHERE prop_Special IN (12, 34)
     * $query->filterBySpecial(array('min' => 12)); // WHERE prop_Special > 12
     * </code>
     *
     * @param     mixed $special The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildGroupPropMasterQuery The current query, for fluid interface
     */
    public function filterBySpecial($special = null, $comparison = null)
    {
        if (is_array($special)) {
            $useMinMax = false;
            if (isset($special['min'])) {
                $this->addUsingAlias(GroupPropMasterTableMap::COL_PROP_SPECIAL, $special['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($special['max'])) {
                $this->addUsingAlias(GroupPropMasterTableMap::COL_PROP_SPECIAL, $special['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(GroupPropMasterTableMap::COL_PROP_SPECIAL, $special, $comparison);
    }

    /**
     * Filter the query on the prop_PersonDisplay column
     *
     * Example usage:
     * <code>
     * $query->filterByPersonDisplay('fooValue');   // WHERE prop_PersonDisplay = 'fooValue'
     * $query->filterByPersonDisplay('%fooValue%', Criteria::LIKE); // WHERE prop_PersonDisplay LIKE '%fooValue%'
     * </code>
     *
     * @param     string $personDisplay The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildGroupPropMasterQuery The current query, for fluid interface
     */
    public function filterByPersonDisplay($personDisplay = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($personDisplay)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(GroupPropMasterTableMap::COL_PROP_PERSONDISPLAY, $personDisplay, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildGroupPropMaster $groupPropMaster Object to remove from the list of results
     *
     * @return $this|ChildGroupPropMasterQuery The current query, for fluid interface
     */
    public function prune($groupPropMaster = null)
    {
        if ($groupPropMaster) {
            throw new LogicException('GroupPropMaster object has no primary key');

        }

        return $this;
    }

    /**
     * Deletes all rows from the groupprop_master table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(GroupPropMasterTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            GroupPropMasterTableMap::clearInstancePool();
            GroupPropMasterTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(GroupPropMasterTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(GroupPropMasterTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            GroupPropMasterTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            GroupPropMasterTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // GroupPropMasterQuery
