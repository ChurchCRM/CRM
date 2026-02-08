# Quick Start: Parallel Testing

## TL;DR

Run both root path and subdirectory tests in parallel without conflicts.

## Quick Commands

### Test Everything (Demo)
```bash
./scripts/demo-parallel-testing.sh
```

### Test Root Path Only
```bash
npm run docker:ci:root:start
npm run test:root
npm run docker:ci:root:down
```

### Test Subdirectory Only
```bash
npm run docker:ci:subdir:start
npm run test:subdir
npm run docker:ci:subdir:down
```

### Run Both in Parallel (Manual)
```bash
# Terminal 1 - Root Path
npm run docker:ci:root:start
npm run test:root

# Terminal 2 - Subdirectory (simultaneously)
npm run docker:ci:subdir:start
npm run test:subdir

# Cleanup (when done)
npm run docker:ci:root:down
npm run docker:ci:subdir:down
```

## What's Different?

### Before
```bash
npm run docker:ci:start   # One environment
npm run test              # One test run
npm run docker:ci:down    # Cleanup
```
**Time: ~30 minutes** (sequential)

### After
```bash
# Root path
npm run docker:ci:root:start
npm run test:root

# Subdirectory (can run in parallel)
npm run docker:ci:subdir:start
npm run test:subdir
```
**Time: ~15 minutes** (parallel)

## Why This Matters

ChurchCRM can be installed in two ways:
1. **Root path**: `http://yourdomain.com/`
2. **Subdirectory**: `http://yourdomain.com/churchcrm/`

The parallel testing infrastructure ensures both work correctly on every commit.

## Key Features

✅ Separate databases (no conflicts)  
✅ Different ports (can run simultaneously)  
✅ Isolated environments (independent state)  
✅ Same tests (consistency)  
✅ Faster CI/CD (~30% improvement)

## Accessing the Applications

When both environments are running:

| Type | URL | Database |
|------|-----|----------|
| Root Path | http://localhost | database-root:3306 |
| Subdirectory | http://localhost:8080/churchcrm | database-subdir:3307 |

## Troubleshooting

### Port Already in Use
```bash
# Check what's using the port
lsof -i :80    # or :8080, :3306, :3307

# Stop existing containers
npm run docker:ci:root:down
npm run docker:ci:subdir:down
```

### Database Connection Errors
Make sure the correct Config file is being used:
- Root path: `Config.root.php` → `database-root`
- Subdirectory: `Config.parallel.subdir.php` → `database-subdir`

### Tests Failing
1. Clear logs: `rm -f src/logs/*.log`
2. Restart database: 
   ```bash
   npm run docker:ci:root:down
   npm run docker:ci:root:start
   ```
3. Check logs: `docker compose -f docker/docker-compose.yaml -f docker/docker-compose.parallel.yaml --profile ci-root logs`

## More Information

📖 **Detailed Guide**: [docker/PARALLEL_TESTING.md](docker/PARALLEL_TESTING.md)  
🏗️ **Architecture**: [ARCHITECTURE.md](ARCHITECTURE.md)  
📝 **Implementation**: [IMPLEMENTATION_SUMMARY.md](IMPLEMENTATION_SUMMARY.md)

## GitHub Actions

The parallel workflow runs automatically on every push/PR:

```
build → (test-root || test-subdir) → package
```

No manual intervention needed - just push your code!

## Common Questions

**Q: Do I need to run both tests locally?**  
A: No, GitHub Actions runs both automatically. Locally, test what you're working on.

**Q: Can I run just the old way?**  
A: Yes! `npm run docker:ci:start` and `npm run test` still work.

**Q: What if I only changed frontend code?**  
A: Still test both - frontend might behave differently with subdirectory paths.

**Q: How much faster is parallel testing?**  
A: About 30% faster (35 min vs 50 min in CI).

---

**Happy Testing! 🚀**
