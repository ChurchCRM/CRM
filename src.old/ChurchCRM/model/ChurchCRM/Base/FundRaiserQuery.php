<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\FundRaiser as ChildFundRaiser;
use ChurchCRM\FundRaiserQuery as ChildFundRaiserQuery;
use ChurchCRM\Map\FundRaiserTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'fundraiser_fr' table.
 *
 *
 *
 * @method     ChildFundRaiserQuery orderById($order = Criteria::ASC) Order by the fr_ID column
 * @method     ChildFundRaiserQuery orderByDate($order = Criteria::ASC) Order by the fr_date column
 * @method     ChildFundRaiserQuery orderByTitle($order = Criteria::ASC) Order by the fr_title column
 * @method     ChildFundRaiserQuery orderByDescription($order = Criteria::ASC) Order by the fr_description column
 * @method     ChildFundRaiserQuery orderByEnteredBy($order = Criteria::ASC) Order by the fr_EnteredBy column
 * @method     ChildFundRaiserQuery orderByEnteredDate($order = Criteria::ASC) Order by the fr_EnteredDate column
 *
 * @method     ChildFundRaiserQuery groupById() Group by the fr_ID column
 * @method     ChildFundRaiserQuery groupByDate() Group by the fr_date column
 * @method     ChildFundRaiserQuery groupByTitle() Group by the fr_title column
 * @method     ChildFundRaiserQuery groupByDescription() Group by the fr_description column
 * @method     ChildFundRaiserQuery groupByEnteredBy() Group by the fr_EnteredBy column
 * @method     ChildFundRaiserQuery groupByEnteredDate() Group by the fr_EnteredDate column
 *
 * @method     ChildFundRaiserQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildFundRaiserQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildFundRaiserQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildFundRaiserQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildFundRaiserQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildFundRaiserQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildFundRaiser findOne(ConnectionInterface $con = null) Return the first ChildFundRaiser matching the query
 * @method     ChildFundRaiser findOneOrCreate(ConnectionInterface $con = null) Return the first ChildFundRaiser matching the query, or a new ChildFundRaiser object populated from the query conditions when no match is found
 *
 * @method     ChildFundRaiser findOneById(int $fr_ID) Return the first ChildFundRaiser filtered by the fr_ID column
 * @method     ChildFundRaiser findOneByDate(string $fr_date) Return the first ChildFundRaiser filtered by the fr_date column
 * @method     ChildFundRaiser findOneByTitle(string $fr_title) Return the first ChildFundRaiser filtered by the fr_title column
 * @method     ChildFundRaiser findOneByDescription(string $fr_description) Return the first ChildFundRaiser filtered by the fr_description column
 * @method     ChildFundRaiser findOneByEnteredBy(int $fr_EnteredBy) Return the first ChildFundRaiser filtered by the fr_EnteredBy column
 * @method     ChildFundRaiser findOneByEnteredDate(string $fr_EnteredDate) Return the first ChildFundRaiser filtered by the fr_EnteredDate column *

 * @method     ChildFundRaiser requirePk($key, ConnectionInterface $con = null) Return the ChildFundRaiser by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFundRaiser requireOne(ConnectionInterface $con = null) Return the first ChildFundRaiser matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildFundRaiser requireOneById(int $fr_ID) Return the first ChildFundRaiser filtered by the fr_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFundRaiser requireOneByDate(string $fr_date) Return the first ChildFundRaiser filtered by the fr_date column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFundRaiser requireOneByTitle(string $fr_title) Return the first ChildFundRaiser filtered by the fr_title column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFundRaiser requireOneByDescription(string $fr_description) Return the first ChildFundRaiser filtered by the fr_description column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFundRaiser requireOneByEnteredBy(int $fr_EnteredBy) Return the first ChildFundRaiser filtered by the fr_EnteredBy column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildFundRaiser requireOneByEnteredDate(string $fr_EnteredDate) Return the first ChildFundRaiser filtered by the fr_EnteredDate column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildFundRaiser[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildFundRaiser objects based on current ModelCriteria
 * @method     ChildFundRaiser[]|ObjectCollection findById(int $fr_ID) Return ChildFundRaiser objects filtered by the fr_ID column
 * @method     ChildFundRaiser[]|ObjectCollection findByDate(string $fr_date) Return ChildFundRaiser objects filtered by the fr_date column
 * @method     ChildFundRaiser[]|ObjectCollection findByTitle(string $fr_title) Return ChildFundRaiser objects filtered by the fr_title column
 * @method     ChildFundRaiser[]|ObjectCollection findByDescription(string $fr_description) Return ChildFundRaiser objects filtered by the fr_description column
 * @method     ChildFundRaiser[]|ObjectCollection findByEnteredBy(int $fr_EnteredBy) Return ChildFundRaiser objects filtered by the fr_EnteredBy column
 * @method     ChildFundRaiser[]|ObjectCollection findByEnteredDate(string $fr_EnteredDate) Return ChildFundRaiser objects filtered by the fr_EnteredDate column
 * @method     ChildFundRaiser[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class FundRaiserQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\FundRaiserQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\FundRaiser', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildFundRaiserQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildFundRaiserQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildFundRaiserQuery) {
            return $criteria;
        }
        $query = new ChildFundRaiserQuery();
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
     * @return ChildFundRaiser|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(FundRaiserTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (
            $this->formatter || $this->modelAlias || $this->with || $this->select
            || $this->selectColumns || $this->asColumns || $this->selectModifiers
            || $this->map || $this->having || $this->joins
        ) {
            return $this->findPkComplex($key, $con);
        }

        if ((null !== ($obj = FundRaiserTableMap::getInstanceFromPool(null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key)))) {
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
     * @return ChildFundRaiser A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT fr_ID, fr_date, fr_title, fr_description, fr_EnteredBy, fr_EnteredDate FROM fundraiser_fr WHERE fr_ID = :p0';
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
            /** @var ChildFundRaiser $obj */
            $obj = new ChildFundRaiser();
            $obj->hydrate($row);
            FundRaiserTableMap::addInstanceToPool($obj, null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key);
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
     * @return ChildFundRaiser|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildFundRaiserQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(FundRaiserTableMap::COL_FR_ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildFundRaiserQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(FundRaiserTableMap::COL_FR_ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the fr_ID column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE fr_ID = 1234
     * $query->filterById(array(12, 34)); // WHERE fr_ID IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE fr_ID > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFundRaiserQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(FundRaiserTableMap::COL_FR_ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(FundRaiserTableMap::COL_FR_ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FundRaiserTableMap::COL_FR_ID, $id, $comparison);
    }

    /**
     * Filter the query on the fr_date column
     *
     * Example usage:
     * <code>
     * $query->filterByDate('2011-03-14'); // WHERE fr_date = '2011-03-14'
     * $query->filterByDate('now'); // WHERE fr_date = '2011-03-14'
     * $query->filterByDate(array('max' => 'yesterday')); // WHERE fr_date > '2011-03-13'
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
     * @return $this|ChildFundRaiserQuery The current query, for fluid interface
     */
    public function filterByDate($date = null, $comparison = null)
    {
        if (is_array($date)) {
            $useMinMax = false;
            if (isset($date['min'])) {
                $this->addUsingAlias(FundRaiserTableMap::COL_FR_DATE, $date['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($date['max'])) {
                $this->addUsingAlias(FundRaiserTableMap::COL_FR_DATE, $date['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FundRaiserTableMap::COL_FR_DATE, $date, $comparison);
    }

    /**
     * Filter the query on the fr_title column
     *
     * Example usage:
     * <code>
     * $query->filterByTitle('fooValue');   // WHERE fr_title = 'fooValue'
     * $query->filterByTitle('%fooValue%', Criteria::LIKE); // WHERE fr_title LIKE '%fooValue%'
     * </code>
     *
     * @param     string $title The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFundRaiserQuery The current query, for fluid interface
     */
    public function filterByTitle($title = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($title)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FundRaiserTableMap::COL_FR_TITLE, $title, $comparison);
    }

    /**
     * Filter the query on the fr_description column
     *
     * Example usage:
     * <code>
     * $query->filterByDescription('fooValue');   // WHERE fr_description = 'fooValue'
     * $query->filterByDescription('%fooValue%', Criteria::LIKE); // WHERE fr_description LIKE '%fooValue%'
     * </code>
     *
     * @param     string $description The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFundRaiserQuery The current query, for fluid interface
     */
    public function filterByDescription($description = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($description)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FundRaiserTableMap::COL_FR_DESCRIPTION, $description, $comparison);
    }

    /**
     * Filter the query on the fr_EnteredBy column
     *
     * Example usage:
     * <code>
     * $query->filterByEnteredBy(1234); // WHERE fr_EnteredBy = 1234
     * $query->filterByEnteredBy(array(12, 34)); // WHERE fr_EnteredBy IN (12, 34)
     * $query->filterByEnteredBy(array('min' => 12)); // WHERE fr_EnteredBy > 12
     * </code>
     *
     * @param     mixed $enteredBy The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFundRaiserQuery The current query, for fluid interface
     */
    public function filterByEnteredBy($enteredBy = null, $comparison = null)
    {
        if (is_array($enteredBy)) {
            $useMinMax = false;
            if (isset($enteredBy['min'])) {
                $this->addUsingAlias(FundRaiserTableMap::COL_FR_ENTEREDBY, $enteredBy['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($enteredBy['max'])) {
                $this->addUsingAlias(FundRaiserTableMap::COL_FR_ENTEREDBY, $enteredBy['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FundRaiserTableMap::COL_FR_ENTEREDBY, $enteredBy, $comparison);
    }

    /**
     * Filter the query on the fr_EnteredDate column
     *
     * Example usage:
     * <code>
     * $query->filterByEnteredDate('2011-03-14'); // WHERE fr_EnteredDate = '2011-03-14'
     * $query->filterByEnteredDate('now'); // WHERE fr_EnteredDate = '2011-03-14'
     * $query->filterByEnteredDate(array('max' => 'yesterday')); // WHERE fr_EnteredDate > '2011-03-13'
     * </code>
     *
     * @param     mixed $enteredDate The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildFundRaiserQuery The current query, for fluid interface
     */
    public function filterByEnteredDate($enteredDate = null, $comparison = null)
    {
        if (is_array($enteredDate)) {
            $useMinMax = false;
            if (isset($enteredDate['min'])) {
                $this->addUsingAlias(FundRaiserTableMap::COL_FR_ENTEREDDATE, $enteredDate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($enteredDate['max'])) {
                $this->addUsingAlias(FundRaiserTableMap::COL_FR_ENTEREDDATE, $enteredDate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FundRaiserTableMap::COL_FR_ENTEREDDATE, $enteredDate, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildFundRaiser $fundRaiser Object to remove from the list of results
     *
     * @return $this|ChildFundRaiserQuery The current query, for fluid interface
     */
    public function prune($fundRaiser = null)
    {
        if ($fundRaiser) {
            $this->addUsingAlias(FundRaiserTableMap::COL_FR_ID, $fundRaiser->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the fundraiser_fr table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(FundRaiserTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            FundRaiserTableMap::clearInstancePool();
            FundRaiserTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(FundRaiserTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(FundRaiserTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            FundRaiserTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            FundRaiserTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // FundRaiserQuery
