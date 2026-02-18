---
title: Dependency Inversion Principle
impact: CRITICAL
impactDescription: Depend on abstractions, improves testability and flexibility
tags: solid, dip, design-principles, abstractions, dependency-injection
---

# Dependency Inversion Principle (DIP)

High-level modules should not depend on low-level modules. Both should depend on abstractions.

## Bad Example

```php
<?php

declare(strict_types=1);

// High-level module directly depends on low-level implementations
class OrderService
{
    private MySqlDatabase $database;
    private SmtpMailer $mailer;
    private StripePayment $payment;
    private FileLogger $logger;

    public function __construct()
    {
        // Creating concrete implementations - tight coupling
        $this->database = new MySqlDatabase('localhost', 'shop', 'user', 'pass');
        $this->mailer = new SmtpMailer('smtp.gmail.com', 587);
        $this->payment = new StripePayment('sk_test_...');
        $this->logger = new FileLogger('/var/log/orders.log');
    }

    public function createOrder(array $data): Order
    {
        $this->logger->log('Creating order...');

        // Directly using concrete classes
        $order = new Order($data);
        $this->database->insert('orders', $order->toArray());
        $this->payment->charge($order->getTotal());
        $this->mailer->send($order->getCustomerEmail(), 'Order Confirmation', '...');

        return $order;
    }
}

// Problems:
// - Can't swap MySQL for PostgreSQL without changing OrderService
// - Can't test without real database, mailer, payment gateway
// - Can't use different mailer in production vs development
// - OrderService knows about infrastructure details
```

## Good Example

```php
<?php

declare(strict_types=1);

// Define abstractions (interfaces) in the domain/application layer
interface OrderRepository
{
    public function save(Order $order): void;
    public function find(OrderId $id): ?Order;
}

interface PaymentGateway
{
    public function charge(Money $amount, PaymentMethod $method): PaymentResult;
}

interface NotificationService
{
    public function notify(User $user, Notification $notification): void;
}

interface Logger
{
    public function info(string $message, array $context = []): void;
    public function error(string $message, array $context = []): void;
}

// High-level module depends on abstractions
class OrderService
{
    public function __construct(
        private OrderRepository $repository,
        private PaymentGateway $payment,
        private NotificationService $notifications,
        private Logger $logger,
    ) {}

    public function createOrder(CreateOrderCommand $command): Order
    {
        $this->logger->info('Creating order', ['customer' => $command->customerId]);

        $order = Order::create(
            customerId: $command->customerId,
            items: $command->items,
        );

        $paymentResult = $this->payment->charge(
            $order->getTotal(),
            $command->paymentMethod,
        );

        if (!$paymentResult->isSuccessful()) {
            throw new PaymentFailedException($paymentResult->getError());
        }

        $order->markAsPaid($paymentResult->getTransactionId());
        $this->repository->save($order);

        $this->notifications->notify(
            $order->getCustomer(),
            new OrderConfirmationNotification($order),
        );

        return $order;
    }
}

// Low-level modules implement the abstractions
class DoctrineOrderRepository implements OrderRepository
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function save(Order $order): void
    {
        $this->em->persist($order);
        $this->em->flush();
    }

    public function find(OrderId $id): ?Order
    {
        return $this->em->find(Order::class, $id);
    }
}

class StripePaymentGateway implements PaymentGateway
{
    public function __construct(
        private StripeClient $stripe,
    ) {}

    public function charge(Money $amount, PaymentMethod $method): PaymentResult
    {
        // Stripe implementation
    }
}

class EmailNotificationService implements NotificationService
{
    public function __construct(
        private Mailer $mailer,
        private TemplateRenderer $renderer,
    ) {}

    public function notify(User $user, Notification $notification): void
    {
        $content = $this->renderer->render($notification->getTemplate(), [
            'user' => $user,
            'data' => $notification->getData(),
        ]);

        $this->mailer->send($user->getEmail(), $notification->getSubject(), $content);
    }
}

// Wire everything together in composition root (DI container)
class OrderServiceProvider
{
    public function register(Container $container): void
    {
        $container->bind(OrderRepository::class, DoctrineOrderRepository::class);
        $container->bind(PaymentGateway::class, StripePaymentGateway::class);
        $container->bind(NotificationService::class, EmailNotificationService::class);
        $container->bind(Logger::class, MonologLogger::class);
    }
}
```

### Testing Benefits

```php
<?php

declare(strict_types=1);

class OrderServiceTest extends TestCase
{
    public function testCreateOrderChargesPayment(): void
    {
        // Use test doubles - easy because we depend on abstractions
        $repository = new InMemoryOrderRepository();
        $payment = $this->createMock(PaymentGateway::class);
        $notifications = new NullNotificationService();
        $logger = new NullLogger();

        $payment->expects($this->once())
            ->method('charge')
            ->willReturn(PaymentResult::success('txn_123'));

        $service = new OrderService(
            $repository,
            $payment,
            $notifications,
            $logger,
        );

        $order = $service->createOrder(new CreateOrderCommand(
            customerId: 1,
            items: [new OrderItem('product-1', 2, Money::USD(1000))],
            paymentMethod: new CreditCard('4242...'),
        ));

        $this->assertTrue($order->isPaid());
    }
}

// In-memory implementation for testing
class InMemoryOrderRepository implements OrderRepository
{
    private array $orders = [];

    public function save(Order $order): void
    {
        $this->orders[$order->getId()->value] = $order;
    }

    public function find(OrderId $id): ?Order
    {
        return $this->orders[$id->value] ?? null;
    }
}

// Null implementation for testing
class NullNotificationService implements NotificationService
{
    public function notify(User $user, Notification $notification): void
    {
        // Do nothing - for testing
    }
}
```

## Why

- **Decoupling**: High-level policy doesn't depend on low-level details
- **Testability**: Easy to substitute test doubles for dependencies
- **Flexibility**: Swap implementations without changing business logic
- **Maintainability**: Changes to infrastructure don't affect domain code
- **Framework Independence**: Business logic isn't tied to specific frameworks
- **Parallel Development**: Teams can work on different layers independently
