/// <reference types="cypress" />

/**
 * Member Portal (MP2, #9863) — the landing rule for a self-service login.
 *
 * Seed persona: user 100, Lena Black (person 100, family 20). usr_EditSelf=1 and
 * no admin flag, so User::isEditSelfExclusive() is true. usr_UserName is
 * VARCHAR(50) since #9831, so the seeded address is stored whole.
 *
 * Design: .agents/skills/churchcrm/member-portal-design.md §2.2 / §2.3 (P10).
 *   - an Edit-Self-only login lands in /portal and never sees the admin shell
 *   - /external/limited-access is retired and redirects to /portal
 *   - a legacy *.php page bounces to /portal (Include/PageInit.php)
 */
describe("Member Portal — self-service landing", () => {
    const memberUser = "lena.black.editself.notes@example.com";
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

    it("The account menu offers Sign out, which returns to the login page", () => {
        login();
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.get("#portal-account-toggle").click();
        cy.get("#portal-account-menu").contains('[role="menuitem"]', "Sign out").click();
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

    // The bar is gone for everyone (2026-09-17): the account menu's "Admin
    // Console" entry is the way back to the admin area, and a member never
    // sees that entry either.
    it("No staff bar is rendered, and the account menu has no Admin Console", () => {
        login();
        cy.url({ timeout: 10000 }).should("include", "/portal");
        cy.get(".portal-staff-bar").should("not.exist");
        cy.get("#portal-account-toggle").click();
        cy.get("#portal-account-menu").contains("Admin Console").should("not.exist");
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
    const memberUser = "lena.black.editself.notes@example.com";
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
    // Footer social links — issue #9907.
    // The footer's right-hand side is the church's own social accounts, read
    // from `church.socialLinks` (ChurchMetaData::getChurchSocialLinks()). The
    // ChurchCRM credit link that used to live there is gone for good.
    describe("Footer social links (#9907)", () => {
        const adminKey = () => Cypress.env("admin.api.key");

        const SOCIAL = [
            { config: "sChurchX", id: "x", label: "X", url: "https://x.com/seedchurch" },
            { config: "sChurchYouTube", id: "youtube", label: "YouTube", url: "https://www.youtube.com/@seedchurch" },
            { config: "sChurchFacebook", id: "facebook", label: "Facebook", url: "https://facebook.com/seedchurch" },
            { config: "sChurchInstagram", id: "instagram", label: "Instagram", url: "https://instagram.com/seedchurch" },
        ];

        const setConfig = (name, value) =>
            cy.request({
                method: "POST",
                url: `/admin/api/system/config/${name}`,
                headers: { "content-type": "application/json", "x-api-key": adminKey() },
                body: { value },
                failOnStatusCode: false,
            });

        /** The content-box right edge of the footer row, i.e. inside its padding. */
        const rowContentRight = (row) => {
            const rect = row.getBoundingClientRect();
            return rect.right - parseFloat(window.getComputedStyle(row).paddingRight);
        };

        const setAllSocial = (on) => {
            SOCIAL.forEach((network) => setConfig(network.config, on ? network.url : ""));
        };

        before(() => setAllSocial(true));

        // Leave the install as we found it — no church social links configured.
        after(() => setAllSocial(false));

        it("The footer shows one icon link per configured network, opened safely", () => {
            login();
            cy.url({ timeout: 10000 }).should("include", "/portal");

            cy.get(".portal-footer-social a").should("have.length", SOCIAL.length);

            SOCIAL.forEach((network) => {
                cy.get(`.portal-footer-social a[aria-label="${network.label}"]`)
                    .should("have.attr", "href", network.url)
                    .and("have.attr", "target", "_blank")
                    .and("have.attr", "rel", "noopener noreferrer");
            });
        });

        it("The footer no longer carries a ChurchCRM credit link", () => {
            login();
            cy.url({ timeout: 10000 }).should("include", "/portal");

            cy.get(".portal-footer").should("exist");
            cy.get(".portal-footer-credit").should("not.exist");
            // Matched on the href prefix, not a substring: the seed church's own
            // contact address is demo@churchcrm.io, and that mailto: link stays.
            cy.get('.portal-footer a[href^="https://churchcrm.io"]').should("not.exist");
        });

        it("The social links sit on the right-hand side of the footer row", () => {
            login();
            cy.url({ timeout: 10000 }).should("include", "/portal");

            // The footer row is also the portal container, so its border box is
            // wider than its content box by the container's own side padding.
            // Compare against the CONTENT edge, which is where a right-aligned
            // child actually ends.
            cy.get(".portal-footer-inner").then(($row) => {
                const contentRight = rowContentRight($row[0]);
                cy.get(".portal-footer-social").then(($social) => {
                    const socialRight = $social[0].getBoundingClientRect().right;
                    expect(contentRight - socialRight, "social links are right-aligned").to.be.lessThan(2);
                });
            });
        });

        it("The social links stay right-aligned when the row wraps on a phone", () => {
            cy.viewport(375, 812);
            login();
            cy.url({ timeout: 10000 }).should("include", "/portal");

            cy.get(".portal-footer-inner").then(($row) => {
                const contentRight = rowContentRight($row[0]);
                const churchRect = $row[0].querySelector(".portal-footer-church").getBoundingClientRect();
                cy.get(".portal-footer-social").then(($social) => {
                    const socialRect = $social[0].getBoundingClientRect();
                    expect(contentRight - socialRect.right, "still right-aligned").to.be.lessThan(2);
                    // ...and on a phone the row has wrapped, so the icons sit on
                    // their own line below the church details.
                    expect(socialRect.top, "wrapped below the church details")
                        .to.be.greaterThan(churchRect.top);
                });
            });
        });

        it("With no social links configured the footer shows neither icons nor a ChurchCRM link", () => {
            setAllSocial(false);

            login();
            cy.url({ timeout: 10000 }).should("include", "/portal");

            cy.get(".portal-footer").should("exist");
            cy.get(".portal-footer-social").should("not.exist");
            cy.get(".portal-footer-credit").should("not.exist");
            cy.get('.portal-footer a[href^="https://churchcrm.io"]').should("not.exist");

            // Restore for any test that runs after this one in the same suite.
            setAllSocial(true);
        });
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
