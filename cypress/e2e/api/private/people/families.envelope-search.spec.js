/// <reference types="cypress" />

describe("Family search by envelope number (issue #9502)", () => {
    const FAMILY_ID = 5;
    const ENVELOPE = 9502;
    const search = (call) => call("GET", `/api/families/search/${ENVELOPE}`, null, 200);
    let originalSetting;

    before(() => {
        cy.getSystemConfig("bUseDonationEnvelopes").then((v) => {
            originalSetting = v;
        });
        cy.dbQuery("UPDATE family_fam SET fam_Envelope = ? WHERE fam_ID = ?", [ENVELOPE, FAMILY_ID]);
    });

    after(() => {
        cy.dbQuery("UPDATE family_fam SET fam_Envelope = 0 WHERE fam_ID = ?", [FAMILY_ID]);
        cy.restoreSystemConfig("bUseDonationEnvelopes", originalSetting);
    });

    it("does not match envelope numbers when envelopes are disabled", () => {
        cy.makePrivateAdminAPICall("POST", "admin/api/system/config/bUseDonationEnvelopes", { value: "" }, 200);
        search(cy.makePrivateFinanceOnlyAPICall).its("body.Families").should("have.length", 0);
    });

    context("with envelopes enabled", () => {
        before(() => {
            cy.makePrivateAdminAPICall("POST", "admin/api/system/config/bUseDonationEnvelopes", { value: "1" }, 200);
        });

        it("finds the family by envelope number for a Finance user", () => {
            search(cy.makePrivateFinanceOnlyAPICall).then((response) => {
                expect(response.body.Families).to.have.length(1);
                expect(response.body.Families[0].Id).to.equal(FAMILY_ID);
                expect(response.body.Families[0].displayName).to.contain(`#${ENVELOPE}`);
            });
        });

        it("does not expose envelope numbers to a user without Finance", () => {
            search(cy.makePrivateNoFinanceAPICall).its("body.Families").should("have.length", 0);
        });

        it("still finds families by name without an envelope suffix", () => {
            cy.makePrivateFinanceOnlyAPICall("GET", "/api/families/search/Smith", null, 200).then((response) => {
                expect(response.body.Families).to.not.be.empty;
                response.body.Families.forEach((f) => expect(f.displayName).to.not.contain("Envelope"));
            });
        });
    });
});
