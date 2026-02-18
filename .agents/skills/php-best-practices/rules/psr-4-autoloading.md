---
title: PSR-4 Autoloading
impact: CRITICAL
impactDescription: Standard autoloading, predictable class location
tags: psr, autoloading, organization, php-fig
---

# PSR-4 Autoloading

Follow PSR-4 autoloading standard for class file organization.

## Bad Example

```php
<?php

// File: includes/classes/user_model.php
// Wrong: File name doesn't match class name
// Wrong: Using underscores instead of directories

class User_Model
{
    // ...
}

// File: lib/MyApp/Services/userService.php
// Wrong: File name case doesn't match class name

namespace MyApp\Services;

class UserService
{
    // ...
}

// Manual includes - fragile and error-prone
require_once 'includes/classes/user_model.php';
require_once 'includes/classes/order_model.php';
require_once 'lib/helpers.php';
```

## Good Example

```php
<?php

// File: src/Domain/User/User.php
// Correct: Namespace matches directory structure

namespace App\Domain\User;

class User
{
    public function __construct(
        private UserId $id,
        private Email $email,
    ) {}
}

// File: src/Domain/User/UserId.php
namespace App\Domain\User;

readonly class UserId
{
    public function __construct(
        public string $value,
    ) {}
}

// File: src/Application/Services/UserService.php
namespace App\Application\Services;

use App\Domain\User\User;
use App\Domain\User\UserRepository;

class UserService
{
    public function __construct(
        private UserRepository $repository,
    ) {}
}
```

### Composer Configuration

```json
{
    "autoload": {
        "psr-4": {
            "App\\": "src/",
            "Tests\\": "tests/"
        }
    }
}
```

### Directory Structure

```
project/
├── composer.json
├── src/
│   ├── Application/
│   │   ├── Commands/
│   │   │   └── CreateUserCommand.php
│   │   └── Services/
│   │       └── UserService.php
│   ├── Domain/
│   │   ├── User/
│   │   │   ├── User.php
│   │   │   ├── UserId.php
│   │   │   ├── Email.php
│   │   │   └── UserRepository.php
│   │   └── Order/
│   │       ├── Order.php
│   │       └── OrderRepository.php
│   └── Infrastructure/
│       ├── Persistence/
│       │   └── DoctrineUserRepository.php
│       └── Http/
│           └── Controllers/
│               └── UserController.php
└── tests/
    ├── Unit/
    │   └── Domain/
    │       └── User/
    │           └── UserTest.php
    └── Integration/
        └── UserServiceTest.php
```

## Why

- **Automatic Loading**: No manual require/include statements needed
- **Predictable Structure**: Class location is deterministic from namespace
- **IDE Support**: Enables full autocompletion and navigation
- **Composer Integration**: Standard Composer autoloader works out of the box
- **Interoperability**: Works with any PSR-4 compliant framework
- **Maintainability**: Clear organization makes codebases easier to navigate
