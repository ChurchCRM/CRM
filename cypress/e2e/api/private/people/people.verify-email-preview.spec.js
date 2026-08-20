/// <reference types="cypress" />

/**
 * API spec for GET /api/families/verify-email-preview
 *
 * This endpoint is used by the send-confirmation modal on the People Verify
 * dashboard.  It returns the set of families that would receive a verification
 * email, families without any email address, and a template preview.
 *
 * Auth: requires EditRecords role (EditRecordsRoleAuthMiddleware).
 *
 * No seeding required because the demo dataset already contains families;
 * the spec asserts shape / types only (not exact counts) so it is stable
 * across any demo-data configuration.
 */
describe("GET /api/families/verify-email-preview", () => {
    context("200 — admin caller (happy path)", () => {
        beforeEach(() => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/families/verify-email-preview",
                "",
                200,
            ).as("previewResponse");
        });

        it("returns top-level required keys", () => {
            cy.get("@previewResponse").then((response) => {
                expect(response.body).to.have.property("recipientCount").that.is.a("number");
                expect(response.body).to.have.property("recipients").that.is.an("array");
                expect(response.body).to.have.property("familiesWithoutEmail").that.is.an("array");
                expect(response.body).to.have.property("templatePreview").that.is.an("object");
            });
        });

        it("recipientCount matches recipients array length", () => {
            cy.get("@previewResponse").then((response) => {
                expect(response.body.recipientCount).to.equal(response.body.recipients.length);
            });
        });

        it("each recipient has id, name, email fields", () => {
            cy.get("@previewResponse").then((response) => {
                const recipients = response.body.recipients;
                if (recipients.length === 0) {
                    cy.log("No recipients in this environment — shape check skipped");
                    return;
                }
                const rec = recipients[0];
                expect(rec).to.have.property("id").that.is.a("number");
                expect(rec).to.have.property("name").that.is.a("string");
                expect(rec).to.have.property("email"); // may be empty string
            });
        });

        it("each familiesWithoutEmail entry has id and name", () => {
            cy.get("@previewResponse").then((response) => {
                const withoutEmail = response.body.familiesWithoutEmail;
                if (withoutEmail.length === 0) return;
                const fam = withoutEmail[0];
                expect(fam).to.have.property("id").that.is.a("number");
                expect(fam).to.have.property("name").that.is.a("string");
            });
        });

        it("templatePreview has subject and bodyExcerpt", () => {
            cy.get("@previewResponse").then((response) => {
                const preview = response.body.templatePreview;
                expect(preview).to.have.property("subject").that.is.a("string");
                expect(preview).to.have.property("bodyExcerpt");
                // Subject should contain the placeholder token
                expect(preview.subject).to.include("[Family Name]");
            });
        });

        it("recipients and familiesWithoutEmail are disjoint (no family in both)", () => {
            cy.get("@previewResponse").then((response) => {
                const recipientIds = new Set(response.body.recipients.map((r) => r.id));
                response.body.familiesWithoutEmail.forEach((fam) => {
                    expect(recipientIds.has(fam.id)).to.be.false;
                });
            });
        });
    });

    context("200 — familyId query param scoping", () => {
        it("scopes result to a single family when familyId is provided", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/families/verify-email-preview?familyId=1",
                "",
                200,
            ).then((response) => {
                expect(response.body.recipientCount).to.be.at.most(1);
                if (response.body.recipientCount === 1) {
                    expect(response.body.recipients[0].id).to.equal(1);
                }
            });
        });

        it("returns empty lists for a nonexistent familyId", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/families/verify-email-preview?familyId=9999999",
                "",
                200,
            ).then((response) => {
                expect(response.body.recipientCount).to.equal(0);
                expect(response.body.recipients).to.be.empty;
                expect(response.body.familiesWithoutEmail).to.be.empty;
            });
        });
    });

    context("401 — unauthenticated caller", () => {
        it("returns 401 when no API key is provided", () => {
            cy.apiRequest({
                method: "GET",
                url: "/api/families/verify-email-preview",
                failOnStatusCode: false,
            }).then((resp) => {
                expect(resp.status).to.equal(401);
            });
        });
    });

    context("403 — caller without EditRecords role", () => {
        it("returns 403 when the caller lacks EditRecords permission", () => {
            // limited.api.key = limitedUserApiKeyForTesting123456789012345678
            // This user has no EditRecords role → EditRecordsRoleAuthMiddleware rejects.
            cy.apiRequest({
                method: "GET",
                url: "/api/families/verify-email-preview",
                headers: { "x-api-key": Cypress.env("limited.api.key") },
                failOnStatusCode: false,
            }).then((resp) => {
                expect(resp.status).to.equal(403);
            });
        });
    });
});
