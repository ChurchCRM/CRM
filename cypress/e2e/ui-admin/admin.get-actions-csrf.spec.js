/// <reference types="cypress" />

// #10202: a link must not change data. Each action refuses GET and answers 403 to a POST without a token.
describe("State-changing actions need a POST with a CSRF token", () => {
    const PERSON_ID = 1;
    const unique = () => `${Date.now()}`.slice(-8);

    const postForm = (url, body) =>
        cy.request({ method: "POST", url, form: true, body, followRedirect: false, failOnStatusCode: false });

    beforeEach(() => cy.setupAdminSession());

    it("property type delete runs only on a DELETE with a token", () => {
        cy.makePrivateAdminAPICall("POST", "/admin/api/property-types", {
            class: "p",
            name: `CSRF PT ${unique()}`,
            description: "CSRF regression",
        }, 201).then(({ body }) => {
            const id = body.propertyType.id;
            const typeStatus = () =>
                cy.request({ url: `/admin/api/property-types/${id}`, failOnStatusCode: false }).its("status");

            cy.request({ url: `/people/properties/types/${id}`, failOnStatusCode: false });
            typeStatus().should("eq", 200);

            cy.request({ method: "DELETE", url: `/people/properties/types/${id}`, failOnStatusCode: false })
                .its("status")
                .should("eq", 403);
            typeStatus().should("eq", 200);

            cy.visit("/people/properties/types");
            cy.get(`tr[data-type-id="${id}"] button[data-bs-toggle="dropdown"]`).click();
            cy.get(`button.delete-type-btn[data-id="${id}"]`).click();
            cy.contains(".bootbox button", "Yes, delete").click();
            cy.get(`tr[data-type-id="${id}"]`).should("not.exist");
            typeStatus().should("eq", 404);
        });
    });

    it("cart to group adds the cart only on a POST with a token", () => {
        cy.request({ method: "DELETE", url: "/api/cart/", body: {} });
        cy.request({ method: "POST", url: "/api/cart/", body: { Persons: [PERSON_ID] } });
        cy.request({ method: "POST", url: "/api/groups/", body: { groupName: `CSRF Cart ${unique()}` } }).then(
            ({ body }) => {
                const groupId = body.Id;
                const cartCount = () => cy.request("/api/cart/").its("body.PeopleCart.length");

                cy.request(`/groups/cart/add?groupeCreationID=${groupId}`);
                cartCount().should("eq", 1);

                postForm("/groups/cart/add", { GroupID: groupId }).its("status").should("eq", 403);
                cartCount().should("eq", 1);

                cy.request({ method: "DELETE", url: `/api/groups/${groupId}`, failOnStatusCode: false });
                cy.request({ method: "DELETE", url: "/api/cart/", body: {} });
            },
        );
    });

    it("convert individuals to families runs only on a POST with a token", () => {
        const convertAll = "/admin/api/people/convert-unassigned-to-families";

        cy.visit("/admin/people/convert-to-families");
        cy.get("#familyConversionCount")
            .invoke("text")
            .then((before) => {
                cy.request({ url: convertAll, failOnStatusCode: false }).its("status").should("eq", 405);
                postForm(convertAll, {}).its("status").should("eq", 403);
                postForm(`/admin/api/person/${PERSON_ID}/convert-to-family`, {}).its("status").should("eq", 403);

                cy.reload();
                cy.get("#familyConversionCount").should("have.text", before);
            });
    });

    it("upgrade release download refuses GET and a POST without a token", () => {
        cy.request({ url: "/admin/api/upgrade/download-latest-release", failOnStatusCode: false })
            .its("status")
            .should("eq", 405);
        postForm("/admin/api/upgrade/download-latest-release", {}).its("status").should("eq", 403);
    });

    it("email debug sends only on a POST with a token", () => {
        cy.visit("/admin/email/debug");
        cy.contains("SMTP Configuration");
        cy.get("#collapseSmtpDebug").should("not.exist");
        postForm("/admin/email/debug", {}).its("status").should("eq", 403);
    });

    it("backup download refuses GET and a POST without a token", () => {
        cy.request({ url: "/admin/api/database/download/missing.sql", failOnStatusCode: false })
            .its("status")
            .should("eq", 405);
        postForm("/admin/api/database/download/missing.sql", {}).its("status").should("eq", 403);
    });
});
