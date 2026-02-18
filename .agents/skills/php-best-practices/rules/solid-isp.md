---
title: Interface Segregation Principle
impact: HIGH
impactDescription: Focused interfaces, no forced implementations
tags: solid, isp, design-principles, interfaces
---

# Interface Segregation Principle (ISP)

Clients should not be forced to depend on interfaces they do not use.

## Bad Example

```php
<?php

declare(strict_types=1);

// Fat interface - forces implementers to define methods they don't need
interface WorkerInterface
{
    public function work(): void;
    public function eat(): void;
    public function sleep(): void;
    public function attendMeeting(): void;
    public function submitTimesheet(): void;
    public function requestVacation(): void;
}

// Robot must implement methods that don't make sense for it
class Robot implements WorkerInterface
{
    public function work(): void
    {
        echo "Working...";
    }

    public function eat(): void
    {
        // Robots don't eat - forced to implement anyway
        throw new LogicException("Robots don't eat");
    }

    public function sleep(): void
    {
        // Robots don't sleep
        throw new LogicException("Robots don't sleep");
    }

    public function attendMeeting(): void
    {
        throw new LogicException("Robots don't attend meetings");
    }

    public function submitTimesheet(): void
    {
        throw new LogicException("Robots don't submit timesheets");
    }

    public function requestVacation(): void
    {
        throw new LogicException("Robots don't take vacation");
    }
}

// Another bloated interface
interface RepositoryInterface
{
    public function find(int $id): ?object;
    public function findAll(): array;
    public function create(array $data): object;
    public function update(int $id, array $data): object;
    public function delete(int $id): void;
    public function paginate(int $page, int $perPage): array;
    public function search(string $query): array;
    public function count(): int;
    public function exists(int $id): bool;
    public function createMany(array $records): array;
    public function deleteMany(array $ids): void;
}
```

## Good Example

```php
<?php

declare(strict_types=1);

// Segregated interfaces - each has a focused purpose
interface Workable
{
    public function work(): void;
}

interface Eatable
{
    public function eat(): void;
}

interface Sleepable
{
    public function sleep(): void;
}

interface MeetingAttendee
{
    public function attendMeeting(): void;
}

interface TimesheetSubmitter
{
    public function submitTimesheet(): void;
}

interface VacationRequester
{
    public function requestVacation(): void;
}

// Human worker implements all relevant interfaces
class HumanWorker implements
    Workable,
    Eatable,
    Sleepable,
    MeetingAttendee,
    TimesheetSubmitter,
    VacationRequester
{
    public function work(): void
    {
        echo "Working on tasks...";
    }

    public function eat(): void
    {
        echo "Taking lunch break...";
    }

    public function sleep(): void
    {
        echo "Resting after work...";
    }

    public function attendMeeting(): void
    {
        echo "Attending team meeting...";
    }

    public function submitTimesheet(): void
    {
        echo "Submitting weekly timesheet...";
    }

    public function requestVacation(): void
    {
        echo "Requesting PTO...";
    }
}

// Robot only implements what makes sense
class Robot implements Workable
{
    public function work(): void
    {
        echo "Performing automated tasks...";
    }
}

// Contractor doesn't get vacation
class Contractor implements Workable, Eatable, TimesheetSubmitter
{
    public function work(): void
    {
        echo "Working on contract...";
    }

    public function eat(): void
    {
        echo "Lunch break...";
    }

    public function submitTimesheet(): void
    {
        echo "Submitting contractor timesheet...";
    }
}
```

### Repository Example with Segregated Interfaces

```php
<?php

declare(strict_types=1);

// Core read operations
interface ReadableRepository
{
    public function find(int $id): ?object;
    public function findAll(): array;
    public function exists(int $id): bool;
}

// Core write operations
interface WritableRepository
{
    public function create(array $data): object;
    public function update(int $id, array $data): object;
    public function delete(int $id): void;
}

// Optional pagination capability
interface PaginatableRepository
{
    public function paginate(int $page, int $perPage): PaginatedResult;
}

// Optional search capability
interface SearchableRepository
{
    public function search(string $query): array;
}

// Optional bulk operations
interface BulkWritableRepository
{
    public function createMany(array $records): array;
    public function deleteMany(array $ids): void;
}

// Full-featured repository for normal entities
class UserRepository implements
    ReadableRepository,
    WritableRepository,
    PaginatableRepository,
    SearchableRepository
{
    public function find(int $id): ?User
    {
        // Implementation
    }

    public function findAll(): array
    {
        // Implementation
    }

    public function exists(int $id): bool
    {
        // Implementation
    }

    public function create(array $data): User
    {
        // Implementation
    }

    public function update(int $id, array $data): User
    {
        // Implementation
    }

    public function delete(int $id): void
    {
        // Implementation
    }

    public function paginate(int $page, int $perPage): PaginatedResult
    {
        // Implementation
    }

    public function search(string $query): array
    {
        // Implementation
    }
}

// Read-only repository for audit logs
class AuditLogRepository implements ReadableRepository, PaginatableRepository
{
    public function find(int $id): ?AuditLog
    {
        // Implementation
    }

    public function findAll(): array
    {
        // Implementation
    }

    public function exists(int $id): bool
    {
        // Implementation
    }

    public function paginate(int $page, int $perPage): PaginatedResult
    {
        // Implementation
    }

    // No write methods - audit logs are immutable
}

// Service only needs what it uses
class UserListService
{
    public function __construct(
        private ReadableRepository&PaginatableRepository $repository,
    ) {}

    public function getPage(int $page): PaginatedResult
    {
        return $this->repository->paginate($page, 20);
    }
}
```

## Why

- **No Dead Code**: Classes don't implement unused methods
- **Focused Contracts**: Each interface has clear, specific purpose
- **Flexibility**: Clients depend only on what they need
- **Easier Testing**: Mock only the interfaces actually used
- **Better Design**: Promotes composition over inheritance
- **Decoupling**: Changes to one interface don't affect unrelated clients
