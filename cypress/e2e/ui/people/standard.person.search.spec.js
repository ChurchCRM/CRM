/// <reference types="cypress" />

describe("Standard Person", () => {
    const uniqueSeed = Date.now().toString();
    
    beforeEach(() => cy.setupStandardSession());
    
    it("Add Person only first and last name", () => {
        const name = "Robby " + uniqueSeed;
        cy.visit("PersonEditor.php");
        cy.get("#FirstName").type(name);
        cy.get("#LastName").type("Campbell");
        // Click FAB save button
        cy.get(".fab-save").click();

        cy.url().should("contain", "PersonView.php");
        cy.contains(name).should("be.visible");
    });

    it("Add Person with middle name", () => {
        const firstName = "Mathew " + uniqueSeed;
        cy.visit("PersonEditor.php");
        cy.get("#FirstName").type(firstName);
        cy.get("#MiddleName").type("Henry");
        cy.get("#LastName").type("Campbell");
        // Click FAB save button
        cy.get(".fab-save").click();
        cy.url().should("contain", "PersonView.php");
        cy.contains(firstName).should("be.visible");
    });

    it("Name Search", () => {
        cy.visit("v2/dashboard");
        cy.apiRequest({
            method: "GET",
            url: "/api/search/cam",
        }).then((resp) => {
            expect(resp.status).to.eq(200);
            expect(resp.body).to.have.property('length');

            // Verify Persons group exists with at least one person
            const personsGroup = resp.body.find(group => group.text && group.text.startsWith('Persons'));
            expect(personsGroup).to.exist;
            expect(personsGroup.children).to.be.an('array');
            expect(personsGroup.children.length).to.be.gte(2);
        });
    });

    it("Middle Name Search", () => {
        cy.visit("v2/dashboard");
        cy.apiRequest({
            method: "GET",
            url: "/api/search/henry",
        }).then((resp) => {
            expect(resp.status).to.eq(200);
            expect(resp.body).to.have.property('length');
            expect(resp.body.length).to.be.gte(1);
            
            // Verify Persons group exists with at least one person
            const personsGroup = resp.body.find(group => group.text && group.text.startsWith('Persons'));
            expect(personsGroup).to.exist;
            expect(personsGroup.children).to.be.an('array');
            expect(personsGroup.children.length).to.be.gte(1);
        });
    });

    it("Unknown Name Search", () => {
        const unknownName = "nobody " + uniqueSeed;
        cy.visit("v2/dashboard");
        cy.apiRequest({
            method: "GET",
            url: "/api/search/" + unknownName,
        }).then((resp) => {
            expect(resp.status).to.eq(200);
            expect(resp.body).to.have.property('length');
            
            // Ensure no Persons or Families groups are returned
            const personsGroup = resp.body.find(group => group.text && group.text.startsWith('Persons'));
            const familiesGroup = resp.body.find(group => group.text && group.text.startsWith('Families'));
            expect(personsGroup).to.be.undefined;
            expect(familiesGroup).to.be.undefined;
        });
    });
});
