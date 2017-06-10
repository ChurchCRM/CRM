<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\DonatedItem as ChildDonatedItem;
use ChurchCRM\DonatedItemQuery as ChildDonatedItemQuery;
use ChurchCRM\Map\DonatedItemTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'donateditem_di' table.
 *
 *
 *
 * @method     ChildDonatedItemQuery orderById($order = Criteria::ASC) Order by the di_ID column
 * @method     ChildDonatedItemQuery orderByItem($order = Criteria::ASC) Order by the di_item column
 * @method     ChildDonatedItemQuery orderByFrId($order = Criteria::ASC) Order by the di_FR_ID column
 * @method     ChildDonatedItemQuery orderByDonorId($order = Criteria::ASC) Order by the di_donor_ID column
 * @method     ChildDonatedItemQuery orderByBuyerId($order = Criteria::ASC) Order by the di_buyer_ID column
 * @method     ChildDonatedItemQuery orderByMultibuy($order = Criteria::ASC) Order by the di_multibuy column
 * @method     ChildDonatedItemQuery orderByTitle($order = Criteria::ASC) Order by the di_title column
 * @method     ChildDonatedItemQuery orderByDescription($order = Criteria::ASC) Order by the di_description column
 * @method     ChildDonatedItemQuery orderBySellprice($order = Criteria::ASC) Order by the di_sellprice column
 * @method     ChildDonatedItemQuery orderByEstprice($order = Criteria::ASC) Order by the di_estprice column
 * @method     ChildDonatedItemQuery orderByMinimum($order = Criteria::ASC) Order by the di_minimum column
 * @method     ChildDonatedItemQuery orderByMaterialValue($order = Criteria::ASC) Order by the di_materialvalue column
 * @method     ChildDonatedItemQuery orderByEnteredby($order = Criteria::ASC) Order by the di_EnteredBy column
 * @method     ChildDonatedItemQuery orderByEntereddate($order = Criteria::ASC) Order by the di_EnteredDate column
 * @method     ChildDonatedItemQuery orderByPicture($order = Criteria::ASC) Order by the di_picture column
 *
 * @method     ChildDonatedItemQuery groupById() Group by the di_ID column
 * @method     ChildDonatedItemQuery groupByItem() Group by the di_item column
 * @method     ChildDonatedItemQuery groupByFrId() Group by the di_FR_ID column
 * @method     ChildDonatedItemQuery groupByDonorId() Group by the di_donor_ID column
 * @method     ChildDonatedItemQuery groupByBuyerId() Group by the di_buyer_ID column
 * @method     ChildDonatedItemQuery groupByMultibuy() Group by the di_multibuy column
 * @method     ChildDonatedItemQuery groupByTitle() Group by the di_title column
 * @method     ChildDonatedItemQuery groupByDescription() Group by the di_description column
 * @method     ChildDonatedItemQuery groupBySellprice() Group by the di_sellprice column
 * @method     ChildDonatedItemQuery groupByEstprice() Group by the di_estprice column
 * @method     ChildDonatedItemQuery groupByMinimum() Group by the di_minimum column
 * @method     ChildDonatedItemQuery groupByMaterialValue() Group by the di_materialvalue column
 * @method     ChildDonatedItemQuery groupByEnteredby() Group by the di_EnteredBy column
 * @method     ChildDonatedItemQuery groupByEntereddate() Group by the di_EnteredDate column
 * @method     ChildDonatedItemQuery groupByPicture() Group by the di_picture column
 *
 * @method     ChildDonatedItemQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildDonatedItemQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildDonatedItemQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildDonatedItemQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildDonatedItemQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildDonatedItemQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildDonatedItem findOne(ConnectionInterface $con = null) Return the first ChildDonatedItem matching the query
 * @method     ChildDonatedItem findOneOrCreate(ConnectionInterface $con = null) Return the first ChildDonatedItem matching the query, or a new ChildDonatedItem object populated from the query conditions when no match is found
 *
 * @method     ChildDonatedItem findOneById(int $di_ID) Return the first ChildDonatedItem filtered by the di_ID column
 * @method     ChildDonatedItem findOneByItem(string $di_item) Return the first ChildDonatedItem filtered by the di_item column
 * @method     ChildDonatedItem findOneByFrId(int $di_FR_ID) Return the first ChildDonatedItem filtered by the di_FR_ID column
 * @method     ChildDonatedItem findOneByDonorId(int $di_donor_ID) Return the first ChildDonatedItem filtered by the di_donor_ID column
 * @method     ChildDonatedItem findOneByBuyerId(int $di_buyer_ID) Return the first ChildDonatedItem filtered by the di_buyer_ID column
 * @method     ChildDonatedItem findOneByMultibuy(int $di_multibuy) Return the first ChildDonatedItem filtered by the di_multibuy column
 * @method     ChildDonatedItem findOneByTitle(string $di_title) Return the first ChildDonatedItem filtered by the di_title column
 * @method     ChildDonatedItem findOneByDescription(string $di_description) Return the first ChildDonatedItem filtered by the di_description column
 * @method     ChildDonatedItem findOneBySellprice(string $di_sellprice) Return the first ChildDonatedItem filtered by the di_sellprice column
 * @method     ChildDonatedItem findOneByEstprice(string $di_estprice) Return the first ChildDonatedItem filtered by the di_estprice column
 * @method     ChildDonatedItem findOneByMinimum(string $di_minimum) Return the first ChildDonatedItem filtered by the di_minimum column
 * @method     ChildDonatedItem findOneByMaterialValue(string $di_materialvalue) Return the first ChildDonatedItem filtered by the di_materialvalue column
 * @method     ChildDonatedItem findOneByEnteredby(int $di_EnteredBy) Return the first ChildDonatedItem filtered by the di_EnteredBy column
 * @method     ChildDonatedItem findOneByEntereddate(string $di_EnteredDate) Return the first ChildDonatedItem filtered by the di_EnteredDate column
 * @method     ChildDonatedItem findOneByPicture(string $di_picture) Return the first ChildDonatedItem filtered by the di_picture column *

 * @method     ChildDonatedItem requirePk($key, ConnectionInterface $con = null) Return the ChildDonatedItem by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildDonatedItem requireOne(ConnectionInterface $con = null) Return the first ChildDonatedItem matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildDonatedItem requireOneById(int $di_ID) Return the first ChildDonatedItem filtered by the di_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildDonatedItem requireOneByItem(string $di_item) Return the first ChildDonatedItem filtered by the di_item column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildDonatedItem requireOneByFrId(int $di_FR_ID) Return the first ChildDonatedItem filtered by the di_FR_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildDonatedItem requireOneByDonorId(int $di_donor_ID) Return the first ChildDonatedItem filtered by the di_donor_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildDonatedItem requireOneByBuyerId(int $di_buyer_ID) Return the first ChildDonatedItem filtered by the di_buyer_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildDonatedItem requireOneByMultibuy(int $di_multibuy) Return the first ChildDonatedItem filtered by the di_multibuy column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildDonatedItem requireOneByTitle(string $di_title) Return the first ChildDonatedItem filtered by the di_title column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildDonatedItem requireOneByDescription(string $di_description) Return the first ChildDonatedItem filtered by the di_description column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildDonatedItem requireOneBySellprice(string $di_sellprice) Return the first ChildDonatedItem filtered by the di_sellprice column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildDonatedItem requireOneByEstprice(string $di_estprice) Return the first ChildDonatedItem filtered by the di_estprice column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildDonatedItem requireOneByMinimum(string $di_minimum) Return the first ChildDonatedItem filtered by the di_minimum column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildDonatedItem requireOneByMaterialValue(string $di_materialvalue) Return the first ChildDonatedItem filtered by the di_materialvalue column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildDonatedItem requireOneByEnteredby(int $di_EnteredBy) Return the first ChildDonatedItem filtered by the di_EnteredBy column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildDonatedItem requireOneByEntereddate(string $di_EnteredDate) Return the first ChildDonatedItem filtered by the di_EnteredDate column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildDonatedItem requireOneByPicture(string $di_picture) Return the first ChildDonatedItem filtered by the di_picture column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildDonatedItem[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildDonatedItem objects based on current ModelCriteria
 * @method     ChildDonatedItem[]|ObjectCollection findById(int $di_ID) Return ChildDonatedItem objects filtered by the di_ID column
 * @method     ChildDonatedItem[]|ObjectCollection findByItem(string $di_item) Return ChildDonatedItem objects filtered by the di_item column
 * @method     ChildDonatedItem[]|ObjectCollection findByFrId(int $di_FR_ID) Return ChildDonatedItem objects filtered by the di_FR_ID column
 * @method     ChildDonatedItem[]|ObjectCollection findByDonorId(int $di_donor_ID) Return ChildDonatedItem objects filtered by the di_donor_ID column
 * @method     ChildDonatedItem[]|ObjectCollection findByBuyerId(int $di_buyer_ID) Return ChildDonatedItem objects filtered by the di_buyer_ID column
 * @method     ChildDonatedItem[]|ObjectCollection findByMultibuy(int $di_multibuy) Return ChildDonatedItem objects filtered by the di_multibuy column
 * @method     ChildDonatedItem[]|ObjectCollection findByTitle(string $di_title) Return ChildDonatedItem objects filtered by the di_title column
 * @method     ChildDonatedItem[]|ObjectCollection findByDescription(string $di_description) Return ChildDonatedItem objects filtered by the di_description column
 * @method     ChildDonatedItem[]|ObjectCollection findBySellprice(string $di_sellprice) Return ChildDonatedItem objects filtered by the di_sellprice column
 * @method     ChildDonatedItem[]|ObjectCollection findByEstprice(string $di_estprice) Return ChildDonatedItem objects filtered by the di_estprice column
 * @method     ChildDonatedItem[]|ObjectCollection findByMinimum(string $di_minimum) Return ChildDonatedItem objects filtered by the di_minimum column
 * @method     ChildDonatedItem[]|ObjectCollection findByMaterialValue(string $di_materialvalue) Return ChildDonatedItem objects filtered by the di_materialvalue column
 * @method     ChildDonatedItem[]|ObjectCollection findByEnteredby(int $di_EnteredBy) Return ChildDonatedItem objects filtered by the di_EnteredBy column
 * @method     ChildDonatedItem[]|ObjectCollection findByEntereddate(string $di_EnteredDate) Return ChildDonatedItem objects filtered by the di_EnteredDate column
 * @method     ChildDonatedItem[]|ObjectCollection findByPicture(string $di_picture) Return ChildDonatedItem objects filtered by the di_picture column
 * @method     ChildDonatedItem[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class DonatedItemQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\DonatedItemQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\DonatedItem', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildDonatedItemQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildDonatedItemQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildDonatedItemQuery) {
            return $criteria;
        }
        $query = new ChildDonatedItemQuery();
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
     * @return ChildDonatedItem|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(DonatedItemTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (
            $this->formatter || $this->modelAlias || $this->with || $this->select
            || $this->selectColumns || $this->asColumns || $this->selectModifiers
            || $this->map || $this->having || $this->joins
        ) {
            return $this->findPkComplex($key, $con);
        }

        if ((null !== ($obj = DonatedItemTableMap::getInstanceFromPool(null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key)))) {
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
     * @return ChildDonatedItem A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT di_ID, di_item, di_FR_ID, di_donor_ID, di_buyer_ID, di_multibuy, di_title, di_description, di_sellprice, di_estprice, di_minimum, di_materialvalue, di_EnteredBy, di_EnteredDate, di_picture FROM donateditem_di WHERE di_ID = :p0';
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
            /** @var ChildDonatedItem $obj */
            $obj = new ChildDonatedItem();
            $obj->hydrate($row);
            DonatedItemTableMap::addInstanceToPool($obj, null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key);
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
     * @return ChildDonatedItem|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildDonatedItemQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(DonatedItemTableMap::COL_DI_ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildDonatedItemQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(DonatedItemTableMap::COL_DI_ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the di_ID column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE di_ID = 1234
     * $query->filterById(array(12, 34)); // WHERE di_ID IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE di_ID > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildDonatedItemQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(DonatedItemTableMap::COL_DI_ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(DonatedItemTableMap::COL_DI_ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(DonatedItemTableMap::COL_DI_ID, $id, $comparison);
    }

    /**
     * Filter the query on the di_item column
     *
     * Example usage:
     * <code>
     * $query->filterByItem('fooValue');   // WHERE di_item = 'fooValue'
     * $query->filterByItem('%fooValue%', Criteria::LIKE); // WHERE di_item LIKE '%fooValue%'
     * </code>
     *
     * @param     string $item The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildDonatedItemQuery The current query, for fluid interface
     */
    public function filterByItem($item = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($item)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(DonatedItemTableMap::COL_DI_ITEM, $item, $comparison);
    }

    /**
     * Filter the query on the di_FR_ID column
     *
     * Example usage:
     * <code>
     * $query->filterByFrId(1234); // WHERE di_FR_ID = 1234
     * $query->filterByFrId(array(12, 34)); // WHERE di_FR_ID IN (12, 34)
     * $query->filterByFrId(array('min' => 12)); // WHERE di_FR_ID > 12
     * </code>
     *
     * @param     mixed $frId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildDonatedItemQuery The current query, for fluid interface
     */
    public function filterByFrId($frId = null, $comparison = null)
    {
        if (is_array($frId)) {
            $useMinMax = false;
            if (isset($frId['min'])) {
                $this->addUsingAlias(DonatedItemTableMap::COL_DI_FR_ID, $frId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($frId['max'])) {
                $this->addUsingAlias(DonatedItemTableMap::COL_DI_FR_ID, $frId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(DonatedItemTableMap::COL_DI_FR_ID, $frId, $comparison);
    }

    /**
     * Filter the query on the di_donor_ID column
     *
     * Example usage:
     * <code>
     * $query->filterByDonorId(1234); // WHERE di_donor_ID = 1234
     * $query->filterByDonorId(array(12, 34)); // WHERE di_donor_ID IN (12, 34)
     * $query->filterByDonorId(array('min' => 12)); // WHERE di_donor_ID > 12
     * </code>
     *
     * @param     mixed $donorId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildDonatedItemQuery The current query, for fluid interface
     */
    public function filterByDonorId($donorId = null, $comparison = null)
    {
        if (is_array($donorId)) {
            $useMinMax = false;
            if (isset($donorId['min'])) {
                $this->addUsingAlias(DonatedItemTableMap::COL_DI_DONOR_ID, $donorId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($donorId['max'])) {
                $this->addUsingAlias(DonatedItemTableMap::COL_DI_DONOR_ID, $donorId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(DonatedItemTableMap::COL_DI_DONOR_ID, $donorId, $comparison);
    }

    /**
     * Filter the query on the di_buyer_ID column
     *
     * Example usage:
     * <code>
     * $query->filterByBuyerId(1234); // WHERE di_buyer_ID = 1234
     * $query->filterByBuyerId(array(12, 34)); // WHERE di_buyer_ID IN (12, 34)
     * $query->filterByBuyerId(array('min' => 12)); // WHERE di_buyer_ID > 12
     * </code>
     *
     * @param     mixed $buyerId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildDonatedItemQuery The current query, for fluid interface
     */
    public function filterByBuyerId($buyerId = null, $comparison = null)
    {
        if (is_array($buyerId)) {
            $useMinMax = false;
            if (isset($buyerId['min'])) {
                $this->addUsingAlias(DonatedItemTableMap::COL_DI_BUYER_ID, $buyerId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($buyerId['max'])) {
                $this->addUsingAlias(DonatedItemTableMap::COL_DI_BUYER_ID, $buyerId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(DonatedItemTableMap::COL_DI_BUYER_ID, $buyerId, $comparison);
    }

    /**
     * Filter the query on the di_multibuy column
     *
     * Example usage:
     * <code>
     * $query->filterByMultibuy(1234); // WHERE di_multibuy = 1234
     * $query->filterByMultibuy(array(12, 34)); // WHERE di_multibuy IN (12, 34)
     * $query->filterByMultibuy(array('min' => 12)); // WHERE di_multibuy > 12
     * </code>
     *
     * @param     mixed $multibuy The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildDonatedItemQuery The current query, for fluid interface
     */
    public function filterByMultibuy($multibuy = null, $comparison = null)
    {
        if (is_array($multibuy)) {
            $useMinMax = false;
            if (isset($multibuy['min'])) {
                $this->addUsingAlias(DonatedItemTableMap::COL_DI_MULTIBUY, $multibuy['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($multibuy['max'])) {
                $this->addUsingAlias(DonatedItemTableMap::COL_DI_MULTIBUY, $multibuy['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(DonatedItemTableMap::COL_DI_MULTIBUY, $multibuy, $comparison);
    }

    /**
     * Filter the query on the di_title column
     *
     * Example usage:
     * <code>
     * $query->filterByTitle('fooValue');   // WHERE di_title = 'fooValue'
     * $query->filterByTitle('%fooValue%', Criteria::LIKE); // WHERE di_title LIKE '%fooValue%'
     * </code>
     *
     * @param     string $title The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildDonatedItemQuery The current query, for fluid interface
     */
    public function filterByTitle($title = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($title)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(DonatedItemTableMap::COL_DI_TITLE, $title, $comparison);
    }

    /**
     * Filter the query on the di_description column
     *
     * Example usage:
     * <code>
     * $query->filterByDescription('fooValue');   // WHERE di_description = 'fooValue'
     * $query->filterByDescription('%fooValue%', Criteria::LIKE); // WHERE di_description LIKE '%fooValue%'
     * </code>
     *
     * @param     string $description The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildDonatedItemQuery The current query, for fluid interface
     */
    public function filterByDescription($description = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($description)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(DonatedItemTableMap::COL_DI_DESCRIPTION, $description, $comparison);
    }

    /**
     * Filter the query on the di_sellprice column
     *
     * Example usage:
     * <code>
     * $query->filterBySellprice(1234); // WHERE di_sellprice = 1234
     * $query->filterBySellprice(array(12, 34)); // WHERE di_sellprice IN (12, 34)
     * $query->filterBySellprice(array('min' => 12)); // WHERE di_sellprice > 12
     * </code>
     *
     * @param     mixed $sellprice The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildDonatedItemQuery The current query, for fluid interface
     */
    public function filterBySellprice($sellprice = null, $comparison = null)
    {
        if (is_array($sellprice)) {
            $useMinMax = false;
            if (isset($sellprice['min'])) {
                $this->addUsingAlias(DonatedItemTableMap::COL_DI_SELLPRICE, $sellprice['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($sellprice['max'])) {
                $this->addUsingAlias(DonatedItemTableMap::COL_DI_SELLPRICE, $sellprice['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(DonatedItemTableMap::COL_DI_SELLPRICE, $sellprice, $comparison);
    }

    /**
     * Filter the query on the di_estprice column
     *
     * Example usage:
     * <code>
     * $query->filterByEstprice(1234); // WHERE di_estprice = 1234
     * $query->filterByEstprice(array(12, 34)); // WHERE di_estprice IN (12, 34)
     * $query->filterByEstprice(array('min' => 12)); // WHERE di_estprice > 12
     * </code>
     *
     * @param     mixed $estprice The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildDonatedItemQuery The current query, for fluid interface
     */
    public function filterByEstprice($estprice = null, $comparison = null)
    {
        if (is_array($estprice)) {
            $useMinMax = false;
            if (isset($estprice['min'])) {
                $this->addUsingAlias(DonatedItemTableMap::COL_DI_ESTPRICE, $estprice['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($estprice['max'])) {
                $this->addUsingAlias(DonatedItemTableMap::COL_DI_ESTPRICE, $estprice['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(DonatedItemTableMap::COL_DI_ESTPRICE, $estprice, $comparison);
    }

    /**
     * Filter the query on the di_minimum column
     *
     * Example usage:
     * <code>
     * $query->filterByMinimum(1234); // WHERE di_minimum = 1234
     * $query->filterByMinimum(array(12, 34)); // WHERE di_minimum IN (12, 34)
     * $query->filterByMinimum(array('min' => 12)); // WHERE di_minimum > 12
     * </code>
     *
     * @param     mixed $minimum The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildDonatedItemQuery The current query, for fluid interface
     */
    public function filterByMinimum($minimum = null, $comparison = null)
    {
        if (is_array($minimum)) {
            $useMinMax = false;
            if (isset($minimum['min'])) {
                $this->addUsingAlias(DonatedItemTableMap::COL_DI_MINIMUM, $minimum['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($minimum['max'])) {
                $this->addUsingAlias(DonatedItemTableMap::COL_DI_MINIMUM, $minimum['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(DonatedItemTableMap::COL_DI_MINIMUM, $minimum, $comparison);
    }

    /**
     * Filter the query on the di_materialvalue column
     *
     * Example usage:
     * <code>
     * $query->filterByMaterialValue(1234); // WHERE di_materialvalue = 1234
     * $query->filterByMaterialValue(array(12, 34)); // WHERE di_materialvalue IN (12, 34)
     * $query->filterByMaterialValue(array('min' => 12)); // WHERE di_materialvalue > 12
     * </code>
     *
     * @param     mixed $materialValue The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildDonatedItemQuery The current query, for fluid interface
     */
    public function filterByMaterialValue($materialValue = null, $comparison = null)
    {
        if (is_array($materialValue)) {
            $useMinMax = false;
            if (isset($materialValue['min'])) {
                $this->addUsingAlias(DonatedItemTableMap::COL_DI_MATERIALVALUE, $materialValue['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($materialValue['max'])) {
                $this->addUsingAlias(DonatedItemTableMap::COL_DI_MATERIALVALUE, $materialValue['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(DonatedItemTableMap::COL_DI_MATERIALVALUE, $materialValue, $comparison);
    }

    /**
     * Filter the query on the di_EnteredBy column
     *
     * Example usage:
     * <code>
     * $query->filterByEnteredby(1234); // WHERE di_EnteredBy = 1234
     * $query->filterByEnteredby(array(12, 34)); // WHERE di_EnteredBy IN (12, 34)
     * $query->filterByEnteredby(array('min' => 12)); // WHERE di_EnteredBy > 12
     * </code>
     *
     * @param     mixed $enteredby The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildDonatedItemQuery The current query, for fluid interface
     */
    public function filterByEnteredby($enteredby = null, $comparison = null)
    {
        if (is_array($enteredby)) {
            $useMinMax = false;
            if (isset($enteredby['min'])) {
                $this->addUsingAlias(DonatedItemTableMap::COL_DI_ENTEREDBY, $enteredby['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($enteredby['max'])) {
                $this->addUsingAlias(DonatedItemTableMap::COL_DI_ENTEREDBY, $enteredby['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(DonatedItemTableMap::COL_DI_ENTEREDBY, $enteredby, $comparison);
    }

    /**
     * Filter the query on the di_EnteredDate column
     *
     * Example usage:
     * <code>
     * $query->filterByEntereddate('2011-03-14'); // WHERE di_EnteredDate = '2011-03-14'
     * $query->filterByEntereddate('now'); // WHERE di_EnteredDate = '2011-03-14'
     * $query->filterByEntereddate(array('max' => 'yesterday')); // WHERE di_EnteredDate > '2011-03-13'
     * </code>
     *
     * @param     mixed $entereddate The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildDonatedItemQuery The current query, for fluid interface
     */
    public function filterByEntereddate($entereddate = null, $comparison = null)
    {
        if (is_array($entereddate)) {
            $useMinMax = false;
            if (isset($entereddate['min'])) {
                $this->addUsingAlias(DonatedItemTableMap::COL_DI_ENTEREDDATE, $entereddate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($entereddate['max'])) {
                $this->addUsingAlias(DonatedItemTableMap::COL_DI_ENTEREDDATE, $entereddate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(DonatedItemTableMap::COL_DI_ENTEREDDATE, $entereddate, $comparison);
    }

    /**
     * Filter the query on the di_picture column
     *
     * Example usage:
     * <code>
     * $query->filterByPicture('fooValue');   // WHERE di_picture = 'fooValue'
     * $query->filterByPicture('%fooValue%', Criteria::LIKE); // WHERE di_picture LIKE '%fooValue%'
     * </code>
     *
     * @param     string $picture The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildDonatedItemQuery The current query, for fluid interface
     */
    public function filterByPicture($picture = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($picture)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(DonatedItemTableMap::COL_DI_PICTURE, $picture, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildDonatedItem $donatedItem Object to remove from the list of results
     *
     * @return $this|ChildDonatedItemQuery The current query, for fluid interface
     */
    public function prune($donatedItem = null)
    {
        if ($donatedItem) {
            $this->addUsingAlias(DonatedItemTableMap::COL_DI_ID, $donatedItem->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the donateditem_di table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(DonatedItemTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            DonatedItemTableMap::clearInstancePool();
            DonatedItemTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(DonatedItemTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(DonatedItemTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            DonatedItemTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            DonatedItemTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // DonatedItemQuery
