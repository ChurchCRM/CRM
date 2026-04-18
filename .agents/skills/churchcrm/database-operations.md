# Skill: Database Operations with Perpl ORM

## Context
This skill covers all database access patterns using Perpl ORM (actively maintained fork of Propel2).

## Core Rules
- ALWAYS use Perpl ORM Query classes (perplorm/perpl - fork of Propel2)
- NEVER use raw SQL or RunQuery()
- Cast dynamic IDs to (int)
- Check `=== null` not `empty()` for objects
- Access properties as objects: `$obj->prop`, never `$obj['prop']`
- **Legacy `mysqli_fetch_array()` / `extract()` values are always strings** — cast to `(int)` before strict comparison to int literals (see code-standards.md → "Strict vs Loose Comparisons")

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

### Person / Family postInsert Auto-Writes a Timeline Note <!-- learned: 2026-04-18 -->

`Person::postInsert()` and `Family::postInsert()` (see `src/ChurchCRM/model/ChurchCRM/Person.php:143` and `Family.php:86`) automatically call `createTimeLineNote('create')`, which writes a `Note` row with:

- `nte_Type = 'create'`
- `nte_Text = gettext('Created')`
- `nte_EnteredBy` / `nte_DateEntered` copied from the Person/Family
- attached via `nte_per_ID` or `nte_fam_ID`

**Consequence for bulk creators (importers, migrations, seeders):** do **not** manually `new Note()` with `type='create'` after saving a Person or Family — you will write a duplicate row. Either accept the default `"Created"` text, or find the auto-created note and mutate its text:

```php
$person->save($con);  // postInsert hook writes the 'create' note

$note = NoteQuery::create()
    ->filterByPerId($person->getId())
    ->filterByType('create')
    ->orderByDateEntered('DESC')
    ->findOne($con);
if ($note !== null) {
    $note->setText(gettext('Imported from CSV'));
    $note->save($con);
}
```

Note that the legacy `PersonEditor.php` still does a manual `new Note()` after save, which produces two `'create'` notes per new person. Don't copy that pattern.

Same hook also sends a new-person/family notification email when `SystemConfig::sNewPersonNotificationRecipientIDs` is non-empty — relevant if you're writing a bulk importer (each imported person triggers a mail).

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

### MySQL 8.0 Strict Mode: DATE Comparisons <!-- learned: 2026-03-02 -->

**CRITICAL:** MySQL 8.0+ strict mode rejects comparing DATE columns to empty strings (`''`), returning `SQLSTATE[HY000]: 1525 Incorrect DATE value: ''`.

```php
// ❌ WRONG - MySQL 8.0 strict mode rejects this
->filterByWeddingdate('', Criteria::NOT_EQUAL)
// Generates: WHERE wedding_date <> '' → SQL error in strict mode

// ✅ CORRECT - Use IS NOT NULL for non-empty date checks
->filterByWeddingdate(null, Criteria::ISNOTNULL)
// Generates: WHERE wedding_date IS NOT NULL → Works on all MySQL versions
```

**Why this matters:** Empty string literals are invalid for DATE type in strict mode. Use `ISNOTNULL` criteria when filtering for non-empty dates; use `null` value with `ISNOTNULL` criterion (not an empty string).

### Datetime Year-Range Filters — Use Full Timestamps <!-- learned: 2026-03-29 -->

When filtering by a year range on a DATETIME column, using `YYYY-12-31` as the `max` boundary silently excludes events after midnight on Dec 31 (e.g., `2025-12-31 14:00:00` fails `<= '2025-12-31 00:00:00'`). Use explicit end-of-day timestamps.

```php
// ❌ WRONG — excludes Dec 31 events after midnight
->filterByStart(['min' => $year . '-01-01', 'max' => $year . '-12-31'])

// ✅ CORRECT — full year inclusive
$yearMin = $year . '-01-01 00:00:00';
$yearMax = $year . '-12-31 23:59:59';
->filterByStart(['min' => $yearMin, 'max' => $yearMax])
```

This applies to any DATETIME column used as an annual boundary filter.

### ObjectCollection Must Be Converted to Array for API Responses <!-- learned: 2026-03-03 -->

`SlimUtils::renderJSON(array $obj)` requires a plain PHP array. Passing a Propel `ObjectCollection` directly causes a `TypeError` (HTTP 500) in PHP 8.4. Always call `->toArray()` before returning ORM results to the API.

```php
// ❌ WRONG — ObjectCollection passed to renderJSON(array) → TypeError → HTTP 500
$events = $Calendar->getEvents($start, $end);
return SlimUtils::renderJSON($response, $events);

// ✅ CORRECT — convert to plain array first
$events = $Calendar->getEvents($start, $end);
return SlimUtils::renderJSON($response, $events->toArray());
```

This applies to any `->find()` result or relation collection returned by Perpl ORM.

### Looking Up ListOption Role Names for Group Memberships <!-- learned: 2026-03-30 -->

`Group` has no `getListOptionById()` method. To resolve a role name from a group membership, query `ListOptionQuery` with both the group's role list ID and the membership's option ID.

```php
// ❌ WRONG — method does not exist, throws BadMethodCallException
$roleList = $group->getListOptionById($membership->getRoleId());

// ✅ CORRECT
$roleList = ListOptionQuery::create()
    ->filterById($group->getRoleListId())       // which list (the group's role list)
    ->filterByOptionId($membership->getRoleId()) // which option within that list
    ->findOne();
if ($roleList !== null) {
    $roleName = $roleList->getOptionName();
}
```

### Propel Reverse Relation Query Methods — Named After Relation, Not Model <!-- learned: 2026-03-30 -->

When Propel generates query methods for a **reverse** (one-to-many) relation, the method is named after the *relation alias in the schema*, not the foreign model class. For example, `Event` has a FK to `EventType` with the relation alias `"EventType"`. From `EventTypeQuery` (the "one" side), the generated reverse join method is `useEventTypeQuery()` — NOT `useEventQuery()`.

```php
// ❌ WRONG — throws "Undefined method Criteria::useEventQuery()"
EventTypeQuery::create()
    ->useEventQuery()
    ->endUse()

// ✅ CORRECT — use the relation alias name; method returns EventQuery internally
EventTypeQuery::create()
    ->useEventTypeQuery()
    ->endUse()
    ->distinct()
    ->find();
```

When unsure of the method name, grep `public function use.*Query` in `Base/XxxQuery.php` to see all generated relation methods and their return types.

### People Without Families — Always Use leftJoinWithFamily() <!-- learned: 2026-03-29 -->

Not every `Person` has a family (`per_fam_ID = 0` is valid). Using `joinWithFamily()` (inner join) silently excludes these people from results. **Always use `leftJoinWithFamily()`** when querying persons and family data together.

When filtering out deactivated families, do it at the SQL level — `Family.DateDeactivated IS NULL` correctly handles all three cases with a LEFT JOIN:
- Person with no family → `Family.DateDeactivated` is `NULL` (LEFT JOIN null row) → included ✓
- Person in active family → `DateDeactivated` is `NULL` → included ✓
- Person in deactivated family → `DateDeactivated` is set → excluded ✓

```php
// ❌ WRONG — excludes people with no family (per_fam_ID = 0)
PersonQuery::create()->joinWithFamily()->find();

// ✅ CORRECT — includes everyone; filters deactivated families at SQL level
PersonQuery::create()
    ->leftJoinWithFamily()
    ->where('Family.DateDeactivated IS NULL')  // NULL family rows pass through correctly
    ->find();
```

### Propel Temporal Getters Return DateTime Objects <!-- learned: 2026-04-03 -->

Propel columns of type `TIMESTAMP`, `DATE`, or `TIME` return `DateTime` objects from
their getters, not strings. This causes `TypeError` if you pass them to string functions
like `DateTime::createFromFormat()`, `mb_substr()`, or string concatenation.

```php
// ❌ WRONG — getDefStartTime() returns DateTime, not string
$startTime = $eventType->getDefStartTime();
$dateTime = DateTime::createFromFormat('H:i:s', $startTime); // TypeError!

// ❌ WRONG — getDefRecurDOY() returns DateTime, mb_substr expects string
$doy = $eventType->getDefRecurDOY();
$monthDay = mb_substr($doy, 5); // TypeError!

// ✅ CORRECT — pass format string to get a string back
$startTime = $eventType->getDefStartTime('H:i:s');

// ✅ CORRECT — pass format to temporal getter
$doy = $eventType->getDefRecurDOY('Y-m-d');
$monthDay = mb_substr($doy, 5); // "MM-DD"

// ✅ CORRECT — check instanceof when format may vary
$startTime = $eventType->getDefStartTime();
if ($startTime instanceof \DateTime) {
    $display = $startTime->format('g:i A');
} elseif (is_string($startTime) && $startTime !== '') {
    $display = $startTime;
} else {
    $display = '';
}
```

**Rule:** When converting raw SQL (`extract()` / `mysqli_fetch_array`) to ORM, always
check the Base model's getter signature. Temporal columns accept an optional `$format`
parameter — pass it to get a string, or handle the `DateTime` object explicitly.

### Null Guards on ORM findOne Results <!-- learned: 2026-04-03 -->

`findOneByXxx()` and `findOne()` return `null` when no row matches. Always guard
before calling methods on the result, especially when IDs come from user input.

```php
// ❌ WRONG — fatal error if ID doesn't exist
$propertyType = PropertyTypeQuery::create()->findOneByPrtId($id);
$propertyType->setPrtName($name); // TypeError if null

// ✅ CORRECT — null guard with redirect
$propertyType = PropertyTypeQuery::create()->findOneByPrtId($id);
if ($propertyType === null) {
    RedirectUtils::redirect('PropertyTypeList.php');
}
$propertyType->setPrtName($name);
```

### DDL Statements Cannot Use ORM <!-- learned: 2026-04-03 -->

`ALTER TABLE`, `CREATE TABLE`, and `DROP` statements cannot be parameterized or
expressed through Propel ORM. When converting raw SQL to ORM, DDL must remain as
`RunQuery()` calls. Protect interpolated values with:

1. `(int)` cast for table name suffixes (e.g., `groupprop_` . (int)$groupId)
2. Regex validation for column names (e.g., `/^c\d+$/` for custom field columns)

```php
// Column names in ChurchCRM custom field tables follow pattern: c1, c2, c3, ...
if (!preg_match('/^c\d+$/', $sField)) {
    RedirectUtils::redirect('PersonCustomFieldsEditor.php');
    exit;
}
// Now safe to use in DDL
$sSQL = 'ALTER TABLE `person_custom` DROP IF EXISTS `' . $sField . '`';
RunQuery($sSQL);
```

### Timeline Note Types for Event Check-in/out <!-- learned: 2026-04-07 -->

The `Note` model supports a `type` field for filtering. Existing types: `note`, `create`, `edit`, `verify`, `group`, `photo`, `user`, `cal`. The `event` type is used for check-in/out timeline entries. Use `setType('event')` and add the type to `TimelineService::createTimeLineItem()` switch for icon/color mapping.

```php
$note = new Note();
$note->setPerId($personId);
$note->setFamId(0);
$note->setText(sprintf(gettext('Checked in to event: %s'), $event->getTitle()));
$note->setType('event');
$note->setPrivate(0);
$note->setEntered($currentUserId);
$note->save();
```

### MySQL ENUM Columns Need Exact String Values <!-- learned: 2026-04-03 -->

When a MySQL column uses `ENUM('Sunday','Monday',...)`, Propel setters require the
exact enum string. HTML forms that post numeric values (1-7) must be mapped before
calling the setter — MySQL's silent coercion of numbers to enum positions doesn't
work through ORM.

```php
// ❌ WRONG — form posts "1", but column is ENUM('Sunday','Monday',...)
$eventType->setDefRecurDOW($_POST['newEvtRecurDOW']); // Propel may reject "1"

// ✅ CORRECT — map numeric form value to enum string
$dayOfWeekMap = [
    '1' => 'Sunday', '2' => 'Monday', '3' => 'Tuesday',
    '4' => 'Wednesday', '5' => 'Thursday', '6' => 'Friday', '7' => 'Saturday',
];
$dowValue = $_POST['newEvtRecurDOW'];
if (isset($dayOfWeekMap[$dowValue])) {
    $dowValue = $dayOfWeekMap[$dowValue];
}
$eventType->setDefRecurDOW($dowValue);
```

### Replacing INSERT...ON DUPLICATE KEY UPDATE with findPk + Create-If-Missing <!-- learned: 2026-04-08 -->

Propel has no direct equivalent to `INSERT ... ON DUPLICATE KEY UPDATE`. When migrating raw
SQL upserts to ORM, use `findPk()` with the composite primary key and create the record if
missing — do not hunt for a single "upsert" method.

```php
// ❌ Raw SQL
// INSERT eventcounts_evtcnt (evtcnt_eventid, evtcnt_countid, evtcnt_countcount)
// VALUES (1, 2, 5)
// ON DUPLICATE KEY UPDATE evtcnt_countcount=5;

// ✅ Propel ORM (works with composite primary keys)
$eventCount = EventCountsQuery::create()->findPk([$eventId, $countId]);
if ($eventCount === null) {
    $eventCount = new EventCounts();
    $eventCount->setEvtcntEventid($eventId);
    $eventCount->setEvtcntCountid($countId);
}
$eventCount->setEvtcntCountcount($value);
$eventCount->save();
```

`findPk()` accepts an array for composite keys in the exact order declared in the schema.

### Replacing MONTH() / YEAR() SQL with Propel Date Range <!-- learned: 2026-04-08 -->

Don't try to translate `WHERE MONTH(date) = 4 AND YEAR(date) = 2026` directly — Propel has
no helper for SQL date functions. Use a full date-range filter with `cal_days_in_month()` for
the inclusive upper bound.

```php
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$monthMin = sprintf('%04d-%02d-01 00:00:00', $year, $month);
$monthMax = sprintf('%04d-%02d-%02d 23:59:59', $year, $month, $daysInMonth);

EventQuery::create()
    ->filterByStart(['min' => $monthMin, 'max' => $monthMax])
    ->find();
```

Always use full `HH:MM:SS` timestamps on the boundaries — see "Datetime Year-Range Filters"
above for why a bare `YYYY-MM-DD` max silently drops same-day afternoon records.
