---
title: PSR-12 Coding Style
impact: HIGH
impactDescription: Consistent formatting, improved readability and collaboration
tags: psr, coding-style, formatting, php-fig
---

# PSR-12 Coding Style

Follow PSR-12 extended coding style for consistent, readable code.

## Bad Example

```php
<?php
namespace App\Services;
use App\Models\User;use App\Repositories\UserRepository;

class UserService{
    private $repository;

    public function __construct(UserRepository $repo){
        $this->repository=$repo;
    }

    public function find($id){
        if($id<1){return null;}
        return $this->repository->find($id);
    }

    public function create($data)
    {
        if(!isset($data['email'])||!isset($data['name'])){throw new \Exception('Missing data');}
        return $this->repository->create($data);
    }
}
```

## Good Example

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use InvalidArgumentException;

class UserService
{
    public function __construct(
        private UserRepository $repository,
    ) {}

    public function find(int $id): ?User
    {
        if ($id < 1) {
            return null;
        }

        return $this->repository->find($id);
    }

    public function create(array $data): User
    {
        if (!isset($data['email']) || !isset($data['name'])) {
            throw new InvalidArgumentException('Missing required data');
        }

        return $this->repository->create($data);
    }

    public function update(
        int $id,
        array $data,
        bool $validate = true,
    ): User {
        $user = $this->find($id);

        if ($user === null) {
            throw new UserNotFoundException($id);
        }

        if ($validate) {
            $this->validate($data);
        }

        return $this->repository->update($user, $data);
    }
}
```

### Key PSR-12 Rules

```php
<?php

declare(strict_types=1);

namespace App\Example;

use App\Contracts\ServiceInterface;
use App\Exceptions\CustomException;
use Psr\Log\LoggerInterface;

// One blank line after namespace and use blocks
class ExampleService implements ServiceInterface
{
    // Opening brace on same line for classes
    private const MAX_RETRIES = 3;

    public function __construct(
        private LoggerInterface $logger,
        private int $timeout = 30,
    ) {
        // Constructor body
    }

    // One blank line between methods
    public function process(
        string $input,
        array $options = [],
    ): string {
        // Opening brace on same line for methods
        $result = '';

        // Space after control structure keywords
        if ($input === '') {
            return $result;
        }

        // Operators surrounded by spaces
        $length = strlen($input) + 1;

        // Foreach with proper spacing
        foreach ($options as $key => $value) {
            $result .= "{$key}: {$value}\n";
        }

        // Switch statement formatting
        switch ($input[0]) {
            case 'a':
                $result = 'starts with a';
                break;
            case 'b':
                $result = 'starts with b';
                break;
            default:
                $result = 'other';
                break;
        }

        // Try-catch formatting
        try {
            $this->validate($input);
        } catch (CustomException $e) {
            $this->logger->error($e->getMessage());
            throw $e;
        } finally {
            $this->cleanup();
        }

        return $result;
    }

    // Closure formatting
    public function withCallback(callable $callback): array
    {
        $items = [1, 2, 3];

        // Short closure
        $doubled = array_map(fn($n) => $n * 2, $items);

        // Multi-line closure
        $processed = array_filter(
            $items,
            function (int $item) use ($callback): bool {
                return $callback($item) > 0;
            }
        );

        return $processed;
    }
}
```

### Interface and Trait

```php
<?php

declare(strict_types=1);

namespace App\Contracts;

interface ServiceInterface
{
    public function process(string $input, array $options = []): string;

    public function withCallback(callable $callback): array;
}

trait LoggableTrait
{
    protected function log(string $message): void
    {
        // Trait method implementation
    }
}
```

## Why

- **Consistency**: All code looks the same regardless of author
- **Readability**: Standardized formatting is easier to read
- **Tooling**: PHP CS Fixer and IDE formatters can enforce automatically
- **Collaboration**: Reduces friction in code reviews
- **Industry Standard**: Most PHP projects and frameworks follow PSR-12
- **Professionalism**: Demonstrates attention to code quality
