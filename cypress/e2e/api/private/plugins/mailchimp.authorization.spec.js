const MAILCHIMP_ROUTES = [
    "/plugins/mailchimp/dashboard",
    "/plugins/mailchimp/list/test-list/unsubscribed",
    "/plugins/mailchimp/list/test-list/missing",
    "/plugins/mailchimp/api/lists",
    "/plugins/mailchimp/api/list/test-list",
    "/plugins/mailchimp/api/list/test-list/missing",
    "/plugins/mailchimp/api/list/test-list/not-subscribed",
    "/plugins/mailchimp/api/person/1",
    "/plugins/mailchimp/api/family/1",
];

const getAs = (apiKey, url) =>
    cy.request({
        method: "GET",
        url,
        failOnStatusCode: false,
        headers: { "x-api-key": apiKey, Accept: "application/json" },
        withCredentials: false,
    });

describe("MailChimp plugin authorization", () => {
    let wasActive;

    before(() => {
        cy.makePrivateAdminAPICall("GET", "/plugins/api/plugins").then((response) => {
            wasActive = response.body.data.find((plugin) => plugin.id === "mailchimp").isActive;
        });
        cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/mailchimp/enable");
    });

    after(() => {
        if (wasActive === false) {
            cy.makePrivateAdminAPICall("POST", "/plugins/api/plugins/mailchimp/disable");
        }
    });

    [
        ["zero-permission user", "noperm.api.key"],
        ["standard non-admin user", "user.api.key"],
    ].forEach(([label, keyName]) => {
        describe(label, () => {
            MAILCHIMP_ROUTES.forEach((url) => {
                it(`gets 403 on GET ${url}`, () => {
                    getAs(Cypress.env(keyName), url).its("status").should("eq", 403);
                });
            });
        });
    });

    describe("admin", () => {
        MAILCHIMP_ROUTES.forEach((url) => {
            it(`is not refused on GET ${url}`, () => {
                getAs(Cypress.env("admin.api.key"), url).its("status").should("not.be.oneOf", [401, 403]);
            });
        });
    });
});
