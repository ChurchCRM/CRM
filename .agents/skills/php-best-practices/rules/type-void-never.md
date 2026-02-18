---
title: Void and Never Return Types
impact: HIGH
impactDescription: Clarifies method intent, enables better static analysis
tags: type-system, void, never, return-types, php71, php81
---

# Void and Never Return Types

Use void for methods that don't return a value, and never for methods that always throw or exit.

## Bad Example

```php
<?php

declare(strict_types=1);

class EventDispatcher
{
    // No return type - unclear if something is returned
    public function dispatch(Event $event)
    {
        foreach ($this->listeners as $listener) {
            $listener->handle($event);
        }
    }
}

class ExceptionHandler
{
    // Returns void but actually never returns
    public function handleFatal(Throwable $e): void
    {
        $this->logger->critical($e->getMessage());
        exit(1);
    }

    // Throws but return type doesn't indicate this
    public function abort(int $code): void
    {
        throw new HttpException($code);
    }
}
```

## Good Example

```php
<?php

declare(strict_types=1);

class EventDispatcher
{
    // Explicit void - method performs action, returns nothing
    public function dispatch(Event $event): void
    {
        foreach ($this->listeners as $listener) {
            $listener->handle($event);
        }
    }

    public function subscribe(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    public function unsubscribe(string $event, callable $listener): void
    {
        $this->listeners[$event] = array_filter(
            $this->listeners[$event] ?? [],
            fn($l) => $l !== $listener
        );
    }
}

class ExceptionHandler
{
    // Never type - method never returns normally (PHP 8.1+)
    public function handleFatal(Throwable $e): never
    {
        $this->logger->critical($e->getMessage());
        $this->renderErrorPage($e);
        exit(1);
    }

    // Never type - always throws
    public function abort(int $code, string $message = ''): never
    {
        throw new HttpException($code, $message);
    }

    // Never type - infinite loop
    public function runDaemon(): never
    {
        while (true) {
            $this->processQueue();
            sleep(1);
        }
    }
}

// Static analysis understands code after never call is unreachable
function processOrFail(mixed $data): array
{
    if (!is_array($data)) {
        $this->abort(400, 'Invalid data'); // never returns
    }

    // Static analysis knows $data is array here
    return $data;
}
```

## Why

- **Clear Intent**: void shows method performs side effects without returning
- **Never Semantics**: never tells analyzers code after call is unreachable
- **Better Analysis**: Static analysis uses never for reachability analysis
- **Explicit Contract**: Distinguishes "returns nothing" from "never returns"
- **Documentation**: Self-documenting method behavior
- **Type Narrowing**: never enables better type inference after calls
