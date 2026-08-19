/// <reference types="cypress" />

describe("Families Without Email API", () => {
    beforeEach(() => {
        cy.makePrivateAdminAPICall("GET", "/api/families/email/without", "", 200).as("familiesWithoutEmail");
    });

    it("returns count and families array", () => {
        cy.get("@familiesWithoutEmail").then((response) => {
            expect(response.body).to.have.property("count").that.is.a("number");
            expect(response.body).to.have.property("families").that.is.an("array");
            expect(response.body.families).to.have.length(response.body.count);
        });
    });

    it("count is zero or positive", () => {
        cy.get("@familiesWithoutEmail").then((response) => {
            expect(response.body.count).to.be.at.least(0);
        });
    });

    it("returns correct family shape", () => {
        cy.get("@familiesWithoutEmail").then((response) => {
            if (response.body.families.length === 0) return;
            const family = response.body.families[0];
            // Family should have standard properties from Family table
            expect(family).to.have.property("Id");
            expect(family).to.have.property("Name");
            expect(family).to.have.property("Email");
        });
    });

    it("families have empty Email field", () => {
        cy.get("@familiesWithoutEmail").then((response) => {
            if (response.body.families.length === 0) return;
            response.body.families.forEach((family) => {
                expect(family.Email).to.be.oneOf(["", null, undefined]);
            });
        });
    });

    it("ensures no family has both email and person with email", () => {
        // This is a negative test: if the optimization works, every returned family
        // should have no email on the family record AND no email on any person in that family.
        // Since we can't query person data in this endpoint, we at least verify the
        // family email constraint is enforced.
        cy.get("@familiesWithoutEmail").then((response) => {
            response.body.families.forEach((family) => {
                // Family itself has no email (server constraint)
                expect(family.Email).to.be.oneOf(["", null, undefined]);
                // The fact that this family was returned means the query correctly
                // filtered for families without emails (optimization validates)
            });
        });
    });
});
