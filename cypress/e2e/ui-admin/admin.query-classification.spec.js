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
            cy.get('select[name="percls[]"]').within(() => {
                    // Live list from list_lst, in sequence order, plus Unassigned.
                    cy.get("option:not([disabled])").then(($opts) => {
                        const labels = [...$opts].map((o) => o.textContent.trim());
                        expect(labels).to.deep.equal([
                            "Member",
                            "Regular Attender",
                            "Guest",
                            "Non-Attender",
                            "Non-Attender (staff)",
                            "Unassigned",
                        ]);
                    });
                    cy.get('option[value="5"]').should("have.text", "Non-Attender");
                    cy.get('option[value="0"]').should("have.text", "Unassigned");
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
        cy.get("table tbody tr").should("have.length", 10);
        cy.get("table tbody").should("contain", "Constance Hart");
        cy.get("table tbody").should("contain", "Austin Robertson");
        cy.get("code").should("contain", "'0'");
    });

    it("Birthdays: Unassigned selects people with no classification", () => {
        cy.visit("/QueryView.php?QueryID=18");
        cy.get('input[name="birthmonth"]').clear().type("7");
        cy.get('select[name="percls[]"]').select(["Unassigned"]);
        cy.get('input[name="Submit"]').click();

        cy.get("table tbody tr").should("have.length", 6);
        cy.get("table tbody").should("contain", "Austin Robertson");
        cy.get("table tbody").should("not.contain", "Constance Hart");
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
