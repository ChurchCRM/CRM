<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\QueryParameters as ChildQueryParameters;
use ChurchCRM\QueryParametersQuery as ChildQueryParametersQuery;
use ChurchCRM\Map\QueryParametersTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'queryparameters_qrp' table.
 *
 *
 *
 * @method     ChildQueryParametersQuery orderById($order = Criteria::ASC) Order by the qrp_ID column
 * @method     ChildQueryParametersQuery orderByQryId($order = Criteria::ASC) Order by the qrp_qry_ID column
 * @method     ChildQueryParametersQuery orderByType($order = Criteria::ASC) Order by the qrp_Type column
 * @method     ChildQueryParametersQuery orderByOptionSQL($order = Criteria::ASC) Order by the qrp_OptionSQL column
 * @method     ChildQueryParametersQuery orderByName($order = Criteria::ASC) Order by the qrp_Name column
 * @method     ChildQueryParametersQuery orderByDescription($order = Criteria::ASC) Order by the qrp_Description column
 * @method     ChildQueryParametersQuery orderByAlias($order = Criteria::ASC) Order by the qrp_Alias column
 * @method     ChildQueryParametersQuery orderByDefault($order = Criteria::ASC) Order by the qrp_Default column
 * @method     ChildQueryParametersQuery orderByRequired($order = Criteria::ASC) Order by the qrp_Required column
 * @method     ChildQueryParametersQuery orderByInputBoxSize($order = Criteria::ASC) Order by the qrp_InputBoxSize column
 * @method     ChildQueryParametersQuery orderByValidation($order = Criteria::ASC) Order by the qrp_Validation column
 * @method     ChildQueryParametersQuery orderByNumericMax($order = Criteria::ASC) Order by the qrp_NumericMax column
 * @method     ChildQueryParametersQuery orderByNumericMin($order = Criteria::ASC) Order by the qrp_NumericMin column
 * @method     ChildQueryParametersQuery orderByAlphaMinLength($order = Criteria::ASC) Order by the qrp_AlphaMinLength column
 * @method     ChildQueryParametersQuery orderByAlphaMaxLength($order = Criteria::ASC) Order by the qrp_AlphaMaxLength column
 *
 * @method     ChildQueryParametersQuery groupById() Group by the qrp_ID column
 * @method     ChildQueryParametersQuery groupByQryId() Group by the qrp_qry_ID column
 * @method     ChildQueryParametersQuery groupByType() Group by the qrp_Type column
 * @method     ChildQueryParametersQuery groupByOptionSQL() Group by the qrp_OptionSQL column
 * @method     ChildQueryParametersQuery groupByName() Group by the qrp_Name column
 * @method     ChildQueryParametersQuery groupByDescription() Group by the qrp_Description column
 * @method     ChildQueryParametersQuery groupByAlias() Group by the qrp_Alias column
 * @method     ChildQueryParametersQuery groupByDefault() Group by the qrp_Default column
 * @method     ChildQueryParametersQuery groupByRequired() Group by the qrp_Required column
 * @method     ChildQueryParametersQuery groupByInputBoxSize() Group by the qrp_InputBoxSize column
 * @method     ChildQueryParametersQuery groupByValidation() Group by the qrp_Validation column
 * @method     ChildQueryParametersQuery groupByNumericMax() Group by the qrp_NumericMax column
 * @method     ChildQueryParametersQuery groupByNumericMin() Group by the qrp_NumericMin column
 * @method     ChildQueryParametersQuery groupByAlphaMinLength() Group by the qrp_AlphaMinLength column
 * @method     ChildQueryParametersQuery groupByAlphaMaxLength() Group by the qrp_AlphaMaxLength column
 *
 * @method     ChildQueryParametersQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildQueryParametersQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildQueryParametersQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildQueryParametersQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildQueryParametersQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildQueryParametersQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildQueryParameters findOne(ConnectionInterface $con = null) Return the first ChildQueryParameters matching the query
 * @method     ChildQueryParameters findOneOrCreate(ConnectionInterface $con = null) Return the first ChildQueryParameters matching the query, or a new ChildQueryParameters object populated from the query conditions when no match is found
 *
 * @method     ChildQueryParameters findOneById(int $qrp_ID) Return the first ChildQueryParameters filtered by the qrp_ID column
 * @method     ChildQueryParameters findOneByQryId(int $qrp_qry_ID) Return the first ChildQueryParameters filtered by the qrp_qry_ID column
 * @method     ChildQueryParameters findOneByType(int $qrp_Type) Return the first ChildQueryParameters filtered by the qrp_Type column
 * @method     ChildQueryParameters findOneByOptionSQL(string $qrp_OptionSQL) Return the first ChildQueryParameters filtered by the qrp_OptionSQL column
 * @method     ChildQueryParameters findOneByName(string $qrp_Name) Return the first ChildQueryParameters filtered by the qrp_Name column
 * @method     ChildQueryParameters findOneByDescription(string $qrp_Description) Return the first ChildQueryParameters filtered by the qrp_Description column
 * @method     ChildQueryParameters findOneByAlias(string $qrp_Alias) Return the first ChildQueryParameters filtered by the qrp_Alias column
 * @method     ChildQueryParameters findOneByDefault(string $qrp_Default) Return the first ChildQueryParameters filtered by the qrp_Default column
 * @method     ChildQueryParameters findOneByRequired(int $qrp_Required) Return the first ChildQueryParameters filtered by the qrp_Required column
 * @method     ChildQueryParameters findOneByInputBoxSize(int $qrp_InputBoxSize) Return the first ChildQueryParameters filtered by the qrp_InputBoxSize column
 * @method     ChildQueryParameters findOneByValidation(string $qrp_Validation) Return the first ChildQueryParameters filtered by the qrp_Validation column
 * @method     ChildQueryParameters findOneByNumericMax(int $qrp_NumericMax) Return the first ChildQueryParameters filtered by the qrp_NumericMax column
 * @method     ChildQueryParameters findOneByNumericMin(int $qrp_NumericMin) Return the first ChildQueryParameters filtered by the qrp_NumericMin column
 * @method     ChildQueryParameters findOneByAlphaMinLength(int $qrp_AlphaMinLength) Return the first ChildQueryParameters filtered by the qrp_AlphaMinLength column
 * @method     ChildQueryParameters findOneByAlphaMaxLength(int $qrp_AlphaMaxLength) Return the first ChildQueryParameters filtered by the qrp_AlphaMaxLength column *

 * @method     ChildQueryParameters requirePk($key, ConnectionInterface $con = null) Return the ChildQueryParameters by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildQueryParameters requireOne(ConnectionInterface $con = null) Return the first ChildQueryParameters matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildQueryParameters requireOneById(int $qrp_ID) Return the first ChildQueryParameters filtered by the qrp_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildQueryParameters requireOneByQryId(int $qrp_qry_ID) Return the first ChildQueryParameters filtered by the qrp_qry_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildQueryParameters requireOneByType(int $qrp_Type) Return the first ChildQueryParameters filtered by the qrp_Type column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildQueryParameters requireOneByOptionSQL(string $qrp_OptionSQL) Return the first ChildQueryParameters filtered by the qrp_OptionSQL column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildQueryParameters requireOneByName(string $qrp_Name) Return the first ChildQueryParameters filtered by the qrp_Name column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildQueryParameters requireOneByDescription(string $qrp_Description) Return the first ChildQueryParameters filtered by the qrp_Description column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildQueryParameters requireOneByAlias(string $qrp_Alias) Return the first ChildQueryParameters filtered by the qrp_Alias column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildQueryParameters requireOneByDefault(string $qrp_Default) Return the first ChildQueryParameters filtered by the qrp_Default column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildQueryParameters requireOneByRequired(int $qrp_Required) Return the first ChildQueryParameters filtered by the qrp_Required column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildQueryParameters requireOneByInputBoxSize(int $qrp_InputBoxSize) Return the first ChildQueryParameters filtered by the qrp_InputBoxSize column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildQueryParameters requireOneByValidation(string $qrp_Validation) Return the first ChildQueryParameters filtered by the qrp_Validation column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildQueryParameters requireOneByNumericMax(int $qrp_NumericMax) Return the first ChildQueryParameters filtered by the qrp_NumericMax column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildQueryParameters requireOneByNumericMin(int $qrp_NumericMin) Return the first ChildQueryParameters filtered by the qrp_NumericMin column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildQueryParameters requireOneByAlphaMinLength(int $qrp_AlphaMinLength) Return the first ChildQueryParameters filtered by the qrp_AlphaMinLength column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildQueryParameters requireOneByAlphaMaxLength(int $qrp_AlphaMaxLength) Return the first ChildQueryParameters filtered by the qrp_AlphaMaxLength column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildQueryParameters[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildQueryParameters objects based on current ModelCriteria
 * @method     ChildQueryParameters[]|ObjectCollection findById(int $qrp_ID) Return ChildQueryParameters objects filtered by the qrp_ID column
 * @method     ChildQueryParameters[]|ObjectCollection findByQryId(int $qrp_qry_ID) Return ChildQueryParameters objects filtered by the qrp_qry_ID column
 * @method     ChildQueryParameters[]|ObjectCollection findByType(int $qrp_Type) Return ChildQueryParameters objects filtered by the qrp_Type column
 * @method     ChildQueryParameters[]|ObjectCollection findByOptionSQL(string $qrp_OptionSQL) Return ChildQueryParameters objects filtered by the qrp_OptionSQL column
 * @method     ChildQueryParameters[]|ObjectCollection findByName(string $qrp_Name) Return ChildQueryParameters objects filtered by the qrp_Name column
 * @method     ChildQueryParameters[]|ObjectCollection findByDescription(string $qrp_Description) Return ChildQueryParameters objects filtered by the qrp_Description column
 * @method     ChildQueryParameters[]|ObjectCollection findByAlias(string $qrp_Alias) Return ChildQueryParameters objects filtered by the qrp_Alias column
 * @method     ChildQueryParameters[]|ObjectCollection findByDefault(string $qrp_Default) Return ChildQueryParameters objects filtered by the qrp_Default column
 * @method     ChildQueryParameters[]|ObjectCollection findByRequired(int $qrp_Required) Return ChildQueryParameters objects filtered by the qrp_Required column
 * @method     ChildQueryParameters[]|ObjectCollection findByInputBoxSize(int $qrp_InputBoxSize) Return ChildQueryParameters objects filtered by the qrp_InputBoxSize column
 * @method     ChildQueryParameters[]|ObjectCollection findByValidation(string $qrp_Validation) Return ChildQueryParameters objects filtered by the qrp_Validation column
 * @method     ChildQueryParameters[]|ObjectCollection findByNumericMax(int $qrp_NumericMax) Return ChildQueryParameters objects filtered by the qrp_NumericMax column
 * @method     ChildQueryParameters[]|ObjectCollection findByNumericMin(int $qrp_NumericMin) Return ChildQueryParameters objects filtered by the qrp_NumericMin column
 * @method     ChildQueryParameters[]|ObjectCollection findByAlphaMinLength(int $qrp_AlphaMinLength) Return ChildQueryParameters objects filtered by the qrp_AlphaMinLength column
 * @method     ChildQueryParameters[]|ObjectCollection findByAlphaMaxLength(int $qrp_AlphaMaxLength) Return ChildQueryParameters objects filtered by the qrp_AlphaMaxLength column
 * @method     ChildQueryParameters[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class QueryParametersQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\QueryParametersQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\QueryParameters', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildQueryParametersQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildQueryParametersQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildQueryParametersQuery) {
            return $criteria;
        }
        $query = new ChildQueryParametersQuery();
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
     * @return ChildQueryParameters|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(QueryParametersTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (
            $this->formatter || $this->modelAlias || $this->with || $this->select
            || $this->selectColumns || $this->asColumns || $this->selectModifiers
            || $this->map || $this->having || $this->joins
        ) {
            return $this->findPkComplex($key, $con);
        }

        if ((null !== ($obj = QueryParametersTableMap::getInstanceFromPool(null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key)))) {
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
     * @return ChildQueryParameters A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT qrp_ID, qrp_qry_ID, qrp_Type, qrp_OptionSQL, qrp_Name, qrp_Description, qrp_Alias, qrp_Default, qrp_Required, qrp_InputBoxSize, qrp_Validation, qrp_NumericMax, qrp_NumericMin, qrp_AlphaMinLength, qrp_AlphaMaxLength FROM queryparameters_qrp WHERE qrp_ID = :p0';
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
            /** @var ChildQueryParameters $obj */
            $obj = new ChildQueryParameters();
            $obj->hydrate($row);
            QueryParametersTableMap::addInstanceToPool($obj, null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key);
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
     * @return ChildQueryParameters|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildQueryParametersQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(QueryParametersTableMap::COL_QRP_ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildQueryParametersQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(QueryParametersTableMap::COL_QRP_ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the qrp_ID column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE qrp_ID = 1234
     * $query->filterById(array(12, 34)); // WHERE qrp_ID IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE qrp_ID > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildQueryParametersQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(QueryParametersTableMap::COL_QRP_ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(QueryParametersTableMap::COL_QRP_ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(QueryParametersTableMap::COL_QRP_ID, $id, $comparison);
    }

    /**
     * Filter the query on the qrp_qry_ID column
     *
     * Example usage:
     * <code>
     * $query->filterByQryId(1234); // WHERE qrp_qry_ID = 1234
     * $query->filterByQryId(array(12, 34)); // WHERE qrp_qry_ID IN (12, 34)
     * $query->filterByQryId(array('min' => 12)); // WHERE qrp_qry_ID > 12
     * </code>
     *
     * @param     mixed $qryId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildQueryParametersQuery The current query, for fluid interface
     */
    public function filterByQryId($qryId = null, $comparison = null)
    {
        if (is_array($qryId)) {
            $useMinMax = false;
            if (isset($qryId['min'])) {
                $this->addUsingAlias(QueryParametersTableMap::COL_QRP_QRY_ID, $qryId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($qryId['max'])) {
                $this->addUsingAlias(QueryParametersTableMap::COL_QRP_QRY_ID, $qryId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(QueryParametersTableMap::COL_QRP_QRY_ID, $qryId, $comparison);
    }

    /**
     * Filter the query on the qrp_Type column
     *
     * Example usage:
     * <code>
     * $query->filterByType(1234); // WHERE qrp_Type = 1234
     * $query->filterByType(array(12, 34)); // WHERE qrp_Type IN (12, 34)
     * $query->filterByType(array('min' => 12)); // WHERE qrp_Type > 12
     * </code>
     *
     * @param     mixed $type The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildQueryParametersQuery The current query, for fluid interface
     */
    public function filterByType($type = null, $comparison = null)
    {
        if (is_array($type)) {
            $useMinMax = false;
            if (isset($type['min'])) {
                $this->addUsingAlias(QueryParametersTableMap::COL_QRP_TYPE, $type['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($type['max'])) {
                $this->addUsingAlias(QueryParametersTableMap::COL_QRP_TYPE, $type['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(QueryParametersTableMap::COL_QRP_TYPE, $type, $comparison);
    }

    /**
     * Filter the query on the qrp_OptionSQL column
     *
     * Example usage:
     * <code>
     * $query->filterByOptionSQL('fooValue');   // WHERE qrp_OptionSQL = 'fooValue'
     * $query->filterByOptionSQL('%fooValue%', Criteria::LIKE); // WHERE qrp_OptionSQL LIKE '%fooValue%'
     * </code>
     *
     * @param     string $optionSQL The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildQueryParametersQuery The current query, for fluid interface
     */
    public function filterByOptionSQL($optionSQL = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($optionSQL)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(QueryParametersTableMap::COL_QRP_OPTIONSQL, $optionSQL, $comparison);
    }

    /**
     * Filter the query on the qrp_Name column
     *
     * Example usage:
     * <code>
     * $query->filterByName('fooValue');   // WHERE qrp_Name = 'fooValue'
     * $query->filterByName('%fooValue%', Criteria::LIKE); // WHERE qrp_Name LIKE '%fooValue%'
     * </code>
     *
     * @param     string $name The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildQueryParametersQuery The current query, for fluid interface
     */
    public function filterByName($name = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($name)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(QueryParametersTableMap::COL_QRP_NAME, $name, $comparison);
    }

    /**
     * Filter the query on the qrp_Description column
     *
     * Example usage:
     * <code>
     * $query->filterByDescription('fooValue');   // WHERE qrp_Description = 'fooValue'
     * $query->filterByDescription('%fooValue%', Criteria::LIKE); // WHERE qrp_Description LIKE '%fooValue%'
     * </code>
     *
     * @param     string $description The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildQueryParametersQuery The current query, for fluid interface
     */
    public function filterByDescription($description = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($description)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(QueryParametersTableMap::COL_QRP_DESCRIPTION, $description, $comparison);
    }

    /**
     * Filter the query on the qrp_Alias column
     *
     * Example usage:
     * <code>
     * $query->filterByAlias('fooValue');   // WHERE qrp_Alias = 'fooValue'
     * $query->filterByAlias('%fooValue%', Criteria::LIKE); // WHERE qrp_Alias LIKE '%fooValue%'
     * </code>
     *
     * @param     string $alias The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildQueryParametersQuery The current query, for fluid interface
     */
    public function filterByAlias($alias = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($alias)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(QueryParametersTableMap::COL_QRP_ALIAS, $alias, $comparison);
    }

    /**
     * Filter the query on the qrp_Default column
     *
     * Example usage:
     * <code>
     * $query->filterByDefault('fooValue');   // WHERE qrp_Default = 'fooValue'
     * $query->filterByDefault('%fooValue%', Criteria::LIKE); // WHERE qrp_Default LIKE '%fooValue%'
     * </code>
     *
     * @param     string $default The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildQueryParametersQuery The current query, for fluid interface
     */
    public function filterByDefault($default = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($default)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(QueryParametersTableMap::COL_QRP_DEFAULT, $default, $comparison);
    }

    /**
     * Filter the query on the qrp_Required column
     *
     * Example usage:
     * <code>
     * $query->filterByRequired(1234); // WHERE qrp_Required = 1234
     * $query->filterByRequired(array(12, 34)); // WHERE qrp_Required IN (12, 34)
     * $query->filterByRequired(array('min' => 12)); // WHERE qrp_Required > 12
     * </code>
     *
     * @param     mixed $required The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildQueryParametersQuery The current query, for fluid interface
     */
    public function filterByRequired($required = null, $comparison = null)
    {
        if (is_array($required)) {
            $useMinMax = false;
            if (isset($required['min'])) {
                $this->addUsingAlias(QueryParametersTableMap::COL_QRP_REQUIRED, $required['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($required['max'])) {
                $this->addUsingAlias(QueryParametersTableMap::COL_QRP_REQUIRED, $required['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(QueryParametersTableMap::COL_QRP_REQUIRED, $required, $comparison);
    }

    /**
     * Filter the query on the qrp_InputBoxSize column
     *
     * Example usage:
     * <code>
     * $query->filterByInputBoxSize(1234); // WHERE qrp_InputBoxSize = 1234
     * $query->filterByInputBoxSize(array(12, 34)); // WHERE qrp_InputBoxSize IN (12, 34)
     * $query->filterByInputBoxSize(array('min' => 12)); // WHERE qrp_InputBoxSize > 12
     * </code>
     *
     * @param     mixed $inputBoxSize The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildQueryParametersQuery The current query, for fluid interface
     */
    public function filterByInputBoxSize($inputBoxSize = null, $comparison = null)
    {
        if (is_array($inputBoxSize)) {
            $useMinMax = false;
            if (isset($inputBoxSize['min'])) {
                $this->addUsingAlias(QueryParametersTableMap::COL_QRP_INPUTBOXSIZE, $inputBoxSize['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($inputBoxSize['max'])) {
                $this->addUsingAlias(QueryParametersTableMap::COL_QRP_INPUTBOXSIZE, $inputBoxSize['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(QueryParametersTableMap::COL_QRP_INPUTBOXSIZE, $inputBoxSize, $comparison);
    }

    /**
     * Filter the query on the qrp_Validation column
     *
     * Example usage:
     * <code>
     * $query->filterByValidation('fooValue');   // WHERE qrp_Validation = 'fooValue'
     * $query->filterByValidation('%fooValue%', Criteria::LIKE); // WHERE qrp_Validation LIKE '%fooValue%'
     * </code>
     *
     * @param     string $validation The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildQueryParametersQuery The current query, for fluid interface
     */
    public function filterByValidation($validation = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($validation)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(QueryParametersTableMap::COL_QRP_VALIDATION, $validation, $comparison);
    }

    /**
     * Filter the query on the qrp_NumericMax column
     *
     * Example usage:
     * <code>
     * $query->filterByNumericMax(1234); // WHERE qrp_NumericMax = 1234
     * $query->filterByNumericMax(array(12, 34)); // WHERE qrp_NumericMax IN (12, 34)
     * $query->filterByNumericMax(array('min' => 12)); // WHERE qrp_NumericMax > 12
     * </code>
     *
     * @param     mixed $numericMax The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildQueryParametersQuery The current query, for fluid interface
     */
    public function filterByNumericMax($numericMax = null, $comparison = null)
    {
        if (is_array($numericMax)) {
            $useMinMax = false;
            if (isset($numericMax['min'])) {
                $this->addUsingAlias(QueryParametersTableMap::COL_QRP_NUMERICMAX, $numericMax['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($numericMax['max'])) {
                $this->addUsingAlias(QueryParametersTableMap::COL_QRP_NUMERICMAX, $numericMax['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(QueryParametersTableMap::COL_QRP_NUMERICMAX, $numericMax, $comparison);
    }

    /**
     * Filter the query on the qrp_NumericMin column
     *
     * Example usage:
     * <code>
     * $query->filterByNumericMin(1234); // WHERE qrp_NumericMin = 1234
     * $query->filterByNumericMin(array(12, 34)); // WHERE qrp_NumericMin IN (12, 34)
     * $query->filterByNumericMin(array('min' => 12)); // WHERE qrp_NumericMin > 12
     * </code>
     *
     * @param     mixed $numericMin The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildQueryParametersQuery The current query, for fluid interface
     */
    public function filterByNumericMin($numericMin = null, $comparison = null)
    {
        if (is_array($numericMin)) {
            $useMinMax = false;
            if (isset($numericMin['min'])) {
                $this->addUsingAlias(QueryParametersTableMap::COL_QRP_NUMERICMIN, $numericMin['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($numericMin['max'])) {
                $this->addUsingAlias(QueryParametersTableMap::COL_QRP_NUMERICMIN, $numericMin['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(QueryParametersTableMap::COL_QRP_NUMERICMIN, $numericMin, $comparison);
    }

    /**
     * Filter the query on the qrp_AlphaMinLength column
     *
     * Example usage:
     * <code>
     * $query->filterByAlphaMinLength(1234); // WHERE qrp_AlphaMinLength = 1234
     * $query->filterByAlphaMinLength(array(12, 34)); // WHERE qrp_AlphaMinLength IN (12, 34)
     * $query->filterByAlphaMinLength(array('min' => 12)); // WHERE qrp_AlphaMinLength > 12
     * </code>
     *
     * @param     mixed $alphaMinLength The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildQueryParametersQuery The current query, for fluid interface
     */
    public function filterByAlphaMinLength($alphaMinLength = null, $comparison = null)
    {
        if (is_array($alphaMinLength)) {
            $useMinMax = false;
            if (isset($alphaMinLength['min'])) {
                $this->addUsingAlias(QueryParametersTableMap::COL_QRP_ALPHAMINLENGTH, $alphaMinLength['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($alphaMinLength['max'])) {
                $this->addUsingAlias(QueryParametersTableMap::COL_QRP_ALPHAMINLENGTH, $alphaMinLength['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(QueryParametersTableMap::COL_QRP_ALPHAMINLENGTH, $alphaMinLength, $comparison);
    }

    /**
     * Filter the query on the qrp_AlphaMaxLength column
     *
     * Example usage:
     * <code>
     * $query->filterByAlphaMaxLength(1234); // WHERE qrp_AlphaMaxLength = 1234
     * $query->filterByAlphaMaxLength(array(12, 34)); // WHERE qrp_AlphaMaxLength IN (12, 34)
     * $query->filterByAlphaMaxLength(array('min' => 12)); // WHERE qrp_AlphaMaxLength > 12
     * </code>
     *
     * @param     mixed $alphaMaxLength The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildQueryParametersQuery The current query, for fluid interface
     */
    public function filterByAlphaMaxLength($alphaMaxLength = null, $comparison = null)
    {
        if (is_array($alphaMaxLength)) {
            $useMinMax = false;
            if (isset($alphaMaxLength['min'])) {
                $this->addUsingAlias(QueryParametersTableMap::COL_QRP_ALPHAMAXLENGTH, $alphaMaxLength['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($alphaMaxLength['max'])) {
                $this->addUsingAlias(QueryParametersTableMap::COL_QRP_ALPHAMAXLENGTH, $alphaMaxLength['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(QueryParametersTableMap::COL_QRP_ALPHAMAXLENGTH, $alphaMaxLength, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildQueryParameters $queryParameters Object to remove from the list of results
     *
     * @return $this|ChildQueryParametersQuery The current query, for fluid interface
     */
    public function prune($queryParameters = null)
    {
        if ($queryParameters) {
            $this->addUsingAlias(QueryParametersTableMap::COL_QRP_ID, $queryParameters->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the queryparameters_qrp table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(QueryParametersTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            QueryParametersTableMap::clearInstancePool();
            QueryParametersTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(QueryParametersTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(QueryParametersTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            QueryParametersTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            QueryParametersTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // QueryParametersQuery
