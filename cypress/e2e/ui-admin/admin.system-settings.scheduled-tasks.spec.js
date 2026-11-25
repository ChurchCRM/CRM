/// <reference types="cypress" />

describe("Administrator adjusts the scheduled-task warning", () => {
    const key = "iTimerJobsStaleHours";
    const input = `#scheduledTasksSettings input[name='${key}']`;
    let original;

    before(() => cy.getSystemConfig(key).then((value) => { original = value; }));
    after(() => cy.restoreSystemConfig(key, original));

    it("auto-saves the warning threshold, keeps 0, and shows no internal timing fields", () => {
        cy.restoreSystemConfig(key, "26");
        cy.freshAdminFormLogin({ enabled: true });
        cy.intercept("POST", `**/admin/api/system/config/${key}`).as("save");
        cy.visit("/admin/settings");
        cy.get(input).should("have.value", "26");
        cy.get("[name='iTimerJobsMinIntervalMinutes'], [name='sLastTimerJobsRunDateTime']").should("not.exist");
        cy.get(input).clear().type("0").blur();
        cy.wait("@save").its("response.statusCode").should("eq", 200);
        cy.reload();
        cy.get(input).should("have.value", "0");
        cy.visit("/admin/");
        cy.get("#timer-jobs-stale-warning").should("not.exist");
    });
});
