/// <reference types="cypress" />

describe("Standard Person", () => {
    const uniqueSeed = Date.now().toString();
    
    it("Add Person only first and last name", () => {
        const name = "Robby " + uniqueSeed;
        cy.loginStandard("PersonEditor.php");
        cy.get("#FirstName").type(name);
        cy.get("#LastName").type("Campbell");
        cy.get("#PersonSaveButton").click();

        cy.url().should("contain", "PersonView.php");
        cy.contains(name).should("be.visible");
    });

    it("Add Person with middle name", () => {
        const firstName = "Mathew " + uniqueSeed;
        cy.loginStandard("PersonEditor.php");
        cy.get("#FirstName").type(firstName);
        cy.get("#MiddleName").type("Henry");
        cy.get("#LastName").type("Campbell");
        cy.get("#PersonSaveButton").click();
        cy.url().should("contain", "PersonView.php");
        cy.contains(firstName).should("be.visible");
    });

    it("Name Search", () => {
        cy.loginStandard("v2/dashboard");
        cy.apiRequest({
            method: "GET",
            url: "/api/search/cam",
        }).then((resp) => {
            expect(resp.status).to.eq(200);
            expect(resp.body).to.have.property('length');
            expect(resp.body.length).to.be.gte(2);
        });
    });

    it("Middle Name Search", () => {
        cy.loginStandard("v2/dashboard");
        cy.apiRequest({
            method: "GET",
            url: "/api/search/henry",
        }).then((resp) => {
            expect(resp.status).to.eq(200);
            expect(resp.body).to.have.property('length');
            expect(resp.body.length).to.be.gte(1);
        });
    });

    it("Unknown Name Search", () => {
        const unknownName = "nobody " + uniqueSeed;
        cy.loginStandard("v2/dashboard");
        cy.apiRequest({
            method: "GET",
            url: "/api/search/" + unknownName,
        }).then((resp) => {
            expect(resp.status).to.eq(200);
            expect(resp.body).to.have.property('length');
            expect(resp.body.length).to.eq(0);
        });
    });
});
