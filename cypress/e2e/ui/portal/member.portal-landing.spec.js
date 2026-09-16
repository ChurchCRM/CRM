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
 * The nav is feature-gated, one entry at a time (design §5). MP6 (#9867) added
 * the first gated entry: Volunteering, shown only while the Volunteer v2 rollout
 * flag includes V2.
 */
describe("Member Portal — the navigation follows the enabled features", () => {
    // Its own copy: the landing describe's helper is scoped to that block.
    const login = () => {
        cy.clearCookies();
        cy.visit("session/begin");
        cy.get("input[name=User]").type("lena.black.editself.notes@exampl");
        cy.get("input[name=Password]").type("changeme{enter}");
    };

    const setVersion = (value) =>
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/system/config/sVolunteerVersion",
            { value },
            200,
        );

    after(() => {
        setVersion("v1");
    });

    it("hides Volunteering while the volunteer rollout flag is v1", () => {
        setVersion("v1");
        login();
        cy.url({ timeout: 10000 }).should("include", "/portal");

        cy.get("#portal-nav").should("exist");
        cy.get("#portal-nav").find("a[href$='/portal/volunteer/schedule']").should("not.exist");
        // …and the page itself is not there to be reached by URL either.
        cy.visit("portal/volunteer/schedule", { failOnStatusCode: false });
        cy.get("#volunteer-my-schedule").should("not.exist");
    });

    it("shows Volunteering once the volunteer rollout flag is v2", () => {
        setVersion("v2");
        login();
        cy.url({ timeout: 10000 }).should("include", "/portal");

        cy.get("#portal-nav")
            .find("a[href$='/portal/volunteer/schedule']")
            .should("exist")
            .and("contain", "Volunteering");

        // The home page's volunteering card is real, not a "Coming soon" tile.
        cy.get("#portal-volunteering-card").should("exist");
        cy.get("#portal-volunteering-card .portal-placeholder-badge").should("not.exist");
    });

    it("Home is always there, and is the active entry on /portal", () => {
        login();
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.get("#portal-nav .portal-nav-link.is-active").should("contain", "Home");
    });
});
