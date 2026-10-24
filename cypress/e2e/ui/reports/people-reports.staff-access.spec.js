/// <reference types="cypress" />

// Seed users: noperm.user (901) has every permission flag off; tony.wade (3, the
// standard user) has Delete Records; Darren Campbell (4) signs in to the portal as a member.
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
                .invoke("prop", "href")
                .then((href) => cy.request(href))
                .its("headers.content-type")
                .should("include", "text/csv");
        });

        it("offers no edit or delete", () => {
            cy.visit("/people/reports/birthdays?month=7");
            cy.get("#reportResults tbody tr[data-person-id]").should("have.length.greaterThan", 0);
            cy.get("#reportResults .delete-person").should("not.exist");
            cy.get('#reportResults a[href*="PersonEditor.php"]').should("not.exist");
        });

        it("prints report labels like any report", () => {
            cy.visit("/people/reports/birthdays?month=7");
            cy.get("#printLabels").should("be.enabled");
            cy.request(LABELS).its("status").should("eq", 200);
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

    describe("as a staff user with Delete Records", () => {
        beforeEach(() => cy.setupStandardSession());

        it("offers delete", () => {
            cy.visit("/people/reports/birthdays?month=7");
            cy.get("#reportResults .delete-person").should("have.length.greaterThan", 0);
        });
    });

    describe("as a member signed in to the portal", () => {
        beforeEach(() => cy.signInAsMember(4));

        it("is sent to the staff sign-in", () => {
            cy.visit("/people/reports");
            cy.location("pathname").should("eq", "/admin/");
            cy.get("#peopleReports").should("not.exist");
            cy.get('a[href$="/people/reports"]').should("not.exist");
        });
    });
});
