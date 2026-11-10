/// <reference types="cypress" />

// #10202: a link must not change data. Each action refuses GET and answers 403 to a POST without a token.
describe("State-changing actions need a POST with a CSRF token", () => {
    const PERSON_ID = 1;
    const personView = `/people/view/${PERSON_ID}`;
    const unique = () => `${Date.now()}`.slice(-8);

    const createOpportunity = () =>
        cy
            .makePrivateAdminAPICall("POST", "/admin/api/volunteer-opportunities", {
                name: `CSRF ${unique()}`,
                description: "CSRF regression",
                active: true,
            }, 201)
            .then(({ body }) => body.volunteerOpportunity.id);

    const deleteOpportunity = (id) =>
        cy.request({ method: "DELETE", url: `/admin/api/volunteer-opportunities/${id}`, failOnStatusCode: false });

    const opportunityStatus = (id) =>
        cy.request({ url: `/admin/api/volunteer-opportunities/${id}`, failOnStatusCode: false }).its("status");

    const postForm = (url, body) =>
        cy.request({ method: "POST", url, form: true, body, followRedirect: false, failOnStatusCode: false });

    beforeEach(() => cy.setupAdminSession());

    it("volunteer opportunity editor deletes only on a POST with a token", () => {
        createOpportunity().then((id) => {
            cy.request(`/VolunteerOpportunityEditor.php?act=ConfDelete&Opp=${id}`);
            opportunityStatus(id).should("eq", 200);

            postForm("/VolunteerOpportunityEditor.php", { act: "ConfDelete", Opp: id }).its("status").should("eq", 403);
            opportunityStatus(id).should("eq", 200);

            cy.visit(`/VolunteerOpportunityEditor.php?act=delete&Opp=${id}`);
            cy.get('form input[name="act"][value="ConfDelete"]').closest("form").submit();
            opportunityStatus(id).should("eq", 404);
        });
    });

    it("volunteer opportunity editor reorders with POST buttons", () => {
        createOpportunity().then((first) => {
            createOpportunity().then((second) => {
                cy.visit("/VolunteerOpportunityEditor.php");
                cy.get('a[href*="act=up"], a[href*="act=down"]').should("not.exist");
                cy.get('#oppMoveUp input[name="csrf_token"]').should("exist");
                cy.get('#oppMoveDown input[name="csrf_token"]').should("exist");
                cy.get('button[form="oppMoveDown"][name="row_num"]').should("exist");

                postForm("/VolunteerOpportunityEditor.php", { act: "down", row_num: 1 }).its("status").should("eq", 403);

                deleteOpportunity(first);
                deleteOpportunity(second);
            });
        });
    });

    it("property type delete runs only on a POST with a token", () => {
        cy.makePrivateAdminAPICall("POST", "/admin/api/property-types", {
            class: "p",
            name: `CSRF PT ${unique()}`,
            description: "CSRF regression",
        }, 201).then(({ body }) => {
            const id = body.propertyType.id;
            const typeStatus = () =>
                cy.request({ url: `/admin/api/property-types/${id}`, failOnStatusCode: false }).its("status");

            cy.request(`/PropertyTypeDelete.php?Confirmed=Yes&PropertyTypeID=${id}`);
            typeStatus().should("eq", 200);

            postForm("/PropertyTypeDelete.php", { PropertyTypeID: id }).its("status").should("eq", 403);
            typeStatus().should("eq", 200);

            cy.visit(`/PropertyTypeDelete.php?PropertyTypeID=${id}`);
            cy.contains("button", "Yes, delete").click();
            cy.location("pathname").should("include", "PropertyTypeList.php");
            typeStatus().should("eq", 404);
        });
    });

    it("person view removes a volunteer opportunity only on a POST with a token", () => {
        createOpportunity().then((id) => {
            const removeButton = `#volunteer button[name="RemoveVO"][value="${id}"]`;

            cy.sessionCsrfToken(personView).then((csrfToken) => {
                postForm(personView, { csrf_token: csrfToken, "VolunteerOpportunityIDs[]": id });
            });
            postForm(personView, { "VolunteerOpportunityIDs[]": id }).its("status").should("eq", 403);

            cy.request(`${personView}?RemoveVO=${id}`);
            cy.visit(personView);
            cy.get(removeButton).should("exist");

            postForm(personView, { RemoveVO: id }).its("status").should("eq", 403);

            cy.get("#nav-item-volunteer").click();
            cy.get(removeButton).click();
            cy.location("pathname").should("include", personView);
            cy.get(removeButton).should("not.exist");
            deleteOpportunity(id);
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
        cy.visit("/ConvertIndividualToFamily.php");
        cy.contains("People without a family");
        cy.get('form[action="ConvertIndividualToFamily.php"] input[name="csrf_token"]').should("exist");
        postForm("/ConvertIndividualToFamily.php", { all: "true" }).its("status").should("eq", 403);
    });

    it("family verify URL refuses GET", () => {
        cy.request({ url: "/api/family/1/verify/url", failOnStatusCode: false }).its("status").should("eq", 405);
    });

    it("upgrade release download refuses GET", () => {
        cy.request({ url: "/admin/api/upgrade/download-latest-release", failOnStatusCode: false })
            .its("status")
            .should("eq", 405);
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
