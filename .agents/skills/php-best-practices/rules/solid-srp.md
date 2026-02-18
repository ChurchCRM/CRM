---
title: Single Responsibility Principle
impact: CRITICAL
impactDescription: One reason to change, easier testing and maintenance
tags: solid, srp, design-principles, single-responsibility
---

# Single Responsibility Principle (SRP)

A class should have only one reason to change - one responsibility.

## Bad Example

```php
<?php

declare(strict_types=1);

// This class has multiple responsibilities
class User
{
    public function __construct(
        private int $id,
        private string $name,
        private string $email,
    ) {}

    // Responsibility 1: User data
    public function getName(): string
    {
        return $this->name;
    }

    // Responsibility 2: Database operations
    public function save(): void
    {
        $pdo = new PDO('mysql:host=localhost;dbname=app', 'user', 'pass');
        $stmt = $pdo->prepare('INSERT INTO users (name, email) VALUES (?, ?)');
        $stmt->execute([$this->name, $this->email]);
    }

    // Responsibility 3: Email sending
    public function sendWelcomeEmail(): void
    {
        mail(
            $this->email,
            'Welcome!',
            "Hello {$this->name}, welcome to our platform!"
        );
    }

    // Responsibility 4: Formatting/Display
    public function toJson(): string
    {
        return json_encode([
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ]);
    }

    // Responsibility 5: Validation
    public function validate(): array
    {
        $errors = [];
        if (empty($this->name)) {
            $errors[] = 'Name is required';
        }
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email';
        }
        return $errors;
    }
}
```

## Good Example

```php
<?php

declare(strict_types=1);

// Responsibility: User domain entity
class User
{
    public function __construct(
        private UserId $id,
        private UserName $name,
        private Email $email,
        private UserStatus $status = UserStatus::Active,
    ) {}

    public function getId(): UserId
    {
        return $this->id;
    }

    public function getName(): UserName
    {
        return $this->name;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function activate(): void
    {
        $this->status = UserStatus::Active;
    }

    public function deactivate(): void
    {
        $this->status = UserStatus::Inactive;
    }
}

// Responsibility: Database persistence
interface UserRepository
{
    public function find(UserId $id): ?User;
    public function save(User $user): void;
    public function delete(User $user): void;
}

class DatabaseUserRepository implements UserRepository
{
    public function __construct(
        private PDO $pdo,
    ) {}

    public function find(UserId $id): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id->value]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? $this->hydrate($data) : null;
    }

    public function save(User $user): void
    {
        // Save implementation
    }

    public function delete(User $user): void
    {
        // Delete implementation
    }

    private function hydrate(array $data): User
    {
        // Hydration logic
    }
}

// Responsibility: Email notifications
class UserEmailNotifier
{
    public function __construct(
        private MailerInterface $mailer,
        private EmailTemplateRenderer $renderer,
    ) {}

    public function sendWelcomeEmail(User $user): void
    {
        $content = $this->renderer->render('welcome', [
            'name' => $user->getName()->value,
        ]);

        $this->mailer->send(
            to: $user->getEmail()->value,
            subject: 'Welcome!',
            body: $content,
        );
    }

    public function sendPasswordResetEmail(User $user, string $token): void
    {
        // Password reset email logic
    }
}

// Responsibility: Serialization
class UserSerializer
{
    public function toArray(User $user): array
    {
        return [
            'id' => $user->getId()->value,
            'name' => $user->getName()->value,
            'email' => $user->getEmail()->value,
        ];
    }

    public function toJson(User $user): string
    {
        return json_encode($this->toArray($user), JSON_THROW_ON_ERROR);
    }
}

// Responsibility: Validation
class CreateUserValidator
{
    public function validate(array $data): ValidationResult
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors['name'] = 'Name is required';
        }

        if (empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }

        return new ValidationResult($errors);
    }
}

// Responsibility: Orchestrating user creation (Application Service)
class UserService
{
    public function __construct(
        private UserRepository $repository,
        private CreateUserValidator $validator,
        private UserEmailNotifier $notifier,
        private UserFactory $factory,
    ) {}

    public function create(array $data): User
    {
        $result = $this->validator->validate($data);

        if (!$result->isValid()) {
            throw new ValidationException($result->getErrors());
        }

        $user = $this->factory->create($data);
        $this->repository->save($user);
        $this->notifier->sendWelcomeEmail($user);

        return $user;
    }
}
```

## Why

- **Focused Classes**: Each class does one thing well
- **Easier Testing**: Small, focused classes are easier to unit test
- **Better Reusability**: Single-purpose classes can be reused elsewhere
- **Simpler Maintenance**: Changes to one responsibility don't affect others
- **Clear Dependencies**: Easier to understand what a class needs
- **Team Scalability**: Different developers can work on different responsibilities
