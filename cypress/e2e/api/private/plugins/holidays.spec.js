/**
 * Holiday Calendar Plugin — API tests
 *
 * Covers:
 *  - Plugin enable/disable via management API
 *  - Settings save (countries + categories)
 *  - System calendar list reflects plugin state
 *  - FullCalendar endpoint returns events with extendedProps (country, type)
 *  - Category filtering is honoured
 *  - Date-range filtering is honoured
 */
describe("Holiday Calendar Plugin API", () => {
    // Restore plugin to a known state after every test so tests don't bleed
    afterEach(() => {
        cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/holidays/settings", {
            settings: { countries: "USA", categories: "official,religious" },
        });
    });

    // -------------------------------------------------------------------------
    // Plugin management
    // -------------------------------------------------------------------------
    describe("Enable / disable", () => {
        it("enables the holidays plugin", () => {
            cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/holidays/enable").then((resp) => {
                expect(resp.status).to.eq(200);
                expect(resp.body).to.have.property("success", true);
            });
        });

        it("disables the holidays plugin", () => {
            cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/holidays/disable").then((resp) => {
                expect(resp.status).to.eq(200);
                expect(resp.body).to.have.property("success", true);
            });

            // Re-enable so afterEach settings call doesn't confuse subsequent tests
            cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/holidays/enable");
        });
    });

    // -------------------------------------------------------------------------
    // Settings
    // -------------------------------------------------------------------------
    describe("Settings save", () => {
        before(() => {
            cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/holidays/enable");
        });

        it("saves multiple countries and categories", () => {
            cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/holidays/settings", {
                settings: { countries: "USA,Canada", categories: "official,religious" },
            }).then((resp) => {
                expect(resp.status).to.eq(200);
                expect(resp.body).to.have.property("success", true);
            });
        });

        it("saves a single country with empty categories", () => {
            cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/holidays/settings", {
                settings: { countries: "Germany", categories: "" },
            }).then((resp) => {
                expect(resp.status).to.eq(200);
                expect(resp.body).to.have.property("success", true);
            });
        });

        it("returns 404 for a non-existent plugin id", () => {
            cy.makePrivateAdminAPICall(
                "POST",
                "/plugins/api/plugins/non-existent-holidays/settings",
                { settings: { countries: "USA" } },
                404,
            );
        });
    });

    // -------------------------------------------------------------------------
    // System calendar list
    // -------------------------------------------------------------------------
    describe("System calendar list", () => {
        it("includes a holiday calendar when plugin is enabled with USA", () => {
            cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/holidays/enable");
            cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/holidays/settings", {
                settings: { countries: "USA", categories: "" },
            });

            cy.makePrivateAdminAPICall("GET", "/api/systemcalendars").then((resp) => {
                expect(resp.status).to.eq(200);
                const names = resp.body.Calendars.map((c) => c.Name);
                expect(names.some((n) => n.toLowerCase().includes("holidays"))).to.be.true;
            });
        });

        it("includes one calendar per configured country", () => {
            cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/holidays/enable");
            cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/holidays/settings", {
                settings: { countries: "USA,Germany", categories: "" },
            });

            cy.makePrivateAdminAPICall("GET", "/api/systemcalendars").then((resp) => {
                expect(resp.status).to.eq(200);
                const holidayNames = resp.body.Calendars.map((c) => c.Name).filter((n) =>
                    n.toLowerCase().includes("holidays"),
                );
                expect(holidayNames.length).to.be.at.least(2);
            });
        });

        it("removes holiday calendars when plugin is disabled", () => {
            cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/holidays/disable");

            cy.makePrivateAdminAPICall("GET", "/api/systemcalendars").then((resp) => {
                expect(resp.status).to.eq(200);
                const names = resp.body.Calendars.map((c) => c.Name);
                expect(names.some((n) => n.toLowerCase().includes("holidays"))).to.be.false;
            });

            cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/holidays/enable");
        });

        it("assigns distinct background colours for different countries", () => {
            cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/holidays/enable");
            cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/holidays/settings", {
                settings: { countries: "USA,Germany", categories: "" },
            });

            cy.makePrivateAdminAPICall("GET", "/api/systemcalendars").then((resp) => {
                const holidayCalendars = resp.body.Calendars.filter((c) =>
                    c.Name.toLowerCase().includes("holidays"),
                );
                expect(holidayCalendars.length).to.be.at.least(2);
                const colors = holidayCalendars.map((c) => c.BackgroundColor);
                expect(new Set(colors).size).to.eq(colors.length);
            });
        });

        it("uses a friendly display name — no raw CamelCase internal names", () => {
            cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/holidays/enable");
            cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/holidays/settings", {
                settings: { countries: "UnitedKingdom", categories: "" },
            });

            cy.makePrivateAdminAPICall("GET", "/api/systemcalendars").then((resp) => {
                const ukName = resp.body.Calendars.map((c) => c.Name).find((n) =>
                    n.toLowerCase().includes("holidays"),
                );
                expect(ukName).to.exist;
                expect(ukName).to.match(/United Kingdom/);
                expect(ukName).not.to.include("UnitedKingdom");
            });
        });
    });

    // -------------------------------------------------------------------------
    // FullCalendar events — extendedProps, date range, editability
    // -------------------------------------------------------------------------
    describe("FullCalendar events", () => {
        let usaCalendarId;

        before(() => {
            cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/holidays/enable");
            cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/holidays/settings", {
                settings: { countries: "USA", categories: "" },
            });

            cy.makePrivateAdminAPICall("GET", "/api/systemcalendars").then((resp) => {
                const cal = resp.body.Calendars.find((c) => c.Name.toLowerCase().includes("holidays"));
                expect(cal, "USA holiday calendar must exist").to.exist;
                usaCalendarId = cal.Id;
            });
        });

        it("returns events with country and type in extendedProps", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/systemcalendars/${usaCalendarId}/fullcalendar?start=2025-01-01&end=2025-02-01`,
            ).then((resp) => {
                expect(resp.status).to.eq(200);
                expect(resp.body).to.be.an("array").and.have.length.greaterThan(0);

                const event = resp.body[0];
                expect(event).to.have.property("title").and.be.a("string");
                expect(event).to.have.property("start").and.be.a("string");
                expect(event).to.have.property("extendedProps");
                expect(event.extendedProps).to.have.property("country", "USA");
                expect(event.extendedProps).to.have.property("type").and.be.a("string");
            });
        });

        it("all returned events fall within the requested date range", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/systemcalendars/${usaCalendarId}/fullcalendar?start=2025-07-01&end=2025-08-01`,
            ).then((resp) => {
                expect(resp.status).to.eq(200);
                resp.body.forEach((event) => {
                    const d = new Date(event.start);
                    expect(d.getFullYear()).to.eq(2025);
                    expect(d.getMonth()).to.eq(6); // July (0-indexed)
                });
            });
        });

        it("spans multiple years when range crosses a year boundary", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/systemcalendars/${usaCalendarId}/fullcalendar?start=2024-12-01&end=2025-02-01`,
            ).then((resp) => {
                expect(resp.status).to.eq(200);
                const years = new Set(resp.body.map((e) => new Date(e.start).getFullYear()));
                expect(years.has(2024)).to.be.true;
                expect(years.has(2025)).to.be.true;
            });
        });

        it("marks all events as non-editable", () => {
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/systemcalendars/${usaCalendarId}/fullcalendar?start=2025-01-01&end=2025-02-01`,
            ).then((resp) => {
                expect(resp.status).to.eq(200);
                resp.body.forEach((event) => {
                    expect(event.editable).to.be.false;
                });
            });
        });
    });

    // -------------------------------------------------------------------------
    // Category filtering
    // Netherlands is used here because it has both official + observance holidays,
    // making it suitable for tests that assert type variety and filtering reduces count.
    // USA Yasumi only provides official-type holidays.
    // -------------------------------------------------------------------------
    describe("Category filtering", () => {
        let netherlandsCalendarId;

        before(() => {
            cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/holidays/enable");
            cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/holidays/settings", {
                settings: { countries: "Netherlands", categories: "" },
            });
            cy.makePrivateAdminAPICall("GET", "/api/systemcalendars").then((resp) => {
                const cal = resp.body.Calendars.find((c) => c.Name.toLowerCase().includes("holidays"));
                netherlandsCalendarId = cal.Id;
            });
        });

        it("returns multiple event types when categories is empty (show all)", () => {
            cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/holidays/settings", {
                settings: { countries: "Netherlands", categories: "" },
            });

            cy.makePrivateAdminAPICall(
                "GET",
                `/api/systemcalendars/${netherlandsCalendarId}/fullcalendar?start=2025-01-01&end=2026-01-01`,
            ).then((resp) => {
                expect(resp.status).to.eq(200);
                expect(resp.body.length).to.be.greaterThan(0);
                const types = new Set(resp.body.map((e) => e.extendedProps?.type));
                // Netherlands has official + observance types
                expect(types.size).to.be.at.least(2);
            });
        });

        it("filters to only official events when categories=official", () => {
            cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/holidays/settings", {
                settings: { countries: "Netherlands", categories: "official" },
            });

            cy.makePrivateAdminAPICall(
                "GET",
                `/api/systemcalendars/${netherlandsCalendarId}/fullcalendar?start=2025-01-01&end=2026-01-01`,
            ).then((resp) => {
                expect(resp.status).to.eq(200);
                expect(resp.body.length).to.be.greaterThan(0);
                resp.body.forEach((event) => {
                    expect(event.extendedProps?.type?.toLowerCase()).to.eq("official");
                });
            });
        });

        it("returns fewer events with a narrow category than with no filter", () => {
            let allCount;

            cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/holidays/settings", {
                settings: { countries: "Netherlands", categories: "" },
            });
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/systemcalendars/${netherlandsCalendarId}/fullcalendar?start=2025-01-01&end=2026-01-01`,
            ).then((resp) => {
                allCount = resp.body.length;
            });

            cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/holidays/settings", {
                settings: { countries: "Netherlands", categories: "official" },
            });
            cy.makePrivateAdminAPICall(
                "GET",
                `/api/systemcalendars/${netherlandsCalendarId}/fullcalendar?start=2025-01-01&end=2026-01-01`,
            ).then((resp) => {
                // Filtering to official only must exclude observance holidays
                expect(resp.body.length).to.be.lessThan(allCount);
            });
        });
    });
});
