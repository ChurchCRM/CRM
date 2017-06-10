<?php

namespace ChurchCRM\Base;

use \Exception;
use \PDO;
use ChurchCRM\Property as ChildProperty;
use ChurchCRM\PropertyQuery as ChildPropertyQuery;
use ChurchCRM\Map\PropertyTableMap;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\ModelJoin;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;

/**
 * Base class that represents a query for the 'property_pro' table.
 *
 *
 *
 * @method     ChildPropertyQuery orderByProId($order = Criteria::ASC) Order by the pro_ID column
 * @method     ChildPropertyQuery orderByProClass($order = Criteria::ASC) Order by the pro_Class column
 * @method     ChildPropertyQuery orderByProPrtId($order = Criteria::ASC) Order by the pro_prt_ID column
 * @method     ChildPropertyQuery orderByProName($order = Criteria::ASC) Order by the pro_Name column
 * @method     ChildPropertyQuery orderByProDescription($order = Criteria::ASC) Order by the pro_Description column
 * @method     ChildPropertyQuery orderByProPrompt($order = Criteria::ASC) Order by the pro_Prompt column
 *
 * @method     ChildPropertyQuery groupByProId() Group by the pro_ID column
 * @method     ChildPropertyQuery groupByProClass() Group by the pro_Class column
 * @method     ChildPropertyQuery groupByProPrtId() Group by the pro_prt_ID column
 * @method     ChildPropertyQuery groupByProName() Group by the pro_Name column
 * @method     ChildPropertyQuery groupByProDescription() Group by the pro_Description column
 * @method     ChildPropertyQuery groupByProPrompt() Group by the pro_Prompt column
 *
 * @method     ChildPropertyQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildPropertyQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildPropertyQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildPropertyQuery leftJoinWith($relation) Adds a LEFT JOIN clause and with to the query
 * @method     ChildPropertyQuery rightJoinWith($relation) Adds a RIGHT JOIN clause and with to the query
 * @method     ChildPropertyQuery innerJoinWith($relation) Adds a INNER JOIN clause and with to the query
 *
 * @method     ChildPropertyQuery leftJoinPropertyType($relationAlias = null) Adds a LEFT JOIN clause to the query using the PropertyType relation
 * @method     ChildPropertyQuery rightJoinPropertyType($relationAlias = null) Adds a RIGHT JOIN clause to the query using the PropertyType relation
 * @method     ChildPropertyQuery innerJoinPropertyType($relationAlias = null) Adds a INNER JOIN clause to the query using the PropertyType relation
 *
 * @method     ChildPropertyQuery joinWithPropertyType($joinType = Criteria::INNER_JOIN) Adds a join clause and with to the query using the PropertyType relation
 *
 * @method     ChildPropertyQuery leftJoinWithPropertyType() Adds a LEFT JOIN clause and with to the query using the PropertyType relation
 * @method     ChildPropertyQuery rightJoinWithPropertyType() Adds a RIGHT JOIN clause and with to the query using the PropertyType relation
 * @method     ChildPropertyQuery innerJoinWithPropertyType() Adds a INNER JOIN clause and with to the query using the PropertyType relation
 *
 * @method     ChildPropertyQuery leftJoinPersonProperty($relationAlias = null) Adds a LEFT JOIN clause to the query using the PersonProperty relation
 * @method     ChildPropertyQuery rightJoinPersonProperty($relationAlias = null) Adds a RIGHT JOIN clause to the query using the PersonProperty relation
 * @method     ChildPropertyQuery innerJoinPersonProperty($relationAlias = null) Adds a INNER JOIN clause to the query using the PersonProperty relation
 *
 * @method     ChildPropertyQuery joinWithPersonProperty($joinType = Criteria::INNER_JOIN) Adds a join clause and with to the query using the PersonProperty relation
 *
 * @method     ChildPropertyQuery leftJoinWithPersonProperty() Adds a LEFT JOIN clause and with to the query using the PersonProperty relation
 * @method     ChildPropertyQuery rightJoinWithPersonProperty() Adds a RIGHT JOIN clause and with to the query using the PersonProperty relation
 * @method     ChildPropertyQuery innerJoinWithPersonProperty() Adds a INNER JOIN clause and with to the query using the PersonProperty relation
 *
 * @method     \ChurchCRM\PropertyTypeQuery|\ChurchCRM\PersonPropertyQuery endUse() Finalizes a secondary criteria and merges it with its primary Criteria
 *
 * @method     ChildProperty findOne(ConnectionInterface $con = null) Return the first ChildProperty matching the query
 * @method     ChildProperty findOneOrCreate(ConnectionInterface $con = null) Return the first ChildProperty matching the query, or a new ChildProperty object populated from the query conditions when no match is found
 *
 * @method     ChildProperty findOneByProId(int $pro_ID) Return the first ChildProperty filtered by the pro_ID column
 * @method     ChildProperty findOneByProClass(string $pro_Class) Return the first ChildProperty filtered by the pro_Class column
 * @method     ChildProperty findOneByProPrtId(int $pro_prt_ID) Return the first ChildProperty filtered by the pro_prt_ID column
 * @method     ChildProperty findOneByProName(string $pro_Name) Return the first ChildProperty filtered by the pro_Name column
 * @method     ChildProperty findOneByProDescription(string $pro_Description) Return the first ChildProperty filtered by the pro_Description column
 * @method     ChildProperty findOneByProPrompt(string $pro_Prompt) Return the first ChildProperty filtered by the pro_Prompt column *

 * @method     ChildProperty requirePk($key, ConnectionInterface $con = null) Return the ChildProperty by primary key and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildProperty requireOne(ConnectionInterface $con = null) Return the first ChildProperty matching the query and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildProperty requireOneByProId(int $pro_ID) Return the first ChildProperty filtered by the pro_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildProperty requireOneByProClass(string $pro_Class) Return the first ChildProperty filtered by the pro_Class column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildProperty requireOneByProPrtId(int $pro_prt_ID) Return the first ChildProperty filtered by the pro_prt_ID column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildProperty requireOneByProName(string $pro_Name) Return the first ChildProperty filtered by the pro_Name column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildProperty requireOneByProDescription(string $pro_Description) Return the first ChildProperty filtered by the pro_Description column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 * @method     ChildProperty requireOneByProPrompt(string $pro_Prompt) Return the first ChildProperty filtered by the pro_Prompt column and throws \Propel\Runtime\Exception\EntityNotFoundException when not found
 *
 * @method     ChildProperty[]|ObjectCollection find(ConnectionInterface $con = null) Return ChildProperty objects based on current ModelCriteria
 * @method     ChildProperty[]|ObjectCollection findByProId(int $pro_ID) Return ChildProperty objects filtered by the pro_ID column
 * @method     ChildProperty[]|ObjectCollection findByProClass(string $pro_Class) Return ChildProperty objects filtered by the pro_Class column
 * @method     ChildProperty[]|ObjectCollection findByProPrtId(int $pro_prt_ID) Return ChildProperty objects filtered by the pro_prt_ID column
 * @method     ChildProperty[]|ObjectCollection findByProName(string $pro_Name) Return ChildProperty objects filtered by the pro_Name column
 * @method     ChildProperty[]|ObjectCollection findByProDescription(string $pro_Description) Return ChildProperty objects filtered by the pro_Description column
 * @method     ChildProperty[]|ObjectCollection findByProPrompt(string $pro_Prompt) Return ChildProperty objects filtered by the pro_Prompt column
 * @method     ChildProperty[]|\Propel\Runtime\Util\PropelModelPager paginate($page = 1, $maxPerPage = 10, ConnectionInterface $con = null) Issue a SELECT query based on the current ModelCriteria and uses a page and a maximum number of results per page to compute an offset and a limit
 *
 */
abstract class PropertyQuery extends ModelCriteria
{
    protected $entityNotFoundExceptionClass = '\\Propel\\Runtime\\Exception\\EntityNotFoundException';

    /**
     * Initializes internal state of \ChurchCRM\Base\PropertyQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'default', $modelName = '\\ChurchCRM\\Property', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildPropertyQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildPropertyQuery
     */
    public static function create($modelAlias = null, Criteria $criteria = null)
    {
        if ($criteria instanceof ChildPropertyQuery) {
            return $criteria;
        }
        $query = new ChildPropertyQuery();
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
     * @return ChildProperty|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, ConnectionInterface $con = null)
    {
        if ($key === null) {
            return null;
        }

        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(PropertyTableMap::DATABASE_NAME);
        }

        $this->basePreSelect($con);

        if (
            $this->formatter || $this->modelAlias || $this->with || $this->select
            || $this->selectColumns || $this->asColumns || $this->selectModifiers
            || $this->map || $this->having || $this->joins
        ) {
            return $this->findPkComplex($key, $con);
        }

        if ((null !== ($obj = PropertyTableMap::getInstanceFromPool(null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key)))) {
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
     * @return ChildProperty A model object, or null if the key is not found
     */
    protected function findPkSimple($key, ConnectionInterface $con)
    {
        $sql = 'SELECT pro_ID, pro_Class, pro_prt_ID, pro_Name, pro_Description, pro_Prompt FROM property_pro WHERE pro_ID = :p0';
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
            /** @var ChildProperty $obj */
            $obj = new ChildProperty();
            $obj->hydrate($row);
            PropertyTableMap::addInstanceToPool($obj, null === $key || is_scalar($key) || is_callable([$key, '__toString']) ? (string) $key : $key);
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
     * @return ChildProperty|array|mixed the result, formatted by the current formatter
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
     * @return $this|ChildPropertyQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(PropertyTableMap::COL_PRO_ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return $this|ChildPropertyQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(PropertyTableMap::COL_PRO_ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the pro_ID column
     *
     * Example usage:
     * <code>
     * $query->filterByProId(1234); // WHERE pro_ID = 1234
     * $query->filterByProId(array(12, 34)); // WHERE pro_ID IN (12, 34)
     * $query->filterByProId(array('min' => 12)); // WHERE pro_ID > 12
     * </code>
     *
     * @param     mixed $proId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPropertyQuery The current query, for fluid interface
     */
    public function filterByProId($proId = null, $comparison = null)
    {
        if (is_array($proId)) {
            $useMinMax = false;
            if (isset($proId['min'])) {
                $this->addUsingAlias(PropertyTableMap::COL_PRO_ID, $proId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($proId['max'])) {
                $this->addUsingAlias(PropertyTableMap::COL_PRO_ID, $proId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PropertyTableMap::COL_PRO_ID, $proId, $comparison);
    }

    /**
     * Filter the query on the pro_Class column
     *
     * Example usage:
     * <code>
     * $query->filterByProClass('fooValue');   // WHERE pro_Class = 'fooValue'
     * $query->filterByProClass('%fooValue%', Criteria::LIKE); // WHERE pro_Class LIKE '%fooValue%'
     * </code>
     *
     * @param     string $proClass The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPropertyQuery The current query, for fluid interface
     */
    public function filterByProClass($proClass = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($proClass)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PropertyTableMap::COL_PRO_CLASS, $proClass, $comparison);
    }

    /**
     * Filter the query on the pro_prt_ID column
     *
     * Example usage:
     * <code>
     * $query->filterByProPrtId(1234); // WHERE pro_prt_ID = 1234
     * $query->filterByProPrtId(array(12, 34)); // WHERE pro_prt_ID IN (12, 34)
     * $query->filterByProPrtId(array('min' => 12)); // WHERE pro_prt_ID > 12
     * </code>
     *
     * @see       filterByPropertyType()
     *
     * @param     mixed $proPrtId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPropertyQuery The current query, for fluid interface
     */
    public function filterByProPrtId($proPrtId = null, $comparison = null)
    {
        if (is_array($proPrtId)) {
            $useMinMax = false;
            if (isset($proPrtId['min'])) {
                $this->addUsingAlias(PropertyTableMap::COL_PRO_PRT_ID, $proPrtId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($proPrtId['max'])) {
                $this->addUsingAlias(PropertyTableMap::COL_PRO_PRT_ID, $proPrtId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PropertyTableMap::COL_PRO_PRT_ID, $proPrtId, $comparison);
    }

    /**
     * Filter the query on the pro_Name column
     *
     * Example usage:
     * <code>
     * $query->filterByProName('fooValue');   // WHERE pro_Name = 'fooValue'
     * $query->filterByProName('%fooValue%', Criteria::LIKE); // WHERE pro_Name LIKE '%fooValue%'
     * </code>
     *
     * @param     string $proName The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPropertyQuery The current query, for fluid interface
     */
    public function filterByProName($proName = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($proName)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PropertyTableMap::COL_PRO_NAME, $proName, $comparison);
    }

    /**
     * Filter the query on the pro_Description column
     *
     * Example usage:
     * <code>
     * $query->filterByProDescription('fooValue');   // WHERE pro_Description = 'fooValue'
     * $query->filterByProDescription('%fooValue%', Criteria::LIKE); // WHERE pro_Description LIKE '%fooValue%'
     * </code>
     *
     * @param     string $proDescription The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPropertyQuery The current query, for fluid interface
     */
    public function filterByProDescription($proDescription = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($proDescription)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PropertyTableMap::COL_PRO_DESCRIPTION, $proDescription, $comparison);
    }

    /**
     * Filter the query on the pro_Prompt column
     *
     * Example usage:
     * <code>
     * $query->filterByProPrompt('fooValue');   // WHERE pro_Prompt = 'fooValue'
     * $query->filterByProPrompt('%fooValue%', Criteria::LIKE); // WHERE pro_Prompt LIKE '%fooValue%'
     * </code>
     *
     * @param     string $proPrompt The value to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return $this|ChildPropertyQuery The current query, for fluid interface
     */
    public function filterByProPrompt($proPrompt = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($proPrompt)) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(PropertyTableMap::COL_PRO_PROMPT, $proPrompt, $comparison);
    }

    /**
     * Filter the query by a related \ChurchCRM\PropertyType object
     *
     * @param \ChurchCRM\PropertyType|ObjectCollection $propertyType The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return ChildPropertyQuery The current query, for fluid interface
     */
    public function filterByPropertyType($propertyType, $comparison = null)
    {
        if ($propertyType instanceof \ChurchCRM\PropertyType) {
            return $this
                ->addUsingAlias(PropertyTableMap::COL_PRO_PRT_ID, $propertyType->getPrtId(), $comparison);
        } elseif ($propertyType instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(PropertyTableMap::COL_PRO_PRT_ID, $propertyType->toKeyValue('PrimaryKey', 'PrtId'), $comparison);
        } else {
            throw new PropelException('filterByPropertyType() only accepts arguments of type \ChurchCRM\PropertyType or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the PropertyType relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return $this|ChildPropertyQuery The current query, for fluid interface
     */
    public function joinPropertyType($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('PropertyType');

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
            $this->addJoinObject($join, 'PropertyType');
        }

        return $this;
    }

    /**
     * Use the PropertyType relation PropertyType object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return \ChurchCRM\PropertyTypeQuery A secondary query class using the current class as primary query
     */
    public function usePropertyTypeQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinPropertyType($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'PropertyType', '\ChurchCRM\PropertyTypeQuery');
    }

    /**
     * Filter the query by a related \ChurchCRM\PersonProperty object
     *
     * @param \ChurchCRM\PersonProperty|ObjectCollection $personProperty the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildPropertyQuery The current query, for fluid interface
     */
    public function filterByPersonProperty($personProperty, $comparison = null)
    {
        if ($personProperty instanceof \ChurchCRM\PersonProperty) {
            return $this
                ->addUsingAlias(PropertyTableMap::COL_PRO_ID, $personProperty->getPropertyId(), $comparison);
        } elseif ($personProperty instanceof ObjectCollection) {
            return $this
                ->usePersonPropertyQuery()
                ->filterByPrimaryKeys($personProperty->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByPersonProperty() only accepts arguments of type \ChurchCRM\PersonProperty or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the PersonProperty relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return $this|ChildPropertyQuery The current query, for fluid interface
     */
    public function joinPersonProperty($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('PersonProperty');

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
            $this->addJoinObject($join, 'PersonProperty');
        }

        return $this;
    }

    /**
     * Use the PersonProperty relation PersonProperty object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return \ChurchCRM\PersonPropertyQuery A secondary query class using the current class as primary query
     */
    public function usePersonPropertyQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinPersonProperty($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'PersonProperty', '\ChurchCRM\PersonPropertyQuery');
    }

    /**
     * Filter the query by a related Person object
     * using the record2property_r2p table as cross reference
     *
     * @param Person $person the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildPropertyQuery The current query, for fluid interface
     */
    public function filterByPerson($person, $comparison = Criteria::EQUAL)
    {
        return $this
            ->usePersonPropertyQuery()
            ->filterByPerson($person, $comparison)
            ->endUse();
    }

    /**
     * Exclude object from result
     *
     * @param   ChildProperty $property Object to remove from the list of results
     *
     * @return $this|ChildPropertyQuery The current query, for fluid interface
     */
    public function prune($property = null)
    {
        if ($property) {
            $this->addUsingAlias(PropertyTableMap::COL_PRO_ID, $property->getProId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the property_pro table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(PropertyTableMap::DATABASE_NAME);
        }

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con) {
            $affectedRows = 0; // initialize var to track total num of affected rows
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            PropertyTableMap::clearInstancePool();
            PropertyTableMap::clearRelatedInstancePool();

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
            $con = Propel::getServiceContainer()->getWriteConnection(PropertyTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(PropertyTableMap::DATABASE_NAME);

        // use transaction because $criteria could contain info
        // for more than one table or we could emulating ON DELETE CASCADE, etc.
        return $con->transaction(function () use ($con, $criteria) {
            $affectedRows = 0; // initialize var to track total num of affected rows

            PropertyTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            PropertyTableMap::clearRelatedInstancePool();

            return $affectedRows;
        });
    }

} // PropertyQuery
