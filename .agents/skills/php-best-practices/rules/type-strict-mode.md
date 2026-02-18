---
title: Strict Types Declaration
impact: CRITICAL
impactDescription: Prevents type coercion bugs, enforces type safety
tags: type-system, strict-types, type-safety, php7
---

# Strict Types Declaration

## Why It Matters

`declare(strict_types=1)` enforces strict type checking for function arguments and return values. Without it, PHP silently coerces types, hiding bugs. Strict mode catches type errors early, improving code reliability.

## Incorrect

```php
<?php

// ❌ No strict types - silent coercion
function calculateTotal(int $price, int $quantity): int
{
    return $price * $quantity;
}

// These work but hide problems:
calculateTotal("10", "5");   // Returns 50 - strings coerced to int
calculateTotal(10.99, 2);    // Returns 20 - float truncated to int
calculateTotal("abc", 2);    // Returns 0 - "abc" becomes 0

// ❌ Missing from file
namespace App\Services;

class Calculator
{
    // ...
}
```

## Correct

```php
<?php

declare(strict_types=1);

// ✅ Strict types - TypeError on wrong types
function calculateTotal(int $price, int $quantity): int
{
    return $price * $quantity;
}

calculateTotal(10, 5);       // ✅ Returns 50
calculateTotal("10", "5");   // ❌ TypeError
calculateTotal(10.99, 2);    // ❌ TypeError
```

## Declaration Rules

```php
<?php

// ✅ MUST be the first statement in the file
declare(strict_types=1);

namespace App\Services;

use App\Models\User;

class UserService
{
    // ...
}
```

```php
<?php

namespace App\Services; // ❌ Wrong - declare must come first

declare(strict_types=1);

class UserService
{
    // ...
}
```

## Scope

```php
<?php

declare(strict_types=1);

// Strict mode applies to function CALLS in this file
function addNumbers(int $a, int $b): int
{
    return $a + $b;
}

// This call is in strict mode
addNumbers(1, 2);     // ✅
addNumbers("1", "2"); // ❌ TypeError

// But internal PHP functions follow their own rules
strlen("hello");      // ✅ Works
strlen(12345);        // ✅ Still works (internal function)
```

## File-by-File Basis

```php
<?php
// file: src/Strict.php
declare(strict_types=1);

function strictFunction(int $n): int
{
    return $n * 2;
}
```

```php
<?php
// file: src/NonStrict.php
// No declare - weak mode

require_once 'Strict.php';

// Calls from weak mode file still coerce
strictFunction("5"); // Returns 10 - coercion happens at call site
```

## Return Type Enforcement

```php
<?php

declare(strict_types=1);

// ✅ Return type strictly enforced
function getPrice(): float
{
    return 99.99; // Must return float
}

function getCount(): int
{
    return 42; // Must return int
}

// ❌ This would cause TypeError
function broken(): int
{
    return "42"; // TypeError - can't return string as int
}
```

## With Nullable Types

```php
<?php

declare(strict_types=1);

function findUser(int $id): ?User
{
    // Must return User or null, nothing else
    return User::find($id);
}

function process(?string $data): void
{
    // $data must be string or null
}

process("hello"); // ✅
process(null);    // ✅
process(123);     // ❌ TypeError
```

## With Union Types

```php
<?php

declare(strict_types=1);

function format(string|int $value): string
{
    return (string) $value;
}

format("hello"); // ✅
format(42);      // ✅
format(3.14);    // ❌ TypeError - float not in union
```

## Best Practice Template

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\RepositoryInterface;
use App\Models\User;
use App\Exceptions\UserNotFoundException;

final class UserService
{
    public function __construct(
        private readonly RepositoryInterface $repository,
    ) {}

    public function findById(int $id): User
    {
        $user = $this->repository->find($id);

        if ($user === null) {
            throw new UserNotFoundException($id);
        }

        return $user;
    }

    public function create(array $data): User
    {
        return $this->repository->create($data);
    }

    /**
     * @param array<int> $ids
     * @return array<User>
     */
    public function findMany(array $ids): array
    {
        return $this->repository->findMany($ids);
    }
}
```

## IDE/Static Analysis

```php
<?php

declare(strict_types=1);

// PHPStan/Psalm will catch type errors even more strictly
// Combined with strict_types, you get maximum type safety

/** @var positive-int $count */
$count = getCount();

/** @var non-empty-string $name */
$name = getName();
```

## Benefits

- Catches type bugs at runtime immediately
- No silent type coercion surprises
- Works with static analysis tools
- Self-documenting code intent
- Matches behavior of other typed languages
- Required for reliable modern PHP
