/// <reference types="cypress" />

/**
 * Tests the Finance permission guard on the fundraiser and pledge pages.
 *
 * Background: these pages previously had NO top-of-file access guard. The only
 * thing keeping users off them was the coarse User::isEditSelfExclusive() entry gate
 * in PageInit.php, which now only blocks EditSelf-exclusive users. Any
 * user with a single unrelated permission (e.g. AddRecords) cleared that gate and
 * reached fundraiser, donor, paddle-number and pledge data. They are now gated on
 * isFinanceEnabled(), which redirects to /v2/access-denied?role=Finance.
 *
 * Finance is an interim stand-in until a dedicated fundraiser permission exists.
 *
 * Seed users (cypress/configs/docker.config.ts -> cypress/data/seed.sql):
 *  - standard  = tony.wade@example.com       usr_Finance=1, usr_Admin=0  -> ALLOWED
 *  - nofinance = judith.matthews@example.com usr_AddRecords=1, usr_EditRecords=1,
 *                                            usr_Finance=0, usr_Admin=0  -> DENIED
 *
 * judith is the load-bearing user here: she HAS permissions, so she passes the
 * PageInit entry gate. She is the only seeded user who can prove the per-page
 * Finance guard works. noperm.user would be bounced by the entry gate regardless,
 * and would pass this test even if the guard did not exist.
 *
 * The positive path (a Finance user can still use these pages) is additionally
 * covered by cypress/e2e/ui/fundraiser/*.spec.js, which run as tony.wade.
 */

const ACCESS_DENIED = "/v2/access-denied";

// Pages that render a form/list on GET without mutating anything.
// Safe to load as an allowed user in the positive test.
const READABLE_PAGES = [
    "FindFundRaiser.php",
    "PaddleNumList.php",
    "FundRaiserEditor.php?FundRaiserID=-1",
    "DonatedItemEditor.php",
    "PaddleNumEditor.php",
];

// Pages whose GET handler performs writes or a destructive action. These are only
// exercised in the negative test — visiting them as a Finance user would mutate
// seed data, so the positive path is never asserted against them.
const MUTATING_PAGES = [
    "AddDonors.php?FundRaiserID=1",
    "BatchWinnerEntry.php?CurrentFundraiser=1",
    "DonatedItemReplicate.php?DonatedItemID=1&Count=1",
    "FundRaiserDelete.php?FundRaiserID=999999",
];

const ALL_GUARDED_PAGES = [...READABLE_PAGES, ...MUTATING_PAGES, "PledgeEditor.php"];

describe("Finance permission guard on fundraiser and pledge pages", () => {
    describe("User WITHOUT Finance (judith.matthews: AddRecords+EditRecords, Finance=0)", () => {
        beforeEach(() => {
            cy.setupNoFinanceSession();
        });

        ALL_GUARDED_PAGES.forEach((page) => {
            it(`denies ${page}`, () => {
                cy.visit(`/${page}`, { failOnStatusCode: false });
                cy.url().should("include", ACCESS_DENIED);
                cy.url().should("include", "role=Finance");
            });
        });

        it("does not show Fundraiser menu items", () => {
            cy.visit("/v2/dashboard");
            cy.contains("a", "Create New Fundraiser").should("not.exist");
            cy.contains("a", "Add Donors to Buyer List").should("not.exist");
            cy.contains("a", "View Buyers").should("not.exist");
        });
    });

    describe("User WITH Finance (tony.wade: Finance=1, Admin=0)", () => {
        beforeEach(() => {
            cy.setupStandardSession();
        });

        READABLE_PAGES.forEach((page) => {
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
