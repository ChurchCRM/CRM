/// <reference types="cypress" />

describe("API Recurring Events: Nth weekday of the month (#10906)", () => {
    const eventTypeId = 1;
    const createdEventIds = [];

    after(() => {
        cy.cleanupEvents(createdEventIds);
    });

    const repeat = (overrides, expectedStatus = 200) =>
        cy
            .makePrivateAdminAPICall(
                "POST",
                "/api/events/repeat",
                {
                    Title: `Nth weekday probe ${Date.now()}`,
                    Type: eventTypeId,
                    StartTime: "19:00",
                    EndTime: "20:00",
                    RecurType: "monthly-weekday",
                    RecurDOW: "Tuesday",
                    RecurWOM: 2,
                    RangeStart: "2041-01-01",
                    RangeEnd: "2041-12-31",
                    ...overrides,
                },
                expectedStatus,
            )
            .then((response) => {
                createdEventIds.push(...(response.body.eventIds || []));
                return response;
            });

    const startDates = (ids) => {
        const dates = [];
        ids.forEach((id) => {
            cy.makePrivateAdminAPICall("GET", `/api/events/${id}`, null, 200).then((resp) => {
                dates.push(String(resp.body.Start ?? resp.body.start).slice(0, 10));
            });
        });
        return cy.wrap(dates, { log: false });
    };

    it("creates one event per month on the 2nd Tuesday", () => {
        repeat({}).then((resp) => {
            expect(resp.body.count).to.eq(12);
            startDates(resp.body.eventIds).then((dates) => {
                expect([...dates].sort()).to.deep.eq([
                    "2041-01-08",
                    "2041-02-12",
                    "2041-03-12",
                    "2041-04-09",
                    "2041-05-14",
                    "2041-06-11",
                    "2041-07-09",
                    "2041-08-13",
                    "2041-09-10",
                    "2041-10-08",
                    "2041-11-12",
                    "2041-12-10",
                ]);
            });
        });
    });

    it("skips months that have no 5th Sunday", () => {
        repeat({ RecurDOW: "Sunday", RecurWOM: 5 }).then((resp) => {
            expect(resp.body.count).to.eq(4);
            startDates(resp.body.eventIds).then((dates) => {
                expect([...dates].sort()).to.deep.eq(["2041-03-31", "2041-06-30", "2041-09-29", "2041-12-29"]);
            });
        });
    });

    it("answers 400 without a week of the month", () => {
        repeat({ RecurWOM: undefined }, 400);
    });

    it("answers 400 for week 6", () => {
        repeat({ RecurWOM: 6 }, 400);
    });

    for (const week of ["2.9", "2abc", 0]) {
        it(`answers 400 for week ${JSON.stringify(week)}`, () => {
            repeat({ RecurWOM: week }, 400).then((resp) => {
                expect(resp.body.message).to.eq("RecurWOM must be a whole number from 1 to 5");
            });
        });
    }

    it("answers 400 for an unknown day of the week", () => {
        repeat({ RecurDOW: "Funday" }, 400);
    });
});
