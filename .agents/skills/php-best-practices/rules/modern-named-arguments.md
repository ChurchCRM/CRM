---
title: Named Arguments
impact: MEDIUM
impactDescription: Self-documenting calls, skip optional parameters
tags: modern-features, named-arguments, readability, php8
---

# Named Arguments

Use named arguments for clarity and flexibility (PHP 8.0+).

## Bad Example

```php
<?php

declare(strict_types=1);

// Hard to understand what each argument means
$user = new User(
    1,
    'John',
    'Doe',
    'john@example.com',
    null,
    true,
    false,
    'America/New_York'
);

// What do these booleans mean?
$result = $validator->validate($data, true, false, true);

// Must pass all preceding optional arguments
$query = $repository->findAll(null, null, null, 100);

// Confusing function calls
setcookie('session', $value, 0, '/', '', true, true);
```

## Good Example

```php
<?php

declare(strict_types=1);

// Clear what each argument represents
$user = new User(
    id: 1,
    firstName: 'John',
    lastName: 'Doe',
    email: 'john@example.com',
    phone: null,
    isActive: true,
    isAdmin: false,
    timezone: 'America/New_York',
);

// Self-documenting boolean parameters
$result = $validator->validate(
    data: $data,
    strict: true,
    allowEmpty: false,
    throwOnError: true,
);

// Skip optional parameters - only pass what you need
$query = $repository->findAll(limit: 100);

// Much clearer
setcookie(
    name: 'session',
    value: $value,
    secure: true,
    httponly: true,
);

// Mix positional and named (positional must come first)
function createNotification(
    string $message,
    string $title = 'Notice',
    string $type = 'info',
    bool $persistent = false,
    ?int $timeout = null,
): Notification {
    return new Notification($message, $title, $type, $persistent, $timeout);
}

// Can skip defaults and only specify what differs
$notification = createNotification(
    'Your order has shipped!',
    type: 'success',
    persistent: true,
);

// Perfect for configuration objects
class DatabaseConfig
{
    public function __construct(
        public string $host = 'localhost',
        public int $port = 3306,
        public string $database = '',
        public string $username = '',
        public string $password = '',
        public string $charset = 'utf8mb4',
        public bool $persistent = false,
        public int $timeout = 30,
    ) {}
}

$config = new DatabaseConfig(
    host: 'db.example.com',
    database: 'myapp',
    username: 'admin',
    password: 'secret',
    timeout: 60,
);

// Variadic functions with named arguments
function logMessage(
    string $message,
    string $level = 'info',
    array ...$context
): void {
    // Implementation
}

logMessage(
    message: 'User logged in',
    level: 'info',
    user: ['id' => 1, 'name' => 'John'],
    ip: ['address' => '192.168.1.1'],
);
```

## Why

- **Self-Documenting**: Argument purpose is clear at call site
- **Skip Defaults**: Only pass arguments that differ from defaults
- **Order Independence**: Arguments can be in any order when named
- **Boolean Clarity**: Named booleans are much clearer than positional
- **Refactoring Safe**: Reordering parameters won't break named calls
- **IDE Support**: Full autocompletion for parameter names
