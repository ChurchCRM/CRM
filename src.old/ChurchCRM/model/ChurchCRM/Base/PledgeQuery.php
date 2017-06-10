<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\Pledge as ChildPledge;
use ChurchCRM\PledgeQuery as ChildPledgeQuery;
use ChurchCRM\Map\PledgeTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\ModelJoin;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'pledge_plg' table.
 *
 *
 *
 * @method     ChildPledgeQuery orderById($order = Criteria::ASC) Order by the plg_plgID column
 * @method     ChildPledgeQuery orderByFamId($order = Criteria::ASC) Order by the plg_FamID column
 * @method     ChildPledgeQuery orderByFyid($order = Criteria::ASC) Order by the plg_FYID column
 * @method     ChildPledgeQuery orderByDate($order = Criteria::ASC) Order by the plg_date column
 * @method     ChildPledgeQuery orderByAmount($order = Criteria::ASC) Order by the plg_amount column
 * @method     ChildPledgeQuery orderBySchedule($order = Criteria::ASC) Order by the plg_schedule column
 * @method     ChildPledgeQuery orderByMethod($order = Criteria::ASC) Order by the plg_method column
 * @method     ChildPledgeQuery orderByComment($order = Criteria::ASC) Order by the plg_comment column
 * @method     ChildPledgeQuery orderByDatelastedited($order = Criteria::ASC) Order by the plg_DateLastEdited column
 * @method     ChildPledgeQuery orderByEditedby($order = Criteria::ASC) Order by the plg_EditedBy column
 * @method     ChildPledgeQuery orderByPledgeorpayment($order = Criteria::ASC) Order by the plg_PledgeOrPayment column
 * @method     ChildPledgeQuery orderByFundid($order = Criteria::ASC) Order by the plg_fundID column
 * @method     ChildPledgeQuery orderByDepid($order = Criteria::ASC) Order by the plg_depID column
 * @method     ChildPledgeQuery orderByCheckno($order = Criteria::ASC) Order by the plg_CheckNo column
 * @method     ChildPledgeQuery orderByProblem($order = Criteria::ASC) Order by the plg_Problem column
 * @method     ChildPledgeQuery orderByScanstring($order = Criteria::ASC) Order by the plg_scanString column
 * @method     ChildPledgeQuery orderByAutId($order = Criteria::ASC) Order by the plg_aut_ID column
 * @method     ChildPledgeQuery orderByAutCleared($order = Criteria::ASC) Order by the plg_aut_Cleared column
 * @method     ChildPledgeQuery orderByAutResultid($order = Criteria::ASC) Order by the plg_aut_ResultID column
 * @method     ChildPledgeQuery orderByNondeductible($order = Criteria::ASC) Order by the plg_NonDeductible column
 * @method     ChildPledgeQuery orderByGroupkey($order = Criteria::ASC) Order by the plg_GroupKey column
 *
 * @method     ChildPledgeQuery groupById() Group by the plg_plgID column
 * @method     ChildPledgeQuery groupByFamId() Group by the plg_FamID column
 * @method     ChildPledgeQuery groupByFyid() Group by the plg_FYID column
 * @method     ChildPledgeQuery groupByDate() Group by the plg_date column
 * @method     ChildPledgeQuery groupByAmount() Group by the plg_amount column
 * @method     ChildPledgeQuery groupBySchedule() Group by the plg_schedule column
 * @method     ChildPledgeQuery groupByMethod() Group by the plg_method column
 * @method     ChildPledgeQuery groupByComment() Group by the plg_comment column
 * @method     ChildPledgeQuery groupByDatelastedited() Group by the plg_DateLastEdited column
 * @method     ChildPledgeQuery groupByEditedby() Group by the plg_EditedBy column
 * @method     ChildPledgeQuery groupByPledgeorpayment() Group by the plg_PledgeOrPayment column
 * @method     ChildPledgeQuery groupByFundid() Group by the plg_fundID column
 * @method     ChildPledgeQuery groupByDepid() Group by the plg_depID column
 * @method     ChildPledgeQuery groupByCheckno() Group by the plg_CheckNo column
 * @method     ChildPledgeQuery groupByProblem() Group by the plg_Problem column
 * @method     ChildPledgeQuery groupByScanstring() Group by the plg_scanString column
 * @method     ChildPledgeQuery groupByAutId() Group by the plg_aut_ID column
 * @method     ChildPledgeQuery groupByAutCleared() Group by the plg_aut_Cleared column
 * @method     ChildPledgeQuery groupByAutResultid() Group by the plg_aut_ResultID column
 * @method     ChildPledgeQuery groupByNondeductible() Group by the plg_NonDeductible column
 * @method     ChildPledgeQuery groupByGroupkey() Group by the plg_GroupKey column
 *
 * @method     ChildPledgeQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildPledgeQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildPledgeQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildPledgeQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildPledgeQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildPledgeQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildPledgeQuery leftJoinDeposit($relationAlias = null) Adds a LEFT JOIN clause to the query using the Deposit relation
 * @method     ChildPledgeQuery rightJoinDeposit($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Deposit relation
 * @method     ChildPledgeQuery innerJoinDeposit($relationAlias = null) Adds a INNER JOIN clause to the query using the Deposit relation
 *
 * @method     ChildPledgeQuery joinWithDeposit($joinType = Criteria::INNER_JOIN) Adds a join clause and with to the query using the Deposit relation
 *
 * @method     ChildPledgeQuery leftJoinWithDeposit() Adds a LEFT JOIN clause and with to the query using the Deposit relation
 * @method     ChildPledgeQuery rightJoinWithDeposit() Adds a RIGHT JOIN clause and with to the query using the Deposit relation
 * @method     ChildPledgeQuery innerJoinWithDeposit() Adds a INNER JOIN clause and with to the query using the Deposit relation
 *
 * @method     ChildPledgeQuery leftJoinDonationFund($relationAlias = null) Adds a LEFT JOIN clause to the query using the DonationFund relation
 * @method     ChildPledgeQuery rightJoinDonationFund($relationAlias = null) Adds a RIGHT JOIN clause to the query using the DonationFund relation
 * @method     ChildPledgeQuery innerJoinDonationFund($relationAlias = null) Adds a INNER JOIN clause to the query using the DonationFund relation
 *
 * @method     ChildPledgeQuery joinWithDonationFund($joinType = Criteria::INNER_JOIN) Adds a join clause and with to the query using the DonationFund relation
 *
 * @method     ChildPledgeQuery leftJoinWithDonationFund() Adds a LEFT JOIN clause and with to the query using the DonationFund relation
 * @method     ChildPledgeQuery rightJoinWithDonationFund() Adds a RIGHT JOIN clause and with to the query using the DonationFund relation
 * @method     ChildPledgeQuery innerJoinWithDonationFund() Adds a INNER JOIN clause and with to the query using the DonationFund relation
 *
 * @method     ChildPledgeQuery leftJoinFamily($relationAlias = null) Adds a LEFT JOIN clause to the query using the Family relation
 * @method     ChildPledgeQuery rightJoinFamily($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Family relation
 * @method     ChildPledgeQuery innerJoinFamily($relationAlias = null) Adds a INNER JOIN clause to the query using the Family relation
 *
 * @method     ChildPledgeQuery joinWithFamily($joinType = Criteria::INNER_JOIN) Adds a join clause and with to the query using the Family relation
 *
 * @method     ChildPledgeQuery leftJoinWithFamily() Adds a LEFT JOIN clause and with to the query using the Family relation
 * @method     ChildPledgeQuery rightJoinWithFamily() Adds a RIGHT JOIN clause and with to the query using the Family relation
 * @method     ChildPledgeQuery innerJoinWithFamily() Adds a INNER JOIN clause and with to the query using the Family relation
 *
 * @method     \ChurchCRM\DepositQuery|\ChurchCRM\DonationFundQuery|\ChurchCRM\FamilyQuery endUse() Finalizes a secondary criteria and merges it with its primary Criteria
 *
 * @method     ChildPledge findOne(ConnectionInterface $con = null) Return the first ChildPledge matching the query
 * @method     ChildPledge findOneOrCreate(ConnectionInterface $con = null) Return the first ChildPledge matching the query, or a new ChildPledge object populated from the query conditions when no match is found
 *
 * @method     ChildPledge findOneById(int $plg_plgID) Return the first ChildPledge filtered by the plg_plgID column
 * @method     ChildPledge findOneByFamId(int $plg_FamID) Return the first ChildPledge filtered by the plg_FamID column
 * @method     ChildPledge findOneByFyid(int $plg_FYID) Return the first ChildPledge filtered by the plg_FYID column
 * @method     ChildPledge findOneByDate(string $plg_date) Return the first ChildPledge filtered by the plg_date column
 * @method     ChildPledge findOneByAmount(string $plg_amount) Return the first ChildPledge filtered by the plg_amount column
 * @method     ChildPledge findOneBySchedule(string $plg_schedule) Return the first ChildPledge filtered by the plg_schedule column
 * @method     ChildPledge findOneByMethod(string $plg_method) Return the first ChildPledge filtered by the plg_method column
 * @method     ChildPledge findOneByComment(string $plg_comment) Return the first ChildPledge filtered by the plg_comment column
 * @method     ChildPledge findOneByDatelastedited(string $plg_DateLastEdited) Return the first ChildPledge filtered by the plg_DateLastEdited column
 * @method     ChildPledge findOneByEditedby(int $plg_EditedBy) Return the first ChildPledge filtered by the plg_EditedBy column
 * @method     ChildPledge findOneByPledgeorpayment(string $plg_PledgeOrPayment) Return the first ChildPledge filtered by the plg_PledgeOrPayment column
 * @method     ChildPledge findOneByFundid(int $plg_fundID) Return the first ChildPledge filtered by the plg_fundID column
 * @method     ChildPledge findOneByDepid(int $plg_depID) Return the first ChildPledge filtered by the plg_depID column
 * @method     ChildPledge findOneByCheckno(string $plg_CheckNo) Return the first ChildPledge filtered by the plg_CheckNo column
 * @method     ChildPledge findOneByProblem(boolean $plg_Problem) Return the first ChildPledge filtered by the plg_Problem column
 * @method     ChildPledge findOneByScanstring(string $plg_scanString) Return the first ChildPledge filtered by the plg_scanString column
 * @method     ChildPledge findOneByAutId(int $plg_aut_ID) Return the first ChildPledge filtered by the plg_aut_ID column
 * @method     ChildPledge findOneByAutCleared(boolean $plg_aut_Cleared) Return the first ChildPledge filtered by the plg_aut_Cleared column
 * @method     ChildPledge findOneByAutResultid(int $plg_aut_ResultID) Return the first ChildPledge filtered by the plg_aut_ResultID column
 * @method     ChildPledge findOneByNondeductible(string $plg_NonDeductible) Return the first ChildPledge filtered by the plg_NonDeductible column
 * @method     ChildPledge findOneByGroupkey(string $plg_GroupKey) Return the first ChildPledge filtered by the plg_GroupKey column *

 * @method     ChildPledge requirePk($key, ConnectionInterface $con = null) Return the ChildPledge by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPledge requireOne(ConnectionInterface $con = null) Return the first ChildPledge matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildPledge requireOneById(int $plg_plgID) Return the first ChildPledge filtered by the plg_plgID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPledge requireOneByFamId(int $plg_FamID) Return the first ChildPledge filtered by the plg_FamID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPledge requireOneByFyid(int $plg_FYID) Return the first ChildPledge filtered by the plg_FYID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPledge requireOneByDate(string $plg_date) Return the first ChildPledge filtered by the plg_date column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPledge requireOneByAmount(string $plg_amount) Return the first ChildPledge filtered by the plg_amount column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPledge requireOneBySchedule(string $plg_schedule) Return the first ChildPledge filtered by the plg_schedule column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPledge requireOneByMethod(string $plg_method) Return the first ChildPledge filtered by the plg_method column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPledge requireOneByComment(string $plg_comment) Return the first ChildPledge filtered by the plg_comment column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPledge requireOneByDatelastedited(string $plg_DateLastEdited) Return the first ChildPledge filtered by the plg_DateLastEdited column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPledge requireOneByEditedby(int $plg_EditedBy) Return the first ChildPledge filtered by the plg_EditedBy column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPledge requireOneByPledgeorpayment(string $plg_PledgeOrPayment) Return the first ChildPledge filtered by the plg_PledgeOrPayment column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPledge requireOneByFundid(int $plg_fundID) Return the first ChildPledge filtered by the plg_fundID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPledge requireOneByDepid(int $plg_depID) Return the first ChildPledge filtered by the plg_depID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPledge requireOneByCheckno(string $plg_CheckNo) Return the first ChildPledge filtered by the plg_CheckNo column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPledge requireOneByProblem(boolean $plg_Problem) Return the first ChildPledge filtered by the plg_Problem column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPledge requireOneByScanstring(string $plg_scanString) Return the first ChildPledge filtered by the plg_scanString column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPledge requireOneByAutId(int $plg_aut_ID) Return the first ChildPledge filtered by the plg_aut_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPledge requireOneByAutCleared(boolean $plg_aut_Cleared) Return the first ChildPledge filtered by the plg_aut_Cleared column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPledge requireOneByAutResultid(int $plg_aut_ResultID) Return the first ChildPledge filtered by the plg_aut_ResultID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPledge requireOneByNondeductible(string $plg_NonDeductible) Return the first ChildPledge filtered by the plg_NonDeductible column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPledge requireOneByGroupkey(string $plg_GroupKey) Return the first ChildPledge filtered by the plg_GroupKey column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildPledge[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildPledge objects based on current ModelCriteria
 * @method     ChildPledge[]|ObjectCollection findById(int $plg_plgID) Return ChildPledge objects filtered by the plg_plgID column
 * @method     ChildPledge[]|ObjectCollection findByFamId(int $plg_FamID) Return ChildPledge objects filtered by the plg_FamID column
 * @method     ChildPledge[]|ObjectCollection findByFyid(int $plg_FYID) Return ChildPledge objects filtered by the plg_FYID column
 * @method     ChildPledge[]|ObjectCollection findByDate(string $plg_date) Return ChildPledge objects filtered by the plg_date column
 * @method     ChildPledge[]|ObjectCollection findByAmount(string $plg_amount) Return ChildPledge objects filtered by the plg_amount column
 * @method     ChildPledge[]|ObjectCollection findBySchedule(string $plg_schedule) Return ChildPledge objects filtered by the plg_schedule column
 * @method     ChildPledge[]|ObjectCollection findByMethod(string $plg_method) Return ChildPledge objects filtered by the plg_method column
 * @method     ChildPledge[]|ObjectCollection findByComment(string $plg_comment) Return ChildPledge objects filtered by the plg_comment column
 * @method     ChildPledge[]|ObjectCollection findByDatelastedited(string $plg_DateLastEdited) Return ChildPledge objects filtered by the plg_DateLastEdited column
 * @method     ChildPledge[]|ObjectCollection findByEditedby(int $plg_EditedBy) Return ChildPledge objects filtered by the plg_EditedBy column
 * @method     ChildPledge[]|ObjectCollection findByPledgeorpayment(string $plg_PledgeOrPayment) Return ChildPledge objects filtered by the plg_PledgeOrPayment column
 * @method     ChildPledge[]|ObjectCollection findByFundid(int $plg_fundID) Return ChildPledge objects filtered by the plg_fundID column
 * @method     ChildPledge[]|ObjectCollection findByDepid(int $plg_depID) Return ChildPledge objects filtered by the plg_depID column
 * @method     ChildPledge[]|ObjectCollection findByCheckno(string $plg_CheckNo) Return ChildPledge objects filtered by the plg_CheckNo column
 * @method     ChildPledge[]|ObjectCollection findByProblem(boolean $plg_Problem) Return ChildPledge objects filtered by the plg_Problem column
 * @method     ChildPledge[]|ObjectCollection findByScanstring(string $plg_scanString) Return ChildPledge objects filtered by the plg_scanString column
 * @method     ChildPledge[]|ObjectCollection findByAutId(int $plg_aut_ID) Return ChildPledge objects filtered by the plg_aut_ID column
 * @method     ChildPledge[]|ObjectCollection findByAutCleared(boolean $plg_aut_Cleared) Return ChildPledge objects filtered by the plg_aut_Cleared column
 * @method     ChildPledge[]|ObjectCollection findByAutResultid(int $plg_aut_ResultID) Return ChildPledge objects filtered by the plg_aut_ResultID column
 * @method     ChildPledge[]|ObjectCollection findByNondeductible(string $plg_NonDeductible) Return ChildPledge objects filtered by the plg_NonDeductible column
 * @method     ChildPledge[]|ObjectCollection findByGroupkey(string $plg_GroupKey) Return ChildPledge objects filtered by the plg_GroupKey column
 * @method     ChildPledge[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class PledgeQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\PledgeQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\Pledge', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildPledgeQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildPledgeQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildPledgeQuery) {
            return $criteria;
        }
        $query = new ChildPledgeQuery();
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
     * @return ChildPledge|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(PledgeTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (
            $this->formatter || $this->modelAlias || $this->with || $this->select
            || $this->selectColumns || $this->asColumns || $this->selectModifiers
            || $this->map || $this->having || $this->joins
        ) {
            return $this->findPkComplex($key, $con);
        }

        if ((null !== ($obj = PledgeTableMap::getInstanceFromPool(null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key)))) {
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
     * @return ChildPledge A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT plg_plgID, plg_FamID, plg_FYID, plg_date, plg_amount, plg_schedule, plg_method, plg_comment, plg_DateLastEdited, plg_EditedBy, plg_PledgeOrPayment, plg_fundID, plg_depID, plg_CheckNo, plg_Problem, plg_scanString, plg_aut_ID, plg_aut_Cleared, plg_aut_ResultID, plg_NonDeductible, plg_GroupKey FROM pledge_plg WHERE plg_plgID = :p0';
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
            /** @var ChildPledge $obj */
            $obj = new ChildPledge();
            $obj->hydrate($row);
            PledgeTableMap::addInstanceToPool($obj, null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key);
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
     * @return ChildPledge|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildPledgeQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(PledgeTableMap::COL_PLG_PLGID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildPledgeQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(PledgeTableMap::COL_PLG_PLGID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the plg_plgID column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE plg_plgID = 1234
     * $query->filterById(array(12, 34)); // WHERE plg_plgID IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE plg_plgID > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPledgeQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(PledgeTableMap::COL_PLG_PLGID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(PledgeTableMap::COL_PLG_PLGID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PledgeTableMap::COL_PLG_PLGID, $id, $comparison);
    }

    /**
     * Filter the query on the plg_FamID column
     *
     * Example usage:
     * <code>
     * $query->filterByFamId(1234); // WHERE plg_FamID = 1234
     * $query->filterByFamId(array(12, 34)); // WHERE plg_FamID IN (12, 34)
     * $query->filterByFamId(array('min' => 12)); // WHERE plg_FamID > 12
     * </code>
     *
     * @see       filterByFamily()
     *
     * @param     mixed $famId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPledgeQuery The current query, for fluid interface
     */
    public function filterByFamId($famId = null, $comparison = null)
    {
        if (is_array($famId)) {
            $useMinMax = false;
            if (isset($famId['min'])) {
                $this->addUsingAlias(PledgeTableMap::COL_PLG_FAMID, $famId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($famId['max'])) {
                $this->addUsingAlias(PledgeTableMap::COL_PLG_FAMID, $famId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PledgeTableMap::COL_PLG_FAMID, $famId, $comparison);
    }

    /**
     * Filter the query on the plg_FYID column
     *
     * Example usage:
     * <code>
     * $query->filterByFyid(1234); // WHERE plg_FYID = 1234
     * $query->filterByFyid(array(12, 34)); // WHERE plg_FYID IN (12, 34)
     * $query->filterByFyid(array('min' => 12)); // WHERE plg_FYID > 12
     * </code>
     *
     * @param     mixed $fyid The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPledgeQuery The current query, for fluid interface
     */
    public function filterByFyid($fyid = null, $comparison = null)
    {
        if (is_array($fyid)) {
            $useMinMax = false;
            if (isset($fyid['min'])) {
                $this->addUsingAlias(PledgeTableMap::COL_PLG_FYID, $fyid['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($fyid['max'])) {
                $this->addUsingAlias(PledgeTableMap::COL_PLG_FYID, $fyid['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PledgeTableMap::COL_PLG_FYID, $fyid, $comparison);
    }

    /**
     * Filter the query on the plg_date column
     *
     * Example usage:
     * <code>
     * $query->filterByDate('2011-03-14'); // WHERE plg_date = '2011-03-14'
     * $query->filterByDate('now'); // WHERE plg_date = '2011-03-14'
     * $query->filterByDate(array('max' => 'yesterday')); // WHERE plg_date > '2011-03-13'
     * </code>
     *
     * @param     mixed $date The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPledgeQuery The current query, for fluid interface
     */
    public function filterByDate($date = null, $comparison = null)
    {
        if (is_array($date)) {
            $useMinMax = false;
            if (isset($date['min'])) {
                $this->addUsingAlias(PledgeTableMap::COL_PLG_DATE, $date['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($date['max'])) {
                $this->addUsingAlias(PledgeTableMap::COL_PLG_DATE, $date['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PledgeTableMap::COL_PLG_DATE, $date, $comparison);
    }

    /**
     * Filter the query on the plg_amount column
     *
     * Example usage:
     * <code>
     * $query->filterByAmount(1234); // WHERE plg_amount = 1234
     * $query->filterByAmount(array(12, 34)); // WHERE plg_amount IN (12, 34)
     * $query->filterByAmount(array('min' => 12)); // WHERE plg_amount > 12
     * </code>
     *
     * @param     mixed $amount The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPledgeQuery The current query, for fluid interface
     */
    public function filterByAmount($amount = null, $comparison = null)
    {
        if (is_array($amount)) {
            $useMinMax = false;
            if (isset($amount['min'])) {
                $this->addUsingAlias(PledgeTableMap::COL_PLG_AMOUNT, $amount['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($amount['max'])) {
                $this->addUsingAlias(PledgeTableMap::COL_PLG_AMOUNT, $amount['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PledgeTableMap::COL_PLG_AMOUNT, $amount, $comparison);
    }

    /**
     * Filter the query on the plg_schedule column
     *
     * Example usage:
     * <code>
     * $query->filterBySchedule('fooValue');   // WHERE plg_schedule = 'fooValue'
     * $query->filterBySchedule('%fooValue%', Criteria::LIKE); // WHERE plg_schedule LIKE '%fooValue%'
     * </code>
     *
     * @param     string $schedule The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPledgeQuery The current query, for fluid interface
     */
    public function filterBySchedule($schedule = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($schedule)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PledgeTableMap::COL_PLG_SCHEDULE, $schedule, $comparison);
    }

    /**
     * Filter the query on the plg_method column
     *
     * Example usage:
     * <code>
     * $query->filterByMethod('fooValue');   // WHERE plg_method = 'fooValue'
     * $query->filterByMethod('%fooValue%', Criteria::LIKE); // WHERE plg_method LIKE '%fooValue%'
     * </code>
     *
     * @param     string $method The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPledgeQuery The current query, for fluid interface
     */
    public function filterByMethod($method = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($method)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PledgeTableMap::COL_PLG_METHOD, $method, $comparison);
    }

    /**
     * Filter the query on the plg_comment column
     *
     * Example usage:
     * <code>
     * $query->filterByComment('fooValue');   // WHERE plg_comment = 'fooValue'
     * $query->filterByComment('%fooValue%', Criteria::LIKE); // WHERE plg_comment LIKE '%fooValue%'
     * </code>
     *
     * @param     string $comment The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPledgeQuery The current query, for fluid interface
     */
    public function filterByComment($comment = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($comment)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PledgeTableMap::COL_PLG_COMMENT, $comment, $comparison);
    }

    /**
     * Filter the query on the plg_DateLastEdited column
     *
     * Example usage:
     * <code>
     * $query->filterByDatelastedited('2011-03-14'); // WHERE plg_DateLastEdited = '2011-03-14'
     * $query->filterByDatelastedited('now'); // WHERE plg_DateLastEdited = '2011-03-14'
     * $query->filterByDatelastedited(array('max' => 'yesterday')); // WHERE plg_DateLastEdited > '2011-03-13'
     * </code>
     *
     * @param     mixed $datelastedited The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPledgeQuery The current query, for fluid interface
     */
    public function filterByDatelastedited($datelastedited = null, $comparison = null)
    {
        if (is_array($datelastedited)) {
            $useMinMax = false;
            if (isset($datelastedited['min'])) {
                $this->addUsingAlias(PledgeTableMap::COL_PLG_DATELASTEDITED, $datelastedited['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($datelastedited['max'])) {
                $this->addUsingAlias(PledgeTableMap::COL_PLG_DATELASTEDITED, $datelastedited['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PledgeTableMap::COL_PLG_DATELASTEDITED, $datelastedited, $comparison);
    }

    /**
     * Filter the query on the plg_EditedBy column
     *
     * Example usage:
     * <code>
     * $query->filterByEditedby(1234); // WHERE plg_EditedBy = 1234
     * $query->filterByEditedby(array(12, 34)); // WHERE plg_EditedBy IN (12, 34)
     * $query->filterByEditedby(array('min' => 12)); // WHERE plg_EditedBy > 12
     * </code>
     *
     * @param     mixed $editedby The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPledgeQuery The current query, for fluid interface
     */
    public function filterByEditedby($editedby = null, $comparison = null)
    {
        if (is_array($editedby)) {
            $useMinMax = false;
            if (isset($editedby['min'])) {
                $this->addUsingAlias(PledgeTableMap::COL_PLG_EDITEDBY, $editedby['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($editedby['max'])) {
                $this->addUsingAlias(PledgeTableMap::COL_PLG_EDITEDBY, $editedby['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PledgeTableMap::COL_PLG_EDITEDBY, $editedby, $comparison);
    }

    /**
     * Filter the query on the plg_PledgeOrPayment column
     *
     * Example usage:
     * <code>
     * $query->filterByPledgeorpayment('fooValue');   // WHERE plg_PledgeOrPayment = 'fooValue'
     * $query->filterByPledgeorpayment('%fooValue%', Criteria::LIKE); // WHERE plg_PledgeOrPayment LIKE '%fooValue%'
     * </code>
     *
     * @param     string $pledgeorpayment The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPledgeQuery The current query, for fluid interface
     */
    public function filterByPledgeorpayment($pledgeorpayment = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($pledgeorpayment)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PledgeTableMap::COL_PLG_PLEDGEORPAYMENT, $pledgeorpayment, $comparison);
    }

    /**
     * Filter the query on the plg_fundID column
     *
     * Example usage:
     * <code>
     * $query->filterByFundid(1234); // WHERE plg_fundID = 1234
     * $query->filterByFundid(array(12, 34)); // WHERE plg_fundID IN (12, 34)
     * $query->filterByFundid(array('min' => 12)); // WHERE plg_fundID > 12
     * </code>
     *
     * @see       filterByDonationFund()
     *
     * @param     mixed $fundid The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPledgeQuery The current query, for fluid interface
     */
    public function filterByFundid($fundid = null, $comparison = null)
    {
        if (is_array($fundid)) {
            $useMinMax = false;
            if (isset($fundid['min'])) {
                $this->addUsingAlias(PledgeTableMap::COL_PLG_FUNDID, $fundid['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($fundid['max'])) {
                $this->addUsingAlias(PledgeTableMap::COL_PLG_FUNDID, $fundid['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PledgeTableMap::COL_PLG_FUNDID, $fundid, $comparison);
    }

    /**
     * Filter the query on the plg_depID column
     *
     * Example usage:
     * <code>
     * $query->filterByDepid(1234); // WHERE plg_depID = 1234
     * $query->filterByDepid(array(12, 34)); // WHERE plg_depID IN (12, 34)
     * $query->filterByDepid(array('min' => 12)); // WHERE plg_depID > 12
     * </code>
     *
     * @see       filterByDeposit()
     *
     * @param     mixed $depid The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPledgeQuery The current query, for fluid interface
     */
    public function filterByDepid($depid = null, $comparison = null)
    {
        if (is_array($depid)) {
            $useMinMax = false;
            if (isset($depid['min'])) {
                $this->addUsingAlias(PledgeTableMap::COL_PLG_DEPID, $depid['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($depid['max'])) {
                $this->addUsingAlias(PledgeTableMap::COL_PLG_DEPID, $depid['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PledgeTableMap::COL_PLG_DEPID, $depid, $comparison);
    }

    /**
     * Filter the query on the plg_CheckNo column
     *
     * Example usage:
     * <code>
     * $query->filterByCheckno(1234); // WHERE plg_CheckNo = 1234
     * $query->filterByCheckno(array(12, 34)); // WHERE plg_CheckNo IN (12, 34)
     * $query->filterByCheckno(array('min' => 12)); // WHERE plg_CheckNo > 12
     * </code>
     *
     * @param     mixed $checkno The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPledgeQuery The current query, for fluid interface
     */
    public function filterByCheckno($checkno = null, $comparison = null)
    {
        if (is_array($checkno)) {
            $useMinMax = false;
            if (isset($checkno['min'])) {
                $this->addUsingAlias(PledgeTableMap::COL_PLG_CHECKNO, $checkno['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($checkno['max'])) {
                $this->addUsingAlias(PledgeTableMap::COL_PLG_CHECKNO, $checkno['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PledgeTableMap::COL_PLG_CHECKNO, $checkno, $comparison);
    }

    /**
     * Filter the query on the plg_Problem column
     *
     * Example usage:
     * <code>
     * $query->filterByProblem(true); // WHERE plg_Problem = true
     * $query->filterByProblem('yes'); // WHERE plg_Problem = true
     * </code>
     *
     * @param     boolean|string $problem The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPledgeQuery The current query, for fluid interface
     */
    public function filterByProblem($problem = null, $comparison = null)
    {
        if (is_string($problem)) {
            $problem = in_array(strtolower($problem), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(PledgeTableMap::COL_PLG_PROBLEM, $problem, $comparison);
    }

    /**
     * Filter the query on the plg_scanString column
     *
     * Example usage:
     * <code>
     * $query->filterByScanstring('fooValue');   // WHERE plg_scanString = 'fooValue'
     * $query->filterByScanstring('%fooValue%', Criteria::LIKE); // WHERE plg_scanString LIKE '%fooValue%'
     * </code>
     *
     * @param     string $scanstring The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPledgeQuery The current query, for fluid interface
     */
    public function filterByScanstring($scanstring = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($scanstring)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PledgeTableMap::COL_PLG_SCANSTRING, $scanstring, $comparison);
    }

    /**
     * Filter the query on the plg_aut_ID column
     *
     * Example usage:
     * <code>
     * $query->filterByAutId(1234); // WHERE plg_aut_ID = 1234
     * $query->filterByAutId(array(12, 34)); // WHERE plg_aut_ID IN (12, 34)
     * $query->filterByAutId(array('min' => 12)); // WHERE plg_aut_ID > 12
     * </code>
     *
     * @param     mixed $autId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPledgeQuery The current query, for fluid interface
     */
    public function filterByAutId($autId = null, $comparison = null)
    {
        if (is_array($autId)) {
            $useMinMax = false;
            if (isset($autId['min'])) {
                $this->addUsingAlias(PledgeTableMap::COL_PLG_AUT_ID, $autId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($autId['max'])) {
                $this->addUsingAlias(PledgeTableMap::COL_PLG_AUT_ID, $autId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PledgeTableMap::COL_PLG_AUT_ID, $autId, $comparison);
    }

    /**
     * Filter the query on the plg_aut_Cleared column
     *
     * Example usage:
     * <code>
     * $query->filterByAutCleared(true); // WHERE plg_aut_Cleared = true
     * $query->filterByAutCleared('yes'); // WHERE plg_aut_Cleared = true
     * </code>
     *
     * @param     boolean|string $autCleared The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPledgeQuery The current query, for fluid interface
     */
    public function filterByAutCleared($autCleared = null, $comparison = null)
    {
        if (is_string($autCleared)) {
            $autCleared = in_array(strtolower($autCleared), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(PledgeTableMap::COL_PLG_AUT_CLEARED, $autCleared, $comparison);
    }

    /**
     * Filter the query on the plg_aut_ResultID column
     *
     * Example usage:
     * <code>
     * $query->filterByAutResultid(1234); // WHERE plg_aut_ResultID = 1234
     * $query->filterByAutResultid(array(12, 34)); // WHERE plg_aut_ResultID IN (12, 34)
     * $query->filterByAutResultid(array('min' => 12)); // WHERE plg_aut_ResultID > 12
     * </code>
     *
     * @param     mixed $autResultid The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPledgeQuery The current query, for fluid interface
     */
    public function filterByAutResultid($autResultid = null, $comparison = null)
    {
        if (is_array($autResultid)) {
            $useMinMax = false;
            if (isset($autResultid['min'])) {
                $this->addUsingAlias(PledgeTableMap::COL_PLG_AUT_RESULTID, $autResultid['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($autResultid['max'])) {
                $this->addUsingAlias(PledgeTableMap::COL_PLG_AUT_RESULTID, $autResultid['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PledgeTableMap::COL_PLG_AUT_RESULTID, $autResultid, $comparison);
    }

    /**
     * Filter the query on the plg_NonDeductible column
     *
     * Example usage:
     * <code>
     * $query->filterByNondeductible(1234); // WHERE plg_NonDeductible = 1234
     * $query->filterByNondeductible(array(12, 34)); // WHERE plg_NonDeductible IN (12, 34)
     * $query->filterByNondeductible(array('min' => 12)); // WHERE plg_NonDeductible > 12
     * </code>
     *
     * @param     mixed $nondeductible The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPledgeQuery The current query, for fluid interface
     */
    public function filterByNondeductible($nondeductible = null, $comparison = null)
    {
        if (is_array($nondeductible)) {
            $useMinMax = false;
            if (isset($nondeductible['min'])) {
                $this->addUsingAlias(PledgeTableMap::COL_PLG_NONDEDUCTIBLE, $nondeductible['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($nondeductible['max'])) {
                $this->addUsingAlias(PledgeTableMap::COL_PLG_NONDEDUCTIBLE, $nondeductible['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PledgeTableMap::COL_PLG_NONDEDUCTIBLE, $nondeductible, $comparison);
    }

    /**
     * Filter the query on the plg_GroupKey column
     *
     * Example usage:
     * <code>
     * $query->filterByGroupkey('fooValue');   // WHERE plg_GroupKey = 'fooValue'
     * $query->filterByGroupkey('%fooValue%', Criteria::LIKE); // WHERE plg_GroupKey LIKE '%fooValue%'
     * </code>
     *
     * @param     string $groupkey The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPledgeQuery The current query, for fluid interface
     */
    public function filterByGroupkey($groupkey = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($groupkey)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PledgeTableMap::COL_PLG_GROUPKEY, $groupkey, $comparison);
    }

    /**
     * Filter the query by a related \ChurchCRM\Deposit object
     *
     * @param \ChurchCRM\Deposit|ObjectCollection $deposit The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return ChildPledgeQuery The current query, for fluid interface
     */
    public function filterByDeposit($deposit, $comparison = null)
    {
        if ($deposit instanceof \ChurchCRM\Deposit) {
            return $this
                ->addUsingAlias(PledgeTableMap::COL_PLG_DEPID, $deposit->getId(), $comparison);
        } elseif ($deposit instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(PledgeTableMap::COL_PLG_DEPID, $deposit->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByDeposit() only accepts arguments of type \ChurchCRM\Deposit or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Deposit relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return $this|ChildPledgeQuery The current query, for fluid interface
     */
    public function joinDeposit($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Deposit');

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
            $this->addJoinObject($join, 'Deposit');
        }

        return $this;
    }

    /**
     * Use the Deposit relation Deposit object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return \ChurchCRM\DepositQuery A secondary query class using the current class as primary query
     */
    public function useDepositQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinDeposit($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Deposit', '\ChurchCRM\DepositQuery');
    }

    /**
     * Filter the query by a related \ChurchCRM\DonationFund object
     *
     * @param \ChurchCRM\DonationFund|ObjectCollection $donationFund The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return ChildPledgeQuery The current query, for fluid interface
     */
    public function filterByDonationFund($donationFund, $comparison = null)
    {
        if ($donationFund instanceof \ChurchCRM\DonationFund) {
            return $this
                ->addUsingAlias(PledgeTableMap::COL_PLG_FUNDID, $donationFund->getId(), $comparison);
        } elseif ($donationFund instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(PledgeTableMap::COL_PLG_FUNDID, $donationFund->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByDonationFund() only accepts arguments of type \ChurchCRM\DonationFund or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the DonationFund relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return $this|ChildPledgeQuery The current query, for fluid interface
     */
    public function joinDonationFund($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('DonationFund');

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
            $this->addJoinObject($join, 'DonationFund');
        }

        return $this;
    }

    /**
     * Use the DonationFund relation DonationFund object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return \ChurchCRM\DonationFundQuery A secondary query class using the current class as primary query
     */
    public function useDonationFundQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinDonationFund($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'DonationFund', '\ChurchCRM\DonationFundQuery');
    }

    /**
     * Filter the query by a related \ChurchCRM\Family object
     *
     * @param \ChurchCRM\Family|ObjectCollection $family The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return ChildPledgeQuery The current query, for fluid interface
     */
    public function filterByFamily($family, $comparison = null)
    {
        if ($family instanceof \ChurchCRM\Family) {
            return $this
                ->addUsingAlias(PledgeTableMap::COL_PLG_FAMID, $family->getId(), $comparison);
        } elseif ($family instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(PledgeTableMap::COL_PLG_FAMID, $family->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByFamily() only accepts arguments of type \ChurchCRM\Family or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Family relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return $this|ChildPledgeQuery The current query, for fluid interface
     */
    public function joinFamily($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Family');

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
            $this->addJoinObject($join, 'Family');
        }

        return $this;
    }

    /**
     * Use the Family relation Family object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return \ChurchCRM\FamilyQuery A secondary query class using the current class as primary query
     */
    public function useFamilyQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinFamily($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Family', '\ChurchCRM\FamilyQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildPledge $pledge Object to remove from the list of results
     *
     * @return $this|ChildPledgeQuery The current query, for fluid interface
     */
    public function prune($pledge = null)
    {
        if ($pledge) {
            $this->addUsingAlias(PledgeTableMap::COL_PLG_PLGID, $pledge->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the pledge_plg table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(PledgeTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            PledgeTableMap::clearInstancePool();
            PledgeTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(PledgeTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(PledgeTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            PledgeTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            PledgeTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // PledgeQuery
