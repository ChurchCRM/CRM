/// <reference types="cypress" />

describe("Administrator adjusts scheduled-task timing on the dashboard", () => {
    const keys = ["iTimerJobsStaleHours", "iTimerJobsMinIntervalMinutes"];
    const original = {};
    const input = (key) => `#scheduledTasksSettings input[name='${key}']`;

    before(() => {
        keys.forEach((key) => cy.getSystemConfig(key).then((value) => { original[key] = value; }));
        cy.restoreSystemConfig(keys[0], "26");
        cy.restoreSystemConfig(keys[1], "15");
    });

    after(() => {
        keys.forEach((key) => cy.restoreSystemConfig(key, original[key]));
    });

    it("auto-saves both controls, preserves zero values and keeps the run marker internal", () => {
        cy.freshAdminFormLogin({ enabled: true });
        cy.intercept("POST", "**/admin/api/system/config/*").as("saveTiming");
        cy.visit("/admin/");
        cy.get(input(keys[0])).should("have.value", "26");
        cy.get(input(keys[1])).should("have.value", "15");
        cy.get("[name='sLastTimerJobsRunDateTime']").should("not.exist");
        [["48", "0"], ["0", "3"]].forEach((values) => {
            keys.forEach((key, index) => {
                cy.get(input(key)).clear().type(values[index]).blur();
                cy.wait("@saveTiming").its("response.statusCode").should("eq", 200);
            });
            cy.reload();
            keys.forEach((key, index) => cy.get(input(key)).should("have.value", values[index]));
        });
        cy.get("#timer-jobs-stale-warning").should("not.exist");
        keys.forEach((key, index) => cy.getSystemConfig(key).should("eq", index === 0 ? "0" : "3"));
    });
});
