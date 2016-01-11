<?php

namespace Base;

use \Person as ChildPerson;
use \PersonQuery as ChildPersonQuery;
use \Exception;
use \PDO;
use Map\PersonTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'person_per' table.
 *
 *
 *
 * @method     ChildPersonQuery orderById($order = Criteria::ASC) Order by the per_ID column
 * @method     ChildPersonQuery orderByTitle($order = Criteria::ASC) Order by the per_Title column
 * @method     ChildPersonQuery orderByFirstName($order = Criteria::ASC) Order by the per_FirstName column
 * @method     ChildPersonQuery orderByMiddleName($order = Criteria::ASC) Order by the per_MiddleName column
 * @method     ChildPersonQuery orderByLastName($order = Criteria::ASC) Order by the per_LastName column
 * @method     ChildPersonQuery orderBySuffix($order = Criteria::ASC) Order by the per_Suffix column
 * @method     ChildPersonQuery orderByAddress1($order = Criteria::ASC) Order by the per_Address1 column
 * @method     ChildPersonQuery orderByAddress2($order = Criteria::ASC) Order by the per_Address2 column
 * @method     ChildPersonQuery orderByCity($order = Criteria::ASC) Order by the per_City column
 * @method     ChildPersonQuery orderByState($order = Criteria::ASC) Order by the per_State column
 * @method     ChildPersonQuery orderByZip($order = Criteria::ASC) Order by the per_Zip column
 * @method     ChildPersonQuery orderByCountry($order = Criteria::ASC) Order by the per_Country column
 * @method     ChildPersonQuery orderByHomePhone($order = Criteria::ASC) Order by the per_HomePhone column
 * @method     ChildPersonQuery orderByWorkPhone($order = Criteria::ASC) Order by the per_WorkPhone column
 * @method     ChildPersonQuery orderByCellPhone($order = Criteria::ASC) Order by the per_CellPhone column
 * @method     ChildPersonQuery orderByEmail($order = Criteria::ASC) Order by the per_Email column
 * @method     ChildPersonQuery orderByWorkEmail($order = Criteria::ASC) Order by the per_WorkEmail column
 * @method     ChildPersonQuery orderByBirthMonth($order = Criteria::ASC) Order by the per_BirthMonth column
 * @method     ChildPersonQuery orderByBirthDay($order = Criteria::ASC) Order by the per_BirthDay column
 * @method     ChildPersonQuery orderByBirthYear($order = Criteria::ASC) Order by the per_BirthYear column
 * @method     ChildPersonQuery orderByMembershipDate($order = Criteria::ASC) Order by the per_MembershipDate column
 * @method     ChildPersonQuery orderByGender($order = Criteria::ASC) Order by the per_Gender column
 * @method     ChildPersonQuery orderByFmrId($order = Criteria::ASC) Order by the per_fmr_ID column
 * @method     ChildPersonQuery orderByClsId($order = Criteria::ASC) Order by the per_cls_ID column
 * @method     ChildPersonQuery orderByFamId($order = Criteria::ASC) Order by the per_fam_ID column
 * @method     ChildPersonQuery orderByEnvelope($order = Criteria::ASC) Order by the per_Envelope column
 * @method     ChildPersonQuery orderByDateLastEdited($order = Criteria::ASC) Order by the per_DateLastEdited column
 * @method     ChildPersonQuery orderByDateEntered($order = Criteria::ASC) Order by the per_DateEntered column
 * @method     ChildPersonQuery orderByEnteredBy($order = Criteria::ASC) Order by the per_EnteredBy column
 * @method     ChildPersonQuery orderByEditedBy($order = Criteria::ASC) Order by the per_EditedBy column
 * @method     ChildPersonQuery orderByFriendDate($order = Criteria::ASC) Order by the per_FriendDate column
 * @method     ChildPersonQuery orderByFlags($order = Criteria::ASC) Order by the per_Flags column
 *
 * @method     ChildPersonQuery groupById() Group by the per_ID column
 * @method     ChildPersonQuery groupByTitle() Group by the per_Title column
 * @method     ChildPersonQuery groupByFirstName() Group by the per_FirstName column
 * @method     ChildPersonQuery groupByMiddleName() Group by the per_MiddleName column
 * @method     ChildPersonQuery groupByLastName() Group by the per_LastName column
 * @method     ChildPersonQuery groupBySuffix() Group by the per_Suffix column
 * @method     ChildPersonQuery groupByAddress1() Group by the per_Address1 column
 * @method     ChildPersonQuery groupByAddress2() Group by the per_Address2 column
 * @method     ChildPersonQuery groupByCity() Group by the per_City column
 * @method     ChildPersonQuery groupByState() Group by the per_State column
 * @method     ChildPersonQuery groupByZip() Group by the per_Zip column
 * @method     ChildPersonQuery groupByCountry() Group by the per_Country column
 * @method     ChildPersonQuery groupByHomePhone() Group by the per_HomePhone column
 * @method     ChildPersonQuery groupByWorkPhone() Group by the per_WorkPhone column
 * @method     ChildPersonQuery groupByCellPhone() Group by the per_CellPhone column
 * @method     ChildPersonQuery groupByEmail() Group by the per_Email column
 * @method     ChildPersonQuery groupByWorkEmail() Group by the per_WorkEmail column
 * @method     ChildPersonQuery groupByBirthMonth() Group by the per_BirthMonth column
 * @method     ChildPersonQuery groupByBirthDay() Group by the per_BirthDay column
 * @method     ChildPersonQuery groupByBirthYear() Group by the per_BirthYear column
 * @method     ChildPersonQuery groupByMembershipDate() Group by the per_MembershipDate column
 * @method     ChildPersonQuery groupByGender() Group by the per_Gender column
 * @method     ChildPersonQuery groupByFmrId() Group by the per_fmr_ID column
 * @method     ChildPersonQuery groupByClsId() Group by the per_cls_ID column
 * @method     ChildPersonQuery groupByFamId() Group by the per_fam_ID column
 * @method     ChildPersonQuery groupByEnvelope() Group by the per_Envelope column
 * @method     ChildPersonQuery groupByDateLastEdited() Group by the per_DateLastEdited column
 * @method     ChildPersonQuery groupByDateEntered() Group by the per_DateEntered column
 * @method     ChildPersonQuery groupByEnteredBy() Group by the per_EnteredBy column
 * @method     ChildPersonQuery groupByEditedBy() Group by the per_EditedBy column
 * @method     ChildPersonQuery groupByFriendDate() Group by the per_FriendDate column
 * @method     ChildPersonQuery groupByFlags() Group by the per_Flags column
 *
 * @method     ChildPersonQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildPersonQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildPersonQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildPersonQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildPersonQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildPersonQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildPerson findOne(ConnectionInterface $con = null) Return the first ChildPerson matching the query
 * @method     ChildPerson findOneOrCreate(ConnectionInterface $con = null) Return the first ChildPerson matching the query, or a new ChildPerson object populated from the query conditions when no match is found
 *
 * @method     ChildPerson findOneById(int $per_ID) Return the first ChildPerson filtered by the per_ID column
 * @method     ChildPerson findOneByTitle(string $per_Title) Return the first ChildPerson filtered by the per_Title column
 * @method     ChildPerson findOneByFirstName(string $per_FirstName) Return the first ChildPerson filtered by the per_FirstName column
 * @method     ChildPerson findOneByMiddleName(string $per_MiddleName) Return the first ChildPerson filtered by the per_MiddleName column
 * @method     ChildPerson findOneByLastName(string $per_LastName) Return the first ChildPerson filtered by the per_LastName column
 * @method     ChildPerson findOneBySuffix(string $per_Suffix) Return the first ChildPerson filtered by the per_Suffix column
 * @method     ChildPerson findOneByAddress1(string $per_Address1) Return the first ChildPerson filtered by the per_Address1 column
 * @method     ChildPerson findOneByAddress2(string $per_Address2) Return the first ChildPerson filtered by the per_Address2 column
 * @method     ChildPerson findOneByCity(string $per_City) Return the first ChildPerson filtered by the per_City column
 * @method     ChildPerson findOneByState(string $per_State) Return the first ChildPerson filtered by the per_State column
 * @method     ChildPerson findOneByZip(string $per_Zip) Return the first ChildPerson filtered by the per_Zip column
 * @method     ChildPerson findOneByCountry(string $per_Country) Return the first ChildPerson filtered by the per_Country column
 * @method     ChildPerson findOneByHomePhone(string $per_HomePhone) Return the first ChildPerson filtered by the per_HomePhone column
 * @method     ChildPerson findOneByWorkPhone(string $per_WorkPhone) Return the first ChildPerson filtered by the per_WorkPhone column
 * @method     ChildPerson findOneByCellPhone(string $per_CellPhone) Return the first ChildPerson filtered by the per_CellPhone column
 * @method     ChildPerson findOneByEmail(string $per_Email) Return the first ChildPerson filtered by the per_Email column
 * @method     ChildPerson findOneByWorkEmail(string $per_WorkEmail) Return the first ChildPerson filtered by the per_WorkEmail column
 * @method     ChildPerson findOneByBirthMonth(int $per_BirthMonth) Return the first ChildPerson filtered by the per_BirthMonth column
 * @method     ChildPerson findOneByBirthDay(int $per_BirthDay) Return the first ChildPerson filtered by the per_BirthDay column
 * @method     ChildPerson findOneByBirthYear(int $per_BirthYear) Return the first ChildPerson filtered by the per_BirthYear column
 * @method     ChildPerson findOneByMembershipDate(string $per_MembershipDate) Return the first ChildPerson filtered by the per_MembershipDate column
 * @method     ChildPerson findOneByGender(boolean $per_Gender) Return the first ChildPerson filtered by the per_Gender column
 * @method     ChildPerson findOneByFmrId(int $per_fmr_ID) Return the first ChildPerson filtered by the per_fmr_ID column
 * @method     ChildPerson findOneByClsId(int $per_cls_ID) Return the first ChildPerson filtered by the per_cls_ID column
 * @method     ChildPerson findOneByFamId(int $per_fam_ID) Return the first ChildPerson filtered by the per_fam_ID column
 * @method     ChildPerson findOneByEnvelope(int $per_Envelope) Return the first ChildPerson filtered by the per_Envelope column
 * @method     ChildPerson findOneByDateLastEdited(string $per_DateLastEdited) Return the first ChildPerson filtered by the per_DateLastEdited column
 * @method     ChildPerson findOneByDateEntered(string $per_DateEntered) Return the first ChildPerson filtered by the per_DateEntered column
 * @method     ChildPerson findOneByEnteredBy(int $per_EnteredBy) Return the first ChildPerson filtered by the per_EnteredBy column
 * @method     ChildPerson findOneByEditedBy(int $per_EditedBy) Return the first ChildPerson filtered by the per_EditedBy column
 * @method     ChildPerson findOneByFriendDate(string $per_FriendDate) Return the first ChildPerson filtered by the per_FriendDate column
 * @method     ChildPerson findOneByFlags(int $per_Flags) Return the first ChildPerson filtered by the per_Flags column *

 * @method     ChildPerson requirePk($key, ConnectionInterface $con = null) Return the ChildPerson by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOne(ConnectionInterface $con = null) Return the first ChildPerson matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildPerson requireOneById(int $per_ID) Return the first ChildPerson filtered by the per_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByTitle(string $per_Title) Return the first ChildPerson filtered by the per_Title column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByFirstName(string $per_FirstName) Return the first ChildPerson filtered by the per_FirstName column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByMiddleName(string $per_MiddleName) Return the first ChildPerson filtered by the per_MiddleName column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByLastName(string $per_LastName) Return the first ChildPerson filtered by the per_LastName column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneBySuffix(string $per_Suffix) Return the first ChildPerson filtered by the per_Suffix column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByAddress1(string $per_Address1) Return the first ChildPerson filtered by the per_Address1 column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByAddress2(string $per_Address2) Return the first ChildPerson filtered by the per_Address2 column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByCity(string $per_City) Return the first ChildPerson filtered by the per_City column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByState(string $per_State) Return the first ChildPerson filtered by the per_State column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByZip(string $per_Zip) Return the first ChildPerson filtered by the per_Zip column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByCountry(string $per_Country) Return the first ChildPerson filtered by the per_Country column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByHomePhone(string $per_HomePhone) Return the first ChildPerson filtered by the per_HomePhone column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByWorkPhone(string $per_WorkPhone) Return the first ChildPerson filtered by the per_WorkPhone column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByCellPhone(string $per_CellPhone) Return the first ChildPerson filtered by the per_CellPhone column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByEmail(string $per_Email) Return the first ChildPerson filtered by the per_Email column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByWorkEmail(string $per_WorkEmail) Return the first ChildPerson filtered by the per_WorkEmail column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByBirthMonth(int $per_BirthMonth) Return the first ChildPerson filtered by the per_BirthMonth column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByBirthDay(int $per_BirthDay) Return the first ChildPerson filtered by the per_BirthDay column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByBirthYear(int $per_BirthYear) Return the first ChildPerson filtered by the per_BirthYear column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByMembershipDate(string $per_MembershipDate) Return the first ChildPerson filtered by the per_MembershipDate column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByGender(boolean $per_Gender) Return the first ChildPerson filtered by the per_Gender column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByFmrId(int $per_fmr_ID) Return the first ChildPerson filtered by the per_fmr_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByClsId(int $per_cls_ID) Return the first ChildPerson filtered by the per_cls_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByFamId(int $per_fam_ID) Return the first ChildPerson filtered by the per_fam_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByEnvelope(int $per_Envelope) Return the first ChildPerson filtered by the per_Envelope column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByDateLastEdited(string $per_DateLastEdited) Return the first ChildPerson filtered by the per_DateLastEdited column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByDateEntered(string $per_DateEntered) Return the first ChildPerson filtered by the per_DateEntered column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByEnteredBy(int $per_EnteredBy) Return the first ChildPerson filtered by the per_EnteredBy column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByEditedBy(int $per_EditedBy) Return the first ChildPerson filtered by the per_EditedBy column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByFriendDate(string $per_FriendDate) Return the first ChildPerson filtered by the per_FriendDate column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildPerson requireOneByFlags(int $per_Flags) Return the first ChildPerson filtered by the per_Flags column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildPerson[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildPerson objects based on current ModelCriteria
 * @method     ChildPerson[]|ObjectCollection findById(int $per_ID) Return ChildPerson objects filtered by the per_ID column
 * @method     ChildPerson[]|ObjectCollection findByTitle(string $per_Title) Return ChildPerson objects filtered by the per_Title column
 * @method     ChildPerson[]|ObjectCollection findByFirstName(string $per_FirstName) Return ChildPerson objects filtered by the per_FirstName column
 * @method     ChildPerson[]|ObjectCollection findByMiddleName(string $per_MiddleName) Return ChildPerson objects filtered by the per_MiddleName column
 * @method     ChildPerson[]|ObjectCollection findByLastName(string $per_LastName) Return ChildPerson objects filtered by the per_LastName column
 * @method     ChildPerson[]|ObjectCollection findBySuffix(string $per_Suffix) Return ChildPerson objects filtered by the per_Suffix column
 * @method     ChildPerson[]|ObjectCollection findByAddress1(string $per_Address1) Return ChildPerson objects filtered by the per_Address1 column
 * @method     ChildPerson[]|ObjectCollection findByAddress2(string $per_Address2) Return ChildPerson objects filtered by the per_Address2 column
 * @method     ChildPerson[]|ObjectCollection findByCity(string $per_City) Return ChildPerson objects filtered by the per_City column
 * @method     ChildPerson[]|ObjectCollection findByState(string $per_State) Return ChildPerson objects filtered by the per_State column
 * @method     ChildPerson[]|ObjectCollection findByZip(string $per_Zip) Return ChildPerson objects filtered by the per_Zip column
 * @method     ChildPerson[]|ObjectCollection findByCountry(string $per_Country) Return ChildPerson objects filtered by the per_Country column
 * @method     ChildPerson[]|ObjectCollection findByHomePhone(string $per_HomePhone) Return ChildPerson objects filtered by the per_HomePhone column
 * @method     ChildPerson[]|ObjectCollection findByWorkPhone(string $per_WorkPhone) Return ChildPerson objects filtered by the per_WorkPhone column
 * @method     ChildPerson[]|ObjectCollection findByCellPhone(string $per_CellPhone) Return ChildPerson objects filtered by the per_CellPhone column
 * @method     ChildPerson[]|ObjectCollection findByEmail(string $per_Email) Return ChildPerson objects filtered by the per_Email column
 * @method     ChildPerson[]|ObjectCollection findByWorkEmail(string $per_WorkEmail) Return ChildPerson objects filtered by the per_WorkEmail column
 * @method     ChildPerson[]|ObjectCollection findByBirthMonth(int $per_BirthMonth) Return ChildPerson objects filtered by the per_BirthMonth column
 * @method     ChildPerson[]|ObjectCollection findByBirthDay(int $per_BirthDay) Return ChildPerson objects filtered by the per_BirthDay column
 * @method     ChildPerson[]|ObjectCollection findByBirthYear(int $per_BirthYear) Return ChildPerson objects filtered by the per_BirthYear column
 * @method     ChildPerson[]|ObjectCollection findByMembershipDate(string $per_MembershipDate) Return ChildPerson objects filtered by the per_MembershipDate column
 * @method     ChildPerson[]|ObjectCollection findByGender(boolean $per_Gender) Return ChildPerson objects filtered by the per_Gender column
 * @method     ChildPerson[]|ObjectCollection findByFmrId(int $per_fmr_ID) Return ChildPerson objects filtered by the per_fmr_ID column
 * @method     ChildPerson[]|ObjectCollection findByClsId(int $per_cls_ID) Return ChildPerson objects filtered by the per_cls_ID column
 * @method     ChildPerson[]|ObjectCollection findByFamId(int $per_fam_ID) Return ChildPerson objects filtered by the per_fam_ID column
 * @method     ChildPerson[]|ObjectCollection findByEnvelope(int $per_Envelope) Return ChildPerson objects filtered by the per_Envelope column
 * @method     ChildPerson[]|ObjectCollection findByDateLastEdited(string $per_DateLastEdited) Return ChildPerson objects filtered by the per_DateLastEdited column
 * @method     ChildPerson[]|ObjectCollection findByDateEntered(string $per_DateEntered) Return ChildPerson objects filtered by the per_DateEntered column
 * @method     ChildPerson[]|ObjectCollection findByEnteredBy(int $per_EnteredBy) Return ChildPerson objects filtered by the per_EnteredBy column
 * @method     ChildPerson[]|ObjectCollection findByEditedBy(int $per_EditedBy) Return ChildPerson objects filtered by the per_EditedBy column
 * @method     ChildPerson[]|ObjectCollection findByFriendDate(string $per_FriendDate) Return ChildPerson objects filtered by the per_FriendDate column
 * @method     ChildPerson[]|ObjectCollection findByFlags(int $per_Flags) Return ChildPerson objects filtered by the per_Flags column
 * @method     ChildPerson[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class PersonQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \Base\PersonQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\Person', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildPersonQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildPersonQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildPersonQuery) {
            return $criteria;
        }
        $query = new ChildPersonQuery();
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
     * @return ChildPerson|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = PersonTableMap::getInstanceFromPool(null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(PersonTableMap::DATABASE_NAME);
        }
        $this->basePreSelect($con);
        if ($this->formatter || $this->modelAlias || $this->with || $this->select
         || $this->selectColumns || $this->asColumns || $this->selectModifiers
         || $this->map || $this->having || $this->joins) {
            return $this->findPkComplex($key, $con);
        } else {
            return $this->findPkSimple($key, $con);
        }
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
     * @return ChildPerson A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT per_ID, per_Title, per_FirstName, per_MiddleName, per_LastName, per_Suffix, per_Address1, per_Address2, per_City, per_State, per_Zip, per_Country, per_HomePhone, per_WorkPhone, per_CellPhone, per_Email, per_WorkEmail, per_BirthMonth, per_BirthDay, per_BirthYear, per_MembershipDate, per_Gender, per_fmr_ID, per_cls_ID, per_fam_ID, per_Envelope, per_DateLastEdited, per_DateEntered, per_EnteredBy, per_EditedBy, per_FriendDate, per_Flags FROM person_per WHERE per_ID = :p0';
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
            /** @var ChildPerson $obj */
            $obj = new ChildPerson();
            $obj->hydrate($row);
            PersonTableMap::addInstanceToPool($obj, null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key);
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
     * @return ChildPerson|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(PersonTableMap::COL_PER_ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(PersonTableMap::COL_PER_ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the per_ID column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE per_ID = 1234
     * $query->filterById(array(12, 34)); // WHERE per_ID IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE per_ID > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_ID, $id, $comparison);
    }

    /**
     * Filter the query on the per_Title column
     *
     * Example usage:
     * <code>
     * $query->filterByTitle('fooValue');   // WHERE per_Title = 'fooValue'
     * $query->filterByTitle('%fooValue%'); // WHERE per_Title LIKE '%fooValue%'
     * </code>
     *
     * @param     string $title The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByTitle($title = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($title)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $title)) {
                $title = str_replace('*', '%', $title);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_TITLE, $title, $comparison);
    }

    /**
     * Filter the query on the per_FirstName column
     *
     * Example usage:
     * <code>
     * $query->filterByFirstName('fooValue');   // WHERE per_FirstName = 'fooValue'
     * $query->filterByFirstName('%fooValue%'); // WHERE per_FirstName LIKE '%fooValue%'
     * </code>
     *
     * @param     string $firstName The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByFirstName($firstName = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($firstName)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $firstName)) {
                $firstName = str_replace('*', '%', $firstName);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_FIRSTNAME, $firstName, $comparison);
    }

    /**
     * Filter the query on the per_MiddleName column
     *
     * Example usage:
     * <code>
     * $query->filterByMiddleName('fooValue');   // WHERE per_MiddleName = 'fooValue'
     * $query->filterByMiddleName('%fooValue%'); // WHERE per_MiddleName LIKE '%fooValue%'
     * </code>
     *
     * @param     string $middleName The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByMiddleName($middleName = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($middleName)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $middleName)) {
                $middleName = str_replace('*', '%', $middleName);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_MIDDLENAME, $middleName, $comparison);
    }

    /**
     * Filter the query on the per_LastName column
     *
     * Example usage:
     * <code>
     * $query->filterByLastName('fooValue');   // WHERE per_LastName = 'fooValue'
     * $query->filterByLastName('%fooValue%'); // WHERE per_LastName LIKE '%fooValue%'
     * </code>
     *
     * @param     string $lastName The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByLastName($lastName = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($lastName)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $lastName)) {
                $lastName = str_replace('*', '%', $lastName);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_LASTNAME, $lastName, $comparison);
    }

    /**
     * Filter the query on the per_Suffix column
     *
     * Example usage:
     * <code>
     * $query->filterBySuffix('fooValue');   // WHERE per_Suffix = 'fooValue'
     * $query->filterBySuffix('%fooValue%'); // WHERE per_Suffix LIKE '%fooValue%'
     * </code>
     *
     * @param     string $suffix The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterBySuffix($suffix = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($suffix)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $suffix)) {
                $suffix = str_replace('*', '%', $suffix);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_SUFFIX, $suffix, $comparison);
    }

    /**
     * Filter the query on the per_Address1 column
     *
     * Example usage:
     * <code>
     * $query->filterByAddress1('fooValue');   // WHERE per_Address1 = 'fooValue'
     * $query->filterByAddress1('%fooValue%'); // WHERE per_Address1 LIKE '%fooValue%'
     * </code>
     *
     * @param     string $address1 The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByAddress1($address1 = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($address1)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $address1)) {
                $address1 = str_replace('*', '%', $address1);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_ADDRESS1, $address1, $comparison);
    }

    /**
     * Filter the query on the per_Address2 column
     *
     * Example usage:
     * <code>
     * $query->filterByAddress2('fooValue');   // WHERE per_Address2 = 'fooValue'
     * $query->filterByAddress2('%fooValue%'); // WHERE per_Address2 LIKE '%fooValue%'
     * </code>
     *
     * @param     string $address2 The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByAddress2($address2 = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($address2)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $address2)) {
                $address2 = str_replace('*', '%', $address2);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_ADDRESS2, $address2, $comparison);
    }

    /**
     * Filter the query on the per_City column
     *
     * Example usage:
     * <code>
     * $query->filterByCity('fooValue');   // WHERE per_City = 'fooValue'
     * $query->filterByCity('%fooValue%'); // WHERE per_City LIKE '%fooValue%'
     * </code>
     *
     * @param     string $city The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByCity($city = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($city)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $city)) {
                $city = str_replace('*', '%', $city);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_CITY, $city, $comparison);
    }

    /**
     * Filter the query on the per_State column
     *
     * Example usage:
     * <code>
     * $query->filterByState('fooValue');   // WHERE per_State = 'fooValue'
     * $query->filterByState('%fooValue%'); // WHERE per_State LIKE '%fooValue%'
     * </code>
     *
     * @param     string $state The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByState($state = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($state)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $state)) {
                $state = str_replace('*', '%', $state);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_STATE, $state, $comparison);
    }

    /**
     * Filter the query on the per_Zip column
     *
     * Example usage:
     * <code>
     * $query->filterByZip('fooValue');   // WHERE per_Zip = 'fooValue'
     * $query->filterByZip('%fooValue%'); // WHERE per_Zip LIKE '%fooValue%'
     * </code>
     *
     * @param     string $zip The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByZip($zip = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($zip)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $zip)) {
                $zip = str_replace('*', '%', $zip);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_ZIP, $zip, $comparison);
    }

    /**
     * Filter the query on the per_Country column
     *
     * Example usage:
     * <code>
     * $query->filterByCountry('fooValue');   // WHERE per_Country = 'fooValue'
     * $query->filterByCountry('%fooValue%'); // WHERE per_Country LIKE '%fooValue%'
     * </code>
     *
     * @param     string $country The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByCountry($country = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($country)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $country)) {
                $country = str_replace('*', '%', $country);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_COUNTRY, $country, $comparison);
    }

    /**
     * Filter the query on the per_HomePhone column
     *
     * Example usage:
     * <code>
     * $query->filterByHomePhone('fooValue');   // WHERE per_HomePhone = 'fooValue'
     * $query->filterByHomePhone('%fooValue%'); // WHERE per_HomePhone LIKE '%fooValue%'
     * </code>
     *
     * @param     string $homePhone The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByHomePhone($homePhone = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($homePhone)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $homePhone)) {
                $homePhone = str_replace('*', '%', $homePhone);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_HOMEPHONE, $homePhone, $comparison);
    }

    /**
     * Filter the query on the per_WorkPhone column
     *
     * Example usage:
     * <code>
     * $query->filterByWorkPhone('fooValue');   // WHERE per_WorkPhone = 'fooValue'
     * $query->filterByWorkPhone('%fooValue%'); // WHERE per_WorkPhone LIKE '%fooValue%'
     * </code>
     *
     * @param     string $workPhone The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByWorkPhone($workPhone = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($workPhone)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $workPhone)) {
                $workPhone = str_replace('*', '%', $workPhone);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_WORKPHONE, $workPhone, $comparison);
    }

    /**
     * Filter the query on the per_CellPhone column
     *
     * Example usage:
     * <code>
     * $query->filterByCellPhone('fooValue');   // WHERE per_CellPhone = 'fooValue'
     * $query->filterByCellPhone('%fooValue%'); // WHERE per_CellPhone LIKE '%fooValue%'
     * </code>
     *
     * @param     string $cellPhone The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByCellPhone($cellPhone = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($cellPhone)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $cellPhone)) {
                $cellPhone = str_replace('*', '%', $cellPhone);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_CELLPHONE, $cellPhone, $comparison);
    }

    /**
     * Filter the query on the per_Email column
     *
     * Example usage:
     * <code>
     * $query->filterByEmail('fooValue');   // WHERE per_Email = 'fooValue'
     * $query->filterByEmail('%fooValue%'); // WHERE per_Email LIKE '%fooValue%'
     * </code>
     *
     * @param     string $email The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByEmail($email = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($email)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $email)) {
                $email = str_replace('*', '%', $email);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_EMAIL, $email, $comparison);
    }

    /**
     * Filter the query on the per_WorkEmail column
     *
     * Example usage:
     * <code>
     * $query->filterByWorkEmail('fooValue');   // WHERE per_WorkEmail = 'fooValue'
     * $query->filterByWorkEmail('%fooValue%'); // WHERE per_WorkEmail LIKE '%fooValue%'
     * </code>
     *
     * @param     string $workEmail The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByWorkEmail($workEmail = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($workEmail)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $workEmail)) {
                $workEmail = str_replace('*', '%', $workEmail);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_WORKEMAIL, $workEmail, $comparison);
    }

    /**
     * Filter the query on the per_BirthMonth column
     *
     * Example usage:
     * <code>
     * $query->filterByBirthMonth(1234); // WHERE per_BirthMonth = 1234
     * $query->filterByBirthMonth(array(12, 34)); // WHERE per_BirthMonth IN (12, 34)
     * $query->filterByBirthMonth(array('min' => 12)); // WHERE per_BirthMonth > 12
     * </code>
     *
     * @param     mixed $birthMonth The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByBirthMonth($birthMonth = null, $comparison = null)
    {
        if (is_array($birthMonth)) {
            $useMinMax = false;
            if (isset($birthMonth['min'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_BIRTHMONTH, $birthMonth['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($birthMonth['max'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_BIRTHMONTH, $birthMonth['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_BIRTHMONTH, $birthMonth, $comparison);
    }

    /**
     * Filter the query on the per_BirthDay column
     *
     * Example usage:
     * <code>
     * $query->filterByBirthDay(1234); // WHERE per_BirthDay = 1234
     * $query->filterByBirthDay(array(12, 34)); // WHERE per_BirthDay IN (12, 34)
     * $query->filterByBirthDay(array('min' => 12)); // WHERE per_BirthDay > 12
     * </code>
     *
     * @param     mixed $birthDay The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByBirthDay($birthDay = null, $comparison = null)
    {
        if (is_array($birthDay)) {
            $useMinMax = false;
            if (isset($birthDay['min'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_BIRTHDAY, $birthDay['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($birthDay['max'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_BIRTHDAY, $birthDay['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_BIRTHDAY, $birthDay, $comparison);
    }

    /**
     * Filter the query on the per_BirthYear column
     *
     * Example usage:
     * <code>
     * $query->filterByBirthYear(1234); // WHERE per_BirthYear = 1234
     * $query->filterByBirthYear(array(12, 34)); // WHERE per_BirthYear IN (12, 34)
     * $query->filterByBirthYear(array('min' => 12)); // WHERE per_BirthYear > 12
     * </code>
     *
     * @param     mixed $birthYear The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByBirthYear($birthYear = null, $comparison = null)
    {
        if (is_array($birthYear)) {
            $useMinMax = false;
            if (isset($birthYear['min'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_BIRTHYEAR, $birthYear['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($birthYear['max'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_BIRTHYEAR, $birthYear['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_BIRTHYEAR, $birthYear, $comparison);
    }

    /**
     * Filter the query on the per_MembershipDate column
     *
     * Example usage:
     * <code>
     * $query->filterByMembershipDate('2011-03-14'); // WHERE per_MembershipDate = '2011-03-14'
     * $query->filterByMembershipDate('now'); // WHERE per_MembershipDate = '2011-03-14'
     * $query->filterByMembershipDate(array('max' => 'yesterday')); // WHERE per_MembershipDate > '2011-03-13'
     * </code>
     *
     * @param     mixed $membershipDate The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByMembershipDate($membershipDate = null, $comparison = null)
    {
        if (is_array($membershipDate)) {
            $useMinMax = false;
            if (isset($membershipDate['min'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_MEMBERSHIPDATE, $membershipDate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($membershipDate['max'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_MEMBERSHIPDATE, $membershipDate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_MEMBERSHIPDATE, $membershipDate, $comparison);
    }

    /**
     * Filter the query on the per_Gender column
     *
     * Example usage:
     * <code>
     * $query->filterByGender(true); // WHERE per_Gender = true
     * $query->filterByGender('yes'); // WHERE per_Gender = true
     * </code>
     *
     * @param     boolean|string $gender The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByGender($gender = null, $comparison = null)
    {
        if (is_string($gender)) {
            $gender = in_array(strtolower($gender), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_GENDER, $gender, $comparison);
    }

    /**
     * Filter the query on the per_fmr_ID column
     *
     * Example usage:
     * <code>
     * $query->filterByFmrId(1234); // WHERE per_fmr_ID = 1234
     * $query->filterByFmrId(array(12, 34)); // WHERE per_fmr_ID IN (12, 34)
     * $query->filterByFmrId(array('min' => 12)); // WHERE per_fmr_ID > 12
     * </code>
     *
     * @param     mixed $fmrId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByFmrId($fmrId = null, $comparison = null)
    {
        if (is_array($fmrId)) {
            $useMinMax = false;
            if (isset($fmrId['min'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_FMR_ID, $fmrId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($fmrId['max'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_FMR_ID, $fmrId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_FMR_ID, $fmrId, $comparison);
    }

    /**
     * Filter the query on the per_cls_ID column
     *
     * Example usage:
     * <code>
     * $query->filterByClsId(1234); // WHERE per_cls_ID = 1234
     * $query->filterByClsId(array(12, 34)); // WHERE per_cls_ID IN (12, 34)
     * $query->filterByClsId(array('min' => 12)); // WHERE per_cls_ID > 12
     * </code>
     *
     * @param     mixed $clsId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByClsId($clsId = null, $comparison = null)
    {
        if (is_array($clsId)) {
            $useMinMax = false;
            if (isset($clsId['min'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_CLS_ID, $clsId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($clsId['max'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_CLS_ID, $clsId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_CLS_ID, $clsId, $comparison);
    }

    /**
     * Filter the query on the per_fam_ID column
     *
     * Example usage:
     * <code>
     * $query->filterByFamId(1234); // WHERE per_fam_ID = 1234
     * $query->filterByFamId(array(12, 34)); // WHERE per_fam_ID IN (12, 34)
     * $query->filterByFamId(array('min' => 12)); // WHERE per_fam_ID > 12
     * </code>
     *
     * @param     mixed $famId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByFamId($famId = null, $comparison = null)
    {
        if (is_array($famId)) {
            $useMinMax = false;
            if (isset($famId['min'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_FAM_ID, $famId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($famId['max'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_FAM_ID, $famId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_FAM_ID, $famId, $comparison);
    }

    /**
     * Filter the query on the per_Envelope column
     *
     * Example usage:
     * <code>
     * $query->filterByEnvelope(1234); // WHERE per_Envelope = 1234
     * $query->filterByEnvelope(array(12, 34)); // WHERE per_Envelope IN (12, 34)
     * $query->filterByEnvelope(array('min' => 12)); // WHERE per_Envelope > 12
     * </code>
     *
     * @param     mixed $envelope The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByEnvelope($envelope = null, $comparison = null)
    {
        if (is_array($envelope)) {
            $useMinMax = false;
            if (isset($envelope['min'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_ENVELOPE, $envelope['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($envelope['max'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_ENVELOPE, $envelope['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_ENVELOPE, $envelope, $comparison);
    }

    /**
     * Filter the query on the per_DateLastEdited column
     *
     * Example usage:
     * <code>
     * $query->filterByDateLastEdited('2011-03-14'); // WHERE per_DateLastEdited = '2011-03-14'
     * $query->filterByDateLastEdited('now'); // WHERE per_DateLastEdited = '2011-03-14'
     * $query->filterByDateLastEdited(array('max' => 'yesterday')); // WHERE per_DateLastEdited > '2011-03-13'
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
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByDateLastEdited($dateLastEdited = null, $comparison = null)
    {
        if (is_array($dateLastEdited)) {
            $useMinMax = false;
            if (isset($dateLastEdited['min'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_DATELASTEDITED, $dateLastEdited['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($dateLastEdited['max'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_DATELASTEDITED, $dateLastEdited['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_DATELASTEDITED, $dateLastEdited, $comparison);
    }

    /**
     * Filter the query on the per_DateEntered column
     *
     * Example usage:
     * <code>
     * $query->filterByDateEntered('2011-03-14'); // WHERE per_DateEntered = '2011-03-14'
     * $query->filterByDateEntered('now'); // WHERE per_DateEntered = '2011-03-14'
     * $query->filterByDateEntered(array('max' => 'yesterday')); // WHERE per_DateEntered > '2011-03-13'
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
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByDateEntered($dateEntered = null, $comparison = null)
    {
        if (is_array($dateEntered)) {
            $useMinMax = false;
            if (isset($dateEntered['min'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_DATEENTERED, $dateEntered['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($dateEntered['max'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_DATEENTERED, $dateEntered['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_DATEENTERED, $dateEntered, $comparison);
    }

    /**
     * Filter the query on the per_EnteredBy column
     *
     * Example usage:
     * <code>
     * $query->filterByEnteredBy(1234); // WHERE per_EnteredBy = 1234
     * $query->filterByEnteredBy(array(12, 34)); // WHERE per_EnteredBy IN (12, 34)
     * $query->filterByEnteredBy(array('min' => 12)); // WHERE per_EnteredBy > 12
     * </code>
     *
     * @param     mixed $enteredBy The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByEnteredBy($enteredBy = null, $comparison = null)
    {
        if (is_array($enteredBy)) {
            $useMinMax = false;
            if (isset($enteredBy['min'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_ENTEREDBY, $enteredBy['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($enteredBy['max'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_ENTEREDBY, $enteredBy['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_ENTEREDBY, $enteredBy, $comparison);
    }

    /**
     * Filter the query on the per_EditedBy column
     *
     * Example usage:
     * <code>
     * $query->filterByEditedBy(1234); // WHERE per_EditedBy = 1234
     * $query->filterByEditedBy(array(12, 34)); // WHERE per_EditedBy IN (12, 34)
     * $query->filterByEditedBy(array('min' => 12)); // WHERE per_EditedBy > 12
     * </code>
     *
     * @param     mixed $editedBy The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByEditedBy($editedBy = null, $comparison = null)
    {
        if (is_array($editedBy)) {
            $useMinMax = false;
            if (isset($editedBy['min'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_EDITEDBY, $editedBy['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($editedBy['max'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_EDITEDBY, $editedBy['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_EDITEDBY, $editedBy, $comparison);
    }

    /**
     * Filter the query on the per_FriendDate column
     *
     * Example usage:
     * <code>
     * $query->filterByFriendDate('2011-03-14'); // WHERE per_FriendDate = '2011-03-14'
     * $query->filterByFriendDate('now'); // WHERE per_FriendDate = '2011-03-14'
     * $query->filterByFriendDate(array('max' => 'yesterday')); // WHERE per_FriendDate > '2011-03-13'
     * </code>
     *
     * @param     mixed $friendDate The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByFriendDate($friendDate = null, $comparison = null)
    {
        if (is_array($friendDate)) {
            $useMinMax = false;
            if (isset($friendDate['min'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_FRIENDDATE, $friendDate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($friendDate['max'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_FRIENDDATE, $friendDate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_FRIENDDATE, $friendDate, $comparison);
    }

    /**
     * Filter the query on the per_Flags column
     *
     * Example usage:
     * <code>
     * $query->filterByFlags(1234); // WHERE per_Flags = 1234
     * $query->filterByFlags(array(12, 34)); // WHERE per_Flags IN (12, 34)
     * $query->filterByFlags(array('min' => 12)); // WHERE per_Flags > 12
     * </code>
     *
     * @param     mixed $flags The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function filterByFlags($flags = null, $comparison = null)
    {
        if (is_array($flags)) {
            $useMinMax = false;
            if (isset($flags['min'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_FLAGS, $flags['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($flags['max'])) {
                $this->addUsingAlias(PersonTableMap::COL_PER_FLAGS, $flags['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PersonTableMap::COL_PER_FLAGS, $flags, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildPerson $person Object to remove from the list of results
     *
     * @return $this|ChildPersonQuery The current query, for fluid interface
     */
    public function prune($person = null)
    {
        if ($person) {
            $this->addUsingAlias(PersonTableMap::COL_PER_ID, $person->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the person_per table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(PersonTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            PersonTableMap::clearInstancePool();
            PersonTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(PersonTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(PersonTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            PersonTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            PersonTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // PersonQuery
