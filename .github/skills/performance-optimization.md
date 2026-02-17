# Performance Optimization & Best Practices

This skill documents performance patterns verified in the ChurchCRM codebase and best practices from code/web research.

## Database Query Optimization

### ✅ Pattern: Single-Query Aggregation (UserService Example)

**Recommended Approach:**

Instead of fetching all records and aggregating in PHP, use SQL aggregation:

```php
// BETTER: Single query with aggregation
$stats = UserQuery::create()
    ->select(['failedLogins', 'twoFactorAuthSecret'])  // Only needed columns
    ->find();

$maxFailedLogins = SystemConfig::getIntValue('iMaxFailedLogins');
foreach ($stats as $user) {
    if ($user['failedLogins'] >= $maxFailedLogins) {
        $lockedCount++;  // In-memory aggregation
    }
}

// AVOID: Multiple queries in loop
foreach ($users as $user) {
    $count = UserQuery::create()->filterByFamilyId($user->getId())->count();
    // Each iteration = 1 DB query (N+1 problem)
}
```

**Benefits:**
- Selective field loading reduces data transfer
- Single query maintains consistency
- In-memory aggregation avoids database overhead

### ✅ Pattern: Eager Loading with withColumn()

```php
// Use TableMap constants and join
$query = PledgeQuery::create()
    ->innerJoinDonationFund()
    ->withColumn(DonationFundTableMap::COL_FUN_NAME, 'FundName');

foreach ($query->find() as $pledge) {
    echo $pledge->getFundName();  // Virtual column, no extra query
}
```

**Benefits:**
- Loads related data in single query
- Avoids lazy-loading overhead
- Virtual columns prevent N+1 queries

### ⚠️ Problem: Unbounded Queries with Lazy Loading

**AVOID:**
```php
// Loads ALL records into memory at once
foreach (PersonQuery::create()->find() as $person) {
    $classification = $person->getClassificationName();  // Potential lazy-load
    // With large datasets (5000+ people), causes memory exhaustion
}
```

**BETTER:**
```php
// Limit and paginate
$pageSize = 100;
for ($page = 1; $page * $pageSize < $totalCount; $page++) {
    $people = PersonQuery::create()
        ->limit($pageSize)
        ->offset(($page - 1) * $pageSize)
        ->with('Classification')  // Eager load
        ->find();
    
    foreach ($people as $person) {
        // Process limited batch
    }
}
```

### ⚠️ Problem: Inefficient Aggregation in Loops

**AVOID:**
```php
$total = 0;
foreach ($query->find() as $pledge) {
    $total += $pledge->getAmount();  // Multiple getter calls
}
```

**BETTER:**
```php
// Use SQL SUM aggregation
return PledgeQuery::create()
    ->filterByDepositId($id)
    ->withColumn('SUM(' . PledgeTableMap::COL_PLG_AMOUNT . ')', 'TotalAmount')
    ->select(['TotalAmount'])
    ->findOne();
```

## Algorithm Efficiency Patterns

### ✅ Pattern: Hash-Based Lookups (O(1) vs O(n))

**AVOID (O(n*m) nested loop):**
```php
$remoteOnly = array_filter($remotePeople, function($remotePerson) use ($localPeople) {
    foreach ($localPeople as $localPerson) {  // O(m) per remote person
        if ($localPerson->getId() === $remotePerson['id']) {
            return false;
        }
    }
    return true;
});
```

**BETTER (O(n+m) with hash lookup):**
```php
// Build lookup set (O(n))
$localIdSet = array_flip(array_column($localPeople, 'id'));

// Filter with O(1) lookups (O(m))
$remoteOnly = array_filter($remotePeople, function($p) use ($localIdSet) {
    return !isset($localIdSet[$p['id']]);  // O(1) hash lookup
});
```

**Benefits:**
- 1000 local × 1000 remote = 1M comparisons (nested loop) vs 2K comparisons (hash)
- Scales linearly instead of quadratically

### ✅ Pattern: Avoid in_array() in Loops

```php
// AVOID - O(n) per check in loop
foreach ($items as $item) {
    if (in_array($item['role'], $allowedRoles)) {  // O(n) for each item
        process($item);
    }
}

// BETTER - O(1) per check
$rolesSet = array_flip($allowedRoles);
foreach ($items as $item) {
    if (isset($rolesSet[$item['role']])) {  // O(1) hash lookup
        process($item);
    }
}
```

## Frontend Performance Patterns

### ✅ Pattern: Webpack Bundle Splitting

ChurchCRM uses Webpack entry points for code splitting:

```javascript
// webpack/calendar.entry.js - separates calendar code from main bundle
import '../../react/calendar-event-editor';
module.exports = {};

// webpack/dashboard.entry.js - dashboard-specific code
import '../../react/components/DashboardWidgets';
module.exports = {};
```

**Benefits:**
- Smaller main bundle (faster initial load)
- Parallel loading of chunks
- Browser caches individual bundles separately

### ✅ Pattern: CSS Tree-Shaking

Bootstrap 4.6.2 CSS is included selectively:

```css
/* Only include used Bootstrap components */
@import '~bootstrap/scss/functions';
@import '~bootstrap/scss/variables';
@import '~bootstrap/scss/mixins';
@import '~bootstrap/scss/containers';
@import '~bootstrap/scss/grid';
/* Skip unused: carousel, transitions, modals, etc. */
```

**Benefits:**
- Compiled CSS only includes used classes
- Smaller skin/v2/churchcrm.min.css file
- Faster rendering

### ⚠️ Problem: Missing Code Splitting in Photo Handling

Photo API routes could benefit from separate caching:

```php
// Current: Each photo request goes through full Slim app
// Route: /api/photo/person/:id

// Consider: Separate fast path for cached photo requests
// Could use X-Accel-Redirect to nginx for direct file serving
```

## Caching Patterns

### ✅ Pattern: HTTP Cache Headers for Photos

```php
// File: src/api/routes/photo/photo.php
$response = SlimUtils::renderPhoto($response, $photo);
$response = $response
    ->withHeader('Cache-Control', 'public, max-age=7200')  // 2 hours
    ->withHeader('ETag', '"' . md5($photoPath . filemtime($photoPath)) . '"');
```

**Benefits:**
- Browser caches photo locally
- Reduces server requests
- ETag enables browser cache validation

### ✅ Pattern: Response Caching Middleware

```php
// Route-level caching
$group->get('/photo', function ($request, $response, $args) {
    $photo = new Photo('Person', $args['personId']);
    return SlimUtils::renderPhoto($response, $photo);
})->add(new Cache('public', Photo::CACHE_DURATION_SECONDS));
```

**Benefits:**
- HTTP intermediaries (CDN, proxies) can cache response
- Reduces load on server
- Improves response time for frequently accessed data

## Best Practices by Category

### Service Layer Performance

1. **Use selective field loading**: `->select(['field1', 'field2'])` to fetch only what you need
2. **Eager-load relationships**: Use `->with('Relation')` instead of lazy-loading
3. **Batch operations**: Use `setUpdateValue()` + `update()` for bulk changes
4. **Single aggregation query**: Calculate totals in SQL with `withColumn()` and `SUM()`

### API Route Performance

1. **Pre-fetch related data**: Avoid N+1 queries with proper joins
2. **Return only needed fields**: Use API versioning to exclude unnecessary data
3. **Implement caching**: Use Cache middleware for read-only endpoints
4. **Paginate large results**: Never return unbounded result sets

### Frontend Performance

1. **Code splitting**: Separate entry points for different features
2. **Lazy loading**: Load heavy components only when needed
3. **Tree-shaking CSS**: Remove unused Bootstrap utilities
4. **HTTP caching**: Set Cache-Control headers with appropriate TTLs

## Profiling & Monitoring

### Database Query Profiling

Enable Propel2 query logging for development:

```php
$serviceContainer = \Propel\Runtime\Propel::getServiceContainer();
$serviceContainer->setLogger('default', new \Psr\Log\NullLogger());
```

Better: Use MySQL slow query log to identify performance bottlenecks:

```sql
-- Check slow queries
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 0.5;
SELECT query_time, sql_text FROM mysql.slow_log;
```

### Memory Usage Profiling

For large queries, monitor memory:

```php
echo memory_get_usage(true) / 1024 / 1024;  // MB
```

If exceeding 256MB on typical requests, consider pagination or selective fields.

## Checklist for Performance Reviews

- [ ] Database queries fetch only needed columns (use `->select()`)
- [ ] Related data is eager-loaded (use `->with()` or `->innerJoin()`)
- [ ] No N+1 queries in loops (use `withColumn()` for related data)
- [ ] Aggregations use SQL not PHP loops (use `SUM()`, `COUNT()`, etc.)
- [ ] Hash-based lookups used for set membership (not `in_array()` in loops)
- [ ] Large result sets either paginated or processed in batches
- [ ] Cache headers set on frequently accessed responses
- [ ] Frontend code is split by feature/route
- [ ] CSS tree-shaking removes unused utilities
- [ ] No unnec essary asset downloads or duplicate requests

---

**Last updated: February 16, 2026**
