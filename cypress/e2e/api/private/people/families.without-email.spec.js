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

    it("returns correct family shape, with an empty deprecated Email", () => {
        cy.get("@familiesWithoutEmail").then((response) => {
            if (response.body.families.length === 0) return;
            const family = response.body.families[0];
            expect(family).to.have.property("Id");
            expect(family).to.have.property("Name");
            expect(family.Email).to.eq("");
        });
    });

    /**
     * Person-level email filtering test.
     *
     * Seeds a family whose member HAS a per_Email, and one whose member has none.
     * The endpoint must return only the second (the HAVING clause must catch the
     * first). Without the fix (two separate ->having() calls overwriting each
     * other), the per_Email constraint would have been silently dropped.
     *
     * Lifecycle:
     *   before()  — enable self-reg, create the two test families
     *   after()   — delete the families (with members), restore self-reg to its captured value
     */
    describe("person-level email filtering (seeded data)", () => {
        let testFamilyId = null;
        let noEmailFamilyId = null;
        let savedSelfReg;

        before(() => {
            cy.getSystemConfig("bEnableSelfRegistration").then((value) => {
                savedSelfReg = value;
            });

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

            cy.request({
                method: "POST",
                url: "/api/public/register/family",
                body: {
                    Name: "CypressTest FamiliesWithoutEmail None",
                    Address1: "2 Cypress Lane",
                    City: "Testville",
                    State: "TS",
                    Country: "US",
                    Zip: "00000",
                    people: [{ firstName: "Cypress", lastName: "NoEmail", gender: 1, role: 1, email: "" }],
                },
            }).then((resp) => {
                expect(resp.status).to.eq(200);
                noEmailFamilyId = resp.body.Id;
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
            if (noEmailFamilyId) {
                cy.makePrivateAdminAPICall("DELETE", `/api/family/${noEmailFamilyId}?deleteMembers=true`, "", 200);
            }
            cy.restoreSystemConfig("bEnableSelfRegistration", savedSelfReg);
        });

        it("includes a family none of whose members has an email", () => {
            cy.makePrivateAdminAPICall("GET", "/api/families/email/without", "", 200).then((response) => {
                expect(response.body.families.map((f) => f.Id)).to.include(noEmailFamilyId);
            });
        });

        it("excludes families whose members have a personal email", () => {
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
