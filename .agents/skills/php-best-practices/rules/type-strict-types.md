---
title: Strict Types Declaration
impact: CRITICAL
impactDescription: Prevents type coercion bugs, enforces type safety
tags: type-system, strict-types, type-safety, php8
---

# Strict Types Declaration

Always enable strict type checking at the beginning of every PHP file.

## Bad Example

```php
<?php

// No strict types - PHP will coerce types silently
function calculateTotal(int $price, int $quantity): int
{
    return $price * $quantity;
}

// This works but "10" is silently converted to 10
echo calculateTotal("10", 2); // Outputs: 20
```

## Good Example

```php
<?php

declare(strict_types=1);

function calculateTotal(int $price, int $quantity): int
{
    return $price * $quantity;
}

// This now throws TypeError - enforcing proper types
echo calculateTotal(10, 2); // Outputs: 20
// calculateTotal("10", 2); // TypeError!
```

## Why

- **Type Safety**: Catches type errors at runtime rather than silently coercing values
- **Bug Prevention**: Prevents subtle bugs caused by unexpected type conversions
- **Code Clarity**: Makes the expected types explicit and enforced
- **Better Debugging**: Errors are caught immediately where they occur
- **IDE Support**: Enables better static analysis and IDE autocompletion
- **Modern Standard**: Aligns with modern PHP development practices (PHP 7.0+)
