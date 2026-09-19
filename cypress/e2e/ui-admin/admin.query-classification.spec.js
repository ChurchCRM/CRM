/// <reference types="cypress" />

// #9914: every people query on Data & Reports offers a multi-select
// Classification filter whose options come from list_lst (lst_ID = 1), and
// leaving it blank means "all classifications".
//
// Seed facts (cypress/data/seed.sql):
//   July birthdays: Constance Hart (cls 1 Member), Ruben Ray (cls 2 Regular
//   Attender), Lorraine Diaz (cls 3 Guest), Darren Campbell (cls 5
//   Non-Attender), plus six people with per_cls_ID = 0 (Unassigned), e.g.
//   Austin Robertson.
//   July wedding anniversaries: Gordon (Sherri Gordon, cls 2) on 2011-07-13 and
//   Beck (Franklin + Julie Beck, cls 0) on 2010-07-22.
//   list_lst classification 1 = Member, 2 = Regular Attender, 3 = Guest,
//   5 = Non-Attender, 4 = Non-Attender (staff).
//
// The admin-ui CI job runs every spec against one database, so earlier specs
// leave extra classifications (admin.people.spec.js) and unclassified
// "ImportTest" people with July birthdays (admin.csvimport.spec.js) behind.
// Assertions below therefore check the seeded rows and relative order rather
// than exact lists and counts.

const SEEDED_OPTIONS = [
    ["1", "Member"],
    ["2", "Regular Attender"],
    ["3", "Guest"],
    ["5", "Non-Attender"],
    ["4", "Non-Attender (staff)"],
];

function readOptions($opts) {
    return [...$opts].map((o) => [o.value, o.textContent.trim()]);
}

// Asserts that `expected` pairs appear in `actual` in the same relative order.
function expectInOrder(actual, expected) {
    let cursor = 0;
    expected.forEach(([value, label]) => {
        const idx = actual.findIndex(([v, l], i) => i >= cursor && v === value && l === label);
        expect(idx, `option ${value} "${label}" present after position ${cursor}`).to.be.at.least(cursor);
        cursor = idx + 1;
    });
}

const CLASSIFICATION_QUERIES = {
    "Person by Property": 9,
    Birthdays: 18,
    "Membership anniversaries": 22,
    Volunteers: 25,
    "Recent friends": 26,
    "Wedding Anniversaries": 300,
    "Birthdays & Anniversaries": 301,
};

describe("Data & Reports — Classification filter (#9914)", () => {
    beforeEach(() => cy.setupAdminSession());

    Object.entries(CLASSIFICATION_QUERIES).forEach(([name, queryId]) => {
        it(`"${name}" (QueryID ${queryId}) offers a live multi-select Classification filter`, () => {
            cy.visit(`/QueryView.php?QueryID=${queryId}`);
            cy.get("body").should("not.contain", "Fatal error");
            cy.get('select[name="percls[]"]').should("have.attr", "multiple");
            cy.get('select[name="percls[]"] option:not([disabled])').then(($opts) => {
                const options = readOptions($opts);
                // Seeded rows from list_lst in sequence order (ids 5 and 4 are
                // deliberately out of numeric order in the seed) ...
                expectInOrder(options, SEEDED_OPTIONS);
                // ... and Unassigned (per_cls_ID = 0) last.
                expect(options[options.length - 1]).to.deep.equal(["0", "Unassigned"]);
            });
            cy.contains("Leave empty for all classifications");
        });
    });

    it("Birthdays: one classification returns only that classification", () => {
        cy.visit("/QueryView.php?QueryID=18");
        cy.get('input[name="birthmonth"]').clear().type("7");
        cy.get('select[name="percls[]"]').select(["Member"]);
        cy.get('input[name="Submit"]').click();

        cy.get("table tbody tr").should("have.length", 1);
        cy.get("table tbody").should("contain", "Constance Hart");
        cy.get("table tbody").should("not.contain", "Ruben Ray");
        cy.get("table tbody").should("not.contain", "Austin Robertson");
        // The substituted SQL is echoed below the results.
        cy.get("code").should("contain", "per_cls_ID IN ('1')");
    });

    it("Birthdays: several classifications are combined in one run", () => {
        cy.visit("/QueryView.php?QueryID=18");
        cy.get('input[name="birthmonth"]').clear().type("7");
        cy.get('select[name="percls[]"]').select(["Member", "Regular Attender", "Non-Attender"]);
        cy.get('input[name="Submit"]').click();

        cy.get("table tbody tr").should("have.length", 3);
        cy.get("table tbody").should("contain", "Constance Hart");
        cy.get("table tbody").should("contain", "Ruben Ray");
        cy.get("table tbody").should("contain", "Darren Campbell");
        cy.get("table tbody").should("not.contain", "Lorraine Diaz");
        cy.get("table tbody").should("not.contain", "Austin Robertson");
    });

    it("Birthdays: leaving Classification blank returns everyone, including Unassigned", () => {
        cy.visit("/QueryView.php?QueryID=18");
        cy.get('input[name="birthmonth"]').clear().type("7");
        cy.get('input[name="Submit"]').click();

        cy.get("body").should("not.contain", "This value is required");
        // Ten seeded July birthdays; other specs may add unclassified people.
        cy.get("table tbody tr").should("have.length.at.least", 10);
        cy.get("table tbody").should("contain", "Constance Hart");
        cy.get("table tbody").should("contain", "Ruben Ray");
        cy.get("table tbody").should("contain", "Austin Robertson");
        // Every option value, including Unassigned, was substituted.
        cy.get("code").should("contain", "per_cls_ID IN (");
        cy.get("code").should("contain", "'0'");
    });

    it("Birthdays: Unassigned selects people with no classification", () => {
        cy.visit("/QueryView.php?QueryID=18");
        cy.get('input[name="birthmonth"]').clear().type("7");
        cy.get('select[name="percls[]"]').select(["Unassigned"]);
        cy.get('input[name="Submit"]').click();

        // Six seeded unclassified July birthdays; other specs may add more.
        cy.get("table tbody tr").should("have.length.at.least", 6);
        cy.get("table tbody").should("contain", "Austin Robertson");
        cy.get("table tbody").should("not.contain", "Constance Hart");
        cy.get("table tbody").should("not.contain", "Ruben Ray");
        cy.get("code").should("contain", "per_cls_ID IN ('0')");
    });

    it("a classification added in Classification Manager appears in the filter", () => {
        const name = "CypressQueryCls_" + Date.now();

        cy.visit("/admin/system/options?mode=classes");
        cy.get("#newOptionName").type(name);
        cy.get("#addOptionBtn").click();
        cy.get(`#optionsTable tbody input.option-name-input[value="${name}"]`, { timeout: 10000 }).should("exist");

        cy.visit("/QueryView.php?QueryID=18");
        cy.get('select[name="percls[]"] option:not([disabled])').then(($opts) => {
            const options = readOptions($opts);
            const idx = options.findIndex(([, label]) => label === name);
            expect(idx, "new classification listed").to.be.at.least(0);
            expect(options[options.length - 1][1], "Unassigned stays last").to.equal("Unassigned");
        });

        // Clean up so later specs see the seeded list.
        cy.visit("/admin/system/options?mode=classes");
        cy.get(`#optionsTable tbody input.option-name-input[value="${name}"]`)
            .closest("tr")
            .within(() => {
                cy.get('[data-bs-toggle="dropdown"]').click();
                cy.get(".delete-btn").click();
            });
        cy.get(".bootbox .btn-danger").click();
        cy.get(`#optionsTable tbody input.option-name-input[value="${name}"]`, { timeout: 10000 }).should("not.exist");
    });

    it("Wedding Anniversaries honours the Classification filter", () => {
        cy.visit("/QueryView.php?QueryID=300");
        cy.get('input[name="weddingmonth"]').clear().type("7");
        cy.get('select[name="percls[]"]').select(["Regular Attender"]);
        cy.get('input[name="Submit"]').click();

        cy.get("table tbody tr").should("have.length", 1);
        cy.get("table tbody").should("contain", "Sherri Gordon");
        cy.get("table tbody").should("not.contain", "Franklin Beck");
    });

    it("Wedding Anniversaries with Classification blank lists every couple", () => {
        cy.visit("/QueryView.php?QueryID=300");
        cy.get('input[name="weddingmonth"]').clear().type("7");
        cy.get('input[name="Submit"]').click();

        cy.get("table tbody tr").should("have.length", 3);
        cy.get("table tbody").should("contain", "Sherri Gordon");
        cy.get("table tbody").should("contain", "Franklin Beck");
        cy.get("table tbody").should("contain", "Julie Beck");
    });

    it("Birthdays & Anniversaries applies the filter to both halves of the union", () => {
        cy.visit("/QueryView.php?QueryID=301");
        cy.get('input[name="month"]').clear().type("7");
        cy.get('select[name="percls[]"]').select(["Member", "Regular Attender"]);
        cy.get('input[name="Submit"]').click();

        // Birthdays: Constance Hart (1), Ruben Ray (2). Anniversary: Sherri Gordon (2).
        cy.get("table tbody tr").should("have.length", 3);
        cy.get("table tbody").should("contain", "Constance Hart");
        cy.get("table tbody").should("contain", "Ruben Ray");
        cy.get("table tbody").should("contain", "Sherri Gordon");
        cy.get("table tbody").should("not.contain", "Franklin Beck");
        cy.get("table tbody").should("not.contain", "Austin Robertson");
    });

    it("Membership anniversaries no longer hardcodes Member", () => {
        cy.visit("/QueryView.php?QueryID=22");
        cy.get("body").should("contain", "People who joined in a particular month");
        cy.get('select[name="percls[]"]').should("exist");
    });
});
