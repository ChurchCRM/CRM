context("Standard Person", () => {
    const uniqueSeed = Date.now().toString();
    it("Add Person only first and last name", () => {
        const name = "Robby " + uniqueSeed;
        cy.loginStandard("PersonEditor.php");
        cy.get("#FirstName").type(name);
        cy.get("#LastName").type("Campbell");
        cy.get("#PersonSaveButton").click();

        cy.url().should("contains", "PersonView.php");
        cy.contains(name);
    });

    it("Add Person with middle name", () => {
        const firstName = "Mathew " + uniqueSeed;
        cy.loginStandard("PersonEditor.php");
        cy.get("#FirstName").type(firstName);
        cy.get("#MiddleName").type("Henry");
        cy.get("#LastName").type("Campbell");
        cy.get("#PersonSaveButton").click();
        cy.url().should("contains", "PersonView.php");
        cy.contains(firstName);
    });

    it("Name Search", () => {
        cy.loginStandard("v2/dashboard");
        cy.request({
            method: "GET",
            url: "/api/search/cam",
        }).then((resp) => {
            const result = JSON.parse(JSON.stringify(resp.body));
            expect(resp.status).to.eq(200);
            expect(result.length).be.gte(2);
        });
    });

    it("Middle Name Search", () => {
        cy.loginStandard("v2/dashboard");
        cy.request({
            method: "GET",
            url: "/api/search/henry",
        }).then((resp) => {
            const result = JSON.parse(JSON.stringify(resp.body));
            expect(resp.status).to.eq(200);
            expect(result.length).be.gte(1);
        });
    });

    it("Unknown Name Search", () => {
        const unknownName = "nobody " + uniqueSeed;
        cy.loginStandard("v2/dashboard");
        cy.request({
            method: "GET",
            url: "/api/search/" + unknownName,
        }).then((resp) => {
            const result = JSON.parse(JSON.stringify(resp.body));
            expect(resp.status).to.eq(200);
            expect(result.length).be.eq(0);
        });
    });
});
