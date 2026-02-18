---
title: Parameter Type Declarations
impact: CRITICAL
impactDescription: Prevents invalid arguments, improves code clarity
tags: type-system, parameter-types, type-safety, php7
---

# Parameter Type Declarations

Always declare types for all function and method parameters.

## Bad Example

```php
<?php

declare(strict_types=1);

class OrderService
{
    // No parameter types - anything could be passed
    public function createOrder($user, $items, $discount)
    {
        // What is $user? An ID? An object? An array?
        // What format should $items be?
        // Is $discount a percentage or fixed amount?
    }

    public function processPayment($amount, $method)
    {
        // Type assumptions lead to bugs
    }
}
```

## Good Example

```php
<?php

declare(strict_types=1);

class OrderService
{
    public function createOrder(
        User $user,
        array $items,
        float $discountPercentage = 0.0
    ): Order {
        $order = new Order($user);

        foreach ($items as $item) {
            $order->addItem($item);
        }

        $order->applyDiscount($discountPercentage);

        return $order;
    }

    public function processPayment(
        Money $amount,
        PaymentMethod $method
    ): PaymentResult {
        return $this->paymentGateway->charge($amount, $method);
    }
}
```

## Why

- **Clear Contracts**: Parameters define the method's expected input
- **Early Error Detection**: Type errors are caught immediately at call site
- **Self-Documenting**: Types eliminate guesswork about expected inputs
- **Refactoring Safety**: Changes to parameter types are caught by static analysis
- **IDE Intelligence**: Enables parameter hints and autocompletion
- **Defensive Programming**: Prevents invalid data from entering your methods
