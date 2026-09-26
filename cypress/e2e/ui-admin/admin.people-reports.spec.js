/// <reference types="cypress" />

// Seed facts (cypress/data/seed.sql): July birthdays are Constance Hart (cls 1),
// Ruben Ray (cls 2), Lorraine Diaz (cls 3), Darren Campbell (cls 5) and six
// unclassified people such as Austin Robertson. July wedding anniversaries are
// Sherri Gordon (cls 2) and Franklin + Julie Beck (cls 0). Property 1
// "Disabled" is on Franklin Beck (cls 0) and eleven "Boby Hall" rows (cls 1).
// Other admin specs add unclassified people with July birthdays, so counts on
// unfiltered runs are lower bounds.

const REPORTS = [
    "person-by-property",
    "birthdays",
    "membership-anniversaries",
    "volunteers",
    "recent-friends",
    "volunteers-two-opportunities",
    "missing-people",
    "wedding-anniversaries",
    "birthdays-anniversaries",
];

const MONTH_NAMES = [
    "January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December",
];

// The server computes next month in the configured timezone. Accept next month
// relative to either the browser or the UTC clock so a run around midnight on
// the last day of a month does not flake.
function acceptableNextMonths() {
    const now = new Date();
    const local = (now.getMonth() + 1) % 12 + 1;
    const utc = (now.getUTCMonth() + 1) % 12 + 1;
    return [String(local), String(utc)];
}

function rows() {
    return cy.get("#reportResults tbody tr");
}

describe("People Reports (#9914, #9915)", () => {
    beforeEach(() => cy.setupAdminSession());

    it("lists the nine people reports", () => {
        cy.visit("/v2/reports/people");
        cy.get("body").should("not.contain", "Fatal error");
        cy.get("#peopleReports .list-group-item").should("have.length", REPORTS.length);
        REPORTS.forEach((slug) => {
            cy.get(`#report-${slug}`).should("have.attr", "href").and("include", `/v2/reports/people/${slug}`);
        });
    });

    it("is reachable from the Data/Reports menu next to Queries", () => {
        cy.visit("/v2/dashboard");
        cy.get('a[href$="/v2/reports/people"]').should("exist");
        cy.get('a[href$="QueryList.php"]').should("exist");
    });

    it("month dropdown lists the twelve months and defaults to next month", () => {
        cy.visit("/v2/reports/people/birthdays");
        cy.get("#month option").then(($opts) => {
            expect([...$opts].map((o) => o.textContent.trim())).to.deep.equal(MONTH_NAMES);
            expect([...$opts].map((o) => o.value)).to.deep.equal(MONTH_NAMES.map((_, i) => String(i + 1)));
        });
        cy.get("#month").invoke("val").should("be.oneOf", acceptableNextMonths());
    });

    it("classification options come from list_lst with Unassigned last", () => {
        cy.visit("/v2/reports/people/birthdays");
        cy.get("#classification").should("have.attr", "multiple");
        cy.get("#classification option").then(($opts) => {
            const options = [...$opts].map((o) => [o.value, o.textContent.trim()]);
            expect(options).to.deep.equal([
                ["1", "Member"],
                ["2", "Regular Attender"],
                ["3", "Guest"],
                ["5", "Non-Attender"],
                ["4", "Non-Attender (staff)"],
                ["0", "Unassigned"],
            ]);
        });
    });

    it("Birthdays for July returns the seeded birthdays with every classification", () => {
        cy.visit("/v2/reports/people/birthdays?month=7");
        rows().should("have.length.at.least", 10);
        cy.get("#reportResults tbody").should("contain", "Constance Hart");
        cy.get("#reportResults tbody").should("contain", "Ruben Ray");
        cy.get("#reportResults tbody").should("contain", "Austin Robertson");
        cy.get("#reportResults tbody tr").first().find("td").first().should("have.text", "8");
        cy.get('#reportResults a[href$="/people/view/6"]').should("contain", "Constance Hart");
    });

    it("selecting classifications in the form filters the results", () => {
        cy.visit("/v2/reports/people/birthdays?month=7");
        cy.get("#classification").should(($select) => {
            const ts = $select[0].tomselect;
            expect(ts, "TomSelect initialized on #classification").to.exist;
            ts.setValue(["1", "2", "5"]);
            expect(ts.getValue()).to.deep.equal(["1", "2", "5"]);
        });
        cy.get("#runReport").click();

        cy.url().should("include", "classification%5B%5D=1");
        cy.url().should("include", "classification%5B%5D=5");
        rows().should("have.length", 3);
        cy.get("#reportResults tbody").should("contain", "Constance Hart");
        cy.get("#reportResults tbody").should("contain", "Ruben Ray");
        cy.get("#reportResults tbody").should("contain", "Darren Campbell");
        cy.get("#reportResults tbody").should("not.contain", "Lorraine Diaz");
        cy.get("#reportResults tbody").should("not.contain", "Austin Robertson");
    });

    it("Unassigned selects people with no classification", () => {
        cy.visit("/v2/reports/people/birthdays?month=7&classification[]=0");
        rows().should("have.length.at.least", 6);
        cy.get("#reportResults tbody").should("contain", "Austin Robertson");
        cy.get("#reportResults tbody").should("not.contain", "Constance Hart");
    });

    it("Wedding Anniversaries honours the classification filter", () => {
        cy.visit("/v2/reports/people/wedding-anniversaries?month=7");
        rows().should("have.length", 3);
        cy.get("#reportResults tbody").should("contain", "Franklin Beck");
        cy.get("#reportResults tbody").should("contain", "Julie Beck");

        cy.visit("/v2/reports/people/wedding-anniversaries?month=7&classification[]=2");
        rows().should("have.length", 1);
        cy.get("#reportResults tbody").should("contain", "Sherri Gordon");
    });

    it("Birthdays & Anniversaries applies the filter to both halves", () => {
        cy.visit("/v2/reports/people/birthdays-anniversaries?month=7&classification[]=1&classification[]=2");
        rows().should("have.length", 3);
        cy.get("#reportResults tbody").should("contain", "Constance Hart");
        cy.get("#reportResults tbody").should("contain", "Ruben Ray");
        cy.get("#reportResults tbody").should("contain", "Sherri Gordon");
        cy.get("#reportResults tbody").should("contain", "Anniversary");
    });

    it("Person by Property lists the value column and filters by classification", () => {
        cy.visit("/v2/reports/people/person-by-property?property=1&classification[]=0");
        rows().should("have.length", 1);
        cy.get("#reportResults tbody").should("contain", "Franklin Beck");
        cy.get("#reportResults tbody").should("contain", "N/A");
    });

    it("Missing People excludes the attendee of the chosen event", () => {
        cy.visit("/v2/reports/people/missing-people?events[]=3");
        rows().should("have.length.at.least", 100);
        cy.get("#reportResults tbody").should("not.contain", "Mark Smith");
    });

    it("reports without a required choice show the form and no results", () => {
        cy.visit("/v2/reports/people/volunteers");
        cy.get("#opportunity").should("exist");
        cy.get("#reportResults").should("not.exist");
    });

    it("every report page renders without errors", () => {
        REPORTS.forEach((slug) => {
            cy.visit(`/v2/reports/people/${slug}`);
            cy.get("body").should("not.contain", "Fatal error");
            cy.get("#reportFilters").should("exist");
        });
    });

    it("adds one row and then all rows to the cart", () => {
        cy.request({ method: "DELETE", url: "/api/cart/", body: {} });
        cy.intercept("POST", "**/api/cart/").as("addToCart");
        cy.visit("/v2/reports/people/birthdays?month=7&classification[]=1&classification[]=2");
        rows().should("have.length", 2);

        cy.get("#reportResults tbody tr").first().find('[data-bs-toggle="dropdown"]').click();
        cy.get("#reportResults tbody tr").first().find(".dropdown-menu.show .AddToCart").click({ force: true });
        cy.wait("@addToCart").its("request.body.Persons").should("deep.equal", [6]);
        cy.request("/api/cart/").its("body.PeopleCart").should("deep.equal", [6]);

        cy.get("#addAllToCart").click();
        cy.wait("@addToCart").its("request.body.Persons").should("deep.equal", [6, 20]);
        cy.request("/api/cart/").its("body.PeopleCart").should("deep.equal", [6, 20]);

        cy.request({ method: "DELETE", url: "/api/cart/", body: {} });
    });

    it("downloads the current result as CSV", () => {
        cy.request("/v2/reports/people/birthdays/csv?month=7&classification[]=1").then((response) => {
            expect(response.status).to.equal(200);
            expect(response.headers["content-type"]).to.include("text/csv");
            expect(response.headers["content-disposition"]).to.include("birthdays");
            const lines = response.body.trim().split(/\r?\n/);
            expect(lines[0]).to.equal("Day,Name");
            expect(lines).to.have.length(2);
            expect(lines[1]).to.include("Constance Hart");
        });
    });
});

describe("People Reports access", () => {
    it("redirects non-admins to access denied", () => {
        cy.setupStandardSession();
        cy.visit("/v2/reports/people", { failOnStatusCode: false });
        cy.url().should("include", "/v2/access-denied");
    });
});
