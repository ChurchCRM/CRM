---
title: Class Naming Conventions
impact: HIGH
impactDescription: Clear communication through consistent naming patterns
tags: psr, naming, conventions, readability
---

# Class Naming Conventions

Use PascalCase for class names with descriptive, intention-revealing names.

## Bad Example

```php
<?php

declare(strict_types=1);

// Wrong: Lowercase, underscores
class user_service
{
}

// Wrong: Abbreviations
class UsrMgr
{
}

// Wrong: Generic names
class Manager
{
}

class Data
{
}

class Helper
{
}

// Wrong: Verb as class name (sounds like method)
class GetUser
{
}

// Wrong: Inconsistent suffixes
class UserProcess
{
}

class OrderHandler
{
}

class ProductManager
{
}
```

## Good Example

```php
<?php

declare(strict_types=1);

// Domain entities - nouns
class User
{
}

class Order
{
}

class Product
{
}

// Services - NounService pattern
class UserService
{
}

class OrderService
{
}

class PaymentService
{
}

// Repositories - NounRepository pattern
class UserRepository
{
}

class OrderRepository
{
}

// Controllers - NounController pattern
class UserController
{
}

class OrderController
{
}

// Factories - NounFactory pattern
class UserFactory
{
}

class OrderFactory
{
}

// Commands/Actions - VerbNounCommand pattern
class CreateUserCommand
{
}

class ProcessPaymentCommand
{
}

class SendEmailCommand
{
}

// Handlers - NounHandler pattern
class CreateUserHandler
{
}

class PaymentFailedHandler
{
}

// Events - PastTenseNoun pattern
class UserCreated
{
}

class OrderShipped
{
}

class PaymentFailed
{
}

// Exceptions - DescriptiveException pattern
class UserNotFoundException
{
}

class InvalidPaymentMethodException
{
}

class InsufficientFundsException
{
}

// Value Objects - descriptive nouns
class EmailAddress
{
}

class Money
{
}

class DateRange
{
}

// Interfaces - Adjective-able or Noun pattern
interface Cacheable
{
}

interface Serializable
{
}

interface UserRepository
{
}

interface PaymentGateway
{
}

// Abstract classes - AbstractNoun or BaseNoun
abstract class AbstractRepository
{
}

abstract class BaseController
{
}
```

### Naming Patterns by Type

```php
<?php

declare(strict_types=1);

namespace App\Domain\Order;

// Entity - simple noun
class Order
{
    public function __construct(
        private OrderId $id,
        private CustomerId $customerId,
        private OrderStatus $status,
    ) {}
}

// Value Object - descriptive noun
readonly class OrderId
{
    public function __construct(
        public string $value,
    ) {}
}

// Enum - singular noun
enum OrderStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
}

// Repository Interface - NounRepository
interface OrderRepository
{
    public function find(OrderId $id): ?Order;
    public function save(Order $order): void;
}

// Service - NounService
class OrderService
{
    public function __construct(
        private OrderRepository $repository,
    ) {}
}

// Event - past tense
readonly class OrderCreated
{
    public function __construct(
        public OrderId $orderId,
        public DateTimeImmutable $occurredAt,
    ) {}
}

// Command - imperative
readonly class CreateOrder
{
    public function __construct(
        public CustomerId $customerId,
        public array $items,
    ) {}
}
```

## Why

- **Clarity**: PascalCase visually distinguishes classes from variables/functions
- **Consistency**: Standard naming patterns make code predictable
- **Discoverability**: Suffixes (Service, Repository) indicate purpose
- **Communication**: Names reveal intent to other developers
- **IDE Support**: Consistent naming improves autocompletion
- **PSR Compliance**: Follows PHP-FIG recommendations
