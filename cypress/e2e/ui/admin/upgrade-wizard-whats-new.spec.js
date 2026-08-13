/**
 * Upgrade Wizard — What you'll gain (redesigned step) — new spec
 *
 * All scenarios intercept /admin/api/upgrade/preview (the app's server-side
 * proxy for GitHub releases data) and return a mocked "future version" of
 * 99.0.0 so the tests are completely decoupled from real GitHub release data.
 *
 * Scenarios:
 *   (a) single-version-behind  — one release separates installed from latest
 *   (b) multi-version-behind   — three skipped releases rendered as stacked blocks
 *   (c) advanced picker warning — non-latest selection triggers red banner + CTA update
 */

// ── Fixtures ─────────────────────────────────────────────────────────────────

/** Single version behind: current → 99.0.0 (one jump) */
const singleVersionFixture = {
    installedVersion: "5.0.0",
    nextVersion: "99.0.0",
    latestVersion: "99.0.0",
    nextReleaseNotes:
        "## 99.0.0 — Future Release\n\n- Groundbreaking new feature\n- Security patch: CVE-9999-0001\n",
    nextChangelogUrl: "https://github.com/ChurchCRM/CRM/blob/master/changelog/99.0.0.md",
    releasesAhead: 1,
    latestReleaseNotes: "",
    latestChangelogUrl: "https://github.com/ChurchCRM/CRM/blob/master/changelog/99.0.0.md",
    upgradePath: [
        {
            version: "99.0.0",
            type: "major",
            notes: "## 99.0.0 — Future Release\n\n- Groundbreaking new feature\n- Security patch: CVE-9999-0001\n",
            changelogUrl: "https://github.com/ChurchCRM/CRM/blob/master/changelog/99.0.0.md",
            isNext: true,
        },
    ],
};

/** Multi-version behind: current → 97.0.0 → 98.0.0 → 99.0.0 (three jumps) */
const multiVersionFixture = {
    installedVersion: "5.0.0",
    nextVersion: "97.0.0",
    latestVersion: "99.0.0",
    nextReleaseNotes: "## 97.0.0\n\n- Intermediate release A\n",
    nextChangelogUrl: "https://github.com/ChurchCRM/CRM/blob/master/changelog/97.0.0.md",
    releasesAhead: 3,
    latestReleaseNotes: "",
    latestChangelogUrl: "https://github.com/ChurchCRM/CRM/blob/master/changelog/99.0.0.md",
    upgradePath: [
        {
            version: "97.0.0",
            type: "major",
            notes: "## 97.0.0\n\n- Intermediate release A\n",
            changelogUrl: "https://github.com/ChurchCRM/CRM/blob/master/changelog/97.0.0.md",
            isNext: true,
        },
        {
            version: "98.0.0",
            type: "major",
            notes: "## 98.0.0\n\n- Intermediate release B with security fix\n",
            changelogUrl: "https://github.com/ChurchCRM/CRM/blob/master/changelog/98.0.0.md",
            isNext: false,
        },
        {
            version: "99.0.0",
            type: "major",
            notes: "## 99.0.0 — Future Release\n\n- Latest stable with critical security patch\n",
            changelogUrl: "https://github.com/ChurchCRM/CRM/blob/master/changelog/99.0.0.md",
            isNext: false,
        },
    ],
};

// ── Helpers ───────────────────────────────────────────────────────────────────

/** Navigate to the What you'll gain step via the wizard. */
function reachWhatsNewStep(previewAlias) {
    cy.get("#acceptWarnings").click();
    cy.get("#step-backup").should("be.visible");
    cy.get("#skipBackup").click();
    cy.wait(previewAlias, { timeout: 10000 });
    cy.get("#whatsNewContent").should("not.have.class", "d-none");
}

// ── Suite ─────────────────────────────────────────────────────────────────────

describe("Upgrade Wizard — What you'll gain redesign", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    // ── (a) Single version behind ─────────────────────────────────────────────

    describe("(a) Single version behind — 99.0.0", () => {
        beforeEach(() => {
            cy.intercept("GET", "**/admin/api/upgrade/preview", {
                statusCode: 200,
                body: singleVersionFixture,
            }).as("previewRequest");

            cy.visit("/admin/system/upgrade");
        });

        it("always defaults to the latest available version", () => {
            reachWhatsNewStep("@previewRequest");

            // Target version shown in heading should be the latest (99.0.0)
            cy.get("#whatsNewVersion").should("contain", "99.0.0");
        });

        it("shows the green Recommended badge on the latest target", () => {
            reachWhatsNewStep("@previewRequest");

            cy.get("#recommendedBadge").should("not.have.class", "d-none").and("contain", "Recommended");
        });

        it("shows the security recommendation callout", () => {
            reachWhatsNewStep("@previewRequest");

            cy.get("#securityRecommendationCallout")
                .should("not.have.class", "d-none")
                .and("contain", "security fixes");
        });

        it("shows #recommendedBadge with 'Recommended' text", () => {
            reachWhatsNewStep("@previewRequest");

            cy.get("#recommendedBadge")
                .should("not.have.class", "d-none")
                .and("contain", "Recommended");
        });

        it("CTA reads 'Download & Apply 99.0.0'", () => {
            reachWhatsNewStep("@previewRequest");

            cy.get("#proceedToDownload")
                .should("be.visible")
                .and("contain", "Download & Apply")
                .and("contain", "99.0.0");
        });

        it("renders a version block for 99.0.0 with the correct content", () => {
            reachWhatsNewStep("@previewRequest");

            cy.get("#whatsNewNotes .version-notes-block").should("have.length", 1);
            cy.get("#whatsNewNotes .version-notes-block").first().should("contain", "99.0.0");
        });

        it("does NOT show the advanced picker when only one version ahead (nothing to downgrade to)", () => {
            reachWhatsNewStep("@previewRequest");

            cy.get("#advancedVersionPanel").should("have.class", "d-none");
        });

        it("renders a deep-link anchor for 99.0.0", () => {
            reachWhatsNewStep("@previewRequest");

            cy.get("#whatsNewNotes #v99-0-0").should("exist");
        });

        it("shows a Full release notes link for the version block", () => {
            reachWhatsNewStep("@previewRequest");

            cy.get("#whatsNewNotes .version-notes-block").first().find("a").should("contain", "Full release notes");
        });
    });

    // ── (b) Multi-version behind ──────────────────────────────────────────────

    describe("(b) Multi-version behind — three skipped releases to 99.0.0", () => {
        beforeEach(() => {
            cy.intercept("GET", "**/admin/api/upgrade/preview", {
                statusCode: 200,
                body: multiVersionFixture,
            }).as("previewRequest");

            cy.visit("/admin/system/upgrade");
        });

        it("stacks release notes for all skipped versions (newest first)", () => {
            reachWhatsNewStep("@previewRequest");

            cy.get("#whatsNewNotes .version-notes-block").should("have.length", 3);

            // Verify newest-first order: 99.0.0, 98.0.0, 97.0.0
            cy.get("#whatsNewNotes .version-notes-block").eq(0).should("contain", "99.0.0");
            cy.get("#whatsNewNotes .version-notes-block").eq(1).should("contain", "98.0.0");
            cy.get("#whatsNewNotes .version-notes-block").eq(2).should("contain", "97.0.0");
        });

        it("renders a deep-link anchor for each skipped version", () => {
            reachWhatsNewStep("@previewRequest");

            cy.get("#whatsNewNotes #v99-0-0").should("exist");
            cy.get("#whatsNewNotes #v98-0-0").should("exist");
            cy.get("#whatsNewNotes #v97-0-0").should("exist");
        });

        it("each version block has a Full release notes link to GitHub", () => {
            reachWhatsNewStep("@previewRequest");

            cy.get("#whatsNewNotes .version-notes-block").each(($block) => {
                cy.wrap($block)
                    .find("a")
                    .should("have.attr", "href")
                    .and("include", "github.com");
            });
        });

        it("default target is latest (99.0.0) with Recommended badge", () => {
            reachWhatsNewStep("@previewRequest");

            cy.get("#whatsNewVersion").should("contain", "99.0.0");
            cy.get("#recommendedBadge").should("not.have.class", "d-none");
        });

        it("security callout is visible when multi-version behind", () => {
            reachWhatsNewStep("@previewRequest");

            cy.get("#securityRecommendationCallout").should("not.have.class", "d-none");
        });
    });

    // ── (c) Advanced picker warning banner ───────────────────────────────────

    describe("(c) Advanced picker — selecting a non-latest version shows warning", () => {
        beforeEach(() => {
            cy.intercept("GET", "**/admin/api/upgrade/preview", {
                statusCode: 200,
                body: multiVersionFixture,
            }).as("previewRequest");

            cy.visit("/admin/system/upgrade");
        });

        it("advanced panel is collapsed by default", () => {
            reachWhatsNewStep("@previewRequest");

            cy.get("#advancedVersionCollapse").should("not.have.class", "show");
        });

        it("expands the advanced section when toggle is clicked", () => {
            reachWhatsNewStep("@previewRequest");

            cy.contains('a[data-bs-toggle="collapse"]', "Advanced: Install a specific version instead").click();
            cy.get("#advancedVersionCollapse").should("have.class", "show", { timeout: 5000 });
            cy.get("#targetVersionSelect").should("be.visible");
        });

        it("lists the latest version as the default Recommended option", () => {
            reachWhatsNewStep("@previewRequest");

            cy.contains('a[data-bs-toggle="collapse"]', "Advanced: Install a specific version instead").click();
            cy.get("#advancedVersionCollapse").should("have.class", "show", { timeout: 5000 });

            // First option should be 99.0.0 (Recommended) and selected
            cy.get("#targetVersionSelect option").first().should("contain", "99.0.0").and("contain", "Recommended");
            cy.get("#targetVersionSelect").should("have.value", "99.0.0");
        });

        it("shows a red security warning banner when a non-latest version is selected", () => {
            reachWhatsNewStep("@previewRequest");

            cy.contains('a[data-bs-toggle="collapse"]', "Advanced: Install a specific version instead").click();
            cy.get("#advancedVersionCollapse").should("have.class", "show", { timeout: 5000 });

            // Select a non-latest version
            cy.get("#targetVersionSelect").select("98.0.0");

            // Red warning banner should appear
            cy.get("#advancedWarningBanner")
                .should("not.have.class", "d-none")
                .and("have.class", "alert-danger");
            cy.get("#advancedWarningText").should("contain", "98.0.0").and("contain", "99.0.0");
        });

        it("hides the Recommended badge when a non-latest version is selected", () => {
            reachWhatsNewStep("@previewRequest");

            cy.contains('a[data-bs-toggle="collapse"]', "Advanced: Install a specific version instead").click();
            cy.get("#advancedVersionCollapse").should("have.class", "show", { timeout: 5000 });
            cy.get("#targetVersionSelect").select("98.0.0");

            cy.get("#recommendedBadge").should("have.class", "d-none");
        });

        it("CTA updates to the chosen non-latest version", () => {
            reachWhatsNewStep("@previewRequest");

            cy.contains('a[data-bs-toggle="collapse"]', "Advanced: Install a specific version instead").click();
            cy.get("#advancedVersionCollapse").should("have.class", "show", { timeout: 5000 });
            cy.get("#targetVersionSelect").select("98.0.0");

            cy.get("#proceedToDownload")
                .should("contain", "Download & Apply")
                .and("contain", "98.0.0");
        });

        it("notes stack is trimmed to the chosen version (only 97.0.0 and 98.0.0 shown)", () => {
            reachWhatsNewStep("@previewRequest");

            cy.contains('a[data-bs-toggle="collapse"]', "Advanced: Install a specific version instead").click();
            cy.get("#advancedVersionCollapse").should("have.class", "show", { timeout: 5000 });
            cy.get("#targetVersionSelect").select("98.0.0");

            // Should show only 98.0.0 and 97.0.0 (not 99.0.0)
            cy.get("#whatsNewNotes .version-notes-block").should("have.length", 2);
            cy.get("#whatsNewNotes").should("not.contain", "99.0.0");
        });

        it("restores default state when the latest version is re-selected", () => {
            reachWhatsNewStep("@previewRequest");

            cy.contains('a[data-bs-toggle="collapse"]', "Advanced: Install a specific version instead").click();
            cy.get("#advancedVersionCollapse").should("have.class", "show", { timeout: 5000 });

            // Pick non-latest first
            cy.get("#targetVersionSelect").select("98.0.0");
            cy.get("#advancedWarningBanner").should("not.have.class", "d-none");

            // Revert to latest
            cy.get("#targetVersionSelect").select("99.0.0");

            // Warning gone, Recommended badge restored, all 3 blocks back
            cy.get("#advancedWarningBanner").should("have.class", "d-none");
            cy.get("#recommendedBadge").should("not.have.class", "d-none");
            cy.get("#whatsNewNotes .version-notes-block").should("have.length", 3);
            cy.get("#proceedToDownload").should("contain", "99.0.0");
        });
    });

    // ── (d) Prerelease / dev build ahead of latest stable (Case 1) ──────────────

    describe("(d) Running a pre-release build ahead of latest stable", () => {
        const prereleaseMockFixture = {
            installedVersion: "99.5.0-dev",
            nextVersion: "99.0.0",
            latestVersion: "99.0.0",
            nextReleaseNotes: "## 99.0.0\n\n- Latest stable release\n- Security patch included\n",
            nextChangelogUrl: "https://github.com/ChurchCRM/CRM/blob/master/changelog/99.0.0.md",
            releasesAhead: 0,
            latestReleaseNotes: "## 99.0.0\n\n- Latest stable release\n",
            latestChangelogUrl: "https://github.com/ChurchCRM/CRM/blob/master/changelog/99.0.0.md",
            upgradePath: [],
            isAheadOfStable: true,
        };

        beforeEach(() => {
            cy.intercept("GET", "**/admin/api/upgrade/preview", {
                statusCode: 200,
                body: prereleaseMockFixture,
            }).as("previewRequest");

            cy.visit("/admin/system/upgrade");
        });

        it("shows a pre-release warning banner", () => {
            reachWhatsNewStep("@previewRequest");

            cy.get("#whatsNewContent .alert-warning").should("be.visible").and("contain", "pre-release");
        });

        it("shows the stable version in the version heading", () => {
            reachWhatsNewStep("@previewRequest");

            cy.get("#whatsNewVersion").should("contain", "99.0.0");
        });

        it("CTA reads 'Install stable 99.0.0'", () => {
            reachWhatsNewStep("@previewRequest");

            cy.get("#proceedToDownload").should("be.visible").and("contain", "Install stable").and("contain", "99.0.0");
        });

        it("renders a version block for the stable target", () => {
            reachWhatsNewStep("@previewRequest");

            cy.get("#whatsNewNotes .version-notes-block").should("have.length", 1);
            cy.get("#whatsNewNotes .version-notes-block").first().should("contain", "99.0.0");
        });

        it("does NOT show security callout in prerelease mode", () => {
            reachWhatsNewStep("@previewRequest");

            cy.get("#securityRecommendationCallout").should("have.class", "d-none");
        });

        it("does NOT show the advanced version picker in prerelease mode", () => {
            reachWhatsNewStep("@previewRequest");

            cy.get("#advancedVersionPanel").should("have.class", "d-none");
        });
    });
});
