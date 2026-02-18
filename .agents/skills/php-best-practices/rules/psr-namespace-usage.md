---
title: Namespace Usage
impact: HIGH
impactDescription: Proper code organization, avoids naming conflicts
tags: psr, namespaces, organization, php-fig
---

# Namespace Usage

Use namespaces properly to organize code and avoid naming conflicts.

## Bad Example

```php
<?php

// No namespace - global scope pollution
class User
{
}

class UserService
{
}

// Wrong: Using namespace as prefix only
namespace MyApp;

class MyApp_User // Redundant prefix
{
}

// Wrong: Too deep/specific namespaces
namespace App\Domain\User\Entity\Model\Base;

class User
{
}

// Wrong: Using fully qualified names everywhere
class UserService
{
    public function find(int $id): \App\Domain\User\User
    {
        return $this->repository->find($id);
    }

    public function create(\App\Domain\User\CreateUserDto $dto): \App\Domain\User\User
    {
        // Cluttered and hard to read
    }
}
```

## Good Example

```php
<?php

declare(strict_types=1);

namespace App\Domain\User;

// Import classes at the top
use App\Domain\Shared\ValueObject;
use DateTimeImmutable;

class User
{
    public function __construct(
        private UserId $id,
        private Email $email,
        private DateTimeImmutable $createdAt,
    ) {}
}

// Service in separate namespace
// File: src/Application/Services/UserService.php
namespace App\Application\Services;

use App\Domain\User\User;
use App\Domain\User\UserId;
use App\Domain\User\UserRepository;
use App\Application\Dto\CreateUserDto;

class UserService
{
    public function __construct(
        private UserRepository $repository,
    ) {}

    public function find(UserId $id): ?User
    {
        return $this->repository->find($id);
    }

    public function create(CreateUserDto $dto): User
    {
        // Clean and readable
    }
}
```

### Namespace Organization Patterns

```php
<?php

// Domain Layer
namespace App\Domain\User;           // User aggregate
namespace App\Domain\Order;          // Order aggregate
namespace App\Domain\Shared;         // Shared value objects

// Application Layer
namespace App\Application\Services;  // Application services
namespace App\Application\Commands;  // Command objects
namespace App\Application\Queries;   // Query objects
namespace App\Application\Dto;       // Data transfer objects

// Infrastructure Layer
namespace App\Infrastructure\Persistence;      // Database implementations
namespace App\Infrastructure\Http\Controllers; // HTTP controllers
namespace App\Infrastructure\Http\Middleware;  // HTTP middleware
namespace App\Infrastructure\Queue;            // Queue workers
namespace App\Infrastructure\Cache;            // Cache implementations

// Tests
namespace Tests\Unit\Domain\User;
namespace Tests\Integration\Application;
namespace Tests\Functional\Http;
```

### Handling Name Conflicts

```php
<?php

declare(strict_types=1);

namespace App\Services;

// Aliasing to avoid conflicts
use App\Domain\User\User;
use App\External\Payment\User as PaymentUser;
use DateTimeImmutable as DateTime;

// Or use more descriptive aliases
use App\Domain\User\User as DomainUser;
use App\Http\Resources\User as UserResource;
use App\Database\Models\User as UserModel;

class UserSyncService
{
    public function sync(DomainUser $domainUser): UserModel
    {
        // Clear which User class is being used
    }
}
```

### Grouping Related Imports

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

// Group 1: PHP built-in
use DateTimeImmutable;
use InvalidArgumentException;

// Group 2: Framework classes
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

// Group 3: External packages
use League\Fractal\Manager;
use Psr\Log\LoggerInterface;

// Group 4: Application classes
use App\Application\Services\UserService;
use App\Domain\User\UserId;
use App\Http\Requests\CreateUserRequest;
use App\Http\Resources\UserResource;

class UserController extends Controller
{
    // ...
}
```

### Function and Constant Imports

```php
<?php

declare(strict_types=1);

namespace App\Services;

// Import specific functions
use function array_map;
use function array_filter;
use function sprintf;

// Import constants
use const PHP_EOL;
use const SORT_REGULAR;

// Or use namespace functions/constants directly with prefix
class DataProcessor
{
    public function process(array $data): string
    {
        // Imported function
        $filtered = array_filter($data, fn($v) => $v !== null);

        // With namespace prefix (also valid)
        $sorted = \sort($filtered, SORT_REGULAR);

        return \implode(PHP_EOL, $filtered);
    }
}
```

## Why

- **Organization**: Namespaces group related code logically
- **Autoloading**: PSR-4 maps namespaces to directories
- **Conflict Prevention**: Same class name can exist in different namespaces
- **Readability**: Import statements make class origins clear
- **IDE Support**: Enables better autocompletion and navigation
- **Maintainability**: Clear boundaries between modules
