---
title: Liskov Substitution Principle
impact: HIGH
impactDescription: Subtypes are substitutable, maintains polymorphism
tags: solid, lsp, design-principles, substitutability
---

# Liskov Substitution Principle (LSP)

Subtypes must be substitutable for their base types without altering correctness.

## Bad Example

```php
<?php

declare(strict_types=1);

class Rectangle
{
    protected int $width;
    protected int $height;

    public function setWidth(int $width): void
    {
        $this->width = $width;
    }

    public function setHeight(int $height): void
    {
        $this->height = $height;
    }

    public function getArea(): int
    {
        return $this->width * $this->height;
    }
}

// Violates LSP - Square changes Rectangle behavior
class Square extends Rectangle
{
    public function setWidth(int $width): void
    {
        // Breaks the contract - also sets height
        $this->width = $width;
        $this->height = $width;
    }

    public function setHeight(int $height): void
    {
        // Breaks the contract - also sets width
        $this->width = $height;
        $this->height = $height;
    }
}

// This function expects Rectangle behavior
function resizeRectangle(Rectangle $rect): int
{
    $rect->setWidth(5);
    $rect->setHeight(10);
    return $rect->getArea(); // Expects 50
}

$rectangle = new Rectangle();
echo resizeRectangle($rectangle); // 50 - correct

$square = new Square();
echo resizeRectangle($square); // 100 - unexpected! LSP violated

// Another violation example
class Bird
{
    public function fly(): void
    {
        echo "Flying...";
    }
}

class Penguin extends Bird
{
    public function fly(): void
    {
        // Penguins can't fly - throws exception
        throw new LogicException("Penguins can't fly!");
    }
}

function makeBirdFly(Bird $bird): void
{
    $bird->fly(); // Throws if penguin
}
```

## Good Example

```php
<?php

declare(strict_types=1);

// Use interface for common behavior
interface Shape
{
    public function getArea(): int;
}

// Rectangle and Square are separate implementations
readonly class Rectangle implements Shape
{
    public function __construct(
        private int $width,
        private int $height,
    ) {}

    public function getArea(): int
    {
        return $this->width * $this->height;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }
}

readonly class Square implements Shape
{
    public function __construct(
        private int $side,
    ) {}

    public function getArea(): int
    {
        return $this->side * $this->side;
    }

    public function getSide(): int
    {
        return $this->side;
    }
}

// Both work correctly with the Shape interface
function calculateTotalArea(array $shapes): int
{
    return array_sum(
        array_map(fn(Shape $shape) => $shape->getArea(), $shapes)
    );
}

$shapes = [
    new Rectangle(5, 10),  // 50
    new Square(5),         // 25
];

echo calculateTotalArea($shapes); // 75 - correct

// Bird example fixed with proper abstraction
interface Bird
{
    public function eat(): void;
    public function sleep(): void;
}

interface FlyingBird extends Bird
{
    public function fly(): void;
}

interface SwimmingBird extends Bird
{
    public function swim(): void;
}

class Sparrow implements FlyingBird
{
    public function eat(): void
    {
        echo "Eating seeds...";
    }

    public function sleep(): void
    {
        echo "Sleeping in nest...";
    }

    public function fly(): void
    {
        echo "Flying through the air...";
    }
}

class Penguin implements SwimmingBird
{
    public function eat(): void
    {
        echo "Eating fish...";
    }

    public function sleep(): void
    {
        echo "Sleeping on ice...";
    }

    public function swim(): void
    {
        echo "Swimming in the ocean...";
    }
}

// Only flying birds are passed to this function
function makeBirdsFly(FlyingBird ...$birds): void
{
    foreach ($birds as $bird) {
        $bird->fly();
    }
}
```

### Contract Preservation Example

```php
<?php

declare(strict_types=1);

interface PaymentGateway
{
    /**
     * Process a payment.
     *
     * @throws InsufficientFundsException When balance is insufficient
     * @throws PaymentDeclinedException When payment is declined
     */
    public function charge(Money $amount, PaymentMethod $method): PaymentResult;

    /**
     * Refund must return the same currency as the original payment.
     */
    public function refund(PaymentId $paymentId, Money $amount): RefundResult;
}

// Correct - follows the contract
class StripeGateway implements PaymentGateway
{
    public function charge(Money $amount, PaymentMethod $method): PaymentResult
    {
        try {
            $charge = $this->stripe->charges->create([
                'amount' => $amount->cents,
                'currency' => $amount->currency,
            ]);

            return new PaymentResult(
                id: new PaymentId($charge->id),
                amount: $amount,
                status: PaymentStatus::Completed,
            );
        } catch (StripeException $e) {
            if ($e->getCode() === 'insufficient_funds') {
                throw new InsufficientFundsException($e->getMessage());
            }
            throw new PaymentDeclinedException($e->getMessage());
        }
    }

    public function refund(PaymentId $paymentId, Money $amount): RefundResult
    {
        // Returns same currency as documented
        $refund = $this->stripe->refunds->create([
            'charge' => $paymentId->value,
            'amount' => $amount->cents,
        ]);

        return new RefundResult(
            id: new RefundId($refund->id),
            amount: $amount,
        );
    }
}

// Also correct - different implementation, same contract
class PayPalGateway implements PaymentGateway
{
    public function charge(Money $amount, PaymentMethod $method): PaymentResult
    {
        // Different implementation, same exceptions for same conditions
        // Same return type with same semantics
    }

    public function refund(PaymentId $paymentId, Money $amount): RefundResult
    {
        // Same contract honored
    }
}
```

## Why

- **Substitutability**: Subclasses work in place of parent classes
- **Reliability**: Code using base types works with any subtype
- **Polymorphism**: True polymorphism requires LSP compliance
- **Testing**: Base type tests apply to all subtypes
- **Design Quality**: Violations indicate wrong inheritance hierarchy
- **Maintenance**: Changing subtype implementation won't break callers
