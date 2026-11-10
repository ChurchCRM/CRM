/// <reference types="cypress" />

const mailpit = (path) => cy.request({ url: `${Cypress.mailpitUrl()}/api/v1/${path}` });

const newMessageTo = (address, knownIds, attemptsLeft = 20) =>
    mailpit("messages?limit=200").then((resp) => {
        const found = resp.body.messages.find((m) => !knownIds.includes(m.ID) && m.To.some((to) => to.Address === address));
        if (found) {
            return found;
        }
        expect(attemptsLeft, `a message to ${address} arrived`).to.be.greaterThan(0);
        cy.wait(500);
        return newMessageTo(address, knownIds, attemptsLeft - 1);
    });

describe("Approving a pending self-registration from the family page", () => {
    const familyIds = [];
    let familyId;
    let savedSelfReg;
    const address = `ada.approve.${Date.now()}@example.com`;

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
                people: [{ firstName: "Ada", lastName: "Approve", gender: 2, role: 1, email: address }],
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

    it("approves with one click, the banner goes away and the family is invited to the portal", () => {
        cy.setupAdminSession();
        cy.visit(`people/family/${familyId}`);
        mailpit("messages?limit=200").then((resp) => {
            const knownIds = resp.body.messages.map((m) => m.ID);
            cy.get("#approveFamily").click();
            cy.get("#approveFamily").should("not.exist");
            newMessageTo(address, knownIds).then((message) =>
                mailpit(`message/${message.ID}`).then((full) => {
                    expect(full.body.HTML).to.include("/portal/family");
                    expect(full.body.Attachments, "attachments").to.have.length(0);
                }),
            );
        });
        cy.visit(`people/family/${familyId}`);
        cy.get("#family-verification-card").should("contain", "Last invited").and("not.contain", "Last invited: Never");
        cy.makePrivateAdminAPICall("GET", `/api/family/${familyId}`, null, 200).its("body.NeedsReview").should("not.eq", true);
    });
});
