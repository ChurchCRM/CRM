/// <reference types="cypress" />

/**
 * Volunteer Opportunities (V1) on their way out (#10606): hidden where nobody uses them,
 * announced where somebody does, and converted to Ministries with a preview.
 *
 * Seed facts: Mathew Campbell (2), Constance Hart (6), Marion Hart (7) and Lena Black (100)
 * are living; Salvador Berry (68) is deceased. The seed holds no opportunities.
 *
 * Order inside every hook is API setup → freshAdminLogin() → cy.visit(), because an
 * x-api-key request replaces the browser's session cookie.
 */

const SETTING_URL = "/admin/api/system/config/sVolunteerVersion";
const V1_API = "/admin/api/volunteer-opportunities";
const CONVERSION_API = "/admin/api/volunteer-conversion";
const PREFIX = "Cypress V1";
const GREETER = `${PREFIX} Greeter`;
const USHER = `${PREFIX} Usher`;
const MUSIC = `${PREFIX} Music`;
const KITCHEN = `${PREFIX} Kitchen`;
const TARGET = `${PREFIX} Ministries`;
const HOSPITALITY = `${PREFIX} Hospitality`;

const opportunities = {};

function freshAdminLogin() {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(Cypress.testEnv("admin.username"));
    cy.get("input[name=Password]").type(Cypress.testEnv("admin.password") + "{enter}");
    cy.location("pathname").should("not.eq", "/admin/");
}

function setVersion(value) {
    cy.makePrivateAdminAPICall("POST", SETTING_URL, { value }, 200);
}

function setConfig(name, value) {
    cy.makePrivateAdminAPICall("POST", `/admin/api/system/config/${name}`, { value }, 200);
}

function createOpportunity(name) {
    return cy.makePrivateAdminAPICall("POST", V1_API, { name, description: `${name} for the spec` }, 201).then((resp) => {
        opportunities[name] = resp.body.volunteerOpportunity.id;
    });
}

function link(personId, names) {
    cy.then(() => {
        const body = {};
        names.forEach((name, i) => {
            body[`VolunteerOpportunityIDs[${i}]`] = String(opportunities[name]);
        });
        cy.request({ method: "POST", url: `/people/view/${personId}`, form: true, body, followRedirect: false });
    });
}

function ministryByName(name) {
    return cy
        .makePrivateAdminAPICall("GET", "/api/ministries/ministries", null, 200)
        .then((resp) => resp.body.ministries.find((ministry) => ministry.name === name));
}

function cleanUp() {
    setVersion("both");
    cy.makePrivateAdminAPICall("GET", "/api/ministries/ministries", null, 200).then((resp) => {
        resp.body.ministries
            .filter((ministry) => ministry.name.startsWith(PREFIX))
            .forEach((ministry) => {
                cy.makePrivateAdminAPICall("POST", `/api/ministries/ministries/${ministry.id}`, { active: false }, [200, 404]);
                cy.makePrivateAdminAPICall("DELETE", `/api/ministries/ministries/${ministry.id}`, null, [200, 404]);
            });
    });
    cy.makePrivateAdminAPICall("GET", V1_API, null, 200).then((resp) => {
        const mine = resp.body.volunteerOpportunities.filter((opportunity) => opportunity.name.startsWith(PREFIX));
        if (mine.length === 0) {
            return;
        }
        freshAdminLogin();
        [2, 6, 7, 68, 100].forEach((personId) =>
            mine.forEach((opportunity) => cy.request(`/people/view/${personId}?RemoveVO=${opportunity.id}`)),
        );
        mine.forEach((opportunity) => cy.makePrivateAdminAPICall("DELETE", `${V1_API}/${opportunity.id}`, null, 200));
    });
    setConfig("aVolunteerV1Conversion", "{}");
    setConfig("sVolunteerV1NoticeDismissed", "");
    setVersion("v1");
}

before(() => {
    cy.rememberTestEnv(["admin.username", "admin.password"]);
    cleanUp();
});

after(() => {
    cleanUp();
});

describe("Volunteer Opportunities retirement (#10606)", () => {
    describe("on V2 with no opportunities", () => {
        beforeEach(() => {
            setVersion("v2");
            freshAdminLogin();
        });

        it("hides every Volunteer Opportunities screen", () => {
            cy.visit("/people/view/2");
            cy.get("#nav-item-volunteer").should("not.exist");
            cy.get("a[href$='VolunteerOpportunityEditor.php']").should("not.exist");

            cy.visit("/VolunteerOpportunityEditor.php");
            cy.location("pathname").should("eq", "/ministries/dashboard");

            cy.visit("/admin/people");
            cy.contains("a", "Volunteer Opportunities").should("not.exist");

            cy.visit("/admin/system/feature-toggles");
            cy.get("select[data-setting='sVolunteerVersion'] option").then(($options) => {
                expect([...$options].map((option) => option.value)).to.deep.eq(["v2"]);
            });

            cy.visit("/admin/");
            cy.get("#volunteer-v1-notice").should("not.exist");

            cy.request({ url: "/people/reports/volunteers", failOnStatusCode: false }).its("status").should("eq", 404);
        });
    });

    describe("with opportunities to convert", () => {
        before(() => {
            createOpportunity(GREETER);
            createOpportunity(USHER);
            createOpportunity(MUSIC);
            setVersion("v1");
            freshAdminLogin();
            link(2, [GREETER]);
            link(6, [GREETER]);
            link(68, [GREETER]);
            link(7, [USHER, MUSIC]);
            link(100, [MUSIC]);
        });

        it("shows them again on V2 while any are unconverted, with the retirement notice", () => {
            setVersion("v2");
            freshAdminLogin();

            cy.visit("/admin/");
            cy.get("#volunteer-v1-notice")
                .should("contain.text", "Convert your 3 opportunities and 5 volunteers")
                .find("#volunteer-v1-notice-convert")
                .should("have.attr", "href", "/admin/volunteer-conversion");

            cy.visit("/people/view/7");
            cy.get("#nav-item-volunteer").should("contain.text", "Volunteer (Legacy)").click();
            cy.get("#volunteer #volunteer-v1-notice").should("be.visible");

            cy.visit("/VolunteerOpportunityEditor.php");
            cy.location("pathname").should("eq", "/VolunteerOpportunityEditor.php");
            cy.get("#volunteer-v1-notice").should("be.visible");

            cy.visit("/admin/system/feature-toggles");
            cy.get("select[data-setting='sVolunteerVersion'] option").should("have.length", 3);
        });

        it("dismisses the dashboard notice until the next upgrade", () => {
            freshAdminLogin();
            cy.visit("/admin/");
            cy.get(".js-dismiss-volunteer-v1-notice").click();
            cy.get("#volunteer-v1-notice").should("not.exist");
            cy.reload();
            cy.get("#volunteer-v1-notice").should("not.exist");
            setConfig("sVolunteerV1NoticeDismissed", "");
        });

        it("previews and converts them, sending no email and moving a V1 install to Both", () => {
            setVersion("v1");
            freshAdminLogin();
            cy.intercept("GET", "**/admin/api/volunteer-conversion/preview*").as("preview");
            cy.intercept("POST", "**/admin/api/volunteer-conversion").as("convert");
            cy.visit("/admin/volunteer-conversion");
            cy.wait("@preview");

            cy.get("#volunteerConversionMinistryName").clear().type(TARGET);
            cy.wait("@preview");
            cy.get(`#volunteerConversionTable tr[data-opportunity='${opportunities[MUSIC]}'] select`).select("ministry");
            cy.wait("@preview").its("response.body.totals").should("deep.include", {
                positions: 2,
                ministries: 1,
                qualifications: 5,
                skipped: 1,
            });
            cy.get(`#volunteerConversionTable tr[data-opportunity='${opportunities[GREETER]}']`).should("contain.text", "Salvador Berry (deceased)");

            cy.get("#volunteerConversionRun").click();
            cy.get(".bootbox .btn-primary").click();
            cy.wait("@convert").its("response.statusCode").should("eq", 200);
            cy.get("#volunteerConversionDone").should("contain.text", "2 positions and 1 ministries, 5 volunteers qualified");

            cy.getSystemConfig("sVolunteerVersion").should("eq", "both");
            ministryByName(TARGET).then((ministry) => {
                expect(ministry, TARGET).to.exist;
                cy.makePrivateAdminAPICall("GET", `/api/ministries/ministries/${ministry.id}/positions`, null, 200).then((resp) => {
                    const positions = resp.body.positions;
                    expect(positions.map((position) => position.name)).to.have.members([GREETER, USHER]);
                    positions.forEach((position) => expect(position.selfAssignable, position.name).to.eq(false));
                    const greeter = positions.find((position) => position.name === GREETER);
                    cy.makePrivateAdminAPICall("GET", `/api/ministries/positions/${greeter.id}/qualifications`, null, 200)
                        .its("body.qualifications")
                        .then((qualifications) => {
                            expect(qualifications.map((qualification) => qualification.personId)).to.have.members([2, 6]);
                        });
                });
            });
            ministryByName(MUSIC).should("exist");
        });

        it("converts only what is new when run again, renaming a position whose name is taken", () => {
            cy.makePrivateAdminAPICall("POST", "/api/ministries/ministries", { name: HOSPITALITY }, [200, 201]).then((resp) => {
                const hospitality = resp.body.ministry ?? resp.body;
                cy.makePrivateAdminAPICall("GET", `/api/ministries/ministries/${hospitality.id}/teams`, null, 200).then((teams) => {
                    const team = teams.body.teams[0];
                    cy.makePrivateAdminAPICall("POST", `/api/ministries/ministries/${hospitality.id}/positions`, { teamId: team.id, name: KITCHEN }, [200, 201]);
                });
                createOpportunity(KITCHEN);

                cy.makePrivateAdminAPICall("GET", `${CONVERSION_API}/preview?ministryId=${hospitality.id}`, null, 200).then((preview) => {
                    expect(preview.body.totals).to.deep.include({ positions: 1, converted: 3 });
                    const kitchen = preview.body.opportunities.find((opportunity) => opportunity.name === KITCHEN);
                    expect(kitchen).to.deep.include({ newName: `${KITCHEN} (2)`, renamed: true });
                });
                cy.makePrivateAdminAPICall("POST", CONVERSION_API, { ministryId: hospitality.id }, 200)
                    .its("body.totals")
                    .should("deep.include", { positions: 1, converted: 3 });
                cy.makePrivateAdminAPICall("POST", CONVERSION_API, {}, 200)
                    .its("body.totals")
                    .should("deep.include", { positions: 0, ministries: 0, converted: 4 });
            });
        });

        it("hides Volunteer Opportunities again on V2 once everything is converted", () => {
            setVersion("v2");
            freshAdminLogin();
            cy.visit("/admin/");
            cy.get("#volunteer-v1-notice").should("not.exist");
            cy.visit("/VolunteerOpportunityEditor.php");
            cy.location("pathname").should("eq", "/ministries/dashboard");
        });

        it("refuses a new ministry name that is already taken", () => {
            cy.makePrivateAdminAPICall("GET", `${CONVERSION_API}/preview?ministryName=${encodeURIComponent(TARGET)}`, null, 400)
                .its("body.message")
                .should("contain", "already exists");
        });
    });
});
