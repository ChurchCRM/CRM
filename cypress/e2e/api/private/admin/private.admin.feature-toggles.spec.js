/// <reference types="cypress" />

describe("API Private Admin Feature Toggles", () => {
    const url = "/admin/api/system/feature-toggles";
    let original;

    before(() => {
        cy.getSystemConfig("bEnabledEvents").then((v) => {
            original = v;
        });
    });

    after(() => {
        cy.restoreSystemConfig("bEnabledEvents", original);
    });

    it("returns the current state of requested toggles", () => {
        cy.makePrivateAdminAPICall(
            "GET",
            `${url}?settings=bEnabledEvents,bEnabledFinance`,
            null,
            200,
        ).then((resp) => {
            expect(resp.body.toggles).to.have.all.keys("bEnabledEvents", "bEnabledFinance");
            expect(resp.body.toggles.bEnabledEvents).to.be.a("boolean");
            expect(resp.body.toggles.bEnabledFinance).to.be.a("boolean");
        });
    });

    it("rejects a GET without settings, with a non-boolean or with an unknown setting", () => {
        cy.makePrivateAdminAPICall("GET", url, null, 400);
        cy.makePrivateAdminAPICall("GET", `${url}?settings=sSMTPHost`, null, 400);
        cy.makePrivateAdminAPICall("GET", `${url}?settings=notARealSetting`, null, 400);
    });

    it("persists a saved toggle across requests", () => {
        cy.makePrivateAdminAPICall("POST", url, { bEnabledEvents: "0" }, 200);
        cy.makePrivateAdminAPICall("GET", `${url}?settings=bEnabledEvents`, null, 200).then(
            (resp) => expect(resp.body.toggles.bEnabledEvents).to.eq(false),
        );
        cy.makePrivateAdminAPICall("POST", url, { bEnabledEvents: "1" }, 200);
        cy.makePrivateAdminAPICall("GET", `${url}?settings=bEnabledEvents`, null, 200).then(
            (resp) => expect(resp.body.toggles.bEnabledEvents).to.eq(true),
        );
    });

    it("rejects non-admin users", () => {
        cy.makePrivateUserAPICall("GET", `${url}?settings=bEnabledEvents`, null, 403);
        cy.makePrivateUserAPICall("POST", url, { bEnabledEvents: "0" }, 403);
        cy.getSystemConfig("bEnabledEvents").should("eq", original);
    });

    it("saves a boolean toggle", () => {
        cy.makePrivateAdminAPICall("POST", url, { bEnabledEvents: "0" }, 200).then((resp) => {
            expect(resp.body.success).to.eq(true);
        });
        cy.getSystemConfig("bEnabledEvents").should("eq", "0");

        cy.makePrivateAdminAPICall("POST", url, { bEnabledEvents: "1" }, 200);
        cy.getSystemConfig("bEnabledEvents").should("eq", "1");
    });

    it("rejects an empty body", () => {
        cy.makePrivateAdminAPICall("POST", url, {}, 400);
    });

    it("rejects a non-boolean setting without changing it", () => {
        cy.getSystemConfig("sSMTPHost").then((before) => {
            cy.makePrivateAdminAPICall("POST", url, { sSMTPHost: "evil.example.org" }, 400);
            cy.getSystemConfig("sSMTPHost").should("eq", before);
        });
    });

    it("rejects an unknown setting", () => {
        cy.makePrivateAdminAPICall("POST", url, { notARealSetting: "1" }, 400);
    });

    it("writes nothing when a request mixes valid and invalid keys", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            url,
            { bEnabledEvents: "0", notARealSetting: "1" },
            400,
        );
        cy.getSystemConfig("bEnabledEvents").should("eq", original);
    });
});
