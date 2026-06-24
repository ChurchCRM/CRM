// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************

/**
 * Internal helper: Sets up a cached login session using cy.session()
 * Performs login and validates with CRM cookie presence
 * @param {string} sessionName - Unique identifier for this session (e.g., 'admin-session')
 * @param {string} username - The username to authenticate with
 * @param {string} password - The password to authenticate with
 * @param {{ forceLogin?: boolean }} options - Additional behaviour flags
 */
Cypress.Commands.add('setupLoginSession', (sessionName, username, password, options = {}) => {
    const { forceLogin = false } = options;
    const uniqueSuffix = forceLogin ? `-${Date.now()}-${Math.random().toString(36).slice(2, 8)}` : '';
    const effectiveSessionName = `${sessionName}${uniqueSuffix}`;

    cy.session(
        effectiveSessionName,
        () => {
            // Perform the login
            cy.visit('/login');
            cy.get('input[name=User]').type(username);
            cy.get('input[name=Password]').type(password + '{enter}');
            // Wait for redirect away from session/login pages
            // ChurchCRM's login page is /session/begin, not /login
            cy.url().should('not.include', '/session/begin');
        },
        {
            // Validate session by checking for a CRM cookie
            validate: () => {
                cy.getCookies().should('satisfy', (cookies) => {
                    return cookies.some(cookie => cookie.name.startsWith('CRM-'));
                });
            }
        }
    );
});

/**
 * Sets up a cached admin login session for Cypress UI tests.
 * Reads credentials from the Cypress config env (cypress/configs/docker.config.ts
 * for the standard CI/dev runner, cypress/configs/new-system.config.ts for
 * the new-system job). See `.agents/skills/churchcrm/cypress-testing.md`
 * for the full rationale.
 * Usage in test files:
 *   beforeEach(() => cy.setupAdminSession());
 *
 * Note: Uses cy.session() with explicit validation to cache login across test runs.
 * If validation fails, the session is cleared and login is re-attempted.
 */
Cypress.Commands.add('setupAdminSession', (options = {}) => {
    const username = Cypress.env('admin.username');
    const password = Cypress.env('admin.password');
    if (!username || !password) {
        throw new Error('Admin credentials not configured in cypress/configs/docker.config.ts (or cypress/configs/new-system.config.ts) env: admin.username and admin.password required');
    }
    cy.setupLoginSession('admin-session', username, password, options);
});

/**
 * Sets up a cached standard user login session for Cypress UI tests.
 * Reads credentials from the Cypress config env
 * (cypress/configs/docker.config.ts for the standard CI/dev runner).
 * Usage in test files:
 *   beforeEach(() => cy.setupStandardSession());
 *
 * Note: Uses cy.session() with explicit validation to cache login across test runs.
 * If validation fails, the session is cleared and login is re-attempted.
 */
Cypress.Commands.add('setupStandardSession', (options = {}) => {
    const username = Cypress.env('standard.username');
    const password = Cypress.env('standard.password');
    if (!username || !password) {
        throw new Error('Standard user credentials not configured in cypress/configs/docker.config.ts env: standard.username and standard.password required');
    }
    cy.setupLoginSession('standard-session', username, password, options);
});

/**
 * Sets up a cached session for a user WITHOUT finance permissions.
 * Used to test that finance pages correctly deny access to non-finance users.
 * Reads credentials from the Cypress config env
 * (cypress/configs/docker.config.ts for the standard CI/dev runner).
 * Usage in test files:
 *   beforeEach(() => cy.setupNoFinanceSession());
 */
Cypress.Commands.add('setupNoFinanceSession', (options = {}) => {
    const username = Cypress.env('nofinance.username');
    const password = Cypress.env('nofinance.password');
    if (!username || !password) {
        throw new Error('No-finance user credentials not configured in cypress/configs/docker.config.ts env: nofinance.username and nofinance.password required');
    }
    cy.setupLoginSession('nofinance-session', username, password, options);
});

/**
 * cy.loginWithCredentials(username, password, sessionName, expectSuccess = true)
 * Login with custom credentials (for testing password changes, etc.)
 * Creates a new session with the provided credentials
 * If expectSuccess is false, skips CRM cookie validation (for testing bad credentials)
 */
Cypress.Commands.add('loginWithCredentials', (username, password, sessionName = 'custom-session', expectSuccess = true) => {
    cy.session(sessionName, () => {
        cy.visit('/login');
        cy.get('input[name=User]').type(username);
        cy.get('input[name=Password]').type(password + '{enter}');
    }, {
        validate: () => {
            if (expectSuccess) {
                cy.getCookies().should('satisfy', (cookies) => {
                    return cookies.some(cookie => cookie.name.startsWith('CRM-'));
                });
            }
        }
    });
});

Cypress.Commands.add("buildRandom", (prefixString) => {
    const rand = Math.random().toString(36).substring(7);
    return prefixString.concat(" - ", rand);
});

// Modern command for better element interaction
Cypress.Commands.add("getByTestId", (testId) => {
    return cy.get(`[data-cy="${testId}"], [data-testid="${testId}"]`);
});

// Birthday calendar test commands
Cypress.Commands.add('createPersonWithBirthday', (personData) => {
    cy.visit('/PersonEditor.php');
    
    cy.get("#FirstName").type(personData.name);
    cy.get("#LastName").type("TestUser");
    cy.get("#Gender").select("1");
    
    // Set birthday fields
    if (personData.month > 0) {
        cy.get("#BirthMonth").select(personData.month.toString());
    }
    if (personData.day > 0) {
        cy.get("#BirthDay").select(personData.day.toString());
    }
    if (personData.year) {
        cy.get("#BirthYear").clear().type(personData.year.toString());
    }
    
    cy.get("#Classification").select("1");
    cy.get("#PersonSaveButton").click();
    
    // Wait for save to complete
    cy.url().should("contain", "people/view/");
});

Cypress.Commands.add('deletePersonByName', (name) => {
    cy.apiRequest({
        method: 'GET',
        url: '/api/search/' + name
    }).then((response) => {
        if (response.body && response.body.length > 0) {
            const personId = response.body[0].children[0].id;
            cy.apiRequest({
                method: 'DELETE',
                url: `/api/persons/${personId}`
            });
        }
    });
});

Cypress.Commands.add('createPeopleViaCSV', (peopleData) => {
    // Build CSV with headers that auto-map to ChurchCRM fields
    const headers = 'LastName,FirstName,Address1,City,State,Zip,Email,BirthDate,HomePhone';
    let csvContent = headers + '\n';

    Object.values(peopleData).forEach(person => {
        const birthDate = person.year ?
            `${person.year}-${String(person.month).padStart(2, '0')}-${String(person.day).padStart(2, '0')}` :
            (person.month > 0 && person.day > 0) ?
                `0000-${String(person.month).padStart(2, '0')}-${String(person.day).padStart(2, '0')}` :
                '';

        const row = [
            'TestUser',
            person.name,
            '123 Test St',
            'TestCity',
            'TX',
            '77777',
            `${person.name}@test.com`,
            birthDate,
            '555-123-4567'
        ].join(',');
        csvContent += row + '\n';
    });

    // Write file and import via new Slim 4 CSV import UI
    cy.writeFile('cypress/downloads/test_birthday_people.csv', csvContent);

    cy.visit('/admin/import/csv');
    cy.get('#csvFile').selectFile('cypress/downloads/test_birthday_people.csv', { force: true });
    cy.get('#csv-import-form').submit();

    // Wait for mapping step (upload + auto-map)
    cy.get('#mapping-card', { timeout: 10000 }).should('be.visible');

    // Execute import (fields are auto-mapped from headers)
    cy.get('#execute-import').click();

    // Wait for summary card
    cy.get('#summary-card', { timeout: 10000 }).should('be.visible');
});

// ============================================================
// Tom Select Testing Commands
// ============================================================

/**
 * Wait for TomSelect to initialize on an element
 * TomSelect wraps the original select in a .ts-wrapper sibling
 * @param {string} selector - CSS selector for the original select element
 * @example cy.tomSelectReady('#Country')
 */
Cypress.Commands.add('tomSelectReady', (selector) => {
    cy.get(selector, { timeout: 10000 }).should($el => {
        const el = $el[0];
        expect(el.tomselect, 'TomSelect should be initialized').to.exist;
    });
});

/**
 * Select an option in a TomSelect dropdown by visible text
 * @param {string} selector - CSS selector for the original select element
 * @param {string} text - The text of the option to select
 * @example cy.tomSelectByText('#Country', 'United States')
 */
Cypress.Commands.add('tomSelectByText', (selector, text) => {
    // Click the TomSelect control to open the dropdown
    cy.get(selector).siblings('.ts-wrapper').find('.ts-control').click();

    // Type in the search input
    cy.get(selector).siblings('.ts-wrapper').find('.ts-control input')
        .type(text);

    // Wait for results and click the matching option
    cy.get(selector).siblings('.ts-wrapper').find('.ts-dropdown .ts-dropdown-content .option', { timeout: 5000 })
        .should('be.visible')
        .contains(text)
        .click({ force: true });
});

/**
 * Select an option in a TomSelect dropdown by value (programmatically)
 * @param {string} selector - CSS selector for the original select element
 * @param {string} value - The value of the option to select
 * @example cy.tomSelectByValue('#Country', 'us')
 */
Cypress.Commands.add('tomSelectByValue', (selector, value) => {
    cy.get(selector).then($select => {
        const el = $select[0];
        if (el.tomselect) {
            el.tomselect.setValue(value);
        }
    });
});

/**
 * Type and search in a TomSelect with remote/AJAX data
 * @param {string} selector - CSS selector for the original select element
 * @param {string} searchText - Text to search for
 * @param {string} resultText - Text of the result to click (optional, clicks first if not provided)
 * @example cy.tomSelectSearch('.personSearch', 'John', 'John Doe')
 */
Cypress.Commands.add('tomSelectSearch', (selector, searchText, resultText = null) => {
    // Click the TomSelect control to focus
    cy.get(selector).siblings('.ts-wrapper').find('.ts-control').click();

    // Type in the search input
    cy.get(selector).siblings('.ts-wrapper').find('.ts-control input')
        .type(searchText);

    // Wait for results
    cy.get(selector).siblings('.ts-wrapper').find('.ts-dropdown .ts-dropdown-content .option', { timeout: 10000 })
        .should('be.visible');

    // Click the specific result or the first one
    if (resultText) {
        cy.get(selector).siblings('.ts-wrapper').find('.ts-dropdown .ts-dropdown-content .option')
            .contains(resultText)
            .click({ force: true });
    } else {
        cy.get(selector).siblings('.ts-wrapper').find('.ts-dropdown .ts-dropdown-content .option')
            .first()
            .click({ force: true });
    }
});

/**
 * Verify TomSelect is initialized on an element
 * @param {string} selector - CSS selector for the original select element
 * @example cy.tomSelectIsInitialized('#Country')
 */
Cypress.Commands.add('tomSelectIsInitialized', (selector) => {
    cy.get(selector).should($select => {
        expect($select[0].tomselect, 'TomSelect instance').to.exist;
    });
    cy.get(selector).siblings('.ts-wrapper').should('exist');
});

/**
 * Clear a TomSelect selection
 * @param {string} selector - CSS selector for the original select element
 * @example cy.tomSelectClear('#Country')
 */
Cypress.Commands.add('tomSelectClear', (selector) => {
    cy.get(selector).then($select => {
        const el = $select[0];
        if (el.tomselect) {
            el.tomselect.clear();
        }
    });
});

/**
 * Get the currently selected text from a TomSelect
 * @param {string} selector - CSS selector for the original select element
 * @example cy.tomSelectGetSelected('#Country').should('contain', 'United States')
 */
Cypress.Commands.add('tomSelectGetSelected', (selector) => {
    return cy.get(selector).siblings('.ts-wrapper').find('.ts-control .item');
});

// Legacy aliases — keep old command names working for gradual test migration
Cypress.Commands.add('select2ByText', (selector, text) => {
    cy.tomSelectByText(selector, text);
});
Cypress.Commands.add('select2ByValue', (selector, value) => {
    cy.tomSelectByValue(selector, value);
});
Cypress.Commands.add('select2Search', (selector, searchText, resultText = null) => {
    cy.tomSelectSearch(selector, searchText, resultText);
});
Cypress.Commands.add('select2Clear', (selector) => {
    cy.tomSelectClear(selector);
});
Cypress.Commands.add('select2GetSelected', (selector) => {
    cy.tomSelectGetSelected(selector);
});
Cypress.Commands.add('select2HasTheme', (selector) => {
    cy.tomSelectIsInitialized(selector);
});

/**
 * Type text into a Quill editor (contenteditable div)
 * Quill editors can't use cy.type() because they're not form inputs
 * This command uses Quill's setContents API instead
 * @param {string} editorId - The HTML ID of the Quill editor container (without #)
 * @param {string} text - The text to insert
 * @example cy.typeInQuill('NoteText', 'This is a test note')
 */
Cypress.Commands.add('typeInQuill', (editorId, text) => {
    cy.window().then((win) => {
        const quillEditor = win.quillEditors[editorId];
        if (!quillEditor) {
            throw new Error(`Quill editor not found: ${editorId}. Available editors: ${Object.keys(win.quillEditors).join(', ')}`);
        }
        quillEditor.setContents([{ insert: text }]);
    });
});

/**
 * Get text content from a Quill editor
 * @param {string} editorId - The HTML ID of the Quill editor container (without #)
 * @example cy.getQuillText('NoteText').should('contain', 'test note')
 */
Cypress.Commands.add('getQuillText', (editorId) => {
    return cy.window().then((win) => {
        const quillEditor = win.quillEditors[editorId];
        if (!quillEditor) {
            throw new Error(`Quill editor not found: ${editorId}`);
        }
        return quillEditor.getText();
    });
});

/**
 * Clear content from a Quill editor
 * @param {string} editorId - The HTML ID of the Quill editor container (without #)
 * @example cy.clearQuill('NoteText')
 */
Cypress.Commands.add('clearQuill', (editorId) => {
    cy.window().then((win) => {
        const quillEditor = win.quillEditors[editorId];
        if (!quillEditor) {
            throw new Error(`Quill editor not found: ${editorId}`);
        }
        quillEditor.setContents([]);
    });
});

/**
 * Set a Bootstrap Datepicker value by typing and blurring to trigger change event
 * This mimics user interaction to trigger all proper datepicker events
 * @param {string} selector - The CSS selector for the datepicker input
 * @param {string} dateString - The date string in MM/DD/YYYY format
 * @example cy.setDatePickerValue("#member-birthday-2", "08/07/1980");
 */
Cypress.Commands.add('setDatePickerValue', (selector, dateString) => {
    // Type the date and blur to trigger datepicker's change event
    cy.get(selector).clear().type(dateString);
    
    // Click elsewhere to blur the field and trigger change event
    cy.get('body').click();
    
    // Wait for event handlers to process
    cy.wait(100);
});

/**
 * Wait for ChurchCRM locales to be fully loaded
 * This ensures i18next and all locale files are ready before proceeding
 * @param {number} timeout - Maximum time to wait in milliseconds (default: 10000)
 * @example cy.waitForLocales()
 */
Cypress.Commands.add('waitForLocales', (timeout = 10000) => {
    cy.window({ timeout }).should((win) => {
        expect(win.CRM).to.exist;
        expect(win.CRM.localesLoaded).to.be.true;
    });
});

/**
 * Wait for a Notyf notification with specific text
 * Ensures locales are loaded first (for i18next translations) and verifies notification content
 * @param {string} expectedText - The text to find in the notification
 * @param {object} options - Optional config { timeout: 5000 }
 * @example cy.waitForNotification('Menu added successfully')
 */
Cypress.Commands.add('waitForNotification', (expectedText, options = {}) => {
    const { timeout = 5000 } = options;
    
    // Wait for locales first so translations are available
    cy.waitForLocales(timeout);
    
    // Wait for notification toast to appear and contain the expected text
    cy.get('.notyf__toast', { timeout })
        .should('be.visible')
        .should('contain', expectedText);
});

