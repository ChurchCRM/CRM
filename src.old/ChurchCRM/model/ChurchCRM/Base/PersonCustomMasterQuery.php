<?php

namespace ChurchCRM\Base;

use \Exception;
use ChurchCRM\PersonCustomMaster as ChildPersonCustomMaster;
use ChurchCRM\PersonCustomMasterQuery as ChildPersonCustomMasterQuery;
use ChurchCRM\Map\PersonCustomMasterTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\LogicException;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'person_custom_master' table.
 *
 *
 *
 * @method     ChildPersonCustomMasterQuery orderByCustomOrder($order = Criteria::ASC) Order by the custom_Order column
 * @method     ChildPersonCustomMasterQuery orderByCustomField($order = Criteria::ASC) Order by the custom_Field column
 * @method     ChildPersonCustomMasterQuery orderByCustomName($order = Criteria::ASC) Order by the custom_Name column
 * @method     ChildPersonCustomMasterQuery orderByCustomSpecial($order = Criteria::ASC) Order by the custom_Special column
 * @method     ChildPersonCustomMasterQuery orderByCustomSide($order = Criteria::ASC) Order by the custom_Side column
 * @method     ChildPersonCustomMasterQuery orderByCustomFieldSec($order = Criteria::ASC) Order by the custom_FieldSec column
 * @method     ChildPersonCustomMasterQuery orderByTypeId($order = Criteria::ASC) Order by the type_ID column
 *
 * @method     ChildPersonCustomMasterQuery groupByCustomOrder() Group by the custom_Order column
 * @method     ChildPersonCustomMasterQuery groupByCustomField() Group by the custom_Field column
 * @method     ChildPersonCustomMasterQuery groupByCustomName() Group by the custom_Name column
 * @method     ChildPersonCustomMasterQuery groupByCustomSpecial() Group by the custom_Special column
 * @method     ChildPersonCustomMasterQuery groupByCustomSide() Group by the custom_Side column
 * @method     ChildPersonCustomMasterQuery groupByCustomFieldSec() Group by the custom_FieldSec column
 * @method     ChildPersonCustomMasterQuery groupByTypeId() Group by the type_ID column
 *
 * @method     ChildPersonCustomMasterQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildPersonCustomMasterQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildPersonCustomMasterQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildPersonCustomMasterQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildPersonCustomMasterQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildPersonCustomMasterQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildPersonCustomMaster findOne(ConnectionInterface $con = null) Return the first ChildPersonCustomMaster matching the query
 * @method     ChildPersonCustomMaster findOneOrCreate(ConnectionInterface $con = null) Return the first ChildPersonCustomMaster matching the query, or a new ChildPersonCustomMaster object populated from the query conditions when no match is found
 *
 * @method     ChildPersonCustomMaster findOneByCustomOrder(int $custom_Order) Return the first ChildPersonCustomMaster filtered by the custom_Order column
 * @method     ChildPersonCustomMaster findOneByCustomField(string $custom_Field) Return the first ChildPersonCustomMaster filtered by the custom_Field column
 * @method     ChildPersonCustomMaster findOneByCustomName(string $custom_Name) Return the first ChildPersonCustomMaster filtered by the custom_Name column
 * @method     ChildPersonCustomMaster findOneByCustomSpecial(int $custom_Special) Return the first ChildPersonCustomMaster filtered by the custom_Special column
 * @method     ChildPersonCustomMaster findOneByCustomSide(string $custom_Side) Return the first ChildPersonCustomMaster filtered by the custom_Side column
 * @method     ChildPersonCustomMaster findOneByCustomFieldSec(int $custom_FieldSec) Return the first ChildPersonCustomMaster filtered by the custom_FieldSec column
 * @method     ChildPersonCustomMaster findOneByTypeId(int $type_ID) Return the first ChildPersonCustomMaster filtered by the type_ID column *

 * @method     ChildPersonCustomMaster requirePk($key, ConnectionInterface $con = null) Return the ChildPersonCustomMaster by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPersonCustomMaster requireOne(ConnectionInterface $con = null) Return the first ChildPersonCustomMaster matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildPersonCustomMaster requireOneByCustomOrder(int $custom_Order) Return the first ChildPersonCustomMaster filtered by the custom_Order column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPersonCustomMaster requireOneByCustomField(string $custom_Field) Return the first ChildPersonCustomMaster filtered by the custom_Field column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPersonCustomMaster requireOneByCustomName(string $custom_Name) Return the first ChildPersonCustomMaster filtered by the custom_Name column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPersonCustomMaster requireOneByCustomSpecial(int $custom_Special) Return the first ChildPersonCustomMaster filtered by the custom_Special column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPersonCustomMaster requireOneByCustomSide(string $custom_Side) Return the first ChildPersonCustomMaster filtered by the custom_Side column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPersonCustomMaster requireOneByCustomFieldSec(int $custom_FieldSec) Return the first ChildPersonCustomMaster filtered by the custom_FieldSec column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPersonCustomMaster requireOneByTypeId(int $type_ID) Return the first ChildPersonCustomMaster filtered by the type_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildPersonCustomMaster[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildPersonCustomMaster objects based on current ModelCriteria
 * @method     ChildPersonCustomMaster[]|ObjectCollection findByCustomOrder(int $custom_Order) Return ChildPersonCustomMaster objects filtered by the custom_Order column
 * @method     ChildPersonCustomMaster[]|ObjectCollection findByCustomField(string $custom_Field) Return ChildPersonCustomMaster objects filtered by the custom_Field column
 * @method     ChildPersonCustomMaster[]|ObjectCollection findByCustomName(string $custom_Name) Return ChildPersonCustomMaster objects filtered by the custom_Name column
 * @method     ChildPersonCustomMaster[]|ObjectCollection findByCustomSpecial(int $custom_Special) Return ChildPersonCustomMaster objects filtered by the custom_Special column
 * @method     ChildPersonCustomMaster[]|ObjectCollection findByCustomSide(string $custom_Side) Return ChildPersonCustomMaster objects filtered by the custom_Side column
 * @method     ChildPersonCustomMaster[]|ObjectCollection findByCustomFieldSec(int $custom_FieldSec) Return ChildPersonCustomMaster objects filtered by the custom_FieldSec column
 * @method     ChildPersonCustomMaster[]|ObjectCollection findByTypeId(int $type_ID) Return ChildPersonCustomMaster objects filtered by the type_ID column
 * @method     ChildPersonCustomMaster[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class PersonCustomMasterQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\PersonCustomMasterQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\PersonCustomMaster', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildPersonCustomMasterQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildPersonCustomMasterQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildPersonCustomMasterQuery) {
            return $criteria;
        }
        $query = new ChildPersonCustomMasterQuery();
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
     * @return ChildPersonCustomMaster|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        throw new LogicException('The PersonCustomMaster object has no primary key');
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
        throw new LogicException('The PersonCustomMaster object has no primary key');
    }

    /**
     * Filter the query by primary key
     *
     * @param     mixed $key Primary key to use for the query
     *
     * @return $this|ChildPersonCustomMasterQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {
        throw new LogicException('The PersonCustomMaster object has no primary key');
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildPersonCustomMasterQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {
        throw new LogicException('The PersonCustomMaster object has no primary key');
    }

    /**
     * Filter the query on the custom_Order column
     *
     * Example usage:
     * <code>
     * $query->filterByCustomOrder(1234); // WHERE custom_Order = 1234
     * $query->filterByCustomOrder(array(12, 34)); // WHERE custom_Order IN (12, 34)
     * $query->filterByCustomOrder(array('min' => 12)); // WHERE custom_Order > 12
     * </code>
     *
     * @param     mixed $customOrder The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonCustomMasterQuery The current query, for fluid interface
     */
    public function filterByCustomOrder($customOrder = null, $comparison = null)
    {
        if (is_array($customOrder)) {
            $useMinMax = false;
            if (isset($customOrder['min'])) {
                $this->addUsingAlias(PersonCustomMasterTableMap::COL_CUSTOM_ORDER, $customOrder['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($customOrder['max'])) {
                $this->addUsingAlias(PersonCustomMasterTableMap::COL_CUSTOM_ORDER, $customOrder['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PersonCustomMasterTableMap::COL_CUSTOM_ORDER, $customOrder, $comparison);
    }

    /**
     * Filter the query on the custom_Field column
     *
     * Example usage:
     * <code>
     * $query->filterByCustomField('fooValue');   // WHERE custom_Field = 'fooValue'
     * $query->filterByCustomField('%fooValue%', Criteria::LIKE); // WHERE custom_Field LIKE '%fooValue%'
     * </code>
     *
     * @param     string $customField The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonCustomMasterQuery The current query, for fluid interface
     */
    public function filterByCustomField($customField = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($customField)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PersonCustomMasterTableMap::COL_CUSTOM_FIELD, $customField, $comparison);
    }

    /**
     * Filter the query on the custom_Name column
     *
     * Example usage:
     * <code>
     * $query->filterByCustomName('fooValue');   // WHERE custom_Name = 'fooValue'
     * $query->filterByCustomName('%fooValue%', Criteria::LIKE); // WHERE custom_Name LIKE '%fooValue%'
     * </code>
     *
     * @param     string $customName The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonCustomMasterQuery The current query, for fluid interface
     */
    public function filterByCustomName($customName = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($customName)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PersonCustomMasterTableMap::COL_CUSTOM_NAME, $customName, $comparison);
    }

    /**
     * Filter the query on the custom_Special column
     *
     * Example usage:
     * <code>
     * $query->filterByCustomSpecial(1234); // WHERE custom_Special = 1234
     * $query->filterByCustomSpecial(array(12, 34)); // WHERE custom_Special IN (12, 34)
     * $query->filterByCustomSpecial(array('min' => 12)); // WHERE custom_Special > 12
     * </code>
     *
     * @param     mixed $customSpecial The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonCustomMasterQuery The current query, for fluid interface
     */
    public function filterByCustomSpecial($customSpecial = null, $comparison = null)
    {
        if (is_array($customSpecial)) {
            $useMinMax = false;
            if (isset($customSpecial['min'])) {
                $this->addUsingAlias(PersonCustomMasterTableMap::COL_CUSTOM_SPECIAL, $customSpecial['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($customSpecial['max'])) {
                $this->addUsingAlias(PersonCustomMasterTableMap::COL_CUSTOM_SPECIAL, $customSpecial['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PersonCustomMasterTableMap::COL_CUSTOM_SPECIAL, $customSpecial, $comparison);
    }

    /**
     * Filter the query on the custom_Side column
     *
     * Example usage:
     * <code>
     * $query->filterByCustomSide('fooValue');   // WHERE custom_Side = 'fooValue'
     * $query->filterByCustomSide('%fooValue%', Criteria::LIKE); // WHERE custom_Side LIKE '%fooValue%'
     * </code>
     *
     * @param     string $customSide The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonCustomMasterQuery The current query, for fluid interface
     */
    public function filterByCustomSide($customSide = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($customSide)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PersonCustomMasterTableMap::COL_CUSTOM_SIDE, $customSide, $comparison);
    }

    /**
     * Filter the query on the custom_FieldSec column
     *
     * Example usage:
     * <code>
     * $query->filterByCustomFieldSec(1234); // WHERE custom_FieldSec = 1234
     * $query->filterByCustomFieldSec(array(12, 34)); // WHERE custom_FieldSec IN (12, 34)
     * $query->filterByCustomFieldSec(array('min' => 12)); // WHERE custom_FieldSec > 12
     * </code>
     *
     * @param     mixed $customFieldSec The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonCustomMasterQuery The current query, for fluid interface
     */
    public function filterByCustomFieldSec($customFieldSec = null, $comparison = null)
    {
        if (is_array($customFieldSec)) {
            $useMinMax = false;
            if (isset($customFieldSec['min'])) {
                $this->addUsingAlias(PersonCustomMasterTableMap::COL_CUSTOM_FIELDSEC, $customFieldSec['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($customFieldSec['max'])) {
                $this->addUsingAlias(PersonCustomMasterTableMap::COL_CUSTOM_FIELDSEC, $customFieldSec['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PersonCustomMasterTableMap::COL_CUSTOM_FIELDSEC, $customFieldSec, $comparison);
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
     * @return $this|ChildPersonCustomMasterQuery The current query, for fluid interface
     */
    public function filterByTypeId($typeId = null, $comparison = null)
    {
        if (is_array($typeId)) {
            $useMinMax = false;
            if (isset($typeId['min'])) {
                $this->addUsingAlias(PersonCustomMasterTableMap::COL_TYPE_ID, $typeId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($typeId['max'])) {
                $this->addUsingAlias(PersonCustomMasterTableMap::COL_TYPE_ID, $typeId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PersonCustomMasterTableMap::COL_TYPE_ID, $typeId, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildPersonCustomMaster $personCustomMaster Object to remove from the list of results
     *
     * @return $this|ChildPersonCustomMasterQuery The current query, for fluid interface
     */
    public function prune($personCustomMaster = null)
    {
        if ($personCustomMaster) {
            throw new LogicException('PersonCustomMaster object has no primary key');

        }

        return $this;
    }

    /**
     * Deletes all rows from the person_custom_master table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(PersonCustomMasterTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            PersonCustomMasterTableMap::clearInstancePool();
            PersonCustomMasterTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(PersonCustomMasterTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(PersonCustomMasterTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            PersonCustomMasterTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            PersonCustomMasterTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // PersonCustomMasterQuery
