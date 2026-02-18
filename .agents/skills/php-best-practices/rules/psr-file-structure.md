---
title: File Structure
impact: MEDIUM
impactDescription: Predictable file organization, improves readability
tags: psr, file-structure, organization, php-fig
---

# File Structure

Organize PHP files with proper ordering of elements and logical grouping.

## Bad Example

```php
<?php
class UserService {
use LoggableTrait;
private $repo;
const MAX = 100;
public function find($id) {}
private $logger;
public const MIN = 1;
public function __construct($repo, $logger) {
$this->repo = $repo;
$this->logger = $logger;
}
}
namespace App\Services;
use App\Repositories\UserRepository;
```

## Good Example

```php
<?php

/**
 * This file is part of the MyApp package.
 *
 * (c) Company Name <email@example.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Services;

use App\Contracts\UserServiceInterface;
use App\Domain\User\User;
use App\Domain\User\UserRepository;
use App\Events\UserCreated;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * Handles user-related business operations.
 */
final class UserService implements UserServiceInterface
{
    use LoggableTrait;
    use CacheableTrait;

    // Constants - public first, then protected, then private
    public const DEFAULT_PAGE_SIZE = 20;
    public const MAX_PAGE_SIZE = 100;
    protected const CACHE_TTL = 3600;
    private const LOG_CHANNEL = 'user';

    // Properties - ordered by visibility
    public readonly string $version;
    protected EventDispatcherInterface $dispatcher;
    private UserRepository $repository;
    private LoggerInterface $logger;
    private array $cache = [];

    // Constructor
    public function __construct(
        UserRepository $repository,
        LoggerInterface $logger,
        EventDispatcherInterface $dispatcher,
    ) {
        $this->repository = $repository;
        $this->logger = $logger;
        $this->dispatcher = $dispatcher;
        $this->version = '1.0.0';
    }

    // Public methods
    public function find(int $id): ?User
    {
        return $this->repository->find($id);
    }

    public function findOrFail(int $id): User
    {
        $user = $this->find($id);

        if ($user === null) {
            throw new UserNotFoundException($id);
        }

        return $user;
    }

    public function create(array $data): User
    {
        $this->validateCreateData($data);

        $user = $this->repository->create($data);

        $this->dispatcher->dispatch(
            new UserCreated($user->getId(), new DateTimeImmutable())
        );

        return $user;
    }

    public function update(User $user, array $data): User
    {
        $this->validateUpdateData($data);

        return $this->repository->update($user, $data);
    }

    public function delete(User $user): void
    {
        $this->repository->delete($user);
    }

    // Protected methods
    protected function getCacheKey(int $id): string
    {
        return sprintf('user:%d', $id);
    }

    // Private methods
    private function validateCreateData(array $data): void
    {
        if (empty($data['email'])) {
            throw new InvalidArgumentException('Email is required');
        }

        if (empty($data['name'])) {
            throw new InvalidArgumentException('Name is required');
        }
    }

    private function validateUpdateData(array $data): void
    {
        // Validation logic
    }
}
```

### Standard File Structure Order

```
1. Opening PHP tag (<?php)
2. File-level docblock (optional - license, copyright)
3. declare(strict_types=1)
4. Blank line
5. namespace declaration
6. Blank line
7. use statements (grouped and sorted)
   - PHP native classes
   - External packages
   - Internal project classes
8. Blank line
9. Class/Interface/Trait/Enum docblock
10. Class/Interface/Trait/Enum declaration
    a. Traits (use statements)
    b. Constants (public → protected → private)
    c. Properties (public → protected → private)
    d. Constructor
    e. Public methods
    f. Protected methods
    g. Private methods
```

### Use Statement Organization

```php
<?php

declare(strict_types=1);

namespace App\Services;

// PHP native classes
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

// External packages (alphabetized by vendor)
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

// Internal project classes (alphabetized by namespace)
use App\Contracts\ServiceInterface;
use App\Domain\User\User;
use App\Domain\User\UserRepository;
use App\Events\UserCreated;
use App\Exceptions\UserNotFoundException;
```

## Why

- **Predictability**: Developers know where to find things
- **Readability**: Logical ordering improves code comprehension
- **Maintenance**: Consistent structure makes updates easier
- **Code Review**: Standard format reduces review friction
- **Tooling**: IDE and static analysis tools work better
- **PSR Compliance**: Follows PHP-FIG recommendations
