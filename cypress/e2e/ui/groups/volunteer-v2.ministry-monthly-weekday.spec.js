/// <reference types="cypress" />

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const MINISTRIES_URL = "/api/ministries/ministries";

describe("Ministry New recurring event: Nth weekday of the month (#10906)", () => {
    const title = `Nth weekday ministry ${Date.now()}`;
    let originalVersion = "v1";
    let ministryId = 0;
    const createdEventIds = [];

    before(() => {
        cy.makePrivateAdminAPICall("GET", SETTING_URL, null, 200).then((resp) => {
            originalVersion = resp.body.value ?? resp.body.data ?? "v1";
        });
        cy.makePrivateAdminAPICall("POST", SETTING_URL, { value: "v2" }, 200);
        cy.makePrivateAdminAPICall("POST", MINISTRIES_URL, { name: title, description: "#10906 fixture" }, 201).then((resp) => {
            ministryId = resp.body.ministry.id;
        });
    });

    after(() => {
        cy.cleanupEvents(createdEventIds);
        cy.makePrivateAdminAPICall("POST", `${MINISTRIES_URL}/${ministryId}`, { active: false }, 200);
        cy.makePrivateAdminAPICall("DELETE", `${MINISTRIES_URL}/${ministryId}`, null, 200);
        cy.makePrivateAdminAPICall("POST", SETTING_URL, { value: originalVersion }, 200);
    });

    it("previews and creates the 2nd Tuesday of each month", () => {
        cy.setupAdminSession();
        cy.intercept("POST", `**${MINISTRIES_URL}/${ministryId}/events`).as("createEvents");
        cy.visit(`/ministries/${ministryId}`);
        cy.get("#nav-item-calendar").click();
        cy.get("#ministry-event-add-series-btn").click();

        cy.get("#ministry-event-form-title").should("be.visible").type(title);
        cy.get("#ministry-event-form-recur-type").select("monthly-weekday");
        cy.get("#ministry-event-form-wom-row").should("be.visible");
        cy.get("#ministry-event-form-dow-row").should("not.be.visible");
        cy.get("#ministry-event-form-wom").select("2");
        cy.get("#ministry-event-form-wom-dow").select("Tuesday");
        cy.get("#ministry-event-form-range-start").clear().type("2042-01-01");
        cy.get("#ministry-event-form-range-end").clear().type("2042-03-31");
        cy.get("#ministry-event-form-preview").should("contain.text", "Creates 3 events, on the 2nd Tuesday of each month");

        cy.get("#ministry-event-form-save").click();
        cy.wait("@createEvents").then(({ request, response }) => {
            expect(request.body.recurrence).to.deep.eq({ type: "monthly-weekday", wom: 2, dow: "Tuesday" });
            expect(response.statusCode).to.eq(201);
            createdEventIds.push(...response.body.events.map((event) => event.id));
            expect(response.body.events.map((event) => event.start.slice(0, 10))).to.deep.eq([
                "2042-01-14",
                "2042-02-11",
                "2042-03-11",
            ]);
        });
    });
});
