/// <reference types="cypress" />

/**
 * API tests for Fundraiser CRUD endpoints
 *
 * Covers:
 *   GET    /api/fundraisers
 *   POST   /api/fundraisers
 *   GET    /api/fundraisers/{id}
 *   PUT    /api/fundraisers/{id}
 *   DELETE /api/fundraisers/{id}
 */
describe("API Private Fundraisers", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    describe("GET /api/fundraisers - List fundraisers", () => {
        it("Returns 200 with fundraisers array", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/fundraisers",
                null,
                200,
            ).then((response) => {
                expect(response.body).to.have.property("fundraisers");
                expect(response.body.fundraisers).to.be.an("array");
            });
        });
    });

    describe("POST /api/fundraisers - Create fundraiser", () => {
        it("Creates a fundraiser and returns 201 with fundraiser object", () => {
            const title = `Cypress Test Fundraiser ${Date.now()}`;
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/fundraisers",
                {
                    title,
                    description: "Created by Cypress",
                    date: "2026-06-15",
                },
                201,
            ).then((response) => {
                expect(response.body).to.have.property("fundraiser");
                const fr = response.body.fundraiser;
                expect(fr.id).to.be.a("number");
                expect(fr.title).to.equal(title);
                expect(fr.description).to.equal("Created by Cypress");
                expect(fr.date).to.equal("2026-06-15");
                expect(fr.enteredBy).to.be.a("number");

                // Clean up
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/fundraisers/${fr.id}`,
                    null,
                    200,
                );
            });
        });

        it("Defaults date to today when omitted", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/fundraisers",
                { title: `Cypress FR no-date ${Date.now()}` },
                201,
            ).then((response) => {
                const fr = response.body.fundraiser;
                expect(fr.date).to.match(/^\d{4}-\d{2}-\d{2}$/);
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/fundraisers/${fr.id}`,
                    null,
                    200,
                );
            });
        });

        it("Returns 400 when title is empty", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/fundraisers",
                { title: "", description: "x" },
                400,
            );
        });

        it("Returns 400 for invalid date", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/fundraisers",
                { title: "Bad date", date: "2026-13-40" },
                400,
            );
        });
    });

    describe("GET /api/fundraisers/{id} - Get fundraiser", () => {
        it("Returns 404 for non-existent fundraiser", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                "/api/fundraisers/999999",
                null,
                404,
            );
        });

        it("Round-trips a created fundraiser via GET", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/fundraisers",
                { title: `Cypress FR GET ${Date.now()}` },
                201,
            ).then((createResp) => {
                const id = createResp.body.fundraiser.id;
                cy.makePrivateAdminAPICall(
                    "GET",
                    `/api/fundraisers/${id}`,
                    null,
                    200,
                ).then((getResp) => {
                    expect(getResp.body.fundraiser.id).to.equal(id);
                });
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/fundraisers/${id}`,
                    null,
                    200,
                );
            });
        });
    });

    describe("PUT /api/fundraisers/{id} - Update fundraiser", () => {
        it("Returns 404 when updating a non-existent fundraiser", () => {
            cy.makePrivateAdminAPICall(
                "PUT",
                "/api/fundraisers/999999",
                { title: "nope" },
                404,
            );
        });

        it("Updates title, description, and date", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/fundraisers",
                { title: `Cypress FR PUT ${Date.now()}` },
                201,
            ).then((createResp) => {
                const id = createResp.body.fundraiser.id;
                cy.makePrivateAdminAPICall(
                    "PUT",
                    `/api/fundraisers/${id}`,
                    {
                        title: "Updated Title",
                        description: "Updated Description",
                        date: "2026-12-31",
                    },
                    200,
                ).then((updateResp) => {
                    expect(updateResp.body.fundraiser.title).to.equal(
                        "Updated Title",
                    );
                    expect(updateResp.body.fundraiser.description).to.equal(
                        "Updated Description",
                    );
                    expect(updateResp.body.fundraiser.date).to.equal(
                        "2026-12-31",
                    );
                });
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/fundraisers/${id}`,
                    null,
                    200,
                );
            });
        });

        it("Returns 400 when clearing title", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/fundraisers",
                { title: `Cypress FR empty-title ${Date.now()}` },
                201,
            ).then((createResp) => {
                const id = createResp.body.fundraiser.id;
                cy.makePrivateAdminAPICall(
                    "PUT",
                    `/api/fundraisers/${id}`,
                    { title: "" },
                    400,
                );
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/fundraisers/${id}`,
                    null,
                    200,
                );
            });
        });
    });

    describe("DELETE /api/fundraisers/{id} - Delete fundraiser", () => {
        // TODO: add 409 coverage for "fundraiser has donated items" once a
        // DonatedItem REST API exists to seed the dependency. Seed data has
        // fundraiser id=1 but zero donated items, so a 409 test against seed
        // data is not reliable. The PHP route already blocks via
        // DonatedItemQuery::filterByFrId()->count().

        it("Returns 404 for non-existent fundraiser", () => {
            cy.makePrivateAdminAPICall(
                "DELETE",
                "/api/fundraisers/999999",
                null,
                404,
            );
        });

        it("Deletes an existing fundraiser", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/fundraisers",
                { title: `Cypress FR DELETE ${Date.now()}` },
                201,
            ).then((createResp) => {
                const id = createResp.body.fundraiser.id;
                cy.makePrivateAdminAPICall(
                    "DELETE",
                    `/api/fundraisers/${id}`,
                    null,
                    200,
                ).then((delResp) => {
                    expect(delResp.body).to.have.property("success", true);
                });
                cy.makePrivateAdminAPICall(
                    "GET",
                    `/api/fundraisers/${id}`,
                    null,
                    404,
                );
            });
        });
    });

    describe("Tier-1 fields (endDate/status/goalAmount/type/fundId)", () => {
        it("Creates a fundraiser with all Tier-1 fields and round-trips them", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/fundraisers",
                {
                    title: `Cypress FR fields ${Date.now()}`,
                    date: "2026-06-01",
                    endDate: "2026-06-03",
                    status: "Planning",
                    type: "Gala",
                    goalAmount: 1500,
                    fundId: 2,
                },
                201,
            ).then((response) => {
                const fr = response.body.fundraiser;
                expect(fr.endDate).to.equal("2026-06-03");
                expect(fr.status).to.equal("Planning");
                expect(fr.type).to.equal("Gala");
                expect(fr.goalAmount).to.equal(1500);
                expect(fr.fundId).to.equal(2);
                cy.makePrivateAdminAPICall("DELETE", `/api/fundraisers/${fr.id}`, null, 200);
            });
        });

        it("Returns 400 for an invalid status value", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/fundraisers",
                { title: `Cypress FR bad-status ${Date.now()}`, status: "Cancelled" },
                400,
            );
        });

        it("Returns 400 for an invalid type value", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/fundraisers",
                { title: `Cypress FR bad-type ${Date.now()}`, type: "Bake Sale" },
                400,
            );
        });

        it("Returns 400 for a negative goalAmount", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/fundraisers",
                { title: `Cypress FR neg-goal ${Date.now()}`, goalAmount: -50 },
                400,
            );
        });

        it("Returns 400 for a non-numeric goalAmount", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/fundraisers",
                { title: `Cypress FR nan-goal ${Date.now()}`, goalAmount: "abc" },
                400,
            );
        });

        it("Returns 400 for an invalid endDate format", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/fundraisers",
                { title: `Cypress FR bad-enddate ${Date.now()}`, date: "2026-06-01", endDate: "not-a-date" },
                400,
            );
        });

        it("Returns 400 when endDate precedes the start date", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/fundraisers",
                { title: `Cypress FR enddate-before-start ${Date.now()}`, date: "2026-06-10", endDate: "2026-06-01" },
                400,
            );
        });

        it("Coerces a non-positive fundId to null instead of storing it", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/fundraisers",
                { title: `Cypress FR bad-fund ${Date.now()}`, fundId: -1 },
                201,
            ).then((response) => {
                const fr = response.body.fundraiser;
                expect(fr.fundId).to.be.null;
                cy.makePrivateAdminAPICall("DELETE", `/api/fundraisers/${fr.id}`, null, 200);
            });
        });

        it("Updates Tier-1 fields via PUT", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/fundraisers",
                { title: `Cypress FR PUT-fields ${Date.now()}`, date: "2026-05-01" },
                201,
            ).then((createResp) => {
                const id = createResp.body.fundraiser.id;
                cy.makePrivateAdminAPICall(
                    "PUT",
                    `/api/fundraisers/${id}`,
                    { status: "Closed", type: "Raffle", goalAmount: 250, fundId: 3 },
                    200,
                ).then((updateResp) => {
                    const fr = updateResp.body.fundraiser;
                    expect(fr.status).to.equal("Closed");
                    expect(fr.type).to.equal("Raffle");
                    expect(fr.goalAmount).to.equal(250);
                    expect(fr.fundId).to.equal(3);
                });
                cy.makePrivateAdminAPICall("DELETE", `/api/fundraisers/${id}`, null, 200);
            });
        });
    });

    describe("Fundraisers system calendar", () => {
        it("Appears in the system calendar list", () => {
            cy.makePrivateAdminAPICall("GET", "/api/systemcalendars", null, 200).then((response) => {
                const calendar = response.body.Calendars.find((c) => c.Name === "Fundraisers");
                expect(calendar).to.exist;
                expect(calendar).to.have.property("Id");
            });
        });

        it("Returns a fundraiser as a calendar event within its date range", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/fundraisers",
                { title: `Cypress FR Calendar ${Date.now()}`, date: "2026-09-15" },
                201,
            ).then((createResp) => {
                const fr = createResp.body.fundraiser;
                cy.makePrivateAdminAPICall("GET", "/api/systemcalendars", null, 200).then((listResp) => {
                    const calendar = listResp.body.Calendars.find((c) => c.Name === "Fundraisers");
                    cy.makePrivateAdminAPICall(
                        "GET",
                        `/api/systemcalendars/${calendar.Id}/fullcalendar?start=2026-09-01&end=2026-09-30`,
                        null,
                        200,
                    ).then((eventsResp) => {
                        const match = eventsResp.body.find((e) => e.id === String(fr.id));
                        expect(match).to.exist;
                        expect(match.title).to.include(fr.title);
                        expect(match.url).to.include(`/fundraiser/view/${fr.id}`);
                        expect(match.allDay).to.be.true;
                    });
                });
                cy.makePrivateAdminAPICall("DELETE", `/api/fundraisers/${fr.id}`, null, 200);
            });
        });

        it("Excludes a fundraiser outside the requested date range", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/api/fundraisers",
                { title: `Cypress FR OutOfRange ${Date.now()}`, date: "2026-01-05" },
                201,
            ).then((createResp) => {
                const fr = createResp.body.fundraiser;
                cy.makePrivateAdminAPICall("GET", "/api/systemcalendars", null, 200).then((listResp) => {
                    const calendar = listResp.body.Calendars.find((c) => c.Name === "Fundraisers");
                    cy.makePrivateAdminAPICall(
                        "GET",
                        `/api/systemcalendars/${calendar.Id}/fullcalendar?start=2026-09-01&end=2026-09-30`,
                        null,
                        200,
                    ).then((eventsResp) => {
                        const match = eventsResp.body.find((e) => e.id === String(fr.id));
                        expect(match).to.be.undefined;
                    });
                });
                cy.makePrivateAdminAPICall("DELETE", `/api/fundraisers/${fr.id}`, null, 200);
            });
        });
    });

    describe("Access control", () => {
        it("Returns 401 when no API key is provided", () => {
            cy.request({
                method: "GET",
                url: "/api/fundraisers",
                failOnStatusCode: false,
                headers: { "content-type": "application/json" },
            }).then((response) => {
                expect(response.status).to.equal(401);
            });
        });

        it("Denies a caller without Manage Fundraisers permission", () => {
            // Uses a dedicated seed user (per_ID=96) who has Finance=1 but
            // ManageFundraisers=0, proving the ManageFundraisers gate fires
            // independently of the Finance role.
            cy.makePrivateNoManageFundraisersAPICall(
                "GET",
                "/api/fundraisers",
                null,
                [401, 403],
            );
        });
    });
});
