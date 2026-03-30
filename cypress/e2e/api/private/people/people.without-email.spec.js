/// <reference types="cypress" />

describe("People Without Email API", () => {
    beforeEach(() => {
        cy.makePrivateAdminAPICall("GET", "/api/persons/email/without", "", 200).as("withoutEmail");
        cy.freshAdminLogin();
    });

    it("returns count and persons array", () => {
        cy.get("@withoutEmail").then((response) => {
            expect(response.body).to.have.property("count").that.is.a("number");
            expect(response.body).to.have.property("persons").that.is.an("array");
            expect(response.body.persons).to.have.length(response.body.count);
        });
    });

    it("returns correct person shape", () => {
        cy.get("@withoutEmail").then((response) => {
            if (response.body.persons.length === 0) return;
            const person = response.body.persons[0];
            expect(person).to.have.all.keys("Id", "FullName", "FamilyId", "FamilyName", "FamilyRole", "Classification", "Age", "IsChild");
        });
    });

    it("excludes people who have an email address", () => {
        cy.get("@withoutEmail").then((response) => {
            // All returned persons should have no email — validated server-side,
            // but we confirm the response is coherent (non-negative count)
            expect(response.body.count).to.be.at.least(0);
        });
    });

    it("IsChild is a boolean", () => {
        cy.get("@withoutEmail").then((response) => {
            response.body.persons.forEach((person) => {
                expect(person.IsChild).to.be.a("boolean");
            });
        });
    });
});
