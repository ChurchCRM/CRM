# solid-single-responsibility

**Priority:** HIGH
**Category:** SOLID Principles

## Why It Matters

A class should have only one reason to change. When a class does too much, changes to one responsibility risk breaking others. Single responsibility makes code easier to test, maintain, and understand.

## Incorrect

```php
<?php

// ❌ God class - multiple responsibilities
class UserManager
{
    public function createUser(array $data): User
    {
        // Validation
        if (empty($data['email'])) {
            throw new ValidationException('Email required');
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Invalid email');
        }
        if (strlen($data['password']) < 8) {
            throw new ValidationException('Password too short');
        }

        // Hashing
        $hashedPassword = password_hash($data['password'], PASSWORD_ARGON2ID);

        // Database
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (email, password) VALUES (?, ?)'
        );
        $stmt->execute([$data['email'], $hashedPassword]);
        $id = $this->pdo->lastInsertId();

        $user = new User($id, $data['email']);

        // Sending email
        $subject = 'Welcome!';
        $body = "Hello {$data['email']}, welcome to our platform!";
        mail($data['email'], $subject, $body);

        // Logging
        file_put_contents(
            '/var/log/app.log',
            date('Y-m-d H:i:s') . " User created: {$id}\n",
            FILE_APPEND
        );

        return $user;
    }

    public function exportUsers(): string
    {
        // CSV export logic
    }

    public function importUsers(string $csv): void
    {
        // CSV import logic
    }

    public function generateReport(): string
    {
        // PDF report logic
    }
}
```

**Problems:**
- Validation, hashing, database, email, logging all mixed
- Testing requires mocking everything
- Changing email provider affects user creation
- Hard to reuse parts independently

## Correct

```php
<?php

declare(strict_types=1);

// ✅ Separate validator
class UserValidator
{
    public function validate(array $data): void
    {
        $errors = [];

        if (empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }

        if (empty($data['password'])) {
            $errors['password'] = 'Password is required';
        } elseif (strlen($data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}

// ✅ Separate repository
class UserRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    public function create(string $email, string $hashedPassword): User
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (email, password, created_at) VALUES (?, ?, NOW())'
        );
        $stmt->execute([$email, $hashedPassword]);

        return new User(
            id: (int) $this->pdo->lastInsertId(),
            email: $email,
        );
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? User::fromArray($row) : null;
    }
}

// ✅ Separate password hasher
class PasswordHasher
{
    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3,
        ]);
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}

// ✅ Separate notification service
interface NotificationInterface
{
    public function send(User $user, string $template, array $data = []): void;
}

class EmailNotification implements NotificationInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
    ) {}

    public function send(User $user, string $template, array $data = []): void
    {
        $this->mailer->send(
            to: $user->email,
            template: $template,
            data: array_merge(['user' => $user], $data),
        );
    }
}

// ✅ Service coordinates but doesn't do the work
class UserService
{
    public function __construct(
        private readonly UserValidator $validator,
        private readonly UserRepository $repository,
        private readonly PasswordHasher $hasher,
        private readonly NotificationInterface $notification,
        private readonly LoggerInterface $logger,
    ) {}

    public function register(array $data): User
    {
        $this->validator->validate($data);

        $hashedPassword = $this->hasher->hash($data['password']);
        $user = $this->repository->create($data['email'], $hashedPassword);

        $this->notification->send($user, 'welcome');
        $this->logger->info('User registered', ['user_id' => $user->id]);

        return $user;
    }
}
```

## Identifying Violations

### Signs of Multiple Responsibilities

```php
<?php

// ❌ Class name contains "And" or "Manager"
class UserAndOrderManager {}
class DataManager {}

// ❌ Large number of dependencies
class ReportService
{
    public function __construct(
        private UserRepository $users,
        private OrderRepository $orders,
        private ProductRepository $products,
        private PdfGenerator $pdf,
        private CsvExporter $csv,
        private EmailSender $email,
        private S3Client $s3,
        private Logger $logger,
        // ... more dependencies
    ) {}
}

// ❌ Methods operate on different data
class Helper
{
    public function formatDate(DateTime $date): string {}
    public function calculateTax(float $amount): float {}
    public function sendEmail(string $to, string $body): void {}
    public function resizeImage(string $path): void {}
}
```

### Refactoring Strategy

```php
<?php

// Before: Fat controller
class UserController
{
    public function store(Request $request)
    {
        // Validation (60 lines)
        // Business logic (40 lines)
        // Database operations (30 lines)
        // Email sending (20 lines)
        // Logging (10 lines)
        // Response formatting (20 lines)
    }
}

// After: Thin controller, focused services
class UserController
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    public function store(CreateUserRequest $request): JsonResponse
    {
        $user = $this->userService->register($request->validated());

        return response()->json(
            new UserResource($user),
            Response::HTTP_CREATED
        );
    }
}
```

## Testing Benefits

```php
<?php

// ✅ Easy to test in isolation
class UserValidatorTest extends TestCase
{
    public function test_validates_email_required(): void
    {
        $validator = new UserValidator();

        $this->expectException(ValidationException::class);
        $validator->validate(['password' => 'secret123']);
    }
}

class PasswordHasherTest extends TestCase
{
    public function test_hashes_password(): void
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('secret123');

        $this->assertTrue($hasher->verify('secret123', $hash));
    }
}

class UserServiceTest extends TestCase
{
    public function test_registers_user(): void
    {
        // Easy to mock each dependency
        $validator = $this->createMock(UserValidator::class);
        $repository = $this->createMock(UserRepository::class);
        $hasher = $this->createMock(PasswordHasher::class);
        $notification = $this->createMock(NotificationInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $repository->expects($this->once())
            ->method('create')
            ->willReturn(new User(1, 'test@example.com'));

        $service = new UserService(
            $validator,
            $repository,
            $hasher,
            $notification,
            $logger,
        );

        $user = $service->register([
            'email' => 'test@example.com',
            'password' => 'secret123',
        ]);

        $this->assertEquals('test@example.com', $user->email);
    }
}
```

## Benefits

- Each class is focused and understandable
- Changes are isolated to relevant classes
- Easy to test individual responsibilities
- Promotes reusability
- Follows "a class should have one reason to change"
- Smaller classes are easier to maintain
