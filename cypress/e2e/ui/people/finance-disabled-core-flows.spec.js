/// <reference types="cypress" />

/**
 * Daily people pages for a church that is not using finance (#11880).
 * An admin still has finance while bEnabledFinance is on, so that profile
 * turns the module off. The staff profile has every other permission and
 * leaves the module on.
 * The finance-on family view stays in family.view-no-finance.spec.js.
 */

const createdFamilyIds = [];

afterEach(function () {
    if (this.currentTest?.state !== "failed" || createdFamilyIds.length === 0) {
        return;
    }
    cy.cleanupFamilies(createdFamilyIds.splice(0));
});

const postEditorForm = (fields) =>
    cy.get("#familyEditor").then(($form) => {
        const body = new URLSearchParams(new FormData($form[0]));
        Object.entries({ ...fields, FamilySubmit: "" }).forEach(([key, value]) => body.set(key, value));
        return cy.request({
            method: "POST",
            url: $form.attr("action"),
            headers: { "content-type": "application/x-www-form-urlencoded" },
            body: body.toString(),
            followRedirect: false,
        });
    });

const walkCoreFlows = (familyName, street, updatedStreet) => {
    cy.visit("/FamilyEditor.php");
    cy.get("#FamilyName").type(familyName);
    postEditorForm({
        Address1: street,
        City: "Kansas City",
        stateType: "dropDown",
        State: "MO",
        Zip: "64111",
        Country: "US",
        FirstName1: "Nora",
        Classification1: "1",
    }).then((response) => {
        expect(response.status).to.eq(302);
        const familyId = Number(String(response.headers.location).split("/").pop());
        expect(familyId).to.be.greaterThan(0);
        createdFamilyIds.push(familyId);

        cy.visit(`/people/family/${familyId}`);
        cy.get("h2.page-title").should("contain.text", familyName);
        cy.get("#family-profile-header").should("be.visible");
        cy.get("#family-address-card").should("contain.text", street);
        cy.get("#nav-item-giving").should("not.exist");
        cy.get("#family-giving-summary").should("not.exist");
        cy.contains("Nora").should("be.visible");

        cy.get('a[href*="/people/view/"]').first().click();
        cy.location("pathname").should("match", /\/people\/view\/\d+$/);
        cy.get("h2.page-title").should("contain.text", "Nora");
        cy.get("#nav-item-giving").should("not.exist");
        cy.get("#nav-item-groups").should("be.visible");

        cy.visit("/people/list");
        cy.get("#members tbody tr", { timeout: 10000 }).should("have.length.greaterThan", 0);
        cy.get(".dt-search input").first().type("Nora");
        cy.get("#members tbody").should("contain.text", familyName);

        cy.visit("/people/dashboard");
        cy.location("pathname").should("include", "/people/dashboard");
        cy.contains("Families");
        cy.get("#depositChartRow").should("not.exist");

        cy.visit(`/FamilyEditor.php?FamilyID=${familyId}`);
        cy.get("#FamilyName").should("have.value", familyName);
        postEditorForm({ Address1: updatedStreet }).its("status").should("eq", 302);

        cy.visit(`/people/family/${familyId}`);
        cy.get("h2.page-title").should("contain.text", familyName);
        cy.get("#family-address-card").should("contain.text", updatedStreet);
        cy.get("#nav-item-giving").should("not.exist");
    });
};

describe("Admin with finance turned off", () => {
    let originalFinance;

    before(() => {
        cy.getSystemConfig("bEnabledFinance").then((value) => {
            originalFinance = value;
        });
        cy.setSystemConfig("bEnabledFinance", "0");
    });

    beforeEach(() => {
        cy.setupPersonaSession("admin-finance-disabled");
    });

    after(() => {
        cy.restoreSystemConfig("bEnabledFinance", originalFinance);
        cy.cleanupFamilies(createdFamilyIds.splice(0));
    });

    it("opens the family, the person, the people list and the dashboard, then saves a family edit", () => {
        const suffix = Cypress._.random(0, 1e6);
        walkCoreFlows(`AdaNoFinance${suffix}`, "11880 Admin Way", "11880 Admin Updated Way");
    });
});

describe("Staff with every permission except finance", () => {
    beforeEach(() => {
        cy.setupPersonaSession("staff-all-but-finance");
    });

    after(() => {
        cy.cleanupFamilies(createdFamilyIds.splice(0));
    });

    it("opens the family, the person, the people list and the dashboard, then saves a family edit", () => {
        const suffix = Cypress._.random(0, 1e6);
        walkCoreFlows(`OwenNoFinance${suffix}`, "11880 Staff Way", "11880 Staff Updated Way");
    });
});
