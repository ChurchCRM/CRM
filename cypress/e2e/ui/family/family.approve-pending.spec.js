/// <reference types="cypress" />

describe("Approving a pending self-registration from the family page", () => {
    const familyIds = [];
    let familyId;
    let savedSelfReg;

    before(() => {
        cy.getSystemConfig("bEnableSelfRegistration").then((value) => {
            savedSelfReg = value;
        });
        cy.makePrivateAdminAPICall("POST", "admin/api/system/config/bEnableSelfRegistration", { value: "1" });
        cy.request({
            method: "POST",
            url: "/api/public/register/family",
            body: {
                Name: `CypressApprove ${Date.now()}`,
                Address1: "1 Approve Way",
                City: "Testville",
                State: "TS",
                Country: "US",
                Zip: "00000",
                people: [{ firstName: "Ada", lastName: "Approve", gender: 2, role: 1, email: "" }],
            },
        }).then((response) => {
            familyId = response.body.Id;
            familyIds.push(familyId);
        });
    });

    after(() => {
        cy.cleanupFamilies(familyIds);
        cy.restoreSystemConfig("bEnableSelfRegistration", savedSelfReg);
    });

    it("approves with one click and the pending banner goes away", () => {
        cy.setupAdminSession();
        cy.visit(`people/family/${familyId}`);
        cy.get("#approveFamily").click();
        cy.get("#approveFamily").should("not.exist");
        cy.makePrivateAdminAPICall("GET", `/api/family/${familyId}`, null, 200).its("body.NeedsReview").should("not.eq", true);
    });
});
