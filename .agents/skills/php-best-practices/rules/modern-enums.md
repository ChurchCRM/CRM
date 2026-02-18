---
title: Type-Safe Enums
impact: CRITICAL
impactDescription: Provides type safety for constants, prevents invalid values
tags: modern-features, enums, type-safety, php81
---

# Type-Safe Enums

## Why It Matters

Enums (PHP 8.1+) provide type-safe constants with methods. They prevent invalid values, enable IDE autocompletion, and encapsulate related behavior. Always prefer enums over class constants for finite sets of values.

## Incorrect

```php
<?php

// ❌ Class constants - no type safety
class OrderStatus
{
    public const PENDING = 'pending';
    public const PROCESSING = 'processing';
    public const SHIPPED = 'shipped';
    public const DELIVERED = 'delivered';
    public const CANCELLED = 'cancelled';
}

// Anyone can pass invalid value
function updateStatus(string $status): void
{
    // 'invalid_status' would be accepted
}

updateStatus('typo'); // No error!

// ❌ Constants scattered or duplicated
class Order
{
    public const STATUS_PENDING = 1;
    public const STATUS_ACTIVE = 2;
}

class Payment
{
    public const STATUS_PENDING = 1; // Duplicated
    public const STATUS_COMPLETED = 2;
}
```

## Correct

### Basic Enum (Unit Enum)

```php
<?php

// ✅ Unit enum - no backing value
enum Direction
{
    case North;
    case South;
    case East;
    case West;

    public function opposite(): self
    {
        return match($this) {
            self::North => self::South,
            self::South => self::North,
            self::East => self::West,
            self::West => self::East,
        };
    }
}

$direction = Direction::North;
$opposite = $direction->opposite(); // Direction::South
```

### Backed Enum (String or Int)

```php
<?php

// ✅ String-backed enum - for database/API values
enum OrderStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Pending => 'Awaiting Processing',
            self::Processing => 'Being Prepared',
            self::Shipped => 'On the Way',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Pending => 'yellow',
            self::Processing => 'blue',
            self::Shipped => 'purple',
            self::Delivered => 'green',
            self::Cancelled => 'red',
        };
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return match($this) {
            self::Pending => in_array($newStatus, [self::Processing, self::Cancelled]),
            self::Processing => in_array($newStatus, [self::Shipped, self::Cancelled]),
            self::Shipped => $newStatus === self::Delivered,
            self::Delivered, self::Cancelled => false,
        };
    }
}

// Usage
$status = OrderStatus::Pending;
$status->value;  // 'pending'
$status->name;   // 'Pending'
$status->label(); // 'Awaiting Processing'

// From database/API value
$status = OrderStatus::from('pending'); // OrderStatus::Pending
$status = OrderStatus::tryFrom('invalid'); // null (no exception)
```

### Int-Backed Enum

```php
<?php

// ✅ Int-backed enum - for legacy databases
enum Priority: int
{
    case Low = 1;
    case Medium = 2;
    case High = 3;
    case Critical = 4;

    public function isUrgent(): bool
    {
        return $this->value >= self::High->value;
    }
}

// Comparison
$priority = Priority::High;
if ($priority->value > Priority::Medium->value) {
    // Handle high priority
}
```

### Enum with Interface

```php
<?php

interface Labelable
{
    public function label(): string;
}

enum PaymentMethod: string implements Labelable
{
    case CreditCard = 'credit_card';
    case BankTransfer = 'bank_transfer';
    case PayPal = 'paypal';

    public function label(): string
    {
        return match($this) {
            self::CreditCard => 'Credit Card',
            self::BankTransfer => 'Bank Transfer',
            self::PayPal => 'PayPal',
        };
    }

    public function processingFee(): float
    {
        return match($this) {
            self::CreditCard => 0.029,
            self::BankTransfer => 0.01,
            self::PayPal => 0.034,
        };
    }
}
```

### Enum with Traits

```php
<?php

trait EnumHelpers
{
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn($case) => $case->label(), self::cases())
        );
    }
}

enum Role: string
{
    use EnumHelpers;

    case Admin = 'admin';
    case Editor = 'editor';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match($this) {
            self::Admin => 'Administrator',
            self::Editor => 'Content Editor',
            self::Viewer => 'Read Only',
        };
    }

    public function permissions(): array
    {
        return match($this) {
            self::Admin => ['create', 'read', 'update', 'delete', 'manage'],
            self::Editor => ['create', 'read', 'update'],
            self::Viewer => ['read'],
        };
    }
}

// Usage
Role::values();  // ['admin', 'editor', 'viewer']
Role::options(); // ['admin' => 'Administrator', ...]
```

### Type-Safe Function Parameters

```php
<?php

// ✅ Function accepts only valid enum values
function updateOrderStatus(Order $order, OrderStatus $newStatus): void
{
    if (!$order->status->canTransitionTo($newStatus)) {
        throw new InvalidStatusTransitionException(
            $order->status,
            $newStatus
        );
    }

    $order->status = $newStatus;
}

// Compile-time safety
updateOrderStatus($order, OrderStatus::Shipped); // ✅
updateOrderStatus($order, 'shipped'); // ❌ TypeError
```

### In Eloquent/Database

```php
<?php

// Model with enum casting
class Order extends Model
{
    protected $casts = [
        'status' => OrderStatus::class,
        'priority' => Priority::class,
    ];
}

// Query with enum
Order::where('status', OrderStatus::Pending)->get();

// Validation rule
'status' => ['required', new Enum(OrderStatus::class)],
```

## Benefits

- Type safety - invalid values caught at compile time
- IDE autocompletion and refactoring support
- Encapsulates related behavior (methods)
- Self-documenting code
- Works with match expressions
- Backed enums integrate with databases
- from()/tryFrom() for safe conversion
