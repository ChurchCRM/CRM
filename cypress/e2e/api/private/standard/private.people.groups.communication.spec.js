/// <reference types="cypress" />

describe("API Group Communication Endpoints", () => {
    const groupID = 9; // Church Board — has members with emails/phones

    describe("GET /groups/{groupID}/phones", () => {
        it("returns phone list with displayList and phones array", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/groups/${groupID}/phones`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body).to.have.property("phones");
                expect(resp.body).to.have.property("displayList");
                expect(resp.body.phones).to.be.an("array");
                expect(resp.body.displayList).to.be.a("string");
            });
        });

        it("returns per-role phone breakdown", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/groups/${groupID}/phones`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body).to.have.property("roles");
                expect(resp.body.roles).to.be.an("object");
            });
        });

        it("returns 404 for non-existent group", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/groups/999999/phones`,
                null,
                404,
            );
        });
    });

    describe("GET /groups/{groupID}/emails", () => {
        it("returns email list with all and roles", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/groups/${groupID}/emails`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body).to.have.property("all");
                expect(resp.body).to.have.property("roles");
                expect(resp.body.all).to.be.a("string");
                expect(resp.body.roles).to.be.an("object");
            });
        });

        it("returns 404 for non-existent group", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/groups/999999/emails`,
                null,
                404,
            );
        });
    });

    describe("GET /groups/{groupID}/sundayschool/phones", () => {
        it("returns segmented phone data", () => {
            // Group 1 is a Sunday School group in seed data
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/groups/1/sundayschool/phones`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body).to.have.property("all");
                expect(resp.body).to.have.property("teachers");
                expect(resp.body).to.have.property("students");
                expect(resp.body).to.have.property("parents");
                // Each segment should have phones and displayList
                ["all", "teachers", "students", "parents"].forEach((segment) => {
                    expect(resp.body[segment]).to.have.property("phones");
                    expect(resp.body[segment]).to.have.property("displayList");
                    expect(resp.body[segment].phones).to.be.an("array");
                });
            });
        });
    });

    describe("GET /groups/{groupID}/sundayschool/emails", () => {
        it("returns segmented email data", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/groups/1/sundayschool/emails`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body).to.have.property("all");
                expect(resp.body).to.have.property("teachers");
                expect(resp.body).to.have.property("parents");
                expect(resp.body).to.have.property("kids");
                // All should be comma-separated strings
                expect(resp.body.all).to.be.a("string");
                expect(resp.body.teachers).to.be.a("string");
            });
        });
    });
});

describe("API System Properties Endpoint", () => {
    it("GET /system/properties/person returns id/value pairs", () => {
        cy.makePrivateAdminAPICall(
            "GET",
            `/api/system/properties/person`,
            null,
            200,
        ).then((resp) => {
            expect(resp.body).to.be.an("array");
            if (resp.body.length > 0) {
                expect(resp.body[0]).to.have.property("id");
                expect(resp.body[0]).to.have.property("value");
                expect(resp.body[0].id).to.be.a("number");
                expect(resp.body[0].value).to.be.a("string");
            }
        });
    });

    it("non-admin is denied access", () => {
        cy.makePrivateUserAPICall(
            "GET",
            `/api/system/properties/person`,
            null,
            [401, 403],
        );
    });
});
