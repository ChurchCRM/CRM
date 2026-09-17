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
