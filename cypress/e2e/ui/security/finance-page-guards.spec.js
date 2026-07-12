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
    "FindFundRaiser.php",
    "PaddleNumList.php",
    "FundRaiserEditor.php?FundRaiserID=-1",
    "DonatedItemEditor.php",
    "PaddleNumEditor.php",
];

// Fundraiser pages whose GET handler mutates state. Only tested in the negative
// path — visiting them as tony.wade would corrupt seed data.
const MUTATING_FUNDRAISER_PAGES = [
    "AddDonors.php?FundRaiserID=1",
    "BatchWinnerEntry.php?CurrentFundraiser=1",
    "DonatedItemReplicate.php?DonatedItemID=1&Count=1",
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

        it("denies FundRaiserDelete.php?FundRaiserID=999999", () => {
            // This user passes the DeleteRecords gate and is blocked by the
            // ManageFundraisers gate — proving the second guard is enforced.
            cy.visit("/FundRaiserDelete.php?FundRaiserID=999999", { failOnStatusCode: false });
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
