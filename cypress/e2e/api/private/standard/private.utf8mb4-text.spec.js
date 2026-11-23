/// <reference types="cypress" />

describe("API Private - 4-byte characters in group and calendar names (#11846)", () => {
    const groupName = `Youth 🎉 ${Date.now()}`;
    const calendarName = `Choir 🎂 ${Date.now()}`;
    let groupId = null;
    let calendarId = null;

    after(() => {
        if (groupId !== null) {
            cy.makePrivateAdminAPICall("DELETE", `/api/groups/${groupId}`, null, [200, 404]);
        }
        if (calendarId !== null) {
            cy.makePrivateAdminAPICall("DELETE", `/api/calendars/${calendarId}`, null, [200, 404]);
        }
    });

    it("saves an emoji in a group name and a calendar name and reads both back", () => {
        cy.makePrivateAdminAPICall("POST", "/api/groups/", { groupName, description: "" }, 200).then((resp) => {
            groupId = resp.body.Id;
            cy.makePrivateAdminAPICall("GET", `/api/groups/${groupId}`, null, 200).then((getResp) => {
                expect(getResp.body.Name).to.eq(groupName);
            });
        });

        cy.makePrivateAdminAPICall(
            "POST",
            "/api/calendars",
            { Name: calendarName, ForegroundColor: "#FFFFFF", BackgroundColor: "#1565C0" },
            200,
        ).then((resp) => {
            calendarId = resp.body.Id;
            cy.makePrivateAdminAPICall("GET", `/api/calendars/${calendarId}`, null, 200).then((getResp) => {
                expect(getResp.body.Calendars[0].Name).to.eq(calendarName);
            });
        });
    });
});
