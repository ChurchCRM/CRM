---
title: "Modern PHP 8.4+ & Framework Best Practices"
intent: "Security hardening and framework-level best practices for the PHP 8.4+ / Slim stack"
tags: ["php", "security", "slim"]
prereqs: ["[[php-best-practices]]"]
complexity: "intermediate"
---
# Modern PHP 8.4+ & Framework Best Practices

Stack versions: `src/composer.json` (PHP, Perpl ORM, Slim, Monolog).

## Passwords

Hash with `password_hash()` and verify with `password_verify()`. `User::hashPassword()` is the single entry point (`PASSWORD_DEFAULT`).

## Sessions

`Bootstrapper` configures sessions: strict mode, cookies only, `httponly`, `samesite`, `secure` on HTTPS. Change session settings there. Call `session_regenerate_id(true)` after authentication.

## Error display

- Production: `display_errors = 0`, `log_errors = 1`, `error_reporting = E_ALL`, `expose_php = 0`
- Log through `LoggerUtils::getAppLogger()`

## Server hardening

Deployments set `disable_functions` (`exec`, `system`, `shell_exec`, `passthru`, `proc_open`, `pcntl_exec`), `allow_url_fopen = 0` and `allow_url_include = 0` in php.ini or the image.

## Slim 4

Middleware order, error handling, DI and response patterns: [`slim-4-best-practices.md`](./slim-4-best-practices.md).

### Route error handling

Catch exceptions in the route and return sanitized JSON through `SlimUtils`:

```php
$app->post('/payments/delete', function (Request $req, Response $res): Response {
    try {
        $service = new FinancialService();
        $service->deletePayment($groupKey);
        return SlimUtils::renderJSON($res, ['success' => true]);
    } catch (Throwable $e) {
        return SlimUtils::renderErrorJSON($res, gettext('An error occurred'), [], 500, $e, $req);
    }
});
```

## Perpl ORM

### Typed collections

```php
$users = UserQuery::create()->filterByActive(true)->findObjects();
```

### Eager loading

```php
$books = BookQuery::create()->with('Author')->findObjects();
foreach ($books as $book) {
    echo $book->getAuthor()->getName();
}
```

### Bulk updates

```php
PersonQuery::create()
    ->filterByFamilyId($familyId)
    ->setUpdateValue('status', 'Inactive')
    ->update();
```

### Type-safe joins

```php
$books = BookQuery::create()
    ->useAuthorQuery()
        ->filterByLastName('Smith')
    ->endUse()
    ->useCategoryQuery('category')
        ->filterByType('Fiction')
    ->endUse()
    ->findObjects();
```

More query patterns: `performance-optimization.md`.

## Checklist

### Security
- [ ] Passwords go through `User::hashPassword()`
- [ ] Error details stay out of production responses
- [ ] User input passes through `InputUtils`
- [ ] Object-level authorization uses `canEditPerson()`
- [ ] Outbound HTTPS verifies TLS (self-signed is opt-in)

### Performance
- [ ] `->select()` for needed columns, `->with()` / `withColumn()` for related data
- [ ] SQL `SUM()` / `COUNT()` for aggregates
- [ ] Batch updates for bulk changes
- [ ] Pagination or batching for large result sets

### Framework
- [ ] Routes catch errors and return `SlimUtils::renderErrorJSON()`
- [ ] Services are instantiated per `service-layer.md`
- [ ] Routes handle HTTP only; logic lives in services
