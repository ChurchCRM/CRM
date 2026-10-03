/// <reference types="cypress" />

describe("API Private Self-Register Review", () => {
    const created = { persons: [], families: [] };

    const unique = (prefix) => `${prefix}${Date.now()}${Math.floor(Math.random() * 1000)}`;

    const registerPerson = () =>
        cy
            .request({
                method: "POST",
                url: "/api/public/register/person",
                body: {
                    firstName: "Cypress",
                    lastName: unique("Pending"),
                    email: "pending@example.com",
                    gender: 1,
                },
            })
            .then((resp) => {
                expect(resp.status).to.eq(200);
                created.persons.push(resp.body.Id);
                return resp.body.Id;
            });

    const registerFamily = () =>
        cy
            .request({
                method: "POST",
                url: "/api/public/register/family",
                body: {
                    Name: unique("PendingFam"),
                    Address1: "1 Review St",
                    Address2: "",
                    City: "Testville",
                    State: "TS",
                    Country: "US",
                    Zip: "12345",
                    HomePhone: "(555) 123-4567",
                    Email: "pendingfam@example.com",
                    people: [
                        {
                            firstName: "Fam",
                            lastName: "Member",
                            gender: 1,
                            role: 1,
                            email: "member@example.com",
                            cellPhone: "",
                            homePhone: "",
                            workPhone: "",
                            birthday: "01/15/1980",
                            hideAge: false,
                        },
                    ],
                },
            })
            .then((resp) => {
                expect(resp.status).to.eq(200);
                created.families.push(resp.body.Id);
                return resp.body.Id;
            });

    const pendingPersonIds = () =>
        cy
            .makePrivateAdminAPICall("GET", "/api/persons/self-register", null, 200)
            .then((response) => response.body.people.filter((p) => p.NeedsReview).map((p) => p.Id));

    const pendingFamilyIds = () =>
        cy
            .makePrivateAdminAPICall("GET", "/api/families/self-register", null, 200)
            .then((response) => response.body.families.filter((f) => f.NeedsReview).map((f) => f.Id));

    const pendingCount = () =>
        cy
            .makePrivateAdminAPICall("GET", "/api/persons/self-register/count", null, 200)
            .then((response) => response.body.count);

    after(() => {
        created.persons.forEach((id) => {
            cy.makePrivateAdminAPICall("DELETE", `/api/person/${id}`, null, [200, 404]);
        });
        created.families.forEach((id) => {
            cy.makePrivateAdminAPICall("DELETE", `/api/family/${id}?deleteMembers=true`, null, [200, 404]);
        });
    });

    it("Flags a public person registration as pending review", () => {
        registerPerson().then((id) => {
            pendingPersonIds().then((ids) => expect(ids).to.include(id));
        });
    });

    it("Flags a public family registration as pending review", () => {
        registerFamily().then((id) => {
            pendingFamilyIds().then((ids) => expect(ids).to.include(id));
        });
    });

    it("Lists each pending family's member names", () => {
        registerFamily().then((id) => {
            cy.makePrivateAdminAPICall("GET", "/api/families/self-register", null, 200).then((response) => {
                const family = response.body.families.find((f) => f.Id === id);
                expect(family.Members).to.deep.equal(["Fam"]);
                expect(family.MemberEmails).to.include("member@example.com");
            });
        });
    });

    it("Reports approved and total counts alongside pending", () => {
        registerPerson().then((personId) => {
            cy.makePrivateAdminAPICall("GET", "/api/persons/self-register/count", null, 200).then((before) => {
                cy.makePrivateAdminAPICall("POST", `/api/person/${personId}/approve-review`, null, 200);
                cy.makePrivateAdminAPICall("GET", "/api/persons/self-register/count", null, 200).then((after) => {
                    expect(after.body.count).to.eq(before.body.count - 1);
                    expect(after.body.approved).to.eq(before.body.approved + 1);
                    expect(after.body.total).to.eq(before.body.total);
                });
            });
        });
    });

    it("Rejects a second single approve of the same person", () => {
        registerPerson().then((personId) => {
            cy.makePrivateAdminAPICall("POST", `/api/person/${personId}/approve-review`, null, 200);
            cy.makePrivateAdminAPICall("POST", `/api/person/${personId}/approve-review`, null, 400);
        });
    });

    it("Counts pending registrations", () => {
        pendingCount().then((before) => {
            registerPerson();
            pendingCount().then((after) => expect(after).to.eq(before + 1));
        });
    });

    it("Batch approve clears families and people and updates the count", () => {
        registerPerson().then((personId) => {
            registerFamily().then((familyId) => {
                pendingCount().then((before) => {
                    cy.makePrivateAdminAPICall(
                        "POST",
                        "/api/persons/self-register/approve",
                        { families: [familyId], persons: [personId] },
                        200,
                    ).then((response) => {
                        expect(response.body).to.have.property("success", true);
                        // the family counts once, plus the standalone person
                        expect(response.body.approved).to.eq(2);
                    });

                    pendingPersonIds().then((ids) => expect(ids).to.not.include(personId));
                    pendingFamilyIds().then((ids) => expect(ids).to.not.include(familyId));
                    pendingCount().then((after) => expect(after).to.eq(before - 2));
                });
            });
        });
    });

    it("Batch approve a second time approves nothing", () => {
        registerPerson().then((personId) => {
            const body = { families: [], persons: [personId] };
            cy.makePrivateAdminAPICall("POST", "/api/persons/self-register/approve", body, 200);
            cy.makePrivateAdminAPICall("POST", "/api/persons/self-register/approve", body, 200).then((response) => {
                expect(response.body.approved).to.eq(0);
            });
        });
    });

    it("Batch approve ignores ids that are not self-registered", () => {
        // person 1 (Church Admin) and family 1 (Campbell) are staff-created
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/persons/self-register/approve",
            { families: [1], persons: [1] },
            200,
        ).then((response) => {
            expect(response.body.approved).to.eq(0);
        });
    });

    it("Batch approve tolerates an empty request", () => {
        cy.makePrivateAdminAPICall("POST", "/api/persons/self-register/approve", {}, 200).then((response) => {
            expect(response.body.approved).to.eq(0);
        });
    });

    it("Batch approve is refused for a user without EditRecords", () => {
        registerPerson().then((personId) => {
            cy.makePrivateNoPermAPICall(
                "POST",
                "/api/persons/self-register/approve",
                { families: [], persons: [personId] },
                403,
            );
            pendingPersonIds().then((ids) => expect(ids).to.include(personId));
        });
    });

    it("Single approve removes a person from the pending list", () => {
        registerPerson().then((personId) => {
            cy.makePrivateAdminAPICall("POST", `/api/person/${personId}/approve-review`, null, 200);
            pendingPersonIds().then((ids) => expect(ids).to.not.include(personId));
        });
    });
});
