describe("Admin organizes properties into property types", () => {
    const typeName = `Ministry Skills ${Date.now()}`;
    const renamedTypeName = `${typeName} Renamed`;
    let propertyTypeId;

    beforeEach(() => {
        cy.setupAdminSession();
    });

    after(() => {
        cy.makePrivateAdminAPICall("DELETE", `/admin/api/property-types/${propertyTypeId}`, null, 200);
        cy.then(() => Cypress.session.clearAllSavedSessions());
    });

    const propertyTypeCount = () => cy.request("/admin/api/property-types").its("body.propertyTypes.length");

    it("adds a family property type from the list and renames it", () => {
        cy.visit("/PropertyTypeList.php");
        cy.get('a[href$="/admin/property-types/new"]').first().click();
        cy.location("pathname").should("match", /\/admin\/property-types\/new$/);

        cy.get("#class").select("f");
        cy.get("#name").type(typeName);
        cy.get("#description").type("Skills a family can offer");
        cy.get("#save-property-type").click();

        cy.location("pathname").should("match", /\/PropertyTypeList\.php$/);
        cy.contains("tr", typeName)
            .should("contain", "Family")
            .find('a[href*="/admin/property-types/"]')
            .invoke("attr", "href")
            .then((href) => {
                propertyTypeId = Number(href.match(/\/admin\/property-types\/(\d+)\/edit$/)[1]);
                cy.visit(`/admin/property-types/${propertyTypeId}/edit`);
            });

        cy.get("#class").should("have.value", "f");
        cy.get("#name").should("have.value", typeName).clear().type(renamedTypeName);
        cy.get("#save-property-type").click();

        cy.location("pathname").should("match", /\/PropertyTypeList\.php$/);
        cy.contains("tr", renamedTypeName).should("exist");
        cy.then(() => cy.request(`/admin/api/property-types/${propertyTypeId}`))
            .its("body.propertyType")
            .should("deep.include", {
                class: "f",
                name: renamedTypeName,
                description: "Skills a family can offer",
            });
    });

    it("refuses a blank or too long name and keeps the type list unchanged", () => {
        cy.visit("/admin/property-types/new");
        propertyTypeCount().then((countBefore) => {
            cy.get('input[name="csrf_token"]')
                .invoke("val")
                .then((csrfToken) => {
                    [
                        ["", "You must enter a name"],
                        ["x".repeat(51), "The name can be at most 50 characters"],
                    ].forEach(([name, message]) => {
                        cy.request({
                            method: "POST",
                            url: "/admin/property-types/new",
                            form: true,
                            body: { csrf_token: csrfToken, class: "p", name, description: "" },
                        })
                            .its("body")
                            .should("contain", message);
                    });
                });
            propertyTypeCount().should("eq", countBefore);
        });
    });

    it("answers 404 for a property type that does not exist", () => {
        cy.request({ url: "/admin/property-types/999999/edit", failOnStatusCode: false })
            .its("status")
            .should("eq", 404);
    });
});
