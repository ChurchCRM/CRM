<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\AutoPayment as ChildAutoPayment;
use ChurchCRM\AutoPaymentQuery as ChildAutoPaymentQuery;
use ChurchCRM\Map\AutoPaymentTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'autopayment_aut' table.
 *
 *
 *
 * @method     ChildAutoPaymentQuery orderById($order = Criteria::ASC) Order by the aut_ID column
 * @method     ChildAutoPaymentQuery orderByFamilyid($order = Criteria::ASC) Order by the aut_FamID column
 * @method     ChildAutoPaymentQuery orderByEnableBankDraft($order = Criteria::ASC) Order by the aut_EnableBankDraft column
 * @method     ChildAutoPaymentQuery orderByEnableCreditCard($order = Criteria::ASC) Order by the aut_EnableCreditCard column
 * @method     ChildAutoPaymentQuery orderByNextPayDate($order = Criteria::ASC) Order by the aut_NextPayDate column
 * @method     ChildAutoPaymentQuery orderByFyid($order = Criteria::ASC) Order by the aut_FYID column
 * @method     ChildAutoPaymentQuery orderByAmount($order = Criteria::ASC) Order by the aut_Amount column
 * @method     ChildAutoPaymentQuery orderByInterval($order = Criteria::ASC) Order by the aut_Interval column
 * @method     ChildAutoPaymentQuery orderByFund($order = Criteria::ASC) Order by the aut_Fund column
 * @method     ChildAutoPaymentQuery orderByFirstName($order = Criteria::ASC) Order by the aut_FirstName column
 * @method     ChildAutoPaymentQuery orderByLastName($order = Criteria::ASC) Order by the aut_LastName column
 * @method     ChildAutoPaymentQuery orderByAddress1($order = Criteria::ASC) Order by the aut_Address1 column
 * @method     ChildAutoPaymentQuery orderByAddress2($order = Criteria::ASC) Order by the aut_Address2 column
 * @method     ChildAutoPaymentQuery orderByCity($order = Criteria::ASC) Order by the aut_City column
 * @method     ChildAutoPaymentQuery orderByState($order = Criteria::ASC) Order by the aut_State column
 * @method     ChildAutoPaymentQuery orderByZip($order = Criteria::ASC) Order by the aut_Zip column
 * @method     ChildAutoPaymentQuery orderByCountry($order = Criteria::ASC) Order by the aut_Country column
 * @method     ChildAutoPaymentQuery orderByPhone($order = Criteria::ASC) Order by the aut_Phone column
 * @method     ChildAutoPaymentQuery orderByEmail($order = Criteria::ASC) Order by the aut_Email column
 * @method     ChildAutoPaymentQuery orderByCreditCard($order = Criteria::ASC) Order by the aut_CreditCard column
 * @method     ChildAutoPaymentQuery orderByExpMonth($order = Criteria::ASC) Order by the aut_ExpMonth column
 * @method     ChildAutoPaymentQuery orderByExpYear($order = Criteria::ASC) Order by the aut_ExpYear column
 * @method     ChildAutoPaymentQuery orderByBankName($order = Criteria::ASC) Order by the aut_BankName column
 * @method     ChildAutoPaymentQuery orderByRoute($order = Criteria::ASC) Order by the aut_Route column
 * @method     ChildAutoPaymentQuery orderByAccount($order = Criteria::ASC) Order by the aut_Account column
 * @method     ChildAutoPaymentQuery orderByDateLastEdited($order = Criteria::ASC) Order by the aut_DateLastEdited column
 * @method     ChildAutoPaymentQuery orderByEditedby($order = Criteria::ASC) Order by the aut_EditedBy column
 * @method     ChildAutoPaymentQuery orderBySerial($order = Criteria::ASC) Order by the aut_Serial column
 * @method     ChildAutoPaymentQuery orderByCreditcardvanco($order = Criteria::ASC) Order by the aut_CreditCardVanco column
 * @method     ChildAutoPaymentQuery orderByAccountVanco($order = Criteria::ASC) Order by the aut_AccountVanco column
 *
 * @method     ChildAutoPaymentQuery groupById() Group by the aut_ID column
 * @method     ChildAutoPaymentQuery groupByFamilyid() Group by the aut_FamID column
 * @method     ChildAutoPaymentQuery groupByEnableBankDraft() Group by the aut_EnableBankDraft column
 * @method     ChildAutoPaymentQuery groupByEnableCreditCard() Group by the aut_EnableCreditCard column
 * @method     ChildAutoPaymentQuery groupByNextPayDate() Group by the aut_NextPayDate column
 * @method     ChildAutoPaymentQuery groupByFyid() Group by the aut_FYID column
 * @method     ChildAutoPaymentQuery groupByAmount() Group by the aut_Amount column
 * @method     ChildAutoPaymentQuery groupByInterval() Group by the aut_Interval column
 * @method     ChildAutoPaymentQuery groupByFund() Group by the aut_Fund column
 * @method     ChildAutoPaymentQuery groupByFirstName() Group by the aut_FirstName column
 * @method     ChildAutoPaymentQuery groupByLastName() Group by the aut_LastName column
 * @method     ChildAutoPaymentQuery groupByAddress1() Group by the aut_Address1 column
 * @method     ChildAutoPaymentQuery groupByAddress2() Group by the aut_Address2 column
 * @method     ChildAutoPaymentQuery groupByCity() Group by the aut_City column
 * @method     ChildAutoPaymentQuery groupByState() Group by the aut_State column
 * @method     ChildAutoPaymentQuery groupByZip() Group by the aut_Zip column
 * @method     ChildAutoPaymentQuery groupByCountry() Group by the aut_Country column
 * @method     ChildAutoPaymentQuery groupByPhone() Group by the aut_Phone column
 * @method     ChildAutoPaymentQuery groupByEmail() Group by the aut_Email column
 * @method     ChildAutoPaymentQuery groupByCreditCard() Group by the aut_CreditCard column
 * @method     ChildAutoPaymentQuery groupByExpMonth() Group by the aut_ExpMonth column
 * @method     ChildAutoPaymentQuery groupByExpYear() Group by the aut_ExpYear column
 * @method     ChildAutoPaymentQuery groupByBankName() Group by the aut_BankName column
 * @method     ChildAutoPaymentQuery groupByRoute() Group by the aut_Route column
 * @method     ChildAutoPaymentQuery groupByAccount() Group by the aut_Account column
 * @method     ChildAutoPaymentQuery groupByDateLastEdited() Group by the aut_DateLastEdited column
 * @method     ChildAutoPaymentQuery groupByEditedby() Group by the aut_EditedBy column
 * @method     ChildAutoPaymentQuery groupBySerial() Group by the aut_Serial column
 * @method     ChildAutoPaymentQuery groupByCreditcardvanco() Group by the aut_CreditCardVanco column
 * @method     ChildAutoPaymentQuery groupByAccountVanco() Group by the aut_AccountVanco column
 *
 * @method     ChildAutoPaymentQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildAutoPaymentQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildAutoPaymentQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildAutoPaymentQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildAutoPaymentQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildAutoPaymentQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildAutoPayment findOne(ConnectionInterface $con = null) Return the first ChildAutoPayment matching the query
 * @method     ChildAutoPayment findOneOrCreate(ConnectionInterface $con = null) Return the first ChildAutoPayment matching the query, or a new ChildAutoPayment object populated from the query conditions when no match is found
 *
 * @method     ChildAutoPayment findOneById(int $aut_ID) Return the first ChildAutoPayment filtered by the aut_ID column
 * @method     ChildAutoPayment findOneByFamilyid(int $aut_FamID) Return the first ChildAutoPayment filtered by the aut_FamID column
 * @method     ChildAutoPayment findOneByEnableBankDraft(boolean $aut_EnableBankDraft) Return the first ChildAutoPayment filtered by the aut_EnableBankDraft column
 * @method     ChildAutoPayment findOneByEnableCreditCard(boolean $aut_EnableCreditCard) Return the first ChildAutoPayment filtered by the aut_EnableCreditCard column
 * @method     ChildAutoPayment findOneByNextPayDate(string $aut_NextPayDate) Return the first ChildAutoPayment filtered by the aut_NextPayDate column
 * @method     ChildAutoPayment findOneByFyid(int $aut_FYID) Return the first ChildAutoPayment filtered by the aut_FYID column
 * @method     ChildAutoPayment findOneByAmount(string $aut_Amount) Return the first ChildAutoPayment filtered by the aut_Amount column
 * @method     ChildAutoPayment findOneByInterval(int $aut_Interval) Return the first ChildAutoPayment filtered by the aut_Interval column
 * @method     ChildAutoPayment findOneByFund(int $aut_Fund) Return the first ChildAutoPayment filtered by the aut_Fund column
 * @method     ChildAutoPayment findOneByFirstName(string $aut_FirstName) Return the first ChildAutoPayment filtered by the aut_FirstName column
 * @method     ChildAutoPayment findOneByLastName(string $aut_LastName) Return the first ChildAutoPayment filtered by the aut_LastName column
 * @method     ChildAutoPayment findOneByAddress1(string $aut_Address1) Return the first ChildAutoPayment filtered by the aut_Address1 column
 * @method     ChildAutoPayment findOneByAddress2(string $aut_Address2) Return the first ChildAutoPayment filtered by the aut_Address2 column
 * @method     ChildAutoPayment findOneByCity(string $aut_City) Return the first ChildAutoPayment filtered by the aut_City column
 * @method     ChildAutoPayment findOneByState(string $aut_State) Return the first ChildAutoPayment filtered by the aut_State column
 * @method     ChildAutoPayment findOneByZip(string $aut_Zip) Return the first ChildAutoPayment filtered by the aut_Zip column
 * @method     ChildAutoPayment findOneByCountry(string $aut_Country) Return the first ChildAutoPayment filtered by the aut_Country column
 * @method     ChildAutoPayment findOneByPhone(string $aut_Phone) Return the first ChildAutoPayment filtered by the aut_Phone column
 * @method     ChildAutoPayment findOneByEmail(string $aut_Email) Return the first ChildAutoPayment filtered by the aut_Email column
 * @method     ChildAutoPayment findOneByCreditCard(string $aut_CreditCard) Return the first ChildAutoPayment filtered by the aut_CreditCard column
 * @method     ChildAutoPayment findOneByExpMonth(string $aut_ExpMonth) Return the first ChildAutoPayment filtered by the aut_ExpMonth column
 * @method     ChildAutoPayment findOneByExpYear(string $aut_ExpYear) Return the first ChildAutoPayment filtered by the aut_ExpYear column
 * @method     ChildAutoPayment findOneByBankName(string $aut_BankName) Return the first ChildAutoPayment filtered by the aut_BankName column
 * @method     ChildAutoPayment findOneByRoute(string $aut_Route) Return the first ChildAutoPayment filtered by the aut_Route column
 * @method     ChildAutoPayment findOneByAccount(string $aut_Account) Return the first ChildAutoPayment filtered by the aut_Account column
 * @method     ChildAutoPayment findOneByDateLastEdited(string $aut_DateLastEdited) Return the first ChildAutoPayment filtered by the aut_DateLastEdited column
 * @method     ChildAutoPayment findOneByEditedby(int $aut_EditedBy) Return the first ChildAutoPayment filtered by the aut_EditedBy column
 * @method     ChildAutoPayment findOneBySerial(int $aut_Serial) Return the first ChildAutoPayment filtered by the aut_Serial column
 * @method     ChildAutoPayment findOneByCreditcardvanco(string $aut_CreditCardVanco) Return the first ChildAutoPayment filtered by the aut_CreditCardVanco column
 * @method     ChildAutoPayment findOneByAccountVanco(string $aut_AccountVanco) Return the first ChildAutoPayment filtered by the aut_AccountVanco column *

 * @method     ChildAutoPayment requirePk($key, ConnectionInterface $con = null) Return the ChildAutoPayment by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOne(ConnectionInterface $con = null) Return the first ChildAutoPayment matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildAutoPayment requireOneById(int $aut_ID) Return the first ChildAutoPayment filtered by the aut_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOneByFamilyid(int $aut_FamID) Return the first ChildAutoPayment filtered by the aut_FamID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOneByEnableBankDraft(boolean $aut_EnableBankDraft) Return the first ChildAutoPayment filtered by the aut_EnableBankDraft column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOneByEnableCreditCard(boolean $aut_EnableCreditCard) Return the first ChildAutoPayment filtered by the aut_EnableCreditCard column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOneByNextPayDate(string $aut_NextPayDate) Return the first ChildAutoPayment filtered by the aut_NextPayDate column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOneByFyid(int $aut_FYID) Return the first ChildAutoPayment filtered by the aut_FYID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOneByAmount(string $aut_Amount) Return the first ChildAutoPayment filtered by the aut_Amount column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOneByInterval(int $aut_Interval) Return the first ChildAutoPayment filtered by the aut_Interval column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOneByFund(int $aut_Fund) Return the first ChildAutoPayment filtered by the aut_Fund column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOneByFirstName(string $aut_FirstName) Return the first ChildAutoPayment filtered by the aut_FirstName column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOneByLastName(string $aut_LastName) Return the first ChildAutoPayment filtered by the aut_LastName column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOneByAddress1(string $aut_Address1) Return the first ChildAutoPayment filtered by the aut_Address1 column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOneByAddress2(string $aut_Address2) Return the first ChildAutoPayment filtered by the aut_Address2 column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOneByCity(string $aut_City) Return the first ChildAutoPayment filtered by the aut_City column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOneByState(string $aut_State) Return the first ChildAutoPayment filtered by the aut_State column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOneByZip(string $aut_Zip) Return the first ChildAutoPayment filtered by the aut_Zip column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOneByCountry(string $aut_Country) Return the first ChildAutoPayment filtered by the aut_Country column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOneByPhone(string $aut_Phone) Return the first ChildAutoPayment filtered by the aut_Phone column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOneByEmail(string $aut_Email) Return the first ChildAutoPayment filtered by the aut_Email column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOneByCreditCard(string $aut_CreditCard) Return the first ChildAutoPayment filtered by the aut_CreditCard column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOneByExpMonth(string $aut_ExpMonth) Return the first ChildAutoPayment filtered by the aut_ExpMonth column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOneByExpYear(string $aut_ExpYear) Return the first ChildAutoPayment filtered by the aut_ExpYear column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOneByBankName(string $aut_BankName) Return the first ChildAutoPayment filtered by the aut_BankName column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOneByRoute(string $aut_Route) Return the first ChildAutoPayment filtered by the aut_Route column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOneByAccount(string $aut_Account) Return the first ChildAutoPayment filtered by the aut_Account column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOneByDateLastEdited(string $aut_DateLastEdited) Return the first ChildAutoPayment filtered by the aut_DateLastEdited column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOneByEditedby(int $aut_EditedBy) Return the first ChildAutoPayment filtered by the aut_EditedBy column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOneBySerial(int $aut_Serial) Return the first ChildAutoPayment filtered by the aut_Serial column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOneByCreditcardvanco(string $aut_CreditCardVanco) Return the first ChildAutoPayment filtered by the aut_CreditCardVanco column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildAutoPayment requireOneByAccountVanco(string $aut_AccountVanco) Return the first ChildAutoPayment filtered by the aut_AccountVanco column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildAutoPayment[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildAutoPayment objects based on current ModelCriteria
 * @method     ChildAutoPayment[]|ObjectCollection findById(int $aut_ID) Return ChildAutoPayment objects filtered by the aut_ID column
 * @method     ChildAutoPayment[]|ObjectCollection findByFamilyid(int $aut_FamID) Return ChildAutoPayment objects filtered by the aut_FamID column
 * @method     ChildAutoPayment[]|ObjectCollection findByEnableBankDraft(boolean $aut_EnableBankDraft) Return ChildAutoPayment objects filtered by the aut_EnableBankDraft column
 * @method     ChildAutoPayment[]|ObjectCollection findByEnableCreditCard(boolean $aut_EnableCreditCard) Return ChildAutoPayment objects filtered by the aut_EnableCreditCard column
 * @method     ChildAutoPayment[]|ObjectCollection findByNextPayDate(string $aut_NextPayDate) Return ChildAutoPayment objects filtered by the aut_NextPayDate column
 * @method     ChildAutoPayment[]|ObjectCollection findByFyid(int $aut_FYID) Return ChildAutoPayment objects filtered by the aut_FYID column
 * @method     ChildAutoPayment[]|ObjectCollection findByAmount(string $aut_Amount) Return ChildAutoPayment objects filtered by the aut_Amount column
 * @method     ChildAutoPayment[]|ObjectCollection findByInterval(int $aut_Interval) Return ChildAutoPayment objects filtered by the aut_Interval column
 * @method     ChildAutoPayment[]|ObjectCollection findByFund(int $aut_Fund) Return ChildAutoPayment objects filtered by the aut_Fund column
 * @method     ChildAutoPayment[]|ObjectCollection findByFirstName(string $aut_FirstName) Return ChildAutoPayment objects filtered by the aut_FirstName column
 * @method     ChildAutoPayment[]|ObjectCollection findByLastName(string $aut_LastName) Return ChildAutoPayment objects filtered by the aut_LastName column
 * @method     ChildAutoPayment[]|ObjectCollection findByAddress1(string $aut_Address1) Return ChildAutoPayment objects filtered by the aut_Address1 column
 * @method     ChildAutoPayment[]|ObjectCollection findByAddress2(string $aut_Address2) Return ChildAutoPayment objects filtered by the aut_Address2 column
 * @method     ChildAutoPayment[]|ObjectCollection findByCity(string $aut_City) Return ChildAutoPayment objects filtered by the aut_City column
 * @method     ChildAutoPayment[]|ObjectCollection findByState(string $aut_State) Return ChildAutoPayment objects filtered by the aut_State column
 * @method     ChildAutoPayment[]|ObjectCollection findByZip(string $aut_Zip) Return ChildAutoPayment objects filtered by the aut_Zip column
 * @method     ChildAutoPayment[]|ObjectCollection findByCountry(string $aut_Country) Return ChildAutoPayment objects filtered by the aut_Country column
 * @method     ChildAutoPayment[]|ObjectCollection findByPhone(string $aut_Phone) Return ChildAutoPayment objects filtered by the aut_Phone column
 * @method     ChildAutoPayment[]|ObjectCollection findByEmail(string $aut_Email) Return ChildAutoPayment objects filtered by the aut_Email column
 * @method     ChildAutoPayment[]|ObjectCollection findByCreditCard(string $aut_CreditCard) Return ChildAutoPayment objects filtered by the aut_CreditCard column
 * @method     ChildAutoPayment[]|ObjectCollection findByExpMonth(string $aut_ExpMonth) Return ChildAutoPayment objects filtered by the aut_ExpMonth column
 * @method     ChildAutoPayment[]|ObjectCollection findByExpYear(string $aut_ExpYear) Return ChildAutoPayment objects filtered by the aut_ExpYear column
 * @method     ChildAutoPayment[]|ObjectCollection findByBankName(string $aut_BankName) Return ChildAutoPayment objects filtered by the aut_BankName column
 * @method     ChildAutoPayment[]|ObjectCollection findByRoute(string $aut_Route) Return ChildAutoPayment objects filtered by the aut_Route column
 * @method     ChildAutoPayment[]|ObjectCollection findByAccount(string $aut_Account) Return ChildAutoPayment objects filtered by the aut_Account column
 * @method     ChildAutoPayment[]|ObjectCollection findByDateLastEdited(string $aut_DateLastEdited) Return ChildAutoPayment objects filtered by the aut_DateLastEdited column
 * @method     ChildAutoPayment[]|ObjectCollection findByEditedby(int $aut_EditedBy) Return ChildAutoPayment objects filtered by the aut_EditedBy column
 * @method     ChildAutoPayment[]|ObjectCollection findBySerial(int $aut_Serial) Return ChildAutoPayment objects filtered by the aut_Serial column
 * @method     ChildAutoPayment[]|ObjectCollection findByCreditcardvanco(string $aut_CreditCardVanco) Return ChildAutoPayment objects filtered by the aut_CreditCardVanco column
 * @method     ChildAutoPayment[]|ObjectCollection findByAccountVanco(string $aut_AccountVanco) Return ChildAutoPayment objects filtered by the aut_AccountVanco column
 * @method     ChildAutoPayment[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class AutoPaymentQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\AutoPaymentQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\AutoPayment', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildAutoPaymentQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildAutoPaymentQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildAutoPaymentQuery) {
            return $criteria;
        }
        $query = new ChildAutoPaymentQuery();
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
     * @return ChildAutoPayment|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(AutoPaymentTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (
            $this->formatter || $this->modelAlias || $this->with || $this->select
            || $this->selectColumns || $this->asColumns || $this->selectModifiers
            || $this->map || $this->having || $this->joins
        ) {
            return $this->findPkComplex($key, $con);
        }

        if ((null !== ($obj = AutoPaymentTableMap::getInstanceFromPool(null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key)))) {
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
     * @return ChildAutoPayment A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT aut_ID, aut_FamID, aut_EnableBankDraft, aut_EnableCreditCard, aut_NextPayDate, aut_FYID, aut_Amount, aut_Interval, aut_Fund, aut_FirstName, aut_LastName, aut_Address1, aut_Address2, aut_City, aut_State, aut_Zip, aut_Country, aut_Phone, aut_Email, aut_CreditCard, aut_ExpMonth, aut_ExpYear, aut_BankName, aut_Route, aut_Account, aut_DateLastEdited, aut_EditedBy, aut_Serial, aut_CreditCardVanco, aut_AccountVanco FROM autopayment_aut WHERE aut_ID = :p0';
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
            /** @var ChildAutoPayment $obj */
            $obj = new ChildAutoPayment();
            $obj->hydrate($row);
            AutoPaymentTableMap::addInstanceToPool($obj, null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key);
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
     * @return ChildAutoPayment|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the aut_ID column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE aut_ID = 1234
     * $query->filterById(array(12, 34)); // WHERE aut_ID IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE aut_ID > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_ID, $id, $comparison);
    }

    /**
     * Filter the query on the aut_FamID column
     *
     * Example usage:
     * <code>
     * $query->filterByFamilyid(1234); // WHERE aut_FamID = 1234
     * $query->filterByFamilyid(array(12, 34)); // WHERE aut_FamID IN (12, 34)
     * $query->filterByFamilyid(array('min' => 12)); // WHERE aut_FamID > 12
     * </code>
     *
     * @param     mixed $familyid The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByFamilyid($familyid = null, $comparison = null)
    {
        if (is_array($familyid)) {
            $useMinMax = false;
            if (isset($familyid['min'])) {
                $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_FAMID, $familyid['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($familyid['max'])) {
                $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_FAMID, $familyid['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_FAMID, $familyid, $comparison);
    }

    /**
     * Filter the query on the aut_EnableBankDraft column
     *
     * Example usage:
     * <code>
     * $query->filterByEnableBankDraft(true); // WHERE aut_EnableBankDraft = true
     * $query->filterByEnableBankDraft('yes'); // WHERE aut_EnableBankDraft = true
     * </code>
     *
     * @param     boolean|string $enableBankDraft The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByEnableBankDraft($enableBankDraft = null, $comparison = null)
    {
        if (is_string($enableBankDraft)) {
            $enableBankDraft = in_array(strtolower($enableBankDraft), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_ENABLEBANKDRAFT, $enableBankDraft, $comparison);
    }

    /**
     * Filter the query on the aut_EnableCreditCard column
     *
     * Example usage:
     * <code>
     * $query->filterByEnableCreditCard(true); // WHERE aut_EnableCreditCard = true
     * $query->filterByEnableCreditCard('yes'); // WHERE aut_EnableCreditCard = true
     * </code>
     *
     * @param     boolean|string $enableCreditCard The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByEnableCreditCard($enableCreditCard = null, $comparison = null)
    {
        if (is_string($enableCreditCard)) {
            $enableCreditCard = in_array(strtolower($enableCreditCard), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_ENABLECREDITCARD, $enableCreditCard, $comparison);
    }

    /**
     * Filter the query on the aut_NextPayDate column
     *
     * Example usage:
     * <code>
     * $query->filterByNextPayDate('2011-03-14'); // WHERE aut_NextPayDate = '2011-03-14'
     * $query->filterByNextPayDate('now'); // WHERE aut_NextPayDate = '2011-03-14'
     * $query->filterByNextPayDate(array('max' => 'yesterday')); // WHERE aut_NextPayDate > '2011-03-13'
     * </code>
     *
     * @param     mixed $nextPayDate The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByNextPayDate($nextPayDate = null, $comparison = null)
    {
        if (is_array($nextPayDate)) {
            $useMinMax = false;
            if (isset($nextPayDate['min'])) {
                $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_NEXTPAYDATE, $nextPayDate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($nextPayDate['max'])) {
                $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_NEXTPAYDATE, $nextPayDate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_NEXTPAYDATE, $nextPayDate, $comparison);
    }

    /**
     * Filter the query on the aut_FYID column
     *
     * Example usage:
     * <code>
     * $query->filterByFyid(1234); // WHERE aut_FYID = 1234
     * $query->filterByFyid(array(12, 34)); // WHERE aut_FYID IN (12, 34)
     * $query->filterByFyid(array('min' => 12)); // WHERE aut_FYID > 12
     * </code>
     *
     * @param     mixed $fyid The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByFyid($fyid = null, $comparison = null)
    {
        if (is_array($fyid)) {
            $useMinMax = false;
            if (isset($fyid['min'])) {
                $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_FYID, $fyid['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($fyid['max'])) {
                $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_FYID, $fyid['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_FYID, $fyid, $comparison);
    }

    /**
     * Filter the query on the aut_Amount column
     *
     * Example usage:
     * <code>
     * $query->filterByAmount(1234); // WHERE aut_Amount = 1234
     * $query->filterByAmount(array(12, 34)); // WHERE aut_Amount IN (12, 34)
     * $query->filterByAmount(array('min' => 12)); // WHERE aut_Amount > 12
     * </code>
     *
     * @param     mixed $amount The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByAmount($amount = null, $comparison = null)
    {
        if (is_array($amount)) {
            $useMinMax = false;
            if (isset($amount['min'])) {
                $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_AMOUNT, $amount['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($amount['max'])) {
                $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_AMOUNT, $amount['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_AMOUNT, $amount, $comparison);
    }

    /**
     * Filter the query on the aut_Interval column
     *
     * Example usage:
     * <code>
     * $query->filterByInterval(1234); // WHERE aut_Interval = 1234
     * $query->filterByInterval(array(12, 34)); // WHERE aut_Interval IN (12, 34)
     * $query->filterByInterval(array('min' => 12)); // WHERE aut_Interval > 12
     * </code>
     *
     * @param     mixed $interval The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByInterval($interval = null, $comparison = null)
    {
        if (is_array($interval)) {
            $useMinMax = false;
            if (isset($interval['min'])) {
                $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_INTERVAL, $interval['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($interval['max'])) {
                $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_INTERVAL, $interval['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_INTERVAL, $interval, $comparison);
    }

    /**
     * Filter the query on the aut_Fund column
     *
     * Example usage:
     * <code>
     * $query->filterByFund(1234); // WHERE aut_Fund = 1234
     * $query->filterByFund(array(12, 34)); // WHERE aut_Fund IN (12, 34)
     * $query->filterByFund(array('min' => 12)); // WHERE aut_Fund > 12
     * </code>
     *
     * @param     mixed $fund The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByFund($fund = null, $comparison = null)
    {
        if (is_array($fund)) {
            $useMinMax = false;
            if (isset($fund['min'])) {
                $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_FUND, $fund['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($fund['max'])) {
                $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_FUND, $fund['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_FUND, $fund, $comparison);
    }

    /**
     * Filter the query on the aut_FirstName column
     *
     * Example usage:
     * <code>
     * $query->filterByFirstName('fooValue');   // WHERE aut_FirstName = 'fooValue'
     * $query->filterByFirstName('%fooValue%', Criteria::LIKE); // WHERE aut_FirstName LIKE '%fooValue%'
     * </code>
     *
     * @param     string $firstName The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByFirstName($firstName = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($firstName)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_FIRSTNAME, $firstName, $comparison);
    }

    /**
     * Filter the query on the aut_LastName column
     *
     * Example usage:
     * <code>
     * $query->filterByLastName('fooValue');   // WHERE aut_LastName = 'fooValue'
     * $query->filterByLastName('%fooValue%', Criteria::LIKE); // WHERE aut_LastName LIKE '%fooValue%'
     * </code>
     *
     * @param     string $lastName The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByLastName($lastName = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($lastName)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_LASTNAME, $lastName, $comparison);
    }

    /**
     * Filter the query on the aut_Address1 column
     *
     * Example usage:
     * <code>
     * $query->filterByAddress1('fooValue');   // WHERE aut_Address1 = 'fooValue'
     * $query->filterByAddress1('%fooValue%', Criteria::LIKE); // WHERE aut_Address1 LIKE '%fooValue%'
     * </code>
     *
     * @param     string $address1 The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByAddress1($address1 = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($address1)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_ADDRESS1, $address1, $comparison);
    }

    /**
     * Filter the query on the aut_Address2 column
     *
     * Example usage:
     * <code>
     * $query->filterByAddress2('fooValue');   // WHERE aut_Address2 = 'fooValue'
     * $query->filterByAddress2('%fooValue%', Criteria::LIKE); // WHERE aut_Address2 LIKE '%fooValue%'
     * </code>
     *
     * @param     string $address2 The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByAddress2($address2 = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($address2)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_ADDRESS2, $address2, $comparison);
    }

    /**
     * Filter the query on the aut_City column
     *
     * Example usage:
     * <code>
     * $query->filterByCity('fooValue');   // WHERE aut_City = 'fooValue'
     * $query->filterByCity('%fooValue%', Criteria::LIKE); // WHERE aut_City LIKE '%fooValue%'
     * </code>
     *
     * @param     string $city The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByCity($city = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($city)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_CITY, $city, $comparison);
    }

    /**
     * Filter the query on the aut_State column
     *
     * Example usage:
     * <code>
     * $query->filterByState('fooValue');   // WHERE aut_State = 'fooValue'
     * $query->filterByState('%fooValue%', Criteria::LIKE); // WHERE aut_State LIKE '%fooValue%'
     * </code>
     *
     * @param     string $state The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByState($state = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($state)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_STATE, $state, $comparison);
    }

    /**
     * Filter the query on the aut_Zip column
     *
     * Example usage:
     * <code>
     * $query->filterByZip('fooValue');   // WHERE aut_Zip = 'fooValue'
     * $query->filterByZip('%fooValue%', Criteria::LIKE); // WHERE aut_Zip LIKE '%fooValue%'
     * </code>
     *
     * @param     string $zip The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByZip($zip = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($zip)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_ZIP, $zip, $comparison);
    }

    /**
     * Filter the query on the aut_Country column
     *
     * Example usage:
     * <code>
     * $query->filterByCountry('fooValue');   // WHERE aut_Country = 'fooValue'
     * $query->filterByCountry('%fooValue%', Criteria::LIKE); // WHERE aut_Country LIKE '%fooValue%'
     * </code>
     *
     * @param     string $country The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByCountry($country = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($country)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_COUNTRY, $country, $comparison);
    }

    /**
     * Filter the query on the aut_Phone column
     *
     * Example usage:
     * <code>
     * $query->filterByPhone('fooValue');   // WHERE aut_Phone = 'fooValue'
     * $query->filterByPhone('%fooValue%', Criteria::LIKE); // WHERE aut_Phone LIKE '%fooValue%'
     * </code>
     *
     * @param     string $phone The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByPhone($phone = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($phone)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_PHONE, $phone, $comparison);
    }

    /**
     * Filter the query on the aut_Email column
     *
     * Example usage:
     * <code>
     * $query->filterByEmail('fooValue');   // WHERE aut_Email = 'fooValue'
     * $query->filterByEmail('%fooValue%', Criteria::LIKE); // WHERE aut_Email LIKE '%fooValue%'
     * </code>
     *
     * @param     string $email The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByEmail($email = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($email)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_EMAIL, $email, $comparison);
    }

    /**
     * Filter the query on the aut_CreditCard column
     *
     * Example usage:
     * <code>
     * $query->filterByCreditCard('fooValue');   // WHERE aut_CreditCard = 'fooValue'
     * $query->filterByCreditCard('%fooValue%', Criteria::LIKE); // WHERE aut_CreditCard LIKE '%fooValue%'
     * </code>
     *
     * @param     string $creditCard The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByCreditCard($creditCard = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($creditCard)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_CREDITCARD, $creditCard, $comparison);
    }

    /**
     * Filter the query on the aut_ExpMonth column
     *
     * Example usage:
     * <code>
     * $query->filterByExpMonth('fooValue');   // WHERE aut_ExpMonth = 'fooValue'
     * $query->filterByExpMonth('%fooValue%', Criteria::LIKE); // WHERE aut_ExpMonth LIKE '%fooValue%'
     * </code>
     *
     * @param     string $expMonth The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByExpMonth($expMonth = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($expMonth)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_EXPMONTH, $expMonth, $comparison);
    }

    /**
     * Filter the query on the aut_ExpYear column
     *
     * Example usage:
     * <code>
     * $query->filterByExpYear('fooValue');   // WHERE aut_ExpYear = 'fooValue'
     * $query->filterByExpYear('%fooValue%', Criteria::LIKE); // WHERE aut_ExpYear LIKE '%fooValue%'
     * </code>
     *
     * @param     string $expYear The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByExpYear($expYear = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($expYear)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_EXPYEAR, $expYear, $comparison);
    }

    /**
     * Filter the query on the aut_BankName column
     *
     * Example usage:
     * <code>
     * $query->filterByBankName('fooValue');   // WHERE aut_BankName = 'fooValue'
     * $query->filterByBankName('%fooValue%', Criteria::LIKE); // WHERE aut_BankName LIKE '%fooValue%'
     * </code>
     *
     * @param     string $bankName The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByBankName($bankName = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($bankName)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_BANKNAME, $bankName, $comparison);
    }

    /**
     * Filter the query on the aut_Route column
     *
     * Example usage:
     * <code>
     * $query->filterByRoute('fooValue');   // WHERE aut_Route = 'fooValue'
     * $query->filterByRoute('%fooValue%', Criteria::LIKE); // WHERE aut_Route LIKE '%fooValue%'
     * </code>
     *
     * @param     string $route The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByRoute($route = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($route)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_ROUTE, $route, $comparison);
    }

    /**
     * Filter the query on the aut_Account column
     *
     * Example usage:
     * <code>
     * $query->filterByAccount('fooValue');   // WHERE aut_Account = 'fooValue'
     * $query->filterByAccount('%fooValue%', Criteria::LIKE); // WHERE aut_Account LIKE '%fooValue%'
     * </code>
     *
     * @param     string $account The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByAccount($account = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($account)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_ACCOUNT, $account, $comparison);
    }

    /**
     * Filter the query on the aut_DateLastEdited column
     *
     * Example usage:
     * <code>
     * $query->filterByDateLastEdited('2011-03-14'); // WHERE aut_DateLastEdited = '2011-03-14'
     * $query->filterByDateLastEdited('now'); // WHERE aut_DateLastEdited = '2011-03-14'
     * $query->filterByDateLastEdited(array('max' => 'yesterday')); // WHERE aut_DateLastEdited > '2011-03-13'
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
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByDateLastEdited($dateLastEdited = null, $comparison = null)
    {
        if (is_array($dateLastEdited)) {
            $useMinMax = false;
            if (isset($dateLastEdited['min'])) {
                $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_DATELASTEDITED, $dateLastEdited['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($dateLastEdited['max'])) {
                $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_DATELASTEDITED, $dateLastEdited['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_DATELASTEDITED, $dateLastEdited, $comparison);
    }

    /**
     * Filter the query on the aut_EditedBy column
     *
     * Example usage:
     * <code>
     * $query->filterByEditedby(1234); // WHERE aut_EditedBy = 1234
     * $query->filterByEditedby(array(12, 34)); // WHERE aut_EditedBy IN (12, 34)
     * $query->filterByEditedby(array('min' => 12)); // WHERE aut_EditedBy > 12
     * </code>
     *
     * @param     mixed $editedby The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByEditedby($editedby = null, $comparison = null)
    {
        if (is_array($editedby)) {
            $useMinMax = false;
            if (isset($editedby['min'])) {
                $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_EDITEDBY, $editedby['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($editedby['max'])) {
                $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_EDITEDBY, $editedby['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_EDITEDBY, $editedby, $comparison);
    }

    /**
     * Filter the query on the aut_Serial column
     *
     * Example usage:
     * <code>
     * $query->filterBySerial(1234); // WHERE aut_Serial = 1234
     * $query->filterBySerial(array(12, 34)); // WHERE aut_Serial IN (12, 34)
     * $query->filterBySerial(array('min' => 12)); // WHERE aut_Serial > 12
     * </code>
     *
     * @param     mixed $serial The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterBySerial($serial = null, $comparison = null)
    {
        if (is_array($serial)) {
            $useMinMax = false;
            if (isset($serial['min'])) {
                $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_SERIAL, $serial['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($serial['max'])) {
                $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_SERIAL, $serial['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_SERIAL, $serial, $comparison);
    }

    /**
     * Filter the query on the aut_CreditCardVanco column
     *
     * Example usage:
     * <code>
     * $query->filterByCreditcardvanco('fooValue');   // WHERE aut_CreditCardVanco = 'fooValue'
     * $query->filterByCreditcardvanco('%fooValue%', Criteria::LIKE); // WHERE aut_CreditCardVanco LIKE '%fooValue%'
     * </code>
     *
     * @param     string $creditcardvanco The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByCreditcardvanco($creditcardvanco = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($creditcardvanco)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_CREDITCARDVANCO, $creditcardvanco, $comparison);
    }

    /**
     * Filter the query on the aut_AccountVanco column
     *
     * Example usage:
     * <code>
     * $query->filterByAccountVanco('fooValue');   // WHERE aut_AccountVanco = 'fooValue'
     * $query->filterByAccountVanco('%fooValue%', Criteria::LIKE); // WHERE aut_AccountVanco LIKE '%fooValue%'
     * </code>
     *
     * @param     string $accountVanco The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function filterByAccountVanco($accountVanco = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($accountVanco)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_ACCOUNTVANCO, $accountVanco, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildAutoPayment $autoPayment Object to remove from the list of results
     *
     * @return $this|ChildAutoPaymentQuery The current query, for fluid interface
     */
    public function prune($autoPayment = null)
    {
        if ($autoPayment) {
            $this->addUsingAlias(AutoPaymentTableMap::COL_AUT_ID, $autoPayment->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the autopayment_aut table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(AutoPaymentTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            AutoPaymentTableMap::clearInstancePool();
            AutoPaymentTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(AutoPaymentTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(AutoPaymentTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            AutoPaymentTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            AutoPaymentTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // AutoPaymentQuery
