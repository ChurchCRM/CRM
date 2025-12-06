/// <reference types="cypress" />

describe("Event Types Management", () => {
  const eventTypeName = "Test Event Type " + Date.now();

  beforeEach(() => {
    cy.setupAdminSession();
  });

  it("should view all event types", () => {
  cy.visit("EventNames.php");
  cy.contains("Event Types");
  cy.get(".table tbody tr").should("exist");
  cy.get(".table thead").should("contain", "Name");
  cy.get(".table tbody").should("contain", "Church Service");
  cy.get(".table tbody").should("contain", "Sunday School");
  });

  it("should add an event type", () => {
    cy.visit("EventNames.php");
    cy.contains("Event Types");
    cy.contains("Add Event Type").click();

    cy.get('input[name="newEvtName"]').type(eventTypeName);
    cy.get('input[name="newEvtTypeCntLst"]').type("Members, Visitors");
    cy.contains("Save Event Type").click();

    cy.contains(eventTypeName).should("exist");
  });

  it("should view event type by direct URL", () => {
    cy.visit("EditEventTypes.php?EN_tyid=1");
    cy.get('input[name="newEvtName"]').should("have.value", "Church Service");
  });
  
});
