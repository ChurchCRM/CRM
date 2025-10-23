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
    // Click the Select2 container to open the dropdown
    cy.get(selector).siblings('.select2-container').click();
    
    // Wait for dropdown to appear and search for the text
    cy.get('.select2-search__field').type(text);
    
    // Click the matching result
    cy.get('.select2-results__option')
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
    // Click to open the dropdown
    cy.get(selector).siblings('.select2-container').click();
    
    // Type in the search field
    cy.get('.select2-search__field').type(searchText);
    
    // Wait for AJAX results
    cy.get('.select2-results__option').should('be.visible');
    
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
 * @example cy.select2HasTheme('#Country', 'bootstrap4')
 */
Cypress.Commands.add('select2HasTheme', (selector, theme = 'bootstrap4') => {
    cy.get(selector)
        .siblings('.select2-container')
        .should('have.class', `select2-container--${theme}`);
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
