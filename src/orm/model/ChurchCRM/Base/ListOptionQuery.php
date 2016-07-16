<?php

namespace ChurchCRM\Base;

use \Exception;
use ChurchCRM\ListOption as ChildListOption;
use ChurchCRM\ListOptionQuery as ChildListOptionQuery;
use ChurchCRM\Map\ListOptionTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\LogicException;
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
     * $obj  = $c->findPk(12, $con);
     * </code>
     *
     * @param mixed $key Primary key to use for the query
     * @param ConnectionInterface $con an optional connection object
     *
     * @return ChildListOption|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        throw new LogicException('The ListOption object has no primary key');
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
        throw new LogicException('The ListOption object has no primary key');
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
        throw new LogicException('The ListOption object has no primary key');
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
        throw new LogicException('The ListOption object has no primary key');
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
     * $query->filterByOptionName('%fooValue%'); // WHERE lst_OptionName LIKE '%fooValue%'
     * </code>
     *
     * @param     string $optionName The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
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
     * Exclude object from result
     *
     * @param   ChildListOption $listOption Object to remove from the list of results
     *
     * @return $this|ChildListOptionQuery The current query, for fluid interface
     */
    public function prune($listOption = null)
    {
        if ($listOption) {
            throw new LogicException('ListOption object has no primary key');

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
