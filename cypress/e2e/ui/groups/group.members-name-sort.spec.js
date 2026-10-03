/// <reference types="cypress" />

describe("Group members table sorts by last name, then first name (#9836)", () => {
    beforeEach(() => cy.setupStandardSession());

    it("orders the Name column independently of the display name style", () => {
        cy.request("/api/groups/9/members").then((response) => {
            const members = response.body.Person2group2roleP2g2rs;
            expect(members.length).to.be.greaterThan(1);
            const key = (m) => `${m.Person.LastName || ""} ${m.Person.FirstName || ""}`.toLowerCase();
            const expectedFirst = [...members].sort((a, b) => key(a).localeCompare(key(b)))[0];

            cy.visit("groups/view/9");
            cy.get("#membersTable tbody tr", { timeout: 10000 }).should("have.length.at.least", 2);
            cy.get("#membersTable thead th[aria-sort='ascending']").should("exist");
            cy.get("#membersTable tbody tr")
                .first()
                .find(`a[href$='/people/view/${expectedFirst.PersonId}']`)
                .should("exist");
        });
    });
});
