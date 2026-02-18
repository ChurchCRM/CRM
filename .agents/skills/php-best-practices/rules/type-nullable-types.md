---
title: Nullable Types
impact: CRITICAL
impactDescription: Makes null handling explicit, prevents null pointer bugs
tags: type-system, nullable, null-safety, php71
---

# Nullable Types

Use nullable types explicitly when null is a valid value.

## Bad Example

```php
<?php

declare(strict_types=1);

class UserService
{
    // Unclear if null is valid
    public function findByEmail(string $email)
    {
        $data = $this->repository->findByEmail($email);
        if (!$data) {
            return null; // Surprise! Returns null
        }
        return new User($data);
    }

    // Default null but no nullable type
    public function setMiddleName(string $name = null): void
    {
        // This works but is confusing and deprecated pattern
        $this->middleName = $name;
    }

    // Returning null from non-nullable return type
    public function getActiveSubscription(): Subscription
    {
        // Bug: returns null but type says Subscription
        return $this->subscriptions->getActive();
    }
}
```

## Good Example

```php
<?php

declare(strict_types=1);

class UserService
{
    // Explicit nullable return type
    public function findByEmail(string $email): ?User
    {
        $data = $this->repository->findByEmail($email);
        return $data ? new User($data) : null;
    }

    // Explicit nullable parameter with modern syntax
    public function setMiddleName(?string $name): void
    {
        $this->middleName = $name;
    }

    // Clear that subscription might not exist
    public function getActiveSubscription(): ?Subscription
    {
        return $this->subscriptions->getActive();
    }

    // Alternative: throw exception when null is not acceptable
    public function getActiveSubscriptionOrFail(): Subscription
    {
        $subscription = $this->subscriptions->getActive();

        if ($subscription === null) {
            throw new NoActiveSubscriptionException();
        }

        return $subscription;
    }
}

// Proper null handling in calling code
$user = $userService->findByEmail($email);
if ($user !== null) {
    $user->sendWelcomeEmail();
}

// Or with null coalescing
$displayName = $user?->getDisplayName() ?? 'Guest';
```

## Why

- **Explicit Intent**: Makes null as a valid value intentional and clear
- **Null Safety**: Forces callers to handle the null case
- **No Surprises**: Eliminates unexpected null returns
- **IDE Support**: IDEs warn about potential null pointer access
- **Static Analysis**: Tools catch null-related bugs before runtime
- **Modern Syntax**: Use `?Type` instead of `Type|null` for brevity
