/// <reference types="cypress" />

import { createPersonInEditor, savePersonEditor } from "../../../support/person-editor";
import { pdfText } from "../../../support/pdf-text";

/**
 * GitHub Issue #10263: every output that picks people honors the Inactive classification
 * list. Each case sends one active and one inactive person through the output's own route.
 */
const STAMP = Date.now();
const ACTIVE = { first: "Outa", last: `Activecls${STAMP}`, email: `outa${STAMP}@example.com` };
const INACTIVE = { first: "Outi", last: `Inactivecls${STAMP}`, email: `outi${STAMP}@example.com` };
const GROUP_ID = 1;
const DIAZ_MEMBER_IDS = [43, 44, 48, 49];
const DIAZ_CLASSIFICATION = 3;
const created = { classificationId: null, ids: {} };
let originalInactive;

const person = (who) =>
    createPersonInEditor({
        FirstName: who.first,
        LastName: who.last,
        Email: who.email,
        Gender: 1,
        Classification: who === INACTIVE ? created.classificationId : 1,
        Family: 1,
        Address1: "12 Orchard Lane",
        City: "Springfield",
        Zip: "62704",
    }).then((id) => {
        created.ids[who.last] = id;
    });

const expectOnlyActive = (text) => {
    expect(text, "active person").to.include(ACTIVE.last);
    expect(text, "inactive person").to.not.include(INACTIVE.last);
};

const fetchPdfText = (request) =>
    cy.request({ ...request, encoding: "binary" }).then((response) => {
        expect(response.headers["content-type"]).to.include("application/pdf");
        return pdfText(response.body).then((lines) => lines.join(" "));
    });

const cart = (method, body = {}) =>
    cy.request({ method, url: "/api/cart/", headers: { "Content-Type": "application/json" }, body: JSON.stringify(body) });

describe("Inactive classifications apply to every output (#10263)", () => {
    before(() => {
        cy.getSystemConfig("sInactiveClassification").then((value) => {
            originalInactive = value;
        });
        cy.makePrivateAdminAPICall("POST", "/admin/api/options/1", { name: `CypOutputsCls_${STAMP}` }, 200).then((resp) => {
            created.classificationId = resp.body.optionId ?? resp.body.id;
        });
        cy.then(() => person(ACTIVE));
        cy.then(() => person(INACTIVE));
        cy.then(() => {
            cy.makePrivateAdminAPICall("POST", `/admin/api/options/1/${created.classificationId}/inactive`, {}, 200);
            Object.values(created.ids).forEach((id) =>
                cy.makePrivateAdminAPICall("POST", `/api/groups/${GROUP_ID}/addperson/${id}`, {}, 200),
            );
        });
    });

    beforeEach(() => {
        cy.setupAdminSession();
    });

    after(() => {
        cy.restoreSystemConfig("sInactiveClassification", originalInactive);
        cart("DELETE");
        Object.values(created.ids).forEach((id) =>
            cy.makePrivateAdminAPICall("DELETE", `/api/groups/${GROUP_ID}/removeperson/${id}`, null, [200, 404]),
        );
        cy.cleanupPeople(Object.values(created.ids));
        if (created.classificationId !== null) {
            cy.makePrivateAdminAPICall("DELETE", `/admin/api/options/1/${created.classificationId}`, null, [200, 404]);
        }
    });

    it("leaves inactive people out of the CSV export", () => {
        cy.request({
            method: "POST",
            url: "/admin/export/csv",
            form: true,
            body: { output: "csv", Format: "Default", Source: "person", familyonly: "false" },
        }).then((response) => expectOnlyActive(response.body));
    });

    it("leaves inactive people out of the email lists", () => {
        cy.request("/api/people/emails").then((response) => {
            expect(JSON.stringify(response.body)).to.include(ACTIVE.email);
            expect(JSON.stringify(response.body)).to.not.include(INACTIVE.email);
        });
        cy.request(`/api/groups/${GROUP_ID}/emails`).then((response) => {
            expect(JSON.stringify(response.body)).to.include(ACTIVE.email);
            expect(JSON.stringify(response.body)).to.not.include(INACTIVE.email);
        });
    });

    it("leaves inactive people out of cart emails and labels", () => {
        cart("DELETE");
        cart("POST", { Persons: Object.values(created.ids) });
        cy.request("/api/cart/emails").then((response) => {
            expect(JSON.stringify(response.body)).to.include(ACTIVE.email);
            expect(JSON.stringify(response.body)).to.not.include(INACTIVE.email);
        });
        fetchPdfText({
            url: "/people/labels?labeltype=5160&labelfont=Helvetica&labelfontsize=default&startrow=1&startcol=1&filetype=PDF&groupbymode=indiv",
        }).then(expectOnlyActive);
        fetchPdfText({ url: "/people/labels/name-tags?labeltype=74536&labelfont=Times&labelfontsize=36" }).then((text) => {
            expect(text).to.include(ACTIVE.first);
            expect(text).to.not.include(INACTIVE.first);
        });
    });

    it("leaves inactive people out of the group directory report", () => {
        fetchPdfText({
            method: "POST",
            url: "groups/reports/group-directory",
            form: true,
            body: { GroupID: GROUP_ID, GroupRole: "" },
        }).then(expectOnlyActive);
    });

    it("leaves inactive people out of the people dashboard widgets", () => {
        Object.values(created.ids).forEach((id) => savePersonEditor(id, {}));
        cy.setupAdminSession();
        ["latest", "updated"].forEach((widget) =>
            cy.request(`/api/persons/${widget}?days=1`).then((response) => {
                expectOnlyActive(JSON.stringify(response.body));
            }),
        );
    });

    it("shows Unassigned as a read-only row of the classifications editor", () => {
        cy.visit("/admin/system/options?mode=classes");
        cy.get("#unassignedRow").should("contain", "Unassigned");
        cy.get("#unassignedRow input[type=checkbox]").should("have.length", 2).and("be.disabled");
    });

    describe("finance reports for a family whose members are all inactive", () => {
        const diazFilter = "FYID=23&family%5B%5D=9&output=pdf";
        const download = (url) => ({
            method: "POST",
            url,
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: diazFilter,
        });

        const classifyDiaz = (classificationId) =>
            DIAZ_MEMBER_IDS.forEach((id) => savePersonEditor(id, { Classification: classificationId }));

        afterEach(() => {
            classifyDiaz(DIAZ_CLASSIFICATION);
        });

        ["family-pledge-summary", "pledge-reminders"].forEach((report) => {
            it(`${report} lists the family while a member is active and drops it when none is`, () => {
                cy.setupFinanceOnlySession();
                fetchPdfText(download(`/finance/reports/${report}/download`)).then((text) => {
                    expect(text).to.include("Diaz");
                });
                cy.then(() => classifyDiaz(created.classificationId));
                cy.setupFinanceOnlySession();
                cy.request({ ...download(`/finance/reports/${report}/download`), encoding: "binary", followRedirect: false }).then(
                    (response) => {
                        expect(response.headers["content-type"]).to.include("text/html");
                    },
                );
            });
        });
    });
});
