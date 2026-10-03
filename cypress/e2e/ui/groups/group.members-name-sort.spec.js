/// <reference types="cypress" />

describe("Group members table sorts by last name, then first name (#9836)", () => {
    let savedStyle;

    before(() => {
        cy.setupAdminSession();
        cy.getSystemConfig("iPersonNameStyle").then((value) => {
            savedStyle = value;
        });
    });

    after(() => {
        cy.setupAdminSession();
        cy.restoreSystemConfig("iPersonNameStyle", savedStyle);
    });

    it("orders every row by last then first name, whatever the display name style", () => {
        // "First Last" display makes a rendered-name sort differ from a last-name sort
        cy.makePrivateAdminAPICall("POST", "admin/api/system/config/iPersonNameStyle", { value: "0" }, 200);

        cy.request("/api/groups/9/members").then((response) => {
            const members = response.body.Person2group2roleP2g2rs;
            expect(members.length).to.be.greaterThan(1);
            const key = (m) => `${m.Person.LastName || ""} ${m.Person.FirstName || ""}`.toLowerCase();
            const expectedIds = [...members].sort((a, b) => key(a).localeCompare(key(b))).map((m) => String(m.PersonId));

            cy.visit("/groups/view/9");
            cy.get("#membersTable tbody tr", { timeout: 10000 }).should("have.length.at.least", 2);
            cy.get("#membersTable thead th[aria-sort='ascending']").should("exist");
            cy.get("#membersTable tbody tr").then(($rows) => {
                const ids = [...$rows].map((row) => row.querySelector("a[href*='/people/view/']").href.split("/").pop());
                expect(ids).to.deep.equal(expectedIds.slice(0, ids.length));
            });
        });
    });
});
