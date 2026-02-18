---
title: Enum Methods
impact: HIGH
impactDescription: Encapsulate behavior within enums, reduce external helpers
tags: modern-features, enums, methods, encapsulation, php81
---

# Enum Methods

Add methods to enums to encapsulate related behavior and logic.

## Bad Example

```php
<?php

declare(strict_types=1);

enum UserRole: string
{
    case Admin = 'admin';
    case Editor = 'editor';
    case Viewer = 'viewer';
}

// Logic scattered outside the enum
class PermissionChecker
{
    public function canEdit(UserRole $role): bool
    {
        return $role === UserRole::Admin || $role === UserRole::Editor;
    }

    public function canDelete(UserRole $role): bool
    {
        return $role === UserRole::Admin;
    }

    public function canView(UserRole $role): bool
    {
        return true; // All roles can view
    }

    public function getLabel(UserRole $role): string
    {
        return match ($role) {
            UserRole::Admin => 'Administrator',
            UserRole::Editor => 'Content Editor',
            UserRole::Viewer => 'Read-only Viewer',
        };
    }
}
```

## Good Example

```php
<?php

declare(strict_types=1);

enum UserRole: string
{
    case Admin = 'admin';
    case Editor = 'editor';
    case Viewer = 'viewer';

    // Permission methods directly on the enum
    public function canEdit(): bool
    {
        return match ($this) {
            self::Admin, self::Editor => true,
            self::Viewer => false,
        };
    }

    public function canDelete(): bool
    {
        return $this === self::Admin;
    }

    public function canView(): bool
    {
        return true;
    }

    public function canManageUsers(): bool
    {
        return $this === self::Admin;
    }

    // Human-readable label
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Editor => 'Content Editor',
            self::Viewer => 'Read-only Viewer',
        };
    }

    // Get permissions array
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => ['view', 'edit', 'delete', 'manage_users'],
            self::Editor => ['view', 'edit'],
            self::Viewer => ['view'],
        };
    }

    // Static factory methods
    public static function default(): self
    {
        return self::Viewer;
    }

    public static function fromPermissionLevel(int $level): self
    {
        return match (true) {
            $level >= 100 => self::Admin,
            $level >= 50 => self::Editor,
            default => self::Viewer,
        };
    }
}

// More complex enum with interface implementation
interface Describable
{
    public function description(): string;
}

enum OrderStatus: string implements Describable
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function description(): string
    {
        return match ($this) {
            self::Pending => 'Order is awaiting processing',
            self::Processing => 'Order is being prepared',
            self::Shipped => 'Order has been shipped',
            self::Delivered => 'Order has been delivered',
            self::Cancelled => 'Order was cancelled',
        };
    }

    public function isFinal(): bool
    {
        return match ($this) {
            self::Delivered, self::Cancelled => true,
            default => false,
        };
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::Pending => in_array($newStatus, [self::Processing, self::Cancelled]),
            self::Processing => in_array($newStatus, [self::Shipped, self::Cancelled]),
            self::Shipped => $newStatus === self::Delivered,
            self::Delivered, self::Cancelled => false,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => '#FFA500',
            self::Processing => '#0000FF',
            self::Shipped => '#800080',
            self::Delivered => '#008000',
            self::Cancelled => '#FF0000',
        };
    }
}

// Usage
$role = UserRole::Editor;
if ($role->canEdit()) {
    // Edit content
}

$status = OrderStatus::Processing;
if ($status->canTransitionTo(OrderStatus::Shipped)) {
    // Allow status change
}
```

## Why

- **Encapsulation**: Behavior lives with the data it operates on
- **Single Source of Truth**: All enum-related logic in one place
- **Type Safety**: Methods have access to `$this` for the current case
- **Exhaustive Matching**: Static analysis ensures all cases are handled
- **Interface Support**: Enums can implement interfaces
- **Clean Architecture**: No need for separate helper classes
