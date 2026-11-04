/// <reference types="cypress" />

import { setConfig } from "../../support/member-sign-in";

/**
 * The family page shows, under each member's email, whether they may sign in to
 * the Member Portal and why not (member sign-in design §11.3). Family 12 is the
 * Olsons: Jon (57) is the head of household and Sandra (58) the spouse. Only the
 * head role may sign in here, so Jon may and Sandra may not.
 */

const SETTINGS_URL = "/admin/api/member-portal/sign-in";
const RULE = { sMemberSignInFamilyRoles: [1], sMemberSignInClassifications: [1, 2], iMemberSignInMinAge: 13 };
const FAMILY = 12;

describe("Family page: who may sign in to the Member Portal", () => {
    const saved = {};

    before(() => {
        cy.makePrivateAdminAPICall("GET", SETTINGS_URL, null, 200).then(({ body }) => {
            Object.keys(RULE).forEach((key) => {
                saved.rule = { ...saved.rule, [key]: body.values[key] };
            });
        });
        cy.getSystemConfig("bMemberSignIn").then((value) => {
            saved.memberSignIn = value;
        });
        cy.makePrivateAdminAPICall("POST", SETTINGS_URL, RULE, 200);
        setConfig("bMemberSignIn", "1");
    });

    after(() => {
        cy.makePrivateAdminAPICall("POST", SETTINGS_URL, saved.rule, 200);
        cy.restoreSystemConfig("bMemberSignIn", saved.memberSignIn);
    });

    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("says a member may sign in", () => {
        cy.visit(`/people/family/${FAMILY}`);
        cy.contains("tr", "Jon").find('[data-portal-signin-status="yes"]').should("contain", "Can sign in to the Member Portal");
    });

    it("says why a member may not, linking to their sign-in card", () => {
        cy.visit(`/people/family/${FAMILY}`);
        cy.contains("tr", "Sandra")
            .find('[data-portal-signin-status="no"]')
            .should("contain", "Cannot sign in")
            .and("contain", "Their family role may not sign in")
            .find("a")
            .should("have.attr", "href")
            .and("include", "/people/view/58");
    });

    it("shows nothing while member sign-in is off", () => {
        setConfig("bMemberSignIn", "0");
        cy.visit(`/people/family/${FAMILY}`);
        cy.get("[data-portal-signin-status]").should("not.exist");
        setConfig("bMemberSignIn", "1");
    });

    it("shows nothing to staff without Edit Records", () => {
        cy.setupPersonaSession("zero-permission-staff");
        cy.visit(`/people/family/${FAMILY}`);
        cy.contains("Jon");
        cy.get("[data-portal-signin-status]").should("not.exist");
    });
});
