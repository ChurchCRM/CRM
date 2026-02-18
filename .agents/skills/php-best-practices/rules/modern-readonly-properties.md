---
title: Readonly Properties
impact: CRITICAL
impactDescription: Ensures immutability, prevents accidental modification
tags: modern-features, readonly, immutability, php81
---

# Readonly Properties

Use readonly properties for immutable data that should only be set once (PHP 8.1+).

## Bad Example

```php
<?php

declare(strict_types=1);

class Invoice
{
    private string $invoiceNumber;
    private DateTimeImmutable $issuedAt;
    private float $amount;

    public function __construct(
        string $invoiceNumber,
        DateTimeImmutable $issuedAt,
        float $amount
    ) {
        $this->invoiceNumber = $invoiceNumber;
        $this->issuedAt = $issuedAt;
        $this->amount = $amount;
    }

    // No protection against accidental modification
    public function setInvoiceNumber(string $number): void
    {
        // This shouldn't be allowed after creation!
        $this->invoiceNumber = $number;
    }

    public function getInvoiceNumber(): string
    {
        return $this->invoiceNumber;
    }
}
```

## Good Example

```php
<?php

declare(strict_types=1);

class Invoice
{
    public function __construct(
        public readonly string $invoiceNumber,
        public readonly DateTimeImmutable $issuedAt,
        public readonly float $amount,
        public readonly string $currency = 'USD',
    ) {}

    // No setters needed - properties are immutable
    // Direct public access is safe because they can't be modified
}

// Usage
$invoice = new Invoice(
    invoiceNumber: 'INV-2024-001',
    issuedAt: new DateTimeImmutable(),
    amount: 99.99,
);

echo $invoice->invoiceNumber; // Works - reading is allowed
// $invoice->invoiceNumber = 'INV-2024-002'; // Error! Cannot modify readonly

// Readonly with private visibility when you need getters
class User
{
    public function __construct(
        private readonly int $id,
        private readonly string $email,
        private readonly string $passwordHash,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    // No getter for passwordHash - it stays private
}

// Value objects are perfect for readonly
class Coordinates
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
    ) {
        if ($latitude < -90 || $latitude > 90) {
            throw new InvalidArgumentException('Invalid latitude');
        }
        if ($longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException('Invalid longitude');
        }
    }

    public function distanceTo(Coordinates $other): float
    {
        // Calculate distance...
    }
}
```

## Why

- **Immutability**: Properties cannot be modified after initialization
- **Thread Safety**: Immutable objects are inherently thread-safe
- **Value Objects**: Perfect for implementing value object pattern
- **No Defensive Copies**: Safe to expose without getters
- **Clear Intent**: Signals that property should never change
- **Bug Prevention**: Prevents accidental modification of critical data
