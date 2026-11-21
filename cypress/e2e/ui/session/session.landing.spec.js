/// <reference types="cypress" />

import { restoreSettings, setConfig, useMemberSignIn } from "../../../support/member-sign-in";

/**
 * The signed-out chooser at /session/ (#11844). Doors follow the install's
 * member sign-in and self-registration settings. One door redirects.
 */

const saved = {};

const doors = () => cy.get("#sessionDoorMember, #sessionDoorRegister, #sessionDoorStaff");

describe("Session landing", () => {
    before(() => {
        cy.rememberTestEnv(["admin.username", "admin.password", "standard.username", "standard.password"]);
        cy.getSystemConfig("bEnableSelfRegistration").then((value) => {
            saved.selfRegistrationEnabled = value;
        });
        useMemberSignIn(saved);
    });

    beforeEach(() => {
        cy.clearCookies();
    });

    after(() => {
        restoreSettings(saved);
        cy.restoreSystemConfig("bEnableSelfRegistration", saved.selfRegistrationEnabled);
    });

    it("shows the member door as primary, plus registration and staff, when both settings are on", () => {
        setConfig("bMemberSignIn", "1");
        setConfig("bEnableSelfRegistration", "1");
        cy.visit("/session/");
        cy.get("#sessionDoorMember").should("have.class", "login-door-primary").and("have.attr", "href").and("include", "/portal/login");
        cy.get("#sessionDoorMember").should("contain", "Congregation login");
        cy.get("#sessionDoorRegister").should("have.class", "login-door").and("not.have.class", "login-door-primary").and("have.attr", "href").and("include", "/portal/register");
        cy.get("#sessionDoorRegister").should("contain", "Register your family to our church");
        cy.get("#sessionDoorStaff").should("have.class", "login-door").and("not.have.class", "login-door-primary").and("have.attr", "href").and("match", /\/session\/begin$/);
        cy.get("#sessionDoorStaff").should("contain", "Staff login");
        doors().should("have.length", 3);

        cy.visit("/");
        cy.location("pathname").should("match", /\/session\/$/);
    });

    it("shows the member and staff doors when registration is off", () => {
        setConfig("bMemberSignIn", "1");
        setConfig("bEnableSelfRegistration", "0");
        cy.visit("/session/");
        cy.get("#sessionDoorMember").should("have.class", "login-door-primary");
        cy.get("#sessionDoorRegister").should("not.exist");
        cy.get("#sessionDoorStaff").should("exist");
        doors().should("have.length", 2);
    });

    it("makes family registration the primary door when member sign-in is off", () => {
        setConfig("bMemberSignIn", "0");
        setConfig("bEnableSelfRegistration", "1");
        cy.visit("/session/");
        cy.get("#sessionDoorMember").should("not.exist");
        cy.get("#sessionDoorRegister").should("have.class", "login-door-primary");
        cy.get("#sessionDoorStaff").should("not.have.class", "login-door-primary");
        doors().should("have.length", 2);
    });

    it("sends /session/ and the web root straight to staff sign-in when both settings are off", () => {
        setConfig("bMemberSignIn", "0");
        setConfig("bEnableSelfRegistration", "0");
        cy.visit("/session/");
        cy.location("pathname").should("match", /\/session\/begin$/);
        cy.get("#UserBox").should("be.visible");
        cy.visit("/");
        cy.location("pathname").should("match", /\/session\/begin$/);
        cy.get("#UserBox").should("be.visible");
    });

    it("sends a signed-in staff member to their dashboard", () => {
        setConfig("bMemberSignIn", "1");
        setConfig("bEnableSelfRegistration", "1");
        const standard = { username: Cypress.testEnv("standard.username"), password: Cypress.testEnv("standard.password") };
        cy.request({
            method: "POST",
            url: "/session/begin",
            form: true,
            body: { User: standard.username, Password: standard.password },
            followRedirect: false,
        }).then((response) => {
            expect(response.status).to.eq(302);
            const landed = new URL(response.headers.location, Cypress.config("baseUrl")).pathname;
            cy.visit("/session/");
            cy.location("pathname").should("eq", landed);
            cy.get("#UserBox").should("not.exist");
        });
    });

    it("sends a portal session to the portal", () => {
        cy.task("mail:available").its("available").should("eq", true);
        cy.signInAsMember(4);
        cy.visit("/session/");
        cy.location("pathname").should("match", /\/portal\/$/);
    });
});
