// The server counts days in the church's time zone (sTimeZone). A CI runner's
// browser is on UTC, a day ahead of that zone every evening, so specs that build
// dates from the browser's clock switch it to the church's zone. Chromium only.

function setBrowserTimeZone(timezoneId) {
    return Cypress.automation("remote:debugger:protocol", {
        command: "Emulation.setTimezoneOverride",
        params: { timezoneId },
    });
}

Cypress.Commands.add("useChurchTimeZone", () =>
    cy.getSystemConfig("sTimeZone").then((zone) => setBrowserTimeZone(zone || "UTC")),
);

Cypress.Commands.add("useHostTimeZone", () => cy.then(() => setBrowserTimeZone("")));
