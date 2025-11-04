// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************

// -- This is a login command --
Cypress.Commands.add("loginAdmin", (location, checkMatchingLocation = true) => {
    cy.login("admin", "changeme", location, checkMatchingLocation);
});

Cypress.Commands.add(
    "loginStandard",
    (location, checkMatchingLocation = true) => {
        cy.login(
            "tony.wade@example.com",
            "basicjoe",
            location,
            checkMatchingLocation,
        );
    },
);

Cypress.Commands.add(
    "login",
    (username, password, location, checkMatchingLocation = true) => {
        cy.visit("/?location=/" + location);
        cy.wait(150);
        
        // Use data-cy attributes when available, fallback to ID
        cy.get("[data-cy=username], #UserBox").type(username);
        cy.get("[data-cy=password], #PasswordBox").type(password);
        cy.get("form").submit();

        if (location && checkMatchingLocation) {
            cy.location("pathname").should("include", location.split("?")[0]);
        }
        
        // Wait for the page to fully load before returning from login
        cy.document().should("have.property", "readyState", "complete");
        cy.window().then((win) => {
            return new Cypress.Promise((resolve) => {
                if (win.document.readyState === "complete") {
                    resolve();
                } else {
                    win.addEventListener("load", () => resolve());
                }
            });
        });
    },
);

Cypress.Commands.add("buildRandom", (prefixString) => {
    const rand = Math.random().toString(36).substring(7);
    return prefixString.concat(" - ", rand);
});

// Modern command to wait for page to be ready
Cypress.Commands.add("waitForPageLoad", () => {
    cy.window().should("have.property", "document");
    cy.document().should("have.property", "readyState", "complete");
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
    cy.url().should("contain", "PersonView.php");
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
    // Create CSV content with simple format matching the existing test pattern
    let csvContent = '';
    
    Object.values(peopleData).forEach(person => {
        // Format: LastName,FirstName,Address,City,State,Zip,Email,BirthDate,Phone
        const birthDate = person.year ? 
            `${person.year}-${String(person.month).padStart(2, '0')}-${String(person.day).padStart(2, '0')}` :
            (person.month > 0 && person.day > 0) ? 
                `0000-${String(person.month).padStart(2, '0')}-${String(person.day).padStart(2, '0')}` :
                '';
        
        const row = [
            'TestUser',
            person.name,
            '"123 Test St"',
            'TestCity',
            'TX',
            '77777',
            `${person.name}@test.com`,
            birthDate,
            '555-123-4567'
        ].join(',');
        csvContent += row + '\n';
    });
    
    // Write CSV file and import it
    cy.writeFile('cypress/downloads/test_birthday_people.csv', csvContent);
    
    cy.visit('/CSVImport.php');
    cy.get('#CSVFileChooser').selectFile('cypress/downloads/test_birthday_people.csv');
    cy.get('#UploadCSVBtn').click();
    
    // Map the fields to match our CSV structure
    cy.get('#SelField0').select('Last Name', { force: true });
    cy.get('#SelField1').select('First Name', { force: true });
    cy.get('#SelField2').select('Address 1', { force: true });
    cy.get('#SelField3').select('City', { force: true });
    cy.get('#SelField4').select('State', { force: true });
    cy.get('#SelField5').select('Zip', { force: true });
    cy.get('#SelField6').select('Email', { force: true });
    cy.get('#SelField7').select('Birth Date', { force: true });
    cy.get('#SelField8').select('Home Phone', { force: true });
    
    // Execute the import
    cy.get('#DoImportBtn').click();
    cy.contains('Data import successful.', { timeout: 10000 });
});

// ============================================================
// Select2 Testing Commands
// ============================================================

/**
 * Select an option in a Select2 dropdown by visible text
 * @param {string} selector - CSS selector for the original select element
 * @param {string} text - The text of the option to select
 * @example cy.select2ByText('#Country', 'United States')
 */
Cypress.Commands.add('select2ByText', (selector, text) => {
    // Find the Select2 container for this select element
    cy.get(selector).then($select => {
        const selectId = $select.attr('id');
        const containerId = selectId ? `select2-${selectId}-container` : null;
        
        // Click the Select2 container to open the dropdown
        if (containerId) {
            cy.get(`#${containerId}`).parent('.select2-selection').click();
        } else {
            cy.get(selector).siblings('.select2-container').find('.select2-selection').click();
        }
    });
    
    // Wait for dropdown to appear and search for the text
    cy.get('.select2-container--open .select2-search__field', { timeout: 5000 })
        .should('be.visible')
        .type(text, { delay: 50 });
    
    // Wait for results and click the matching option
    cy.get('.select2-results__option', { timeout: 5000 })
        .should('be.visible')
        .contains(text)
        .click({ force: true });
});

/**
 * Select an option in a Select2 dropdown by value
 * @param {string} selector - CSS selector for the original select element
 * @param {string} value - The value of the option to select
 * @example cy.select2ByValue('#Country', 'us')
 */
Cypress.Commands.add('select2ByValue', (selector, value) => {
    // Use jQuery to trigger Select2 change programmatically
    cy.get(selector).then($select => {
        cy.wrap($select).invoke('val', value).trigger('change');
    });
});

/**
 * Type and search in a Select2 with AJAX
 * @param {string} selector - CSS selector for the original select element
 * @param {string} searchText - Text to search for
 * @param {string} resultText - Text of the result to click (optional, clicks first if not provided)
 * @example cy.select2Search('.multiSearch', 'John', 'John Doe')
 */
Cypress.Commands.add('select2Search', (selector, searchText, resultText = null) => {
    // Click the Select2 container to open the dropdown
    cy.get(selector).then($select => {
        const selectId = $select.attr('id');
        const containerId = selectId ? `select2-${selectId}-container` : null;
        
        if (containerId) {
            cy.get(`#${containerId}`).parent('.select2-selection').click();
        } else {
            cy.get(selector).siblings('.select2-container').find('.select2-selection').click();
        }
    });
    
    // Type in the search field
    cy.get('.select2-container--open .select2-search__field', { timeout: 5000 })
        .should('be.visible')
        .type(searchText, { delay: 50 });
    
    // Wait for AJAX results (look for non-loading options)
    cy.get('.select2-results__option', { timeout: 10000 })
        .not('.select2-results__option--loading')
        .should('be.visible');
    
    // Click the specific result or the first one
    if (resultText) {
        cy.get('.select2-results__option')
            .contains(resultText)
            .click({ force: true });
    } else {
        cy.get('.select2-results__option')
            .not('.select2-results__option--loading')
            .first()
            .click({ force: true });
    }
});

/**
 * Verify Select2 has the Bootstrap 4 theme applied
 * @param {string} selector - CSS selector for the original select element
 * @param {string} theme - Theme name (default: 'bootstrap4')
 * @example cy.select2HasTheme('#Country', 'bootstrap4')
 */
Cypress.Commands.add('select2HasTheme', (selector, theme = 'bootstrap4') => {
    cy.get(selector).then($select => {
        // Check if Select2 is initialized
        cy.wrap($select).should('have.class', 'select2-hidden-accessible');
        
        // Check the theme class on the container
        cy.get(selector)
            .siblings('.select2-container')
            .should('have.class', `select2-container--${theme}`);
    });
});

/**
 * Clear a Select2 selection (if allowClear is enabled)
 * @param {string} selector - CSS selector for the original select element
 * @example cy.select2Clear('#Country')
 */
Cypress.Commands.add('select2Clear', (selector) => {
    cy.get(selector)
        .siblings('.select2-container')
        .find('.select2-selection__clear')
        .click({ force: true });
});

/**
 * Get the currently selected text from a Select2
 * @param {string} selector - CSS selector for the original select element
 * @example cy.select2GetSelected('#Country').should('contain', 'United States')
 */
Cypress.Commands.add('select2GetSelected', (selector) => {
    return cy.get(selector)
        .siblings('.select2-container')
        .find('.select2-selection__rendered');
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
