---
title: Nullsafe Operator
impact: HIGH
impactDescription: Eliminates nested null checks, safer navigation
tags: modern-features, nullsafe, null-handling, php8
---

# Nullsafe Operator

Use the nullsafe operator for cleaner null checking chains (PHP 8.0+).

## Bad Example

```php
<?php

declare(strict_types=1);

class OrderService
{
    public function getCustomerCountry(?Order $order): ?string
    {
        // Nested null checks - pyramid of doom
        if ($order !== null) {
            $customer = $order->getCustomer();
            if ($customer !== null) {
                $address = $customer->getAddress();
                if ($address !== null) {
                    return $address->getCountry();
                }
            }
        }
        return null;
    }

    public function getShippingCity(?Order $order): ?string
    {
        // Ternary chains - hard to read
        return $order !== null
            ? ($order->getShipping() !== null
                ? ($order->getShipping()->getAddress() !== null
                    ? $order->getShipping()->getAddress()->getCity()
                    : null)
                : null)
            : null;
    }
}
```

## Good Example

```php
<?php

declare(strict_types=1);

class OrderService
{
    public function getCustomerCountry(?Order $order): ?string
    {
        // Clean nullsafe chain - stops at first null
        return $order?->getCustomer()?->getAddress()?->getCountry();
    }

    public function getShippingCity(?Order $order): ?string
    {
        return $order?->getShipping()?->getAddress()?->getCity();
    }

    public function getOrderTotal(?Order $order): float
    {
        // Combine with null coalescing for default value
        return $order?->getTotal()?->getAmount() ?? 0.0;
    }
}

// Works with method calls
$user?->getProfile()?->updateLastLogin();

// Works with property access
$length = $order?->items?->count();

// Works with array access (PHP 8.0+)
$firstItem = $order?->getItems()[0] ?? null;

// Practical examples
class NotificationService
{
    public function notify(?User $user, string $message): void
    {
        // Only sends if user and preferences exist
        $user?->getPreferences()?->getNotificationChannel()?->send($message);
    }
}

class ReportGenerator
{
    public function getManagerEmail(?Employee $employee): ?string
    {
        return $employee
            ?->getDepartment()
            ?->getManager()
            ?->getEmail();
    }

    public function getManagerName(?Employee $employee): string
    {
        // Chain with null coalescing for default
        return $employee
            ?->getDepartment()
            ?->getManager()
            ?->getName() ?? 'No Manager Assigned';
    }
}

// Complex example with multiple nullsafe chains
class InvoiceService
{
    public function formatBillingInfo(?Invoice $invoice): string
    {
        $company = $invoice?->getCustomer()?->getCompany()?->getName()
            ?? 'Individual';

        $address = $invoice?->getBillingAddress()?->format()
            ?? 'No address provided';

        $contact = $invoice?->getCustomer()?->getPrimaryContact()?->getEmail()
            ?? $invoice?->getCustomer()?->getEmail()
            ?? 'No contact';

        return "$company\n$address\n$contact";
    }
}
```

## Why

- **Concise**: Eliminates verbose null checking boilerplate
- **Readable**: Clear intent - "access if not null"
- **Safe**: Short-circuits at first null, returning null
- **Chainable**: Perfect for deep object graph navigation
- **Combinable**: Works great with null coalescing (??) for defaults
- **Less Bugs**: Reduces chance of null pointer errors
