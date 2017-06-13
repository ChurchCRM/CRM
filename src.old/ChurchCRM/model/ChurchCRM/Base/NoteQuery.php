<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\Note as ChildNote;
use ChurchCRM\NoteQuery as ChildNoteQuery;
use ChurchCRM\Map\NoteTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\ModelJoin;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'note_nte' table.
 *
 *
 *
 * @method     ChildNoteQuery orderById($order = Criteria::ASC) Order by the nte_ID column
 * @method     ChildNoteQuery orderByPerId($order = Criteria::ASC) Order by the nte_per_ID column
 * @method     ChildNoteQuery orderByFamId($order = Criteria::ASC) Order by the nte_fam_ID column
 * @method     ChildNoteQuery orderByPrivate($order = Criteria::ASC) Order by the nte_Private column
 * @method     ChildNoteQuery orderByText($order = Criteria::ASC) Order by the nte_Text column
 * @method     ChildNoteQuery orderByDateEntered($order = Criteria::ASC) Order by the nte_DateEntered column
 * @method     ChildNoteQuery orderByDateLastEdited($order = Criteria::ASC) Order by the nte_DateLastEdited column
 * @method     ChildNoteQuery orderByEnteredBy($order = Criteria::ASC) Order by the nte_EnteredBy column
 * @method     ChildNoteQuery orderByEditedBy($order = Criteria::ASC) Order by the nte_EditedBy column
 * @method     ChildNoteQuery orderByType($order = Criteria::ASC) Order by the nte_Type column
 *
 * @method     ChildNoteQuery groupById() Group by the nte_ID column
 * @method     ChildNoteQuery groupByPerId() Group by the nte_per_ID column
 * @method     ChildNoteQuery groupByFamId() Group by the nte_fam_ID column
 * @method     ChildNoteQuery groupByPrivate() Group by the nte_Private column
 * @method     ChildNoteQuery groupByText() Group by the nte_Text column
 * @method     ChildNoteQuery groupByDateEntered() Group by the nte_DateEntered column
 * @method     ChildNoteQuery groupByDateLastEdited() Group by the nte_DateLastEdited column
 * @method     ChildNoteQuery groupByEnteredBy() Group by the nte_EnteredBy column
 * @method     ChildNoteQuery groupByEditedBy() Group by the nte_EditedBy column
 * @method     ChildNoteQuery groupByType() Group by the nte_Type column
 *
 * @method     ChildNoteQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildNoteQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildNoteQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildNoteQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildNoteQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildNoteQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildNoteQuery leftJoinPerson($relationAlias = null) Adds a LEFT JOIN clause to the query using the Person relation
 * @method     ChildNoteQuery rightJoinPerson($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Person relation
 * @method     ChildNoteQuery innerJoinPerson($relationAlias = null) Adds a INNER JOIN clause to the query using the Person relation
 *
 * @method     ChildNoteQuery joinWithPerson($joinType = Criteria::INNER_JOIN) Adds a join clause and with to the query using the Person relation
 *
 * @method     ChildNoteQuery leftJoinWithPerson() Adds a LEFT JOIN clause and with to the query using the Person relation
 * @method     ChildNoteQuery rightJoinWithPerson() Adds a RIGHT JOIN clause and with to the query using the Person relation
 * @method     ChildNoteQuery innerJoinWithPerson() Adds a INNER JOIN clause and with to the query using the Person relation
 *
 * @method     ChildNoteQuery leftJoinFamily($relationAlias = null) Adds a LEFT JOIN clause to the query using the Family relation
 * @method     ChildNoteQuery rightJoinFamily($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Family relation
 * @method     ChildNoteQuery innerJoinFamily($relationAlias = null) Adds a INNER JOIN clause to the query using the Family relation
 *
 * @method     ChildNoteQuery joinWithFamily($joinType = Criteria::INNER_JOIN) Adds a join clause and with to the query using the Family relation
 *
 * @method     ChildNoteQuery leftJoinWithFamily() Adds a LEFT JOIN clause and with to the query using the Family relation
 * @method     ChildNoteQuery rightJoinWithFamily() Adds a RIGHT JOIN clause and with to the query using the Family relation
 * @method     ChildNoteQuery innerJoinWithFamily() Adds a INNER JOIN clause and with to the query using the Family relation
 *
 * @method     \ChurchCRM\PersonQuery|\ChurchCRM\FamilyQuery endUse() Finalizes a secondary criteria and merges it with its primary Criteria
 *
 * @method     ChildNote findOne(ConnectionInterface $con = null) Return the first ChildNote matching the query
 * @method     ChildNote findOneOrCreate(ConnectionInterface $con = null) Return the first ChildNote matching the query, or a new ChildNote object populated from the query conditions when no match is found
 *
 * @method     ChildNote findOneById(int $nte_ID) Return the first ChildNote filtered by the nte_ID column
 * @method     ChildNote findOneByPerId(int $nte_per_ID) Return the first ChildNote filtered by the nte_per_ID column
 * @method     ChildNote findOneByFamId(int $nte_fam_ID) Return the first ChildNote filtered by the nte_fam_ID column
 * @method     ChildNote findOneByPrivate(int $nte_Private) Return the first ChildNote filtered by the nte_Private column
 * @method     ChildNote findOneByText(string $nte_Text) Return the first ChildNote filtered by the nte_Text column
 * @method     ChildNote findOneByDateEntered(string $nte_DateEntered) Return the first ChildNote filtered by the nte_DateEntered column
 * @method     ChildNote findOneByDateLastEdited(string $nte_DateLastEdited) Return the first ChildNote filtered by the nte_DateLastEdited column
 * @method     ChildNote findOneByEnteredBy(int $nte_EnteredBy) Return the first ChildNote filtered by the nte_EnteredBy column
 * @method     ChildNote findOneByEditedBy(int $nte_EditedBy) Return the first ChildNote filtered by the nte_EditedBy column
 * @method     ChildNote findOneByType(string $nte_Type) Return the first ChildNote filtered by the nte_Type column *

 * @method     ChildNote requirePk($key, ConnectionInterface $con = null) Return the ChildNote by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildNote requireOne(ConnectionInterface $con = null) Return the first ChildNote matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildNote requireOneById(int $nte_ID) Return the first ChildNote filtered by the nte_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildNote requireOneByPerId(int $nte_per_ID) Return the first ChildNote filtered by the nte_per_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildNote requireOneByFamId(int $nte_fam_ID) Return the first ChildNote filtered by the nte_fam_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildNote requireOneByPrivate(int $nte_Private) Return the first ChildNote filtered by the nte_Private column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildNote requireOneByText(string $nte_Text) Return the first ChildNote filtered by the nte_Text column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildNote requireOneByDateEntered(string $nte_DateEntered) Return the first ChildNote filtered by the nte_DateEntered column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildNote requireOneByDateLastEdited(string $nte_DateLastEdited) Return the first ChildNote filtered by the nte_DateLastEdited column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildNote requireOneByEnteredBy(int $nte_EnteredBy) Return the first ChildNote filtered by the nte_EnteredBy column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildNote requireOneByEditedBy(int $nte_EditedBy) Return the first ChildNote filtered by the nte_EditedBy column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildNote requireOneByType(string $nte_Type) Return the first ChildNote filtered by the nte_Type column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildNote[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildNote objects based on current ModelCriteria
 * @method     ChildNote[]|ObjectCollection findById(int $nte_ID) Return ChildNote objects filtered by the nte_ID column
 * @method     ChildNote[]|ObjectCollection findByPerId(int $nte_per_ID) Return ChildNote objects filtered by the nte_per_ID column
 * @method     ChildNote[]|ObjectCollection findByFamId(int $nte_fam_ID) Return ChildNote objects filtered by the nte_fam_ID column
 * @method     ChildNote[]|ObjectCollection findByPrivate(int $nte_Private) Return ChildNote objects filtered by the nte_Private column
 * @method     ChildNote[]|ObjectCollection findByText(string $nte_Text) Return ChildNote objects filtered by the nte_Text column
 * @method     ChildNote[]|ObjectCollection findByDateEntered(string $nte_DateEntered) Return ChildNote objects filtered by the nte_DateEntered column
 * @method     ChildNote[]|ObjectCollection findByDateLastEdited(string $nte_DateLastEdited) Return ChildNote objects filtered by the nte_DateLastEdited column
 * @method     ChildNote[]|ObjectCollection findByEnteredBy(int $nte_EnteredBy) Return ChildNote objects filtered by the nte_EnteredBy column
 * @method     ChildNote[]|ObjectCollection findByEditedBy(int $nte_EditedBy) Return ChildNote objects filtered by the nte_EditedBy column
 * @method     ChildNote[]|ObjectCollection findByType(string $nte_Type) Return ChildNote objects filtered by the nte_Type column
 * @method     ChildNote[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class NoteQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\NoteQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\Note', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildNoteQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildNoteQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildNoteQuery) {
            return $criteria;
        }
        $query = new ChildNoteQuery();
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
     * @return ChildNote|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(NoteTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (
            $this->formatter || $this->modelAlias || $this->with || $this->select
            || $this->selectColumns || $this->asColumns || $this->selectModifiers
            || $this->map || $this->having || $this->joins
        ) {
            return $this->findPkComplex($key, $con);
        }

        if ((null !== ($obj = NoteTableMap::getInstanceFromPool(null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key)))) {
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
     * @return ChildNote A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT nte_ID, nte_per_ID, nte_fam_ID, nte_Private, nte_Text, nte_DateEntered, nte_DateLastEdited, nte_EnteredBy, nte_EditedBy, nte_Type FROM note_nte WHERE nte_ID = :p0';
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
            /** @var ChildNote $obj */
            $obj = new ChildNote();
            $obj->hydrate($row);
            NoteTableMap::addInstanceToPool($obj, null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key);
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
     * @return ChildNote|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildNoteQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(NoteTableMap::COL_NTE_ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildNoteQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(NoteTableMap::COL_NTE_ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the nte_ID column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE nte_ID = 1234
     * $query->filterById(array(12, 34)); // WHERE nte_ID IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE nte_ID > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildNoteQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(NoteTableMap::COL_NTE_ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(NoteTableMap::COL_NTE_ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(NoteTableMap::COL_NTE_ID, $id, $comparison);
    }

    /**
     * Filter the query on the nte_per_ID column
     *
     * Example usage:
     * <code>
     * $query->filterByPerId(1234); // WHERE nte_per_ID = 1234
     * $query->filterByPerId(array(12, 34)); // WHERE nte_per_ID IN (12, 34)
     * $query->filterByPerId(array('min' => 12)); // WHERE nte_per_ID > 12
     * </code>
     *
     * @see       filterByPerson()
     *
     * @param     mixed $perId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildNoteQuery The current query, for fluid interface
     */
    public function filterByPerId($perId = null, $comparison = null)
    {
        if (is_array($perId)) {
            $useMinMax = false;
            if (isset($perId['min'])) {
                $this->addUsingAlias(NoteTableMap::COL_NTE_PER_ID, $perId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($perId['max'])) {
                $this->addUsingAlias(NoteTableMap::COL_NTE_PER_ID, $perId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(NoteTableMap::COL_NTE_PER_ID, $perId, $comparison);
    }

    /**
     * Filter the query on the nte_fam_ID column
     *
     * Example usage:
     * <code>
     * $query->filterByFamId(1234); // WHERE nte_fam_ID = 1234
     * $query->filterByFamId(array(12, 34)); // WHERE nte_fam_ID IN (12, 34)
     * $query->filterByFamId(array('min' => 12)); // WHERE nte_fam_ID > 12
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
     * @return $this|ChildNoteQuery The current query, for fluid interface
     */
    public function filterByFamId($famId = null, $comparison = null)
    {
        if (is_array($famId)) {
            $useMinMax = false;
            if (isset($famId['min'])) {
                $this->addUsingAlias(NoteTableMap::COL_NTE_FAM_ID, $famId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($famId['max'])) {
                $this->addUsingAlias(NoteTableMap::COL_NTE_FAM_ID, $famId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(NoteTableMap::COL_NTE_FAM_ID, $famId, $comparison);
    }

    /**
     * Filter the query on the nte_Private column
     *
     * Example usage:
     * <code>
     * $query->filterByPrivate(1234); // WHERE nte_Private = 1234
     * $query->filterByPrivate(array(12, 34)); // WHERE nte_Private IN (12, 34)
     * $query->filterByPrivate(array('min' => 12)); // WHERE nte_Private > 12
     * </code>
     *
     * @param     mixed $private The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildNoteQuery The current query, for fluid interface
     */
    public function filterByPrivate($private = null, $comparison = null)
    {
        if (is_array($private)) {
            $useMinMax = false;
            if (isset($private['min'])) {
                $this->addUsingAlias(NoteTableMap::COL_NTE_PRIVATE, $private['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($private['max'])) {
                $this->addUsingAlias(NoteTableMap::COL_NTE_PRIVATE, $private['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(NoteTableMap::COL_NTE_PRIVATE, $private, $comparison);
    }

    /**
     * Filter the query on the nte_Text column
     *
     * Example usage:
     * <code>
     * $query->filterByText('fooValue');   // WHERE nte_Text = 'fooValue'
     * $query->filterByText('%fooValue%', Criteria::LIKE); // WHERE nte_Text LIKE '%fooValue%'
     * </code>
     *
     * @param     string $text The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildNoteQuery The current query, for fluid interface
     */
    public function filterByText($text = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($text)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(NoteTableMap::COL_NTE_TEXT, $text, $comparison);
    }

    /**
     * Filter the query on the nte_DateEntered column
     *
     * Example usage:
     * <code>
     * $query->filterByDateEntered('2011-03-14'); // WHERE nte_DateEntered = '2011-03-14'
     * $query->filterByDateEntered('now'); // WHERE nte_DateEntered = '2011-03-14'
     * $query->filterByDateEntered(array('max' => 'yesterday')); // WHERE nte_DateEntered > '2011-03-13'
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
     * @return $this|ChildNoteQuery The current query, for fluid interface
     */
    public function filterByDateEntered($dateEntered = null, $comparison = null)
    {
        if (is_array($dateEntered)) {
            $useMinMax = false;
            if (isset($dateEntered['min'])) {
                $this->addUsingAlias(NoteTableMap::COL_NTE_DATEENTERED, $dateEntered['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($dateEntered['max'])) {
                $this->addUsingAlias(NoteTableMap::COL_NTE_DATEENTERED, $dateEntered['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(NoteTableMap::COL_NTE_DATEENTERED, $dateEntered, $comparison);
    }

    /**
     * Filter the query on the nte_DateLastEdited column
     *
     * Example usage:
     * <code>
     * $query->filterByDateLastEdited('2011-03-14'); // WHERE nte_DateLastEdited = '2011-03-14'
     * $query->filterByDateLastEdited('now'); // WHERE nte_DateLastEdited = '2011-03-14'
     * $query->filterByDateLastEdited(array('max' => 'yesterday')); // WHERE nte_DateLastEdited > '2011-03-13'
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
     * @return $this|ChildNoteQuery The current query, for fluid interface
     */
    public function filterByDateLastEdited($dateLastEdited = null, $comparison = null)
    {
        if (is_array($dateLastEdited)) {
            $useMinMax = false;
            if (isset($dateLastEdited['min'])) {
                $this->addUsingAlias(NoteTableMap::COL_NTE_DATELASTEDITED, $dateLastEdited['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($dateLastEdited['max'])) {
                $this->addUsingAlias(NoteTableMap::COL_NTE_DATELASTEDITED, $dateLastEdited['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(NoteTableMap::COL_NTE_DATELASTEDITED, $dateLastEdited, $comparison);
    }

    /**
     * Filter the query on the nte_EnteredBy column
     *
     * Example usage:
     * <code>
     * $query->filterByEnteredBy(1234); // WHERE nte_EnteredBy = 1234
     * $query->filterByEnteredBy(array(12, 34)); // WHERE nte_EnteredBy IN (12, 34)
     * $query->filterByEnteredBy(array('min' => 12)); // WHERE nte_EnteredBy > 12
     * </code>
     *
     * @param     mixed $enteredBy The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildNoteQuery The current query, for fluid interface
     */
    public function filterByEnteredBy($enteredBy = null, $comparison = null)
    {
        if (is_array($enteredBy)) {
            $useMinMax = false;
            if (isset($enteredBy['min'])) {
                $this->addUsingAlias(NoteTableMap::COL_NTE_ENTEREDBY, $enteredBy['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($enteredBy['max'])) {
                $this->addUsingAlias(NoteTableMap::COL_NTE_ENTEREDBY, $enteredBy['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(NoteTableMap::COL_NTE_ENTEREDBY, $enteredBy, $comparison);
    }

    /**
     * Filter the query on the nte_EditedBy column
     *
     * Example usage:
     * <code>
     * $query->filterByEditedBy(1234); // WHERE nte_EditedBy = 1234
     * $query->filterByEditedBy(array(12, 34)); // WHERE nte_EditedBy IN (12, 34)
     * $query->filterByEditedBy(array('min' => 12)); // WHERE nte_EditedBy > 12
     * </code>
     *
     * @param     mixed $editedBy The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildNoteQuery The current query, for fluid interface
     */
    public function filterByEditedBy($editedBy = null, $comparison = null)
    {
        if (is_array($editedBy)) {
            $useMinMax = false;
            if (isset($editedBy['min'])) {
                $this->addUsingAlias(NoteTableMap::COL_NTE_EDITEDBY, $editedBy['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($editedBy['max'])) {
                $this->addUsingAlias(NoteTableMap::COL_NTE_EDITEDBY, $editedBy['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(NoteTableMap::COL_NTE_EDITEDBY, $editedBy, $comparison);
    }

    /**
     * Filter the query on the nte_Type column
     *
     * Example usage:
     * <code>
     * $query->filterByType('fooValue');   // WHERE nte_Type = 'fooValue'
     * $query->filterByType('%fooValue%', Criteria::LIKE); // WHERE nte_Type LIKE '%fooValue%'
     * </code>
     *
     * @param     string $type The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildNoteQuery The current query, for fluid interface
     */
    public function filterByType($type = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($type)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(NoteTableMap::COL_NTE_TYPE, $type, $comparison);
    }

    /**
     * Filter the query by a related \ChurchCRM\Person object
     *
     * @param \ChurchCRM\Person|ObjectCollection $person The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return ChildNoteQuery The current query, for fluid interface
     */
    public function filterByPerson($person, $comparison = null)
    {
        if ($person instanceof \ChurchCRM\Person) {
            return $this
                ->addUsingAlias(NoteTableMap::COL_NTE_PER_ID, $person->getId(), $comparison);
        } elseif ($person instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(NoteTableMap::COL_NTE_PER_ID, $person->toKeyValue('PrimaryKey', 'Id'), $comparison);
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
     * @return $this|ChildNoteQuery The current query, for fluid interface
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
     * Filter the query by a related \ChurchCRM\Family object
     *
     * @param \ChurchCRM\Family|ObjectCollection $family The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return ChildNoteQuery The current query, for fluid interface
     */
    public function filterByFamily($family, $comparison = null)
    {
        if ($family instanceof \ChurchCRM\Family) {
            return $this
                ->addUsingAlias(NoteTableMap::COL_NTE_FAM_ID, $family->getId(), $comparison);
        } elseif ($family instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(NoteTableMap::COL_NTE_FAM_ID, $family->toKeyValue('PrimaryKey', 'Id'), $comparison);
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
     * @return $this|ChildNoteQuery The current query, for fluid interface
     */
    public function joinFamily($relationAlias = null, $joinType = Criteria::INNER_JOIN)
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
    public function useFamilyQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinFamily($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Family', '\ChurchCRM\FamilyQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildNote $note Object to remove from the list of results
     *
     * @return $this|ChildNoteQuery The current query, for fluid interface
     */
    public function prune($note = null)
    {
        if ($note) {
            $this->addUsingAlias(NoteTableMap::COL_NTE_ID, $note->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the note_nte table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(NoteTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            NoteTableMap::clearInstancePool();
            NoteTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(NoteTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(NoteTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            NoteTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            NoteTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // NoteQuery
