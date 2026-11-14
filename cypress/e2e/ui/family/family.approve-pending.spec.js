/// <reference types="cypress" />

import { restoreSettings, setConfig, useMemberSignIn } from "../../../support/member-sign-in";

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

const UNASSIGNED = 0;

describe("Approving a pending self-registration from the family page", () => {
    const familyIds = [];
    const saved = {};
    let savedSelfReg;

    const registerFamily = (label) => {
        const address = `ada.${label}.${Date.now()}@example.com`;
        return cy
            .request({
                method: "POST",
                url: "/api/public/register/family",
                body: {
                    Name: `CypressApprove ${label} ${Date.now()}`,
                    Address1: "1 Approve Way",
                    City: "Testville",
                    State: "TS",
                    Country: "US",
                    Zip: "00000",
                    people: [{ firstName: "Ada", lastName: "Approve", gender: 2, role: 1, email: address }],
                },
            })
            .then((response) => {
                familyIds.push(response.body.Id);
                return { familyId: response.body.Id, address };
            });
    };

    const approve = (familyId) => {
        cy.intercept("POST", `**/api/family/${familyId}/approve-review`).as("approve");
        cy.visit(`/people/family/${familyId}`);
        cy.get("#approveFamily").click();
        return cy.wait("@approve").its("response.body");
    };

    const expectNoMailTo = (address) =>
        mailpit("messages?limit=200").then((resp) => {
            expect(resp.body.messages.filter((m) => m.To.some((to) => to.Address === address))).to.have.length(0);
        });

    before(() => {
        cy.getSystemConfig("bEnableSelfRegistration").then((value) => {
            savedSelfReg = value;
        });
        cy.makePrivateAdminAPICall("POST", "admin/api/system/config/bEnableSelfRegistration", { value: "1" });
        useMemberSignIn(saved, { sMemberSignInClassifications: `${UNASSIGNED},1,2` });
    });

    after(() => {
        cy.cleanupFamilies(familyIds);
        restoreSettings(saved);
        cy.restoreSystemConfig("bEnableSelfRegistration", savedSelfReg);
    });

    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("approves with one click, the banner goes away and the family is invited to the portal", () => {
        registerFamily("invited").then(({ familyId, address }) => {
            mailpit("messages?limit=200").then((resp) => {
                const knownIds = resp.body.messages.map((m) => m.ID);
                approve(familyId).then((body) => {
                    expect(body.invited).to.eq(true);
                    expect(body).to.not.have.property("cannotSignIn");
                });
                cy.get("#approveFamily").should("not.exist");
                newMessageTo(address, knownIds).then((message) =>
                    mailpit(`message/${message.ID}`).then((full) => {
                        expect(full.body.HTML).to.include("/portal/family");
                        expect(full.body.Attachments, "attachments").to.have.length(0);
                    }),
                );
            });
            cy.visit(`/people/family/${familyId}`);
            cy.contains("#family-verification-card li", "Last invited").should("not.contain", "Never");
            cy.makePrivateAdminAPICall("GET", `/api/family/${familyId}`, null, 200).its("body.NeedsReview").should("not.eq", true);
        });
    });

    it("tells staff why nobody was invited when the sign-in rule leaves the whole family out", () => {
        setConfig("sMemberSignInClassifications", "1,2");
        registerFamily("ruled-out").then(({ familyId, address }) => {
            approve(familyId).then((body) => {
                expect(body.invited).to.eq(false);
                expect(body.cannotSignIn.members).to.deep.eq([{ name: "Ada Approve", reason: "Their classification may not sign in" }]);
            });
            cy.get(".bootbox")
                .should("contain", "no invitation was sent")
                .and("contain", "under the current sign-in rule")
                .and("contain", "Ada Approve: Their classification may not sign in");
            expectNoMailTo(address);
            cy.get(".bootbox .btn-primary").click();
            cy.get("#approveFamily").should("not.exist");
            cy.contains("#family-verification-card li", "Last invited").should("contain", "Never");
        });
        setConfig("sMemberSignInClassifications", `${UNASSIGNED},1,2`);
    });

    it("tells staff nobody was invited while member sign-in is off", () => {
        setConfig("bMemberSignIn", "0");
        registerFamily("sign-in-off").then(({ familyId, address }) => {
            approve(familyId).then((body) => {
                expect(body.invited).to.eq(false);
                expect(body.cannotSignIn.members).to.have.length(0);
                expect(body.cannotSignIn.message).to.include("Member sign-in is off");
            });
            cy.get(".bootbox").should("contain", "Member sign-in is off");
            expectNoMailTo(address);
        });
        setConfig("bMemberSignIn", "1");
    });
});
