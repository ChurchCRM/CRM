# How to contribute
We love to hear ideas from our users!  It's what makes this platform so great and versatile.  If you have an idea to contribute, please take a few moments to share it with us!

The project welcomes, and depends on, contributions from developers and users in the open source community. Contributions can be made in a number of ways. A few examples are:

- Code patches via pull requests
- Documentation improvements
- Bug reports and patch reviews

## First Steps
1. Read this whole page "top to bottom."
2. Make sure you have a [GitHub account](https://github.com/signup/free)
3. Introduce yourself in the developer chat at [https://gitter.im/ChurchCRM/CRM](https://gitter.im/ChurchCRM/CRM)
4. Take a look at the [Open Issues](https://github.com/ChurchCRM/CRM/issues) page.
  We've made it easy for beginners with the [Good First Bug](https://github.com/ChurchCRM/CRM/issues?q=is%3Aopen+is%3Aissue+label%3A%22Good+first+bug%22) Label - these are issues that should be relatively easy to fix.
6. Have fun!


Certainly! Below is the CONTRIBUTING.md file with an added section mentioning the use of Slim MVC for new APIs and pages:


# Setting up your development environment

## Install Dev Tools

1. **Install Git:**
   - If GitHub desktop app is not already installed, download and install it from [here](https://desktop.github.com/).

2. **Install Node.js version 20:**
   - Download and install Node.js version 20+ from the official website: [Node.js Downloads](https://nodejs.org/en/download/)

3. **Install Docker:**
   - Download and install Docker from the official website:
     - [Docker for Windows](https://docs.docker.com/desktop/install/windows/)
     - [Docker for macOS](https://docs.docker.com/desktop/install/mac/)
     - [Docker for Linux](https://docs.docker.com/desktop/install/linux/)

4. **Clone the repository:**

   ```markdown
   git clone https://github.com/your-username/ChurchCRM.git
   ```

5. **Install dependencies:**
   ```markdown
   npm install
   npm run deploy
   ```

6. **Set up Docker containers:**
   ```
   docker compose -f "docker/docker-compose.test-php8-apache.yaml" up -d --build
   ```

7. **Access the website:**
   - open http://localhost/ in your browser and login with admin/changeme

## User Interface using AdminLTE

ChurchCRM utilizes the AdminLTE framework for its user interface. Follow these guidelines when working on the UI:

1. **Understanding AdminLTE:**
   - Familiarize yourself with [AdminLTE](https://adminlte.io/), the framework used for the ChurchCRM user interface.

2. **Making UI Changes:**
   - UI components are located in the `src` directory.
   - When making changes to the UI, ensure they align with the design principles of AdminLTE.

3. **Custom Styling:**
   - If you need to add custom styling, do so in a modular and organized manner. Create separate CSS files for custom styles.

4. **Responsive Design:**
   - Ensure that UI changes are responsive and work well across different screen sizes.

## Slim MVC for New APIs and Pages

For new APIs and pages, ChurchCRM follows the Slim MVC (Model-View-Controller) architecture. Follow these guidelines when working on new functionalities:

## Adding Tests with Cypress

We use Cypress for end-to-end testing. Follow the previously mentioned steps to set up Cypress and write tests for UI components and functionalities.

## Development Workflow

1. **Branching:**
   - Create a feature branch for your changes:
     ```
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
   ```
   git pull origin main
   ```

2. Rebase your branch if necessary:
   ```
   git rebase main
   ```

3. Push your changes:
   ```
   git push origin feature-name
   ```

4. Submit a pull request via GitHub.

## Code of Conduct

Please adhere to the [Code of Conduct](CODE_OF_CONDUCT.md) in all interactions.

Thank you for your contribution!
