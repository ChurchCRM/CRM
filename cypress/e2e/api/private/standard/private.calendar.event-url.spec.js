/// <reference types="cypress" />

describe("API event URL field", () => {
    const createdEventIds = [];

    after(() => {
        cy.cleanupEvents(createdEventIds);
    });

    const createEvent = () =>
        cy
            .makePrivateAdminAPICall(
                "POST",
                "/api/events",
                {
                    Title: "Event URL spec",
                    Type: 1,
                    Start: "2030-01-01T09:00:00",
                    End: "2030-01-01T10:00:00",
                    PinnedCalendars: [],
                },
                200,
            )
            .then((res) => {
                createdEventIds.push(res.body.eventId);
                return res.body.eventId;
            });

    const saveUrl = (id, URL) =>
        cy.makePrivateAdminAPICall("POST", `/api/events/${id}`, { Title: "Event URL spec", URL }, 200);

    const storedUrl = (id) =>
        cy.makePrivateAdminAPICall("GET", `/api/events/${id}`, null, 200).then((res) => res.body.URL);

    it("keeps an https URL", () => {
        createEvent().then((id) => {
            saveUrl(id, "https://example.org/join");
            storedUrl(id).should("eq", "https://example.org/join");
        });
    });

    ["javascript:alert(1)", "data:text/html,x", "//example.org", "ftp://example.org"].forEach((bad) => {
        it(`stores nothing for ${bad}`, () => {
            createEvent().then((id) => {
                saveUrl(id, bad);
                storedUrl(id).should("not.be.ok");
            });
        });
    });

    it("ignores columns outside the update allowlist", () => {
        createEvent().then((id) => {
            cy.makePrivateAdminAPICall("POST", `/api/events/${id}`, { Title: "Event URL spec", LocationId: 999999 }, 200);
            cy.makePrivateAdminAPICall("GET", `/api/events/${id}`, null, 200).then((res) => {
                expect(res.body.LocationId).to.not.eq(999999);
            });
        });
    });
});
