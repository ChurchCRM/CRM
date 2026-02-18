---
title: Constructor Property Promotion
impact: CRITICAL
impactDescription: Reduces boilerplate by 60%, cleaner class definitions
tags: modern-features, constructor-promotion, php8, boilerplate-reduction
---

# Constructor Property Promotion

## Why It Matters

Constructor property promotion (PHP 8.0+) reduces boilerplate code significantly. It combines parameter declaration, property declaration, and assignment into a single statement, making classes cleaner and easier to maintain.

## Incorrect

```php
<?php

// ❌ Verbose - property declared twice, assigned manually
class User
{
    private string $id;
    private string $name;
    private string $email;
    private bool $active;
    private ?DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $name,
        string $email,
        bool $active = true,
        ?DateTimeImmutable $createdAt = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->active = $active;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
    }
}

// ❌ Mixed styles - inconsistent
class Product
{
    private string $sku;

    public function __construct(
        private string $name,
        string $sku,
        private float $price,
    ) {
        $this->sku = $sku;
    }
}
```

## Correct

```php
<?php

// ✅ Constructor property promotion - clean and concise
class User
{
    public function __construct(
        private string $id,
        private string $name,
        private string $email,
        private bool $active = true,
        private ?DateTimeImmutable $createdAt = null,
    ) {
        $this->createdAt ??= new DateTimeImmutable();
    }
}

// ✅ With readonly for immutable properties
class User
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $email,
        private bool $active = true,
    ) {}

    public function activate(): void
    {
        $this->active = true;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
```

## With Validation

```php
<?php

class Email
{
    public function __construct(
        public readonly string $value,
    ) {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException(
                sprintf('Invalid email address: %s', $value)
            );
        }
    }
}

class Money
{
    public function __construct(
        public readonly int $amount,
        public readonly string $currency = 'USD',
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException('Amount cannot be negative');
        }
        if (strlen($currency) !== 3) {
            throw new InvalidArgumentException('Currency must be 3 characters');
        }
    }
}
```

## Mixed Promoted and Non-Promoted

```php
<?php

// ✅ When you need computed properties
class Order
{
    private string $orderNumber;

    public function __construct(
        public readonly string $id,
        public readonly array $items,
        public readonly DateTimeImmutable $createdAt,
    ) {
        // Computed property - can't be promoted
        $this->orderNumber = sprintf(
            'ORD-%s-%s',
            $createdAt->format('Ymd'),
            strtoupper(substr($id, 0, 8))
        );
    }

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }
}
```

## Visibility Combinations

```php
<?php

class Entity
{
    public function __construct(
        public readonly string $id,           // Public, immutable
        protected string $name,               // Protected, mutable
        private string $internalState,        // Private, mutable
        private readonly array $metadata,     // Private, immutable
    ) {}
}
```

## Benefits

- Reduces code by ~60% for simple DTOs
- Single source of truth for properties
- Easier to read and maintain
- Trailing comma support for clean diffs
- Combines well with readonly
