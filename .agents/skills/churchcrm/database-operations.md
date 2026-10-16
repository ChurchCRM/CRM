---
title: "Database Operations"
intent: "Core patterns for database access using Perpl ORM (actively maintained fork of Propel2)"
tags: ["database", "perpl", "orm", "php"]
prereqs: ["[[php-best-practices]]"]
complexity: "beginner"
---
# Skill: Database Operations with Perpl ORM

Database access uses Perpl ORM Query classes (`perplorm/perpl`, a Propel2 fork). Propel patterns apply. Basic rules (null checks, getters, `(int)` casts, TableMap constants in `withColumn()`) are in `php-best-practices.md`.

## Core Rules

- Query classes for every read and write
- Cast dynamic IDs to `(int)`
- `=== null` for missing objects
- Properties through getters: `$obj->getName()`
- `mysqli_fetch_array()` / `extract()` values are strings: cast to `(int)` before strict comparison (`code-standards.md` → "Strict vs Loose Comparisons")

## Perpl Specifics

### withColumn() takes column names

Pass TableMap constants. Common ones: `FamilyTableMap::COL_FAM_NAME`, `PledgeTableMap::COL_PLG_AMOUNT`, `DonationFundTableMap::COL_FUN_NAME`, `DepositTableMap::COL_DEP_DATE`.

```php
$query->withColumn('SUM(' . PledgeTableMap::COL_PLG_AMOUNT . ')', 'totalAmount');
```

### addForeignValueCondition() takes table and column separately

`COL_*` constants include the table prefix, so pass `TABLE_NAME` plus the bare column string:

```php
$join->addForeignValueCondition(ListOptionTableMap::TABLE_NAME, 'lst_ID', '', 3, self::EQUAL);
```

### Method override signatures

Overrides match the base signatures, with return types:

```php
public function toArray(string $keyType = TableMap::TYPE_PHPNAME, bool $includeLazyLoadColumns = true, array $alreadyDumpedObjects = [], bool $includeForeignObjects = false): array

public function preSave(?ConnectionInterface $con = null): bool      // preInsert, preUpdate, preDelete likewise
public function postSave(?ConnectionInterface $con = null): void     // postInsert, postUpdate, postDelete likewise
public function preSelect(ConnectionInterface $con): void
```

## Person / Family Model Hooks

### Timeline notes

`Person::postInsert()` / `postUpdate()` and `Family::postInsert()` / `postUpdate()` call `createTimeLineNote('create'|'edit')`. They are the only place that writes `create`/`edit` notes (`nte_Text` = "Created"/"Updated"). Editors and services save the record and leave the note to the hook.

When an action writes its own note and then saves to bump `DateLastEdited`, wrap the save in the private `$skipPostUpdateNote` flag (see `Family::setImageFromBase64()`):

```php
$this->skipPostUpdateNote = true;
try {
    $this->save();
} finally {
    $this->skipPostUpdateNote = false;
}
```

Bulk creators (importers, seeders) accept the default "Created" text, or save and then edit the `create` note:

```php
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

The hook also calls `NewPersonOrFamilyEmail::sendIfConfigured()` when `SystemConfig::sNewPersonNotificationRecipientIDs` is set, so each imported person or family sends a mail. Creating a family with N members sends one family-level email.

Other note types: `note`, `create`, `edit`, `verify`, `group`, `photo`, `user`, `cal`, `event`. A new type needs a case in `TimelineService::createTimeLineItem()` for icon and color.

### Photos

`Person::preDelete()` and `Family::preDelete()` call `$this->getPhoto()->delete()`, which removes the uploaded image on every delete path. Controllers call `$person->delete()` and the hook handles the file. `deletePhoto()` serves the UI "delete photo only" action (permission-gated, writes an audit note).

## Method Naming

Query, getter, and setter names derive from the schema `phpName`, not the DB column name. Read the `@method` PHPDoc at the top of `src/ChurchCRM/model/ChurchCRM/Base/*Query.php`, or `orm/schema.xml`.

| Column | phpName | Methods |
|--------|---------|---------|
| `custom_Order` | `Order` | `orderByOrder()`, `getOrder()`, `setOrder()` |
| `lst_ID`, `custom_Field` (primary keys) | `Id` | `findOneById()`, `filterById()`, `getId()` |
| `lst_OptionID` | `OptionId` | `filterByOptionId()`, `getOptionId()` |
| `type_ID` | `TypeId` | `filterByTypeId()`, `findOneByTypeId()` |

Patterns: `findOneById()`, `findOneBy[Column]()`, `filterBy[Column]()`, `orderBy[Column]()`, `groupBy[Column]()`.

```php
$field  = PersonCustomMasterQuery::create()->findOneById($fieldName);
$fields = PersonCustomMasterQuery::create()->orderByOrder()->find();
```

### Reverse relation joins use the relation alias

From the "one" side, the generated join method is named after the relation alias in the schema: `EventTypeQuery::create()->useEventTypeQuery()->endUse()`. List them with `grep "public function use.*Query" Base/XxxQuery.php`.

### Role names for group memberships

`ListOptionQuery` resolves a role name from the group's role list and the membership's option ID:

```php
$roleList = ListOptionQuery::create()
    ->filterById($group->getRoleListId())
    ->filterByOptionId($membership->getRoleId())
    ->findOne();
```

### Custom master models

`PersonCustomMaster::getId()` returns the column name string (`"c3"`); build keys as `'pcustom_' . $cf->getId()`. `FamilyCustomMaster` has a numeric `getId()` and `getField()` for the column name. Confirm with `grep -nE "public function getId" src/ChurchCRM/model/ChurchCRM/Base/*CustomMaster.php`.

## Idempotent Writes: UNIQUE key + `findOneOrCreate()`

Put a `UNIQUE` key on the natural identity and call `findOneOrCreate($con)` inside the caller's transaction. The index is the guarantee; concurrent requests cannot both create the row.

```php
$con = Propel::getWriteConnection(VolunteerOccurrenceTableMap::DATABASE_NAME);
$con->beginTransaction();
try {
    $row = VolunteerOccurrenceQuery::create()
        ->filterByScheduleId($scheduleId)
        ->filterByEventId($eventId)
        ->findOneOrCreate($con);   // the connection sees the transaction's own uncommitted rows

    if ($row->isNew()) {           // the reliable "did I create it?" test
        $row->setGeneratedDate(DateTimeUtils::getNowDateTime());
        $row->save($con);
    }
    $con->commit();
} catch (\Throwable $e) {
    $con->rollBack();
    throw $e;
}
```

- `filterByCol(null)` builds `IS NULL`, and the created object inherits it. A polymorphic "exactly one parent" upsert is `->filterByScheduleId($id)->filterByOccurrenceId(null)`
- MySQL permits multiple `NULL`s in a `UNIQUE` index, so two unique keys over nullable columns dedupe independently (`volunteer_requirement_vreq`: `(schedule, position)` and `(occurrence, position)`)
- Filter on the table's own columns; a joined query throws

Call sites: `Event::checkInPerson()` (`UNIQUE(event_id, person_id)`), `VolunteerAuthorizationService::grantScope()`.

## Upsert by Composite Key

`findPk()` takes composite keys in schema order; create the record when it is missing.

```php
$eventCount = EventCountsQuery::create()->findPk([$eventId, $countId]);
if ($eventCount === null) {
    $eventCount = new EventCounts();
    $eventCount->setEvtcntEventid($eventId);
    $eventCount->setEvtcntCountid($countId);
}
$eventCount->setEvtcntCountcount($value);
$eventCount->save();
```

## Filtering Patterns

### Ranges

`Criteria` has no `BETWEEN` constant. Use two filters:

```php
->filterByOccurrenceDate($from, Criteria::GREATER_EQUAL)
->filterByOccurrenceDate($to, Criteria::LESS_EQUAL)
```

### DATE columns

MySQL 8 strict mode rejects `''` against DATE columns (`1525 Incorrect DATE value`). Filter non-empty dates with `IS NOT NULL`:

```php
->filterByWeddingdate(null, Criteria::ISNOTNULL)
```

### DATETIME boundaries

Use full timestamps on both ends so the last day is inclusive:

```php
$yearMin = $year . '-01-01 00:00:00';
$yearMax = $year . '-12-31 23:59:59';
->filterByStart(['min' => $yearMin, 'max' => $yearMax])
```

For a month, take the last day from `cal_days_in_month(CAL_GREGORIAN, $month, $year)`.

To match a month regardless of year (birthdays, anniversaries), bind the TableMap constant in a raw clause: `->where('MONTH(' . FamilyTableMap::COL_FAM_WEDDINGDATE . ') = ?', $month, \PDO::PARAM_INT)`. The phpName form does not resolve inside SQL functions.

### Raw `where()` with `OR`

Propel joins criteria with `AND` and leaves a raw `where()` string unparenthesised, so a top-level `OR` escapes every other filter (including access-control filters). Use `condition()` / `combine()`, or parenthesise the raw string:

```php
$events->condition('noEnd', EventTableMap::COL_EVENT_END . ' IS NULL');
$events->condition('endsAfter', EventTableMap::COL_EVENT_END . ' >= ?', $start);
$events->combine(['noEnd', 'endsAfter'], 'or', 'overlapsView');
$events->where(['overlapsView']);

$families->where('(' . implode(' OR ', $conditions) . ')');
```

Audit: `grep -rn --include="*.php" -e "->where(" src | grep -i " or "`.

### People and families

`per_fam_ID = 0` is valid. Use `leftJoinWithFamily()` and filter deactivated families in SQL; `Family.DateDeactivated IS NULL` passes people with no family, and people in active families.

```php
PersonQuery::create()
    ->leftJoinWithFamily()
    ->where('Family.DateDeactivated IS NULL')
    ->find();
```

### Nested joins hydrate in order

`useXxxQuery()` adds a JOIN without adding columns to SELECT. Call `joinWith*()` at the top level first so the primary table's columns come first, then nest `leftJoinWith*()`:

```php
EventAttendQuery::create()
    ->joinWithEvent(Criteria::LEFT_JOIN)
    ->useEventQuery(null, Criteria::LEFT_JOIN)
        ->leftJoinWithEventType()
        ->orderByStart(Criteria::DESC)
    ->endUse()
    ->find();
```

### Deleting through a join

`delete()` and `update()` throw on a query with a `useXxxQuery()` join. Resolve IDs through the join, then delete by `IN`:

```php
$occurrenceIds = VolunteerOccurrenceQuery::create()
    ->useScheduleQuery()->filterByMinistryId($id)->endUse()
    ->select('Id')->find()->toArray();
if ($occurrenceIds !== []) {
    VolunteerAssignmentQuery::create()
        ->filterByOccurrenceId(array_map('intval', $occurrenceIds), Criteria::IN)
        ->delete($connection);
}
```

## Object Handling

### Collections

- API responses: `SlimUtils::renderJSON()` takes an array; pass `$collection->toArray()`
- ID lists: `foreach` with `->getId()`. `array_column()` on ORM objects returns nulls/zeros

```php
$rosterIds = [];
foreach ($assignment->getActiveGroupMembers() as $member) {
    $rosterIds[] = $member->getId();
}
```

### Temporal getters

`TIMESTAMP`, `DATE`, `TIME` getters return `DateTime`. Pass a format string to get a string:

```php
$startTime = $eventType->getDefStartTime('H:i:s');
$doy = $eventType->getDefRecurDOY('Y-m-d');
```

Without a format, branch on `instanceof \DateTime`.

### Time zones

Construct and emit times with `DateTimeUtils` (`src/ChurchCRM/Utils/DateTimeUtils.php`) so they follow `sTimeZone`:

```php
$dt = DateTimeUtils::createDateTime($userInput);
$now = DateTimeUtils::getToday();

$tz = DateTimeUtils::getConfiguredTimezone();
$start = clone $event->getStart();            // setTimezone mutates in place
$json = ['start' => $start->setTimezone($tz)->format('c')];
```

Review hotspots on calendar and time changes: `new \DateTime($string)` with one argument, `strtotime()` feeding `date()`, and `->format('c')` on `TIMESTAMP` columns (hydrated without a zone).

`DateTimeUtils::parsePartialDate(string $raw)` parses user-entered partial dates (`YYYY-MM-DD`, `M/D/YYYY`, `0000-MM-DD`, `M/D`) and returns `array{month, day, year}` or `null`. Null-guard before calling setters.

### Null guards

`findOne*()` returns `null` on no match. Guard before calling methods, especially with IDs from user input:

```php
$propertyType = PropertyTypeQuery::create()->findOneByPrtId($id);
if ($propertyType === null) {
    RedirectUtils::redirect('PropertyTypeList.php');
}
```

### ENUM columns

Setters take the exact enum string. Map numeric form values first:

```php
$dayOfWeekMap = ['1' => 'Sunday', '2' => 'Monday', '3' => 'Tuesday', '4' => 'Wednesday', '5' => 'Thursday', '6' => 'Friday', '7' => 'Saturday'];
$eventType->setDefRecurDOW($dayOfWeekMap[$_POST['newEvtRecurDOW']] ?? $_POST['newEvtRecurDOW']);
```

### Update after insert in one request

`save()` on an existing row and `delete()` look up the TableMap by table name, which a `new`ed-and-INSERTed object has not registered. A second `save()` throws `TableNotFoundException`. Re-read through the Query first:

```php
$created = $this->createSchedule($ministry, $fields, $actor);
$schedule = VolunteerScheduleQuery::create()->findPk($created->getId());
$schedule->setOneOff(true);
$schedule->save();
```

## DDL

`ALTER TABLE`, `CREATE TABLE`, and `DROP` run through `FunctionsUtils::runQuery()`. Validate interpolated identifiers:

```php
if (!preg_match('/^c\d+$/', $sField)) {          // custom field columns: c1, c2, ...
    RedirectUtils::redirect('PersonCustomFieldsEditor.php');
}
FunctionsUtils::runQuery('ALTER TABLE `person_custom` DROP IF EXISTS `' . $sField . '`');
```

Table name suffixes take an `(int)` cast (`'groupprop_' . (int)$groupId`). Schema changes: `db-schema-migration.md`.

## Files

- ORM configuration: `orm/schema.xml`, `orm/propel.php.dist`
- Generated models: `src/ChurchCRM/model/ChurchCRM/`, edited through `schema.xml` and the subclass files
