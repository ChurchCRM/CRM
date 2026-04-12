/// <reference types="cypress" />

describe("Event Types Management", () => {
  const eventTypeName = "Test Event Type " + Date.now();

  beforeEach(() => {
    cy.setupAdminSession();
  });

  it("should view all event types", () => {
  cy.visit("event/types");
  cy.contains("Event Types");
  cy.get(".table tbody tr").should("exist");
  cy.get(".table thead").should("contain", "Name");
  cy.get(".table tbody").should("contain", "Church Service");
  cy.get(".table tbody").should("contain", "Sunday School");
  });

  it("should add an event type", () => {
    cy.visit("event/types");
    cy.contains("Event Types");
    cy.contains("Add Event Type").click();

    cy.get('input[name="newEvtName"]').type(eventTypeName);
    cy.get('input[name="newEvtTypeCntLst"]').type("Members, Visitors");
    cy.contains("Save Event Type").click();

    cy.contains(eventTypeName).should("exist");
  });

  it("should view event type by direct URL", () => {
    cy.visit("event/types/1");
    cy.contains("Edit Event Type");
    cy.get('input[name="newEvtName"]').should("exist");
  });

  it("should display Events count column", () => {
    cy.visit("event/types");
    cy.get(".table thead").should("contain", "Events");
  });

  it("should show event count for types with events", () => {
    // Create an event using type 1 so the count is > 0
    cy.makePrivateAdminAPICall(
      "POST",
      "/api/events/quick-create",
      { eventTypeId: 1 },
      200,
    );

    // cy.request resets PHP sessions — clearCookies + direct login
    cy.clearCookies();
    cy.visit("session/begin");
    cy.get("input[name=User]").type(Cypress.env("admin.username"));
    cy.get("input[name=Password]").type(Cypress.env("admin.password") + "{enter}");
    cy.url().should("not.include", "session/begin");

    cy.visit("event/types");
    // The "Church Service" type should have a badge with a count
    cy.get(".table tbody tr").first().within(() => {
      cy.get(".badge.bg-blue-lt").should("exist");
    });
  });

  it("should block deletion of event types that have events", () => {
    // Ensure an event exists for type 1
    cy.makePrivateAdminAPICall(
      "POST",
      "/api/events/quick-create",
      { eventTypeId: 1 },
      200,
    );

    // cy.request resets PHP sessions — clearCookies + direct login
    cy.clearCookies();
    cy.visit("session/begin");
    cy.get("input[name=User]").type(Cypress.env("admin.username"));
    cy.get("input[name=Password]").type(Cypress.env("admin.password") + "{enter}");
    cy.url().should("not.include", "session/begin");

    // .delete-type-btn lives on the list page, not the edit page
    cy.visit("event/types");
    cy.get(".delete-type-btn").first().should("have.attr", "data-event-count");
    // Confirm the count attribute is > 0 since we just created an event
    cy.get(".delete-type-btn").first().invoke("attr", "data-event-count").then((count) => {
      expect(parseInt(count)).to.be.greaterThan(0);
    });
  });

  it("should pass data-event-count attribute on delete button", () => {
    // Ensure event exists so the button renders with a count
    cy.makePrivateAdminAPICall(
      "POST",
      "/api/events/quick-create",
      { eventTypeId: 1 },
      200,
    );

    cy.clearCookies();
    cy.visit("session/begin");
    cy.get("input[name=User]").type(Cypress.env("admin.username"));
    cy.get("input[name=Password]").type(Cypress.env("admin.password") + "{enter}");
    cy.url().should("not.include", "session/begin");

    cy.visit("event/types");
    cy.get(".delete-type-btn").first().should("have.attr", "data-event-count");
  });
});
