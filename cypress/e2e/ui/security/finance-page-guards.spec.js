/// <reference types="cypress" />

/**
 * Fundraiser pages stay open to a ManageFundraisers user and hidden from the menu of
 * one without it. The denial of /fundraiser/ and /finance/ is the gate rows in
 * cypress/support/gates/fundraiser.js and finance.js.
 */

const ACCESS_DENIED = "/v2/access-denied";

// Fundraiser pages gated on isManageFundraisersEnabled().
const READABLE_FUNDRAISER_PAGES = [
    "fundraiser/",
    "fundraiser/editor",
    "fundraiser/1/paddle-numbers",
    "fundraiser/1/donated-items/editor",
    "fundraiser/1/paddle-numbers/editor",
];

describe("ManageFundraisers permission guard on fundraiser pages", () => {
    describe("User WITHOUT ManageFundraisers (judith.matthews: AddRecords+EditRecords, ManageFundraisers=0)", () => {
        beforeEach(() => {
            cy.setupNoFinanceSession();
        });

        it("does not show Fundraiser menu items", () => {
            cy.visit("/v2/dashboard");
            cy.contains("a", "Create New Fundraiser").should("not.exist");
            cy.contains("a", "Add Donors to Buyer List").should("not.exist");
            cy.contains("a", "View Buyers").should("not.exist");
        });
    });

    describe("User WITH ManageFundraisers (tony.wade: ManageFundraisers=1, Admin=0)", () => {
        beforeEach(() => {
            cy.setupStandardSession();
        });

        READABLE_FUNDRAISER_PAGES.forEach((page) => {
            it(`allows ${page}`, () => {
                cy.visit(`/${page}`, { failOnStatusCode: false });
                cy.url().should("not.include", ACCESS_DENIED);
            });
        });

        it("shows Fundraiser menu items", () => {
            cy.visit("/v2/dashboard");
            cy.contains("a", "Create New Fundraiser").should("exist");
        });
    });
});

/**
 * Tests that the bEnabledFundraiser feature flag gates /fundraiser/ for ALL
 * users — including admins — when set to false.
 *
 * The flag is toggled via the admin config API, then restored in an after()
 * hook so subsequent specs start with the default (enabled) state.
 */
describe("bEnabledFundraiser feature flag — fundraiser module disabled", () => {
    after(() => {
        // Always restore the flag regardless of test outcome so other specs
        // that visit fundraiser pages are not broken.
        cy.setupAdminSession();
        cy.request({
            method: "POST",
            url: "/admin/api/system/config/bEnabledFundraiser",
            body: { value: "1" },
            headers: { "Content-Type": "application/json" },
            failOnStatusCode: false,
        });
    });

    it("blocks admin access to /fundraiser/ and redirects to home when disabled", () => {
        cy.setupAdminSession();

        // Disable the feature flag.
        cy.request({
            method: "POST",
            url: "/admin/api/system/config/bEnabledFundraiser",
            body: { value: "0" },
            headers: { "Content-Type": "application/json" },
        });

        // Admin should be redirected away — NOT see the fundraiser page or
        // the generic role access-denied page, and should land on the home
        // dashboard (SystemURLs::getRootPath() + '/' resolves to /v2/dashboard).
        cy.visit("/fundraiser/", { failOnStatusCode: false });
        cy.url().should("not.include", "/fundraiser/");
        cy.url().should("not.include", ACCESS_DENIED);
        cy.url().should("include", "/v2/dashboard");
    });

    it("blocks API access to /api/fundraisers when disabled (returns 403)", () => {
        cy.setupAdminSession();

        // Disable the feature flag.
        cy.request({
            method: "POST",
            url: "/admin/api/system/config/bEnabledFundraiser",
            body: { value: "0" },
            headers: { "Content-Type": "application/json" },
        });

        cy.request({
            method: "GET",
            url: "/api/fundraisers",
            failOnStatusCode: false,
        }).its("status").should("eq", 403);
    });

    it("hides Fundraisers button on Finance dashboard when disabled", () => {
        cy.setupAdminSession();

        // Disable the feature flag.
        cy.request({
            method: "POST",
            url: "/admin/api/system/config/bEnabledFundraiser",
            body: { value: "0" },
            headers: { "Content-Type": "application/json" },
        });

        cy.visit("/finance/", { failOnStatusCode: false });
        cy.contains("a", "Fundraisers").should("not.exist");
    });
});
