/// <reference types="cypress" />

describe("Envelope Manager", () => {
    const CAMPBELL = 1;
    const HART = 2;
    const envelopeField = (familyId) => cy.get(`input[name="envelopes[${familyId}]"]`);
    const rows = () => cy.get("#envelope-table tbody tr");

    afterEach(() => {
        cy.setFamilyEnvelope(CAMPBELL, 0);
        cy.setFamilyEnvelope(HART, 0);
    });

    it("a treasurer opens it from the finance dashboard", () => {
        cy.setupFinanceOnlySession();
        cy.visit("/finance/");
        cy.get('.page-header a[href$="/finance/envelopes"]').click();
        cy.location("pathname").should("match", /\/finance\/envelopes$/);
        cy.get("h2.page-title").should("contain", "Envelope Manager");
    });

    it("saves only the envelope number that changed", () => {
        cy.setupFinanceOnlySession();
        cy.visit("/finance/envelopes");
        envelopeField(CAMPBELL).clear().type("7101");
        cy.contains("button", "Update Family Records").click();
        cy.get("#envelope-save").should("be.visible").click();

        cy.location("pathname").should("match", /\/finance\/envelopes$/);
        cy.contains("1 envelope number saved.").should("exist");
        envelopeField(CAMPBELL).should("have.value", "7101");
        envelopeField(HART).should("have.value", "0");
    });

    it("assigns numbers in name order without saving them", () => {
        cy.setupFinanceOnlySession();
        cy.visit("/finance/envelopes");
        cy.get("#assignStartNumber").clear().type("7200");
        cy.get("#envelope-assign").click();

        cy.get("#envelope-unsaved").should("be.visible");
        rows().eq(0).find("input").should("have.value", "7200");
        rows().eq(1).find("input").should("have.value", "7201");

        cy.visit("/finance/envelopes");
        rows().eq(0).find("input").should("have.value", "0");
    });

    it("rejects an envelope number that is not a whole number and saves nothing", () => {
        cy.setupFinanceOnlySession();
        cy.visit("/finance/envelopes");
        envelopeField(CAMPBELL).clear().type("7102");
        envelopeField(HART).clear().type("12a");
        cy.contains("button", "Update Family Records").click();
        cy.get("#envelope-save").should("be.visible").click();

        cy.get("#envelope-error").should("be.visible");
        envelopeField(HART).should("have.class", "is-invalid").and("have.value", "12a");

        cy.visit("/finance/envelopes");
        envelopeField(CAMPBELL).should("have.value", "0");
    });

    it("sorts by envelope number and flags a duplicate", () => {
        cy.setFamilyEnvelope(CAMPBELL, 7300);
        cy.setFamilyEnvelope(HART, 7300);
        cy.setupFinanceOnlySession();
        cy.visit("/finance/envelopes");
        cy.get("#sortByEnvelope").check();
        cy.contains("#envelope-filter button", "Apply").click();

        cy.location("search").should("include", "sortBy=envelope");
        rows().last().find("input").should("have.value", "7300");
        cy.get("#envelope-table td.bg-danger-lt").should("have.length", 1);
    });

    it("lists only families with a member in the chosen classification", () => {
        cy.setupFinanceOnlySession();
        cy.visit("/finance/envelopes");
        cy.get("#classification").select("Guest");
        cy.contains("#envelope-filter button", "Apply").click();

        rows().should("have.length", 1).and("contain", "Diaz");
        cy.get('#envelope-form input[name="classification"]').should("have.value", "3");
    });

    it("sends staff without finance access to access denied", () => {
        cy.setupPersonaSession("office-staff");
        cy.request({ url: "/finance/envelopes", followRedirect: false, failOnStatusCode: false }).then((response) => {
            expect(response.status).to.eq(302);
            expect(response.headers.location).to.include("v2/access-denied?role=Finance");
        });
    });
});
