/// <reference types="cypress" />

/**
 * GitHub Issue #10247: people in an inactive classification (sInactiveClassification)
 * are hidden from the Person Listing's default "active" view and from the home dashboard
 * People count, and appear under "inactive" and "all".
 *
 * The classification, the person and the setting are all put back in after().
 */
const LAST_NAME = `CypInactiveCls${Date.now()}`;
const created = { classificationId: null, personId: null };
let originalInactive;

// cy.request() calls make PHP issue a new session, so the browser login is redone
// (cached) right before every page request instead of once up front.
const pageBody = (path) => {
    cy.setupAdminSession();
    return cy.request(path).its("body");
};
const listBody = (status) => pageBody(`/people/list?personActiveStatus=${status}`);
const dashboardPeopleCount = () =>
    pageBody("/v2/dashboard").then((body) => Number(/id="peopleStatsDashboard">(\d+)</.exec(body)[1]));
const personLink = () => `/people/view/${created.personId}"`;
const toggleInactive = () =>
    cy
        .makePrivateAdminAPICall("POST", `/admin/api/options/1/${created.classificationId}/inactive`, {}, 200)
        .its("body.inactive");

describe("Inactive classifications apply to Person Listing and dashboard (#10247)", () => {
    before(() => {
        cy.getSystemConfig("sInactiveClassification").then((value) => {
            originalInactive = value;
        });
        cy.makePrivateAdminAPICall(
            "POST",
            "/admin/api/options/1",
            { name: `CypInactiveCls_${Date.now()}` },
            200,
        ).then((resp) => {
            created.classificationId = resp.body.optionId ?? resp.body.id;
            expect(created.classificationId, "created classification id").to.be.a("number");
        });

        // Person has no create API; the legacy editor is the only way in.
        cy.freshAdminFormLogin({ clearAll: true, visible: true });
        cy.visit("/PersonEditor.php");
        cy.get("#FirstName").type("Inactive");
        cy.get("#LastName").type(LAST_NAME);
        cy.get("#Gender").select("1");
        // created.classificationId is set by the earlier .then(); read it when this step runs, not when it is queued.
        cy.then(() => cy.get("#Classification").select(String(created.classificationId)));
        cy.get('button[name="PersonSubmit"]').click();
        cy.location("pathname")
            .should("include", "/people/view/")
            .then((pathname) => {
                created.personId = Number(pathname.split("/").pop());
            });
    });

    afterEach(() => {
        cy.restoreSystemConfig("sInactiveClassification", originalInactive);
    });

    after(() => {
        cy.restoreSystemConfig("sInactiveClassification", originalInactive);
        cy.cleanupPeople([created.personId]);
        if (created.classificationId !== null) {
            cy.makePrivateAdminAPICall(
                "DELETE",
                `/admin/api/options/1/${created.classificationId}`,
                null,
                [200, 404],
            );
        }
    });

    it("lists the person while the classification is active", () => {
        listBody("active").should("contain", personLink());
    });

    it("hides the person from the default listing and shows it under inactive and all", () => {
        toggleInactive().should("include", created.classificationId);

        listBody("active").should("not.contain", personLink());
        listBody("inactive").should("contain", personLink());
        listBody("all").should("contain", personLink());
    });

    it("keeps the person out of the inactive view when the classification is not inactive", () => {
        listBody("inactive").should("not.contain", personLink());
    });

    it("drops the home dashboard People count by one, as the listing does", () => {
        dashboardPeopleCount().then((before) => {
            toggleInactive();
            dashboardPeopleCount().should("eq", before - 1);
        });
    });
});
