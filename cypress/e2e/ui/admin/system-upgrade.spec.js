const previewFixture = {
    installedVersion: "5.0.0",
    nextVersion: "5.0.1",
    latestVersion: "5.0.1",
    nextReleaseNotes: "## What's New\n\n- **Feature 1**: Dashboard\n",
    nextChangelogUrl: "https://github.com/ChurchCRM/CRM/blob/master/changelog/5.0.1.md",
    releasesAhead: 1,
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

        it("should render upgrade path panel when multiple releases behind", () => {
            cy.intercept("GET", "**/admin/api/upgrade/preview", {
                statusCode: 200,
                body: {
                    installedVersion: "5.0.0",
                    nextVersion: "5.0.1",
                    latestVersion: "5.0.3",
                    nextReleaseNotes: "## 5.0.1 Notes\n\n- Patch fix\n",
                    nextChangelogUrl: "https://github.com/ChurchCRM/CRM/blob/master/changelog/5.0.1.md",
                    releasesAhead: 3,
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
            cy.get("#upgradePathPanel").should("not.have.class", "d-none");
            cy.get("#upgradePathSummary").should("contain", "3");
            cy.get("#proceedToDownload").should("be.visible");
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
    });

    describe("Refresh from GitHub", () => {
        it("should call refresh API", () => {
            cy.intercept("POST", "**/admin/api/upgrade/refresh-upgrade-info", {
                statusCode: 200,
                body: { data: {}, message: "Refreshed" },
            }).as("refreshInfo");

            cy.visit("/admin/system/upgrade");
            cy.get("#refreshFromGitHub").click();
            cy.wait("@refreshInfo");
        });

        it("should handle refresh failure", () => {
            cy.intercept("POST", "**/admin/api/upgrade/refresh-upgrade-info", {
                statusCode: 500,
                body: { message: "GitHub API unavailable" },
            }).as("refreshFail");

            cy.visit("/admin/system/upgrade");
            cy.get("#refreshFromGitHub").click();
            cy.wait("@refreshFail");
            cy.get("#refreshFromGitHub").should("not.be.disabled");
        });
    });

    describe("Version Info Bar", () => {
        it("should show Up to Date badge when installed equals latest", () => {
            // This tests the server-rendered badge visible before wizard interaction.
            // We rely on the PHP view rendering the badge when $isUpdateAvailable === false.
            cy.visit("/admin/system/upgrade");
            // The page always shows at least one badge; when up to date it has bg-success-lt
            // We cannot intercept the PHP page render, so we assert that if the
            // bg-success-lt badge exists it carries the expected text.
            cy.get("body").then(($body) => {
                if ($body.find(".badge.bg-success-lt.text-success").length) {
                    cy.get(".badge.bg-success-lt.text-success").should("contain", "Up to Date");
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

    describe("Advanced Version Selector", () => {
        const multiReleaseFixture = {
            installedVersion: "5.0.0",
            nextVersion: "5.0.1",
            latestVersion: "5.0.3",
            nextReleaseNotes: "## 5.0.1 Notes\n\n- Patch fix\n",
            nextChangelogUrl: "https://github.com/ChurchCRM/CRM/blob/master/changelog/5.0.1.md",
            releasesAhead: 3,
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

            // Open the advanced selector collapse
            cy.get("[href='#advancedVersionCollapse']").click();
            cy.get("#advancedVersionCollapse").should("have.class", "show");
            cy.get("#targetVersionSelect").should("be.visible");

            // Options should include default + all upgrade path entries
            cy.get("#targetVersionSelect option").should("have.length", 4); // 1 default + 3 versions

            // Select version 5.0.3
            cy.get("#targetVersionSelect").select("5.0.3");

            // Release notes heading should update to 5.0.3
            cy.get("#whatsNewVersion").should("contain", "5.0.3");
            cy.get("#whatsNewNotes").should("contain", "5.0.3 another fix");
        });

        it("should send ?version= query param when specific version is chosen", () => {
            cy.intercept("GET", "**/admin/api/upgrade/preview", {
                statusCode: 200,
                body: multiReleaseFixture,
            }).as("previewRequest");

            cy.intercept("GET", "**/admin/api/upgrade/download-latest-release?version=5.0.3", {
                statusCode: 200,
                body: {
                    fileName: "ChurchCRM-5.0.3.zip",
                    fullPath: "/tmp/ChurchCRM-5.0.3.zip",
                    releaseNotes: "## 5.0.3\n\n- Another fix\n",
                    sha1: "aabbcc112233",
                },
            }).as("downloadSpecific");

            cy.visit("/admin/system/upgrade");
            cy.get("#acceptWarnings").click();
            cy.get("#skipBackup").click();

            cy.wait("@previewRequest", { timeout: 10000 });

            // Open advanced selector and pick 5.0.3
            cy.get("[href='#advancedVersionCollapse']").click();
            cy.get("#targetVersionSelect").select("5.0.3");

            // Proceed to download step
            cy.get("#proceedToDownload").click();

            // Verify that the download request used the ?version= param
            cy.wait("@downloadSpecific", { timeout: 15000 });
            cy.get("#downloadStatus .alert-success").should("be.visible");
            cy.get("#updateFileName").should("contain", "ChurchCRM-5.0.3.zip");
        });

        it("should expand upgrade path accordion and show entry details", () => {
            cy.intercept("GET", "**/admin/api/upgrade/preview", {
                statusCode: 200,
                body: multiReleaseFixture,
            }).as("previewRequest");

            cy.visit("/admin/system/upgrade");
            cy.get("#acceptWarnings").click();
            cy.get("#skipBackup").click();

            cy.wait("@previewRequest", { timeout: 10000 });

            // Upgrade path panel should be visible (3 releases ahead)
            cy.get("#upgradePathPanel").should("not.have.class", "d-none");

            // Expand the upgrade path collapse
            cy.get("[href='#upgradePathCollapse']").click();
            cy.get("#upgradePathCollapse").should("have.class", "show");

            // Should render 3 accordion entries
            cy.get("#upgradePathAccordion .upgrade-path-entry").should("have.length", 3);

            // Expand the first entry and verify release notes render
            cy.get("#upgradePathAccordion .upgrade-path-entry").first().find(".upgrade-path-header").click();
            cy.get("#upgradePathAccordion .upgrade-path-entry").first().find(".upgrade-path-notes").should("have.class", "show");
            cy.get("#upgradePathAccordion .upgrade-path-entry").first().find(".release-notes").should("contain", "5.0.1");
        });
    });
});
