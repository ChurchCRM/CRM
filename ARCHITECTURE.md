# Parallel Testing Architecture

## System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                        GitHub Actions Workflow                       │
│                  build-test-package-parallel.yml                     │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                          JOB: Build                                  │
│  • npm run package                                                   │
│  • Upload build artifact                                             │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                    ┌───────────────┴───────────────┐
                    │                               │
                    ▼                               ▼
┌───────────────────────────────┐  ┌───────────────────────────────┐
│      JOB: test-root           │  │     JOB: test-subdir          │
│  (RUNS IN PARALLEL)           │  │   (RUNS IN PARALLEL)          │
├───────────────────────────────┤  ├───────────────────────────────┤
│ • Download build artifact     │  │ • Download build artifact     │
│ • docker:ci:root:start        │  │ • docker:ci:subdir:start      │
│                               │  │                               │
│ ┌─────────────────────────┐   │  │ ┌─────────────────────────┐   │
│ │  database-root          │   │  │ │  database-subdir        │   │
│ │  Port: 3306             │   │  │ │  Port: 3307             │   │
│ │  Host: database-root    │   │  │ │  Host: database-subdir  │   │
│ └─────────────────────────┘   │  │ └─────────────────────────┘   │
│            │                  │  │            │                  │
│            ▼                  │  │            ▼                  │
│ ┌─────────────────────────┐   │  │ ┌─────────────────────────┐   │
│ │  webserver-root         │   │  │ │  webserver-subdir       │   │
│ │  Port: 80               │   │  │ │  Port: 8080             │   │
│ │  Path: /var/www/html    │   │  │ │  Path: /var/www/html/   │   │
│ │  URL: localhost/        │   │  │ │        churchcrm        │   │
│ └─────────────────────────┘   │  │ │  URL: localhost:8080/   │   │
│            │                  │  │ │        churchcrm        │   │
│            ▼                  │  │ └─────────────────────────┘   │
│ • npm run test:root           │  │            │                  │
│ • Cypress tests with          │  │            ▼                  │
│   baseUrl: localhost/         │  │ • npm run test:subdir         │
│                               │  │ • Cypress tests with          │
│ • docker:ci:root:down         │  │   baseUrl: localhost:8080/    │
│                               │  │           churchcrm/          │
└───────────────────────────────┘  │                               │
                    │               │ • docker:ci:subdir:down       │
                    │               └───────────────────────────────┘
                    │                               │
                    └───────────────┬───────────────┘
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                       JOB: package                                   │
│  (Only runs if BOTH tests pass)                                     │
│  • Download build artifact                                           │
│  • Upload release package                                            │
└─────────────────────────────────────────────────────────────────────┘
```

## Database Isolation

```
Root Path Environment              Subdirectory Environment
┌──────────────────┐              ┌──────────────────┐
│  database-root   │              │ database-subdir  │
│                  │              │                  │
│  Port: 3306      │              │  Port: 3307      │
│  Volume: root-db │              │  Volume: sub-db  │
│                  │              │                  │
│  NO SHARED STATE │              │  NO SHARED STATE │
└──────────────────┘              └──────────────────┘
        ↑                                  ↑
        │                                  │
   Config.root.php              Config.parallel.subdir.php
   $sSERVERNAME =              $sSERVERNAME =
   'database-root'             'database-subdir'
```

## Test Execution Flow

```
Sequential (OLD):                  Parallel (NEW):
─────────────────                 ─────────────────

┌─────────┐                       ┌─────────┐
│  Build  │  15 min               │  Build  │  15 min
└────┬────┘                       └────┬────┘
     │                                 │
     ▼                                 ├──────┬──────┐
┌─────────┐                       ┌────▼───┐ ┌───▼────┐
│Test Root│  15 min               │Test    │ │Test    │
└────┬────┘                       │Root    │ │Subdir  │
     │                            │15 min  │ │15 min  │
     ▼                            └────┬───┘ └───┬────┘
┌─────────┐                            │         │
│Test Sub │  15 min                    └────┬────┘
└────┬────┘                                 │
     │                                      ▼
     ▼                                 ┌─────────┐
┌─────────┐                            │ Package │  5 min
│ Package │  5 min                     └─────────┘
└─────────┘

Total: 50 min                     Total: 35 min
                                  Savings: 30%
```

## Port Allocation

| Component | Root Path | Subdirectory | Purpose |
|-----------|-----------|--------------|---------|
| Webserver | 80 | 8080 | HTTP access |
| Database | 3306 | 3307 | MySQL connection |
| Cypress baseUrl | localhost/ | localhost:8080/churchcrm/ | Test target |

## File Mapping

```
Installation Type: Root Path
────────────────────────────
Config:          docker/Config.root.php
Docker Compose:  docker/docker-compose.parallel.yaml (profile: ci-root)
Cypress Config:  docker/cypress.config.ts
NPM Commands:    docker:ci:root:start, test:root, docker:ci:root:down

Installation Type: Subdirectory
────────────────────────────────
Config:          docker/Config.parallel.subdir.php
Docker Compose:  docker/docker-compose.parallel.yaml (profile: ci-subdir)
Cypress Config:  docker/cypress.subdir.config.ts
NPM Commands:    docker:ci:subdir:start, test:subdir, docker:ci:subdir:down
```

## Key Design Principles

1. **Complete Isolation**: Separate databases prevent state pollution
2. **Port Separation**: Different ports allow simultaneous execution
3. **Shared Build**: Build once, test multiple times (artifact reuse)
4. **Fail Fast**: Package job only runs if all tests pass
5. **Resource Limits**: GitHub Actions resource constraints enforced
6. **Backward Compatibility**: Old scripts still work for single tests

## Benefits Summary

✅ **Speed**: 30% faster CI/CD pipeline  
✅ **Coverage**: Both installation types tested  
✅ **Isolation**: No database conflicts  
✅ **Reliability**: Independent test environments  
✅ **Flexibility**: Can run tests locally in parallel  
✅ **Safety**: Package only created if all tests pass
