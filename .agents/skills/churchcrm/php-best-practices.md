---
title: "PHP Best Practices"
intent: "Project-wide PHP conventions, Perpl ORM rules, and service-layer patterns"
tags: ["php","perpl","service-layer","security"]
prereqs: []
complexity: "intermediate"
---

# Skill: PHP Best Practices

PHP 8.4+ (versions: `src/composer.json`). General rules: `code-standards.md`.

## Language Standards

- Nullable parameters: `?int $param = null`
- Imports: `use` statements at the top of the file, alphabetical; reference classes by short name
- Shared values: `public const PHOTO_WIDTH = 200;`
- Dates: `IntlDateFormatter`
- Dynamic properties: declare them, or add `#[\AllowDynamicProperties]`
- mbstring functions default to UTF-8; no `mb_internal_encoding()` call is needed
- Prefix `\` only on global functions called from namespaced code, e.g. the Quill helpers below

```php
<?php
namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;
use Slim\Exception\HttpNotFoundException;

class MyService
{
    public function test(): string
    {
        $path = SystemURLs::getRootPath();
        throw new HttpNotFoundException($request);
    }
}
```

## Helper Classes

Shared helpers are static methods on `ChurchCRM\Utils\*` classes. Read the class to find the method.

| Need | Class |
|------|-------|
| Dates | `DateTimeUtils` |
| Names, addresses, email checks, fonts | `MiscUtils` |
| Fiscal year strings | `FinancialService::formatFiscalYear()`, `FiscalYearUtils` |
| Custom field validate/display/form/SQL | `CustomFieldUtils` |
| Input filtering and output escaping | `InputUtils` |
| Redirects | `RedirectUtils` |
| Paths (containment, safe require) | `PathUtils` (see `security-best-practices.md`) |
| DDL execution | `FunctionsUtils::runQuery()` |

```php
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\MiscUtils;

$dob  = DateTimeUtils::formatDate($person->getBirthDate());
$name = MiscUtils::formatFullName($title, $first, $middle, $last, $suffix, $style);
```

Utility classes live in `src/ChurchCRM/Utils/` under the namespace `ChurchCRM\Utils`. Run `composer dump-autoload` (or `npm run build:php`) after adding a class.

### Quill Editor Helpers

`src/Include/QuillEditorHelper.php` defines global functions. Call them with a `\` prefix from namespaced code: `\getQuillEditorContainer()`, `\getQuillEditorInitScript()`, `\getQuillEditorContent()`.

## Perpl ORM

Database access uses Perpl ORM (`perplorm/perpl`, a Propel2 fork). Use Query classes for every read and write.

```php
$person = PersonQuery::create()->findOneById((int)$personId);

$people = PersonQuery::create()
    ->filterByActive(true)
    ->orderByLastName()
    ->find();
```

- Method names: read the `@method` PHPDoc in `src/ChurchCRM/model/ChurchCRM/Base/*Query.php`. A missing method throws `UnknownColumnException`
- `withColumn()`: use TableMap constants

```php
use ChurchCRM\model\ChurchCRM\Map\PledgeTableMap;

$query->withColumn('SUM(' . PledgeTableMap::COL_PLG_AMOUNT . ')', 'totalAmount');
```

- Missing record: `if ($person === null)`. `empty()` also matches `0` and `"0"`
- Read values with getters (`$person->getFirstName()`); ORM objects are not arrays
- Cast request IDs: `(int)$_GET['personId']` or `InputUtils::filterInt()`

## Service Layer

Business logic lives in `src/ChurchCRM/Service/`. HTTP handlers call services; services throw exceptions and log. See `service-layer.md`.

```php
class FamilyService
{
    private LoggerUtils $logger;

    public function __construct()
    {
        $this->logger = LoggerUtils::getAppLogger();
    }

    public function getFamilyData(int $familyId): array
    {
        $family = FamilyQuery::create()->findOneById($familyId);
        if ($family === null) {
            throw new InvalidArgumentException("Family $familyId not found");
        }
        $people = PersonQuery::create()->filterByFamId($familyId)->orderByMemberOrder()->find();

        return ['family' => $family, 'people' => $people];
    }
}
```

### Query Count

Fetch related data in one query and group it in PHP memory. Pass an array of IDs to `filterByUserId()` instead of querying inside a loop.

```php
$users = UserQuery::create()->select(['id', 'username', 'failedLogins', 'lastLogin'])->find();

$userIds = $users->getColumnValues('id');
$logs = UserLogQuery::create()->filterByUserId($userIds)->find();
```

### Collection Matching

Match items between collections with a hash set (O(N+M)).

```php
$localIdSet = array_flip(array_column($localPeople, 'id'));
$remoteOnly = array_filter($remotePeople, fn($p) => !isset($localIdSet[$p['id']]));
```

## Authorization

```php
use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\RedirectUtils;

$user = AuthenticationManager::getCurrentUser();

if (!$user->isEditRecordsEnabled()) {
    RedirectUtils::securityRedirect('EditRecords');
}

if (!$user->canEditPerson($personId, $familyId)) {
    RedirectUtils::securityRedirect('PropertyAssign');
}
```

## Input and Output (`InputUtils`)

```php
$firstName   = InputUtils::sanitizeText($_POST['firstName']);      // strips all HTML
$description = InputUtils::sanitizeHTML($_POST['eventDescription']); // keeps safe tags

<?= InputUtils::escapeHTML($person->getFirstName()) ?>
<input value="<?= InputUtils::escapeAttribute($address) ?>">
```

`InputUtils::sanitizeAndEscapeText()` combines both steps.

## Redirects (`RedirectUtils`)

```php
RedirectUtils::redirect('v2/dashboard');                 // root path added automatically
RedirectUtils::securityRedirect('EditRecords');          // logs, redirects to access-denied
RedirectUtils::absoluteRedirect('https://example.com');
```

## File Inclusion

Use `require` for `Header.php` / `Footer.php` so a missing file fails loudly:

```php
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
```

Build paths from `SystemURLs::getRootPath()` / `getDocumentRoot()`.

## Error Handling

Services throw exceptions. API routes catch them and answer with `SlimUtils::renderErrorJSON()` so details stay server-side.

```php
use ChurchCRM\Slim\SlimUtils;

$app->post('/users', function (Request $request, Response $response): Response {
    try {
        $user = $this->get('UserService')->createUser($request->getParsedBody());

        return SlimUtils::renderJSON($response, ['data' => ['id' => $user->getId()]]);
    } catch (InvalidArgumentException $e) {
        return SlimUtils::renderErrorJSON($response, gettext('Validation failed'), [], 400, $e, $request);
    } catch (Throwable $e) {
        return SlimUtils::renderErrorJSON($response, gettext('Operation failed'), [], 500, $e, $request);
    }
});
```

A failed side effect such as `mail()` is logged as a warning and the API call still returns its result.

## Logging

Use `LoggerUtils::getAppLogger()` in business logic. Pass context as an array.

- `debug()` variable values
- `info()` business events
- `warning()` unusual situations
- `error()` failures, with `'exception' => $e` when available
- `critical()` system-critical failures

## Null Safety

```php
echo $notification?->title ?? 'No Title';
$status = $user->getProfile()?->getStatus() ?? 'Unknown';
```

## Outbound Email

Every transactional email extends `ChurchCRM\Emails\BaseEmail` (`src/ChurchCRM/Emails/`). It applies the SMTP settings and sets `From` to the church address (`ChurchMetaData::getChurchEmail()`), keeping SPF/DKIM aligned for every message.

To route replies to the person who triggered the mail, call `setReplyTo()`:

```php
$email = new VolunteerAssignmentEmail([$volunteer->getEmail()]);
$email->setReplyTo($coordinator->getEmail(), $coordinator->getFullName());
if (!$email->send()) {
    LoggerUtils::getAppLogger()->warning('Assignment email failed', ['error' => $email->getError()]);
}
```

- Opt-in: an email without `setReplyTo()` carries no `Reply-To` header
- Applied in `send()`; the last `setReplyTo()` call wins and the message carries one header
- An invalid address is logged and ignored; `getReplyTo()` returns `null` then

Local verification: Mailpit (`http://localhost:8025`, compose `test` profile) lists messages with a `ReplyTo` array via `curl -sS 'http://localhost:8025/api/v1/messages?limit=50'`. The `ci-root` / `ci-subdir` profiles have no SMTP capture, so header assertions belong in PHPUnit or manual checks.

## TLS Verification

HTTPS requests verify certificates. Disable verification only through an explicit opt-in for a configured local-network server.

```php
public function sendRequest(string $url, bool $allowSelfSigned = false): void
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$allowSelfSigned);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $allowSelfSigned ? 0 : 2);
    curl_exec($ch);
}
```

## Checklist

- ORM Query classes for data access
- `use` statements for every external class
- `InputUtils` for HTML/text, `RedirectUtils` for redirects
- Business logic in services; API errors via `SlimUtils::renderErrorJSON()`
- Type hints on parameters and returns; `=== null` checks before property access
- Request IDs cast to `(int)`
- `require` for Header.php / Footer.php; paths from `SystemURLs`

## Related Skills

- [Slim 4 Best Practices](./slim-4-best-practices.md)
- [Database Operations](./database-operations.md)
- [Authorization & Security](./authorization-security.md)
- [Service Layer](./service-layer.md)
- [Code Standards](./code-standards.md)
