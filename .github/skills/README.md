# ChurchCRM Development Skills

This directory contains modular, task-focused development skills for AI coding agents working on ChurchCRM. Each skill covers a specific workflow or technical area, making it easier to understand and apply best practices without needing to reference a massive single document.

## Why Skills?

The original [copilot-instructions.md](../copilot-instructions.md) was a comprehensive 1800+ line document. Breaking it into focused skills provides several benefits:

1. **Easier to navigate** - Find exactly what you need without scrolling through thousands of lines
2. **Better context loading** - AI agents can load only relevant skills for the task at hand
3. **Clearer organization** - Each skill is self-contained with all necessary context
4. **Easier to maintain** - Update specific skills without affecting others
5. **No context loss** - All original guidance preserved, just better organized

## Available Skills

### 1. [Database Operations](./database-operations.md)
**When to use:** Any database access, ORM queries, or data persistence

- Perpl ORM (fork of Propel2) patterns and critical differences
- Method naming conventions and how to find correct query methods
- withColumn() usage with TableMap constants
- Common migration patterns from raw SQL to ORM
- Method override signatures and lifecycle hooks

### 2. [API Development](./api-development.md)
**When to use:** Creating/modifying REST API endpoints, handling API requests

- Slim 4 route patterns and middleware order
- API error handling with SlimUtils::renderErrorJSON()
- HTTP header handling and caching strategies
- Admin API vs public API structure
- Webpack TypeScript API utilities
- Client-side API request patterns (AdminAPIRequest, fetch)

### 3. [Authorization & Security](./authorization-security.md)
**When to use:** Implementing permission checks, authentication, or security features

- User authorization methods (role-based and object-level)
- RedirectUtils for safe navigation and security redirects
- InputUtils for XSS protection and HTML sanitization
- TLS/SSL verification for network requests
- CVE handling and security vulnerability fixes
- Security policy and disclosure guidelines

### 4. [Service Layer](./service-layer.md)
**When to use:** Creating business logic, building service classes

- Service layer first principle
- Performance best practices (selective loading, single query philosophy, avoid N+1)
- Logging standards with LoggerUtils
- SystemConfig for UI settings panels
- When to create API endpoints vs direct service calls

### 5. [Admin MVC Migration](./admin-mvc-migration.md)
**When to use:** Migrating legacy pages to modern MVC structure

- File organization (views, routes, APIs, services)
- Admin System Pages structure (/admin/system/)
- Finance Module patterns (/finance/)
- Route examples and middleware configuration
- Entry point error handling
- Migration workflow from legacy to modern

### 6. [Frontend Development](./frontend-development.md)
**When to use:** UI changes, JavaScript/CSS work, internationalization

- Bootstrap 4.6.2 (CRITICAL: not Bootstrap 5!)
- Asset paths with SystemURLs::getRootPath()
- Notifications (window.CRM.notify, NEVER alert())
- Confirmations (bootbox.confirm, NEVER confirm())
- Bootstrap 4 modals
- Internationalization (i18next, gettext)
- i18n term consolidation patterns
- Server-side rendering best practices

### 7. [Testing](./testing.md)
**When to use:** Writing tests, debugging failures, running test suites

- Cypress configuration and logging
- API test patterns and required categories
- Session-based login pattern for UI tests (REQUIRED)
- Debugging 500 errors workflow
- Test requirements before committing
- Docker test management

### 8. [Plugin Development](./plugin-development.md)
**When to use:** Creating/modifying plugins, extending ChurchCRM

- Plugin architecture and file structure
- plugin.json manifest format
- Creating plugin classes extending AbstractPlugin
- Plugin routes and views
- Plugin config access (sandboxed)
- Using PluginManager (static methods)
- Available hooks and hook registration
- Error handling in plugin entry points

### 9. [Code Standards](./code-standards.md)
**When to use:** General coding, quality checks, PR reviews

- PHP 8.4+ requirements and import statement rules
- Database access standards
- File inclusion (require vs include)
- Algorithm performance patterns (avoid O(N*M))
- Logging standards
- File operations with git
- Commit and PR standards
- Pre-commit checklist
- Agent behavior guidelines

### 10. [Development Workflows](./development-workflows.md)
**When to use:** Setup, build, deploy, Docker management

- Quick start (Codespaces, Dev Containers)
- Setup and build processes
- Docker management (dev, test, ci)
- Testing workflows (local and CI/CD)
- Build processes (frontend, PHP, locale)
- File locations reference
- Development best practices
- Configuration files overview

### 11. [Slim 4 Best Practices](./slim-4-best-practices.md)
**When to use:** Building REST APIs, creating routes, middleware configuration

- Application setup and container configuration
- Middleware ordering (LIFO - critical!)
- Route grouping and nesting patterns
- Response handling (JSON, redirects, files)
- Dependency injection patterns
- Error handling with SlimUtils::renderErrorJSON()
- Type safety with request/response objects

### 12. [Webpack & TypeScript](./webpack-typescript.md)
**When to use:** Frontend bundling, React components, asset management

- Critical window.CRM timing issues and solutions
- API utilities for safe runtime URL construction (api-utils.ts)
- Entry point patterns (JavaScript, TypeScript, React)
- Type safety patterns and generics
- CSS organization and tree shaking
- Preventing common bundling errors
- Best practices for module development

### 13. [Bootstrap 4.6.2 & AdminLTE v3.2.0](./bootstrap-adminlte.md)
**When to use:** Building UI components, styling layouts, admin pages

- Bootstrap 4.6.2 grid system and breakpoints
- AdminLTE v3.2.0 small boxes (stats/KPIs)
- Card components with collapse and tools
- Data tables with styling
- Badges, alerts, and utilities
- Flexbox and spacing utilities
- Admin page layout patterns
- CRITICAL: Bootstrap 5 classes to avoid

### 14. [Cypress Testing](./cypress-testing.md)
**When to use:** Writing tests, debugging failures, CI/CD testing

- Test file organization (UI vs API)
- Session-based login pattern (REQUIRED)
- Test configuration and credentials
- UI test best practices with element IDs
- API test patterns and required categories
- Debugging 500 errors workflow
- Test file organization and execution

### 15. [PHP Best Practices](./php-best-practices.md)
**When to use:** PHP code development, Service layer implementation, Database operations

- PHP 8.3+ requirements and standards
- Import statements and namespacing rules
- Perpl ORM Query methods and patterns
- Service layer architecture and performance
- Authorization checks and permission handling
- InputUtils for XSS protection and sanitization
- RedirectUtils for safe navigation
- Error handling patterns (services vs APIs)
- Logging standards and loggers
- Common patterns (null safety, algorithms, TLS/SSL)
- Code quality checklist

### 16. [Wiki Documentation](./wiki-documentation.md)
**When to use:** Creating complex documentation, admin guides, architecture decisions

- When to use wiki vs skills vs code comments
- Wiki article structure and best practices
- Documentation examples (Admin guides, Developer guides)
- Using diagrams with Mermaid
- Writing style and voice guidelines
- Wiki navigation and organization strategies
- Maintenance and keeping documentation current
- Simple vs complex content guidelines

### 17. [Routing & Project Architecture](./routing-architecture.md)
**When to use:** Organizing code structure, adding new routes, managing project file layout

- API routes structure (`/api/*`)
- Admin pages structure (`/admin/system/*`)
- Admin APIs structure (`/admin/api/*`)
- Finance module structure (`/finance/*`)
- Route patterns and best practices
- Menu system integration
- File organization principles
- Testing routes (unit and integration)

### 18. [Plugin System & Extensibility](./plugin-system.md)
**When to use:** Creating or modifying plugins, extending ChurchCRM functionality

- Plugin architecture and core components
- Plugin structure and plugin.json manifest
- Creating plugin classes extending AbstractPlugin
- Plugin routes and views patterns
- Configuration management (sandboxed)
- PluginManager static methods
- Available hook points (15+ system hooks)
- Registering hooks and hook handlers
- Slim entry point error handling
- Core plugins reference
- Best practices for performance and error handling

### 19. [Git Workflow & Development Standards](./git-workflow.md)
**When to use:** Code organization, commits, PRs, pre-commit validation

- Branch naming convention (fix/issue-NUMBER-description)
- Branch lifecycle and workflow
- Commit message format (imperative, < 72 chars)
- Multi-line commit messages
- Pull request organization and description format
- PR code review checklist
- Pre-commit validation (23-item checklist)
- Agent-specific commit behaviors
- Troubleshooting common git issues

### 20. [i18n & Localization Best Practices](./i18n-localization.md)
**When to use:** Adding UI text, working with translations, reducing translator burden

- Terminology & UI conventions (canonical terms)
- People vs Persons distinction (UI vs API/internal)
- Family lifecycle (Active/Inactive vs Deactivated)
- Adding new UI terms workflow
- Term consolidation patterns (reduce 880 → 315 translations!)
- Delete confirmation consolidation example
- "Add New" button consolidation
- General consolidation principles
- Locale rebuild workflow (`npm run locale:build`)
- PHP localization with gettext()
- JavaScript localization with i18next.t()
- Pre-commit i18n checklist

### 21. [Configuration Management](./configuration-management.md)
**When to use:** Adding settings, managing SystemConfig, creating admin panels

- SystemConfig basic methods and usage
- Boolean configuration with `getBooleanValue()`
- Asset paths with `SystemURLs::getRootPath()`
- Settings panels with `getSettingsConfig()`
- Admin settings panel patterns (Service → Route → View)
- Setting types and form rendering
- Configuration workflow examples
- Performance best practices
- Configuration debugging

### 22. [Security Best Practices](./security-best-practices.md)
**When to use:** Implementing security features, handling sensitive operations, security reviews

- Core security principles and defense layers
- HTML sanitization & XSS protection (InputUtils methods)
- Method selection decision tree (sanitizeText vs sanitizeHTML vs escapeHTML)
- SQL injection prevention with ORM
- Authorization patterns (role-based and object-level)
- Authorization redirect patterns
- TLS/SSL verification (secure by default)
- API error handling with SlimUtils::renderErrorJSON()
- CVE & security vulnerability handling
- Pre-release security checklist

### 23. [Performance Optimization & Best Practices](./performance-optimization.md)
**When to use:** Optimizing queries, scaling for large data, improving response times

- Database query optimization (selective fields, eager loading, batch operations)
- N+1 query prevention patterns
- Algorithm efficiency patterns (O(N*M) vs O(N+M))
- Hash-based lookups for performance
- Frontend code splitting and tree shaking
- Caching strategies (HTTP headers, middleware, response caching)
- Profiling and monitoring with slow query logs
- Performance checklist for code reviews
- Real codebase examples of good and problematic patterns

### 24. [Modern PHP 8.3+ & Framework Best Practices](./modern-php-frameworks.md)
**When to use:** Security hardening, using framework features correctly, upgrading patterns

- Password hashing with Argon2ID and password pepper
- Session security hardening (strict mode, HTTPS only, HTTPOnly, SameSite)
- Error display hardening (production vs development)
- Slim 4 middleware ordering (LIFO critical!)
- Dependency injection patterns with container
- Error handling in routes (avoid exceptions, use sanitized responses)
- Perpl ORM eager loading and batch operations
- Query optimization with findObjects() and selective fields
- Type-safe joins with useXXXQuery()
- Best practices checklist by category

## How to Use These Skills

### For AI Agents

When working on a task:
1. **Identify the workflow** - What type of work is being done?
2. **Load relevant skills** - Load only the skills needed for the task
3. **Follow the patterns** - Apply the specific guidance from each skill
4. **Combine when needed** - Multiple skills may be relevant for complex tasks

### For AI Agents

When working on a task:
1. **Identify the workflow** - What type of work is being done?
2. **Load relevant skills** - Load only the skills needed for the task
3. **Follow the patterns** - Apply the specific guidance from each skill
4. **Combine when needed** - Multiple skills may be relevant for complex tasks

**Example workflows:**

- **Creating a new API endpoint**: Load skills #2 (API Development), #4 (Service Layer), #11 (Slim 4 Best Practices), #22 (Security), #23 (Performance), #24 (Modern PHP), #14 (Cypress Testing), #15 (PHP Best Practices), #19 (Git Workflow)
- **Migrating a legacy page**: Load skills #17 (Routing & Architecture), #6 (Frontend Development), #1 (Database Operations), #13 (Bootstrap & AdminLTE), #15 (PHP Best Practices), #19 (Git Workflow)
- **Fixing a security issue**: Load skills #22 (Security Best Practices), #24 (Modern PHP), #15 (PHP Best Practices), #19 (Git Workflow), #9 (Code Standards)
- **Adding a new plugin**: Load skills #18 (Plugin System), #2 (API Development), #6 (Frontend Development), #15 (PHP Best Practices), #19 (Git Workflow)
- **Optimizing database queries**: Load skills #23 (Performance), #1 (Database Operations), #4 (Service Layer), #15 (PHP Best Practices), #19 (Git Workflow)
- **Managing admin UI pages**: Load skills #17 (Routing & Architecture), #11 (Slim 4), #13 (Bootstrap & AdminLTE), #12 (Webpack & TypeScript), #21 (Configuration), #23 (Performance), #15 (PHP Best Practices), #19 (Git Workflow)
- **Writing tests**: Load skills #14 (Cypress Testing), #7 (Testing), #19 (Git Workflow)
- **Creating documentation**: Load skills #16 (Wiki Documentation) for complex topics, inline code comments for simple items
- **Implementing a Service**: Load skills #4 (Service Layer), #15 (PHP Best Practices), #1 (Database Operations), #21 (Configuration), #23 (Performance), #19 (Git Workflow)
- **Organizing code structure**: Load skills #17 (Routing & Architecture), #18 (Plugin System), #15 (PHP Best Practices), #24 (Modern PHP)
- **Adding UI text**: Load skills #20 (i18n & Localization), #15 (PHP Best Practices), #19 (Git Workflow)
- **Git & commit workflow**: Load skills #19 (Git Workflow), #20 (i18n & Localization for locale:build)
- **Security hardening**: Load skills #22 (Security), #24 (Modern PHP), #19 (Git Workflow)

### For Human Developers

- **Quick reference** - Jump to the skill covering your current task
- **Learning guide** - Read skills to understand ChurchCRM patterns
- **Quality check** - Use skills to verify your code follows standards
- **Pre-commit review** - Check relevant skills before submitting PRs

## Maintaining These Skills

### When to Update

- New patterns are established in the codebase
- Technology is upgraded (PHP version, frameworks, etc.)
- Common mistakes are identified
- Standards change or evolve

### How to Update

1. **Find the relevant skill** - Identify which skill needs updating
2. **Update the skill file** - Make changes to the specific .md file
3. **Keep examples current** - Ensure code examples match actual codebase patterns
4. **Update this README** - If skill purpose changes significantly

### Adding New Skills

If a new workflow area emerges:
1. **Create new skill file** - Follow the existing format
2. **Add to this README** - Document when to use the new skill
3. **Update copilot-instructions.md** - Reference the new skill
4. **Keep it focused** - One skill = one workflow/area

## Relationship to copilot-instructions.md

The main [copilot-instructions.md](../copilot-instructions.md) file now serves as:
- **Entry point** - Quick reference to load skills
- **Core conventions** - Essential patterns used across all skills
- **Terminology** - Project-standard naming and conventions
- **Index** - Points to specific skills for detailed guidance

For detailed workflow guidance, always refer to the appropriate skill file rather than adding more content to the main copilot-instructions.md.

## Questions or Improvements?

If you find:
- **Missing information** - Add it to the relevant skill
- **Unclear guidance** - Clarify in the skill file
- **Conflicting advice** - Align the skills and main instructions
- **New patterns** - Document in appropriate skill or create new one

Keep skills focused, actionable, and aligned with actual codebase patterns.

---

**Last updated:** February 16, 2026 (24 skills - includes sub-agent findings)
