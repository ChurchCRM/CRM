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
        it("returns email list with emails array and byRole object", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/groups/${groupID}/emails`,
                null,
                200,
            ).then((resp) => {
                expect(resp.body).to.have.property("emails");
                expect(resp.body).to.have.property("byRole");
                expect(resp.body.emails).to.be.an("array");
                expect(resp.body.byRole).to.be.an("object");
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
