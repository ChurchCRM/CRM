# Skill: Database Operations with Perpl ORM

## Context
This skill covers all database access patterns using Perpl ORM (actively maintained fork of Propel2).

## Core Rules
- ALWAYS use Perpl ORM Query classes (perplorm/perpl - fork of Propel2)
- NEVER use raw SQL or RunQuery()
- Cast dynamic IDs to (int)
- Check `=== null` not `empty()` for objects
- Access properties as objects: `$obj->prop`, never `$obj['prop']`

## Perpl ORM Critical Differences from Propel

ChurchCRM uses **Perpl ORM** (`perplorm/perpl`), an actively maintained fork of Propel2 with PHP 8.4+ support and 30-50% faster query building. All Propel patterns still apply, but note these **critical differences**:

### withColumn() - Use TableMap Constants (REQUIRED)

In Perpl ORM, `withColumn()` requires **actual database column names**, not Propel phpNames. **Always use TableMap constants** for type safety and IDE support:

```php
use ChurchCRM\model\ChurchCRM\Map\PledgeTableMap;
use ChurchCRM\model\ChurchCRM\Map\FamilyTableMap;
use ChurchCRM\model\ChurchCRM\Map\DonationFundTableMap;
use ChurchCRM\model\ChurchCRM\Map\DepositTableMap;

// ✅ CORRECT - Use TableMap constants (REQUIRED)
$query->withColumn('SUM(' . PledgeTableMap::COL_PLG_AMOUNT . ')', 'totalAmount');
$query->withColumn(FamilyTableMap::COL_FAM_NAME, 'FamilyName');
$query->withColumn(DonationFundTableMap::COL_FUN_NAME, 'FundName');
$query->withColumn(DepositTableMap::COL_DEP_DATE, 'DepositDate');

// ❌ WRONG - phpNames don't work in withColumn()
$query->withColumn('Family.Name', 'FamilyName');
$query->withColumn('SUM(Pledge.Amount)', 'totalAmount');
```

**Common TableMap Constants:**

| TableMap Class | Constant | Resolves To |
|----------------|----------|-------------|
| `FamilyTableMap` | `COL_FAM_NAME` | `family_fam.fam_Name` |
| `FamilyTableMap` | `COL_FAM_ADDRESS1` | `family_fam.fam_Address1` |
| `PledgeTableMap` | `COL_PLG_AMOUNT` | `pledge_plg.plg_amount` |
| `PledgeTableMap` | `COL_PLG_PLGID` | `pledge_plg.plg_plgID` |
| `PledgeTableMap` | `COL_PLG_DEPID` | `pledge_plg.plg_depID` |
| `DonationFundTableMap` | `COL_FUN_NAME` | `donationfund_fun.fun_Name` |
| `DepositTableMap` | `COL_DEP_ID` | `deposit_dep.dep_ID` |
| `DepositTableMap` | `COL_DEP_DATE` | `deposit_dep.dep_Date` |
| `ListOptionTableMap` | `COL_LST_ID` | `list_lst.lst_ID` |
| `Person2group2roleP2g2rTableMap` | `COL_P2G2R_PER_ID` | `person2group2role_p2g2r.p2g2r_per_ID` |

### addForeignValueCondition() - Column Name Only (CRITICAL)

**WARNING:** The `addForeignValueCondition()` method expects **table name and column name separately**, NOT TableMap COL_ constants:

```php
use ChurchCRM\model\ChurchCRM\Map\ListOptionTableMap;

// ✅ CORRECT - Use TABLE_NAME constant + column name string
$join->addForeignValueCondition(ListOptionTableMap::TABLE_NAME, 'lst_ID', '', 3, self::EQUAL);

// ❌ WRONG - COL_ constant includes table prefix, causing duplicate
$join->addForeignValueCondition('list_lst', ListOptionTableMap::COL_LST_ID, '', 3, self::EQUAL);
// This generates: list_lst.list_lst.lst_ID (BROKEN!)
```

**Why this matters:** `ListOptionTableMap::COL_LST_ID` resolves to `'list_lst.lst_ID'` (includes table prefix). When passed to `addForeignValueCondition()` which already adds the table name, you get `list_lst.list_lst.lst_ID` causing SQL errors.

### Method Override Signatures (Strict Types Required)

Perpl ORM enforces strict return types. When overriding base methods:

```php
// ❌ WRONG - Missing return types
public function toArray($keyType = TableMap::TYPE_PHPNAME, $includeLazyLoadColumns = true, $alreadyDumpedObjects = [], $includeForeignObjects = false)

// ✅ CORRECT - Full signature with types
public function toArray(string $keyType = TableMap::TYPE_PHPNAME, bool $includeLazyLoadColumns = true, array $alreadyDumpedObjects = [], bool $includeForeignObjects = false): array
```

**Lifecycle Hooks** - must match base class signatures:

```php
// Pre-hooks return bool
public function preSave(ConnectionInterface $con = null): bool
public function preInsert(ConnectionInterface $con = null): bool
public function preUpdate(ConnectionInterface $con = null): bool
public function preDelete(ConnectionInterface $con = null): bool

// Post-hooks return void
public function postSave(ConnectionInterface $con = null): void
public function postInsert(ConnectionInterface $con = null): void
public function postUpdate(ConnectionInterface $con = null): void
public function postDelete(ConnectionInterface $con = null): void
```

### preSelect Hook Signature

```php
public function preSelect(ConnectionInterface $con): void
{
    // Custom query modifications here
    parent::preSelect($con);
}
```

## Propel ORM Method Naming (CRITICAL)

**NEVER guess ORM method names.** Propel uses strict column-to-method mapping. **Always check the Query class documentation comments** to verify exact method names before writing code.

### Method Naming Pattern

Propel converts database column names to PHP method names using **phpName** (derived from table/column structure):

| Database Column | Query Method | Accessor Method | Mutator Method |
|---|---|---|---|
| `custom_order` (renamed to `Order` in phpName) | `orderByOrder()`, `filterByOrder()` | `getOrder()` | `setOrder()` |
| `custom_field` (renamed to `Id` in phpName) | `findOneById()`, `filterById()` | `getId()` | `setId()` |
| `custom_name` (renamed to `Name` in phpName) | `orderByName()`, `filterByName()` | `getName()` | `setName()` |
| `lst_ID` (renamed to `Id` in phpName) | `findOneById()`, `orderById()` | `getId()` | `setId()` |
| `lst_OptionID` (renamed to `OptionId`) | `filterByOptionId()` | `getOptionId()` | `setOptionId()` |
| `type_ID` (renamed to `TypeId` in phpName) | `filterByTypeId()` | `getTypeId()` | `setTypeId()` |

### How to Find Correct Method Names

1. **Check the Base Query class** at `src/ChurchCRM/model/ChurchCRM/Base/*Query.php`
2. **Look for `@method` PHPDoc comments** at the top of the class - they list ALL available query methods
3. **Common patterns to look for:**
   - `findOneById()` - Find by primary key
   - `findOneBy[ColumnName]()` - Find by any column
   - `filterBy[ColumnName]()` - Add WHERE condition
   - `orderBy[ColumnName]()` - Add ORDER BY
   - `groupBy[ColumnName]()` - Add GROUP BY

### Example: PersonCustomMasterQuery

```php
// Check Base/PersonCustomMasterQuery.php for @method comments:
// @method     ChildPersonCustomMasterQuery orderByOrder($order = Criteria::ASC)
// @method     ChildPersonCustomMasterQuery orderByName($order = Criteria::ASC)
// @method     ChildPersonCustomMaster|null findOneById(string $custom_Field)
// @method     ChildPersonCustomMaster|null findOneByName(string $custom_Name)

// CORRECT - These match the documented methods
$field = PersonCustomMasterQuery::create()
    ->findOneById($fieldName);
$fields = PersonCustomMasterQuery::create()
    ->orderByOrder()
    ->find();

// WRONG - These methods don't exist (will throw UnknownColumnException)
$field = PersonCustomMasterQuery::create()
    ->filterByCustomfield($fieldName);  // ❌ Should be findOneById()
$fields = PersonCustomMasterQuery::create()
    ->filterByCustomorder(1)  // ❌ Should be filterByOrder()
    ->find();
```

### Common Mistakes to Avoid

- ❌ `filterByCustomField()` → Use `findOneById()` for primary key lookups
- ❌ `filterByLstId()` → Use `findOneById()` (Propel renames `lst_ID` to `Id`)
- ❌ `filterByTypeId()` used with `findOne()` instead of `findOneByTypeId()` 
- ❌ `orderByCustomOrder()` → Use `orderByOrder()` (Propel uses phpName, not database column)
- ❌ `setCustomorder()` → Use `setOrder()` (consistent with getter/setter naming)

### When Migrating from Raw SQL

**Always consult the Query class before converting SQL to ORM:**

```php
// RAW SQL (find what to convert)
$sSQL = "SELECT * FROM person_custom_master WHERE custom_Field = '" . $fieldName . "'";
$record = RunQuery($sSQL);

// CORRECT ORM (check Base Query class for method names)
$record = PersonCustomMasterQuery::create()
    ->findOneById($fieldName);  // ← primary key lookup method

// Find column mappings in Base class:
// @method ChildPersonCustomMaster|null findOneById(string $custom_Field)
// This tells us custom_Field → Id in phpName
```

## Database Access Example

```php
// CORRECT - Propel ORM
$event = EventQuery::create()->findById((int)$eventId);
if ($event === null) { 
    // Handle not found
}

// WRONG
$result = RunQuery("SELECT * FROM events WHERE eventid = ?", $eventId);
$event['eventName'];  // TypeError: Cannot access offset on object
```

## Files

**ORM Configuration:** `orm/schema.xml`, `orm/propel.php.dist`
**Generated Models:** `src/ChurchCRM/model/ChurchCRM/` (don't edit directly)
