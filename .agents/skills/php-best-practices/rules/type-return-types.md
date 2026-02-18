---
title: Return Type Declarations
impact: CRITICAL
impactDescription: Enforces type contracts, enables static analysis
tags: type-system, return-types, type-safety, php7
---

# Return Type Declarations

Always declare return types for all methods and functions.

## Bad Example

```php
<?php

declare(strict_types=1);

class UserRepository
{
    // No return type - unclear what this returns
    public function find(int $id)
    {
        return $this->db->query("SELECT * FROM users WHERE id = ?", [$id]);
    }

    // Could return anything
    public function getActiveUsers()
    {
        return $this->db->query("SELECT * FROM users WHERE active = 1");
    }
}
```

## Good Example

```php
<?php

declare(strict_types=1);

class UserRepository
{
    public function find(int $id): ?User
    {
        $data = $this->db->query("SELECT * FROM users WHERE id = ?", [$id]);
        return $data ? new User($data) : null;
    }

    /**
     * @return User[]
     */
    public function getActiveUsers(): array
    {
        $results = $this->db->query("SELECT * FROM users WHERE active = 1");
        return array_map(fn(array $data) => new User($data), $results);
    }

    public function delete(int $id): bool
    {
        return $this->db->execute("DELETE FROM users WHERE id = ?", [$id]);
    }

    public function save(User $user): void
    {
        $this->db->execute("INSERT INTO users ...", $user->toArray());
    }
}
```

## Why

- **Self-Documenting**: Return types serve as inline documentation
- **Type Safety**: Ensures methods return expected types
- **IDE Support**: Enables autocompletion and refactoring tools
- **Error Prevention**: Catches return type mismatches during development
- **Contract Enforcement**: Defines clear contracts between components
- **Static Analysis**: Enables tools like PHPStan and Psalm to find bugs
