---
title: Property Type Declarations
impact: CRITICAL
impactDescription: Ensures state integrity, prevents invalid property assignments
tags: type-system, property-types, type-safety, php74
---

# Property Type Declarations

Always declare types for class properties (PHP 7.4+).

## Bad Example

```php
<?php

declare(strict_types=1);

class Product
{
    // No property types - state can be anything
    private $id;
    private $name;
    private $price;
    private $categories;
    private $createdAt;

    public function __construct($id, $name, $price)
    {
        $this->id = $id;           // Could be int, string, null...
        $this->name = $name;       // Could be anything
        $this->price = $price;     // Hope it's numeric
        $this->categories = [];
    }
}
```

## Good Example

```php
<?php

declare(strict_types=1);

class Product
{
    private int $id;
    private string $name;
    private float $price;
    private array $categories = [];
    private ?string $description = null;
    private DateTimeImmutable $createdAt;
    private ?DateTimeImmutable $updatedAt = null;

    public function __construct(
        int $id,
        string $name,
        float $price,
        ?DateTimeImmutable $createdAt = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->price = $price;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
```

## Why

- **State Integrity**: Properties always contain expected types
- **Null Safety**: Nullable types make null handling explicit
- **Initialization Enforcement**: Uninitialized typed properties throw errors
- **Documentation**: Property types serve as inline documentation
- **IDE Support**: Enables better autocompletion and refactoring
- **Static Analysis**: Tools can verify property usage throughout codebase
