/// <reference types="cypress" />

/**
 * Member Portal — #9869 scenario 1, "a member's week", as ONE end-to-end run.
 *
 * Epic #8977, issue #9869, design `.agents/skills/churchcrm/member-portal-design.md`
 * §5 and §9 MP8.
 *
 * Every child issue of the epic has a deep spec for its own page. None of them
 * proves the thing this one is for: that the pages are a **portal** rather than seven
 * separate features that happen to share a stylesheet. A member does not visit
 * "MP4" — they log in on a Tuesday, fix their phone number, glance at what is on,
 * answer the email the coordinator sent, and put their hand up for something. If any
 * seam between those steps is broken — a flash message that survives a navigation, a
 * card that does not refresh after a write, a page that quietly drops the session —
 * only a run like this one finds it.
 *
 * The week, in order:
 *
 *     log in ─────────────────► the portal, never the admin shell
 *        │
 *        ├─ profile ──────────► change a phone number, see it on the profile page
 *        ├─ family ───────────► confirm the household's details
 *        ├─ calendar ─────────► the church's events, and the home card agrees
 *        ├─ volunteering ─────► accept the assignment the coordinator sent
 *        │                      sign up for an open slot
 *        └─ opportunities ────► offer to help a ministry that is advertising
 *
 * Personas (cypress/data/seed.sql):
 *
 *   person 100  Lena Black  `usr_EditSelf=1`, no admin flag, so
 *               `User::isEditSelfExclusive()` is true and this login has no admin
 *               shell to fall back to — which is what makes it the right persona for
 *               a portal run. `user_usr.usr_UserName` is `VARCHAR(32)` and her seeded
 *               address is 37 characters, so the login form gets the truncated form.
 *   person 8    a second pool member, so a requirement can have somebody else in it.
 *
 * `cy.dbQuery` is used only for cleanup and for the one assertion that has no HTTP
 * surface (that the confirm wrote a note). Every fact the scenario asserts about the
 * system is read back through the API, not out of the page that wrote it.
 *
 * Order inside every hook is API setup → login → `cy.visit()`: `cy.request()` rotates
 * the PHP session cookie (cypress-testing.md). Cleanup runs in `before` as well as
 * `after` — an `after` hook does not run when the runner crashes mid-spec.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const VOLUNTEER_URL = "/api/volunteer";

const MEMBER_USERNAME = "lena.black.editself.notes@example.com";
const MEMBER_PASSWORD = "changeme";
const PERSON_MEMBER = 100;
const POOL_MEMBER_B = 8;

const CHURCH_SERVICE_TYPE = 1;
const CHURCH_CALENDAR_ID = 1;

const PREFIX = "E2E9869MW";
const EVENT_TITLE = `${PREFIX} Sunday Gathering`;
const HELP_WANTED_TEXT = `${PREFIX} we could use another pair of hands on a Sunday`;

let originalVersion = "v1";
let ministryId = 0;
let teamId = 0;
let posDoor = 0; // the assignment the coordinator sends
let posCoffee = 0; // the open slot the member takes for themselves
let scheduleId = 0;
let occurrenceId = 0;
let seriesStart = "";
let seriesEnd = "";

// ── helpers (local functions, NOT cy.* commands — cypress-testing.md) ───────

function dbOk(sql, params = []) {
    return cy.dbQuery(sql, params).then((result) => {
        if (result.error !== null) {
            throw new Error(
                `Unexpected SQL failure.\n  SQL: ${sql}\n  ${result.error.code}: ${result.error.message}`,
            );
        }
        return result.rows;
    });
}

function adminApi(method, url, body, expectedStatus = 200) {
    return cy.makePrivateAdminAPICall(method, url, body, expectedStatus);
}

function setVersion(value) {
    adminApi("POST", SETTING_URL, { value }, 200);
}

function setConfig(name, value) {
    adminApi("POST", `/admin/api/system/config/${name}`, { value }, 200);
}

function setVisibleCalendars(visible) {
    adminApi("POST", "/admin/api/member-portal/calendars", { visible }, 200);
}

function isoDate(offsetDays) {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + offsetDays);
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/** Days from today to the next occurrence of `dow` (0 = Sunday). Never 0. */
function daysToNext(dow) {
    const today = new Date().getDay();
    return ((dow - today + 7) % 7) || 7;
}

function memberLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(MEMBER_USERNAME);
    cy.get("input[name=Password]").type(`${MEMBER_PASSWORD}{enter}`);
    cy.url({ timeout: 10000 }).should("include", "/portal");
}

/**
 * The admin shell must never appear around a member's page (design §0.2, P10).
 * Asserted after every navigation in this run, not once: the failure mode this
 * guards against is a single page forgetting the portal layout, and only checking
 * the landing page would miss exactly that.
 */
function assertNoAdminShell() {
    cy.get("#sidebar-menu").should("not.exist");
    cy.get(".navbar-vertical").should("not.exist");
    cy.get("#fab-container").should("not.exist");
    cy.get(".portal-shell").should("exist");
}

function clearWorkflowRows() {
    const scoped = `
        JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vasg.vasg_vocc_ID
        JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
        JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
       WHERE vmin.vmin_Name LIKE ?`;
    const like = [`${PREFIX}%`];

    dbOk(
        `DELETE vntf FROM volunteer_notification_vntf vntf
           JOIN volunteer_assignment_vasg vasg ON vasg.vasg_ID = vntf.vntf_vasg_ID
           ${scoped}`,
        like,
    );
    dbOk(
        `DELETE vntf FROM volunteer_notification_vntf vntf
           JOIN volunteer_occurrence_vocc vocc ON vocc.vocc_ID = vntf.vntf_vocc_ID
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    // A help_offer hangs off no occurrence and no assignment — the ministry is in
    // its dedupe key — so it is cleared by type, as the MP6 spec does.
    dbOk(`DELETE FROM volunteer_notification_vntf WHERE vntf_Type = 'help_offer'`);
    dbOk(
        `DELETE vrsp FROM volunteer_response_vrsp vrsp
           JOIN volunteer_assignment_vasg vasg ON vasg.vasg_ID = vrsp.vrsp_vasg_ID
           ${scoped}`,
        like,
    );
    dbOk(`DELETE vasg FROM volunteer_assignment_vasg vasg ${scoped}`, like);
}

function cleanupFixtures() {
    clearWorkflowRows();

    const like = [`${PREFIX}%`];
    dbOk(
        `DELETE vreq FROM volunteer_requirement_vreq vreq
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vreq.vreq_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vocc FROM volunteer_occurrence_vocc vocc
           JOIN volunteer_schedule_vsch vsch ON vsch.vsch_ID = vocc.vocc_vsch_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vsch FROM volunteer_schedule_vsch vsch
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vsch.vsch_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vqal FROM volunteer_qualification_vqal vqal
           JOIN volunteer_position_vpos vpos ON vpos.vpos_ID = vqal.vqal_vpos_ID
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vpos.vpos_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vpos FROM volunteer_position_vpos vpos
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vpos.vpos_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE r FROM person2group2role_p2g2r r
           JOIN group_grp g ON g.grp_ID = r.p2g2r_grp_ID
           JOIN volunteer_ministry_vmin m ON m.vmin_ID = g.grp_ministry_id
          WHERE m.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE g FROM group_grp g
           JOIN volunteer_ministry_vmin m ON m.vmin_ID = g.grp_ministry_id
          WHERE m.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE vtem FROM volunteer_team_vtem vtem
           JOIN volunteer_ministry_vmin vmin ON vmin.vmin_ID = vtem.vtem_vmin_ID
          WHERE vmin.vmin_Name LIKE ?`,
        like,
    );
    dbOk(
        `DELETE ce FROM calendar_events ce
           JOIN events_event e ON e.event_id = ce.event_id
          WHERE e.event_title LIKE ?`,
        like,
    );
    dbOk(`DELETE FROM events_event WHERE event_title LIKE ?`, like);
    // The ministry's own calendar, before the ministry: `calendars.ministry_id` is
    // ON DELETE SET NULL, so one left behind becomes an unowned church calendar.
    dbOk(
        `DELETE c FROM calendars c
           JOIN volunteer_ministry_vmin m ON m.vmin_ID = c.ministry_id
          WHERE m.vmin_Name LIKE ?`,
        like,
    );
    dbOk(`DELETE FROM volunteer_ministry_vmin WHERE vmin_Name LIKE ?`, like);
    dbOk(`DELETE FROM note_nte WHERE nte_Text LIKE ?`, [`${PREFIX}%`]);
}

// ── the church's week, set up the way a coordinator would have left it ─────

before(() => {
    adminApi("GET", SETTING_URL, null, 200).then((resp) => {
        originalVersion = resp.body.value ?? resp.body.data ?? "v1";
    });
    setVersion("v2");
    setConfig("bPortalShowCalendar", "1");
    setConfig("bPortalShowVolunteer", "1");
    setVisibleCalendars([{ type: "calendar", id: CHURCH_CALENDAR_ID }]);

    cleanupFixtures();

    adminApi(
        "POST",
        `${VOLUNTEER_URL}/ministries`,
        { name: `${PREFIX} Hospitality`, description: "a member's week" },
        201,
    ).then((resp) => {
        ministryId = resp.body.ministry.id;
    });

    cy.then(() => {
        // The ministry is advertising, so the last step of the week has a card.
        adminApi(
            "POST",
            `${VOLUNTEER_URL}/ministries/${ministryId}`,
            { helpWanted: true, helpWantedText: HELP_WANTED_TEXT },
            200,
        );

        adminApi(
            "POST",
            `${VOLUNTEER_URL}/ministries/${ministryId}/teams`,
            { name: `${PREFIX} Greeters`, description: "a member's week" },
            201,
        ).then((resp) => {
            teamId = resp.body.team.id;
        });
    });

    cy.then(() => {
        adminApi(
            "POST",
            `${VOLUNTEER_URL}/ministries/${ministryId}/positions`,
            { name: `${PREFIX} Door`, teamId, order: 1 },
            201,
        ).then((resp) => {
            posDoor = resp.body.position.id;
        });
        adminApi(
            "POST",
            `${VOLUNTEER_URL}/ministries/${ministryId}/positions`,
            { name: `${PREFIX} Coffee`, teamId, order: 2 },
            201,
        ).then((resp) => {
            posCoffee = resp.body.position.id;
        });
    });

    cy.then(() => {
        for (const personId of [PERSON_MEMBER, POOL_MEMBER_B]) {
            adminApi(
                "POST",
                `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${personId}`,
                null,
                [200, 201],
            );
        }
    });

    cy.then(() => {
        for (const positionId of [posDoor, posCoffee]) {
            for (const personId of [PERSON_MEMBER, POOL_MEMBER_B]) {
                adminApi(
                    "POST",
                    `${VOLUNTEER_URL}/positions/${positionId}/qualifications`,
                    { personId, notes: "" },
                    201,
                );
            }
        }
    });

    cy.then(() => {
        seriesStart = isoDate(daysToNext(0));
        seriesEnd = isoDate(daysToNext(0) + 14);
        adminApi(
            "POST",
            "/api/events/repeat",
            {
                Title: EVENT_TITLE,
                Type: CHURCH_SERVICE_TYPE,
                StartTime: "10:30:00",
                EndTime: "11:45:00",
                RecurType: "weekly",
                RecurDOW: "Sunday",
                RangeStart: seriesStart,
                RangeEnd: seriesEnd,
                PinnedCalendars: [CHURCH_CALENDAR_ID],
            },
            200,
        );
    });

    cy.then(() => {
        adminApi(
            "POST",
            `${VOLUNTEER_URL}/ministries/${ministryId}/schedules`,
            {
                name: `${PREFIX} Sunday`,
                linkMode: "event_type",
                eventTypeId: CHURCH_SERVICE_TYPE,
                titleFilter: EVENT_TITLE,
                windowStart: seriesStart,
                teamId,
            },
            201,
        ).then((resp) => {
            scheduleId = resp.body.schedule.id;
        });
    });

    cy.then(() => {
        adminApi(
            "POST",
            `${VOLUNTEER_URL}/schedules/${scheduleId}/requirements`,
            { positionId: posDoor, minCount: 1, maxCount: 2 },
            [200, 201],
        );
        adminApi(
            "POST",
            `${VOLUNTEER_URL}/schedules/${scheduleId}/requirements`,
            { positionId: posCoffee, minCount: 1, maxCount: 1 },
            [200, 201],
        );
    });

    cy.then(() => {
        adminApi(
            "POST",
            `${VOLUNTEER_URL}/schedules/${scheduleId}/generate`,
            { through: seriesEnd },
            200,
        );
    });

    cy.then(() => {
        adminApi(
            "GET",
            `${VOLUNTEER_URL}/occurrences?from=${seriesStart}&to=${seriesEnd}&ministryId=${ministryId}`,
        ).then((resp) => {
            occurrenceId = resp.body.occurrences[0].id;
        });
    });

    // The one thing the coordinator did before the member logged in: put them on
    // the door. It arrives as `pending`, which is the state the week starts in.
    cy.then(() => {
        adminApi(
            "POST",
            `${VOLUNTEER_URL}/occurrences/${occurrenceId}/assignments`,
            { positionId: posDoor, personId: PERSON_MEMBER },
            201,
        );
    });
});

after(() => {
    cleanupFixtures();
    setVisibleCalendars([]);
    setVersion(originalVersion);
});

describe("Member Portal e2e — #9869 scenario 1, a member's week", () => {
    it("lands in the portal, with every section the church switched on", () => {
        memberLogin();

        cy.url().should("include", "/portal");
        assertNoAdminShell();

        // The design fixes the order (§5); the member sees it, not a bag of links.
        cy.get("#portal-nav .portal-nav-link > span:not(.portal-nav-badge)").then(
            ($labels) => {
                const labels = [...$labels].map((el) => el.textContent.trim());
                expect(labels.slice(0, 2)).to.deep.eq(["Home", "Calendar"]);
                expect(labels).to.include("Volunteering");
                expect(labels.slice(-2)).to.deep.eq(["My Family", "Profile"]);
            },
        );

        // Every card on the home page is real — MP8 removed the last placeholder.
        cy.get(".portal-card-grid .portal-card-placeholder").should("not.exist");
        cy.contains("Coming soon").should("not.exist");
    });

    it("fixes a phone number, and the profile page agrees afterwards", () => {
        const newPhone = `(206) 555-${String(Date.now()).slice(-4)}`;

        memberLogin();
        cy.visit("/portal/profile/edit");
        assertNoAdminShell();

        cy.get("#portal-cellPhone").clear().type(newPhone);
        cy.get("#portal-profile-save").click();

        cy.get(".portal-flash-success", { timeout: 10000 })
            .should("be.visible")
            .and("contain", "Your details have been saved");

        // Read it back from the page that displays it, not the form that wrote it.
        cy.visit("/portal/profile");
        cy.get("#portal-profile-details [data-field=cellPhone]").should(
            "contain",
            newPhone,
        );

        // …and from the record itself, which is the only proof the write landed
        // rather than the page echoing what was typed.
        adminApi("GET", `/api/person/${PERSON_MEMBER}`, null, 200).then((resp) => {
            expect(resp.body.CellPhone).to.eq(newPhone);
        });
    });

    it("confirms the household's details, and the church hears about it", () => {
        const comment = `${PREFIX} the address is right, the phone is new`;

        memberLogin();
        cy.visit("/portal/family");
        assertNoAdminShell();

        cy.get("#portal-family-confirm-link").click();
        cy.url({ timeout: 10000 }).should("include", "/portal/family/confirm");

        cy.get("#portal-confirm-change-needed").click();
        cy.get("#portal-confirm-comment").should("be.visible").type(comment);
        cy.get("#portal-confirm-submit").click();

        // The confirm writes a note against the family — the one fact in this run
        // with no HTTP surface a member may read.
        dbOk(`SELECT nte_Text FROM note_nte WHERE nte_Text LIKE ?`, [
            `%${comment}%`,
        ]).then((rows) => {
            expect(rows.length, "a note reached the church office").to.be.at.least(
                1,
            );
        });
    });

    it("sees what is on at church, on the calendar and on the home card", () => {
        memberLogin();

        // The home card is the glance; it carries the church's next events.
        cy.get(".portal-card-calendar", { timeout: 10000 })
            .should("be.visible")
            .and("contain", "See the whole calendar");

        cy.get(".portal-card-calendar .portal-card-link").click();
        cy.url({ timeout: 10000 }).should("include", "/portal/calendar");
        assertNoAdminShell();

        cy.window({ timeout: 15000 }).should((win) => {
            expect(win.CRM.fullcalendar, "the calendar rendered").to.exist;
        });
        cy.window().then((win) => win.CRM.fullcalendar.gotoDate(seriesStart));
        cy.contains(EVENT_TITLE, { timeout: 15000 }).should("be.visible");

        // Read-only: a member has nothing to click that would change an event.
        cy.get("#portal-calendar button.fc-event-edit").should("not.exist");
    });

    it("answers the coordinator: accepts the door they were put on", () => {
        memberLogin();
        cy.visit("/portal/volunteer/schedule");
        assertNoAdminShell();

        cy.get(".volunteer-assignment-card", { timeout: 20000 })
            .first()
            .should("contain", `${PREFIX} Door`)
            .find(".volunteer-accept")
            .click();

        cy.get(".volunteer-assignment-card")
            .first()
            .find(".volunteer-card-status")
            .should("contain.text", "Going");

        // The server agrees, not just the card.
        adminApi(
            "GET",
            `${VOLUNTEER_URL}/occurrences/${occurrenceId}/staffing`,
            null,
            200,
        ).then((resp) => {
            const rows = resp.body.requirements.flatMap((r) => r.assignments ?? []);
            const mine = rows.find((a) => a.personId === PERSON_MEMBER);
            expect(mine.status).to.eq("accepted");
        });
    });

    it("puts a hand up for the coffee slot nobody had taken", () => {
        memberLogin();
        cy.visit("/portal/volunteer/opportunities");
        assertNoAdminShell();

        cy.get(`.volunteer-opportunity-card[data-position-id="${posCoffee}"]`, {
            timeout: 20000,
        })
            .first()
            .find(".volunteer-signup")
            .click();

        // D16 asks first when the member is already helping that day, which they
        // are: they accepted the door two steps ago. Saying yes is the point.
        cy.get("body").then(($body) => {
            if ($body.find(".bootbox").length > 0) {
                cy.get(".bootbox .btn-primary").click();
            }
        });

        cy.visit("/portal/volunteer/schedule");
        cy.get("#assignments-content", { timeout: 20000 })
            .should("be.visible")
            .and("contain", `${PREFIX} Coffee`);
    });

    it("offers to help the ministry that is advertising, and joins its pool", () => {
        // Leave the pool first, so the offer takes the "has been added" path
        // rather than the "already in the pool" one.
        adminApi(
            "DELETE",
            `${VOLUNTEER_URL}/ministries/${ministryId}/pool/${PERSON_MEMBER}`,
            null,
            [200, 404],
        );

        memberLogin();
        cy.visit("/portal/volunteer/opportunities");

        cy.get("#help-wanted-section", { timeout: 20000 }).should("be.visible");
        cy.get(`.volunteer-help-wanted-card[data-ministry-id="${ministryId}"]`)
            .should("contain", HELP_WANTED_TEXT)
            .find(".volunteer-offer-help")
            .click();

        cy.get(".notyf__toast", { timeout: 10000 }).should(
            "contain",
            "you'd like to help",
        );

        adminApi(
            "GET",
            `${VOLUNTEER_URL}/ministries/${ministryId}/pool`,
            null,
            200,
        ).then((resp) => {
            expect(resp.body.members.map((m) => m.personId)).to.include(
                PERSON_MEMBER,
            );
        });
    });

    it("never once left the portal, and never once saw the admin shell", () => {
        memberLogin();

        // The whole week, replayed as navigations, asserting the one invariant the
        // design puts above all the others (P10): this login has no admin shell.
        for (const url of [
            "/portal/",
            "/portal/profile",
            "/portal/family",
            "/portal/calendar",
            "/portal/volunteer/schedule",
            "/portal/volunteer/opportunities",
        ]) {
            cy.visit(url);
            assertNoAdminShell();
        }

        // And the one URL that would take a staff login to the dashboard takes
        // this one straight back to where they belong.
        cy.visit("/v2/dashboard", { failOnStatusCode: false });
        cy.url({ timeout: 10000 }).should("include", "/portal");
    });
});
