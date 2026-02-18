---
title: Intersection Types
impact: MEDIUM
impactDescription: Enforces multiple interface implementations for composition
tags: type-system, intersection-types, interfaces, php81
---

# Intersection Types

Use intersection types when a value must implement multiple interfaces (PHP 8.1+).

## Bad Example

```php
<?php

declare(strict_types=1);

interface Cacheable
{
    public function getCacheKey(): string;
}

interface Serializable
{
    public function serialize(): string;
}

class CacheService
{
    /**
     * @param Cacheable&Serializable $item
     */
    public function store($item): void
    {
        // No type enforcement - relies on docblock
        // Could receive object implementing only one interface
        $key = $item->getCacheKey();
        $data = $item->serialize();
        $this->cache->set($key, $data);
    }
}
```

## Good Example

```php
<?php

declare(strict_types=1);

interface Cacheable
{
    public function getCacheKey(): string;
    public function getCacheTtl(): int;
}

interface Serializable
{
    public function serialize(): string;
    public function unserialize(string $data): void;
}

class CacheService
{
    // Object MUST implement both interfaces
    public function store(Cacheable&Serializable $item): void
    {
        $key = $item->getCacheKey();
        $data = $item->serialize();
        $ttl = $item->getCacheTtl();

        $this->cache->set($key, $data, $ttl);
    }

    public function retrieve(
        string $key,
        Cacheable&Serializable $prototype
    ): Cacheable&Serializable {
        $data = $this->cache->get($key);
        $prototype->unserialize($data);
        return $prototype;
    }
}

// Implementation example
class User implements Cacheable, Serializable
{
    public function __construct(
        private int $id,
        private string $name
    ) {}

    public function getCacheKey(): string
    {
        return "user:{$this->id}";
    }

    public function getCacheTtl(): int
    {
        return 3600;
    }

    public function serialize(): string
    {
        return json_encode(['id' => $this->id, 'name' => $this->name]);
    }

    public function unserialize(string $data): void
    {
        $decoded = json_decode($data, true);
        $this->id = $decoded['id'];
        $this->name = $decoded['name'];
    }
}
```

## Why

- **Compound Requirements**: Enforces multiple interface implementations
- **Type Safety**: Compiler enforces all required capabilities
- **Composition**: Enables type-safe composition over inheritance
- **Clear Contracts**: Explicitly states all required behaviors
- **Better Than Base Classes**: More flexible than requiring a specific base class
- **Static Analysis**: Full support in PHPStan and Psalm
