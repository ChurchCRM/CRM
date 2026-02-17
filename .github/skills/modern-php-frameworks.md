# Modern PHP 8.3+ & Framework Best Practices

This skill consolidates verified best practices from PHP.net, OWASP, Slim 4 framework docs, and Perpl ORM documentation.

## PHP 8.3+ Security Hardening

### Password Security (CRITICAL)

**Use Argon2ID for new implementations:**

```php
// CORRECT - Modern password hashing (PHP 8.4+ default)
$hash = password_hash(
    $password,
    PASSWORD_ARGON2ID,
    [
        'memory_cost' => 65536,  // 65MB
        'time_cost' => 4,         // 4 iterations
        'threads' => 2            // 2 parallel threads
    ]
);

// LEGACY - Still works but less secure
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// VERIFICATION (same code for both)
if (password_verify($userInput, $hash)) {
    // Password is correct
}
```

**Consider password pepper for additional security:**

```php
// Generate pepper (4096-bit random key) once during installation
$pepper = openssl_random_pseudo_bytes(512);  // Store in .env

// Before hashing
$peppered = hash_hmac('sha256', $password, $_ENV['PASSWORD_PEPPER']);
$hash = password_hash($peppered, PASSWORD_ARGON2ID);

// Verification also uses pepper
$peppered = hash_hmac('sha256', $userInput, $_ENV['PASSWORD_PEPPER']);
if (password_verify($peppered, $storedHash)) { /* ... */ }
```

**Why it matters:**
- Argon2ID resists GPU attacks better than bcrypt
- Pepper provides additional brute-force protection even if salts leak
- NIST 2017 recommends pepper for critical authentication

**Source**: [PHP.net password_hash](https://www.php.net/manual/en/function.password-hash.php), [OWASP Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)

### Session Security (Critical)

**Enable strict session mode in docker/Config.php:**

```php
// Production session configuration
ini_set('session.use_strict_mode', 1);           // Reject client-supplied session IDs
ini_set('session.cookie_secure', 1);              // HTTPS only
ini_set('session.cookie_httponly', 1);            // Block JavaScript access
ini_set('session.cookie_samesite', 'Strict');     // CSRF protection
ini_set('session.name', 'id');                    // Non-standard name
ini_set('session.cookie_lifetime', 0);            // Expires when browser closes
ini_set('session.gc_maxlifetime', 3600);          // Remove after 1 hour idle
```

**Regenerate session after authentication:**

```php
// In authentication routes
if (authenticateUser($username, $password)) {
    session_regenerate_id(true);  // true = delete old session
    $_SESSION['user_id'] = $userId;
    $_SESSION['authenticated'] = true;
}
```

**Why it matters:**
- Strict mode prevents session fixation attacks
- SameSite blocks CSRF by default
- Session regeneration prevents hijacking after login
- HTTPOnly prevents XSS access to session cookie

**Source**: [OWASP Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)

### Error Display Hardening

**Production configuration in docker/Config.php:**

```php
// Never display errors in production
if ($isDevelopment) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');         // Never show errors
    error_reporting(E_ALL);                 // Log all errors
    ini_set('log_errors', '1');
    ini_set('error_log', '/var/log/php-errors.log');
}

// Security headers - hide PHP version
ini_set('expose_php', '0');
header('X-Powered-By', 'hidden');  // Don't advertise PHP
```

**Why it matters:**
- Exposed error messages leak system information to attackers
- Version disclosure enables targeted exploits
- All errors logged server-side for debugging without exposing to users

**Source**: [OWASP PHP Configuration Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html)

### Dangerous Function Restrictions

**Disable in web server configuration:**

```php
// In docker/Config.php or Dockerfile
// Disable shell execution functions in production
disable_functions = "exec,system,shell_exec,passthru,proc_open,pcntl_exec"

// Restrict file operations
allow_url_fopen = 0
allow_url_include = 0
```

**Why it matters:**
- Shell execution allows RCE (Remote Code Execution)
- URL wrappers enable RFI (Remote File Inclusion)
- File restrictions prevent file-based attacks

## Slim 4 Framework Best Practices

### Middleware Ordering (CRITICAL)

**Correct order in public/index.php:**

```php
// ✅ CORRECT - Slim 4 LIFO (Last In, First Out) ordering

$app = AppFactory::create();

// 1. AddBodyParsingMiddleware - First added, last executed
$app->addBodyParsingMiddleware();

// 2. AddRoutingMiddleware - Must come before error middleware
$app->addRoutingMiddleware();

// 3. Custom middleware
$app->add(new CorsMiddleware());        // Runs 2nd (added later = runs earlier)
$app->add(new AuthenticationMiddleware()); // Runs 1st

// 4. ErrorMiddleware - Last added, first executed (catches all errors)
$errorMiddleware = $app->addErrorMiddleware(
    displayErrorDetails: $isDevelopment,
    logErrorDetails: true,
    logErrors: true
);

// Custom error handler
$errorMiddleware->setDefaultErrorHandler(function (Request $request, Throwable $exception) use ($app) {
    $response = $app->getResponseFactory()->createResponse();
    return SlimUtils::renderErrorJSON(
        $response, 
        gettext('An error occurred'),
        [],
        500,
        $exception,
        $request
    );
});
```

**Why order matters:**
- Routing must execute before Error middleware catches 404s properly
- Body parsing must be first so subsequent middleware can access parsed body
- Custom middleware order: Auth → Validation → Business Logic
- Error middleware must be last to catch all exceptions

**Source**: [Slim 4 Official Documentation](https://www.slimframework.com/docs/v4/)

### Dependency Injection Pattern

**Container setup in App.php:**

```php
use Psr\Container\ContainerInterface;

$container = $app->getContainer();

// Lazy-load expensive services
$container->set('FinancialService', function(ContainerInterface $c) {
    return new FinancialService(
        $c->get('Database'),  // Lazy-loaded DB connection
        $c->get('Logger')     // Logger instance
    );
});

$container->set('PersonService', function(ContainerInterface $c) {
    return new PersonService(
        $c->get('Database'),
        $c->get('EventDispatcher')
    );
});

// Use in routes
$app->post('/api/payments', function(Request $request, Response $response) use ($container): Response {
    $service = $container->get('FinancialService');
    $result = $service->processPayment($request->getParsedBody());
    return $response->withJson(['data' => $result]);
});
```

**Benefits:**
- Services only instantiated when needed
- Dependencies explicit and injectable
- Easy to swap implementations for testing
- Centralized configuration

### Error Handling Pattern

**Never throw HTTP exceptions from routes:**

```php
// ❌ WRONG - Exception thrown exposes stack trace
$app->post('/api/payment', function(Request $req, Response $res) {
    $service = new PaymentService();
    $service->process($req->getParsedBody());  // If this throws, 500 error
    return $res->withJson(['success' => true]);
});

// ✅ CORRECT - Catch and return sanitized JSON
$app->post('/api/payment', function(Request $req, Response $res) use ($container) {
    try {
        $service = $container->get('PaymentService');
        $result = $service->process($req->getParsedBody());
        return $res->withJson(['success' => true, 'data' => $result]);
    } catch (ValidationException $e) {
        return SlimUtils::renderErrorJSON(
            $res,
            gettext('Validation failed'),
            ['errors' => $e->getErrors()],
            400,
            $e,
            $req
        );
    } catch (PaymentProcessor\Exception $e) {
        return SlimUtils::renderErrorJSON(
            $res,
            gettext('Payment processing failed'),
            [],
            402,  // Payment Required
            $e,
            $req
        );
    } catch (Throwable $e) {
        return SlimUtils::renderErrorJSON(
            $res,
            gettext('An error occurred'),
            [],
            500,
            $e,
            $req
        );
    }
});
```

**Why it matters:**
- Consumers receive consistent error format
- Stack traces logged server-side, hidden from clients
- Specific HTTP status codes convey error type (400 vs 402 vs 500)
- Security: No information leakage to attacking clients

## Perpl ORM Best Practices

### Query Optimization with findObjects()

**Use typed collections:**

```php
// ✅ BETTER - Returns ObjectCollection<User> with IDE support
$users = UserQuery::create()
    ->filterByActive(true)
    ->findObjects();  // Type hints work in IDE

foreach ($users as $user) {
    echo $user->getFirstName();  // IDE autocomplete works
}

// Older way - less type safety
$users = UserQuery::create()
    ->filterByActive(true)
    ->find();  // Returns generic collection
```

### Eager Loading (Prevent N+1 Queries)

**BEFORE (N+1 problem):**

```php
$books = BookQuery::create()->find();
foreach ($books as $book) {
    echo $book->getAuthor()->getName();  // Extra query per book!
}
// If 1000 books: 1001 queries total!
```

**AFTER (single query):**

```php
$books = BookQuery::create()
    ->with('Author')  // Eager-load author in one query
    ->findObjects();

foreach ($books as $book) {
    echo $book->getAuthor()->getName();  // No extra queries, 2 total!
}
```

### Batch Operations for Performance

**Instead of looping saves:**

```php
// ❌ SLOW - N queries
foreach ($people as $person) {
    $person->setStatus('Inactive');
    $person->save();  // 1 query per person
}

// ✅ FAST - 1 query
PersonQuery::create()
    ->filterByFamilyId($familyId)
    ->setUpdateValue('status', 'Inactive')
    ->update();  // Single query updates all matching rows
```

### Type-Safe Joins with useXXXQuery()

```php
// Type-safe join chain
$query = BookQuery::create()                    // BookQuery<null>
    ->useAuthorQuery()                         // AuthorQuery<BookQuery<null>>
        ->filterByLastName('Smith')
    ->endUse()                                 // Back to BookQuery<null>
    ->useCategoryQuery('category')             // CategoryQuery<BookQuery<null>>
        ->filterByType('Fiction')
    ->endUse();                                // Back to BookQuery<null>

// Result typed properly for IDE
$books = $query->findObjects();  // ObjectCollection<Book>
```

## Best Practices Checklist

### Security
- [ ] Passwords hashed with PASSWORD_ARGON2ID
- [ ] Session strict mode enabled in php.ini
- [ ] Session cookies secure + HTTPOnly + SameSite=Strict
- [ ] Error details never displayed in production
- [ ] All user input sanitized with InputUtils
- [ ] Authorization checks use canEditPerson() for object-level security
- [ ] TLS verification enabled by default (allow-self-signed is opt-in)

### Performance
- [ ] Services use selective field loading with `->select()`
- [ ] Related data eager-loaded with `->with()`
- [ ] No N+1 queries in loops (use `withColumn()` or `with()`)
- [ ] Aggregations use SQL `SUM()`, `COUNT()` not PHP loops
- [ ] Hash-based lookups used for set membership
- [ ] Batch operations for bulk updates
- [ ] Large result sets paginated or processed in batches

### Framework
- [ ] Middleware order correct: Body → Routing → Custom → Error
- [ ] Services lazy-loaded through container
- [ ] All errors caught and returned via SlimUtils::renderErrorJSON
- [ ] No HTTP exceptions thrown from routes
- [ ] Dependency injection used for service access
- [ ] Routes focused on HTTP concerns only

---

**Last updated: February 16, 2026**
