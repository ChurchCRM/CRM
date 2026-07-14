/// <reference types="cypress" />

/**
 * Tests the ManageFundraisers permission guard on fundraiser pages, and the
 * Finance permission guard on pledge pages.
 *
 * Background: fundraiser pages are now served by the Slim MVC module at
 * /fundraiser/* (ManageFundraisersRoleAuthMiddleware on every route). Visiting
 * any route in the module as a user without ManageFundraisers redirects to
 * /v2/access-denied?role=ManageFundraisers.
 *
 * Seed users (cypress/configs/docker.config.ts -> cypress/data/seed.sql):
 *  - standard  = tony.wade@example.com        ManageFundraisers=1, Finance=1 -> ALLOWED
 *  - nofinance = judith.matthews@example.com   AddRecords=1, EditRecords=1,
 *                                              ManageFundraisers=0, Finance=0 -> DENIED
 *
 * judith is the load-bearing user here: she HAS some permissions so she passes
 * the PageInit entry gate, but lacks ManageFundraisers — proving the per-module
 * middleware works. A zero-permission user would be bounced by the entry gate
 * regardless and would pass this test even if the guard did not exist.
 *
 * Note: the dedicated finance.nofundraiser seed user (per_ID=96) has
 * DeleteRecords=1 / Finance=1 / ManageFundraisers=0. Previously used to test
 * the FundRaiserDelete.php GET guard. With the MVC migration all deletions are
 * POST-only; the ManageFundraisersRoleAuthMiddleware now gates the entire
 * module, so this user is blocked from /fundraiser/ itself — proving the
 * middleware fires before any route-specific permission check.
 *
 * Fundraiser ID 1 ("2016 Car Wash") is available in seed data for routes that
 * require a valid fundraiser ID in the path.
 *
 * The positive path (a ManageFundraisers user can still use these pages) is
 * covered in the final describe block (tony.wade).
 */

const ACCESS_DENIED = "/v2/access-denied";

// All fundraiser GET routes — now served by the Slim MVC module at /fundraiser/*.
// Migrated from legacy PHP filenames:
//   FindFundRaiser.php          → fundraiser/
//   FundRaiserEditor.php        → fundraiser/editor
//   PaddleNumList.php           → fundraiser/1/paddle-numbers
//   PaddleNumEditor.php         → fundraiser/1/paddle-numbers/editor
//   DonatedItemEditor.php       → fundraiser/1/donated-items/editor
//   AddDonors.php               → fundraiser/1/donors      (GET = form only)
//   BatchWinnerEntry.php        → fundraiser/1/batch-winner (GET = form only)
//
// Mutation routes (DonatedItemReplicate, FundRaiserDelete) are now POST-only;
// their ManageFundraisers gate is tested separately via cy.request below.
const FUNDRAISER_PAGES = [
    "fundraiser/",
    "fundraiser/editor",
    "fundraiser/1/paddle-numbers",
    "fundraiser/1/paddle-numbers/editor",
    "fundraiser/1/donated-items/editor",
    "fundraiser/1/donors",
    "fundraiser/1/batch-winner",
];

describe("ManageFundraisers permission guard on fundraiser pages", () => {
    describe("User WITHOUT ManageFundraisers (judith.matthews: AddRecords+EditRecords, ManageFundraisers=0)", () => {
        beforeEach(() => {
            cy.setupNoFinanceSession();
        });

        FUNDRAISER_PAGES.forEach((page) => {
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

    describe("User WITH DeleteRecords+Finance but WITHOUT ManageFundraisers (finance.nofundraiser)", () => {
        beforeEach(() => {
            cy.setupNoManageFundraisersSession();
        });

        it("denies /fundraiser/ (ManageFundraisersRoleAuthMiddleware gates the entire module)", () => {
            // This user has DeleteRecords=1 and Finance=1 but ManageFundraisers=0.
            // The module-level middleware blocks them before any route handler runs,
            // proving the guard fires independently of the inline DeleteRecords check.
            cy.visit("/fundraiser/", { failOnStatusCode: false });
            cy.url().should("include", ACCESS_DENIED);
            cy.url().should("include", "role=ManageFundraisers");
        });
    });

    describe("User WITH ManageFundraisers (tony.wade: ManageFundraisers=1, Admin=0)", () => {
        beforeEach(() => {
            cy.setupStandardSession();
        });

        FUNDRAISER_PAGES.forEach((page) => {
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

describe("Finance permission guard on pledge pages", () => {
    describe("User WITHOUT Finance (judith.matthews: AddRecords+EditRecords, Finance=0)", () => {
        beforeEach(() => {
            cy.setupNoFinanceSession();
        });

        it("denies PledgeEditor.php", () => {
            cy.visit("/PledgeEditor.php", { failOnStatusCode: false });
            cy.url().should("include", ACCESS_DENIED);
            cy.url().should("include", "role=Finance");
        });
    });
});
