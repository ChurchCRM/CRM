<?php

namespace ChurchCRM\Base;

use \Exception;
use ChurchCRM\EmailRecipientPending as ChildEmailRecipientPending;
use ChurchCRM\EmailRecipientPendingQuery as ChildEmailRecipientPendingQuery;
use ChurchCRM\Map\EmailRecipientPendingTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\LogicException;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'email_recipient_pending_erp' table.
 *
 *
 *
 * @method     ChildEmailRecipientPendingQuery orderById($order = Criteria::ASC) Order by the erp_id column
 * @method     ChildEmailRecipientPendingQuery orderByUsrId($order = Criteria::ASC) Order by the erp_usr_id column
 * @method     ChildEmailRecipientPendingQuery orderByNumAttempt($order = Criteria::ASC) Order by the erp_num_attempt column
 * @method     ChildEmailRecipientPendingQuery orderByFailedTime($order = Criteria::ASC) Order by the erp_failed_time column
 * @method     ChildEmailRecipientPendingQuery orderByEmailAddress($order = Criteria::ASC) Order by the erp_email_address column
 *
 * @method     ChildEmailRecipientPendingQuery groupById() Group by the erp_id column
 * @method     ChildEmailRecipientPendingQuery groupByUsrId() Group by the erp_usr_id column
 * @method     ChildEmailRecipientPendingQuery groupByNumAttempt() Group by the erp_num_attempt column
 * @method     ChildEmailRecipientPendingQuery groupByFailedTime() Group by the erp_failed_time column
 * @method     ChildEmailRecipientPendingQuery groupByEmailAddress() Group by the erp_email_address column
 *
 * @method     ChildEmailRecipientPendingQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildEmailRecipientPendingQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildEmailRecipientPendingQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildEmailRecipientPendingQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildEmailRecipientPendingQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildEmailRecipientPendingQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildEmailRecipientPending findOne(ConnectionInterface $con = null) Return the first ChildEmailRecipientPending matching the query
 * @method     ChildEmailRecipientPending findOneOrCreate(ConnectionInterface $con = null) Return the first ChildEmailRecipientPending matching the query, or a new ChildEmailRecipientPending object populated from the query conditions when no match is found
 *
 * @method     ChildEmailRecipientPending findOneById(int $erp_id) Return the first ChildEmailRecipientPending filtered by the erp_id column
 * @method     ChildEmailRecipientPending findOneByUsrId(int $erp_usr_id) Return the first ChildEmailRecipientPending filtered by the erp_usr_id column
 * @method     ChildEmailRecipientPending findOneByNumAttempt(int $erp_num_attempt) Return the first ChildEmailRecipientPending filtered by the erp_num_attempt column
 * @method     ChildEmailRecipientPending findOneByFailedTime(string $erp_failed_time) Return the first ChildEmailRecipientPending filtered by the erp_failed_time column
 * @method     ChildEmailRecipientPending findOneByEmailAddress(string $erp_email_address) Return the first ChildEmailRecipientPending filtered by the erp_email_address column *

 * @method     ChildEmailRecipientPending requirePk($key, ConnectionInterface $con = null) Return the ChildEmailRecipientPending by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEmailRecipientPending requireOne(ConnectionInterface $con = null) Return the first ChildEmailRecipientPending matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildEmailRecipientPending requireOneById(int $erp_id) Return the first ChildEmailRecipientPending filtered by the erp_id column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEmailRecipientPending requireOneByUsrId(int $erp_usr_id) Return the first ChildEmailRecipientPending filtered by the erp_usr_id column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEmailRecipientPending requireOneByNumAttempt(int $erp_num_attempt) Return the first ChildEmailRecipientPending filtered by the erp_num_attempt column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEmailRecipientPending requireOneByFailedTime(string $erp_failed_time) Return the first ChildEmailRecipientPending filtered by the erp_failed_time column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEmailRecipientPending requireOneByEmailAddress(string $erp_email_address) Return the first ChildEmailRecipientPending filtered by the erp_email_address column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildEmailRecipientPending[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildEmailRecipientPending objects based on current ModelCriteria
 * @method     ChildEmailRecipientPending[]|ObjectCollection findById(int $erp_id) Return ChildEmailRecipientPending objects filtered by the erp_id column
 * @method     ChildEmailRecipientPending[]|ObjectCollection findByUsrId(int $erp_usr_id) Return ChildEmailRecipientPending objects filtered by the erp_usr_id column
 * @method     ChildEmailRecipientPending[]|ObjectCollection findByNumAttempt(int $erp_num_attempt) Return ChildEmailRecipientPending objects filtered by the erp_num_attempt column
 * @method     ChildEmailRecipientPending[]|ObjectCollection findByFailedTime(string $erp_failed_time) Return ChildEmailRecipientPending objects filtered by the erp_failed_time column
 * @method     ChildEmailRecipientPending[]|ObjectCollection findByEmailAddress(string $erp_email_address) Return ChildEmailRecipientPending objects filtered by the erp_email_address column
 * @method     ChildEmailRecipientPending[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class EmailRecipientPendingQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\EmailRecipientPendingQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\EmailRecipientPending', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildEmailRecipientPendingQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildEmailRecipientPendingQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildEmailRecipientPendingQuery) {
            return $criteria;
        }
        $query = new ChildEmailRecipientPendingQuery();
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
     * @return ChildEmailRecipientPending|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        throw new LogicException('The EmailRecipientPending object has no primary key');
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
        throw new LogicException('The EmailRecipientPending object has no primary key');
    }

    /**
     * Filter the query by primary key
     *
     * @param     mixed $key Primary key to use for the query
     *
     * @return $this|ChildEmailRecipientPendingQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {
        throw new LogicException('The EmailRecipientPending object has no primary key');
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildEmailRecipientPendingQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {
        throw new LogicException('The EmailRecipientPending object has no primary key');
    }

    /**
     * Filter the query on the erp_id column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE erp_id = 1234
     * $query->filterById(array(12, 34)); // WHERE erp_id IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE erp_id > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEmailRecipientPendingQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(EmailRecipientPendingTableMap::COL_ERP_ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(EmailRecipientPendingTableMap::COL_ERP_ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EmailRecipientPendingTableMap::COL_ERP_ID, $id, $comparison);
    }

    /**
     * Filter the query on the erp_usr_id column
     *
     * Example usage:
     * <code>
     * $query->filterByUsrId(1234); // WHERE erp_usr_id = 1234
     * $query->filterByUsrId(array(12, 34)); // WHERE erp_usr_id IN (12, 34)
     * $query->filterByUsrId(array('min' => 12)); // WHERE erp_usr_id > 12
     * </code>
     *
     * @param     mixed $usrId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEmailRecipientPendingQuery The current query, for fluid interface
     */
    public function filterByUsrId($usrId = null, $comparison = null)
    {
        if (is_array($usrId)) {
            $useMinMax = false;
            if (isset($usrId['min'])) {
                $this->addUsingAlias(EmailRecipientPendingTableMap::COL_ERP_USR_ID, $usrId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($usrId['max'])) {
                $this->addUsingAlias(EmailRecipientPendingTableMap::COL_ERP_USR_ID, $usrId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EmailRecipientPendingTableMap::COL_ERP_USR_ID, $usrId, $comparison);
    }

    /**
     * Filter the query on the erp_num_attempt column
     *
     * Example usage:
     * <code>
     * $query->filterByNumAttempt(1234); // WHERE erp_num_attempt = 1234
     * $query->filterByNumAttempt(array(12, 34)); // WHERE erp_num_attempt IN (12, 34)
     * $query->filterByNumAttempt(array('min' => 12)); // WHERE erp_num_attempt > 12
     * </code>
     *
     * @param     mixed $numAttempt The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEmailRecipientPendingQuery The current query, for fluid interface
     */
    public function filterByNumAttempt($numAttempt = null, $comparison = null)
    {
        if (is_array($numAttempt)) {
            $useMinMax = false;
            if (isset($numAttempt['min'])) {
                $this->addUsingAlias(EmailRecipientPendingTableMap::COL_ERP_NUM_ATTEMPT, $numAttempt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($numAttempt['max'])) {
                $this->addUsingAlias(EmailRecipientPendingTableMap::COL_ERP_NUM_ATTEMPT, $numAttempt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EmailRecipientPendingTableMap::COL_ERP_NUM_ATTEMPT, $numAttempt, $comparison);
    }

    /**
     * Filter the query on the erp_failed_time column
     *
     * Example usage:
     * <code>
     * $query->filterByFailedTime('2011-03-14'); // WHERE erp_failed_time = '2011-03-14'
     * $query->filterByFailedTime('now'); // WHERE erp_failed_time = '2011-03-14'
     * $query->filterByFailedTime(array('max' => 'yesterday')); // WHERE erp_failed_time > '2011-03-13'
     * </code>
     *
     * @param     mixed $failedTime The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEmailRecipientPendingQuery The current query, for fluid interface
     */
    public function filterByFailedTime($failedTime = null, $comparison = null)
    {
        if (is_array($failedTime)) {
            $useMinMax = false;
            if (isset($failedTime['min'])) {
                $this->addUsingAlias(EmailRecipientPendingTableMap::COL_ERP_FAILED_TIME, $failedTime['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($failedTime['max'])) {
                $this->addUsingAlias(EmailRecipientPendingTableMap::COL_ERP_FAILED_TIME, $failedTime['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EmailRecipientPendingTableMap::COL_ERP_FAILED_TIME, $failedTime, $comparison);
    }

    /**
     * Filter the query on the erp_email_address column
     *
     * Example usage:
     * <code>
     * $query->filterByEmailAddress('fooValue');   // WHERE erp_email_address = 'fooValue'
     * $query->filterByEmailAddress('%fooValue%', Criteria::LIKE); // WHERE erp_email_address LIKE '%fooValue%'
     * </code>
     *
     * @param     string $emailAddress The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEmailRecipientPendingQuery The current query, for fluid interface
     */
    public function filterByEmailAddress($emailAddress = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($emailAddress)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EmailRecipientPendingTableMap::COL_ERP_EMAIL_ADDRESS, $emailAddress, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildEmailRecipientPending $emailRecipientPending Object to remove from the list of results
     *
     * @return $this|ChildEmailRecipientPendingQuery The current query, for fluid interface
     */
    public function prune($emailRecipientPending = null)
    {
        if ($emailRecipientPending) {
            throw new LogicException('EmailRecipientPending object has no primary key');

        }

        return $this;
    }

    /**
     * Deletes all rows from the email_recipient_pending_erp table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(EmailRecipientPendingTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            EmailRecipientPendingTableMap::clearInstancePool();
            EmailRecipientPendingTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(EmailRecipientPendingTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(EmailRecipientPendingTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            EmailRecipientPendingTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            EmailRecipientPendingTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // EmailRecipientPendingQuery
