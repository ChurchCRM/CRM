describe("Admin defines the properties families and people can carry", () => {
    const propertyName = `Hosts the O'Briens Small Group ${Date.now()}`;
    const renamedPropertyName = `${propertyName} Weekly`;
    const familyGeneralTypeId = 2;
    let propertyId;

    beforeEach(() => {
        cy.setupAdminSession();
    });

    after(() => {
        cy.makePrivateAdminAPICall("DELETE", `/admin/api/properties/definition/${propertyId}`, null, 200);
        cy.then(() => Cypress.session.clearAllSavedSessions());
    });

    const personPropertyCount = () => cy.request("/api/people/properties/person").its("body.length");

    it("adds a family property from the property list and edits it", () => {
        cy.visit("/PropertyList.php?Type=f");
        cy.get('a[href$="/admin/properties/family/new"]').first().click();
        cy.location("pathname").should("match", /\/admin\/properties\/family\/new$/);
        cy.contains("Family Property Editor");

        cy.get("#propertyTypeId").select(String(familyGeneralTypeId));
        cy.get("#name").type(propertyName);
        cy.get("#description").type("hosts a small group at home.");
        cy.get("#prompt").type("Which night?");
        cy.get("#save-property").click();

        cy.location("search").should("eq", "?Type=f");
        cy.contains("tr", propertyName)
            .should("contain", "Which night?")
            .find('a[href*="/admin/properties/"]')
            .invoke("attr", "href")
            .then((href) => {
                propertyId = Number(href.match(/\/admin\/properties\/(\d+)\/edit$/)[1]);
                cy.visit(`/admin/properties/${propertyId}/edit`);
            });

        cy.get("#propertyTypeId").should("have.value", String(familyGeneralTypeId));
        cy.get("#prompt").should("have.value", "Which night?").clear().type("Which night of the week?");
        cy.get("#name").should("have.value", propertyName).clear().type(renamedPropertyName);
        cy.get("#save-property").click();

        cy.location("search").should("eq", "?Type=f");
        cy.contains("tr", renamedPropertyName).should("contain", "Which night of the week?");
        cy.request("/api/people/properties/family")
            .its("body")
            .then((properties) => {
                expect(properties.find((property) => property.ProId === propertyId)).to.deep.include({
                    ProClass: "f",
                    ProPrtId: familyGeneralTypeId,
                    ProName: renamedPropertyName,
                    ProDescription: "hosts a small group at home.",
                    ProPrompt: "Which night of the week?",
                });
            });
    });

    it("refuses a blank name or a property type of another record type", () => {
        cy.visit("/admin/properties/person/new");
        personPropertyCount().then((countBefore) => {
            cy.get('input[name="csrf_token"]')
                .invoke("val")
                .then((csrfToken) => {
                    [
                        [{ propertyTypeId: 1, name: "" }, "You must enter a name"],
                        [{ propertyTypeId: familyGeneralTypeId, name: "Speaks Spanish" }, "You must select a type"],
                    ].forEach(([fields, message]) => {
                        cy.request({
                            method: "POST",
                            url: "/admin/properties/person/new",
                            form: true,
                            body: { csrf_token: csrfToken, description: "", prompt: "", ...fields },
                        })
                            .its("body")
                            .should("contain", message);
                    });
                });
            personPropertyCount().should("eq", countBefore);
        });
    });

    it("answers 404 for an unknown property or record type", () => {
        ["/admin/properties/999999/edit", "/admin/properties/church/new"].forEach((url) => {
            cy.request({ url, failOnStatusCode: false }).its("status").should("eq", 404);
        });
    });
});
