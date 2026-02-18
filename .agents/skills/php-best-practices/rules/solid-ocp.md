---
title: Open/Closed Principle
impact: HIGH
impactDescription: Extend without modifying, reduces regression risk
tags: solid, ocp, design-principles, extension
---

# Open/Closed Principle (OCP)

Classes should be open for extension but closed for modification.

## Bad Example

```php
<?php

declare(strict_types=1);

// Must modify this class every time a new payment method is added
class PaymentProcessor
{
    public function process(string $type, float $amount): PaymentResult
    {
        // Adding new payment type requires modifying this method
        if ($type === 'credit_card') {
            // Credit card processing logic
            $fee = $amount * 0.029;
            return new PaymentResult($amount + $fee, 'credit_card');
        }

        if ($type === 'paypal') {
            // PayPal processing logic
            $fee = $amount * 0.035;
            return new PaymentResult($amount + $fee, 'paypal');
        }

        if ($type === 'bank_transfer') {
            // Bank transfer logic
            $fee = 1.00;
            return new PaymentResult($amount + $fee, 'bank_transfer');
        }

        // Adding crypto? Must modify this class again!
        if ($type === 'crypto') {
            $fee = $amount * 0.01;
            return new PaymentResult($amount + $fee, 'crypto');
        }

        throw new InvalidArgumentException("Unknown payment type: {$type}");
    }
}

// Same problem with discount calculation
class DiscountCalculator
{
    public function calculate(string $type, float $amount): float
    {
        return match ($type) {
            'percentage' => $amount * 0.10,
            'fixed' => 5.00,
            'buy_one_get_one' => $amount / 2,
            // Adding new discount type = modifying this class
            default => 0.0,
        };
    }
}
```

## Good Example

```php
<?php

declare(strict_types=1);

// Define contract for payment methods
interface PaymentMethod
{
    public function process(Money $amount): PaymentResult;
    public function calculateFee(Money $amount): Money;
    public function getName(): string;
}

// Each payment method is a separate class - closed for modification
class CreditCardPayment implements PaymentMethod
{
    private const FEE_PERCENTAGE = 0.029;

    public function __construct(
        private PaymentGateway $gateway,
    ) {}

    public function process(Money $amount): PaymentResult
    {
        $totalAmount = $amount->add($this->calculateFee($amount));
        return $this->gateway->charge($totalAmount);
    }

    public function calculateFee(Money $amount): Money
    {
        return $amount->multiply(self::FEE_PERCENTAGE);
    }

    public function getName(): string
    {
        return 'credit_card';
    }
}

class PayPalPayment implements PaymentMethod
{
    private const FEE_PERCENTAGE = 0.035;

    public function __construct(
        private PayPalClient $client,
    ) {}

    public function process(Money $amount): PaymentResult
    {
        $totalAmount = $amount->add($this->calculateFee($amount));
        return $this->client->createPayment($totalAmount);
    }

    public function calculateFee(Money $amount): Money
    {
        return $amount->multiply(self::FEE_PERCENTAGE);
    }

    public function getName(): string
    {
        return 'paypal';
    }
}

// New payment method - extend without modifying existing code
class CryptoPayment implements PaymentMethod
{
    private const FEE_PERCENTAGE = 0.01;

    public function __construct(
        private CryptoGateway $gateway,
    ) {}

    public function process(Money $amount): PaymentResult
    {
        $totalAmount = $amount->add($this->calculateFee($amount));
        return $this->gateway->processPayment($totalAmount);
    }

    public function calculateFee(Money $amount): Money
    {
        return $amount->multiply(self::FEE_PERCENTAGE);
    }

    public function getName(): string
    {
        return 'crypto';
    }
}

// Payment processor is closed for modification
class PaymentProcessor
{
    /** @var array<string, PaymentMethod> */
    private array $methods = [];

    public function registerMethod(PaymentMethod $method): void
    {
        $this->methods[$method->getName()] = $method;
    }

    public function process(string $methodName, Money $amount): PaymentResult
    {
        if (!isset($this->methods[$methodName])) {
            throw new UnsupportedPaymentMethodException($methodName);
        }

        return $this->methods[$methodName]->process($amount);
    }
}

// Usage with dependency injection
class PaymentServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(PaymentProcessor::class, function ($c) {
            $processor = new PaymentProcessor();
            $processor->registerMethod($c->make(CreditCardPayment::class));
            $processor->registerMethod($c->make(PayPalPayment::class));
            $processor->registerMethod($c->make(CryptoPayment::class));
            return $processor;
        });
    }
}
```

### Strategy Pattern Example

```php
<?php

declare(strict_types=1);

// Discount strategy interface
interface DiscountStrategy
{
    public function calculate(Money $amount): Money;
    public function getDescription(): string;
}

class PercentageDiscount implements DiscountStrategy
{
    public function __construct(
        private float $percentage,
    ) {}

    public function calculate(Money $amount): Money
    {
        return $amount->multiply($this->percentage);
    }

    public function getDescription(): string
    {
        return sprintf('%d%% off', $this->percentage * 100);
    }
}

class FixedAmountDiscount implements DiscountStrategy
{
    public function __construct(
        private Money $discountAmount,
    ) {}

    public function calculate(Money $amount): Money
    {
        return $this->discountAmount->min($amount);
    }

    public function getDescription(): string
    {
        return sprintf('%s off', $this->discountAmount->format());
    }
}

class BuyOneGetOneFreeDiscount implements DiscountStrategy
{
    public function calculate(Money $amount): Money
    {
        return $amount->divide(2);
    }

    public function getDescription(): string
    {
        return 'Buy one get one free';
    }
}

// Closed for modification, open for new discount strategies
class DiscountCalculator
{
    public function apply(Money $amount, DiscountStrategy $strategy): Money
    {
        $discount = $strategy->calculate($amount);
        return $amount->subtract($discount);
    }
}
```

## Why

- **No Regression Risk**: Existing code isn't modified when adding features
- **Easy Extension**: New functionality via new classes, not changes
- **Better Testing**: Existing tests remain valid
- **Plugin Architecture**: Easy to add new behaviors at runtime
- **Team Parallelism**: Different team members add features independently
- **Framework Integration**: Works well with DI containers
