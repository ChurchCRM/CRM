/// <reference types="cypress" />

/**
 * UI tests for the person-search pickers on /event/checkin/{id} — issue #9819.
 *
 * Three AJAX person-search TomSelects were hand-rolled with three different
 * option sets; #9819 extracts them into `webpack/common/person-select.ts`.
 * This spec pins the behaviour of the two that live on the check-in page:
 *
 *   1. the walk-in `#child` / `#adult` `.person-search` selects
 *      (src/event/views/checkin.php), and
 *   2. the `#checkoutBySelect` picker built inside the runtime check-out modal
 *      (webpack/event-checkin.js `openCheckoutByDialog`).
 *
 * Key assertion: each picker's dropdown must be a DIRECT CHILD of <body>,
 * proving `dropdownParent: "body"` (#9488) is in effect. TomSelect appends the
 * dropdown at construction time and gives its `.ts-dropdown-content` the id
 * `<select id>-ts-dropdown`, so `body > .ts-dropdown > #child-ts-dropdown`
 * identifies one specific instance's dropdown unambiguously — important here
 * because several body-mounted dropdowns coexist on this page.
 *
 * The walk-in assertions FAIL before #9819: those two selects omit
 * `dropdownParent` entirely, so their dropdowns stay inside the
 * `.card > .card-body` that clips them.
 */
describe("Event check-in person pickers (#9819)", () => {
    let testEventId;

    before(() => {
        // Fresh event so the walk-in check-in card renders (event type 1 has no
        // linked group, so the page shows the walk-in card and not the roster).
        cy.makePrivateAdminAPICall("POST", "/api/events/quick-create", { eventTypeId: 1 }, 200).then(
            (resp) => {
                expect(resp.body).to.have.property("eventId");
                testEventId = resp.body.eventId;

                // Check person 1 in so the row-level "Check Out" action exists.
                cy.makePrivateAdminAPICall(
                    "POST",
                    `/api/events/${testEventId}/checkin`,
                    { personId: 1 },
                    200,
                );
            },
        );
    });

    after(() => {
        if (testEventId) {
            // An event cannot be deleted while attendees are still checked in.
            cy.makePrivateAdminAPICall("POST", `/api/events/${testEventId}/checkout-all`, {}, 200);
            cy.makePrivateAdminAPICall("DELETE", `/api/events/${testEventId}`, {}, 200);
        }
    });

    beforeEach(() => {
        // setupAdminSession validates the cached session and re-logs in if the
        // API-key calls in before() clobbered the PHP session's auth provider.
        cy.setupAdminSession();
        cy.on("uncaught:exception", () => false);

        cy.visit(`/event/checkin/${testEventId}`);
        cy.window().should("have.property", "CRM");
        cy.window().its("CRM.localesLoaded").should("eq", true);

        // TomSelect marks the original <select> with `tomselected` once it wraps it.
        cy.get("select#child", { timeout: 10000 }).should("have.class", "tomselected");
        cy.get("select#adult", { timeout: 10000 }).should("have.class", "tomselected");
    });

    it("#child picker searches, renders its dropdown on body, and selects a person", () => {
        cy.get("select#child").next(".ts-wrapper").find(".ts-control").click();
        cy.get("select#child").next(".ts-wrapper").find(".ts-control input").type("Smith");

        // Results arrive from /api/persons/search/ (2-character minimum).
        cy.get("#child-ts-dropdown .option", { timeout: 10000 }).should(
            "have.length.greaterThan",
            0,
        );

        // Key assertion — fails before #9819: this select omits dropdownParent,
        // so its dropdown is nested in the clipping .card-body instead of body.
        cy.get("body > .ts-dropdown > #child-ts-dropdown").should("exist");

        cy.get("#child-ts-dropdown .option").contains("Smith").click();

        // Selection is mirrored into the <select> value and rendered inline.
        cy.get("select#child").should("not.have.value", "");
        cy.get("#childDetails").should("be.visible").and("contain.text", "Smith");
    });

    it("#adult picker searches and renders its dropdown on body", () => {
        cy.get("select#adult").next(".ts-wrapper").find(".ts-control").click();
        cy.get("select#adult").next(".ts-wrapper").find(".ts-control input").type("Smith");

        cy.get("#adult-ts-dropdown .option", { timeout: 10000 }).should(
            "have.length.greaterThan",
            0,
        );

        // Key assertion — fails before #9819 for the same reason as #child.
        cy.get("body > .ts-dropdown > #adult-ts-dropdown").should("exist");

        cy.get("#adult-ts-dropdown .option").contains("Smith").click();
        cy.get("#adultDetails").should("be.visible").and("contain.text", "Smith");
    });

    it("both walk-in pickers keep their data-placeholder", () => {
        cy.get("select#child")
            .next(".ts-wrapper")
            .find(".ts-control input")
            .should("have.attr", "placeholder", "Search by name or email...");
        cy.get("select#adult")
            .next(".ts-wrapper")
            .find(".ts-control input")
            .should("have.attr", "placeholder", "Search for supervisor...");
    });

    it("check-out modal picker initialises on shown.bs.modal and is cleaned up on close", () => {
        // Nothing for the modal's select exists before the modal is opened.
        cy.get("#checkoutBySelect-ts-dropdown").should("not.exist");

        cy.get("tr[data-person-id='1'] [data-bs-toggle='dropdown']", { timeout: 10000 })
            .should("exist")
            .click();
        cy.get("tr[data-person-id='1'] .checkout-btn").should("be.visible").click();

        cy.get(".modal.show", { timeout: 10000 }).should("exist");

        // The picker is built inside the shown.bs.modal handler, not at modal
        // creation — so its dropdown only appears once the modal is fully shown.
        cy.get("#checkoutBySelect-ts-dropdown", { timeout: 10000 }).should("exist");
        cy.get("body > .ts-dropdown > #checkoutBySelect-ts-dropdown").should("exist");

        cy.get(".modal.show select#checkoutBySelect")
            .next(".ts-wrapper")
            .find(".ts-control input")
            .type("Smith");
        cy.get("#checkoutBySelect-ts-dropdown .option", { timeout: 10000 }).should(
            "have.length.greaterThan",
            0,
        );

        // Cancel — do not actually check the person out.
        cy.get(".modal.show #checkoutCancelBtn").click({ force: true });
        cy.get("#crm-checkout-by-modal", { timeout: 10000 }).should("not.exist");

        // hidden.bs.modal must destroy the TomSelect, leaving no orphaned
        // body-mounted dropdown behind. The walk-in pickers' own dropdowns are
        // untouched, which is why this is scoped to the modal select's id.
        cy.get("#checkoutBySelect-ts-dropdown").should("not.exist");
    });
});
