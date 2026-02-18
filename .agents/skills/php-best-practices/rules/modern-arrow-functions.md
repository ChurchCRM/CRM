---
title: Arrow Functions
impact: MEDIUM
impactDescription: Concise closures with automatic variable capture
tags: modern-features, arrow-functions, closures, php74
---

# Arrow Functions

Use arrow functions for short, single-expression closures (PHP 7.4+).

## Bad Example

```php
<?php

declare(strict_types=1);

// Verbose closures for simple operations
$numbers = [1, 2, 3, 4, 5];

$doubled = array_map(function ($n) {
    return $n * 2;
}, $numbers);

$evens = array_filter($numbers, function ($n) {
    return $n % 2 === 0;
});

// Must explicitly use `use` to capture outer scope
$multiplier = 3;
$multiplied = array_map(function ($n) use ($multiplier) {
    return $n * $multiplier;
}, $numbers);

// Nested verbose closures
$users = [/* ... */];
$activeEmails = array_map(function ($user) {
    return $user->getEmail();
}, array_filter($users, function ($user) {
    return $user->isActive();
}));
```

## Good Example

```php
<?php

declare(strict_types=1);

$numbers = [1, 2, 3, 4, 5];

// Concise arrow functions
$doubled = array_map(fn($n) => $n * 2, $numbers);

$evens = array_filter($numbers, fn($n) => $n % 2 === 0);

// Automatic capture of outer scope - no `use` needed
$multiplier = 3;
$multiplied = array_map(fn($n) => $n * $multiplier, $numbers);

// Chained operations are more readable
$users = [/* ... */];
$activeEmails = array_map(
    fn($user) => $user->getEmail(),
    array_filter($users, fn($user) => $user->isActive())
);

// With type hints
$prices = [10.5, 20.0, 15.75];
$withTax = array_map(
    fn(float $price): float => $price * 1.1,
    $prices
);

// Collection operations
class UserCollection
{
    /** @var User[] */
    private array $users;

    public function getActiveUsers(): array
    {
        return array_filter($this->users, fn($u) => $u->isActive());
    }

    public function getEmails(): array
    {
        return array_map(fn($u) => $u->getEmail(), $this->users);
    }

    public function findByRole(string $role): array
    {
        return array_filter($this->users, fn($u) => $u->getRole() === $role);
    }

    public function getTotalBalance(): float
    {
        return array_sum(array_map(fn($u) => $u->getBalance(), $this->users));
    }
}

// Sorting with arrow functions
$products = [/* ... */];
usort($products, fn($a, $b) => $a->getPrice() <=> $b->getPrice());

// Callbacks and event handlers
$button->onClick(fn() => $this->handleClick());
$form->onSubmit(fn($data) => $this->processForm($data));

// Validation rules
$rules = [
    'email' => fn($v) => filter_var($v, FILTER_VALIDATE_EMAIL) !== false,
    'age' => fn($v) => is_numeric($v) && $v >= 18,
    'name' => fn($v) => strlen($v) >= 2 && strlen($v) <= 100,
];

// Higher-order functions
function createMultiplier(int $factor): Closure
{
    return fn(int $n): int => $n * $factor;
}

$double = createMultiplier(2);
$triple = createMultiplier(3);

echo $double(5); // 10
echo $triple(5); // 15
```

## Why

- **Concise**: Single expression without braces or return keyword
- **Auto-Capture**: Variables from outer scope captured automatically
- **Readable**: Better for functional programming patterns
- **Type Support**: Full support for parameter and return types
- **Immutable Capture**: Captured variables are by-value (safe)
- **Perfect For**: Callbacks, array functions, short lambdas
