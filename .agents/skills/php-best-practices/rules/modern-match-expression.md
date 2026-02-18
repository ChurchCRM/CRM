---
title: Match Expression
impact: HIGH
impactDescription: Type-safe, concise alternative to switch with no fall-through
tags: modern-features, match, control-flow, php8
---

# Match Expression

Use match expressions instead of switch statements for cleaner, safer code (PHP 8.0+).

## Bad Example

```php
<?php

declare(strict_types=1);

function getStatusMessage(int $code): string
{
    // Switch is verbose and error-prone
    switch ($code) {
        case 200:
            $message = 'OK';
            break;
        case 201:
            $message = 'Created';
            break;
        case 400:
            $message = 'Bad Request';
            break;
        case 404:
            $message = 'Not Found';
            break;
        case 500:
            $message = 'Internal Server Error';
            break;
        default:
            $message = 'Unknown';
            // Easy to forget break - falls through!
    }
    return $message;
}

// Type coercion issues
$value = '1';
switch ($value) {
    case 1: // Matches due to loose comparison!
        echo 'One';
        break;
}
```

## Good Example

```php
<?php

declare(strict_types=1);

function getStatusMessage(int $code): string
{
    // Match is an expression - returns a value
    return match ($code) {
        200 => 'OK',
        201 => 'Created',
        400 => 'Bad Request',
        404 => 'Not Found',
        500 => 'Internal Server Error',
        default => 'Unknown',
    };
}

// Multiple values per arm
function getHttpStatusCategory(int $code): string
{
    return match (true) {
        $code >= 100 && $code < 200 => 'Informational',
        $code >= 200 && $code < 300 => 'Success',
        $code >= 300 && $code < 400 => 'Redirection',
        $code >= 400 && $code < 500 => 'Client Error',
        $code >= 500 && $code < 600 => 'Server Error',
        default => 'Unknown',
    };
}

// Multiple conditions in one arm
function getDiscount(string $customerType): float
{
    return match ($customerType) {
        'premium', 'vip', 'gold' => 0.20,
        'silver', 'regular' => 0.10,
        'new' => 0.05,
        default => 0.0,
    };
}

// With enums - exhaustive matching
enum PaymentMethod
{
    case CreditCard;
    case PayPal;
    case BankTransfer;
    case Crypto;
}

function getPaymentFee(PaymentMethod $method): float
{
    return match ($method) {
        PaymentMethod::CreditCard => 2.9,
        PaymentMethod::PayPal => 3.5,
        PaymentMethod::BankTransfer => 0.5,
        PaymentMethod::Crypto => 1.0,
        // No default needed - all cases covered
        // Adding new enum case = compile error here
    };
}

// Match as expression in complex scenarios
class OrderProcessor
{
    public function calculateShipping(Order $order): Money
    {
        $baseRate = match ($order->shippingMethod) {
            ShippingMethod::Standard => new Money(599, 'USD'),
            ShippingMethod::Express => new Money(1299, 'USD'),
            ShippingMethod::Overnight => new Money(2499, 'USD'),
            ShippingMethod::Pickup => new Money(0, 'USD'),
        };

        return $order->isHeavy()
            ? $baseRate->multiply(1.5)
            : $baseRate;
    }
}

// Strict comparison - no type coercion
$value = '1';
$result = match ($value) {
    1 => 'integer one',      // Won't match - strict comparison
    '1' => 'string one',     // Matches
    default => 'other',
};
```

## Why

- **Expression**: Returns a value directly, no break statements needed
- **Strict Comparison**: Uses === preventing type coercion bugs
- **Exhaustive**: Error if value doesn't match any arm (without default)
- **Concise**: Much less boilerplate than switch
- **No Fall-through**: Impossible to accidentally fall through cases
- **Enum Support**: Perfect pairing with enums for exhaustive matching
