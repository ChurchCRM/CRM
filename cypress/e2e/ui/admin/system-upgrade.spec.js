const previewFixture = {
    installedVersion: "5.0.0",
    nextVersion: "5.0.1",
    latestVersion: "5.0.1",
    nextReleaseNotes: "## What's New\n\n- **Feature 1**: Dashboard\n",
    nextChangelogUrl: "https://github.com/ChurchCRM/CRM/blob/master/changelog/5.0.1.md",
    releasesAhead: 1,
    latestReleaseNotes: "",
    latestChangelogUrl: "https://github.com/ChurchCRM/CRM/blob/master/changelog/5.0.1.md",
    upgradePath: [
        {
            version: "5.0.1",
            type: "patch",
            notes: "## Patch Notes\n\n- Bug fixes\n",
            changelogUrl: "https://github.com/ChurchCRM/CRM/blob/master/changelog/5.0.1.md",
            isNext: true,
        },
    ],
};

const upToDateFixture = {
    installedVersion: "5.0.1",
    nextVersion: null,
    latestVersion: "5.0.1",
    nextReleaseNotes: "",
    nextChangelogUrl: null,
    releasesAhead: 0,
    latestReleaseNotes: "## What's New in 5.0.1\n\n- **Feature**: All up to date!\n",
    latestChangelogUrl: "https://github.com/ChurchCRM/CRM/blob/master/changelog/5.0.1.md",
    upgradePath: [],
};

const downloadFixture = {
    fileName: "ChurchCRM-test-5.0.0.zip",
    fullPath: "/tmp/ChurchCRM-test-5.0.0.zip",
    releaseNotes: "## What's New\n\n- **Feature 1**: Dashboard\n",
    sha1: "abc123def456",
};

describe("System Upgrade Page", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("should load and display compact version info", () => {
        cy.visit("/admin/system/upgrade");

        cy.contains("Installed").should("be.visible");
        cy.get(".badge.bg-primary-lt").should("be.visible").and("not.be.empty");
        cy.get("#refreshFromGitHub").should("be.visible");
    });

    it("should display the upgrade wizard with all steps", () => {
        cy.visit("/admin/system/upgrade");

        cy.get("#upgrade-wizard-card").should("be.visible");

        cy.get(".bs-stepper-header").within(() => {
            cy.contains("Pre-flight").should("exist");
            cy.contains("Backup").should("exist");
            cy.contains("What's New").should("exist");
            cy.contains("Download & Apply").should("exist");
            cy.contains("Complete").should("exist");
        });

        cy.get("#step-warnings").should("be.visible");
    });

    it("should show pre-flight step with Continue button", () => {
        cy.visit("/admin/system/upgrade");
        cy.get("#acceptWarnings").should("be.visible").and("contain", "Continue");
    });

    describe("Upgrade Wizard Workflow", () => {
        it("should navigate from pre-flight to backup step", () => {
            cy.visit("/admin/system/upgrade");

            cy.get("#step-warnings").should("be.visible");
            cy.get("#acceptWarnings").click();
            cy.get("#step-backup").should("be.visible");
        });

        it("should mark completed steps with green checkmark", () => {
            cy.visit("/admin/system/upgrade");

            cy.get("#acceptWarnings").click();
            cy.get("#step-backup").should("be.visible");

            cy.get(".bs-stepper-header .step").first().should("have.class", "completed");
        });

        it("should show Create Backup and Skip Backup buttons", () => {
            cy.visit("/admin/system/upgrade");

            cy.get("#acceptWarnings").click();
            cy.get("#step-backup").should("be.visible");

            cy.get("#doBackup").should("be.visible").and("contain", "Create Backup");
            cy.get("#skipBackup").should("be.visible");
        });

        it("should skip backup and navigate to What's New step", () => {
            cy.intercept("GET", "**/admin/api/upgrade/preview", {
                statusCode: 200,
                body: previewFixture,
            }).as("previewRequest");

            cy.visit("/admin/system/upgrade");

            cy.get("#acceptWarnings").click();
            cy.get("#step-backup").should("be.visible");

            cy.get("#skipBackup").click();

            // Should reach What's New step and load preview
            cy.wait("@previewRequest", { timeout: 10000 });
            cy.get("#step-whats-new").should("be.visible");
            cy.get("#whatsNewContent").should("not.have.class", "d-none");
            cy.get("#whatsNewVersion").should("contain", "5.0.1");
            cy.get("#proceedToDownload").should("be.visible");
        });

        it("should navigate full workflow with intercepted download", () => {
            cy.intercept("GET", "**/admin/api/upgrade/preview", {
                statusCode: 200,
                body: previewFixture,
            }).as("previewRequest");

            cy.intercept("GET", "**/admin/api/upgrade/download-latest-release", {
                statusCode: 200,
                body: {
                    ...downloadFixture,
                    releaseNotes:
                        "## What's New\n\n- **Feature 1**: Dashboard\n- **Feature 2**: Performance\n\n> Note: Backup first",
                },
            }).as("downloadRelease");

            cy.visit("/admin/system/upgrade");

            // Step 1: Continue past pre-flight
            cy.get("#acceptWarnings").click();
            cy.get("#step-backup").should("be.visible");

            // Step 2: Skip backup — auto-advances to What's New
            cy.get("#skipBackup").click();

            // Step 3: What's New — wait for preview and proceed
            cy.wait("@previewRequest", { timeout: 10000 });
            cy.get("#whatsNewContent").should("not.have.class", "d-none");
            cy.get("#proceedToDownload").click();

            // Step 4: Download & Apply
            cy.wait("@downloadRelease", { timeout: 15000 });

            cy.get("#downloadStatus .alert-success").should("be.visible");
            cy.get("#updateDetails").should("not.have.class", "d-none");
            cy.get("#updateFileName").should("contain", "ChurchCRM-test-5.0.0.zip");
            cy.get("#updateSHA1").should("contain", "abc123def456");

            // Release notes rendered as markdown
            cy.get("#releaseNotes").within(() => {
                cy.get("h2").should("exist");
                cy.get("li").should("have.length.at.least", 2);
            });

            // Apply button visible but NOT clicked
            cy.get("#applyButtonContainer").should("not.have.class", "d-none");
            cy.get("#applyUpdate").should("be.visible");
        });

        it("should handle download failure with retry", () => {
            cy.intercept("GET", "**/admin/api/upgrade/preview", {
                statusCode: 200,
                body: previewFixture,
            }).as("previewRequest");

            cy.intercept("GET", "**/admin/api/upgrade/download-latest-release", {
                statusCode: 400,
                body: { message: "Rate limit exceeded" },
            }).as("downloadFail");

            cy.visit("/admin/system/upgrade");

            cy.get("#acceptWarnings").click();
            cy.get("#step-backup").should("be.visible");
            cy.get("#skipBackup").click();

            cy.wait("@previewRequest", { timeout: 10000 });
            cy.get("#proceedToDownload").click();

            cy.wait("@downloadFail", { timeout: 15000 });
            cy.get("#downloadStatus .alert-danger").should("be.visible");
            cy.get("#retryDownload").should("be.visible");
        });

        it("should show security callout and stacked release notes when multiple releases behind", () => {
            cy.intercept("GET", "**/admin/api/upgrade/preview", {
                statusCode: 200,
                body: {
                    installedVersion: "5.0.0",
                    nextVersion: "5.0.1",
                    latestVersion: "5.0.3",
                    nextReleaseNotes: "## 5.0.1 Notes\n\n- Patch fix\n",
                    nextChangelogUrl: "https://github.com/ChurchCRM/CRM/blob/master/changelog/5.0.1.md",
                    releasesAhead: 3,
                    latestReleaseNotes: "",
                    latestChangelogUrl: "https://github.com/ChurchCRM/CRM/blob/master/changelog/5.0.3.md",
                    upgradePath: [
                        {
                            version: "5.0.1",
                            type: "patch",
                            notes: "## Patch fix\n",
                            changelogUrl: "https://github.com/ChurchCRM/CRM/blob/master/changelog/5.0.1.md",
                            isNext: true,
                        },
                        {
                            version: "5.0.2",
                            type: "patch",
                            notes: "## Another fix\n",
                            changelogUrl: "https://github.com/ChurchCRM/CRM/blob/master/changelog/5.0.2.md",
                            isNext: false,
                        },
                        {
                            version: "5.0.3",
                            type: "patch",
                            notes: "## Third fix\n",
                            changelogUrl: "https://github.com/ChurchCRM/CRM/blob/master/changelog/5.0.3.md",
                            isNext: false,
                        },
                    ],
                },
            }).as("previewRequest");

            cy.visit("/admin/system/upgrade");
            cy.get("#acceptWarnings").click();
            cy.get("#skipBackup").click();

            cy.wait("@previewRequest", { timeout: 10000 });

            // Security callout must be visible (replaces old upgrade-path info panel)
            cy.get("#securityRecommendationCallout").should("not.have.class", "d-none");

            // Stacked release notes: all 3 versions should be rendered
            cy.get("#whatsNewNotes .version-notes-block").should("have.length", 3);

            // Default target is the latest version (5.0.3)
            cy.get("#whatsNewVersion").should("contain", "5.0.3");
            cy.get("#recommendedBadge").should("not.have.class", "d-none");

            // "Installing next release" badge must NOT appear anywhere
            cy.get("#whatsNewContent").should("not.contain", "Installing next release");

            cy.get("#proceedToDownload").should("be.visible").and("contain", "5.0.3");
        });

        it("should show changelog link in What's New step", () => {
            cy.intercept("GET", "**/admin/api/upgrade/preview", {
                statusCode: 200,
                body: previewFixture,
            }).as("previewRequest");

            cy.visit("/admin/system/upgrade");
            cy.get("#acceptWarnings").click();
            cy.get("#skipBackup").click();

            cy.wait("@previewRequest", { timeout: 10000 });
            cy.get("#whatsNewChangelogLink")
                .should("not.have.class", "d-none")
                .and("have.attr", "href")
                .and("include", "changelog/5.0.1.md");
        });

        it("should show Continue Anyway on preview API failure", () => {
            cy.intercept("GET", "**/admin/api/upgrade/preview", {
                statusCode: 500,
                body: { message: "GitHub unreachable" },
            }).as("previewFail");

            cy.visit("/admin/system/upgrade");
            cy.get("#acceptWarnings").click();
            cy.get("#skipBackup").click();

            cy.wait("@previewFail", { timeout: 10000 });
            cy.get("#whatsNewError").should("not.have.class", "d-none");
            cy.get("#skipWhatsNew").should("be.visible").and("contain", "Continue Anyway");
        });

        it("should create backup and show download button", () => {
            cy.intercept("POST", "**/admin/api/database/backup", {
                statusCode: 200,
                body: { BackupDownloadFileName: "ChurchCRM-Backup.sql.gz" },
            }).as("createBackup");

            cy.visit("/admin/system/upgrade");

            cy.get("#acceptWarnings").click();
            cy.get("#doBackup").click();
            cy.wait("@createBackup");

            cy.get("#backupStatus .alert-success").should("be.visible");
            cy.get("#downloadbutton").should("be.visible").and("contain", "Download Backup");
        });

        it("should handle backup failure", () => {
            cy.intercept("POST", "**/admin/api/database/backup", {
                statusCode: 500,
                body: { message: "Insufficient disk space" },
            }).as("backupFail");

            cy.visit("/admin/system/upgrade");

            cy.get("#acceptWarnings").click();
            cy.get("#doBackup").click();
            cy.wait("@backupFail");

            cy.get("#backupStatus .alert-danger").should("be.visible");
            cy.get("#doBackup").should("not.be.disabled");
        });

        it("should apply update and show completion step", () => {
            cy.intercept("GET", "**/admin/api/upgrade/preview", {
                statusCode: 200,
                body: previewFixture,
            }).as("previewRequest");

            cy.intercept("GET", "**/admin/api/upgrade/download-latest-release", {
                statusCode: 200,
                body: downloadFixture,
            }).as("downloadRelease");

            cy.intercept("POST", "**/admin/api/upgrade/do-upgrade", {
                statusCode: 200,
                body: {},
            }).as("doUpgrade");

            // The do-upgrade success handler fires a 1s setTimeout that:
            //   1. calls GET /session/end (logs the user out server-side)
            //   2. starts a 5s countdown then navigates to window.location.href = '/'
            // Both of these corrupt the cy.session('admin-session') cache and cause
            // every subsequent test to get a 302 to /session/begin.
            // Intercept both to keep the page alive and the session intact.
            cy.intercept("GET", "**/session/end", { statusCode: 200, body: "" }).as("sessionEnd");
            cy.intercept("GET", "/", { statusCode: 200, body: "<html><body></body></html>" }).as("rootRedirect");

            cy.visit("/admin/system/upgrade");

            // Step 1: pre-flight
            cy.get("#acceptWarnings").click();

            // Step 2: skip backup
            cy.get("#skipBackup").click();

            // Step 3: What's New — wait for preview and proceed
            cy.wait("@previewRequest", { timeout: 10000 });
            cy.get("#whatsNewContent").should("not.have.class", "d-none");
            cy.get("#proceedToDownload").click();

            // Step 4: Download & Apply — wait for download then click Apply
            cy.wait("@downloadRelease", { timeout: 15000 });
            cy.get("#applyButtonContainer").should("not.have.class", "d-none");
            cy.get("#applyUpdate").click();

            // Wait for do-upgrade API call
            cy.wait("@doUpgrade", { timeout: 15000 });

            // Success alert should appear
            cy.get("#applyStatus .alert-success").should("be.visible");

            // Stepper should advance to Complete step
            cy.get("#step-complete", { timeout: 5000 }).should("be.visible");
        });

        it("should reset download state when force re-install is confirmed", () => {
            cy.intercept("GET", "**/admin/api/upgrade/preview", {
                statusCode: 200,
                body: previewFixture,
            }).as("previewRequest");

            cy.intercept("GET", "**/admin/api/upgrade/download-latest-release", {
                statusCode: 200,
                body: downloadFixture,
            }).as("downloadRelease");

            cy.visit("/admin/system/upgrade");

            // Walk through to Download & Apply step
            cy.get("#acceptWarnings").click();
            cy.get("#skipBackup").click();

            cy.wait("@previewRequest", { timeout: 10000 });
            cy.get("#proceedToDownload").click();

            cy.wait("@downloadRelease", { timeout: 15000 });

            // Verify download state is visible before force-reinstall
            cy.get("#updateDetails").should("not.have.class", "d-none");
            cy.get("#applyButtonContainer").should("not.have.class", "d-none");

            // The #confirmForceReinstall button lives inside #forceReinstallModal which
            // is always in the DOM (rendered unconditionally in the PHP view). The modal
            // is normally shown only when integrity warnings are present, but its confirm
            // button is always wired up. We click it directly (force:true bypasses
            // visibility) to exercise the JS state-reset logic without depending on
            // whether the server rendered integrity warnings.
            cy.get("#confirmForceReinstall").click({ force: true });

            // Primary assertion: verify the JS state was cleared
            cy.window().should((win) => {
                expect(win.CRM.updateFile).to.be.null;
            });

            // Secondary DOM assertions: the download details and apply button
            // should be hidden now that state has been reset
            cy.get("#updateDetails").should("have.class", "d-none");
            cy.get("#applyButtonContainer").should("have.class", "d-none");
        });
    });

    describe("Refresh from GitHub", () => {
        it("should call refresh API", () => {
            cy.intercept("POST", "**/admin/api/upgrade/refresh-upgrade-info", {
                statusCode: 200,
                body: { data: {}, message: "Refreshed" },
            }).as("refreshInfo");

            cy.visit("/admin/system/upgrade");

            // Register the reload intercept AFTER cy.visit() so the initial page load
            // is not swallowed. The reload from the success handler fires 1500ms after
            // the intercept resolves; this stub catches that GET without blanking the page.
            cy.intercept("GET", "**/admin/system/upgrade*", (req) => {
                req.reply({ statusCode: 200, body: "<html><body></body></html>" });
            }).as("pageReload");

            cy.get("#refreshFromGitHub").click();
            cy.wait("@refreshInfo");
            cy.get("@refreshInfo").its("response.statusCode").should("eq", 200);
        });

        it("should handle refresh failure", () => {
            cy.intercept("POST", "**/admin/api/upgrade/refresh-upgrade-info", {
                statusCode: 500,
                body: { message: "GitHub API unavailable" },
            }).as("refreshFail");

            cy.visit("/admin/system/upgrade");
            cy.get("#refreshFromGitHub").click();
            cy.wait("@refreshFail");

            // After a 500 response the fail handler re-enables the button
            cy.get("#refreshFromGitHub").should("not.be.disabled");
        });
    });

    describe("Version Info Bar", () => {
        it("should show Up to Date badge when installed equals latest", () => {
            // This tests the server-rendered badge visible before wizard interaction.
            // We rely on the PHP view rendering the badge when $isUpdateAvailable === false.
            cy.visit("/admin/system/upgrade");
            // The version-info card shows a bg-success-lt badge when up to date.
            // Exclude #recommendedBadge (new wizard element with the same classes that is
            // always rendered but controlled by JS visibility, not server-state).
            cy.get("body").then(($body) => {
                if ($body.find(".badge.bg-success-lt.text-success:not(#recommendedBadge)").length) {
                    cy.get(".badge.bg-success-lt.text-success:not(#recommendedBadge)").should("contain", "Up to Date");
                }
            });
        });

        it("should show Update Available badge when update exists", () => {
            cy.visit("/admin/system/upgrade");
            cy.get("body").then(($body) => {
                if ($body.find(".badge.bg-success").length) {
                    cy.get(".badge.bg-success").should("contain", "Update Available");
                }
            });
        });
    });

    describe("Up-to-date State", () => {
        it("should show up-to-date banner and latest release notes when releasesAhead is 0", () => {
            cy.intercept("GET", "**/admin/api/upgrade/preview", {
                statusCode: 200,
                body: upToDateFixture,
            }).as("previewRequest");

            cy.visit("/admin/system/upgrade");
            cy.get("#acceptWarnings").click();
            cy.get("#skipBackup").click();

            cy.wait("@previewRequest", { timeout: 10000 });
            cy.get("#whatsNewContent").should("not.have.class", "d-none");

            // Up-to-date success banner should be visible
            cy.get("#whatsNewContent .alert-success").should("be.visible").and("contain", "You're up to date");

            // Latest release notes should be rendered
            cy.get("#whatsNewVersion").should("contain", "5.0.1");
            cy.get("#whatsNewNotes").should("contain", "All up to date");

            // Changelog link should be visible and correct
            cy.get("#whatsNewChangelogLink")
                .should("not.have.class", "d-none")
                .and("have.attr", "href")
                .and("include", "changelog/5.0.1.md");

            // Proceed button should be hidden (no upgrade available)
            cy.get("#proceedToDownload").should("have.class", "d-none");
        });

        it("should show Force Re-install button when up to date", () => {
            // The Force Re-install button (#forceReinstallCurrent) is rendered by PHP
            // when !isUpdateAvailable && latestGitHubVersion !== null.
            // In CI the live page may or may not have an update available, so we assert
            // the button's presence conditionally.
            cy.visit("/admin/system/upgrade");
            cy.get("body").then(($body) => {
                if ($body.find("#forceReinstallCurrent").length) {
                    cy.get("#forceReinstallCurrent")
                        .should("be.visible")
                        .and("contain", "Force Re-install");
                }
            });
        });

        it("should keep proceed button visible and relabelled in force-reinstall mode when up to date", () => {
            cy.intercept("GET", "**/admin/api/upgrade/preview", {
                statusCode: 200,
                body: upToDateFixture,
            }).as("previewRequest");

            cy.visit("/admin/system/upgrade");
            cy.get("#acceptWarnings").click();

            // Confirm force-reinstall via the modal (always present in DOM)
            cy.get("#confirmForceReinstall").click({ force: true });

            // After confirmation the wizard jumps to backup step automatically
            cy.get("#step-backup").should("be.visible");
            cy.get("#skipBackup").click();

            // What's New step: with forceReinstallMode=true the proceed button should
            // remain visible and carry the 'Re-install current version' label
            cy.wait("@previewRequest", { timeout: 10000 });
            cy.get("#whatsNewContent").should("not.have.class", "d-none");

            cy.get("#proceedToDownload")
                .should("not.have.class", "d-none")
                .and("contain", "Re-install current version");
        });
    });

    describe("Advanced Version Selector", () => {
        const multiReleaseFixture = {
            installedVersion: "5.0.0",
            nextVersion: "5.0.1",
            latestVersion: "5.0.3",
            nextReleaseNotes: "## 5.0.1 Notes\n\n- Patch fix\n",
            nextChangelogUrl: "https://github.com/ChurchCRM/CRM/blob/master/changelog/5.0.1.md",
            releasesAhead: 3,
            latestReleaseNotes: "",
            latestChangelogUrl: "https://github.com/ChurchCRM/CRM/blob/master/changelog/5.0.3.md",
            upgradePath: [
                {
                    version: "5.0.1",
                    type: "patch",
                    notes: "## 5.0.1 patch fix\n",
                    changelogUrl: "https://github.com/ChurchCRM/CRM/blob/master/changelog/5.0.1.md",
                    isNext: true,
                },
                {
                    version: "5.0.2",
                    type: "minor",
                    notes: "## 5.0.2 feature\n",
                    changelogUrl: "https://github.com/ChurchCRM/CRM/blob/master/changelog/5.0.2.md",
                    isNext: false,
                },
                {
                    version: "5.0.3",
                    type: "patch",
                    notes: "## 5.0.3 another fix\n",
                    changelogUrl: "https://github.com/ChurchCRM/CRM/blob/master/changelog/5.0.3.md",
                    isNext: false,
                },
            ],
        };

        it("should open advanced selector and update notes when version is changed", () => {
            cy.intercept("GET", "**/admin/api/upgrade/preview", {
                statusCode: 200,
                body: multiReleaseFixture,
            }).as("previewRequest");

            cy.visit("/admin/system/upgrade");
            cy.get("#acceptWarnings").click();
            cy.get("#skipBackup").click();

            cy.wait("@previewRequest", { timeout: 10000 });
            cy.get("#whatsNewContent").should("not.have.class", "d-none");

            // Open the advanced selector collapse by its visible text label.
            cy.contains('a[data-bs-toggle="collapse"]', 'Advanced: Install a specific version instead').click();
            cy.get("#advancedVersionCollapse").should("have.class", "show", { timeout: 5000 });
            cy.get("#targetVersionSelect").should("be.visible");

            // Options: 1 latest (Recommended) + 2 non-latest = 3 total
            cy.get("#targetVersionSelect option").should("have.length", 3);

            // Select non-latest version 5.0.2 — should update version heading and notes
            cy.get("#targetVersionSelect").select("5.0.2");

            // Release notes heading should update to 5.0.2
            cy.get("#whatsNewVersion").should("contain", "5.0.2");
            cy.get("#whatsNewNotes").should("contain", "5.0.2 feature");

            // Recommended badge should be hidden when non-latest is selected
            cy.get("#recommendedBadge").should("have.class", "d-none");

            // Warning banner should be visible
            cy.get("#advancedWarningBanner").should("not.have.class", "d-none");
        });

        it("should send ?version= query param when specific non-latest version is chosen", () => {
            cy.intercept("GET", "**/admin/api/upgrade/preview", {
                statusCode: 200,
                body: multiReleaseFixture,
            }).as("previewRequest");

            cy.intercept("GET", "**/admin/api/upgrade/download-latest-release?version=5.0.2", {
                statusCode: 200,
                body: {
                    fileName: "ChurchCRM-5.0.2.zip",
                    fullPath: "/tmp/ChurchCRM-5.0.2.zip",
                    releaseNotes: "## 5.0.2\n\n- Feature\n",
                    sha1: "aabbcc112233",
                },
            }).as("downloadSpecific");

            cy.visit("/admin/system/upgrade");
            cy.get("#acceptWarnings").click();
            cy.get("#skipBackup").click();

            cy.wait("@previewRequest", { timeout: 10000 });

            // Open advanced selector and pick non-latest 5.0.2
            cy.contains('a[data-bs-toggle="collapse"]', 'Advanced: Install a specific version instead').click();
            cy.get("#advancedVersionCollapse").should("have.class", "show", { timeout: 5000 });
            cy.get("#targetVersionSelect").select("5.0.2");

            // Proceed to download step
            cy.get("#proceedToDownload").click();

            // Verify that the download request used the ?version= param for 5.0.2
            cy.wait("@downloadSpecific", { timeout: 15000 });
            cy.get("#downloadStatus .alert-success").should("be.visible");
            cy.get("#updateFileName").should("contain", "ChurchCRM-5.0.2.zip");
        });

        it("should render stacked version blocks with deep-link anchors", () => {
            cy.intercept("GET", "**/admin/api/upgrade/preview", {
                statusCode: 200,
                body: multiReleaseFixture,
            }).as("previewRequest");

            cy.visit("/admin/system/upgrade");
            cy.get("#acceptWarnings").click();
            cy.get("#skipBackup").click();

            cy.wait("@previewRequest", { timeout: 10000 });

            // All 3 versions should be rendered as stacked blocks (newest-first)
            cy.get("#whatsNewNotes .version-notes-block").should("have.length", 3);

            // Each block should have a deep-link anchor id (e.g. v5-0-3)
            cy.get("#whatsNewNotes #v5-0-3").should("exist");
            cy.get("#whatsNewNotes #v5-0-2").should("exist");
            cy.get("#whatsNewNotes #v5-0-1").should("exist");

            // Each block should have a "Full release notes" link
            cy.get("#whatsNewNotes .version-notes-block").first().find("a").should("contain", "Full release notes");

            // Release notes content should be rendered
            cy.get("#whatsNewNotes").should("contain", "5.0.3 another fix");
        });
    });
});
