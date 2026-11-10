describe("Intake staff add a family and continue entering its people", () => {
    const families = [];
    after(() => cy.cleanupFamilies(families));

    it("saves each record and opens an empty editor with the family selected", () => {
        const name = `ContinueEntry${Date.now()}`;
        cy.setupPersonaSession("records-intake");
        cy.visit("/people/family/editor");
        cy.get("#FamilyName").type(name);
        cy.intercept("POST", "**/people/family/editor*").as("saveFamily");
        cy.get('[name="familySubmitAndAdd"]').click();
        cy.wait("@saveFamily").its("response.statusCode").should("eq", 302);
        cy.location("pathname").should("include", "/people/family/editor");
        cy.get("#FamilyName").should("have.value", "");
        cy.request(`/api/families/search/${name}`).its("body.Families").then((results) => {
            expect(results).to.have.length(1);
            const familyId = results[0].Id;
            families.push(familyId);
            cy.visit(`/people/person/editor?familyId=${familyId}`);
            cy.get("#familyId").should("have.value", String(familyId));
            cy.get("#FirstName").type(name);
            cy.get("#LastName").type("Household");
            cy.get("#Classification").select("1");
            cy.intercept("POST", "**/people/person/editor*").as("savePerson");
            cy.get('[name="personSubmitAndAdd"]').click();
            cy.wait("@savePerson").its("response.statusCode").should("eq", 302);
            cy.location("pathname").should("include", "/people/person/editor");
            cy.location("search").should("include", `familyId=${familyId}`);
            cy.get("#FirstName").should("have.value", "");
            cy.get("#familyId").should("have.value", String(familyId));
            cy.request(`/api/persons/search/${name}`).its("body").then((people) => {
                expect(people).to.have.length(1);
                cy.request(`/api/person/${people[0].objid}`).its("body.FamId").should("eq", familyId);
            });
        });
    });
});
