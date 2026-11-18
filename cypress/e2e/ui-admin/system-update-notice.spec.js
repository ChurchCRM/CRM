/// <reference types="cypress" />

// System update notice for all staff (#11599).
//
// Why these tests do not assert the banner itself: the notice is computed
// server-side at login from the live GitHub release feed
// (AuthenticationManager::checkSystemUpdates ->
// ChurchCRMReleaseManager::checkSystemUpdateAvailable). CI always runs the
// in-development version (e.g. 7.8.5 while 7.8.4 is the latest release), and
// the release manager explicitly reports "no upgrade available" for a
// development version that is at or ahead of the newest release
// (getNextReleaseStep returns null). The banner therefore legitimately never
// renders in CI, and cy.intercept cannot stub a server-side login-time check.
// What CI can pin down deterministically is covered here: the staff releases
// page renders exactly what the upgrade preview API reports, staff never see
// upgrade controls, and the session-dismissal endpoint accepts a CSRF-checked
// POST and keeps the notice gone for the rest of the session. Banner
// presence, the admin/staff wording, and its return on the next login are
// verified on a behind-version install (release walkthrough), not in CI.
//
// These are full browser/session checks: do not use setupAdminSession /
// setupStandardSession here, because the shared login helper intentionally
// dismisses notifications.
const login = (role) => {
    cy.readEnv(`${role}.username`).then((username) => {
        cy.readEnv(`${role}.password`).then((password) => {
            cy.visit("/session/begin");
            cy.get("input[name=User]").type(username);
            cy.get("input[name=Password]").type(password + "{enter}");
            cy.expectStaffSignedIn();
        });
    });
};

// CSRF tokens are session-wide (CSRFUtils), so any token rendered on a page
// in this session validates against the v2 CSRF middleware. The admin users
// table always renders one; the dashboard and header only render one inside
// conditional notices.
const csrfFrom = (html) => {
    const match = html.match(/data-csrf-token="([^"]+)"/);
    expect(match, "CSRF token on the page").to.not.eq(null);
    return match[1];
};

// The releases page is server-rendered from the same preview payload the
// admin wizard uses, newest first. Assert the page matches the payload
// exactly: in CI the upgrade path is empty (up-to-date state); on a
// behind-version install every release block, its notes and its changelog
// link are rendered.
const expectReleasesPageToMatchPreview = (preview) => {
    const expectedVersions = [...(preview.upgradePath ?? [])].map((release) => release.version).reverse();
    cy.visit("/v2/releases");
    cy.get("#releases-page").should("be.visible");
    cy.contains(`Installed version: ${preview.installedVersion}`);
    if (expectedVersions.length === 0) {
        cy.get("#releases-up-to-date").should("be.visible");
        cy.get("#releases-list .version-notes-block").should("have.length", 0);
        return;
    }
    cy.get("#releases-list .version-notes-block")
        .should("have.length", expectedVersions.length)
        .then(($releases) => {
            expect([...$releases].map((node) => node.dataset.releaseVersion)).to.deep.equal(expectedVersions);
        });
    cy.get("#releases-list .version-notes-block").each(($release) => {
        cy.wrap($release).find(".release-notes").should("not.be.empty");
        cy.wrap($release).find("a").should("have.attr", "href").and("include", "changelog/");
    });
};

describe("System update notice for all staff", () => {
    it("renders the releases page for admins from the upgrade preview", () => {
        login("admin");
        cy.request("/admin/api/upgrade/preview").then(({ body }) => {
            expectReleasesPageToMatchPreview(body.data ?? body);
        });
    });

    it("lets staff read each newer release without upgrade controls", () => {
        login("admin");
        cy.request("/admin/api/upgrade/preview").then(({ body }) => {
            const preview = body.data ?? body;
            cy.visit("/session/end");
            login("standard");
            expectReleasesPageToMatchPreview(preview);
            cy.get('a[href$="/admin/system/upgrade"]').should("not.exist");
        });
    });

    it("dismisses the notice for only the current login session", () => {
        login("admin");
        // The route is CSRF-protected: a tokenless POST is rejected.
        cy.request({
            method: "POST",
            url: "/v2/releases/dismiss-update-notice",
            body: {},
            failOnStatusCode: false,
        }).its("status").should("eq", 403);
        cy.request("/admin/system/users").then(({ body }) => {
            cy.request({
                method: "POST",
                url: "/v2/releases/dismiss-update-notice",
                headers: { "X-CSRF-Token": csrfFrom(body) },
                body: {},
            }).its("status").should("eq", 204);
        });
        cy.visit("/v2/dashboard");
        cy.get('[data-notification-id="system-update-available"]').should("not.exist");
        cy.reload();
        cy.get('[data-notification-id="system-update-available"]').should("not.exist");
    });
});
