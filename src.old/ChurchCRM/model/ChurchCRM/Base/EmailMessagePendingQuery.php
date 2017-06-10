<?php

namespace ChurchCRM\Base;

use \Exception;
use ChurchCRM\EmailMessagePending as ChildEmailMessagePending;
use ChurchCRM\EmailMessagePendingQuery as ChildEmailMessagePendingQuery;
use ChurchCRM\Map\EmailMessagePendingTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\LogicException;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'email_message_pending_emp' table.
 *
 *
 *
 * @method     ChildEmailMessagePendingQuery orderByUsrId($order = Criteria::ASC) Order by the emp_usr_id column
 * @method     ChildEmailMessagePendingQuery orderByToSend($order = Criteria::ASC) Order by the emp_to_send column
 * @method     ChildEmailMessagePendingQuery orderBySubject($order = Criteria::ASC) Order by the emp_subject column
 * @method     ChildEmailMessagePendingQuery orderByMessage($order = Criteria::ASC) Order by the emp_message column
 * @method     ChildEmailMessagePendingQuery orderByAttachName($order = Criteria::ASC) Order by the emp_attach_name column
 * @method     ChildEmailMessagePendingQuery orderByAttach($order = Criteria::ASC) Order by the emp_attach column
 *
 * @method     ChildEmailMessagePendingQuery groupByUsrId() Group by the emp_usr_id column
 * @method     ChildEmailMessagePendingQuery groupByToSend() Group by the emp_to_send column
 * @method     ChildEmailMessagePendingQuery groupBySubject() Group by the emp_subject column
 * @method     ChildEmailMessagePendingQuery groupByMessage() Group by the emp_message column
 * @method     ChildEmailMessagePendingQuery groupByAttachName() Group by the emp_attach_name column
 * @method     ChildEmailMessagePendingQuery groupByAttach() Group by the emp_attach column
 *
 * @method     ChildEmailMessagePendingQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildEmailMessagePendingQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildEmailMessagePendingQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildEmailMessagePendingQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildEmailMessagePendingQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildEmailMessagePendingQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildEmailMessagePending findOne(ConnectionInterface $con = null) Return the first ChildEmailMessagePending matching the query
 * @method     ChildEmailMessagePending findOneOrCreate(ConnectionInterface $con = null) Return the first ChildEmailMessagePending matching the query, or a new ChildEmailMessagePending object populated from the query conditions when no match is found
 *
 * @method     ChildEmailMessagePending findOneByUsrId(int $emp_usr_id) Return the first ChildEmailMessagePending filtered by the emp_usr_id column
 * @method     ChildEmailMessagePending findOneByToSend(int $emp_to_send) Return the first ChildEmailMessagePending filtered by the emp_to_send column
 * @method     ChildEmailMessagePending findOneBySubject(string $emp_subject) Return the first ChildEmailMessagePending filtered by the emp_subject column
 * @method     ChildEmailMessagePending findOneByMessage(string $emp_message) Return the first ChildEmailMessagePending filtered by the emp_message column
 * @method     ChildEmailMessagePending findOneByAttachName(string $emp_attach_name) Return the first ChildEmailMessagePending filtered by the emp_attach_name column
 * @method     ChildEmailMessagePending findOneByAttach(boolean $emp_attach) Return the first ChildEmailMessagePending filtered by the emp_attach column *

 * @method     ChildEmailMessagePending requirePk($key, ConnectionInterface $con = null) Return the ChildEmailMessagePending by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEmailMessagePending requireOne(ConnectionInterface $con = null) Return the first ChildEmailMessagePending matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildEmailMessagePending requireOneByUsrId(int $emp_usr_id) Return the first ChildEmailMessagePending filtered by the emp_usr_id column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEmailMessagePending requireOneByToSend(int $emp_to_send) Return the first ChildEmailMessagePending filtered by the emp_to_send column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEmailMessagePending requireOneBySubject(string $emp_subject) Return the first ChildEmailMessagePending filtered by the emp_subject column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEmailMessagePending requireOneByMessage(string $emp_message) Return the first ChildEmailMessagePending filtered by the emp_message column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEmailMessagePending requireOneByAttachName(string $emp_attach_name) Return the first ChildEmailMessagePending filtered by the emp_attach_name column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildEmailMessagePending requireOneByAttach(boolean $emp_attach) Return the first ChildEmailMessagePending filtered by the emp_attach column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildEmailMessagePending[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildEmailMessagePending objects based on current ModelCriteria
 * @method     ChildEmailMessagePending[]|ObjectCollection findByUsrId(int $emp_usr_id) Return ChildEmailMessagePending objects filtered by the emp_usr_id column
 * @method     ChildEmailMessagePending[]|ObjectCollection findByToSend(int $emp_to_send) Return ChildEmailMessagePending objects filtered by the emp_to_send column
 * @method     ChildEmailMessagePending[]|ObjectCollection findBySubject(string $emp_subject) Return ChildEmailMessagePending objects filtered by the emp_subject column
 * @method     ChildEmailMessagePending[]|ObjectCollection findByMessage(string $emp_message) Return ChildEmailMessagePending objects filtered by the emp_message column
 * @method     ChildEmailMessagePending[]|ObjectCollection findByAttachName(string $emp_attach_name) Return ChildEmailMessagePending objects filtered by the emp_attach_name column
 * @method     ChildEmailMessagePending[]|ObjectCollection findByAttach(boolean $emp_attach) Return ChildEmailMessagePending objects filtered by the emp_attach column
 * @method     ChildEmailMessagePending[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class EmailMessagePendingQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\EmailMessagePendingQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\EmailMessagePending', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildEmailMessagePendingQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildEmailMessagePendingQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildEmailMessagePendingQuery) {
            return $criteria;
        }
        $query = new ChildEmailMessagePendingQuery();
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
     * @return ChildEmailMessagePending|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        throw new LogicException('The EmailMessagePending object has no primary key');
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
        throw new LogicException('The EmailMessagePending object has no primary key');
    }

    /**
     * Filter the query by primary key
     *
     * @param     mixed $key Primary key to use for the query
     *
     * @return $this|ChildEmailMessagePendingQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {
        throw new LogicException('The EmailMessagePending object has no primary key');
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildEmailMessagePendingQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {
        throw new LogicException('The EmailMessagePending object has no primary key');
    }

    /**
     * Filter the query on the emp_usr_id column
     *
     * Example usage:
     * <code>
     * $query->filterByUsrId(1234); // WHERE emp_usr_id = 1234
     * $query->filterByUsrId(array(12, 34)); // WHERE emp_usr_id IN (12, 34)
     * $query->filterByUsrId(array('min' => 12)); // WHERE emp_usr_id > 12
     * </code>
     *
     * @param     mixed $usrId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEmailMessagePendingQuery The current query, for fluid interface
     */
    public function filterByUsrId($usrId = null, $comparison = null)
    {
        if (is_array($usrId)) {
            $useMinMax = false;
            if (isset($usrId['min'])) {
                $this->addUsingAlias(EmailMessagePendingTableMap::COL_EMP_USR_ID, $usrId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($usrId['max'])) {
                $this->addUsingAlias(EmailMessagePendingTableMap::COL_EMP_USR_ID, $usrId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EmailMessagePendingTableMap::COL_EMP_USR_ID, $usrId, $comparison);
    }

    /**
     * Filter the query on the emp_to_send column
     *
     * Example usage:
     * <code>
     * $query->filterByToSend(1234); // WHERE emp_to_send = 1234
     * $query->filterByToSend(array(12, 34)); // WHERE emp_to_send IN (12, 34)
     * $query->filterByToSend(array('min' => 12)); // WHERE emp_to_send > 12
     * </code>
     *
     * @param     mixed $toSend The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEmailMessagePendingQuery The current query, for fluid interface
     */
    public function filterByToSend($toSend = null, $comparison = null)
    {
        if (is_array($toSend)) {
            $useMinMax = false;
            if (isset($toSend['min'])) {
                $this->addUsingAlias(EmailMessagePendingTableMap::COL_EMP_TO_SEND, $toSend['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($toSend['max'])) {
                $this->addUsingAlias(EmailMessagePendingTableMap::COL_EMP_TO_SEND, $toSend['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EmailMessagePendingTableMap::COL_EMP_TO_SEND, $toSend, $comparison);
    }

    /**
     * Filter the query on the emp_subject column
     *
     * Example usage:
     * <code>
     * $query->filterBySubject('fooValue');   // WHERE emp_subject = 'fooValue'
     * $query->filterBySubject('%fooValue%', Criteria::LIKE); // WHERE emp_subject LIKE '%fooValue%'
     * </code>
     *
     * @param     string $subject The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEmailMessagePendingQuery The current query, for fluid interface
     */
    public function filterBySubject($subject = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($subject)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EmailMessagePendingTableMap::COL_EMP_SUBJECT, $subject, $comparison);
    }

    /**
     * Filter the query on the emp_message column
     *
     * Example usage:
     * <code>
     * $query->filterByMessage('fooValue');   // WHERE emp_message = 'fooValue'
     * $query->filterByMessage('%fooValue%', Criteria::LIKE); // WHERE emp_message LIKE '%fooValue%'
     * </code>
     *
     * @param     string $message The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEmailMessagePendingQuery The current query, for fluid interface
     */
    public function filterByMessage($message = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($message)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EmailMessagePendingTableMap::COL_EMP_MESSAGE, $message, $comparison);
    }

    /**
     * Filter the query on the emp_attach_name column
     *
     * Example usage:
     * <code>
     * $query->filterByAttachName('fooValue');   // WHERE emp_attach_name = 'fooValue'
     * $query->filterByAttachName('%fooValue%', Criteria::LIKE); // WHERE emp_attach_name LIKE '%fooValue%'
     * </code>
     *
     * @param     string $attachName The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEmailMessagePendingQuery The current query, for fluid interface
     */
    public function filterByAttachName($attachName = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($attachName)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(EmailMessagePendingTableMap::COL_EMP_ATTACH_NAME, $attachName, $comparison);
    }

    /**
     * Filter the query on the emp_attach column
     *
     * Example usage:
     * <code>
     * $query->filterByAttach(true); // WHERE emp_attach = true
     * $query->filterByAttach('yes'); // WHERE emp_attach = true
     * </code>
     *
     * @param     boolean|string $attach The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildEmailMessagePendingQuery The current query, for fluid interface
     */
    public function filterByAttach($attach = null, $comparison = null)
    {
        if (is_string($attach)) {
            $attach = in_array(strtolower($attach), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(EmailMessagePendingTableMap::COL_EMP_ATTACH, $attach, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildEmailMessagePending $emailMessagePending Object to remove from the list of results
     *
     * @return $this|ChildEmailMessagePendingQuery The current query, for fluid interface
     */
    public function prune($emailMessagePending = null)
    {
        if ($emailMessagePending) {
            throw new LogicException('EmailMessagePending object has no primary key');

        }

        return $this;
    }

    /**
     * Deletes all rows from the email_message_pending_emp table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(EmailMessagePendingTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            EmailMessagePendingTableMap::clearInstancePool();
            EmailMessagePendingTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(EmailMessagePendingTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(EmailMessagePendingTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            EmailMessagePendingTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            EmailMessagePendingTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // EmailMessagePendingQuery
