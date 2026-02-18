---
title: Readonly Classes
impact: HIGH
impactDescription: Enforces complete immutability for value objects and DTOs
tags: modern-features, readonly, immutability, value-objects, php82
---

# Readonly Classes

Use readonly classes when all properties should be immutable (PHP 8.2+).

## Bad Example

```php
<?php

declare(strict_types=1);

// Marking each property as readonly individually
class EmailAddress
{
    public function __construct(
        public readonly string $local,
        public readonly string $domain,
    ) {}
}

// Easy to forget readonly on new properties
class PersonName
{
    public function __construct(
        public readonly string $firstName,
        public readonly string $lastName,
        public string $middleName = '', // Oops! Forgot readonly
    ) {}
}

// Verbose for classes with many properties
class ShippingAddress
{
    public function __construct(
        public readonly string $street,
        public readonly string $city,
        public readonly string $state,
        public readonly string $zipCode,
        public readonly string $country,
    ) {}
}
```

## Good Example

```php
<?php

declare(strict_types=1);

// Readonly class - all properties are automatically readonly
readonly class EmailAddress
{
    public function __construct(
        public string $local,
        public string $domain,
    ) {
        if (!filter_var("$local@$domain", FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email address');
        }
    }

    public function toString(): string
    {
        return "{$this->local}@{$this->domain}";
    }

    public function equals(self $other): bool
    {
        return $this->local === $other->local
            && $this->domain === $other->domain;
    }
}

readonly class PersonName
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $middleName = '',
    ) {}

    public function getFullName(): string
    {
        return trim("{$this->firstName} {$this->middleName} {$this->lastName}");
    }
}

readonly class ShippingAddress
{
    public function __construct(
        public string $street,
        public string $city,
        public string $state,
        public string $zipCode,
        public string $country,
    ) {}

    public function format(): string
    {
        return implode("\n", [
            $this->street,
            "{$this->city}, {$this->state} {$this->zipCode}",
            $this->country,
        ]);
    }
}

// DTOs are perfect candidates for readonly classes
readonly class CreateUserRequest
{
    public function __construct(
        public string $email,
        public string $password,
        public string $name,
        public ?string $phone = null,
    ) {}
}

// Event objects should be immutable
readonly class UserCreatedEvent
{
    public function __construct(
        public int $userId,
        public string $email,
        public DateTimeImmutable $occurredAt,
    ) {}
}
```

## Why

- **Less Verbose**: No need to repeat readonly on each property
- **Enforced Immutability**: New properties are automatically readonly
- **Value Objects**: Ideal for implementing value object pattern
- **DTOs**: Perfect for data transfer objects
- **Events**: Event objects should be immutable by design
- **Clear Intent**: Class declaration signals complete immutability
