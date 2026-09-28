/// <reference types="cypress" />

/**
 * Tests the ManageFundraisers permission guard on fundraiser pages, and the
 * Finance permission guard on pledge pages.
 *
 * Background: these pages previously had NO top-of-file access guard. They are
 * now gated on isManageFundraisersEnabled() (fundraiser pages) or
 * isFinanceEnabled() (pledge pages), which redirect to
 * /v2/access-denied?role=ManageFundraisers or /v2/access-denied?role=Finance.
 *
 * Seed users (cypress/configs/docker.config.ts -> cypress/data/seed.sql):
 *  - standard  = tony.wade@example.com        ManageFundraisers=1, Finance=1 -> ALLOWED
 *  - nofinance = judith.matthews@example.com   AddRecords=1, EditRecords=1,
 *                                              ManageFundraisers=0, Finance=0 -> DENIED
 *
 * judith is the load-bearing user here: she HAS some permissions so she passes
 * the PageInit entry gate, but lacks ManageFundraisers — proving the per-page
 * guard works. A zero-permission user would be bounced by the entry gate
 * regardless and would pass this test even if the guard did not exist.
 *
 * Note: FundRaiserDelete.php requires BOTH DeleteRecords AND ManageFundraisers.
 * judith lacks DeleteRecords, so she is bounced before the ManageFundraisers
 * check. A dedicated seed user (per_ID=96: finance.nofundraiser) with
 * DeleteRecords=1 / Finance=1 / ManageFundraisers=0 is used to cover that gate.
 *
 * The positive path (a ManageFundraisers user can still use these pages) is
 * additionally covered by cypress/e2e/ui/fundraiser/*.spec.js (tony.wade).
 */

const ACCESS_DENIED = "/v2/access-denied";

// Fundraiser pages gated on isManageFundraisersEnabled().
// Safe to load as an allowed user in the positive test.
const READABLE_FUNDRAISER_PAGES = [
    "fundraiser/",
    "fundraiser/editor",
    "fundraiser/1/paddle-numbers",
    "fundraiser/1/donated-items/editor",
    "fundraiser/1/paddle-numbers/editor",
];

// Fundraiser pages whose GET handler renders a form (non-mutating in MVC).
// Only tested in the negative path — the old legacy equivalents were mutating.
const MUTATING_FUNDRAISER_PAGES = [
    "fundraiser/1/donors",
    "fundraiser/1/batch-winner",
    "fundraiser/1/reports/bid-sheets",
];

const ALL_FUNDRAISER_PAGES = [
    ...READABLE_FUNDRAISER_PAGES,
    ...MUTATING_FUNDRAISER_PAGES,
];

describe("ManageFundraisers permission guard on fundraiser pages", () => {
    describe("User WITHOUT ManageFundraisers (judith.matthews: AddRecords+EditRecords, ManageFundraisers=0)", () => {
        beforeEach(() => {
            cy.setupNoFinanceSession();
        });

        ALL_FUNDRAISER_PAGES.forEach((page) => {
            it(`denies ${page}`, () => {
                cy.visit(`/${page}`, { failOnStatusCode: false });
                cy.url().should("include", ACCESS_DENIED);
                cy.url().should("include", "role=ManageFundraisers");
            });
        });

        it("does not show Fundraiser menu items", () => {
            cy.visit("/v2/dashboard");
            cy.contains("a", "Create New Fundraiser").should("not.exist");
            cy.contains("a", "Add Donors to Buyer List").should("not.exist");
            cy.contains("a", "View Buyers").should("not.exist");
        });
    });

    describe("User WITH DeleteRecords but WITHOUT ManageFundraisers (finance.nofundraiser: DeleteRecords=1, Finance=1, ManageFundraisers=0)", () => {
        beforeEach(() => {
            cy.setupNoManageFundraisersSession();
        });

        it("denies fundraiser/", () => {
            // finance.nofundraiser (per_ID=96) has DeleteRecords=1 and Finance=1
            // but ManageFundraisers=0. Visiting any /fundraiser/* GET route is
            // blocked by the module-level ManageFundraisersRoleAuthMiddleware
            // before any route handler runs, confirming the module gate enforces
            // the ManageFundraisers permission regardless of other grants.
            cy.visit("/fundraiser/", { failOnStatusCode: false });
            cy.url().should("include", ACCESS_DENIED);
            cy.url().should("include", "role=ManageFundraisers");
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

describe("Finance permission guard on pledge pages", () => {
    describe("User WITHOUT Finance (judith.matthews: AddRecords+EditRecords, Finance=0)", () => {
        beforeEach(() => {
            cy.setupNoFinanceSession();
        });

        // MVC pledge/payment editor (see #8482) — FinanceRoleAuthMiddleware
        // wraps the whole /finance app, so /finance/pledge/new must be denied.
        it("denies /finance/pledge/new", () => {
            cy.visit("/finance/pledge/new", { failOnStatusCode: false });
            cy.url().should("include", ACCESS_DENIED);
            cy.url().should("include", "role=Finance");
        });
    });
});
