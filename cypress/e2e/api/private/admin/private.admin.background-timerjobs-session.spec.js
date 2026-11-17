/// <reference types="cypress" />

import { inOwnCookieJar } from "../../../../support/cookie-jar";
import { absoluteUrl } from "../../../../support/urls";

describe("API Private Admin - Background Timer Jobs release the session", () => {
    const SETTINGS = ["bEnabledEmail", "sSMTPHost", "iSMTPTimeout"];
    const original = {};

    const configUrl = (name) => `/admin/api/system/config/${name}`;
    const writeConfig = (name, value) => cy.makePrivateAdminAPICall("POST", configUrl(name), { value }, 200);

    before(() => {
        cy.rememberTestEnv(["admin.api.key"]);
        SETTINGS.forEach((name) =>
            cy.makePrivateAdminAPICall("GET", configUrl(name), null, 200).then((resp) => {
                original[name] = resp.body.value;
            }),
        );
    });

    after(() => {
        SETTINGS.forEach((name) => writeConfig(name, original[name] ?? ""));
        cy.makePrivateAdminAPICall("DELETE", "/admin/api/email/queue", null, 200);
    });

    it("answers the same browser's other requests while a slow run sends mail", () => {
        cy.intercept("POST", "**/api/background/timerjobs", (req) => {
            if (!req.body?.force) {
                req.reply({ ran: false });
            }
        });
        cy.setupAdminSession();
        cy.visit("/v2/dashboard");

        inOwnCookieJar(() => {
            writeConfig("bEnabledEmail", "1");
            // Mailpit's web port accepts the connection and never greets, so each send waits out the SMTP timeout.
            writeConfig("sSMTPHost", "mailserver:8025");
            writeConfig("iSMTPTimeout", "2");
            cy.makePrivateAdminAPICall("POST", "/api/email/send", { personIds: [2], subject: `crm-11176-${Date.now()}`, body: "Slow" }, 200);
        });

        cy.window().then({ timeout: 60000 }, (win) => {
            const timed = (path, init = {}) =>
                win.fetch(absoluteUrl(path), { credentials: "same-origin", ...init }).then((resp) => ({
                    status: resp.status,
                    finishedAt: win.performance.now(),
                }));
            const startedAt = win.performance.now();
            const jobs = timed("/api/background/timerjobs", {
                method: "POST",
                headers: { "content-type": "application/json" },
                body: JSON.stringify({ force: true }),
            });
            const other = new Promise((resolve) => win.setTimeout(resolve, 500)).then(() => timed("/api/groups/"));

            return Promise.all([jobs, other]).then(([jobsResult, otherResult]) => {
                expect(jobsResult.status).to.equal(200);
                expect(otherResult.status).to.equal(200);
                expect(jobsResult.finishedAt - startedAt, "the jobs waited for the mail server").to.be.greaterThan(1500);
                expect(otherResult.finishedAt, "the other request finished while the jobs ran").to.be.lessThan(jobsResult.finishedAt);
            });
        });
    });
});
