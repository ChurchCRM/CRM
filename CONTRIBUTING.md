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

### Install Dev Tools

1. **Install Git:**
   - Please follow https://github.com/git-guides/install-git
      - note: if you would like to use a graphical interface, consider using the [GitHub desktop app](https://desktop.github.com/).

2. **Install Node.js version 20:**
   - Download and install Node.js version 20+ from the official website: [Node.js Downloads](https://nodejs.org/en/download/).

3. **Install Docker:**
   - Download and install Docker from the official website:
     - [Docker for Windows](https://docs.docker.com/desktop/install/windows/)
     - [Docker for macOS](https://docs.docker.com/desktop/install/mac/)
     - [Docker for Linux](https://docs.docker.com/desktop/install/linux/).

4. **Clone the Repository:**
   ```bash
   git clone https://github.com/your-username/ChurchCRM.git
   ```

5. **Install Dependencies:**
   ```bash
   npm ci
   npm run deploy
   ```

6. **Set Up Docker Containers:**
   ```bash
   docker compose -f "docker/docker-compose.test-php8-apache.yaml" up -d --build
   ```

7. **Access the Website:**
   - Open [http://localhost/](http://localhost/) in your browser and log in with `admin`/`changeme`.

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

We use Cypress for end-to-end testing. Follow the previously mentioned steps to set up Cypress and write tests for UI components and functionalities.

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

## Pull Request Process

1. Ensure your branch is up-to-date with the main branch:
   ```bash
   git pull origin main
   ```

2. Rebase your branch if necessary:
   ```bash
   git rebase main
   ```

3. Push your changes:
   ```bash
   git push origin feature-name
   ```

4. Submit a pull request via GitHub.

## Code of Conduct

Please adhere to the [Code of Conduct](CODE_OF_CONDUCT.md) in all interactions.

Thank you for your contribution!
