describe("User Editor - Permission Visibility and Persistence Tests", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("Should display module-level permission checkboxes (AddEvent, EmailMailto, CreateDirectory)", () => {
        // These were previously hidden in the User Config table below the
        // main Permissions card — #8458. They must now appear as checkboxes
        // in the Permissions card for discoverability.
        // Use PersonID=3 (tony.wade, usr_Admin=0 usr_EditSelf=0) — a seeded user
        // in "Custom" access mode so that #customPermissions is visible.
        // PersonID=1 (admin) loads in Administrator mode where #customPermissions
        // is intentionally hidden, causing a be.visible assertion failure.
        cy.visit('admin/system/users/3/edit');
        cy.contains("User Editor");

        cy.get('#ucfg_AddEvent').should('exist').and('be.visible');
        cy.get('#ucfg_EmailMailto').should('exist').and('be.visible');
        cy.get('#ucfg_CreateDirectory').should('exist').and('be.visible');
    });

    it("Should persist module-level permission toggle (AddEvent)", () => {
        // Use PersonID=3 (tony.wade, Custom mode) so #customPermissions is
        // visible and the ucfg_AddEvent checkbox is interactable without force.
        cy.intercept('POST', '**/admin/system/users/3/edit*').as('saveUser');

        cy.visit('admin/system/users/3/edit');

        // Toggle AddEvent and save
        cy.get('#ucfg_AddEvent').check();
        cy.get('#SaveButton').click();
        cy.wait('@saveUser');

        // Verify it persisted
        cy.visit('admin/system/users/3/edit');
        cy.get('#ucfg_AddEvent').should('be.checked');

        // Clean up — restore original unchecked state
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
        // Clear cookies first so the API calls below do NOT send any browser
        // session cookie. When cy.request() sends both an x-api-key header AND
        // a session cookie, PHP overwrites $_SESSION['AuthenticationProvider']
        // with APITokenAuthentication, which breaks subsequent cy.visit() calls.
        cy.clearCookies();
        cy.makePrivateAdminAPICall("GET", "/admin/api/system/config/bEnabledEvents", null, 200)
            .then((resp) => { savedEventsEnabled = resp.body.value; });
        cy.makePrivateAdminAPICall("GET", "/admin/api/system/config/bEnabledFinance", null, 200)
            .then((resp) => { savedFinanceEnabled = resp.body.value; });
        // Clearing saved sessions forces the next setupAdminSession() call to
        // do a full fresh login rather than restoring a (possibly corrupted)
        // cached session.
        cy.then(() => Cypress.session.clearAllSavedSessions());
    });

    afterEach(() => {
        // Clear cookies before restoring flags for the same session-corruption
        // reason as in before().
        cy.clearCookies();
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
        // Clear saved sessions so the ORM Migration describe block that follows
        // this describe always gets a fresh PHP login, not the potentially-
        // corrupted cached session.
        cy.then(() => Cypress.session.clearAllSavedSessions());
    });

    it("Admin should access /event/dashboard even when bEnabledEvents is off", () => {
        // Step 1: Disable the flag with NO active browser session so PHP cannot
        //         overwrite the browser session's AuthenticationProvider.
        cy.clearCookies();
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/system/config/bEnabledEvents",
            { value: "0" },
            200,
        );
        // Step 2: Establish a fresh admin browser session now that the flag is set.
        cy.setupAdminSession({ forceLogin: true });

        // Step 3: Verify that the admin bypass works — admins can always see events.
        cy.visit('event/dashboard');
        cy.url().should('not.include', 'access-denied');
        cy.contains('Events Dashboard').should('exist');
    });

    it("Admin should access /finance/ even when bEnabledFinance is off", () => {
        cy.clearCookies();
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/system/config/bEnabledFinance",
            { value: "0" },
            200,
        );
        cy.setupAdminSession({ forceLogin: true });

        cy.visit('finance/');
        cy.url().should('not.include', 'access-denied');
        cy.contains('Finance Dashboard').should('exist');
    });
});

describe("User Editor - ORM Migration Tests", () => {
    // Throwaway persons — not seeded users, safe to create/delete here.
    // Each test uses a DIFFERENT person ID so parallel CI workers (test-root
    // and test-subdir) never race on the same DB row.
    // Test 1: PersonID=6 (Constance Hart, constance.hart@example.com, family 2)
    // Test 2: PersonID=5 (Albert Campbell, albert.garcia@example.com, family 1)
    const throwawayPersonId = 6;
    const throwawayPersonId2 = 5;

    beforeEach(() => {
        cy.setupAdminSession();
    });

    function createCustomUser() {
        cy.makePrivateAdminAPICall("DELETE", `/admin/api/user/${throwawayPersonId}`, null, [200, 204, 404]);
        // The API call's response Set-Cookie will overwrite the browser's session cookie
        // and contaminate the Cypress 'admin-session' cache when cy.session() implicitly
        // saves the current browser state. Clear all saved sessions then force a fresh
        // admin login to rebuild the 'admin-session' cache with a valid admin cookie.
        cy.then(() => Cypress.session.clearAllSavedSessions());
        cy.setupAdminSession();
        cy.intercept("POST", `**/admin/system/users/new*`).as("saveUser");
        cy.visit(`admin/system/users/new?personId=${throwawayPersonId}`);
        cy.contains("User Editor");
        cy.get("#customPermissions").should("be.visible");
    }

    function deleteUser() {
        // withCredentials:false prevents sending the session cookie on the request
        // but the response Set-Cookie still updates the browser jar, contaminating
        // the 'admin-session' Cypress cache. Clear all sessions after cleanup so
        // the next test's beforeEach re-establishes a clean admin session.
        cy.makePrivateAdminAPICall("DELETE", `/admin/api/user/${throwawayPersonId}`, null, [200, 204, 404]);
        cy.then(() => Cypress.session.clearAllSavedSessions());
    }

    function createCustomUser2() {
        cy.makePrivateAdminAPICall("DELETE", `/admin/api/user/${throwawayPersonId2}`, null, [200, 204, 404]);
        cy.then(() => Cypress.session.clearAllSavedSessions());
        cy.setupAdminSession();
        cy.intercept("POST", `**/admin/system/users/new*`).as("saveUser");
        cy.visit(`admin/system/users/new?personId=${throwawayPersonId2}`);
        cy.contains("User Editor");
        cy.get("#customPermissions").should("be.visible");
    }

    function deleteUser2() {
        cy.makePrivateAdminAPICall("DELETE", `/admin/api/user/${throwawayPersonId2}`, null, [200, 204, 404]);
        cy.then(() => Cypress.session.clearAllSavedSessions());
    }

    it("Should persist a single Custom permission via ORM", () => {
        createCustomUser();
        cy.get("#Finance").check();
        cy.get("#SaveButton").click();
        cy.wait("@saveUser");

        // Verify the user was created and Finance flag persisted via edit form
        cy.visit(`admin/system/users/${throwawayPersonId}/edit`);
        cy.contains("User Editor");
        cy.get("#customPermissions").should("be.visible");
        cy.get("#Finance").should("be.checked");
        deleteUser();
    });

    it("Should persist multiple Custom permission changes via ORM", () => {
        createCustomUser2();
        cy.get("#EditRecords").should("be.visible").check();
        cy.get("#SaveButton").click();
        cy.wait("@saveUser");
        cy.wait(500);

        cy.visit(`admin/system/users/${throwawayPersonId2}/edit`);
        cy.contains("User Editor");
        cy.get("#customPermissions").should("be.visible");
        cy.get("#EditRecords").should("be.checked");
        deleteUser2();
    });

    it("Should update username via ORM", () => {
        // The username field is independent of access level, so exercising it on
        // the admin user (PersonID 1) is safe — its mode/permissions are untouched.
        //
        // Reset to canonical username first in case a prior run was interrupted
        // after mutating to 'admin_orm_test' but before restoring.
        cy.intercept("POST", "**/admin/system/users/1/edit*").as("resetIfNeeded");
        cy.visit("admin/system/users/1/edit");
        cy.contains("User Editor");
        cy.get("#UserName").then(($input) => {
            if ($input.val() !== "Admin") {
                cy.get("#UserName").clear().type("Admin");
                cy.get("#SaveButton").click();
                cy.wait("@resetIfNeeded");
            }
        });

        const newUsername = "admin_orm_test";
        cy.intercept("POST", "**/admin/system/users/1/edit*").as("saveUser");
        cy.get("#UserName").clear().type(newUsername);
        cy.get("#SaveButton").click();
        cy.wait("@saveUser");

        cy.visit("admin/system/users/1/edit");
        cy.get("#UserName").should("have.value", newUsername);

        // Restore to canonical seed username
        cy.intercept("POST", "**/admin/system/users/1/edit*").as("restoreUser");
        cy.get("#UserName").clear().type("Admin");
        cy.get("#SaveButton").click();
        cy.wait("@restoreUser");
    });
});

describe("User Editor - Person picker (no ?personId)", () => {
    before(() => {
        cy.setupAdminSession();
    });

    it("Shows the person-picker dropdown when no personId is given", () => {
        cy.visit("admin/system/users/new");
        cy.contains("User Editor");
        // The native <select> stays in DOM after TomSelect hides it
        cy.get("#personSelect").should("exist");
        // Username field present
        cy.get("#UserName").should("exist");
        // Access level radios present
        cy.get('input[name="accessMode"]').should("have.length", 3);
        // Save button present
        cy.get("#SaveButton").should("exist");
    });
});
