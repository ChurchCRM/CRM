/// <reference types="cypress" />

/**
 * Regression test for the orphan-scan exclusion of `src/plugins/community/`.
 *
 * Community plugins are third-party extensions installed at runtime. They are
 * never part of the shipped `src/admin/data/signatures.json`, and the scanner
 * in `AppIntegrityService::isExcludedFromOrphanDetection()` must skip them so
 * the orphaned-files admin page does not show every community plugin file as
 * a rogue artifact.
 *
 * This spec:
 *   1. Creates a fake community plugin directory under src/plugins/community/
 *      with both a .php file and a .js file (the two extensions the scanner
 *      actually inspects).
 *   2. Calls GET /api/orphaned-files with an admin API key.
 *   3. Asserts the scan returns 200, a numeric count, and that NONE of the
 *      files it created are present in the response.files array.
 *   4. Cleans up the fixture whether the test passed or not.
 *
 * If anyone removes the `^plugins/community/` pattern from either
 * `isExcludedFromOrphanDetection()` in PHP or from the exclusion list in
 * `scripts/generate-signatures-node.js`, this test fails loudly.
 */
describe("API Private Admin Orphaned Files — community plugin exclusion", () => {
    const pluginId = "test-orphan-exclusion";
    const pluginDir = `src/plugins/community/${pluginId}`;
    const pluginFiles = [
        `${pluginDir}/plugin.json`,
        `${pluginDir}/src/TestOrphanExclusionPlugin.php`,
        `${pluginDir}/web/test-widget.js`,
    ];

    // Relative-to-src/ paths — this is what the orphaned-files API returns.
    const expectedRelative = pluginFiles.map((p) => p.replace(/^src\//, ""));

    before(() => {
        // Stage a fake community plugin on disk. We intentionally create both
        // a .php and a .js file because the scanner only flags those two
        // extensions — anything else would be a false-negative test.
        cy.exec(`mkdir -p ${pluginDir}/src ${pluginDir}/web`);
        cy.exec(
            `printf '{"id":"${pluginId}","name":"Test Orphan Exclusion","version":"0.0.1","type":"community","mainClass":"ChurchCRM\\\\Plugins\\\\TestOrphanExclusion\\\\TestOrphanExclusionPlugin"}' > ${pluginDir}/plugin.json`,
        );
        cy.exec(
            `printf '<?php\nnamespace ChurchCRM\\\\Plugins\\\\TestOrphanExclusion;\n// orphan-scan fixture — should be ignored\nclass TestOrphanExclusionPlugin {}\n' > ${pluginDir}/src/TestOrphanExclusionPlugin.php`,
        );
        cy.exec(
            `printf '// orphan-scan fixture — should be ignored\nconsole.log("${pluginId}");\n' > ${pluginDir}/web/test-widget.js`,
        );
    });

    after(() => {
        cy.exec(`rm -rf ${pluginDir}`, { failOnNonZeroExit: false });
    });

    it("does not flag files under src/plugins/community/ as orphans", () => {
        cy.makePrivateAdminAPICall(
            "GET",
            "/admin/api/orphaned-files",
            null,
            200,
        ).then((resp) => {
            expect(resp.body).to.have.property("count");
            expect(resp.body).to.have.property("files");
            expect(resp.body.count).to.be.a("number");
            expect(resp.body.files).to.be.an("array");
            // sanity: returned count matches the array length
            expect(resp.body.files.length).to.eq(resp.body.count);

            // None of the three fixture files may appear in the response.
            for (const fixture of expectedRelative) {
                expect(
                    resp.body.files,
                    `community fixture "${fixture}" must be excluded from the orphan scan`,
                ).not.to.include(fixture);
            }

            // Defence in depth: also assert no path under plugins/community/
            // leaks through. If a future change narrows the exclusion to just
            // a subset of that directory, this assertion still catches it.
            const communityLeaks = resp.body.files.filter((f) =>
                f.startsWith("plugins/community/"),
            );
            expect(
                communityLeaks,
                `no path under plugins/community/ should appear in the orphan scan, got: ${communityLeaks.join(", ")}`,
            ).to.have.length(0);
        });
    });
});
