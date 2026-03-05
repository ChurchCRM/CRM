---
title: "PHP Best Practices"
intent: "Project-wide PHP conventions, Perpl ORM rules, and service-layer patterns"
tags: ["php","perpl","service-layer","security"]
prereqs: []
complexity: "intermediate"
---

# Skill: PHP Best Practices

## Context
ChurchCRM uses PHP 8.3+ with modern development patterns. This skill covers essential PHP practices, namespacing, ORM usage, security standards, and code organization patterns used throughout the project.

---

## PHP 8.3+ Requirements & Standards

**MANDATORY:** All code must be compatible with PHP 8.3+ and avoid deprecated patterns.

### Key Standards

- **Nullable parameters**: Use explicit syntax `?int $param = null` (not `int $param = null`)
- **Dynamic properties**: Need `#[\AllowDynamicProperties]` attribute if accessing undefined properties
- **String formatting**: Use `IntlDateFormatter` instead of deprecated `strftime()`
- **Imports**: Add `use` statements at top of file (never inline fully-qualified names)
- **Global functions**: In namespaced code, use backslash prefix: `\MakeFYString($id)`
- **Version checks**: Use `version_compare(phpversion(), '8.3.0', '<')`
- **Constants**: Use public constants for shared values: `public const PHOTO_WIDTH = 200;`

### File Structure Order

```php
<?php
// 1. Opening tag and namespace (required)
namespace ChurchCRM\Service;

// 2. All use statements (alphabetically organized)
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\InputUtils;
use Psr\Log\LoggerInterface;

// 3. Class declaration and code
class FamilyService
{
    public function __construct(
        private PersonQuery $personQuery,
        private LoggerInterface $logger
    ) {}
    
    public function getFamilyMembers(int $familyId): array
    {
        return PersonQuery::create()->findByFamId($familyId);
    }
}
```

### Import Statement Rules (CRITICAL)

❌ **WRONG - Inline fully-qualified names:**
```php
<?php
namespace ChurchCRM\Service;

class MyService {
    public function test() {
        $path = \ChurchCRM\dto\SystemURLs::getRootPath();
        throw new \Slim\Exception\HttpNotFoundException($request);
    }
}
```

✅ **CORRECT - Use imports:**
```php
<?php
namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemURLs;
use Slim\Exception\HttpNotFoundException;

class MyService {
    public function test() {
        $path = SystemURLs::getRootPath();
        throw new HttpNotFoundException($request);
    }
}
```

**Exception Rule:** Only use `\` prefix for global functions in namespaced code:
```php
namespace ChurchCRM\Service;

class MyService {
    public function getCurrencyYear() {
        return \MakeFYString(date('Y'));  // Global function - use backslash
    }
}
```

---

## Global Functions Reference

ChurchCRM defines utility functions in `src/Include/Functions.php` and `src/Include/QuillEditorHelper.php` for common operations. All require `\` prefix when called from namespaced code.

### Formatting Functions

| Function | Purpose | Example |
|----------|---------|---------|
| `\MakeFYString($iFYID)` | Format fiscal year ID as string | `echo \MakeFYString(2024);` → `"2024-2025"` |
| `\FormatDate($date, $withTime)` | Format date with i18n locale | `\FormatDate($person->getDateOfBirth(), false)` |
| `\FormatFullName($title, $first, $middle, $last, $suffix, $style)` | Format person name | `\FormatFullName($per_Title, ..., $style)` |
| `\FormatAddressLine($address, $city, $state)` | Format address single line | `\FormatAddressLine($adr_Address1, $adr_City, ...)` |
| `\FilenameToFontname($filename, $family)` | Convert filename to font name | For font path handling |

### Data Conversion Functions

| Function | Purpose | Example |
|----------|---------|---------|
| `\convertCartToString($aCartArray)` | Convert cart array to string | `$cartStr = \convertCartToString($_SESSION['aPeopleCart']);` |
| `\assembleYearMonthDay($year, $month, $day, $pasfut)` | Assemble date from components | `\assembleYearMonthDay('2024', '02', '15')` |
| `\parseAndValidateDate($data, $locale, $pasfut)` | Parse and validate date string | `$date = \parseAndValidateDate($_POST['dateField']);` |
| `\change_date_for_place_holder($string)` | Convert date for placeholder | Internal date conversion |

### Validation Functions

| Function | Purpose | Example |
|----------|---------|---------|
| `\validateCustomField($type, &$data, $colName, &$aErrors)` | Validate custom field data | `if (!\validateCustomField($type, $data, ...)) { /* handle error */ }` |
| `\checkEmail($email, $domainCheck, $verify, $returnErrors)` | Validate email address | `if (\checkEmail($email)) { /* valid */ }` |

### Custom Field Functions

| Function | Purpose | Example |
|----------|---------|---------|
| `\displayCustomField($type, $data, $special)` | Render custom field for display | Echo HTML for field value |
| `\formCustomField($type, $fieldname, $data, $special, $bFirstPass)` | Render custom field for form editing | Echo HTML form input |
| `\sqlCustomField(&$sSQL, $type, $data, $colName, $special)` | Build SQL for custom field query | Modifies $sSQL by reference |

### UI Helper Functions

| Function | Purpose | Example |
|----------|---------|---------|
| `\PrintFYIDSelect($selectName, $iFYID)` | Render fiscal year dropdown | `\PrintFYIDSelect('SelectedYear', 2024)` |
| `\generateGroupRoleEmailDropdown($roleEmails, $href)` | Render group role email dropdown | For group management pages |
| `\random_color()` | Generate random hex color | `$color = \random_color();` |

### Quill Rich Text Editor Functions

Located in `src/Include/QuillEditorHelper.php`:

| Function | Purpose |
|----------|---------|
| `\getQuillEditorContainer($editorId, $inputId, $content, $cssClasses, $minHeight)` | Render Quill editor HTML container |
| `\getQuillEditorInitScript($editorId, $inputId, $placeholder, $includeScriptTag)` | Render Quill initialization JavaScript |
| `\getQuillEditorContent($inputId)` | Extract Quill editor content from DOM |

### Deprecated Functions (AVOID)

| Function | Status | Replacement |
|----------|--------|-------------|
| `\RunQuery($sSQL, $bStopOnError)` | ❌ DEPRECATED | Use Perpl ORM Query classes instead |
| `\FindMemberClassID()` | ⚠️ Legacy | Use `Group` or `GroupQuery` with ORM |
| `\FontFromName($fontname)` | ⚠️ Internal | For font rendering only |
| `\genGroupKey(...)` | ⚠️ Internal | For group sync operations |

### Usage Pattern

Always call with backslash prefix in namespaced code:

```php
namespace ChurchCRM\Service;

use ChurchCRM\Utils\InputUtils;

class PersonService {
    public function getFormattedName($person) {
        // ✅ CORRECT - Use backslash for global function
        return \FormatFullName(
            $person->getTitle(),
            $person->getFirstName(),
            $person->getMiddleName(),
            $person->getLastName(),
            $person->getSuffix(),
            1  // style parameter
        );
    }
    
    public function getDatesAsString() {
        // ✅ CORRECT - Multiple global function calls
        $fyString = \MakeFYString(date('Y'));
        $dateString = \FormatDate(new \DateTime());
        return "$dateString (FY: $fyString)";
    }
}
```

---

## Perpl ORM (Database Access)

ChurchCRM uses **Perpl ORM** (`perplorm/perpl`), an actively maintained fork of Propel2 with PHP 8.3+ support.

### CRITICAL Rule: NEVER Use Raw SQL

```php
// ✅ CORRECT - Use Perpl ORM Query classes
$person = PersonQuery::create()
    ->findOneById((int)$personId);

$people = PersonQuery::create()
    ->filterByActive(true)
    ->orderByLastName()
    ->find();

// ❌ WRONG - Never use RunQuery() or raw SQL
$result = RunQuery("SELECT * FROM person WHERE per_ID = ?", $personId);
$person['per_FirstName'];  // TypeError: Cannot access offset on object
```

### Query Methods - Always Check Documentation

**Never guess ORM method names.** Check the Query class at `src/ChurchCRM/model/ChurchCRM/Base/*Query.php`:

```php
// Check @method PHPDoc comments for available methods:
// @method ChildPersonQuery findOneById(int $id)
// @method ChildPersonQuery filterByActive(bool $is_active)
// @method ChildPersonQuery orderByLastName($order = 'ASC')

// CORRECT - Match documented methods
$person = PersonQuery::create()->findOneById($personId);
$people = PersonQuery::create()
    ->filterByActive(true)
    ->orderByLastName()
    ->find();

// WRONG - Method doesn't exist (will throw UnknownColumnException)
$person = PersonQuery::create()->findByPersonId($personId);
```

### withColumn() - ALWAYS Use TableMap Constants

```php
use ChurchCRM\model\ChurchCRM\Map\PledgeTableMap;
use ChurchCRM\model\ChurchCRM\Map\FamilyTableMap;

// ✅ CORRECT - Use TableMap constants (REQUIRED for type safety)
$query->withColumn('SUM(' . PledgeTableMap::COL_PLG_AMOUNT . ')', 'totalAmount');
$query->withColumn(FamilyTableMap::COL_FAM_NAME, 'FamilyName');

// ❌ WRONG - phpNames don't work in withColumn()
$query->withColumn('Pledge.Amount', 'totalAmount');
$query->withColumn('Family.Name', 'FamilyName');
```

### Null Checking

```php
// ✅ CORRECT - Check === null for objects
$person = PersonQuery::create()->findOneById($id);
if ($person === null) {
    throw new InvalidArgumentException('Person not found');
}

// ❌ WRONG - empty() can give false positives
if (empty($person)) {  // Zero values, "0" string, etc. trigger this
    throw new InvalidArgumentException('Person not found');
}
```

### Property Access

```php
// ✅ CORRECT - Access as object properties
$firstName = $person->getFirstName();
$familyId = $person->getFamId();

// ❌ WRONG - Never array access on ORM objects
$firstName = $person['firstName'];  // Error or undefined behavior
$familyId = $person['fam_id'];      // Not an array
```

### Type Casting for Dynamic Values

```php
// ✅ CORRECT - Cast dynamic IDs to int
$personId = (int)$_GET['personId'];
$familyId = (int)$_POST['familyId'];

$person = PersonQuery::create()->findOneById($personId);

// ❌ WRONG - Passing string IDs can cause type confusion
$person = PersonQuery::create()->findOneById($_GET['personId']);
```

---

## Service Layer Pattern

Services encapsulate business logic separate from HTTP concerns. Located in `src/ChurchCRM/Service/`.

### Service Class Structure

```php
<?php
namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\LoggerUtils;
use Throwable;

class FamilyService
{
    private LoggerUtils $logger;

    public function __construct(
        private FamilyQuery $familyQuery,
        private PersonQuery $personQuery
    ) {
        $this->logger = LoggerUtils::getAppLogger();
    }

    /**
     * Get family with all members
     * Uses single-query philosophy for performance
     */
    public function getFamilyData(int $familyId): array
    {
        try {
            $family = $this->familyQuery->findOneById($familyId);
            if ($family === null) {
                throw new InvalidArgumentException("Family $familyId not found");
            }

            // Fetch family data with related people efficiently
            $people = $this->personQuery
                ->filterByFamId($familyId)
                ->orderByMemberOrder()
                ->find();

            $this->logger->info('Retrieved family data', [
                'familyId' => $familyId,
                'memberCount' => count($people)
            ]);

            return [
                'family' => $family,
                'people' => $people
            ];
        } catch (Throwable $e) {
            $this->logger->error('Failed to retrieve family data', [
                'familyId' => $familyId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create new family with initial member
     */
    public function createFamily(array $data, int $personId): Family
    {
        $family = new Family();
        $family->setName(InputUtils::sanitizeText($data['name']));
        $family->setAddress1(InputUtils::sanitizeText($data['address']));
        $family->save();

        $this->logger->info('Family created', [
            'familyId' => $family->getId(),
            'founderId' => $personId
        ]);

        return $family;
    }
}
```

### Performance Best Practices

```php
// ✅ CORRECT - Single query philosophy
public function getUserStats(): array
{
    // Fetch all users with stats fields in ONE query
    $users = UserQuery::create()
        ->select(['id', 'username', 'failedLogins', 'lastLogin'])
        ->find();

    // Process statistics in PHP memory
    $stats = [];
    foreach ($users as $user) {
        $stats[$user->getId()] = [
            'username' => $user->getUsername(),
            'failedLogins' => $user->getFailedLogins(),
            'lastLogin' => $user->getLastLogin()
        ];
    }
    return $stats;
}

// ❌ WRONG - N+1 queries (one per user)
public function getUserStats(): array
{
    $users = UserQuery::create()->find();  // Query 1
    $stats = [];
    foreach ($users as $user) {
        // Each iteration executes a new query
        $logs = UserLogQuery::create()
            ->filterByUserId($user->getId())
            ->find();  // Query 2, 3, 4, ... N+1
        $stats[$user->getId()] = count($logs);
    }
    return $stats;
}

// ✅ CORRECT - Pre-fetch related data
$users = UserQuery::create()->find();
$userIds = array_column($users, 'id');
$logs = UserLogQuery::create()
    ->filterByUserId($userIds)
    ->find();  // One query with all user IDs
```

---

## Authorization & Security

### User Authorization Methods

```php
use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\RedirectUtils;

// Check role-based permissions
$user = AuthenticationManager::getCurrentUser();

if (!$user->isEditRecordsEnabled()) {
    RedirectUtils::securityRedirect('EditRecords');
}

if (!$user->isManageGroupsEnabled()) {
    RedirectUtils::securityRedirect('ManageGroups');
}

// Check object-level permissions (prevents privilege escalation)
$currentUser = AuthenticationManager::getCurrentUser();
if (!$currentUser->canEditPerson($personId, $familyId)) {
    RedirectUtils::securityRedirect('PropertyAssign');
}
```

### InputUtils - XSS Protection & Sanitization

**CRITICAL:** Always use InputUtils for HTML/text handling. Located in `src/ChurchCRM/Utils/InputUtils.php`:

```php
use ChurchCRM\Utils\InputUtils;

// ✅ CORRECT - Use InputUtils methods

// 1. Sanitize plain text (remove ALL HTML tags)
$firstName = InputUtils::sanitizeText($_POST['firstName']);
$person->setFirstName($firstName);

// 2. Sanitize rich HTML (XSS protection, allow safe tags)
$description = InputUtils::sanitizeHTML($_POST['eventDescription']);
$event->setDesc($description);

// 3. Escape output for HTML body
<?= InputUtils::escapeHTML($person->getFirstName()) ?>

// 4. Escape output for HTML attributes
<input value="<?= InputUtils::escapeAttribute($address) ?>">

// 5. Combined sanitize + escape
$value = InputUtils::sanitizeAndEscapeText($_POST['userInput']);


// ❌ WRONG - Never use these directly
htmlspecialchars($value);           // Incomplete - uses wrong flags
htmlentities($value);               // Incomplete - uses wrong flags
stripslashes($_POST['value']);      // Don't handle magic quotes yourself
?>{{ $value }}                       // Unescaped output
```

### RedirectUtils - Safe Navigation

```php
use ChurchCRM\Utils\RedirectUtils;

// 1. Safe relative redirects (handles root path automatically)
RedirectUtils::redirect('v2/dashboard');
RedirectUtils::redirect('v2/person/not-found?id=' . $personId);

// 2. Security redirects (logs warning, redirects to access-denied)
if (!$user->isEditRecordsEnabled()) {
    RedirectUtils::securityRedirect('EditRecords');  // Log + redirect
}

// 3. Absolute redirects (no path handling)
RedirectUtils::absoluteRedirect('https://example.com');

// ❌ WRONG - Never use header() directly
header('Location: /v2/dashboard');                      // Bypasses root path
header('Location: ' . SystemURLs::getRootPath() . '...'); // RedirectUtils does this
```

### File Inclusion

```php
// ✅ CORRECT - Use require for critical files (fails loudly)
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';

// WRONG - include allows missing files (fails silently)
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
```

---

## Error Handling

### Service Errors - Let Them Bubble Up

```php
// ✅ CORRECT - Throw exceptions, let caller handle
public function createUser(array $data): User
{
    if (empty($data['email'])) {
        throw new InvalidArgumentException('Email is required');
    }

    $user = new User();
    $user->setEmail(InputUtils::sanitizeText($data['email']));
    $user->save();

    return $user;
}
```

### API Route Errors - Use SlimUtils

```php
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

// ✅ CORRECT - Try/catch with SlimUtils::renderErrorJSON
$app->post('/users', function (Request $request, Response $response): Response {
    try {
        $data = $request->getParsedBody();
        $service = $this->get('UserService');
        $user = $service->createUser($data);
        
        return SlimUtils::renderJSON($response, [
            'data' => ['id' => $user->getId(), 'email' => $user->getEmail()]
        ]);
    } catch (InvalidArgumentException $e) {
        return SlimUtils::renderErrorJSON(
            $response,
            gettext('Validation failed'),
            [],
            400,
            $e,
            $request
        );
    } catch (Throwable $e) {
        return SlimUtils::renderErrorJSON(
            $response,
            gettext('Operation failed'),
            [],
            500,
            $e,
            $request
        );
    }
});

// ❌ WRONG - Never throw exceptions directly from API routes
$app->post('/users', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $service = $this->get('UserService');
    $user = $service->createUser($data);  // Exception exposes details to client
    return $response->withJson(['data' => $user]);
});
```

---

## Logging Standards

Use `LoggerUtils::getAppLogger()` for all business logic operations:

```php
use ChurchCRM\Utils\LoggerUtils;

class MyService
{
    private $logger;

    public function __construct()
    {
        $this->logger = LoggerUtils::getAppLogger();
    }

    public function processPayment(int $familyId, float $amount): void
    {
        $this->logger->debug('Processing payment', [
            'familyId' => $familyId,
            'amount' => $amount
        ]);

        try {
            $account = AccountQuery::create()->findOneById($familyId);
            if ($account === null) {
                throw new InvalidArgumentException("Account $familyId not found");
            }

            $account->addBalance($amount);
            $account->save();

            $this->logger->info('Payment processed successfully', [
                'familyId' => $familyId,
                'amount' => $amount,
                'newBalance' => $account->getBalance()
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Payment processing failed', [
                'familyId' => $familyId,
                'amount' => $amount,
                'error' => $e->getMessage(),
                'exception' => $e
            ]);
            throw $e;
        }
    }
}
```

**Log Levels:**
- `debug()` - Development information, variable values
- `info()` - Business events: "User created", "Payment processed"
- `warning()` - Unusual situations: "Deprecated method used", "Slow query detected"
- `error()` - Failures: "Database connection failed", exceptions
- `critical()` - System-critical: "Payment timeout", "Data corruption"

---

## Common Patterns

### Null Safety

```php
// ✅ CORRECT - Null coalescing + optional chaining
echo $notification?->title ?? 'No Title';
$status = $user->getProfile()?->getStatus() ?? 'Unknown';

// ✅ CORRECT - Explicit null check
if ($person !== null) {
    $name = $person->getFirstName();
}

// ❌ WRONG - Assumes non-null
echo $notification->title;  // TypeError if null
```

### Email Handling

```php
// ✅ CORRECT - Log but don't crash API
if (!mail($to, $subject, $body)) {
    $this->logger->warning('Email delivery failed', [
        'to' => $to,
        'subject' => $subject
    ]);
}
return $response->withJson(['data' => $result]);

// ❌ WRONG - Throwing exception breaks API
if (!mail($to, $subject, $body)) {
    throw new Exception("Email failed");  // Returns 500
}
```

### Algorithm Performance

When matching items between collections, use hash-based lookups not nested loops:

```php
// ✅ CORRECT - O(N+M) using set membership
$localIds = [];
foreach ($localPeople as $person) {
    $localIds[$person->getId()] = true;  // Build hash map
}
$remoteOnly = [];
foreach ($remotePeople as $remotePerson) {
    if (!isset($localIds[$remotePerson['id']])) {  // O(1) lookup
        $remoteOnly[] = $remotePerson;
    }
}

// ✅ CORRECT - Using array_flip for sets
$localIdSet = array_flip(array_column($localPeople, 'id'));
$remoteOnly = array_filter($remotePeople, fn($p) => 
    !isset($localIdSet[$p['id']])
);

// ❌ WRONG - O(N*M) nested loop (scales poorly)
$remoteOnly = array_filter($remotePeople, function($remotePerson) use ($localPeople) {
    foreach ($localPeople as $localPerson) {  // O(M) per item!
        if ($localPerson->getId() === $remotePerson['id']) {
            return false;
        }
    }
    return true;
});
```

### TLS/SSL Verification

Always enable TLS verification by default for HTTPS requests:

```php
// ✅ CORRECT - Secure by default with opt-in insecure for self-signed
public function sendRequest(string $url, bool $allowSelfSigned = false): void
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);

    if ($allowSelfSigned) {
        // Only disable for explicitly configured local network servers
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    } else {
        // Default: verify SSL certificates (secure)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    }
    curl_exec($ch);
}

// ❌ WRONG - Always disables security
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  // MITM vulnerability
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);      // MITM vulnerability
```

---

## Code Quality Checklist

Before committing PHP code:

- [ ] Used ORM Query classes, not raw SQL
- [ ] Added `use` statements for all external classes
- [ ] Used `InputUtils` for all HTML/text handling
- [ ] Used `RedirectUtils` for all redirects
- [ ] Services extracted business logic from HTTP handlers
- [ ] Try/catch used for API errors with `SlimUtils::renderErrorJSON()`
- [ ] `LoggerUtils` used for important operations
- [ ] Type hints on all parameters and return types
- [ ] Null checks performed before accessing object properties
- [ ] Dynamic IDs cast to `(int)`
- [ ] `require` used for critical files (Header.php, Footer.php)
- [ ] No hardcoded paths - used `SystemURLs::getRootPath()`

---

## Related Skills

- [Slim 4 Best Practices](./slim-4-best-practices.md) - REST API patterns
- [Database Operations](./database-operations.md) - ORM details
- [Authorization & Security](./authorization-security.md) - Permission checks
- [Service Layer](./service-layer.md) - Business logic patterns
- [Code Standards](./code-standards.md) - General standards
