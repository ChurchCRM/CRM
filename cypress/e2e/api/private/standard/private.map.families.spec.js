/// <reference types="cypress" />

/**
 * API tests for the Map families endpoint
 * GET /api/map/families — returns geocoded families / group members / cart persons
 * as map marker items for the Leaflet congregation map at /v2/map.
 */
describe("API Private Map", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    describe("GET /api/map/families — all geocoded families (default)", () => {
        it("Returns 200 with an array", () => {
            cy.makePrivateAdminAPICall("GET", "/api/map/families", null, 200).then(
                (response) => {
                    expect(response.body).to.be.an("array");
                },
            );
        });

        it("Each item has the required map marker fields", () => {
            cy.makePrivateAdminAPICall("GET", "/api/map/families", null, 200).then(
                (response) => {
                    expect(response.body.length).to.be.at.least(1);
                    const item = response.body[0];
                    expect(item).to.have.property("id");
                    expect(item).to.have.property("type");
                    expect(item).to.have.property("name");
                    expect(item).to.have.property("salutation");
                    expect(item).to.have.property("address");
                    expect(item).to.have.property("latitude");
                    expect(item).to.have.property("longitude");
                    expect(item).to.have.property("classificationId");
                    expect(item).to.have.property("profileUrl");
                },
            );
        });

        it("Each item has correct field types", () => {
            cy.makePrivateAdminAPICall("GET", "/api/map/families", null, 200).then(
                (response) => {
                    response.body.forEach((item) => {
                        expect(item.id).to.be.a("number");
                        expect(item.type).to.be.a("string");
                        expect(item.name).to.be.a("string");
                        expect(item.salutation).to.be.a("string");
                        expect(item.address).to.be.a("string");
                        expect(item.latitude).to.be.a("number");
                        expect(item.longitude).to.be.a("number");
                        expect(item.classificationId).to.be.a("number");
                        expect(item.profileUrl).to.be.a("string");
                    });
                },
            );
        });

        it("All items have type 'family'", () => {
            cy.makePrivateAdminAPICall("GET", "/api/map/families", null, 200).then(
                (response) => {
                    response.body.forEach((item) => {
                        expect(item.type).to.equal("family");
                    });
                },
            );
        });

        it("All items have non-zero coordinates", () => {
            cy.makePrivateAdminAPICall("GET", "/api/map/families", null, 200).then(
                (response) => {
                    response.body.forEach((item) => {
                        expect(item.latitude).to.not.equal(0);
                        expect(item.longitude).to.not.equal(0);
                    });
                },
            );
        });

        it("profileUrl points to /v2/family/{id}", () => {
            cy.makePrivateAdminAPICall("GET", "/api/map/families", null, 200).then(
                (response) => {
                    response.body.forEach((item) => {
                        expect(item.profileUrl).to.include("/v2/family/" + item.id);
                    });
                },
            );
        });
    });

    describe("GET /api/map/families?groupId=1 — group members", () => {
        it("Returns 200 with an array", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/map/families?groupId=1",
                null,
                200,
            ).then((response) => {
                expect(response.body).to.be.an("array");
            });
        });

        it("Items returned for a group have type 'person'", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/map/families?groupId=1",
                null,
                200,
            ).then((response) => {
                response.body.forEach((item) => {
                    expect(item.type).to.equal("person");
                    expect(item.profileUrl).to.include("PersonID=");
                });
            });
        });
    });

    describe("GET /api/map/families?groupId=0 — cart view", () => {
        it("Returns 200 with an array (empty cart returns empty array)", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/map/families?groupId=0",
                null,
                200,
            ).then((response) => {
                expect(response.body).to.be.an("array");
            });
        });
    });

    describe("Authentication", () => {
        it("Returns 401 when not authenticated", () => {
            cy.apiRequest({
                method: "GET",
                url: "/api/map/families",
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.eq(401);
            });
        });
    });
});
