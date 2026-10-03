/// <reference types="cypress" />

/**
 * Volunteer v2 D32 on screen — the schedule dialog's staffing needs show and edit the default
 * volunteers per position, one picker per place up to Max (D35), the Generate dialog opens on the
 * schedule's saved defaults, and a default whose qualification was revoked is kept and marked.
 * Design §0.8 D32, D35, §5.4.
 *
 * Fixtures go in through the admin API. Order in every hook is fixture → login → visit, because
 * cy.request() rotates the PHP session cookie.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const HORIZON_URL = "/admin/api/system/config/iVolunteerSchedulingHorizonWeeks";
const MINISTRIES_URL = "/api/ministries/ministries";

const PREFIX = "UIDEF32";
const SERVICE = `${PREFIX} Service`;
const LEADER = 8;
const READER = 9;
const SECOND = 10;
const CHURCH_SERVICE_TYPE = 1;
const WEEKDAYS = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

let ministryId = 0;
let teamId = 0;
let scheduleId = 0;
const position = {};

function freshAdminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.testEnv("admin.username"));
    cy.get("input[name=Password]").type(`${Cypress.testEnv("admin.password")}{enter}`);
    cy.url().should("not.include", "/session/begin");
}

function admin(method, url, body, status = 200) {
    return cy.makePrivateAdminAPICall(method, url, body, status);
}

function localDate(offsetDays) {
    const d = new Date();
    d.setHours(12, 0, 0, 0);
    d.setDate(d.getDate() + offsetDays);
    return d;
}

function isoDate(offsetDays) {
    const d = localDate(offsetDays);
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function cleanupFixtures() {
    admin("GET", MINISTRIES_URL, null).then((resp) => {
        for (const ministry of resp.body.ministries) {
            if (ministry.name.startsWith(PREFIX)) {
                admin("POST", `${MINISTRIES_URL}/${ministry.id}`, { active: false }, [200, 404]);
                admin("DELETE", `${MINISTRIES_URL}/${ministry.id}`, null, [200, 404]);
            }
        }
    });
    cy.dbQuery("DELETE t FROM calendar_events t JOIN events_event e ON e.event_id = t.event_id WHERE e.event_title LIKE ?", [
        `${PREFIX}%`,
    ]);
    cy.dbQuery("DELETE FROM events_event WHERE event_title LIKE ?", [`${PREFIX}%`]);
}

function requirements() {
    return admin("GET", `/api/ministries/schedules/${scheduleId}/requirements`).then((resp) =>
        Object.fromEntries(resp.body.requirements.map((row) => [row.positionId, row])),
    );
}

function openSchedulesTab(base = `/ministries/${ministryId}`) {
    cy.visit(base);
    cy.get("#nav-item-schedules").click();
    cy.get("#schedules-loading").should("not.be.visible");
}

function openRowAction(action) {
    cy.get(`.volunteer-schedule-${action}[data-schedule-id="${scheduleId}"]`, { timeout: 15000 })
        .closest("tr")
        .find("[data-bs-toggle='dropdown']")
        .click();
    cy.get(`.volunteer-schedule-${action}[data-schedule-id="${scheduleId}"]`).should("be.visible").click();
}

function need(key) {
    return `#schedule-form-needs .volunteer-need-row[data-position-id="${position[key]}"]`;
}

function picker(key, index = 0) {
    return cy.get(`${need(key)} select.volunteer-need-default-select`).eq(index);
}

before(() => {
    cy.rememberTestEnv(["admin.username", "admin.password"]);
});

describe("Volunteer v2 D32 — default volunteers belong to the schedule", () => {
    before(() => {
        admin("POST", SETTING_URL, { value: "v2" });
        admin("POST", HORIZON_URL, { value: "8" });
        cleanupFixtures();

        admin("POST", MINISTRIES_URL, { name: `${PREFIX} Worship`, description: "D32 fixture" }, 201).then((resp) => {
            ministryId = resp.body.ministry.id;
            admin("GET", `${MINISTRIES_URL}/${ministryId}`).then((detail) => {
                teamId = detail.body.teams[0].id;
            });
        });
        cy.then(() => {
            for (const [key, name] of [
                ["lead", "Song Leader"],
                ["reader", "Reader"],
            ]) {
                admin("POST", `${MINISTRIES_URL}/${ministryId}/positions`, { name: `${PREFIX} ${name}`, teamId }, 201).then((resp) => {
                    position[key] = resp.body.position.id;
                });
            }
            admin(
                "POST",
                `${MINISTRIES_URL}/${ministryId}/events`,
                {
                    title: SERVICE,
                    eventTypeId: CHURCH_SERVICE_TYPE,
                    recurrence: { type: "weekly", dow: WEEKDAYS[localDate(2).getDay()] },
                    rangeStart: isoDate(2),
                    rangeEnd: isoDate(40),
                    startTime: "09:30",
                    endTime: "10:30",
                },
                201,
            );
        });
        cy.then(() => {
            admin("POST", `/api/ministries/positions/${position.lead}/qualifications`, { personId: LEADER }, [200, 201]);
            admin("POST", `/api/ministries/positions/${position.lead}/qualifications`, { personId: SECOND }, [200, 201]);
            admin("POST", `/api/ministries/positions/${position.reader}/qualifications`, { personId: READER }, [200, 201]);
            admin(
                "POST",
                `${MINISTRIES_URL}/${ministryId}/schedules`,
                {
                    name: `${PREFIX} Crew`,
                    teamId,
                    linkMode: "ministry",
                    titleFilter: SERVICE,
                    windowStart: isoDate(0),
                    // Saved paused, so its Save makes no occurrences (D33) and Generate is what staffs them here.
                    active: false,
                    requirements: [
                        { positionId: position.lead, minCount: 1, maxCount: 1 },
                        { positionId: position.reader, minCount: 1, maxCount: 1 },
                    ],
                },
                201,
            ).then((resp) => {
                scheduleId = resp.body.schedule.id;
                admin("POST", `/api/ministries/schedules/${scheduleId}`, { active: true });
            });
        });
    });

    after(() => {
        cleanupFixtures();
        admin("POST", SETTING_URL, { value: "v1" });
    });

    beforeEach(() => {
        freshAdminLogin();
    });

    it("offers one default picker per place in a new schedule, None by default, and a second one when Max goes to 2", () => {
        openSchedulesTab();
        cy.get("#schedule-add-btn").click();
        cy.get("#scheduleModal").should("be.visible");
        cy.get("#schedule-form-name").should("have.focus");
        cy.get("#schedule-form-needs select.volunteer-need-default-select").should("have.length", 2).each(($select) => {
            expect($select.val()).to.eq("");
            expect($select.find("option:selected").text()).to.eq("None");
        });
        picker("lead").find(`option[value="${LEADER}"]`).should("exist");
        picker("lead").find(`option[value="${READER}"]`).should("not.exist");
        cy.get(`${need("lead")} .volunteer-need-default-accepted-wrap`).should("not.be.visible");

        // The staffing needs open once the event is chosen (D31).
        cy.get("#schedule-form-link-mode").select("ministry");
        cy.get(`#schedule-form-title-filter option[value="${SERVICE}"]`).should("exist");
        cy.get("#schedule-form-title-filter").select(SERVICE);
        cy.get(`${need("lead")} .volunteer-need-max`).type("{selectall}2").should("have.value", "2");
        cy.get(`${need("lead")} select.volunteer-need-default-select`).should("have.length", 2);
        picker("lead", 0).select(String(LEADER));
        picker("lead", 1).find(`option[value="${LEADER}"]`).should("not.exist");
        picker("lead", 1).find(`option[value="${SECOND}"]`).should("exist");

        cy.get(`${need("lead")} .volunteer-need-max`).type("{selectall}1").should("have.value", "1");
        cy.get(`${need("lead")} select.volunteer-need-default-select`).should("have.length", 1).and("have.value", String(LEADER));
    });

    it("edits and saves two defaults on a Max-2 need, in order, with the schedule", () => {
        openSchedulesTab();
        openRowAction("edit");
        cy.get("#scheduleModal").should("be.visible");
        cy.get(`${need("lead")} select.volunteer-need-default-select`).should("have.length", 1);
        cy.get(`${need("lead")} .volunteer-need-max`).type("{selectall}2").should("have.value", "2");
        picker("lead", 0).should("have.value", "").select(String(LEADER));
        cy.get(`${need("lead")} .volunteer-need-default-accepted-wrap`).eq(0).scrollIntoView().should("be.visible");
        cy.get(`${need("lead")} .volunteer-need-default-accepted`).eq(0).check();
        picker("lead", 1).select(String(SECOND));
        picker("lead", 0).find(`option[value="${SECOND}"]`).should("not.exist");
        picker("reader").select(String(READER));

        // Unchecking a need takes its defaults out of play with it.
        cy.get(`${need("reader")} .volunteer-need-check`).uncheck();
        picker("reader").should("be.disabled");
        cy.get(`${need("reader")} .volunteer-need-check`).check();

        cy.intercept("POST", `**/api/ministries/schedules/${scheduleId}`).as("save");
        cy.get("#schedule-form-save").click();
        cy.wait("@save").then((interception) => {
            expect(interception.response.statusCode).to.eq(200);
            const lead = interception.request.body.requirements.find((row) => row.positionId === position.lead);
            expect(lead).to.include({ maxCount: 2 });
            expect(lead.defaults).to.deep.eq([
                { personId: LEADER, accepted: true },
                { personId: SECOND, accepted: false },
            ]);
        });
        cy.get("#scheduleModal").should("not.be.visible");

        requirements().then((rows) => {
            expect(rows[position.lead].defaults.map((d) => [d.personId, d.accepted])).to.deep.eq([
                [LEADER, true],
                [SECOND, false],
            ]);
            expect(rows[position.reader].defaults.map((d) => [d.personId, d.accepted])).to.deep.eq([[READER, false]]);
        });
    });

    it("keeps both choices when Max goes below them, marks the extra one and blocks Save until one is removed", () => {
        openSchedulesTab();
        openRowAction("edit");
        cy.get("#scheduleModal").should("be.visible");
        cy.get(`${need("lead")} select.volunteer-need-default-select`).should("have.length", 2);
        picker("lead", 0).should("have.value", String(LEADER));
        picker("lead", 1).should("have.value", String(SECOND));

        cy.get(`${need("lead")} .volunteer-need-max`).type("{selectall}1").should("have.value", "1");
        cy.get(`${need("lead")} select.volunteer-need-default-select`).should("have.length", 2);
        picker("lead", 0).should("not.have.class", "is-invalid");
        picker("lead", 1).should("have.class", "is-invalid").and("have.value", String(SECOND));
        cy.get(`${need("lead")} .volunteer-need-default-over`).eq(1).scrollIntoView().should("be.visible");

        cy.intercept("POST", `**/api/ministries/schedules/${scheduleId}`, cy.spy().as("save"));
        cy.get("#schedule-form-save").click();
        // Said once, where the extra choice is — not repeated in the form's error box.
        cy.get("#schedule-form-needs")
            .parent()
            .find(".volunteer-needs-notice")
            .scrollIntoView()
            .should("be.visible")
            .and("contain.text", "Max is 1 but 2 default volunteers are chosen. Remove one or raise Max.");
        cy.get("#schedule-form-error").should("not.be.visible");
        cy.get("#scheduleModal")
            .contains(/Max is 1 but 2 default volunteers are chosen/)
            .should("have.length", 1);
        cy.get("#scheduleModal").should("be.visible");
        cy.get("@save").should("not.have.been.called");

        picker("lead", 1).select("");
        cy.get(`${need("lead")} select.volunteer-need-default-select`).should("have.length", 1).and("have.value", String(LEADER));
        cy.get("#schedule-form-needs").parent().find(".volunteer-needs-notice").should("not.be.visible");

        // Raising Max again brings the empty place back; nothing was saved in between.
        cy.get(`${need("lead")} .volunteer-need-max`).type("{selectall}2").should("have.value", "2");
        cy.get(`${need("lead")} select.volunteer-need-default-select`).should("have.length", 2);
        requirements().then((rows) => {
            expect(rows[position.lead]).to.include({ maxCount: 2 });
            expect(rows[position.lead].defaults.map((d) => d.personId)).to.deep.eq([LEADER, SECOND]);
        });
    });

    it("opens the Generate dialog on the schedule's saved defaults, and staffs the new occurrences with them", () => {
        openSchedulesTab();
        openRowAction("generate");
        cy.get("#generateOccurrencesModal").should("be.visible");
        cy.get("#generate-form-loading").should("not.be.visible");
        const leadRow = `#generate-form-rows .generate-default-row[data-position-id="${position.lead}"]`;
        const readerRow = `#generate-form-rows .generate-default-row[data-position-id="${position.reader}"]`;
        cy.get(`${leadRow} select.generate-default-select`).should("have.length", 2);
        cy.get(`${leadRow} select.generate-default-select`).eq(0).should("have.value", String(LEADER));
        cy.get(`${leadRow} select.generate-default-select`).eq(1).should("have.value", String(SECOND));
        cy.get(`${leadRow} .generate-default-accepted`).eq(0).should("be.checked");
        cy.get(`${leadRow} .generate-default-accepted`).eq(1).should("not.be.checked");
        cy.get(`${readerRow} select.generate-default-select`).should("have.length", 1).and("have.value", String(READER));
        cy.get(`${readerRow} .generate-default-accepted`).should("not.be.checked");

        cy.intercept("POST", `**/api/ministries/schedules/${scheduleId}/generate`).as("generate");
        cy.get("#generate-form-save").click();
        cy.wait("@generate").then((interception) => {
            expect(interception.request.body.requirements).to.deep.include({
                positionId: position.lead,
                defaults: [
                    { personId: LEADER, accepted: true },
                    { personId: SECOND, accepted: false },
                ],
            });
            expect(interception.response.body.created).to.be.greaterThan(0);
            expect(interception.response.body.assigned).to.eq(interception.response.body.created * 3);
        });
        cy.get("#generateOccurrencesModal").should("not.be.visible");
    });

    it("keeps a default whose qualification was revoked, and marks it as no longer qualified", () => {
        cy.dbQuery("UPDATE volunteer_qualification_vqal SET vqal_Active = 0 WHERE vqal_per_ID = ? AND vqal_vpos_ID = ?", [
            READER,
            position.reader,
        ]);
        openSchedulesTab();
        openRowAction("edit");
        cy.get("#scheduleModal").should("be.visible");
        picker("reader").should("have.value", String(READER));
        picker("reader").find("option:selected").should("contain", "(no longer qualified)");
        cy.get(`${need("reader")} .volunteer-need-default-unqualified`).should("be.visible");
        cy.get("#schedule-form-save").click();
        cy.get("#scheduleModal").should("not.be.visible");

        openRowAction("generate");
        cy.get("#generate-form-loading").should("not.be.visible");
        cy.get(`#generate-form-rows .generate-default-row[data-position-id="${position.reader}"] select.generate-default-select`).should(
            "have.value",
            String(READER),
        );
        cy.get(`#generate-form-rows .generate-default-row[data-position-id="${position.reader}"] .generate-default-unqualified`).should(
            "be.visible",
        );
        requirements().then((rows) => {
            expect(rows[position.reader].defaults[0]).to.include({ personId: READER, qualified: false });
        });
        cy.dbQuery("UPDATE volunteer_qualification_vqal SET vqal_Active = 1 WHERE vqal_per_ID = ? AND vqal_vpos_ID = ?", [
            READER,
            position.reader,
        ]);
    });

    it("gives the portal's team page the same default pickers", () => {
        openSchedulesTab(`/portal/teams/${teamId}`);
        cy.get(`.volunteer-schedule-edit[data-schedule-id="${scheduleId}"]`, { timeout: 15000 })
            .closest("tr")
            .find("[data-bs-toggle='dropdown']")
            .click();
        cy.get(`.volunteer-schedule-edit[data-schedule-id="${scheduleId}"]`).should("be.visible").click();
        cy.get("#scheduleModal").should("be.visible");
        cy.get(`${need("lead")} select.volunteer-need-default-select`).should("have.length", 2);
        picker("lead", 0).should("have.value", String(LEADER));
        picker("lead", 1).should("have.value", String(SECOND));
    });
});
