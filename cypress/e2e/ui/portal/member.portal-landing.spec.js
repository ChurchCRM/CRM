/// <reference types="cypress" />

/**
 * Member Portal (MP2, #9863) — the landing rule for a self-service login.
 *
 * Seed persona: user 100, Lena Black (person 100, family 20). usr_EditSelf=1 and
 * no admin flag, so User::isEditSelfExclusive() is true. The username column is
 * VARCHAR(32), so the seeded address "lena.black.editself.notes@example.com" is
 * stored truncated — log in with the 32-character form.
 *
 * Design: .agents/skills/churchcrm/member-portal-design.md §2.2 / §2.3 (P10).
 *   - an Edit-Self-only login lands in /portal and never sees the admin shell
 *   - /external/limited-access is retired and redirects to /portal
 *   - a legacy *.php page bounces to /portal (Include/PageInit.php)
 */
describe("Member Portal — self-service landing", () => {
    const memberUser = "lena.black.editself.notes@exampl";
    const memberPassword = "changeme";

    const login = () => {
        cy.clearCookies();
        cy.visit("session/begin");
        cy.get("input[name=User]").type(memberUser);
        cy.get("input[name=Password]").type(memberPassword + "{enter}");
    };

    it("Login lands on /portal", () => {
        login();
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.url().should("not.include", "/external/limited-access");
        cy.url().should("not.include", "/v2/dashboard");
    });

    it("The portal home greets the member by name and shows the family name", () => {
        login();
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.get(".portal-home", { timeout: 10000 }).should("exist");
        cy.contains("Lena").should("exist");
        cy.contains("Black").should("exist");
    });

    it("No admin shell furniture is rendered anywhere in the portal", () => {
        login();
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.get("#sidebar").should("not.exist");
        cy.get("#sidebar-menu").should("not.exist");
        cy.get(".navbar-vertical").should("not.exist");
        cy.get("#fab-container").should("not.exist");
    });

    it("The portal header offers Sign out, which returns to the login page", () => {
        login();
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.contains("Sign out").click();
        cy.url({ timeout: 10000 }).should("include", "/session/begin");
    });

    it("The retired /external/limited-access URL redirects to /portal", () => {
        login();
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.visit("external/limited-access", { failOnStatusCode: false });
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.url().should("not.include", "/external/limited-access");
    });

    it("A direct visit to /v2/dashboard bounces to /portal", () => {
        login();
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.visit("v2/dashboard", { failOnStatusCode: false });
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.url().should("not.include", "/v2/dashboard");
    });

    it("A direct visit to another MVC module bounces to /portal", () => {
        login();
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.visit("people/dashboard", { failOnStatusCode: false });
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.url().should("not.include", "/people/dashboard");
    });

    it("A legacy *.php page bounces to /portal (PageInit)", () => {
        login();
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.visit("SystemSettings.php", { failOnStatusCode: false });
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.url().should("not.include", "SystemSettings.php");
    });

    it("The staff 'viewing as yourself' bar is NOT shown to a member", () => {
        login();
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.get(".portal-staff-bar").should("not.exist");
    });
});

/**
 * The home page's Profile card.
 *
 * The card used to print the sentence "Your name, email, phone numbers and
 * photo." under the member's name — a description of the information rather
 * than the information. It now shows the values themselves, taken from the same
 * `PortalSelfService::getProfile()` the Profile page renders.
 *
 * Seed persona: Lena Black (person 100), family role 2 = Spouse.
 *
 * Her email and mobile number are NOT hard-coded here:
 * member.portal-profile.spec.js edits both and does not put them back, so
 * whichever spec runs second would read stale values. The card is checked
 * against `GET /api/portal/me` — the very record it renders — which is the
 * assertion that matters anyway: the card shows the member's details rather
 * than a sentence about them.
 *
 * Birthday follows the Profile page: it is shown only when
 * `bPortalAllowBirthdayEdit` is on, so the test asserts the *agreement* between
 * the two pages rather than hard-coding one of the states.
 */
describe("Member Portal — the home page's Profile card", () => {
    const memberUser = "lena.black.editself.notes@exampl";
    const memberPassword = "changeme";

    const OLD_DESCRIPTION = "Your name, email, phone numbers and photo.";

    const login = () => {
        cy.clearCookies();
        cy.visit("/session/begin");
        cy.get("input[name=User]").type(memberUser);
        cy.get("input[name=Password]").type(memberPassword + "{enter}");
        cy.url({ timeout: 10000 }).should("include", "/portal");
    };

    beforeEach(() => {
        login();
        cy.visit("/portal/");
    });

    it("shows the member's name and their real email and mobile number", () => {
        cy.get("#portal-home-profile-card", { timeout: 10000 }).should("contain.text", "Lena");
        cy.request("/api/portal/me").then(({ body }) => {
            const me = body.profile;
            expect(me.email, "the seed gives Lena an email address").to.contain("@");
            expect(me.cellPhone, "the seed gives Lena a mobile number").to.not.be.empty;
            cy.get("#portal-home-profile-card [data-field=email]").should("contain.text", me.email);
            cy.get("#portal-home-profile-card [data-field=cellPhone]").should(
                "contain.text",
                me.cellPhone
            );
        });
    });

    it("shows the home phone and the family role too", () => {
        cy.request("/api/portal/me").then(({ body }) => {
            const me = body.profile;
            cy.get("#portal-home-profile-card [data-field=homePhone]").should(
                "contain.text",
                me.homePhone
            );
        });
        cy.get("#portal-home-profile-card [data-field=familyRole]").should("contain.text", "Spouse");
    });

    it("no longer describes the information instead of showing it", () => {
        cy.get("#portal-home-profile-card").should("not.contain.text", OLD_DESCRIPTION);
        cy.contains(OLD_DESCRIPTION).should("not.exist");
    });

    it("keeps the 'Update your details' link to the Profile page", () => {
        cy.get("#portal-home-profile-card")
            .contains("Update your details")
            .should("have.attr", "href")
            .and("match", /\/portal\/profile$/);
    });

    it("shows the birthday exactly when the Profile page does", () => {
        cy.get("#portal-home-profile-card").then(($card) => {
            const onHome = $card.find("[data-field=birthday]").length > 0;
            cy.visit("/portal/profile");
            cy.get("#portal-profile-details").then(($details) => {
                const onProfile = $details.find("[data-field=birthday]").length > 0;
                expect(onHome, "birthday on the home card matches the Profile page").to.eq(onProfile);
            });
        });
    });
});
