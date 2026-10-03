/// <reference types="cypress" />

/**
 * People Settings hub (/admin/people) — replaces the collapsible settings
 * panel that used to live on the People Dashboard (#9994, refs #8453).
 *
 * Each section is its own settings panel. Booleans render as Yes/No radio pills
 * (value 1 / 0); fields stay disabled until every value has loaded from
 * GET /admin/api/system/config/{name}. The save test flips bHideFriendDate and
 * after() restores the value captured in before().
 */

describe("People Settings hub", () => {
    const SECTIONS = ["#peopleNewMembers", "#peoplePeople", "#peopleFamilies", "#peopleMap"];
    let savedProviders;
    let savedFriendDate;

    before(() => {
        cy.getSystemConfig("bHideFriendDate").then((value) => {
            savedFriendDate = value;
        });
        cy.getSystemConfig("sGeocoderProviders").then((value) => {
            savedProviders = value;
        });
    });

    after(() => {
        cy.restoreSystemConfig("bHideFriendDate", savedFriendDate);
        cy.restoreSystemConfig("sGeocoderProviders", savedProviders);
    });

    describe("as admin", () => {
        beforeEach(() => {
            cy.setupAdminSession();
        });

        it("links to the hub from the People Dashboard instead of expanding a panel", () => {
            cy.visit("/people/dashboard");
            cy.get(".page-header .btn-list a").should("have.length", 1).and("contain", "People Settings");
            cy.get(".page-header .btn-list a").should("have.attr", "href").and("include", "/admin/people");
            cy.get("#peopleSettings").should("not.exist");
            cy.get(".page-header .btn-list a").click();
            cy.url().should("include", "/admin/people");
        });

        it("lists People Settings in the People > Admin menu", () => {
            cy.visit("/admin/people");
            cy.get(".page-title").should("have.length", 1).and("contain", "People Settings");
            cy.get('a[href$="admin/people"]').should("exist");
        });

        it("links to the lists and editors that used to live only in the menu", () => {
            cy.visit("/admin/people");
            [
                ["Person Classifications", "mode=classes"],
                ["Person Properties", "PropertyList.php?Type=p"],
                ["Person Custom Fields", "PersonCustomFieldsEditor.php"],
                ["Family Roles", "mode=famroles"],
                ["Family Properties", "PropertyList.php?Type=f"],
                ["Family Custom Fields", "FamilyCustomFieldsEditor.php"],
                ["Volunteer Opportunities", "VolunteerOpportunityEditor.php"],
            ].forEach(([label, href]) => {
                cy.get(".container-fluid a.card")
                    .contains(label)
                    .closest("a")
                    .should("have.attr", "href")
                    .and("include", href);
            });
        });

        it("renders a settings panel for every section and enables it once values load", () => {
            cy.visit("/admin/people");
            SECTIONS.forEach((section) => {
                cy.get(`${section} .settings-panel-fields`, { timeout: 10000 }).should("not.be.disabled");
            });
        });

        it("does not link back to the System Settings page from the panels", () => {
            cy.visit("/admin/people");
            cy.get("#peoplePeople .settings-panel-fields", { timeout: 10000 }).should("not.be.disabled");
            cy.get('.settings-panel-card a[href*="SystemSettings.php"]').should("not.exist");
        });

        it("shows the moved settings, including Self-Registration, but not Birthday Emails (Feature Toggles)", () => {
            cy.visit("/admin/people");
            cy.get("#peopleNewMembers [name='sNewPersonNotificationRecipientIDs']").should("exist");
            cy.get("#peopleNewMembers textarea[name='sGreeterCustomMsg1'][maxlength='255']").should("exist");
            cy.get("#peopleNewMembers textarea[name='sGreeterCustomMsg2'][maxlength='255']").should("exist");
            cy.get("#peoplePeople [name='bHidePersonAddress']").should("exist");
            cy.get("#peoplePeople [name='bHideDeceasedFromDirectory']").should("exist");
            cy.get("#peopleFamilies [name='bHideFamilyNewsletter']").should("exist");
            cy.get("#peopleNewMembers input[name='bEnableSelfRegistration']").should("exist");
            cy.get("[name='bEnableBirthdayEmails']").should("not.exist");
        });

        it("no longer lists the moved settings on the System Settings page", () => {
            cy.visit("/SystemSettings.php");
            [
                "sNewPersonNotificationRecipientIDs",
                "sGreeterCustomMsg1",
                "iPersonNameStyle",
                "bHidePersonAddress",
                "bHideFriendDate",
                "bHideWeddingDate",
                "bForceUppercaseZip",
                "sDirRoleHead",
                "sDirClassifications",
                "sInactiveClassification",
                "bHideFamilyNewsletter",
            ].forEach((key) => {
                cy.get(`[name='new_value[${key}]']`).should("not.exist");
            });
            // Not moved yet — still managed here.
            cy.get("[name='new_value[iPersonConfessionFatherCustomField]']").should("exist");
        });

        it("labels settings with short names and keeps the long sentence as help text", () => {
            cy.visit("/admin/people");
            cy.get("#peoplePeople .settings-panel-fields", { timeout: 10000 }).should("not.be.disabled");
            cy.get("#peoplePeople").should("contain.text", "Hide Friend Date");
            cy.get("#peoplePeople .form-label").filter(":contains('Set true to disable')").should("not.exist");
            // Bootstrap moves title to data-bs-original-title once the tooltip is initialised.
            cy.get(
                "#peoplePeople [title*='Set true to disable entering Friend Date'], #peoplePeople [data-bs-original-title*='Set true to disable entering Friend Date']",
            ).should("exist");
        });

        it("has a Map Settings section instead of a panel on the map page", () => {
            cy.visit("/admin/people");
            cy.get("#peopleMap .settings-panel-fields", { timeout: 10000 }).should("not.be.disabled");
            cy.get("#peopleMap select[name='iMapZoom'] option").should("have.length.at.least", 5);
            cy.get("#peopleMap input[name='bHideLatLon']").should("exist");
            cy.get("#peopleMap select[name='sGeocoderProviders']").should("exist");
            // Hide Person Address lives under People, not twice
            cy.get("[name='bHidePersonAddress']").should("have.length", 2); // Yes/No pills of one setting
            cy.get("#peopleMap [name='bHidePersonAddress']").should("not.exist");

            cy.visit("/people/map");
            cy.get("#mapAdminSettings").should("not.exist");
            cy.get(".page-header .btn-list a[href$='/admin/people#peopleMap']").should("contain", "Map Settings");
        });

        it("picks geocoding services in order and auto-saves the ranking", () => {
            cy.intercept("POST", "**/admin/api/system/config/sGeocoderProviders").as("saveProviders");
            cy.visit("/admin/people");
            cy.get("#peopleMap .settings-panel-fields", { timeout: 10000 }).should("not.be.disabled");

            // Nominatim is the default; US Census is opt-in
            cy.get("#peopleMap .ts-control .item").should("have.length", 1).and("contain", "Nominatim");
            cy.get("#peopleMap .ts-control").click();
            cy.get("#peopleMap .ts-dropdown .option").contains("US Census").click({ force: true });

            cy.wait("@saveProviders").then((interception) => {
                expect(interception.response.statusCode).to.eq(200);
                expect(interception.request.body.value).to.eq("Nominatim,US Census");
            });
        });

        it("renders choice settings as selects with their options", () => {
            cy.visit("/admin/people");
            cy.get("#peoplePeople select[name='iPersonNameStyle'] option").should("have.length", 9);
            cy.get("#peoplePeople select[name='iPersonInitialStyle'] option").should("have.length", 2);
            cy.get("#peopleFamilies select[name='sDirRoleHead'] option").should("have.length.greaterThan", 0);
        });

        it("picks notification recipients by name and auto-saves their IDs", () => {
            cy.getSystemConfig("sNewPersonNotificationRecipientIDs").then((original) => {
                cy.intercept("POST", "**/admin/api/system/config/sNewPersonNotificationRecipientIDs").as("saveRecipients");
                cy.visit("/admin/people");
                cy.get("#peopleNewMembers .settings-panel-fields", { timeout: 10000 }).should("not.be.disabled");
                cy.get("#peopleNewMembers .ts-wrapper").should("exist");

                cy.get("#peopleNewMembers .ts-control input").type("Church", { force: true });
                cy.get(".ts-dropdown .option", { timeout: 10000 }).first().click({ force: true });

                cy.wait("@saveRecipients").then((interception) => {
                    expect(interception.response.statusCode).to.eq(200);
                    expect(String(interception.request.body.value)).to.match(/^\d+(,\d+)*$/);
                });

                cy.restoreSystemConfig("sNewPersonNotificationRecipientIDs", original);
            });
        });

        it("auto-saves a changed setting without a Save button", () => {
            cy.intercept("POST", "**/admin/api/system/config/bHideFriendDate").as("saveConfig");
            cy.visit("/admin/people");
            cy.get("#peoplePeople .settings-panel-fields", { timeout: 10000 }).should("not.be.disabled");
            cy.get(".settings-panel-save").should("not.exist");

            const yesRadio = "#peoplePeople input[name='bHideFriendDate'][value='1']";
            const noRadio = "#peoplePeople input[name='bHideFriendDate'][value='0']";

            cy.get(yesRadio).then(($yes) => {
                const currentlyOn = $yes.is(":checked");
                cy.get(currentlyOn ? noRadio : yesRadio).click({ force: true });

                cy.wait("@saveConfig").then((interception) => {
                    expect(interception.response.statusCode).to.eq(200);
                    expect(String(interception.request.body.value)).to.eq(currentlyOn ? "0" : "1");
                });
            });
        });
    });

    describe("as a non-admin", () => {
        it("does not offer the hub", () => {
            cy.setupStandardSession();
            cy.visit("/admin/people", { failOnStatusCode: false });
            cy.get("#peoplePeople").should("not.exist");
            cy.get(".page-title").should("not.contain", "People Settings");
        });
    });
});
