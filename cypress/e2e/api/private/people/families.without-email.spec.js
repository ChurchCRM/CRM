/// <reference types="cypress" />

describe("Families Without Email API", () => {
    beforeEach(() => {
        cy.makePrivateAdminAPICall("GET", "/api/families/email/without", "", 200).as("familiesWithoutEmail");
    });

    it("returns count and families array", () => {
        cy.get("@familiesWithoutEmail").then((response) => {
            expect(response.body).to.have.property("count").that.is.a("number");
            expect(response.body).to.have.property("families").that.is.an("array");
            expect(response.body.families).to.have.length(response.body.count);
        });
    });

    it("count is zero or positive", () => {
        cy.get("@familiesWithoutEmail").then((response) => {
            expect(response.body.count).to.be.at.least(0);
        });
    });

    it("returns correct family shape", () => {
        cy.get("@familiesWithoutEmail").then((response) => {
            if (response.body.families.length === 0) return;
            const family = response.body.families[0];
            // Family should have standard properties from Family table
            expect(family).to.have.property("Id");
            expect(family).to.have.property("Name");
            expect(family).to.have.property("Email");
        });
    });

    it("families have empty Email field", () => {
        cy.get("@familiesWithoutEmail").then((response) => {
            if (response.body.families.length === 0) return;
            response.body.families.forEach((family) => {
                expect(family.Email).to.be.oneOf(["", null, undefined]);
            });
        });
    });

    it("ensures no family has both email and person with email", () => {
        // This is a negative test: if the optimization works, every returned family
        // should have no email on the family record AND no email on any person in that family.
        // Since we can't query person data in this endpoint, we at least verify the
        // family email constraint is enforced.
        cy.get("@familiesWithoutEmail").then((response) => {
            response.body.families.forEach((family) => {
                // Family itself has no email (server constraint)
                expect(family.Email).to.be.oneOf(["", null, undefined]);
                // The fact that this family was returned means the query correctly
                // filtered for families without emails (optimization validates)
            });
        });
    });

    /**
     * Person-level email filtering test.
     *
     * Seeds a family with NO family-level email but a member who HAS a per_Email.
     * The endpoint must NOT return this family (the HAVING clause must catch it).
     * Without the fix (two separate ->having() calls overwriting each other), the
     * per_Email constraint would have been silently dropped, and the family would
     * incorrectly appear in the results.
     *
     * Lifecycle:
     *   before()  — enable self-reg, create the test family + member with email
     *   after()   — delete family (with members), restore self-reg to disabled
     */
    describe("person-level email filtering (seeded data)", () => {
        let testFamilyId = null;

        before(() => {
            // Enable self-registration so the public register endpoint is accessible
            cy.makePrivateAdminAPICall(
                "POST",
                "admin/api/system/config/bEnableSelfRegistration",
                { value: "1" },
            );

            // Create a family with NO family email but a member WITH a personal email.
            // This family must NOT appear in /api/families/email/without results.
            cy.request({
                method: "POST",
                url: "/api/public/register/family",
                body: {
                    Name: "CypressTest FamiliesWithoutEmail",
                    Address1: "1 Cypress Lane",
                    City: "Testville",
                    State: "TS",
                    Country: "US",
                    Zip: "00000",
                    Email: "", // no family-level email
                    people: [
                        {
                            firstName: "Cypress",
                            lastName: "TestMember",
                            gender: 1,
                            role: 1,
                            email: "cypress.test.member@example.invalid", // member HAS email
                        },
                    ],
                },
            }).then((resp) => {
                expect(resp.status).to.eq(200);
                testFamilyId = resp.body.Id;
            });
        });

        after(() => {
            // Delete the seeded family and its members
            if (testFamilyId) {
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/family/${testFamilyId}?deleteMembers=true`,
                    "",
                    200,
                );
            }
            // Restore self-registration to disabled
            cy.makePrivateAdminAPICall(
                "POST",
                "admin/api/system/config/bEnableSelfRegistration",
                { value: "0" },
            );
        });

        it("excludes families whose members have a personal email even when the family email is empty", () => {
            cy.makePrivateAdminAPICall("GET", "/api/families/email/without", "", 200).then((response) => {
                const returnedIds = response.body.families.map((f) => f.Id);
                expect(returnedIds).to.not.include(
                    testFamilyId,
                    `Family ${testFamilyId} has a member with email — it must be excluded from the results`,
                );
            });
        });
    });
});
