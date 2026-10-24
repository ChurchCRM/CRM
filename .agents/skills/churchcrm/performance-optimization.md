---
title: "Performance Optimization & Best Practices"
intent: "Patterns for improving query performance, caching, and frontend optimization"
tags: ["performance","database","caching"]
prereqs: ["[[database-operations]]","[[service-layer]]"]
complexity: "advanced"
---

# Performance Optimization & Best Practices

## Database

### Load only the columns you need

```php
$users = UserQuery::create()
    ->select(['failedLogins', 'twoFactorAuthSecret'])
    ->find();
```

### Join and read related data as virtual columns

```php
$query = PledgeQuery::create()
    ->innerJoinDonationFund()
    ->withColumn(DonationFundTableMap::COL_FUN_NAME, 'FundName');

foreach ($query->find() as $pledge) {
    echo $pledge->getFundName();
}
```

Use `->with('Relation')` to hydrate a related object in the same query.

### Aggregate in SQL

```php
return PledgeQuery::create()
    ->filterByDepositId($id)
    ->withColumn('SUM(' . PledgeTableMap::COL_PLG_AMOUNT . ')', 'TotalAmount')
    ->select(['TotalAmount'])
    ->findOne();
```

### Page large result sets

```php
$pageSize = 100;
$people = PersonQuery::create()
    ->with('Classification')
    ->limit($pageSize)
    ->offset(($page - 1) * $pageSize)
    ->find();
```

### Bulk updates

```php
PersonQuery::create()
    ->filterByFamilyId($familyId)
    ->setUpdateValue('status', 'Inactive')
    ->update();
```

## Algorithms

Build a hash set once, then test membership with `isset()`. This turns O(n*m) loops into O(n+m).

```php
$localIdSet = array_flip(array_column($localPeople, 'id'));
$remoteOnly = array_filter($remotePeople, fn ($p) => !isset($localIdSet[$p['id']]));

$rolesSet = array_flip($allowedRoles);
foreach ($items as $item) {
    if (isset($rolesSet[$item['role']])) {
        process($item);
    }
}
```

## Frontend

- Page logic lives in its own `webpack/` entry, registered in `webpack.config.js`, so each page loads only its code (`webpack-typescript.md`)
- Load heavy components only when the page needs them
- Set `Cache-Control` with an explicit TTL on read-only responses

## HTTP caching

Photo routes add the `Cache` middleware with `Photo::CACHE_DURATION_SECONDS`:

```php
$group->get('/photo', function ($request, $response, $args) {
    $photo = new Photo('Person', $args['personId']);
    return SlimUtils::renderPhoto($response, $photo);
})->add(new Cache('public', Photo::CACHE_DURATION_SECONDS));
```

Use `'private'` for member-specific responses (see `src/api/routes/portal/portal-me.php`).

## Profiling

- Find slow queries with the MySQL slow query log (`slow_query_log`, `long_query_time = 0.5`)
- Check peak memory with `memory_get_peak_usage(true)`. Paginate or select fewer columns when a typical request approaches 256 MB

## Review checklist

- [ ] Queries fetch only needed columns (`->select()`)
- [ ] Related data is joined or eager-loaded (`->with()`, `withColumn()`)
- [ ] Aggregations use SQL (`SUM()`, `COUNT()`)
- [ ] Set membership uses `isset()` on a hash set
- [ ] Large result sets are paginated or batched
- [ ] Read-only responses carry cache headers
- [ ] Frontend code is split by page
