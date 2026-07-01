describe("User Editor - Permission Visibility and Persistence Tests", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Should display module-level permission checkboxes (AddEvent, EmailMailto, CreateDirectory)", () => {
        // These were previously hidden in the User Config table below the
        // main Permissions card — #8458. They must now appear as checkboxes
        // in the Permissions card for discoverability.
        cy.visit('UserEditor.php?PersonID=1');
        cy.contains("User Editor");

        cy.get('#ucfg_AddEvent').should('exist').and('be.visible');
        cy.get('#ucfg_EmailMailto').should('exist').and('be.visible');
        cy.get('#ucfg_CreateDirectory').should('exist').and('be.visible');
    });

    it("Should persist module-level permission toggle (AddEvent)", () => {
        cy.intercept('POST', '**/UserEditor.php*').as('saveUser');

        cy.visit('UserEditor.php?PersonID=1');

        // Toggle AddEvent and save
        cy.get('#ucfg_AddEvent').check();
        cy.get('#SaveButton').click();
        cy.wait('@saveUser');

        // Verify it persisted
        cy.visit('UserEditor.php?PersonID=1');
        cy.get('#ucfg_AddEvent').should('be.checked');

        // Clean up
        cy.get('#ucfg_AddEvent').uncheck();
        cy.get('#SaveButton').click();
        cy.wait('@saveUser');
    });
});

describe("Admin bypass - module feature-flag checks (#8667)", () => {
    // Capture original flag values so they can be restored after each test
    // even if the test assertion fails.
    let savedEventsEnabled;
    let savedFinanceEnabled;

    before(() => {
        cy.makePrivateAdminAPICall("GET", "/admin/api/system/config/bEnabledEvents", null, 200)
            .then((resp) => { savedEventsEnabled = resp.body.value; });
        cy.makePrivateAdminAPICall("GET", "/admin/api/system/config/bEnabledFinance", null, 200)
            .then((resp) => { savedFinanceEnabled = resp.body.value; });
    });

    afterEach(() => {
        // Restore flags regardless of test outcome so other tests are not affected.
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/system/config/bEnabledEvents",
            { value: savedEventsEnabled ?? "1" },
            200,
        );
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/system/config/bEnabledFinance",
            { value: savedFinanceEnabled ?? "1" },
            200,
        );
    });

    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Admin should access /event/dashboard even when bEnabledEvents is off", () => {
        // Explicitly disable bEnabledEvents, then assert the admin bypass works:
        // canViewEvents() must return true for admins regardless of the flag.
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/system/config/bEnabledEvents",
            { value: "0" },
            200,
        );
        cy.visit('event/dashboard');
        cy.url().should('not.include', 'access-denied');
        cy.contains('Events Dashboard').should('exist');
    });

    it("Admin should access /finance/ even when bEnabledFinance is off", () => {
        // Explicitly disable bEnabledFinance, then assert the admin bypass works.
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/system/config/bEnabledFinance",
            { value: "0" },
            200,
        );
        cy.visit('finance/');
        cy.url().should('not.include', 'access-denied');
        cy.contains('Finance Dashboard').should('exist');
    });
});

describe("User Editor - ORM Migration Tests", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Should edit user permissions and persist via ORM", () => {
        // Intercept the UserEditor form POST so we can wait on the real
        // save round-trip rather than on a hard-coded 500ms sleep.
        cy.intercept('POST', '**/UserEditor.php*').as('saveUser');

        // Edit existing admin user (PersonID 1 always exists)
        cy.visit('UserEditor.php?PersonID=1');
        cy.contains("User Editor");

        // Modify a permission
        cy.get('#Finance').check();
        cy.get('#SaveButton').click();
        cy.wait('@saveUser');

        // Reload page and verify ORM loaded the updated value
        cy.visit('UserEditor.php?PersonID=1');
        cy.get('#Finance').should('be.checked');

        // Uncheck to clean up
        cy.get('#Finance').uncheck();
        cy.get('#SaveButton').click();
        cy.wait('@saveUser');
    });

    it("Should handle ORM user update with multiple permission changes", () => {
        cy.intercept('POST', '**/UserEditor.php*').as('saveUser');

        cy.visit('UserEditor.php?PersonID=1');
        cy.contains("User Editor");

        // Update multiple fields at once (tests ORM object state management)
        cy.get('#AddRecords').check();
        cy.get('#EditRecords').check();
        cy.get('#Notes').check();
        cy.get('#SaveButton').click();
        cy.wait('@saveUser');

        // Reload and verify ORM persisted all changes
        cy.visit('UserEditor.php?PersonID=1');
        cy.get('#AddRecords').should('be.checked');
        cy.get('#EditRecords').should('be.checked');
        cy.get('#Notes').should('be.checked');

        // Clean up
        cy.get('#AddRecords').uncheck();
        cy.get('#EditRecords').uncheck();
        cy.get('#Notes').uncheck();
        cy.get('#SaveButton').click();
        cy.wait('@saveUser');
    });

    it("Should update username via ORM", () => {
        cy.intercept('POST', '**/UserEditor.php*').as('saveUser');

        cy.visit('UserEditor.php?PersonID=1');
        cy.contains("User Editor");

        // Get original username
        cy.get('#UserName').invoke('val').then((originalUsername) => {
            const newUsername = 'admin_orm_test';

            // Update username
            cy.get('#UserName').clear().type(newUsername);
            cy.get('#SaveButton').click();
            cy.wait('@saveUser');

            // Verify ORM persisted the change
            cy.visit('UserEditor.php?PersonID=1');
            cy.get('#UserName').should('have.value', newUsername);

            // Reset to original
            cy.get('#UserName').clear().type(originalUsername);
            cy.get('#SaveButton').click();
            cy.wait('@saveUser');
        });
    });

});
