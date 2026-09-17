/// <reference types="cypress" />

/**
 * Member Portal — calendar subscription (design §5.3, "Subscribing").
 *
 * Two surfaces are under test, and the split between them is the whole point:
 *
 *   - `/api/portal/calendar/subscription` (GET / PUT / POST …/reset) is
 *     session-only, like every other portal endpoint: an API key is refused,
 *     the acting member is the session and never an id in the body.
 *   - `/api/public/portal-calendar/{token}/calendar.ics` has no session at
 *     all. The token in the URL is the whole credential, which is why the feed
 *     is confined to the calendars the administrator currently shares with
 *     members and to nothing else about the member who owns it.
 *
 * Seed persona: user 100, Lena Black. usr_EditSelf=1, no admin flag, family 20.
 * The username column is VARCHAR(32), so the seeded address is stored
 * truncated — log in with the 32-character form.
 *
 * Seed facts: calendar 1 is "Public Calendar", calendar 2 is "Private
 * Calendar", the church is "Main St. Cathedral", and system calendar 0 is
 * Birthdays. The feed's window is today − 3 months … today + 18 months, so the
 * events these tests look for are created inside it rather than taken from the
 * 2016 fixtures.
 *
 * NOTE: every x-api-key request replaces the browser session cookie with an
 * API-token session, so a member step that follows one has to log in again.
 */
const MEMBER_USER = "lena.black.editself.notes@exampl";
const MEMBER_PASSWORD = "changeme";

const CHURCH_CALENDAR_ID = 1;
const CHURCH_CALENDAR_NAME = "Public Calendar";
const PRIVATE_CALENDAR_ID = 2;
const PRIVATE_CALENDAR_NAME = "Private Calendar";
const BIRTHDAYS_CALENDAR_ID = 0;

const CHURCH_NAME = "Main St. Cathedral";

const SUBSCRIPTION_URL = "/api/portal/calendar/subscription";

const adminKey = () => Cypress.env("admin.api.key");

const setVisibleCalendars = (visible) =>
    cy.request({
        method: "POST",
        url: "/admin/api/member-portal/calendars",
        headers: { "content-type": "application/json", "x-api-key": adminKey() },
        body: { visible },
        failOnStatusCode: false,
    });

/** Log in the way a browser does, so cy.request() inherits a real session. */
const loginAsMember = () => {
    cy.clearCookies();
    cy.request("/session/begin");
    cy.request({
        method: "POST",
        url: "/session/begin",
        form: true,
        body: { User: MEMBER_USER, Password: MEMBER_PASSWORD },
        followRedirect: false,
    }).then((response) => {
        expect(response.status, "the member's login").to.be.oneOf([200, 302]);
    });
};

/** The session CSRF token, read out of a rendered portal form as a browser would. */
const withCsrfToken = (callback) => {
    cy.request("/portal/profile/edit").then((response) => {
        const match = response.body.match(/name="csrf_token"\s+value="([a-f0-9]+)"/i);
        expect(match, "csrf_token hidden field present in the portal form").to.not.be.null;
        callback(match[1]);
    });
};

const getSubscription = () =>
    cy.request({ method: "GET", url: SUBSCRIPTION_URL, failOnStatusCode: false });

const putSubscription = (calendars, token) =>
    cy.request({
        method: "PUT",
        url: SUBSCRIPTION_URL,
        body: { calendars },
        failOnStatusCode: false,
        headers: { "content-type": "application/json", "X-CSRF-Token": token },
    });

const resetSubscription = (token) =>
    cy.request({
        method: "POST",
        url: `${SUBSCRIPTION_URL}/reset`,
        failOnStatusCode: false,
        headers: { "content-type": "application/json", "X-CSRF-Token": token },
    });

/** Fetch the feed the way a calendar app does: no cookies, no headers. */
const fetchFeed = (url) => {
    cy.clearCookies();
    return cy.request({ method: "GET", url, failOnStatusCode: false });
};

/** Create an event on a calendar, inside the feed's window. */
const createEvent = (title, calendarId, day) =>
    cy.request({
        method: "POST",
        url: "/api/events",
        headers: { "content-type": "application/json", "x-api-key": adminKey() },
        body: {
            Title: title,
            Type: 1,
            PinnedCalendars: [calendarId],
            Start: `${day}T19:00:00`,
            End: `${day}T20:30:00`,
            Desc: "<p>An evening gathering</p>",
        },
    });

/** A day inside the feed window (today − 3 months … today + 18 months). */
const dayInWindow = (monthsAhead) => {
    const date = new Date();
    date.setDate(1);
    date.setMonth(date.getMonth() + monthsAhead);
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-15`;
};

/** Unfold RFC 5545 continuation lines so a property can be matched whole. */
const unfold = (ics) => ics.replace(/\r\n[ \t]/g, "");

describe("Member Portal calendar subscription", () => {
    after(() => {
        setVisibleCalendars([]);
    });

    describe("Who may call the portal endpoint", () => {
        it("refuses an API key, like every other portal route", () => {
            cy.request({
                method: "GET",
                url: SUBSCRIPTION_URL,
                headers: { "x-api-key": adminKey() },
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.eq(403);
            });
        });
    });

    describe("GET — what a member is offered", () => {
        it("lists exactly the calendars the administrator shares, and no URL before a first save", () => {
            setVisibleCalendars([
                { type: "calendar", id: CHURCH_CALENDAR_ID },
                { type: "system", id: BIRTHDAYS_CALENDAR_ID },
            ]);
            loginAsMember();

            getSubscription().then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.title).to.eq(`${CHURCH_NAME} Calendar`);

                const ids = response.body.choices.map((choice) => choice.id);
                expect(ids).to.have.members([
                    `calendar:${CHURCH_CALENDAR_ID}`,
                    `system:${BIRTHDAYS_CALENDAR_ID}`,
                ]);
                expect(ids).to.not.include(`calendar:${PRIVATE_CALENDAR_ID}`);

                for (const choice of response.body.choices) {
                    expect(choice.name, "every choice is named").to.be.a("string").and.not.be.empty;
                    expect(choice.color).to.match(/^#[0-9a-f]{3,8}$/i);
                    expect(choice.selected).to.be.a("boolean");
                }
            });
        });
    });

    describe("PUT — saving a selection", () => {
        it("refuses a calendar that is not shared with members", () => {
            setVisibleCalendars([{ type: "calendar", id: CHURCH_CALENDAR_ID }]);
            loginAsMember();
            withCsrfToken((token) => {
                putSubscription([`calendar:${PRIVATE_CALENDAR_ID}`], token).then((response) => {
                    expect(response.status).to.eq(400);
                });
                putSubscription(["calendar:9999"], token).then((response) => {
                    expect(response.status).to.eq(400);
                });
                putSubscription(["not-a-calendar-id"], token).then((response) => {
                    expect(response.status).to.eq(400);
                });
            });
        });

        it("refuses an empty selection", () => {
            setVisibleCalendars([{ type: "calendar", id: CHURCH_CALENDAR_ID }]);
            loginAsMember();
            withCsrfToken((token) => {
                putSubscription([], token).then((response) => {
                    expect(response.status).to.eq(400);
                });
            });
        });

        it("saves a selection, mints a token and answers with the feed address", () => {
            setVisibleCalendars([{ type: "calendar", id: CHURCH_CALENDAR_ID }]);
            loginAsMember();
            withCsrfToken((token) => {
                putSubscription([`calendar:${CHURCH_CALENDAR_ID}`], token).then((response) => {
                    expect(response.status).to.eq(200);
                    expect(response.body.url).to.include("/api/public/portal-calendar/");
                    expect(response.body.url).to.match(/\/calendar\.ics$/);
                    expect(response.body.webcalUrl).to.match(/^webcal:\/\//);
                    expect(response.body.title).to.eq(`${CHURCH_NAME} Calendar`);

                    const selected = response.body.choices
                        .filter((choice) => choice.selected)
                        .map((choice) => choice.id);
                    expect(selected).to.deep.eq([`calendar:${CHURCH_CALENDAR_ID}`]);
                });
            });
        });
    });

    describe("The public feed", () => {
        it("serves a valid calendar of the selected calendars only, and changes when the selection does", () => {
            const inWindow = dayInWindow(1);
            const churchEvent = `Feed Church Event ${Cypress._.random(0, 1e6)}`;
            const privateEvent = `Feed Private Event ${Cypress._.random(0, 1e6)}`;

            createEvent(churchEvent, CHURCH_CALENDAR_ID, inWindow);
            createEvent(privateEvent, PRIVATE_CALENDAR_ID, inWindow);
            setVisibleCalendars([
                { type: "calendar", id: CHURCH_CALENDAR_ID },
                { type: "calendar", id: PRIVATE_CALENDAR_ID },
            ]);

            loginAsMember();
            withCsrfToken((token) => {
                putSubscription([`calendar:${CHURCH_CALENDAR_ID}`], token).then((saved) => {
                    const feedUrl = saved.body.url;

                    fetchFeed(feedUrl).then((response) => {
                        expect(response.status).to.eq(200);
                        expect(response.headers["content-type"]).to.include("text/calendar");
                        expect(response.headers["content-disposition"]).to.include("calendar.ics");
                        expect(response.headers["cache-control"]).to.include("no-cache");

                        const ics = unfold(response.body);
                        expect(ics).to.include("BEGIN:VCALENDAR");
                        expect(ics).to.include("VERSION:2.0");
                        expect(ics).to.include("PRODID:");
                        expect(ics.trim()).to.match(/END:VCALENDAR$/);
                        expect(ics).to.include(`X-WR-CALNAME:${CHURCH_NAME} Calendar`);
                        expect(ics).to.include("BEGIN:VEVENT");
                        expect(ics).to.include(`SUMMARY:${churchEvent}`);
                        expect(ics).to.include(`CATEGORIES:${CHURCH_CALENDAR_NAME}`);

                        // The calendar that was not ticked is not in the feed,
                        // even though the administrator shares it.
                        expect(ics).to.not.include(`SUMMARY:${privateEvent}`);
                        expect(ics).to.not.include(`CATEGORIES:${PRIVATE_CALENDAR_NAME}`);
                    });

                    // Ticking the other calendar changes the feed at the same address.
                    loginAsMember();
                    withCsrfToken((freshToken) => {
                        putSubscription(
                            [`calendar:${CHURCH_CALENDAR_ID}`, `calendar:${PRIVATE_CALENDAR_ID}`],
                            freshToken
                        ).then((changed) => {
                            expect(changed.body.url, "the address is stable across saves").to.eq(feedUrl);

                            fetchFeed(feedUrl).then((response) => {
                                const ics = unfold(response.body);
                                expect(ics).to.include(`SUMMARY:${churchEvent}`);
                                expect(ics).to.include(`SUMMARY:${privateEvent}`);
                                expect(ics).to.include(`CATEGORIES:${PRIVATE_CALENDAR_NAME}`);
                            });
                        });
                    });
                });
            });
        });

        it("drops a calendar the administrator stops sharing, without the member touching anything", () => {
            const inWindow = dayInWindow(2);
            const privateEvent = `Feed Unshared Event ${Cypress._.random(0, 1e6)}`;

            createEvent(privateEvent, PRIVATE_CALENDAR_ID, inWindow);
            setVisibleCalendars([
                { type: "calendar", id: CHURCH_CALENDAR_ID },
                { type: "calendar", id: PRIVATE_CALENDAR_ID },
            ]);

            loginAsMember();
            withCsrfToken((token) => {
                putSubscription(
                    [`calendar:${CHURCH_CALENDAR_ID}`, `calendar:${PRIVATE_CALENDAR_ID}`],
                    token
                ).then((saved) => {
                    const feedUrl = saved.body.url;

                    fetchFeed(feedUrl).then((response) => {
                        expect(unfold(response.body)).to.include(`SUMMARY:${privateEvent}`);
                    });

                    setVisibleCalendars([{ type: "calendar", id: CHURCH_CALENDAR_ID }]);

                    fetchFeed(feedUrl).then((response) => {
                        expect(response.status).to.eq(200);
                        expect(unfold(response.body)).to.not.include(`SUMMARY:${privateEvent}`);
                    });
                });
            });
        });

        it("escapes and folds the way RFC 5545 asks", () => {
            const inWindow = dayInWindow(3);
            const awkward = `Feed; Comma, Event ${Cypress._.random(0, 1e6)} with a very long title that has to be folded because it runs past seventy-five octets`;

            createEvent(awkward, CHURCH_CALENDAR_ID, inWindow);
            setVisibleCalendars([{ type: "calendar", id: CHURCH_CALENDAR_ID }]);

            loginAsMember();
            withCsrfToken((token) => {
                putSubscription([`calendar:${CHURCH_CALENDAR_ID}`], token).then((saved) => {
                    fetchFeed(saved.body.url).then((response) => {
                        const raw = response.body;

                        // Every content line fits in 75 octets before folding.
                        for (const line of raw.split("\r\n")) {
                            expect(
                                Buffer.byteLength(line, "utf8"),
                                `line within 75 octets: ${line.slice(0, 40)}`
                            ).to.be.at.most(75);
                        }

                        const ics = unfold(raw);
                        expect(ics).to.include(
                            `SUMMARY:${awkward.replace(/([\\,;])/g, "\\$1")}`
                        );

                        // Every VEVENT carries the properties a calendar app needs.
                        const events = ics.split("BEGIN:VEVENT").slice(1);
                        expect(events.length).to.be.greaterThan(0);
                        for (const event of events) {
                            expect(event).to.match(/\r\nUID:[^\r\n]+/);
                            expect(event).to.match(/\r\nDTSTAMP:\d{8}T\d{6}Z/);
                            expect(event).to.match(/\r\nDTSTART[;:]/);
                            expect(event).to.include("END:VEVENT");
                        }
                    });
                });
            });
        });

        it("gives a whole-day VEVENT to a birthday, with no time and no age", () => {
            setVisibleCalendars([{ type: "system", id: BIRTHDAYS_CALENDAR_ID }]);
            loginAsMember();
            withCsrfToken((token) => {
                putSubscription([`system:${BIRTHDAYS_CALENDAR_ID}`], token).then((saved) => {
                    fetchFeed(saved.body.url).then((response) => {
                        expect(response.status).to.eq(200);
                        const ics = unfold(response.body);
                        expect(ics).to.include("DTSTART;VALUE=DATE:");
                        expect(ics).to.include("SUMMARY:Albert C.");
                        expect(ics).to.not.include("Albert Campbell");
                        expect(ics).to.not.match(/SUMMARY:[^\r\n]*\(\d+\)/);
                    });
                });
            });
        });

        it("answers an unknown token with a 404 that says nothing about who exists", () => {
            fetchFeed("/api/public/portal-calendar/deadbeef/calendar.ics").then((response) => {
                expect(response.status).to.eq(404);
                expect(JSON.stringify(response.body), "no member is named").to.not.contain("Lena");
            });
            fetchFeed(`/api/public/portal-calendar/${"a".repeat(64)}/calendar.ics`).then((response) => {
                expect(response.status).to.eq(404);
            });
        });
    });

    describe("Resetting the link", () => {
        it("retires the old address and serves the same calendar at a new one", () => {
            setVisibleCalendars([{ type: "calendar", id: CHURCH_CALENDAR_ID }]);
            loginAsMember();
            withCsrfToken((token) => {
                putSubscription([`calendar:${CHURCH_CALENDAR_ID}`], token).then((saved) => {
                    const oldUrl = saved.body.url;

                    loginAsMember();
                    withCsrfToken((freshToken) => {
                        resetSubscription(freshToken).then((reset) => {
                            expect(reset.status).to.eq(200);
                            expect(reset.body.url).to.not.eq(oldUrl);
                            expect(reset.body.url).to.include("/api/public/portal-calendar/");

                            const stillSelected = reset.body.choices
                                .filter((choice) => choice.selected)
                                .map((choice) => choice.id);
                            expect(stillSelected, "a reset keeps the selection").to.deep.eq([
                                `calendar:${CHURCH_CALENDAR_ID}`,
                            ]);

                            fetchFeed(oldUrl).then((response) => {
                                expect(response.status, "the old address stops working").to.eq(404);
                            });
                            fetchFeed(reset.body.url).then((response) => {
                                expect(response.status).to.eq(200);
                                expect(response.body).to.include("BEGIN:VCALENDAR");
                            });
                        });
                    });
                });
            });
        });
    });
});
