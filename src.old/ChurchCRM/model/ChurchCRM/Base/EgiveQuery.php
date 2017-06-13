<?php

namespace ChurchCRM\Base;

use \Exception;
use ChurchCRM\Egive as ChildEgive;
use ChurchCRM\EgiveQuery as ChildEgiveQuery;
use ChurchCRM\Map\EgiveTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\LogicException;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'egive_egv' table.
 *
 *
 *
 * @method     ChildEgiveQuery orderByEgiveId($order = Criteria::ASC) Order by the egv_egiveID column
 * @method     ChildEgiveQuery orderByFamilyId($order = Criteria::ASC) Order by the egv_famID column
 * @method     ChildEgiveQuery orderByDateEntered($order = Criteria::ASC) Order by the egv_DateEntered column
 * @method     ChildEgiveQuery orderByDateLastEdited($order = Criteria::ASC) Order by the egv_DateLastEdited column
 * @method     ChildEgiveQuery orderByEnteredBy($order = Criteria::ASC) Order by the egv_EnteredBy column
 * @method     ChildEgiveQuery orderByEditedBy($order = Criteria::ASC) Order by the egv_EditedBy column
 *
 * @method     ChildEgiveQuery groupByEgiveId() Group by the egv_egiveID column
 * @method     ChildEgiveQuery groupByFamilyId() Group by the egv_famID column
 * @method     ChildEgiveQuery groupByDateEntered() Group by the egv_DateEntered column
 * @method     ChildEgiveQuery groupByDateLastEdited() Group by the egv_DateLastEdited column
 * @method     ChildEgiveQuery groupByEnteredBy() Group by the egv_EnteredBy column
 * @method     ChildEgiveQuery groupByEditedBy() Group by the egv_EditedBy column
 *
 * @method     ChildEgiveQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildEgiveQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildEgiveQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildEgiveQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildEgiveQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildEgiveQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildEgive findOne(ConnectionInterface $con = null) Return the first ChildEgive matching the query
 * @method     ChildEgive findOneOrCreate(ConnectionInterface $con = null) Return the first ChildEgive matching the query, or a new ChildEgive object populated from the query conditions when no match is found
 *
 * @method     ChildEgive findOneByEgiveId(string $egv_egiveID) Return the first ChildEgive filtered by the egv_egiveID column
 * @method     ChildEgive findOneByFamilyId(int $egv_famID) Return the first ChildEgive filtered by the egv_famID column
 * @method     ChildEgive findOneByDateEntered(string $egv_DateEntered) Return the first ChildEgive filtered by the egv_DateEntered column
 * @method     ChildEgive findOneByDateLastEdited(string $egv_DateLastEdited) Return the first ChildEgive filtered by the egv_DateLastEdited column
 * @method     ChildEgive findOneByEnteredBy(int $egv_EnteredBy) Return the first ChildEgive filtered by the egv_EnteredBy column
 * @method     ChildEgive findOneByEditedBy(int $egv_EditedBy) Return the first ChildEgive filtered by the egv_EditedBy column *

 * @method     ChildEgive requirePk($key, ConnectionInterface $con = null) Return the ChildEgive by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEgive requireOne(ConnectionInterface $con = null) Return the first ChildEgive matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildEgive requireOneByEgiveId(string $egv_egiveID) Return the first ChildEgive filtered by the egv_egiveID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEgive requireOneByFamilyId(int $egv_famID) Return the first ChildEgive filtered by the egv_famID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEgive requireOneByDateEntered(string $egv_DateEntered) Return the first ChildEgive filtered by the egv_DateEntered column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEgive requireOneByDateLastEdited(string $egv_DateLastEdited) Return the first ChildEgive filtered by the egv_DateLastEdited column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEgive requireOneByEnteredBy(int $egv_EnteredBy) Return the first ChildEgive filtered by the egv_EnteredBy column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEgive requireOneByEditedBy(int $egv_EditedBy) Return the first ChildEgive filtered by the egv_EditedBy column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildEgive[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildEgive objects based on current ModelCriteria
 * @method     ChildEgive[]|ObjectCollection findByEgiveId(string $egv_egiveID) Return ChildEgive objects filtered by the egv_egiveID column
 * @method     ChildEgive[]|ObjectCollection findByFamilyId(int $egv_famID) Return ChildEgive objects filtered by the egv_famID column
 * @method     ChildEgive[]|ObjectCollection findByDateEntered(string $egv_DateEntered) Return ChildEgive objects filtered by the egv_DateEntered column
 * @method     ChildEgive[]|ObjectCollection findByDateLastEdited(string $egv_DateLastEdited) Return ChildEgive objects filtered by the egv_DateLastEdited column
 * @method     ChildEgive[]|ObjectCollection findByEnteredBy(int $egv_EnteredBy) Return ChildEgive objects filtered by the egv_EnteredBy column
 * @method     ChildEgive[]|ObjectCollection findByEditedBy(int $egv_EditedBy) Return ChildEgive objects filtered by the egv_EditedBy column
 * @method     ChildEgive[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class EgiveQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\EgiveQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\Egive', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildEgiveQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildEgiveQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildEgiveQuery) {
            return $criteria;
        }
        $query = new ChildEgiveQuery();
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
     * @return ChildEgive|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        throw new LogicException('The Egive object has no primary key');
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
        throw new LogicException('The Egive object has no primary key');
    }

    /**
     * Filter the query by primary key
     *
     * @param     mixed $key Primary key to use for the query
     *
     * @return $this|ChildEgiveQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {
        throw new LogicException('The Egive object has no primary key');
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildEgiveQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {
        throw new LogicException('The Egive object has no primary key');
    }

    /**
     * Filter the query on the egv_egiveID column
     *
     * Example usage:
     * <code>
     * $query->filterByEgiveId('fooValue');   // WHERE egv_egiveID = 'fooValue'
     * $query->filterByEgiveId('%fooValue%', Criteria::LIKE); // WHERE egv_egiveID LIKE '%fooValue%'
     * </code>
     *
     * @param     string $egiveId The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEgiveQuery The current query, for fluid interface
     */
    public function filterByEgiveId($egiveId = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($egiveId)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EgiveTableMap::COL_EGV_EGIVEID, $egiveId, $comparison);
    }

    /**
     * Filter the query on the egv_famID column
     *
     * Example usage:
     * <code>
     * $query->filterByFamilyId(1234); // WHERE egv_famID = 1234
     * $query->filterByFamilyId(array(12, 34)); // WHERE egv_famID IN (12, 34)
     * $query->filterByFamilyId(array('min' => 12)); // WHERE egv_famID > 12
     * </code>
     *
     * @param     mixed $familyId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEgiveQuery The current query, for fluid interface
     */
    public function filterByFamilyId($familyId = null, $comparison = null)
    {
        if (is_array($familyId)) {
            $useMinMax = false;
            if (isset($familyId['min'])) {
                $this->addUsingAlias(EgiveTableMap::COL_EGV_FAMID, $familyId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($familyId['max'])) {
                $this->addUsingAlias(EgiveTableMap::COL_EGV_FAMID, $familyId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EgiveTableMap::COL_EGV_FAMID, $familyId, $comparison);
    }

    /**
     * Filter the query on the egv_DateEntered column
     *
     * Example usage:
     * <code>
     * $query->filterByDateEntered('2011-03-14'); // WHERE egv_DateEntered = '2011-03-14'
     * $query->filterByDateEntered('now'); // WHERE egv_DateEntered = '2011-03-14'
     * $query->filterByDateEntered(array('max' => 'yesterday')); // WHERE egv_DateEntered > '2011-03-13'
     * </code>
     *
     * @param     mixed $dateEntered The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEgiveQuery The current query, for fluid interface
     */
    public function filterByDateEntered($dateEntered = null, $comparison = null)
    {
        if (is_array($dateEntered)) {
            $useMinMax = false;
            if (isset($dateEntered['min'])) {
                $this->addUsingAlias(EgiveTableMap::COL_EGV_DATEENTERED, $dateEntered['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($dateEntered['max'])) {
                $this->addUsingAlias(EgiveTableMap::COL_EGV_DATEENTERED, $dateEntered['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EgiveTableMap::COL_EGV_DATEENTERED, $dateEntered, $comparison);
    }

    /**
     * Filter the query on the egv_DateLastEdited column
     *
     * Example usage:
     * <code>
     * $query->filterByDateLastEdited('2011-03-14'); // WHERE egv_DateLastEdited = '2011-03-14'
     * $query->filterByDateLastEdited('now'); // WHERE egv_DateLastEdited = '2011-03-14'
     * $query->filterByDateLastEdited(array('max' => 'yesterday')); // WHERE egv_DateLastEdited > '2011-03-13'
     * </code>
     *
     * @param     mixed $dateLastEdited The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEgiveQuery The current query, for fluid interface
     */
    public function filterByDateLastEdited($dateLastEdited = null, $comparison = null)
    {
        if (is_array($dateLastEdited)) {
            $useMinMax = false;
            if (isset($dateLastEdited['min'])) {
                $this->addUsingAlias(EgiveTableMap::COL_EGV_DATELASTEDITED, $dateLastEdited['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($dateLastEdited['max'])) {
                $this->addUsingAlias(EgiveTableMap::COL_EGV_DATELASTEDITED, $dateLastEdited['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EgiveTableMap::COL_EGV_DATELASTEDITED, $dateLastEdited, $comparison);
    }

    /**
     * Filter the query on the egv_EnteredBy column
     *
     * Example usage:
     * <code>
     * $query->filterByEnteredBy(1234); // WHERE egv_EnteredBy = 1234
     * $query->filterByEnteredBy(array(12, 34)); // WHERE egv_EnteredBy IN (12, 34)
     * $query->filterByEnteredBy(array('min' => 12)); // WHERE egv_EnteredBy > 12
     * </code>
     *
     * @param     mixed $enteredBy The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEgiveQuery The current query, for fluid interface
     */
    public function filterByEnteredBy($enteredBy = null, $comparison = null)
    {
        if (is_array($enteredBy)) {
            $useMinMax = false;
            if (isset($enteredBy['min'])) {
                $this->addUsingAlias(EgiveTableMap::COL_EGV_ENTEREDBY, $enteredBy['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($enteredBy['max'])) {
                $this->addUsingAlias(EgiveTableMap::COL_EGV_ENTEREDBY, $enteredBy['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EgiveTableMap::COL_EGV_ENTEREDBY, $enteredBy, $comparison);
    }

    /**
     * Filter the query on the egv_EditedBy column
     *
     * Example usage:
     * <code>
     * $query->filterByEditedBy(1234); // WHERE egv_EditedBy = 1234
     * $query->filterByEditedBy(array(12, 34)); // WHERE egv_EditedBy IN (12, 34)
     * $query->filterByEditedBy(array('min' => 12)); // WHERE egv_EditedBy > 12
     * </code>
     *
     * @param     mixed $editedBy The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEgiveQuery The current query, for fluid interface
     */
    public function filterByEditedBy($editedBy = null, $comparison = null)
    {
        if (is_array($editedBy)) {
            $useMinMax = false;
            if (isset($editedBy['min'])) {
                $this->addUsingAlias(EgiveTableMap::COL_EGV_EDITEDBY, $editedBy['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($editedBy['max'])) {
                $this->addUsingAlias(EgiveTableMap::COL_EGV_EDITEDBY, $editedBy['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EgiveTableMap::COL_EGV_EDITEDBY, $editedBy, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildEgive $egive Object to remove from the list of results
     *
     * @return $this|ChildEgiveQuery The current query, for fluid interface
     */
    public function prune($egive = null)
    {
        if ($egive) {
            throw new LogicException('Egive object has no primary key');

        }

        return $this;
    }

    /**
     * Deletes all rows from the egive_egv table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(EgiveTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            EgiveTableMap::clearInstancePool();
            EgiveTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(EgiveTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(EgiveTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            EgiveTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            EgiveTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // EgiveQuery
