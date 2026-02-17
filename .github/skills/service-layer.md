---
title: "Service Layer"
intent: "Guidance for creating service classes and performance best practices"
tags: ["service","performance","architecture"]
prereqs: ["php-best-practices.md","database-operations.md"]
complexity: "intermediate"
---

# Skill: Service Layer Development

## Context
This skill covers creating and using service classes for business logic in ChurchCRM.

## Service Layer First Principle

**When implementing business logic, ALWAYS create/update Service classes** in `src/ChurchCRM/Service/`

- Service methods encapsulate domain logic, database operations, and validation
- Call services from legacy pages (`src/*.php`), APIs, and modern routes
- Services use Propel ORM exclusively - **no RunQuery() or direct SQL**

## Key Services

Located in `src/ChurchCRM/Service/`:

- **PersonService** - Person/family operations
- **GroupService** - Group management
- **FinancialService** - Payments, pledges, funds
- **DepositService** - Deposit slip handling
- **SystemService** - System-wide operations
- **UserService** - User management with optimized database operations

## Example Usage

```php
// In API route or legacy page
$service = $container->get('FinancialService');
$result = $service->addPayment($fam_id, $method, $amount, $date, $funds);
return $response->withJson(['data' => $result]);
```

## Service Layer Performance Best Practices

When creating services, optimize database operations:

### 1. Selective field loading

Use `->select(['field1', 'field2'])` to fetch only required columns:

```php
// GOOD - Only fetch needed fields
$users = UserQuery::create()
    ->select(['Id', 'FailedLogins', 'TwoFactorAuthSecret'])
    ->find();

// BAD - Fetches all columns unnecessarily
$users = UserQuery::create()->find();
```

### 2. Single query philosophy

Group related data retrieval in one query, then process in PHP memory:

```php
// GOOD - One query, process in memory
$allUsers = UserQuery::create()
    ->select(['FailedLogins', 'TwoFactorAuthSecret'])
    ->find();

$lockedCount = 0;
$twoFactorCount = 0;
foreach ($allUsers as $user) {
    if ($user['FailedLogins'] >= 3) $lockedCount++;
    if (!empty($user['TwoFactorAuthSecret'])) $twoFactorCount++;
}

// BAD - Multiple queries
$lockedCount = UserQuery::create()->filterByFailedLogins(3, Criteria::GREATER_EQUAL)->count();
$twoFactorCount = UserQuery::create()->filterByTwoFactorAuthSecret(null, Criteria::ISNOTNULL)->count();
```

### 3. Avoid N+1 queries

Pre-fetch related data instead of looping with individual queries:

```php
// GOOD - Pre-fetch with join
$persons = PersonQuery::create()
    ->joinWith('Family')
    ->find();

foreach ($persons as $person) {
    echo $person->getFamily()->getName(); // No additional query
}

// BAD - N+1 queries (one per person)
$persons = PersonQuery::create()->find();
foreach ($persons as $person) {
    echo $person->getFamily()->getName(); // Additional query each iteration
}
```

### 4. Example: UserService getUserStats()

Fetches all users' `failedLogins` and `twoFactorAuthSecret` in one query, then processes statistics in memory:

```php
public function getUserStats(): array
{
    $users = UserQuery::create()
        ->select(['FailedLogins', 'TwoFactorAuthSecret'])
        ->find();
    
    $stats = [
        'total' => count($users),
        'locked' => 0,
        'twoFactorEnabled' => 0,
    ];
    
    foreach ($users as $user) {
        if ($user['FailedLogins'] >= SystemConfig::getValue('iMaxFailedLogins')) {
            $stats['locked']++;
        }
        if (!empty($user['TwoFactorAuthSecret'])) {
            $stats['twoFactorEnabled']++;
        }
    }
    
    return $stats;
}
```

## Creating API Endpoints vs Service Calls

**Only create API endpoints when:**
- Needed by external clients
- Required for AJAX operations
- Shared across multiple pages

**Do NOT create API endpoints** if a service method is only called from a legacy page:

```php
// GOOD - Call service directly from legacy page
require 'Include/Config.php';
$service = $container->get('PersonService');
$result = $service->updatePerson($personId, $data);

// BAD - Creating unnecessary API endpoint just to call service once
// Don't create /api/person/update if only used by one page
```

## Logging in Services

**Always use LoggerUtils** for business logic operations:

```php
use ChurchCRM\Utils\LoggerUtils;

class MyService
{
    private $logger;
    
    public function __construct()
    {
        $this->logger = LoggerUtils::getAppLogger();
    }
    
    public function doSomething($param): void
    {
        $this->logger->debug('Operation starting', ['param' => $param]);
        
        try {
            // Business logic here
            $this->logger->info('Operation succeeded', ['result' => $value]);
        } catch (\Exception $e) {
            $this->logger->error('Operation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
```

**Log levels:**
- `debug` - Development info, detailed execution flow
- `info` - Business events, successful operations
- `warning` - Issues that don't stop execution
- `error` - Failures, exceptions

**Always include context** as second parameter array for meaningful logs.

## SystemConfig for UI Settings Panels

**For admin pages that display system settings:**

Call `SystemConfig::getSettingsConfig($settingKeys)` to get structured configuration for a settings panel.

**Service method example:**

```php
public function getUserSettingsConfig(): array 
{
    $userSettings = [
        'iSessionTimeout',
        'iMaxFailedLogins',
        'bEnableLostPassword'
    ];
    return SystemConfig::getSettingsConfig($userSettings);
}
```

**Returns array with:** `category`, `name`, `value`, `type`, `options` for each setting

**Frontend usage:** Render collapsed setting panels using this structured data

**Avoid:** Hardcoding settings or creating separate SystemConfig lookups - use the service method

## Files

**Services:** `src/ChurchCRM/Service/`
**Service Container:** `src/ChurchCRM/ServiceContainerBuilder.php`
**Logger:** `src/ChurchCRM/Utils/LoggerUtils.php`
**Config:** `src/ChurchCRM/dto/SystemConfig.php`
