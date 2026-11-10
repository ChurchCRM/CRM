/// <reference types="cypress" />

// noperm.user (id 901) has no permission flags: it stands in for the lowest-privilege staff login.
describe("API responses trimmed for a low-privilege user (#10205)", () => {
    const FUNDRAISERS_CALENDAR = 2;
    const PERSON_WITH_ATTENDANCE = 104;
    const PERSON_WITH_PHOTO = 1;
    const FAMILY_WITH_PHOTO = 42;

    describe("GET /api/calendars", () => {
        before(() => {
            cy.makePrivateAdminAPICall("POST", "/api/calendars/1/NewAccessToken", null, 200);
        });

        after(() => {
            cy.makePrivateAdminAPICall("DELETE", "/api/calendars/1/AccessToken", null, 200);
        });

        it("withholds the public-feed access token from a user who cannot manage events", () => {
            cy.makePrivateNoPermAPICall("GET", "/api/calendars", null, 200).then((resp) => {
                const calendar = resp.body.Calendars.find((c) => c.Id === 1);
                expect(calendar.Name).to.be.a("string");
                expect(calendar.AccessToken).to.equal(null);
            });
            cy.makePrivateNoPermAPICall("GET", "/api/calendars/1", null, 200).then((resp) => {
                expect(resp.body.Calendars[0].AccessToken).to.equal(null);
            });
        });

        it("returns the access token to an admin", () => {
            cy.makePrivateAdminAPICall("GET", "/api/calendars/1", null, 200).then((resp) => {
                expect(resp.body.Calendars[0].AccessToken).to.be.a("string").and.not.be.empty;
            });
        });
    });

    describe("/api/systemcalendars", () => {
        it("hides the fundraiser calendar from a user who cannot see fundraisers", () => {
            cy.makePrivateNoPermAPICall("GET", "/api/systemcalendars", null, 200).then((resp) => {
                expect(resp.body.Calendars.map((c) => c.Id)).not.to.include(FUNDRAISERS_CALENDAR);
            });
            cy.makePrivateNoPermAPICall("GET", `/api/systemcalendars/${FUNDRAISERS_CALENDAR}/events`, null, 404);
            cy.makePrivateNoPermAPICall("GET", `/api/systemcalendars/${FUNDRAISERS_CALENDAR}/events/1`, null, 404);
            cy.makePrivateNoPermAPICall("GET", `/api/systemcalendars/${FUNDRAISERS_CALENDAR}/fullcalendar`, null, 404);
        });

        it("still lists the fundraiser calendar for an admin", () => {
            cy.makePrivateAdminAPICall("GET", "/api/systemcalendars", null, 200).then((resp) => {
                expect(resp.body.Calendars.map((c) => c.Id)).to.include(FUNDRAISERS_CALENDAR);
            });
            cy.makePrivateAdminAPICall("GET", `/api/systemcalendars/${FUNDRAISERS_CALENDAR}/events/1`, null, 200);
        });
    });

    describe("GET /api/timeline/person/{id}", () => {
        const calendarItems = (resp) => resp.body.timeline.filter((item) => item.type === "cal");

        it("leaves out attended events, as /api/attendance/person/{id} does", () => {
            cy.makePrivateNoPermAPICall("GET", `/api/attendance/person/${PERSON_WITH_ATTENDANCE}`, null, 403);
            cy.makePrivateNoPermAPICall("GET", `/api/timeline/person/${PERSON_WITH_ATTENDANCE}`, null, 200).then((resp) => {
                expect(calendarItems(resp)).to.have.length(0);
            });
        });

        it("includes attended events for a user who can edit the person", () => {
            cy.makePrivateAdminAPICall("GET", `/api/timeline/person/${PERSON_WITH_ATTENDANCE}`, null, 200).then((resp) => {
                expect(calendarItems(resp)).to.have.length.greaterThan(0);
            });
        });
    });

    describe("GET /api/person/{id} with the hide-age setting", () => {
        const lastName = `HiddenAge${Date.now()}`;
        let familyId;
        let personId;

        before(() => {
            cy.request({
                method: "POST",
                url: "/api/public/register/family",
                body: {
                    Name: lastName,
                    Address1: "1 Test St",
                    City: "Testville",
                    State: "TS",
                    Country: "US",
                    Zip: "12345",
                    people: [
                        {
                            firstName: "Hidden",
                            lastName,
                            gender: 1,
                            role: 1,
                            birthday: "01/15/1980",
                            hideAge: true,
                        },
                    ],
                },
            }).then((resp) => {
                familyId = resp.body.Id;
            });
            cy.makePrivateAdminAPICall("GET", `/api/persons/search/${lastName}`, null, 200).then((resp) => {
                personId = resp.body[0].objid;
            });
        });

        after(() => {
            cy.makePrivateAdminAPICall("DELETE", `/api/family/${familyId}?deleteMembers=true`, null, [200, 404]);
        });

        it("does not return the birth year to a user who cannot edit the person", () => {
            cy.makePrivateNoPermAPICall("GET", `/api/person/${personId}`, null, 200).then((resp) => {
                expect(resp.body.BirthMonth).to.equal(1);
                expect(resp.body.BirthYear).to.equal(null);
                expect(resp.body.Flags).to.equal(null);
            });
        });

        it("returns the birth year to an admin", () => {
            cy.makePrivateAdminAPICall("GET", `/api/person/${personId}`, null, 200).then((resp) => {
                expect(resp.body.BirthYear).to.equal(1980);
                expect(resp.body.Flags).to.equal(1);
            });
        });
    });

    describe("Authenticated photo responses", () => {
        it("are cached privately", () => {
            cy.makePrivateNoPermAPICall("GET", `/api/person/${PERSON_WITH_PHOTO}/photo`, null, 200).then((resp) => {
                expect(resp.headers["cache-control"]).to.contain("private").and.not.contain("public");
            });
            cy.makePrivateNoPermAPICall("GET", `/api/family/${FAMILY_WITH_PHOTO}/photo`, null, 200).then((resp) => {
                expect(resp.headers["cache-control"]).to.contain("private").and.not.contain("public");
            });
        });
    });

    describe("POST /api/issues", () => {
        const report = {
            pageName: "LowPrivilegePage",
            screenSize: { height: 1080, width: 1920 },
            windowSize: { height: 900, width: 1440 },
            pageSize: { height: 2000, width: 1440 },
        };

        it("leaves server details out for a non-admin", () => {
            cy.makePrivateNoPermAPICall("POST", "/api/issues", report, 200).then((resp) => {
                const body = resp.body.issueBody;
                expect(body).to.contain("LowPrivilegePage");
                expect(body).to.contain("ChurchCRM Version");
                ["Platform Information", "Web Server", "PHP Version", "SQL Version", "Installation ID", "Prerequisite Status"].forEach((row) => {
                    expect(body).not.to.contain(row);
                });
            });
        });

        it("includes server details for an admin", () => {
            cy.makePrivateAdminAPICall("POST", "/api/issues", report, 200).then((resp) => {
                expect(resp.body.issueBody).to.contain("PHP Version").and.contain("SQL Version").and.contain("Installation ID");
            });
        });
    });
});
