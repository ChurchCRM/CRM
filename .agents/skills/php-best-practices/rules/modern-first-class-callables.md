---
title: First-Class Callable Syntax
impact: MEDIUM
impactDescription: Concise callable references with IDE support
tags: modern-features, first-class-callables, closures, php81
---

# First-Class Callable Syntax

Use first-class callable syntax to create closures from callables (PHP 8.1+).

## Bad Example

```php
<?php

declare(strict_types=1);

class StringProcessor
{
    public function toUpperCase(string $str): string
    {
        return strtoupper($str);
    }

    public function toLowerCase(string $str): string
    {
        return strtolower($str);
    }
}

// Old way - verbose Closure::fromCallable
$processor = new StringProcessor();
$upper = Closure::fromCallable([$processor, 'toUpperCase']);
$lower = Closure::fromCallable([$processor, 'toLowerCase']);

// String-based callable - no IDE support, error-prone
$callback = [$processor, 'toUpperCase'];
array_map($callback, $strings); // Works but fragile

// Static methods
$formatter = Closure::fromCallable([DateFormatter::class, 'format']);

// Functions
$trimmer = Closure::fromCallable('trim');
```

## Good Example

```php
<?php

declare(strict_types=1);

class StringProcessor
{
    public function toUpperCase(string $str): string
    {
        return strtoupper($str);
    }

    public function toLowerCase(string $str): string
    {
        return strtolower($str);
    }

    public function getProcessors(): array
    {
        // First-class callable syntax - concise and type-safe
        return [
            'upper' => $this->toUpperCase(...),
            'lower' => $this->toLowerCase(...),
        ];
    }
}

// Instance methods
$processor = new StringProcessor();
$upper = $processor->toUpperCase(...);
$lower = $processor->toLowerCase(...);

$strings = ['hello', 'world'];
$uppercased = array_map($upper, $strings); // ['HELLO', 'WORLD']

// Static methods
class DateFormatter
{
    public static function format(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }

    public static function formatTime(DateTimeInterface $date): string
    {
        return $date->format('H:i:s');
    }
}

$formatDate = DateFormatter::format(...);
$formatTime = DateFormatter::formatTime(...);

$dates = [new DateTime(), new DateTime('+1 day')];
$formatted = array_map($formatDate, $dates);

// Built-in functions
$trim = trim(...);
$strlen = strlen(...);
$strtoupper = strtoupper(...);

$cleaned = array_map($trim, $dirtyStrings);
$lengths = array_map($strlen, $strings);

// Constructor as callable
class User
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}
}

// Can't use new directly, but can wrap
$createUser = fn(array $data) => new User($data['name'], $data['email']);

// Practical use cases
class EventDispatcher
{
    /** @var array<string, Closure[]> */
    private array $listeners = [];

    public function subscribe(string $event, Closure $listener): void
    {
        $this->listeners[$event][] = $listener;
    }
}

class UserController
{
    public function __construct(
        private EventDispatcher $dispatcher,
        private UserService $service,
    ) {
        // Subscribe methods as first-class callables
        $this->dispatcher->subscribe('user.created', $this->onUserCreated(...));
        $this->dispatcher->subscribe('user.deleted', $this->onUserDeleted(...));
    }

    private function onUserCreated(User $user): void
    {
        // Handle event
    }

    private function onUserDeleted(int $userId): void
    {
        // Handle event
    }
}

// Pipeline pattern
class Pipeline
{
    /** @var Closure[] */
    private array $stages = [];

    public function pipe(Closure $stage): self
    {
        $this->stages[] = $stage;
        return $this;
    }

    public function process(mixed $payload): mixed
    {
        return array_reduce(
            $this->stages,
            fn($carry, $stage) => $stage($carry),
            $payload
        );
    }
}

$pipeline = new Pipeline();
$pipeline
    ->pipe(trim(...))
    ->pipe(strtolower(...))
    ->pipe($processor->toUpperCase(...));

$result = $pipeline->process('  Hello World  '); // 'HELLO WORLD'
```

## Why

- **Concise Syntax**: `$obj->method(...)` instead of `Closure::fromCallable()`
- **IDE Support**: Full autocompletion, refactoring, and navigation
- **Type Safety**: Closures maintain the callable's type signature
- **Refactoring Safe**: Renaming methods updates references automatically
- **Consistency**: Same syntax for instance, static, and global functions
- **Functional PHP**: Enables cleaner functional programming patterns
