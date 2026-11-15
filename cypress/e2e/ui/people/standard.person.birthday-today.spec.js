/// <reference types="cypress" />

// Birthday today callout on the person view (#4398).
describe("Person view birthday today", () => {
    const createdPersonIds = [];

    // Forty years back keeps the month and day valid even on 29 February.
    const birthdayOn = (date, name) => ({
        name,
        lastName: "BirthdayTodayTest",
        month: date.getMonth() + 1,
        day: date.getDate(),
        year: date.getFullYear() - 40,
    });

    const createPerson = (person) =>
        cy.createPersonWithBirthday(person).then((personId) => {
            createdPersonIds.push(personId);
            return personId;
        });

    before(() => cy.useChurchTimeZone());

    after(() => {
        cy.useHostTimeZone();
        cy.setupAdminSession();
        cy.cleanupPeople(createdPersonIds);
    });

    beforeEach(() => cy.setupStandardSession());

    it("calls out the birthday on the day", () => {
        createPerson(birthdayOn(new Date(), "Bettie"));
        cy.get("#person-birthday-today").should("be.visible").and("contain", "Birthday today");
    });

    it("shows no callout on another day", () => {
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        createPerson(birthdayOn(tomorrow, "Tomas"));
        cy.get(".fa-cake-candles").should("exist");
        cy.get("#person-birthday-today").should("not.exist");
    });

    it("shows no callout for a deceased person", () => {
        createPerson(birthdayOn(new Date(), "Dorothy")).then((personId) => {
            cy.get("#person-birthday-today").should("be.visible");
            cy.visit(`/PersonEditor.php?PersonID=${personId}`);
            cy.get("#IsDeceased").check();
            cy.get("button[name='PersonSubmit']").click();
            cy.url().should("include", `/people/view/${personId}`);
            cy.contains(".badge", "Deceased").should("be.visible");
            cy.get("#person-birthday-today").should("not.exist");
        });
    });
});
