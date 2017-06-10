<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\Family as ChildFamily;
use ChurchCRM\FamilyQuery as ChildFamilyQuery;
use ChurchCRM\Map\FamilyTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\ModelJoin;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'family_fam' table.
 *
 *
 *
 * @method     ChildFamilyQuery orderById($order = Criteria::ASC) Order by the fam_ID column
 * @method     ChildFamilyQuery orderByName($order = Criteria::ASC) Order by the fam_Name column
 * @method     ChildFamilyQuery orderByAddress1($order = Criteria::ASC) Order by the fam_Address1 column
 * @method     ChildFamilyQuery orderByAddress2($order = Criteria::ASC) Order by the fam_Address2 column
 * @method     ChildFamilyQuery orderByCity($order = Criteria::ASC) Order by the fam_City column
 * @method     ChildFamilyQuery orderByState($order = Criteria::ASC) Order by the fam_State column
 * @method     ChildFamilyQuery orderByZip($order = Criteria::ASC) Order by the fam_Zip column
 * @method     ChildFamilyQuery orderByCountry($order = Criteria::ASC) Order by the fam_Country column
 * @method     ChildFamilyQuery orderByHomePhone($order = Criteria::ASC) Order by the fam_HomePhone column
 * @method     ChildFamilyQuery orderByWorkPhone($order = Criteria::ASC) Order by the fam_WorkPhone column
 * @method     ChildFamilyQuery orderByCellPhone($order = Criteria::ASC) Order by the fam_CellPhone column
 * @method     ChildFamilyQuery orderByEmail($order = Criteria::ASC) Order by the fam_Email column
 * @method     ChildFamilyQuery orderByWeddingdate($order = Criteria::ASC) Order by the fam_WeddingDate column
 * @method     ChildFamilyQuery orderByDateEntered($order = Criteria::ASC) Order by the fam_DateEntered column
 * @method     ChildFamilyQuery orderByDateLastEdited($order = Criteria::ASC) Order by the fam_DateLastEdited column
 * @method     ChildFamilyQuery orderByEnteredBy($order = Criteria::ASC) Order by the fam_EnteredBy column
 * @method     ChildFamilyQuery orderByEditedBy($order = Criteria::ASC) Order by the fam_EditedBy column
 * @method     ChildFamilyQuery orderByScanCheck($order = Criteria::ASC) Order by the fam_scanCheck column
 * @method     ChildFamilyQuery orderByScanCredit($order = Criteria::ASC) Order by the fam_scanCredit column
 * @method     ChildFamilyQuery orderBySendNewsletter($order = Criteria::ASC) Order by the fam_SendNewsLetter column
 * @method     ChildFamilyQuery orderByDateDeactivated($order = Criteria::ASC) Order by the fam_DateDeactivated column
 * @method     ChildFamilyQuery orderByOkToCanvass($order = Criteria::ASC) Order by the fam_OkToCanvass column
 * @method     ChildFamilyQuery orderByCanvasser($order = Criteria::ASC) Order by the fam_Canvasser column
 * @method     ChildFamilyQuery orderByLatitude($order = Criteria::ASC) Order by the fam_Latitude column
 * @method     ChildFamilyQuery orderByLongitude($order = Criteria::ASC) Order by the fam_Longitude column
 * @method     ChildFamilyQuery orderByEnvelope($order = Criteria::ASC) Order by the fam_Envelope column
 *
 * @method     ChildFamilyQuery groupById() Group by the fam_ID column
 * @method     ChildFamilyQuery groupByName() Group by the fam_Name column
 * @method     ChildFamilyQuery groupByAddress1() Group by the fam_Address1 column
 * @method     ChildFamilyQuery groupByAddress2() Group by the fam_Address2 column
 * @method     ChildFamilyQuery groupByCity() Group by the fam_City column
 * @method     ChildFamilyQuery groupByState() Group by the fam_State column
 * @method     ChildFamilyQuery groupByZip() Group by the fam_Zip column
 * @method     ChildFamilyQuery groupByCountry() Group by the fam_Country column
 * @method     ChildFamilyQuery groupByHomePhone() Group by the fam_HomePhone column
 * @method     ChildFamilyQuery groupByWorkPhone() Group by the fam_WorkPhone column
 * @method     ChildFamilyQuery groupByCellPhone() Group by the fam_CellPhone column
 * @method     ChildFamilyQuery groupByEmail() Group by the fam_Email column
 * @method     ChildFamilyQuery groupByWeddingdate() Group by the fam_WeddingDate column
 * @method     ChildFamilyQuery groupByDateEntered() Group by the fam_DateEntered column
 * @method     ChildFamilyQuery groupByDateLastEdited() Group by the fam_DateLastEdited column
 * @method     ChildFamilyQuery groupByEnteredBy() Group by the fam_EnteredBy column
 * @method     ChildFamilyQuery groupByEditedBy() Group by the fam_EditedBy column
 * @method     ChildFamilyQuery groupByScanCheck() Group by the fam_scanCheck column
 * @method     ChildFamilyQuery groupByScanCredit() Group by the fam_scanCredit column
 * @method     ChildFamilyQuery groupBySendNewsletter() Group by the fam_SendNewsLetter column
 * @method     ChildFamilyQuery groupByDateDeactivated() Group by the fam_DateDeactivated column
 * @method     ChildFamilyQuery groupByOkToCanvass() Group by the fam_OkToCanvass column
 * @method     ChildFamilyQuery groupByCanvasser() Group by the fam_Canvasser column
 * @method     ChildFamilyQuery groupByLatitude() Group by the fam_Latitude column
 * @method     ChildFamilyQuery groupByLongitude() Group by the fam_Longitude column
 * @method     ChildFamilyQuery groupByEnvelope() Group by the fam_Envelope column
 *
 * @method     ChildFamilyQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildFamilyQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildFamilyQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildFamilyQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildFamilyQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildFamilyQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildFamilyQuery leftJoinPerson($relationAlias = null) Adds a LEFT JOIN clause to the query using the Person relation
 * @method     ChildFamilyQuery rightJoinPerson($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Person relation
 * @method     ChildFamilyQuery innerJoinPerson($relationAlias = null) Adds a INNER JOIN clause to the query using the Person relation
 *
 * @method     ChildFamilyQuery joinWithPerson($joinType = Criteria::INNER_JOIN) Adds a join clause and with to the query using the Person relation
 *
 * @method     ChildFamilyQuery leftJoinWithPerson() Adds a LEFT JOIN clause and with to the query using the Person relation
 * @method     ChildFamilyQuery rightJoinWithPerson() Adds a RIGHT JOIN clause and with to the query using the Person relation
 * @method     ChildFamilyQuery innerJoinWithPerson() Adds a INNER JOIN clause and with to the query using the Person relation
 *
 * @method     ChildFamilyQuery leftJoinNote($relationAlias = null) Adds a LEFT JOIN clause to the query using the Note relation
 * @method     ChildFamilyQuery rightJoinNote($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Note relation
 * @method     ChildFamilyQuery innerJoinNote($relationAlias = null) Adds a INNER JOIN clause to the query using the Note relation
 *
 * @method     ChildFamilyQuery joinWithNote($joinType = Criteria::INNER_JOIN) Adds a join clause and with to the query using the Note relation
 *
 * @method     ChildFamilyQuery leftJoinWithNote() Adds a LEFT JOIN clause and with to the query using the Note relation
 * @method     ChildFamilyQuery rightJoinWithNote() Adds a RIGHT JOIN clause and with to the query using the Note relation
 * @method     ChildFamilyQuery innerJoinWithNote() Adds a INNER JOIN clause and with to the query using the Note relation
 *
 * @method     ChildFamilyQuery leftJoinPledge($relationAlias = null) Adds a LEFT JOIN clause to the query using the Pledge relation
 * @method     ChildFamilyQuery rightJoinPledge($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Pledge relation
 * @method     ChildFamilyQuery innerJoinPledge($relationAlias = null) Adds a INNER JOIN clause to the query using the Pledge relation
 *
 * @method     ChildFamilyQuery joinWithPledge($joinType = Criteria::INNER_JOIN) Adds a join clause and with to the query using the Pledge relation
 *
 * @method     ChildFamilyQuery leftJoinWithPledge() Adds a LEFT JOIN clause and with to the query using the Pledge relation
 * @method     ChildFamilyQuery rightJoinWithPledge() Adds a RIGHT JOIN clause and with to the query using the Pledge relation
 * @method     ChildFamilyQuery innerJoinWithPledge() Adds a INNER JOIN clause and with to the query using the Pledge relation
 *
 * @method     \ChurchCRM\PersonQuery|\ChurchCRM\NoteQuery|\ChurchCRM\PledgeQuery endUse() Finalizes a secondary criteria and merges it with its primary Criteria
 *
 * @method     ChildFamily findOne(ConnectionInterface $con = null) Return the first ChildFamily matching the query
 * @method     ChildFamily findOneOrCreate(ConnectionInterface $con = null) Return the first ChildFamily matching the query, or a new ChildFamily object populated from the query conditions when no match is found
 *
 * @method     ChildFamily findOneById(int $fam_ID) Return the first ChildFamily filtered by the fam_ID column
 * @method     ChildFamily findOneByName(string $fam_Name) Return the first ChildFamily filtered by the fam_Name column
 * @method     ChildFamily findOneByAddress1(string $fam_Address1) Return the first ChildFamily filtered by the fam_Address1 column
 * @method     ChildFamily findOneByAddress2(string $fam_Address2) Return the first ChildFamily filtered by the fam_Address2 column
 * @method     ChildFamily findOneByCity(string $fam_City) Return the first ChildFamily filtered by the fam_City column
 * @method     ChildFamily findOneByState(string $fam_State) Return the first ChildFamily filtered by the fam_State column
 * @method     ChildFamily findOneByZip(string $fam_Zip) Return the first ChildFamily filtered by the fam_Zip column
 * @method     ChildFamily findOneByCountry(string $fam_Country) Return the first ChildFamily filtered by the fam_Country column
 * @method     ChildFamily findOneByHomePhone(string $fam_HomePhone) Return the first ChildFamily filtered by the fam_HomePhone column
 * @method     ChildFamily findOneByWorkPhone(string $fam_WorkPhone) Return the first ChildFamily filtered by the fam_WorkPhone column
 * @method     ChildFamily findOneByCellPhone(string $fam_CellPhone) Return the first ChildFamily filtered by the fam_CellPhone column
 * @method     ChildFamily findOneByEmail(string $fam_Email) Return the first ChildFamily filtered by the fam_Email column
 * @method     ChildFamily findOneByWeddingdate(string $fam_WeddingDate) Return the first ChildFamily filtered by the fam_WeddingDate column
 * @method     ChildFamily findOneByDateEntered(string $fam_DateEntered) Return the first ChildFamily filtered by the fam_DateEntered column
 * @method     ChildFamily findOneByDateLastEdited(string $fam_DateLastEdited) Return the first ChildFamily filtered by the fam_DateLastEdited column
 * @method     ChildFamily findOneByEnteredBy(int $fam_EnteredBy) Return the first ChildFamily filtered by the fam_EnteredBy column
 * @method     ChildFamily findOneByEditedBy(int $fam_EditedBy) Return the first ChildFamily filtered by the fam_EditedBy column
 * @method     ChildFamily findOneByScanCheck(string $fam_scanCheck) Return the first ChildFamily filtered by the fam_scanCheck column
 * @method     ChildFamily findOneByScanCredit(string $fam_scanCredit) Return the first ChildFamily filtered by the fam_scanCredit column
 * @method     ChildFamily findOneBySendNewsletter(string $fam_SendNewsLetter) Return the first ChildFamily filtered by the fam_SendNewsLetter column
 * @method     ChildFamily findOneByDateDeactivated(string $fam_DateDeactivated) Return the first ChildFamily filtered by the fam_DateDeactivated column
 * @method     ChildFamily findOneByOkToCanvass(string $fam_OkToCanvass) Return the first ChildFamily filtered by the fam_OkToCanvass column
 * @method     ChildFamily findOneByCanvasser(int $fam_Canvasser) Return the first ChildFamily filtered by the fam_Canvasser column
 * @method     ChildFamily findOneByLatitude(double $fam_Latitude) Return the first ChildFamily filtered by the fam_Latitude column
 * @method     ChildFamily findOneByLongitude(double $fam_Longitude) Return the first ChildFamily filtered by the fam_Longitude column
 * @method     ChildFamily findOneByEnvelope(int $fam_Envelope) Return the first ChildFamily filtered by the fam_Envelope column *

 * @method     ChildFamily requirePk($key, ConnectionInterface $con = null) Return the ChildFamily by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFamily requireOne(ConnectionInterface $con = null) Return the first ChildFamily matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildFamily requireOneById(int $fam_ID) Return the first ChildFamily filtered by the fam_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFamily requireOneByName(string $fam_Name) Return the first ChildFamily filtered by the fam_Name column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFamily requireOneByAddress1(string $fam_Address1) Return the first ChildFamily filtered by the fam_Address1 column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFamily requireOneByAddress2(string $fam_Address2) Return the first ChildFamily filtered by the fam_Address2 column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFamily requireOneByCity(string $fam_City) Return the first ChildFamily filtered by the fam_City column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFamily requireOneByState(string $fam_State) Return the first ChildFamily filtered by the fam_State column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFamily requireOneByZip(string $fam_Zip) Return the first ChildFamily filtered by the fam_Zip column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFamily requireOneByCountry(string $fam_Country) Return the first ChildFamily filtered by the fam_Country column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFamily requireOneByHomePhone(string $fam_HomePhone) Return the first ChildFamily filtered by the fam_HomePhone column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFamily requireOneByWorkPhone(string $fam_WorkPhone) Return the first ChildFamily filtered by the fam_WorkPhone column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFamily requireOneByCellPhone(string $fam_CellPhone) Return the first ChildFamily filtered by the fam_CellPhone column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFamily requireOneByEmail(string $fam_Email) Return the first ChildFamily filtered by the fam_Email column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFamily requireOneByWeddingdate(string $fam_WeddingDate) Return the first ChildFamily filtered by the fam_WeddingDate column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFamily requireOneByDateEntered(string $fam_DateEntered) Return the first ChildFamily filtered by the fam_DateEntered column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFamily requireOneByDateLastEdited(string $fam_DateLastEdited) Return the first ChildFamily filtered by the fam_DateLastEdited column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFamily requireOneByEnteredBy(int $fam_EnteredBy) Return the first ChildFamily filtered by the fam_EnteredBy column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFamily requireOneByEditedBy(int $fam_EditedBy) Return the first ChildFamily filtered by the fam_EditedBy column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFamily requireOneByScanCheck(string $fam_scanCheck) Return the first ChildFamily filtered by the fam_scanCheck column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFamily requireOneByScanCredit(string $fam_scanCredit) Return the first ChildFamily filtered by the fam_scanCredit column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFamily requireOneBySendNewsletter(string $fam_SendNewsLetter) Return the first ChildFamily filtered by the fam_SendNewsLetter column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFamily requireOneByDateDeactivated(string $fam_DateDeactivated) Return the first ChildFamily filtered by the fam_DateDeactivated column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFamily requireOneByOkToCanvass(string $fam_OkToCanvass) Return the first ChildFamily filtered by the fam_OkToCanvass column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFamily requireOneByCanvasser(int $fam_Canvasser) Return the first ChildFamily filtered by the fam_Canvasser column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFamily requireOneByLatitude(double $fam_Latitude) Return the first ChildFamily filtered by the fam_Latitude column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFamily requireOneByLongitude(double $fam_Longitude) Return the first ChildFamily filtered by the fam_Longitude column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFamily requireOneByEnvelope(int $fam_Envelope) Return the first ChildFamily filtered by the fam_Envelope column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildFamily[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildFamily objects based on current ModelCriteria
 * @method     ChildFamily[]|ObjectCollection findById(int $fam_ID) Return ChildFamily objects filtered by the fam_ID column
 * @method     ChildFamily[]|ObjectCollection findByName(string $fam_Name) Return ChildFamily objects filtered by the fam_Name column
 * @method     ChildFamily[]|ObjectCollection findByAddress1(string $fam_Address1) Return ChildFamily objects filtered by the fam_Address1 column
 * @method     ChildFamily[]|ObjectCollection findByAddress2(string $fam_Address2) Return ChildFamily objects filtered by the fam_Address2 column
 * @method     ChildFamily[]|ObjectCollection findByCity(string $fam_City) Return ChildFamily objects filtered by the fam_City column
 * @method     ChildFamily[]|ObjectCollection findByState(string $fam_State) Return ChildFamily objects filtered by the fam_State column
 * @method     ChildFamily[]|ObjectCollection findByZip(string $fam_Zip) Return ChildFamily objects filtered by the fam_Zip column
 * @method     ChildFamily[]|ObjectCollection findByCountry(string $fam_Country) Return ChildFamily objects filtered by the fam_Country column
 * @method     ChildFamily[]|ObjectCollection findByHomePhone(string $fam_HomePhone) Return ChildFamily objects filtered by the fam_HomePhone column
 * @method     ChildFamily[]|ObjectCollection findByWorkPhone(string $fam_WorkPhone) Return ChildFamily objects filtered by the fam_WorkPhone column
 * @method     ChildFamily[]|ObjectCollection findByCellPhone(string $fam_CellPhone) Return ChildFamily objects filtered by the fam_CellPhone column
 * @method     ChildFamily[]|ObjectCollection findByEmail(string $fam_Email) Return ChildFamily objects filtered by the fam_Email column
 * @method     ChildFamily[]|ObjectCollection findByWeddingdate(string $fam_WeddingDate) Return ChildFamily objects filtered by the fam_WeddingDate column
 * @method     ChildFamily[]|ObjectCollection findByDateEntered(string $fam_DateEntered) Return ChildFamily objects filtered by the fam_DateEntered column
 * @method     ChildFamily[]|ObjectCollection findByDateLastEdited(string $fam_DateLastEdited) Return ChildFamily objects filtered by the fam_DateLastEdited column
 * @method     ChildFamily[]|ObjectCollection findByEnteredBy(int $fam_EnteredBy) Return ChildFamily objects filtered by the fam_EnteredBy column
 * @method     ChildFamily[]|ObjectCollection findByEditedBy(int $fam_EditedBy) Return ChildFamily objects filtered by the fam_EditedBy column
 * @method     ChildFamily[]|ObjectCollection findByScanCheck(string $fam_scanCheck) Return ChildFamily objects filtered by the fam_scanCheck column
 * @method     ChildFamily[]|ObjectCollection findByScanCredit(string $fam_scanCredit) Return ChildFamily objects filtered by the fam_scanCredit column
 * @method     ChildFamily[]|ObjectCollection findBySendNewsletter(string $fam_SendNewsLetter) Return ChildFamily objects filtered by the fam_SendNewsLetter column
 * @method     ChildFamily[]|ObjectCollection findByDateDeactivated(string $fam_DateDeactivated) Return ChildFamily objects filtered by the fam_DateDeactivated column
 * @method     ChildFamily[]|ObjectCollection findByOkToCanvass(string $fam_OkToCanvass) Return ChildFamily objects filtered by the fam_OkToCanvass column
 * @method     ChildFamily[]|ObjectCollection findByCanvasser(int $fam_Canvasser) Return ChildFamily objects filtered by the fam_Canvasser column
 * @method     ChildFamily[]|ObjectCollection findByLatitude(double $fam_Latitude) Return ChildFamily objects filtered by the fam_Latitude column
 * @method     ChildFamily[]|ObjectCollection findByLongitude(double $fam_Longitude) Return ChildFamily objects filtered by the fam_Longitude column
 * @method     ChildFamily[]|ObjectCollection findByEnvelope(int $fam_Envelope) Return ChildFamily objects filtered by the fam_Envelope column
 * @method     ChildFamily[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class FamilyQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\FamilyQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\Family', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildFamilyQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildFamilyQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildFamilyQuery) {
            return $criteria;
        }
        $query = new ChildFamilyQuery();
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
     * @return ChildFamily|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(FamilyTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (
            $this->formatter || $this->modelAlias || $this->with || $this->select
            || $this->selectColumns || $this->asColumns || $this->selectModifiers
            || $this->map || $this->having || $this->joins
        ) {
            return $this->findPkComplex($key, $con);
        }

        if ((null !== ($obj = FamilyTableMap::getInstanceFromPool(null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key)))) {
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
     * @return ChildFamily A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT fam_ID, fam_Name, fam_Address1, fam_Address2, fam_City, fam_State, fam_Zip, fam_Country, fam_HomePhone, fam_WorkPhone, fam_CellPhone, fam_Email, fam_WeddingDate, fam_DateEntered, fam_DateLastEdited, fam_EnteredBy, fam_EditedBy, fam_scanCheck, fam_scanCredit, fam_SendNewsLetter, fam_DateDeactivated, fam_OkToCanvass, fam_Canvasser, fam_Latitude, fam_Longitude, fam_Envelope FROM family_fam WHERE fam_ID = :p0';
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
            /** @var ChildFamily $obj */
            $obj = new ChildFamily();
            $obj->hydrate($row);
            FamilyTableMap::addInstanceToPool($obj, null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key);
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
     * @return ChildFamily|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(FamilyTableMap::COL_FAM_ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(FamilyTableMap::COL_FAM_ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the fam_ID column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE fam_ID = 1234
     * $query->filterById(array(12, 34)); // WHERE fam_ID IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE fam_ID > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(FamilyTableMap::COL_FAM_ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(FamilyTableMap::COL_FAM_ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FamilyTableMap::COL_FAM_ID, $id, $comparison);
    }

    /**
     * Filter the query on the fam_Name column
     *
     * Example usage:
     * <code>
     * $query->filterByName('fooValue');   // WHERE fam_Name = 'fooValue'
     * $query->filterByName('%fooValue%', Criteria::LIKE); // WHERE fam_Name LIKE '%fooValue%'
     * </code>
     *
     * @param     string $name The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function filterByName($name = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($name)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FamilyTableMap::COL_FAM_NAME, $name, $comparison);
    }

    /**
     * Filter the query on the fam_Address1 column
     *
     * Example usage:
     * <code>
     * $query->filterByAddress1('fooValue');   // WHERE fam_Address1 = 'fooValue'
     * $query->filterByAddress1('%fooValue%', Criteria::LIKE); // WHERE fam_Address1 LIKE '%fooValue%'
     * </code>
     *
     * @param     string $address1 The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function filterByAddress1($address1 = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($address1)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FamilyTableMap::COL_FAM_ADDRESS1, $address1, $comparison);
    }

    /**
     * Filter the query on the fam_Address2 column
     *
     * Example usage:
     * <code>
     * $query->filterByAddress2('fooValue');   // WHERE fam_Address2 = 'fooValue'
     * $query->filterByAddress2('%fooValue%', Criteria::LIKE); // WHERE fam_Address2 LIKE '%fooValue%'
     * </code>
     *
     * @param     string $address2 The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function filterByAddress2($address2 = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($address2)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FamilyTableMap::COL_FAM_ADDRESS2, $address2, $comparison);
    }

    /**
     * Filter the query on the fam_City column
     *
     * Example usage:
     * <code>
     * $query->filterByCity('fooValue');   // WHERE fam_City = 'fooValue'
     * $query->filterByCity('%fooValue%', Criteria::LIKE); // WHERE fam_City LIKE '%fooValue%'
     * </code>
     *
     * @param     string $city The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function filterByCity($city = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($city)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FamilyTableMap::COL_FAM_CITY, $city, $comparison);
    }

    /**
     * Filter the query on the fam_State column
     *
     * Example usage:
     * <code>
     * $query->filterByState('fooValue');   // WHERE fam_State = 'fooValue'
     * $query->filterByState('%fooValue%', Criteria::LIKE); // WHERE fam_State LIKE '%fooValue%'
     * </code>
     *
     * @param     string $state The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function filterByState($state = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($state)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FamilyTableMap::COL_FAM_STATE, $state, $comparison);
    }

    /**
     * Filter the query on the fam_Zip column
     *
     * Example usage:
     * <code>
     * $query->filterByZip('fooValue');   // WHERE fam_Zip = 'fooValue'
     * $query->filterByZip('%fooValue%', Criteria::LIKE); // WHERE fam_Zip LIKE '%fooValue%'
     * </code>
     *
     * @param     string $zip The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function filterByZip($zip = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($zip)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FamilyTableMap::COL_FAM_ZIP, $zip, $comparison);
    }

    /**
     * Filter the query on the fam_Country column
     *
     * Example usage:
     * <code>
     * $query->filterByCountry('fooValue');   // WHERE fam_Country = 'fooValue'
     * $query->filterByCountry('%fooValue%', Criteria::LIKE); // WHERE fam_Country LIKE '%fooValue%'
     * </code>
     *
     * @param     string $country The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function filterByCountry($country = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($country)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FamilyTableMap::COL_FAM_COUNTRY, $country, $comparison);
    }

    /**
     * Filter the query on the fam_HomePhone column
     *
     * Example usage:
     * <code>
     * $query->filterByHomePhone('fooValue');   // WHERE fam_HomePhone = 'fooValue'
     * $query->filterByHomePhone('%fooValue%', Criteria::LIKE); // WHERE fam_HomePhone LIKE '%fooValue%'
     * </code>
     *
     * @param     string $homePhone The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function filterByHomePhone($homePhone = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($homePhone)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FamilyTableMap::COL_FAM_HOMEPHONE, $homePhone, $comparison);
    }

    /**
     * Filter the query on the fam_WorkPhone column
     *
     * Example usage:
     * <code>
     * $query->filterByWorkPhone('fooValue');   // WHERE fam_WorkPhone = 'fooValue'
     * $query->filterByWorkPhone('%fooValue%', Criteria::LIKE); // WHERE fam_WorkPhone LIKE '%fooValue%'
     * </code>
     *
     * @param     string $workPhone The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function filterByWorkPhone($workPhone = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($workPhone)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FamilyTableMap::COL_FAM_WORKPHONE, $workPhone, $comparison);
    }

    /**
     * Filter the query on the fam_CellPhone column
     *
     * Example usage:
     * <code>
     * $query->filterByCellPhone('fooValue');   // WHERE fam_CellPhone = 'fooValue'
     * $query->filterByCellPhone('%fooValue%', Criteria::LIKE); // WHERE fam_CellPhone LIKE '%fooValue%'
     * </code>
     *
     * @param     string $cellPhone The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function filterByCellPhone($cellPhone = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($cellPhone)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FamilyTableMap::COL_FAM_CELLPHONE, $cellPhone, $comparison);
    }

    /**
     * Filter the query on the fam_Email column
     *
     * Example usage:
     * <code>
     * $query->filterByEmail('fooValue');   // WHERE fam_Email = 'fooValue'
     * $query->filterByEmail('%fooValue%', Criteria::LIKE); // WHERE fam_Email LIKE '%fooValue%'
     * </code>
     *
     * @param     string $email The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function filterByEmail($email = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($email)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FamilyTableMap::COL_FAM_EMAIL, $email, $comparison);
    }

    /**
     * Filter the query on the fam_WeddingDate column
     *
     * Example usage:
     * <code>
     * $query->filterByWeddingdate('2011-03-14'); // WHERE fam_WeddingDate = '2011-03-14'
     * $query->filterByWeddingdate('now'); // WHERE fam_WeddingDate = '2011-03-14'
     * $query->filterByWeddingdate(array('max' => 'yesterday')); // WHERE fam_WeddingDate > '2011-03-13'
     * </code>
     *
     * @param     mixed $weddingdate The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function filterByWeddingdate($weddingdate = null, $comparison = null)
    {
        if (is_array($weddingdate)) {
            $useMinMax = false;
            if (isset($weddingdate['min'])) {
                $this->addUsingAlias(FamilyTableMap::COL_FAM_WEDDINGDATE, $weddingdate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($weddingdate['max'])) {
                $this->addUsingAlias(FamilyTableMap::COL_FAM_WEDDINGDATE, $weddingdate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FamilyTableMap::COL_FAM_WEDDINGDATE, $weddingdate, $comparison);
    }

    /**
     * Filter the query on the fam_DateEntered column
     *
     * Example usage:
     * <code>
     * $query->filterByDateEntered('2011-03-14'); // WHERE fam_DateEntered = '2011-03-14'
     * $query->filterByDateEntered('now'); // WHERE fam_DateEntered = '2011-03-14'
     * $query->filterByDateEntered(array('max' => 'yesterday')); // WHERE fam_DateEntered > '2011-03-13'
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
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function filterByDateEntered($dateEntered = null, $comparison = null)
    {
        if (is_array($dateEntered)) {
            $useMinMax = false;
            if (isset($dateEntered['min'])) {
                $this->addUsingAlias(FamilyTableMap::COL_FAM_DATEENTERED, $dateEntered['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($dateEntered['max'])) {
                $this->addUsingAlias(FamilyTableMap::COL_FAM_DATEENTERED, $dateEntered['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FamilyTableMap::COL_FAM_DATEENTERED, $dateEntered, $comparison);
    }

    /**
     * Filter the query on the fam_DateLastEdited column
     *
     * Example usage:
     * <code>
     * $query->filterByDateLastEdited('2011-03-14'); // WHERE fam_DateLastEdited = '2011-03-14'
     * $query->filterByDateLastEdited('now'); // WHERE fam_DateLastEdited = '2011-03-14'
     * $query->filterByDateLastEdited(array('max' => 'yesterday')); // WHERE fam_DateLastEdited > '2011-03-13'
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
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function filterByDateLastEdited($dateLastEdited = null, $comparison = null)
    {
        if (is_array($dateLastEdited)) {
            $useMinMax = false;
            if (isset($dateLastEdited['min'])) {
                $this->addUsingAlias(FamilyTableMap::COL_FAM_DATELASTEDITED, $dateLastEdited['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($dateLastEdited['max'])) {
                $this->addUsingAlias(FamilyTableMap::COL_FAM_DATELASTEDITED, $dateLastEdited['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FamilyTableMap::COL_FAM_DATELASTEDITED, $dateLastEdited, $comparison);
    }

    /**
     * Filter the query on the fam_EnteredBy column
     *
     * Example usage:
     * <code>
     * $query->filterByEnteredBy(1234); // WHERE fam_EnteredBy = 1234
     * $query->filterByEnteredBy(array(12, 34)); // WHERE fam_EnteredBy IN (12, 34)
     * $query->filterByEnteredBy(array('min' => 12)); // WHERE fam_EnteredBy > 12
     * </code>
     *
     * @param     mixed $enteredBy The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function filterByEnteredBy($enteredBy = null, $comparison = null)
    {
        if (is_array($enteredBy)) {
            $useMinMax = false;
            if (isset($enteredBy['min'])) {
                $this->addUsingAlias(FamilyTableMap::COL_FAM_ENTEREDBY, $enteredBy['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($enteredBy['max'])) {
                $this->addUsingAlias(FamilyTableMap::COL_FAM_ENTEREDBY, $enteredBy['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FamilyTableMap::COL_FAM_ENTEREDBY, $enteredBy, $comparison);
    }

    /**
     * Filter the query on the fam_EditedBy column
     *
     * Example usage:
     * <code>
     * $query->filterByEditedBy(1234); // WHERE fam_EditedBy = 1234
     * $query->filterByEditedBy(array(12, 34)); // WHERE fam_EditedBy IN (12, 34)
     * $query->filterByEditedBy(array('min' => 12)); // WHERE fam_EditedBy > 12
     * </code>
     *
     * @param     mixed $editedBy The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function filterByEditedBy($editedBy = null, $comparison = null)
    {
        if (is_array($editedBy)) {
            $useMinMax = false;
            if (isset($editedBy['min'])) {
                $this->addUsingAlias(FamilyTableMap::COL_FAM_EDITEDBY, $editedBy['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($editedBy['max'])) {
                $this->addUsingAlias(FamilyTableMap::COL_FAM_EDITEDBY, $editedBy['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FamilyTableMap::COL_FAM_EDITEDBY, $editedBy, $comparison);
    }

    /**
     * Filter the query on the fam_scanCheck column
     *
     * Example usage:
     * <code>
     * $query->filterByScanCheck('fooValue');   // WHERE fam_scanCheck = 'fooValue'
     * $query->filterByScanCheck('%fooValue%', Criteria::LIKE); // WHERE fam_scanCheck LIKE '%fooValue%'
     * </code>
     *
     * @param     string $scanCheck The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function filterByScanCheck($scanCheck = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($scanCheck)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FamilyTableMap::COL_FAM_SCANCHECK, $scanCheck, $comparison);
    }

    /**
     * Filter the query on the fam_scanCredit column
     *
     * Example usage:
     * <code>
     * $query->filterByScanCredit('fooValue');   // WHERE fam_scanCredit = 'fooValue'
     * $query->filterByScanCredit('%fooValue%', Criteria::LIKE); // WHERE fam_scanCredit LIKE '%fooValue%'
     * </code>
     *
     * @param     string $scanCredit The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function filterByScanCredit($scanCredit = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($scanCredit)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FamilyTableMap::COL_FAM_SCANCREDIT, $scanCredit, $comparison);
    }

    /**
     * Filter the query on the fam_SendNewsLetter column
     *
     * Example usage:
     * <code>
     * $query->filterBySendNewsletter('fooValue');   // WHERE fam_SendNewsLetter = 'fooValue'
     * $query->filterBySendNewsletter('%fooValue%', Criteria::LIKE); // WHERE fam_SendNewsLetter LIKE '%fooValue%'
     * </code>
     *
     * @param     string $sendNewsletter The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function filterBySendNewsletter($sendNewsletter = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($sendNewsletter)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FamilyTableMap::COL_FAM_SENDNEWSLETTER, $sendNewsletter, $comparison);
    }

    /**
     * Filter the query on the fam_DateDeactivated column
     *
     * Example usage:
     * <code>
     * $query->filterByDateDeactivated('2011-03-14'); // WHERE fam_DateDeactivated = '2011-03-14'
     * $query->filterByDateDeactivated('now'); // WHERE fam_DateDeactivated = '2011-03-14'
     * $query->filterByDateDeactivated(array('max' => 'yesterday')); // WHERE fam_DateDeactivated > '2011-03-13'
     * </code>
     *
     * @param     mixed $dateDeactivated The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function filterByDateDeactivated($dateDeactivated = null, $comparison = null)
    {
        if (is_array($dateDeactivated)) {
            $useMinMax = false;
            if (isset($dateDeactivated['min'])) {
                $this->addUsingAlias(FamilyTableMap::COL_FAM_DATEDEACTIVATED, $dateDeactivated['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($dateDeactivated['max'])) {
                $this->addUsingAlias(FamilyTableMap::COL_FAM_DATEDEACTIVATED, $dateDeactivated['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FamilyTableMap::COL_FAM_DATEDEACTIVATED, $dateDeactivated, $comparison);
    }

    /**
     * Filter the query on the fam_OkToCanvass column
     *
     * Example usage:
     * <code>
     * $query->filterByOkToCanvass('fooValue');   // WHERE fam_OkToCanvass = 'fooValue'
     * $query->filterByOkToCanvass('%fooValue%', Criteria::LIKE); // WHERE fam_OkToCanvass LIKE '%fooValue%'
     * </code>
     *
     * @param     string $okToCanvass The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function filterByOkToCanvass($okToCanvass = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($okToCanvass)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FamilyTableMap::COL_FAM_OKTOCANVASS, $okToCanvass, $comparison);
    }

    /**
     * Filter the query on the fam_Canvasser column
     *
     * Example usage:
     * <code>
     * $query->filterByCanvasser(1234); // WHERE fam_Canvasser = 1234
     * $query->filterByCanvasser(array(12, 34)); // WHERE fam_Canvasser IN (12, 34)
     * $query->filterByCanvasser(array('min' => 12)); // WHERE fam_Canvasser > 12
     * </code>
     *
     * @param     mixed $canvasser The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function filterByCanvasser($canvasser = null, $comparison = null)
    {
        if (is_array($canvasser)) {
            $useMinMax = false;
            if (isset($canvasser['min'])) {
                $this->addUsingAlias(FamilyTableMap::COL_FAM_CANVASSER, $canvasser['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($canvasser['max'])) {
                $this->addUsingAlias(FamilyTableMap::COL_FAM_CANVASSER, $canvasser['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FamilyTableMap::COL_FAM_CANVASSER, $canvasser, $comparison);
    }

    /**
     * Filter the query on the fam_Latitude column
     *
     * Example usage:
     * <code>
     * $query->filterByLatitude(1234); // WHERE fam_Latitude = 1234
     * $query->filterByLatitude(array(12, 34)); // WHERE fam_Latitude IN (12, 34)
     * $query->filterByLatitude(array('min' => 12)); // WHERE fam_Latitude > 12
     * </code>
     *
     * @param     mixed $latitude The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function filterByLatitude($latitude = null, $comparison = null)
    {
        if (is_array($latitude)) {
            $useMinMax = false;
            if (isset($latitude['min'])) {
                $this->addUsingAlias(FamilyTableMap::COL_FAM_LATITUDE, $latitude['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($latitude['max'])) {
                $this->addUsingAlias(FamilyTableMap::COL_FAM_LATITUDE, $latitude['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FamilyTableMap::COL_FAM_LATITUDE, $latitude, $comparison);
    }

    /**
     * Filter the query on the fam_Longitude column
     *
     * Example usage:
     * <code>
     * $query->filterByLongitude(1234); // WHERE fam_Longitude = 1234
     * $query->filterByLongitude(array(12, 34)); // WHERE fam_Longitude IN (12, 34)
     * $query->filterByLongitude(array('min' => 12)); // WHERE fam_Longitude > 12
     * </code>
     *
     * @param     mixed $longitude The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function filterByLongitude($longitude = null, $comparison = null)
    {
        if (is_array($longitude)) {
            $useMinMax = false;
            if (isset($longitude['min'])) {
                $this->addUsingAlias(FamilyTableMap::COL_FAM_LONGITUDE, $longitude['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($longitude['max'])) {
                $this->addUsingAlias(FamilyTableMap::COL_FAM_LONGITUDE, $longitude['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FamilyTableMap::COL_FAM_LONGITUDE, $longitude, $comparison);
    }

    /**
     * Filter the query on the fam_Envelope column
     *
     * Example usage:
     * <code>
     * $query->filterByEnvelope(1234); // WHERE fam_Envelope = 1234
     * $query->filterByEnvelope(array(12, 34)); // WHERE fam_Envelope IN (12, 34)
     * $query->filterByEnvelope(array('min' => 12)); // WHERE fam_Envelope > 12
     * </code>
     *
     * @param     mixed $envelope The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function filterByEnvelope($envelope = null, $comparison = null)
    {
        if (is_array($envelope)) {
            $useMinMax = false;
            if (isset($envelope['min'])) {
                $this->addUsingAlias(FamilyTableMap::COL_FAM_ENVELOPE, $envelope['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($envelope['max'])) {
                $this->addUsingAlias(FamilyTableMap::COL_FAM_ENVELOPE, $envelope['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FamilyTableMap::COL_FAM_ENVELOPE, $envelope, $comparison);
    }

    /**
     * Filter the query by a related \ChurchCRM\Person object
     *
     * @param \ChurchCRM\Person|ObjectCollection $person the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildFamilyQuery The current query, for fluid interface
     */
    public function filterByPerson($person, $comparison = null)
    {
        if ($person instanceof \ChurchCRM\Person) {
            return $this
                ->addUsingAlias(FamilyTableMap::COL_FAM_ID, $person->getFamId(), $comparison);
        } elseif ($person instanceof ObjectCollection) {
            return $this
                ->usePersonQuery()
                ->filterByPrimaryKeys($person->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByPerson() only accepts arguments of type \ChurchCRM\Person or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Person relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function joinPerson($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Person');

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
            $this->addJoinObject($join, 'Person');
        }

        return $this;
    }

    /**
     * Use the Person relation Person object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return \ChurchCRM\PersonQuery A secondary query class using the current class as primary query
     */
    public function usePersonQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinPerson($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Person', '\ChurchCRM\PersonQuery');
    }

    /**
     * Filter the query by a related \ChurchCRM\Note object
     *
     * @param \ChurchCRM\Note|ObjectCollection $note the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildFamilyQuery The current query, for fluid interface
     */
    public function filterByNote($note, $comparison = null)
    {
        if ($note instanceof \ChurchCRM\Note) {
            return $this
                ->addUsingAlias(FamilyTableMap::COL_FAM_ID, $note->getFamId(), $comparison);
        } elseif ($note instanceof ObjectCollection) {
            return $this
                ->useNoteQuery()
                ->filterByPrimaryKeys($note->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByNote() only accepts arguments of type \ChurchCRM\Note or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Note relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function joinNote($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Note');

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
            $this->addJoinObject($join, 'Note');
        }

        return $this;
    }

    /**
     * Use the Note relation Note object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return \ChurchCRM\NoteQuery A secondary query class using the current class as primary query
     */
    public function useNoteQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinNote($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Note', '\ChurchCRM\NoteQuery');
    }

    /**
     * Filter the query by a related \ChurchCRM\Pledge object
     *
     * @param \ChurchCRM\Pledge|ObjectCollection $pledge the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildFamilyQuery The current query, for fluid interface
     */
    public function filterByPledge($pledge, $comparison = null)
    {
        if ($pledge instanceof \ChurchCRM\Pledge) {
            return $this
                ->addUsingAlias(FamilyTableMap::COL_FAM_ID, $pledge->getFamId(), $comparison);
        } elseif ($pledge instanceof ObjectCollection) {
            return $this
                ->usePledgeQuery()
                ->filterByPrimaryKeys($pledge->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByPledge() only accepts arguments of type \ChurchCRM\Pledge or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Pledge relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function joinPledge($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Pledge');

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
            $this->addJoinObject($join, 'Pledge');
        }

        return $this;
    }

    /**
     * Use the Pledge relation Pledge object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return \ChurchCRM\PledgeQuery A secondary query class using the current class as primary query
     */
    public function usePledgeQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinPledge($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Pledge', '\ChurchCRM\PledgeQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildFamily $family Object to remove from the list of results
     *
     * @return $this|ChildFamilyQuery The current query, for fluid interface
     */
    public function prune($family = null)
    {
        if ($family) {
            $this->addUsingAlias(FamilyTableMap::COL_FAM_ID, $family->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the family_fam table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(FamilyTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            FamilyTableMap::clearInstancePool();
            FamilyTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(FamilyTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(FamilyTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            FamilyTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            FamilyTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // FamilyQuery
