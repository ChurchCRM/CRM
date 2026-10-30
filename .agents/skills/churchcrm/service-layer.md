---
title: "Service Layer"
intent: "Guidance for creating service classes and performance best practices"
tags: ["service","performance","architecture"]
prereqs: ["[[php-best-practices]]","[[database-operations]]"]
complexity: "intermediate"
---

# Skill: Service Layer Development

## Service Layer First

Business logic lives in `src/ChurchCRM/Service/`. Service methods hold domain logic, database operations, and validation, and pages, APIs, and MVC routes all call them. Services use Propel ORM only. List current services with `ls src/ChurchCRM/Service/`; the finance service is `FinancialService`.

## Obtaining a Service

Construct the service where you use it. There is no dependency-injection container; `$app->getContainer()` is `null`.

```php
use ChurchCRM\Service\FinancialService;

$group->get('/', function (Request $request, Response $response, array $args): Response {
    $financialService = new FinancialService();
    return SlimUtils::renderJSON($response, ['payments' => $financialService->getPayments()]);
});
```

Some services expose static methods only (`AppIntegrityService`, `AuthService`, `LocaleService`, `NotificationService`, `PropertyService`, `TelemetryService`, `UpgradeService`, `UpgradeAPIService`, `BirthdayEmailService`). Read the class to choose `new X()` or `X::method()`.

## Performance

1. Select only the columns you need: `->select(['Id', 'FailedLogins'])`.
2. Fetch related data in one query and aggregate in PHP:
```php
$users = UserQuery::create()->select(['FailedLogins', 'TwoFactorAuthSecret'])->find();
$stats = ['total' => count($users), 'locked' => 0, 'twoFactorEnabled' => 0];
foreach ($users as $user) {
    if ($user['FailedLogins'] >= SystemConfig::getValue('iMaxFailedLogins')) {
        $stats['locked']++;
    }
    if (!empty($user['TwoFactorAuthSecret'])) {
        $stats['twoFactorEnabled']++;
    }
}
```
3. Pre-fetch relations with `joinWith('Family')` before looping over `$person->getFamily()`.

## API Endpoints vs Service Calls

Add an API endpoint for external clients, AJAX operations, or logic shared across pages. A page used by one caller calls the service directly.

## Logging

Use `LoggerUtils::getAppLogger()` and pass context as the second argument.

```php
$this->logger = LoggerUtils::getAppLogger();
$this->logger->debug('Operation starting', ['param' => $param]);
$this->logger->info('Operation succeeded', ['result' => $value]);
$this->logger->error('Operation failed', ['error' => $e->getMessage()]);
```

Levels: `debug` execution detail, `info` business events, `warning` non-fatal issues, `error` failures.

## Settings Panels

Admin settings panels get their data from `SystemConfig::getSettingsConfig($keys)`, which returns `category`, `name`, `value`, `type`, `options` per setting. Wrap it in a service method (`getUserSettingsConfig()` in `admin-mvc-migration.md`).

## Side Effects After Model Saves

Run side effects (for example geocoding) from the save handler: check `$model->isColumnModified(TableMap::COL_NAME)`, save, then call the service.

```php
$family->save();
$family->reload();

if (
    ($family->isColumnModified(FamilyTableMap::COL_FAM_ADDRESS1)
        || $family->isColumnModified(FamilyTableMap::COL_FAM_CITY)
        || $family->isColumnModified(FamilyTableMap::COL_FAM_STATE)
        || $family->isColumnModified(FamilyTableMap::COL_FAM_ZIP))
    && empty($family->getLatitude())
) {
    (new FamilyService())->autoGeocodeFamily($family);
}
```

The service method is idempotent, logs success, skip and failure, returns `false` on failure instead of throwing, and leaves the main transaction intact.

## Time Strings

Normalize a user-supplied time before concatenating it with a date; the input may or may not carry seconds.

```php
private static function normalizeTime(string $time): string
{
    $parsed = \DateTimeImmutable::createFromFormat('H:i:s', $time)
        ?: \DateTimeImmutable::createFromFormat('H:i', $time);
    return $parsed === false ? '09:00:00' : $parsed->format('H:i:s');
}

$eventStart = $occurrenceDate->format('Y-m-d') . ' ' . self::normalizeTime($data['startTime'] ?? '09:00');
```

## User-Supplied Dates

Wrap `new \DateTime($input)` in try/catch and throw `\InvalidArgumentException`, so the API returns a 400.

```php
try {
    $rangeStart = new \DateTime($data['rangeStart'] ?? '');
    $rangeEnd = new \DateTime($data['rangeEnd'] ?? '');
} catch (\Throwable $e) {
    throw new \InvalidArgumentException(gettext('Invalid date format for rangeStart or rangeEnd'));
}
```

For strict route-level validation use `\DateTimeImmutable::createFromFormat('!Y-m-d', $input)` and check the round-trip, because `DateTime` rolls `2026-13-99` over silently.

## Bulk Generation Cap

A service method that generates rows from a date range enforces an upper bound:

```php
public const MAX_REPEAT_OCCURRENCES = 366;

if (count($occurrenceDates) > self::MAX_REPEAT_OCCURRENCES) {
    throw new \InvalidArgumentException(sprintf(
        gettext('Too many occurrences (%d) — narrow the date range. Maximum allowed: %d'),
        count($occurrenceDates),
        self::MAX_REPEAT_OCCURRENCES
    ));
}
```

## Recurring Events

One path creates recurring events:

- `RecurrenceDateGenerator::generate()` computes weekly, monthly, and yearly dates. Anything that needs the repeat dates calls it.
- `EventService::createRecurringEvents()` creates events in bulk. `createRepeatEvents()` wraps it and returns the IDs.
- `POST /api/events/repeat`, `POST /api/events/generate-recurring`, and the repeat-event editor are adapters over it.

The policy (single occurrence cap, caller title/times override event-type defaults, `skipExisting` dedups on event type and calendar date) is in the `EventService` class docblock.

## Geocoding

`GeoUtils::getLatLong($street, $city, $state, $zip, $country)` is the only entry point for geocoding; every caller (family save, person, church info, bulk action, `/api/geocoder/address`) goes through it. It asks `GeocoderChain::fromConfig()`, which reads `sGeocoderProviders` ("Nominatim" by default; "Nominatim, US Census" adds the US Census Bureau) and lazily yields a `GeocodeResult` per provider that finds the address. A time budget stops starting new providers late in a request. A provider still unavailable after its retries rests for `GeocoderChain::REST_SECONDS` (or its Retry-After, when longer) and answers unavailable at once, so a request that geocodes many addresses waits for it only once. A route that geocodes more than one address releases the session lock first (`session_write_close()`), so the user's other requests do not wait for it.

Provider applicability comes from the record's country, then `sDefaultCountry`, then `sChurchCountry`. When all are blank, `supports()` receives null and a country-limited provider declines. Pass the record's country to `getLatLong()`.

To add a provider: extend `AbstractHttpGeocoder`, implement `getName()` and `geocode()` (plus `supports()` for country-limited services), and register the lower-case name in `GeocoderChain::PROVIDERS`.

## Files

Services: `src/ChurchCRM/Service/`. Logger: `src/ChurchCRM/Utils/LoggerUtils.php`. Config: `src/ChurchCRM/dto/SystemConfig.php`.
