/// <reference types="cypress" />

/**
 * Browser-level regression for #9010. Exercise the real bundled token
 * success handler but stub its API response: tests must never create or
 * replace real calendar access tokens in the shared Cypress database.
 */
describe("Calendar access-token feedback", () => {
    beforeEach(() => {
        cy.setupAdminSession();
        cy.visit("/event/calendars");
    });

    function verifyTokenFeedback(externalAccessEnabled) {
        cy.window().its("calendarPropertiesModal.newAccessToken").should("be.a", "function");
        cy.get("#calendarApiWarning").should("exist");

        cy.window().then((win) => {
            const original = { Id: 999999, Name: "Cypress test calendar", AccessToken: null };
            const generated = { ...original, AccessToken: "cypress-fake-token" };
            const modal = win.$('<div><div class="bootbox-body"></div></div>').appendTo(win.document.body);

            // A calendar with no previous token starts without a visible warning.
            // Toggle the page argument, not the admin setting in the database.
            win.CRM.calendarJSArgs.bEnableExternalCalendarAPI = externalAccessEnabled;
            win.$("#calendarApiWarning").addClass("d-none");

            const expectedSuccess = win.i18next.t("A Calendar access token has been generated and saved.");
            const expectedWarning = win.$("#calendarApiWarning p").text().trim();
            expect(expectedWarning, "server-rendered sharing-disabled warning").not.to.equal("");

            win.calendarPropertiesModal.calendar = original;
            win.calendarPropertiesModal.modal = modal;

            cy.stub(win.calendarPropertiesModal, "getBootboxContent").returns("<div>Mock token details</div>");
            cy.stub(win.calendarPropertiesModal, "loadMinistryGrants");
            const request = cy.stub(win.CRM, "APIRequest").callsFake(({ method, path }) => {
                expect(method).to.equal("POST");
                expect(path).to.equal("calendars/999999/NewAccessToken");
                return {
                    done(callback) {
                        callback(generated);
                        return this;
                    },
                };
            });
            const alert = cy.stub(win.bootbox, "alert");

            // Call the production handler, which receives the fake success.
            win.calendarPropertiesModal.newAccessToken();

            expect(request).to.have.been.calledOnce;
            expect(alert).to.have.been.calledOnce;
            const alertHtml = alert.firstCall.args[0];
            const alertText = win.$("<div>").html(alertHtml).text();
            expect(alertText).to.include(expectedSuccess);

            if (externalAccessEnabled) {
                expect(alertText).not.to.include(expectedWarning);
                expect(alertHtml).not.to.include("text-warning");
            } else {
                expect(alertText).to.include(expectedWarning);
                expect(alertHtml).to.include("text-warning");
            }
        });

        cy.get("#calendarApiWarning").should(
            externalAccessEnabled ? "have.class" : "not.have.class",
            "d-none",
        );
    }

    it("shows the existing warning immediately after token creation when sharing is disabled", () => {
        verifyTokenFeedback(false);
    });

    it("shows only normal success feedback when calendar sharing is enabled", () => {
        verifyTokenFeedback(true);
    });
});
