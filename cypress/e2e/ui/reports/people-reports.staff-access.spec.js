/// <reference types="cypress" />

// Seed users: noperm.user (901) has every permission flag off; tony.wade (3, the
// standard user) has Delete Records and Menu Options; limited.user (4) is EditSelf only.
// Seed July birthdays give the Birthdays report rows.
const LABELS = "/Reports/PDFLabel.php?report=birthdays&month=7&labeltype=5160&labelfont=Helvetica&labelfontsize=10&startrow=1&startcol=1&filetype=CSV";

const noPermSession = () => cy.setupLoginSession("people-reports-noperm", "noperm.user", "changeme");

describe("People Reports for staff users (#10570)", () => {
    describe("as a user with no permission flags", () => {
        beforeEach(noPermSession);

        it("lists People Reports in the Reports menu", () => {
            cy.visit("/v2/dashboard");
            cy.get('a[href$="/people/reports"]').should("contain", "People Reports");
        });

        it("opens the reports and runs one", () => {
            cy.visit("/people/reports");
            cy.url().should("include", "/people/reports");
            cy.get("#report-birthdays").should("exist");
            cy.get("#report-missing-people").should("exist");

            cy.visit("/people/reports/birthdays?month=7");
            cy.get("#reportResults tbody tr[data-person-id]").should("have.length.greaterThan", 0);
            cy.get("#addAllToCart").should("exist");
            cy.get("#downloadCsv")
                .invoke("attr", "href")
                .then((href) => cy.request(href))
                .its("headers.content-type")
                .should("include", "text/csv");
        });

        it("offers no edit, delete or labels", () => {
            cy.visit("/people/reports/birthdays?month=7");
            cy.get("#reportResults tbody tr[data-person-id]").should("have.length.greaterThan", 0);
            cy.get("#reportResults .delete-person").should("not.exist");
            cy.get('#reportResults a[href*="PersonEditor.php"]').should("not.exist");
            cy.get("#printLabels").should("not.exist");
            cy.get("#labelsModal").should("not.exist");
        });

        it("cannot print report labels without Menu Options", () => {
            cy.request({ url: LABELS, followRedirect: false }).then((response) => {
                expect(response.status).to.eq(302);
                expect(response.headers.location).to.include("access-denied");
            });
        });
    });

    describe("with the Events module off", () => {
        let original;

        before(() => {
            cy.getSystemConfig("bEnabledEvents").then((value) => {
                original = value;
            });
            cy.makePrivateAdminAPICall("POST", "admin/api/system/config/bEnabledEvents", { value: "0" }, 200);
        });

        after(() => cy.restoreSystemConfig("bEnabledEvents", original));

        beforeEach(noPermSession);

        // cy.request, not cy.visit: with Events off, every page throws from the Calendar menu's counters.
        it("hides Missing People, which reads event attendance", () => {
            cy.request("/people/reports")
                .its("body")
                .should((body) => {
                    expect(body).to.include('id="report-birthdays"');
                    expect(body).not.to.include('id="report-missing-people"');
                });
            cy.request({ url: "/people/reports/missing-people?events[]=3", failOnStatusCode: false })
                .its("status")
                .should("eq", 404);
        });
    });

    describe("as a staff user with Delete Records and Menu Options", () => {
        beforeEach(() => cy.setupStandardSession());

        it("offers delete and labels", () => {
            cy.visit("/people/reports/birthdays?month=7");
            cy.get("#reportResults .delete-person").should("have.length.greaterThan", 0);
            cy.get("#printLabels").should("be.enabled");
            cy.request(LABELS).its("status").should("eq", 200);
        });
    });

    describe("as a self-service (EditSelf only) user", () => {
        beforeEach(() => cy.setupLoginSession("people-reports-editself", "limited.user", "changeme"));

        it("is kept in the portal", () => {
            cy.visit("/people/reports");
            cy.url().should("include", "/portal");
            cy.get("#peopleReports").should("not.exist");
            cy.get('a[href$="/people/reports"]').should("not.exist");
        });
    });
});
