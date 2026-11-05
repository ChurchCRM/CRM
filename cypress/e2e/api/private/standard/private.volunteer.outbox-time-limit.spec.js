/// <reference types="cypress" />

const VOLUNTEER_URL = "/api/ministries";
const PREFIX = "OTL11177";
const SETTINGS = ["sVolunteerVersion", "bEnabledEmail", "sSMTPHost", "iSMTPTimeout", "sLastTimerJobsRunDateTime"];
const PERSON_LEADER = 3;
const PERSON_COORDINATOR = 52;
const PEOPLE_ADDED = [26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 48];
const WEB_RUN_SECONDS = 15;

const original = {};
let ministryId = 0;
let teamId = 0;

const api = (keyName, method, url, body, expectedStatus = 200, timeoutMs) =>
    cy.makePrivateAPICall(Cypress.testEnv(keyName), method, url, body, expectedStatus, timeoutMs);

const runTimerJobs = (timeoutMs) =>
    api("admin.api.key", "POST", "/api/background/timerjobs", { force: true }, 200, timeoutMs);

const removeFixtures = () =>
    api("admin.api.key", "GET", `${VOLUNTEER_URL}/ministries`).then((resp) => {
        for (const ministry of resp.body.ministries.filter((m) => m.name.startsWith(PREFIX))) {
            api("admin.api.key", "POST", `${VOLUNTEER_URL}/ministries/${ministry.id}`, { active: false }, [200, 404]);
            api("admin.api.key", "DELETE", `${VOLUNTEER_URL}/ministries/${ministry.id}`, null, [200, 404]);
        }
    });

before(() => {
    cy.rememberTestEnv(["admin.api.key", "user.api.key"]);
});

describe("Volunteer v2 — the outbox stops at the web run's time limit (#11177)", () => {
    before(() => {
        SETTINGS.forEach((name) =>
            cy.getSystemConfig(name).then((value) => {
                original[name] = value;
            }),
        );
        cy.setSystemConfig("sVolunteerVersion", "v2");
        removeFixtures();

        api("admin.api.key", "POST", `${VOLUNTEER_URL}/ministries`, { name: `${PREFIX} Hospitality` }, 201).then((resp) => {
            ministryId = resp.body.ministry.id;
        });
        cy.then(() => {
            api("admin.api.key", "POST", `${VOLUNTEER_URL}/ministries/${ministryId}/teams`, { name: `${PREFIX} Greeters` }, 201).then(
                (resp) => {
                    teamId = resp.body.team.id;
                },
            );
        });
        cy.then(() => {
            api("admin.api.key", "POST", `${VOLUNTEER_URL}/scopes`, { personId: PERSON_LEADER, scopeType: "team", scopeId: teamId }, [200, 201]);
            api("admin.api.key", "POST", `${VOLUNTEER_URL}/scopes`, { personId: PERSON_COORDINATOR, scopeType: "ministry", scopeId: ministryId }, [
                200, 201,
            ]);
        });
        runTimerJobs(120000);
    });

    after(() => {
        ["bEnabledEmail", "sSMTPHost", "iSMTPTimeout"].forEach((name) => cy.restoreSystemConfig(name, original[name]));
        removeFixtures();
        runTimerJobs(120000);
        cy.restoreSystemConfig("sVolunteerVersion", original.sVolunteerVersion);
        cy.restoreSystemConfig("sLastTimerJobsRunDateTime", original.sLastTimerJobsRunDateTime);
    });

    it("stops starting sends once the web run reaches its time limit", () => {
        cy.then(() => {
            PEOPLE_ADDED.forEach((personId) =>
                api("user.api.key", "POST", `${VOLUNTEER_URL}/teams/${teamId}/pool/${personId}`, {}, 201)
                    .its("body.notified")
                    .should("eq", 1),
            );
        });

        cy.setSystemConfig("bEnabledEmail", "1");
        // Mailpit's web port accepts the connection and never greets, so each send waits out the SMTP timeout.
        cy.setSystemConfig("sSMTPHost", "mailserver:8025");
        cy.setSystemConfig("iSMTPTimeout", "2");

        cy.then(() => {
            const startedAt = Date.now();
            runTimerJobs(180000).then(() => {
                expect(Date.now() - startedAt, "one web run, plus at most a few slow sends").to.be.lessThan((WEB_RUN_SECONDS + 15) * 1000);
            });
        });
        api("admin.api.key", "GET", `/api/email/log?personId=${PERSON_COORDINATOR}&limit=100`).then((resp) => {
            const attempted = resp.body.rows.filter((row) => row.kind === "volunteerpooladd" && row.subject.includes(PREFIX));
            expect(attempted.every((row) => row.status === "failed"), "every attempt waited on the silent server").to.eq(true);
            expect(attempted.length, "the run attempted deliveries").to.be.greaterThan(0);
            expect(attempted.length, "the rest wait for the next run").to.be.lessThan(PEOPLE_ADDED.length);
        });
    });
});
