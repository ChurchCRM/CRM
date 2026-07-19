/// <reference types="cypress" />

/**
 * UI regression tests for /people/cart/to-family
 *
 * Seed data used:
 *   Free persons (per_fam_ID = 0):
 *     - 27  Isaac Murry
 *     - 36  Kathryn Robertson
 *     - 37  Wayne Robertson
 *   In-family person:
 *     - 28  Rafael Dixon  (per_fam_ID = 6, Dixon family)
 *   Family roles (lst_ID = 2):
 *     - option_id 1 = Head of Household
 *   Existing families:
 *     - fam_ID 1 = Campbell
 *
 * Test order is intentional — T3/T4 are destructive (assign persons to families)
 * so non-destructive tests run first.
 */
describe("Cart to Family — UI", () => {
    const ROUTE = "/people/cart/to-family";

    /** Helper: empty the server-side cart via the REST API */
    const emptyCart = () =>
        cy.request({ method: "DELETE", url: "/api/cart/", failOnStatusCode: false });

    /** Helper: add one or more person IDs to the cart via the REST API */
    const addToCart = (personIds) =>
        cy.request({
            method: "POST",
            url: "/api/cart/",
            body: { Persons: personIds },
        });

    beforeEach(() => {
        cy.setupStandardSession();
        emptyCart();
    });

    // ── T1: Empty cart → empty state ────────────────────────────────────────
    it("T1 — shows empty state when cart is empty", () => {
        cy.visit(ROUTE);
        cy.get(".empty").should("be.visible");
        cy.get(".empty-title").should("contain", "cart is empty");
        cy.get("#cartToFamilyForm").should("not.exist");
        cy.get("a[href*='/people/cart']").should("be.visible");
    });

    // ── T2: Cart with 1 person → form displayed ─────────────────────────────
    it("T2 — shows role select for a cart person (per_fam_ID = 0)", () => {
        addToCart([36]);
        cy.visit(ROUTE);
        cy.get("#cartToFamilyForm").should("be.visible");
        cy.get("#role36").should("be.visible");
        cy.get("#role36 option").should("have.length.greaterThan", 1);
        cy.get("#newFamilyFieldset").should("be.visible"); // default: create new
    });

    // ── T5: Validation — blank family name ──────────────────────────────────
    it("T5 — shows error and stays on form when family name is blank", () => {
        addToCart([36]);
        cy.visit(ROUTE);
        // Choose "Create new family" (value=0, already selected by default) and pick role
        cy.get("#FamilyID").select("0");
        cy.get("#role36").select("1"); // Head of Household
        cy.get("#familyNameInput").clear();
        cy.get("#cartToFamilySubmit").click();
        // Should stay on form, show error, NOT redirect
        cy.url().should("include", ROUTE);
        cy.get("#cartToFamilyError").should("be.visible");
        cy.get("#cartToFamilyError").should("contain", "required");
        // Cart should still be populated
        cy.request("/api/cart/").then((resp) => {
            expect(resp.body.PeopleCart).to.have.length.greaterThan(0);
        });
    });

    // ── T7: Validation — no role selected ───────────────────────────────────
    it("T7 — shows error when role is not selected for an eligible person", () => {
        addToCart([36]);
        cy.visit(ROUTE);
        cy.get("#FamilyID").select("0");
        cy.get("#familyNameInput").type("Regression Family T7");
        // Deliberately do NOT select a role for person 36
        cy.get("#cartToFamilySubmit").click();
        cy.url().should("include", ROUTE);
        cy.get("#cartToFamilyError").should("be.visible");
        cy.get("#cartToFamilyError").should("contain", "role");
    });

    // ── T3: Create new family — happy path (destructive: assigns person 36) ─
    it("T3 — creates new family and assigns person, empties cart on success", () => {
        addToCart([36]);
        cy.visit(ROUTE);
        cy.get("#FamilyID").select("0");
        cy.get("#familyNameInput").type("CartToFamilyTestFamily-T3");
        cy.get("#role36").select("1"); // Head of Household
        cy.get("#cartToFamilySubmit").click();
        // Redirected to the new family page
        cy.url().should("match", /\/people\/family\/\d+/);
        // Cart is empty server-side
        cy.request("/api/cart/").then((resp) => {
            expect(resp.body.PeopleCart).to.deep.equal([]);
        });
    });

    // ── T4: Add to existing family (destructive: assigns person 37) ─────────
    it("T4 — assigns person to existing family and empties cart", () => {
        addToCart([37]);
        cy.visit(ROUTE);
        cy.get("#FamilyID").select("1"); // Campbell family (fam_ID = 1)
        cy.get("#newFamilyFieldset").should("not.be.visible"); // progressive disclosure hid it
        cy.get("#role37").select("1"); // Head of Household
        cy.get("#cartToFamilySubmit").click();
        cy.url().should("include", "/people/family/1");
        cy.request("/api/cart/").then((resp) => {
            expect(resp.body.PeopleCart).to.deep.equal([]);
        });
    });

    // ── T6: Mixed cart regression (#5647/#5971) ──────────────────────────────
    it("T6 — assigns free person, skips already-assigned person, no 500", () => {
        // Person 27 is free (per_fam_ID = 0), person 28 is in family 6
        addToCart([27, 28]);
        cy.visit(ROUTE);
        cy.get("#cartToFamilyForm").should("be.visible");
        // Person 27 should have a role select
        cy.get("#role27").should("be.visible");
        // Person 28 should be shown as "not included" (no role select, badge visible)
        cy.get("#role28").should("not.exist");
        cy.get(".badge.bg-blue-lt").should("contain.text", "Already in");
        // Submit: create new family, assign role to person 27
        cy.get("#FamilyID").select("0");
        cy.get("#familyNameInput").type("CartToFamilyTestFamily-T6");
        cy.get("#role27").select("1"); // Head of Household
        cy.get("#cartToFamilySubmit").click();
        // No 500 — redirect to new family page
        cy.url().should("match", /\/people\/family\/\d+/);
        // Cart is empty
        cy.request("/api/cart/").then((resp) => {
            expect(resp.body.PeopleCart).to.deep.equal([]);
        });
    });
});
