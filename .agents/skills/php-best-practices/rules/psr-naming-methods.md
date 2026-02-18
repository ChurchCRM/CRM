---
title: Method Naming Conventions
impact: MEDIUM
impactDescription: Clear intent through descriptive method names
tags: psr, naming, methods, conventions
---

# Method Naming Conventions

Use camelCase for method names with clear, action-oriented verbs.

## Bad Example

```php
<?php

declare(strict_types=1);

class UserService
{
    // Wrong: Underscores
    public function get_user_by_id(int $id): ?User
    {
    }

    // Wrong: Starts with uppercase
    public function FindActive(): array
    {
    }

    // Wrong: No verb - unclear what it does
    public function user(int $id): User
    {
    }

    // Wrong: Abbreviations
    public function getUsrByEml(string $email): ?User
    {
    }

    // Wrong: Boolean method doesn't read as question
    public function checkActive(): bool
    {
    }

    // Wrong: Vague names
    public function process(): void
    {
    }

    public function handle(): void
    {
    }

    public function doStuff(): void
    {
    }
}
```

## Good Example

```php
<?php

declare(strict_types=1);

class UserService
{
    // Finder methods - findBy pattern
    public function find(int $id): ?User
    {
        return $this->repository->find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->repository->findByEmail($email);
    }

    public function findOrFail(int $id): User
    {
        return $this->repository->find($id)
            ?? throw new UserNotFoundException($id);
    }

    // Collection getters - get + plural
    public function getActiveUsers(): array
    {
        return $this->repository->findActive();
    }

    public function getAllByRole(Role $role): array
    {
        return $this->repository->findByRole($role);
    }

    // Boolean methods - is/has/can/should prefix
    public function isActive(User $user): bool
    {
        return $user->status === UserStatus::Active;
    }

    public function hasPermission(User $user, string $permission): bool
    {
        return in_array($permission, $user->getPermissions(), true);
    }

    public function canEdit(User $user, Resource $resource): bool
    {
        return $user->getId() === $resource->getOwnerId()
            || $this->hasPermission($user, 'edit_all');
    }

    public function shouldNotify(User $user): bool
    {
        return $user->preferences->notificationsEnabled;
    }

    // Action methods - verb + noun
    public function create(CreateUserDto $dto): User
    {
        return $this->repository->create($dto);
    }

    public function update(User $user, UpdateUserDto $dto): User
    {
        return $this->repository->update($user, $dto);
    }

    public function delete(User $user): void
    {
        $this->repository->delete($user);
    }

    public function activate(User $user): void
    {
        $user->activate();
        $this->repository->save($user);
    }

    public function deactivate(User $user): void
    {
        $user->deactivate();
        $this->repository->save($user);
    }

    // Conversion methods - to + target type
    public function toArray(User $user): array
    {
        return $user->toArray();
    }

    public function toDto(User $user): UserDto
    {
        return UserDto::fromEntity($user);
    }

    // Calculation methods - calculate/compute prefix
    public function calculateAge(User $user): int
    {
        return $user->birthDate->diff(new DateTimeImmutable())->y;
    }

    public function computeDiscount(User $user): float
    {
        return match ($user->tier) {
            UserTier::Gold => 0.20,
            UserTier::Silver => 0.10,
            default => 0.0,
        };
    }
}
```

### Common Method Prefixes

```php
<?php

declare(strict_types=1);

class ExampleService
{
    // Getters - get + property/computed value
    public function getId(): int { }
    public function getName(): string { }
    public function getFullName(): string { }

    // Setters - set + property
    public function setName(string $name): void { }
    public function setStatus(Status $status): void { }

    // Boolean checks - is/has/can/should/was/will
    public function isValid(): bool { }
    public function isEmpty(): bool { }
    public function hasItems(): bool { }
    public function hasBeenModified(): bool { }
    public function canProcess(): bool { }
    public function shouldRetry(): bool { }
    public function wasSuccessful(): bool { }
    public function willExpire(): bool { }

    // Actions - verb + noun (specific)
    public function sendEmail(Email $email): void { }
    public function processPayment(Payment $payment): void { }
    public function generateReport(ReportType $type): Report { }
    public function validateInput(array $input): ValidationResult { }
    public function parseResponse(string $response): array { }
    public function formatDate(DateTimeInterface $date): string { }

    // CRUD operations
    public function create(array $data): Entity { }
    public function read(int $id): ?Entity { }
    public function update(Entity $entity, array $data): Entity { }
    public function delete(Entity $entity): void { }

    // Collection operations
    public function add(Item $item): void { }
    public function remove(Item $item): void { }
    public function contains(Item $item): bool { }
    public function count(): int { }
    public function clear(): void { }
}
```

## Why

- **Readability**: camelCase is PHP convention for methods
- **Intent**: Verb prefixes reveal the action being performed
- **Boolean Clarity**: is/has/can prefixes make conditionals read naturally
- **Consistency**: Standard prefixes make APIs predictable
- **Self-Documenting**: Good names eliminate need for comments
- **IDE Support**: Consistent naming improves autocomplete suggestions
