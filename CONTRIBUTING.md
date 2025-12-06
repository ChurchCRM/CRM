# How to Contribute
We love to hear ideas from our users! It's what makes this platform so great and versatile. If you have an idea to contribute, please take a few moments to share it with us!

The project welcomes, and depends on, contributions from developers and users in the open source community. Contributions can be made in several ways. A few examples are:

- Code patches via pull requests
- Documentation improvements
- Bug reports and patch reviews

## First Steps
1. Read this whole page "top to bottom."
2. Make sure you have a [GitHub account](https://github.com/signup/free).
3. Introduce yourself in the developer chat at [Gitter](https://gitter.im/ChurchCRM/CRM).
4. Take a look at the [Open Issues](https://github.com/ChurchCRM/CRM/issues) page. We've made it easy for beginners with the [good first issue](https://github.com/ChurchCRM/CRM/labels/good%20first%20issue) label — these are issues that should be relatively easy to fix.
5. Have fun!

## Setting Up Your Development Environment

### Quick Start (Recommended)

**🚀 GitHub Codespaces (Easiest):**
1. Click "Code" → "Codespaces" → "Create codespace on [branch]"
2. Wait 2-3 minutes for automatic setup
3. Open `http://localhost` and login with `admin`/`changeme`

**🐳 VS Code Dev Containers:**
1. Install the "Dev Containers" extension in VS Code
2. Open this repo in VS Code
3. Click "Reopen in Container" when prompted
4. Wait for setup to complete

### Manual Setup

If you prefer manual setup or the automatic options don't work:

1. **Install Prerequisites:**
   - [Git](https://github.com/git-guides/install-git) (or [GitHub Desktop](https://desktop.github.com/))
   - [Node.js version 20+](https://nodejs.org/en/download/)
   - [Docker](https://docs.docker.com/desktop/)

2. **Clone and Setup:**
   ```bash
   git clone https://github.com/your-username/ChurchCRM.git
   cd ChurchCRM
   ./scripts/setup-dev-environment.sh
   ```

3. **Access the Website:**
   - ChurchCRM: [http://localhost/](http://localhost/) (`admin`/`changeme`)
   - MailHog: [http://localhost:8025](http://localhost:8025) (email testing)
   - Adminer: [http://localhost:8088](http://localhost:8088) (database admin)

### Troubleshooting

**Common Issues:**
- **Port 80 conflicts:** Stop other web servers or change Docker port mapping
- **Docker build fails:** Ensure Docker has 4GB+ memory allocated
- **Services not starting:** Run `npm run docker:dev:logs` to check logs
- **Tests failing locally:** Use `npm run docker:test:rebuild` for clean test environment
- **Missing PHP extensions:** Use Docker containers - don't build locally
- **Database schema outdated:** Run `npm run docker:test:restart:db` to refresh

**Quick Commands:**
```bash
# Development
npm run docker:dev:logs       # View service logs
npm run docker:dev:stop       # Stop containers
npm run build:frontend        # Rebuild JS/CSS after changes

# Testing
npm run test                  # Run Cypress tests
npm run docker:test:restart   # Restart test containers
npm run docker:test:rebuild   # Full rebuild (remove volumes, rebuild images)
```

### User Interface using AdminLTE

ChurchCRM utilizes the AdminLTE framework for its user interface. Follow these guidelines when working on the UI:

1. **Understanding AdminLTE:**
   - Familiarize yourself with [AdminLTE](https://adminlte.io/), the framework used for the ChurchCRM user interface.

2. **Making UI Changes:**
   - UI components are located in the `src` directory. Ensure your changes align with the design principles of AdminLTE.

3. **Custom Styling:**
   - If you need to add custom styling, do so in a modular and organized manner. Create separate CSS files for custom styles.

4. **Responsive Design:**
   - Ensure that UI changes are responsive and work well across different screen sizes.

### Slim MVC for New APIs and Pages

For new APIs and pages, ChurchCRM follows the Slim MVC (Model-View-Controller) architecture. Follow these guidelines when working on new functionalities.

### Adding Tests with Cypress

We use Cypress for end-to-end testing. The test environment is automatically configured in Codespaces/Dev Containers.

**Running Tests:**
```bash
npm run test              # Run all tests (headless)
npm run test:ui           # Interactive browser testing
```

**Test Structure:**
- API tests: `cypress/e2e/api/private/[feature]/[endpoint].spec.js`
- UI tests: `cypress/e2e/ui/[feature]/`
- Use helper commands: `cy.loginAdmin()`, `cy.makePrivateAdminAPICall()`, etc.

For complete testing guidelines, see `.github/copilot-instructions.md`.

## Development Workflow

1. **Branching:**
   - Create a feature branch for your changes:
   ```bash
   git checkout -b feature-name
   ```

2. **Coding Standards:**
   - Adhere to the existing coding standards and style, especially in UI components and MVC structures.

3. **Testing:**
   - Write tests for UI components and functionalities using Cypress.

4. **Documentation:**
   - Update relevant documentation if your changes impact the UI or introduce new APIs/pages.

5. **Commit Messages:**
   - Use descriptive commit messages in the present tense.
   - For complete standards, see `.github/copilot-instructions.md`

## AI Coding Standards

ChurchCRM uses **standardized AI agent instructions** to ensure consistent code quality across all contributions.

### Quick Reference
**See `.github/copilot-instructions.md` for complete standards including:**
- ✅ **Database:** Propel ORM mandatory (no raw SQL)
- ✅ **HTML5:** Bootstrap 4.6.2 CSS only, no deprecated attributes
- ✅ **Asset Paths:** Use `SystemURLs::getRootPath()`
- ✅ **Services:** Business logic in Service classes
- ✅ **PHP 8.2+:** (See [System Requirements](https://github.com/ChurchCRM/CRM/wiki/ChurchCRM-Application-Platform-Prerequisites)) Explicit nullable types, modern patterns
- ✅ **i18n:** Wrap UI text with `gettext()` or `i18next.t()`

### Using AI Assistance
If using GitHub Copilot, Claude, or other AI tools:
1. Reference `.github/copilot-instructions.md` for project-specific patterns
2. **CRITICAL:** Follow Slim Framework 4 LIFO middleware order (see copilot-instructions.md)
   - Slim 4 executes middleware in reverse order (last added runs first)
   - Wrong order causes "No active authentication provider" errors
3. Verify all code follows standards before committing
4. Run syntax check: `php -l src/YourFile.php`

## Pull Request Process

1. Ensure your branch is up-to-date with the master branch:
   ```bash
   git pull origin master
   ```

2. Rebase your branch if necessary:
   ```bash
   git rebase master
   ```

3. Push your changes:
   ```bash
   git push origin feature-name
   ```

4. Submit a pull request via GitHub.

## Detailed Workflow Guide

For comprehensive information on issue workflows, branching strategies, PR requirements, and merge processes, see our [Development Workflow Documentation](https://github.com/ChurchCRM/CRM/wiki/Contributing).

## Code of Conduct

Please adhere to the [Code of Conduct](CODE_OF_CONDUCT.md) in all interactions.

Thank you for your contribution!
