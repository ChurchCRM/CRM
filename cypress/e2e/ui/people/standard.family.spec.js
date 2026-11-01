/// <reference types="cypress" />

// Families these suites create through FamilyEditor, removed again when each
// suite finishes so the seeded database does not grow with every run (#9769).
// The hook lives inside the LAST describe on purpose. A root-level after() in
// a spec file runs *after* the support file's own root after(), which is where
// the row-count drift guard checks its snapshot; and the cleanup calls go out
// with x-api-key, which flips the PHP session to APITokenAuthentication and
// would send any later cy.visit() to the login page. A top-level afterEach()
// flushes the list early when a test fails, for the runs that never reach that
// last describe.
const createdFamilyIds = [];

/** Record the family id from the /people/family/{id} URL the editor lands on. */
function trackFamilyFromUrl() {
    cy.location("pathname").then((pathname) => {
        const match = pathname.match(/\/people\/family\/(\d+)/);
        if (match) {
            createdFamilyIds.push(Number.parseInt(match[1], 10));
        }
    });
}

// Safety net for the end-of-file after() below. That hook only fires if the
// runner reaches the last describe, which it does not when a suite-level hook
// blows up or the run is started with --bail; the families created earlier
// would then survive and the drift guard would fail the *next* run instead.
// Flushing after a failed test costs nothing on the green path (the array is
// only non-empty once a family has actually been created) and the following
// beforeEach re-establishes the UI session through cy.session(), so the
// x-api-key calls cannot leak into a later cy.visit().
afterEach(function () {
    if (this.currentTest?.state !== "failed" || createdFamilyIds.length === 0) {
        return;
    }
    cy.cleanupFamilies(createdFamilyIds.splice(0));
});

describe("Standard Family", () => {
    beforeEach(() => cy.setupStandardSession());

    it("View invalid Family", () => {
        cy.visit("people/family/9999");
        cy.location("pathname").should("include", "family/not-found");
        cy.contains("Family not found");
    });

    it("Print button triggers window.print", () => {
        cy.visit("people/family/1");

        cy.window().then((win) => {
            cy.stub(win, "print").as("printStub");
        });
        cy.get("#printFamily").should("be.visible").click();
        cy.get("@printStub").should("have.been.calledOnce");
    });

    it("Entering a new Family", () => {
        cy.visit("FamilyEditor.php");

        cy.contains("Family Info");
        // Fill in Family Info section
        cy.get("#FamilyName").type("Troy" + Cypress._.random(0, 1e6));
        cy.get('input[name="Address1"').type("4222 Clinton Way");
        cy.get('input[name="City"]').clear().type("Los Angeles");
        cy.get('select[name="State"]').select("CA", { force: true });
        // Add clearing of Lat/Long to verify these can be null, instead of default 0
        cy.get('input[name="Latitude"]').clear();
        cy.get('input[name="Longitude"]').clear();

        // Fill in Contact Information section
        cy.get('input[name="Email"]').type("mike@example.com");

        // Fill in Wedding Date (now in Family Identity section)
        const weddingYear = "2024";
        const weddingMonth = "04";
        const weddingDay = "03";
        cy.get("#WeddingDate").type(
            `${weddingYear}-${weddingMonth}-${weddingDay}`,
        );

        // Fill in Family Members (default 4 rows, add 2 more via button)
        cy.get('input[name="FirstName1"]').type("Mike");
        cy.get('input[name="FirstName2"]').type("Carol");
        cy.get('input[name="FirstName3"]').type("Alice");
        cy.get('input[name="FirstName4"]').type("Greg");
        // Add more family members using the button
        cy.get('#addFamilyMemberRow').click();
        cy.get('input[name="FirstName5"]').type("Marcia");
        cy.get('#addFamilyMemberRow').click();
        cy.get('input[name="FirstName6"]').type("Peter");
        cy.get('select[name="Classification1"]').select("1", { force: true });
        cy.get('select[name="Classification2"]').select("1", { force: true });
        cy.get('select[name="Classification3"]').select("1", { force: true });
        cy.get('select[name="Classification4"]').select("2", { force: true });
        cy.get('select[name="Classification5"]').select("1", { force: true });
        cy.get('select[name="Classification6"]').select("2", { force: true });

        // Click FAB save button (on FamilyEditor page, not family view)
        cy.get('button[name="FamilySubmit"]').click();

        // Should redirect to family view page
        cy.location("pathname").should("include", "/people/family/");
        trackFamilyFromUrl();
        // Page subtitle shows Family Profile
        cy.contains("Family Profile");
        // Family members table should show all members
        cy.contains("Mike Troy");
        cy.contains("Carol Troy");
        cy.contains("Alice Troy");
        cy.contains("Greg Troy");
        cy.contains("Marcia Troy");
        cy.contains("Peter Troy");
        // Address and contact info should be visible
        cy.contains("4222 Clinton Way Los Angeles, CA");
        cy.contains("mike@example.com");
        cy.contains(`${weddingMonth}/${weddingDay}/${weddingYear}`);

        // Edit the family — use the toolbar Edit button (FABs removed)
        cy.get('a.btn-ghost-primary').contains('Edit').click();
        cy.get('input[name="Email"]').clear();
        cy.get("#WeddingDate").clear();

        // Click FAB save button (on FamilyEditor page)
        cy.get('button[name="FamilySubmit"]').click();

        cy.location("pathname").should("include", "/people/family/");
        cy.get('body').should('not.contain', 'mike@example.com');
        cy.get('body').should('not.contain', `${weddingMonth}/${weddingDay}/${weddingYear}`);
    });
});

describe("Family Wedding Date Edit Workflow", () => {
    beforeEach(() => cy.setupStandardSession());

    it("Create family without wedding date, then add wedding date via edit", () => {
        cy.visit("FamilyEditor.php");
        cy.contains("Family Info");

        const familyName = "Smith" + Cypress._.random(0, 1e6);
        cy.get("#FamilyName").type(familyName);
        cy.get('input[name="Address1"]').type("123 Main Street");
        cy.get('input[name="City"]').clear().type("Springfield");
        cy.get('select[name="State"]').select("IL", { force: true });
        cy.get('input[name="Email"]').type("test@example.com");
        cy.get('input[name="FirstName1"]').type("John");
        cy.get('select[name="Classification1"]').select("1", { force: true });

        cy.get('button[name="FamilySubmit"]').click();

        cy.location("pathname").should("include", "/people/family/");
        trackFamilyFromUrl();
        cy.contains("Family Profile");
        cy.get("i.fa-ring").should("not.exist");
        cy.contains(familyName).should("exist");

        cy.get('a.btn-ghost-primary').contains("Edit").click();
        cy.contains("Family Info");

        const weddingYear = "2020";
        const weddingMonth = "06";
        const weddingDay = "15";
        cy.get("#WeddingDate").type(`${weddingYear}-${weddingMonth}-${weddingDay}`);

        cy.get('button[name="FamilySubmit"]').click();

        cy.location("pathname").should("include", "/people/family/");
        cy.contains("Family Profile");
        cy.get("i.fa-ring").should("be.visible");
        cy.contains(`${weddingMonth}/${weddingDay}/${weddingYear}`).should("be.visible");
        cy.get("li").contains(`${weddingMonth}/${weddingDay}/${weddingYear}`).find("i.fa-ring").should("be.visible");
    });
});

describe("Family Editor — edit existing record (PR #9351 prepared-statement smoke)", () => {
    beforeEach(() => cy.setupStandardSession());

    it("loads existing family for editing without crash", () => {
        // FamilyEditor.php?FamilyID=1 exercises the FamilyQuery::create()->findOneById()
        // ORM path introduced in PR #9351 to replace raw SQL.
        cy.visit("FamilyEditor.php?FamilyID=1");
        cy.contains("Family Editor").should("exist");
        cy.get("#FamilyName").should("exist");
        cy.get("body").should("not.contain", "Fatal error");
        cy.get("body").should("not.contain", "Warning:");
    });
});

describe("Standard Family Activation", () => {
    // Last suite in the file — cleanup for every family the earlier suites
    // created happens here, so no cy.visit() follows the x-api-key calls.
    // The top-level afterEach() above covers the case where the run never
    // reaches this suite.
    after(() => {
        cy.cleanupFamilies(createdFamilyIds.splice(0));
    });

    beforeEach(() => {
        // Reset family 3 to active BEFORE registering intercepts so the setup
        // call is not captured by @updateToActive — only UI-triggered requests
        // should resolve those aliases.
        cy.makePrivateUserAPICall("POST", "/api/family/3/activate/true", "", 200);

        cy.intercept("POST", "**/api/family/3/activate/true").as("updateToActive");
        cy.intercept("POST", "**/api/family/3/activate/false").as("updateToInActive");

        cy.setupStandardSession({ forceLogin: true });
    });

    it("Family activation flow", () => {
        cy.visit("people/family");
        cy.contains("Family Listing");

        cy.visit("people/family?mode=inactive");
        cy.contains("Lewis").should("not.exist");

        cy.visit("people/family/3");
        cy.contains("This Family is Inactive").should("not.be.visible");
        cy.get("#family-actions-dropdown").click();
        cy.get("#activateDeactivate").click();
        cy.get(".bootbox-accept").should("be.visible").click();
        cy.wait("@updateToInActive");

        cy.visit("people/family?mode=inactive");
        cy.contains("Lewis");

        cy.visit("people/family/3");
        cy.contains("This Family is Inactive").should("be.visible");
        cy.get("#family-actions-dropdown").click();
        cy.get("#activateDeactivate").click();
        cy.get(".bootbox-accept").should("be.visible").click();
        cy.wait("@updateToActive");

        cy.visit("people/family?mode=inactive");
        cy.contains("Lewis").should("not.exist");
    });
});
