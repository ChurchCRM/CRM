/// <reference types="cypress" />

/**
 * #10376: a spouse proposed in the Member Portal keeps that role and waits for review, and
 * every reader of `Family::getAdults()` leaves them out until staff approve them. A family
 * that self-registered as a whole is pending too, and keeps its adults.
 *
 * Family 20 (Black): Amanda (head), Lena (spouse, signed in to the portal with her email), Samantha and Serenity.
 */
const FAMILY_ID = 20;
const LENA_ID = 100;
const SAMANTHA_ID = 102;
const SPOUSE_ROLE = 2;
const STUDENT_ROLE = 2;
const ANNIVERSARIES_CALENDAR = 1;
const FAMILY_ADULT_CAPABILITIES = ["portal.family.contacts.view", "portal.family.edit", "portal.family.confirm"];

const run = String(Date.now()).slice(-6);
const spouseFirstName = `Pat${run}`;
const spouseName = `${spouseFirstName} Black`;
const spouseEmail = `pat.${run}@example.com`;
const className = `Proposed member ${run}`;
const pendingFamily = {
    name: `Pending${run}`,
    head: `Hal${run}`,
    spouse: `Sue${run}`,
    child: `Kid${run}`,
};

const admin = (method, url, body = null, status = 200) => cy.makePrivateAdminAPICall(method, url, body, status);

const memberLogin = () => cy.useMemberSession(LENA_ID);

const memberPost = (url, body) => {
    cy.request("/portal/profile/edit").then((page) => {
        const token = page.body.match(/name="csrf_token"\s+value="([a-f0-9]+)"/i)[1];
        cy.request({
            method: "POST",
            url,
            body,
            headers: { "content-type": "application/json", "X-CSRF-Token": token },
        });
    });
};

let kioskCookie;
const kiosk = (method, url, body) => {
    cy.setCookie("kioskCookie", kioskCookie.value, { path: kioskCookie.path });
    return cy.request({ method, url, body });
};

const parentLine = (firstName) =>
    admin("GET", "/api/groups/sundayschool/export/classlist").then((response) =>
        response.body.split(/\r?\n/).find((line) => line.includes(className) && line.includes(firstName)),
    );

const parentEmails = (groupId) =>
    admin("GET", `/api/groups/${groupId}/sundayschool/emails`).then((response) =>
        response.body.recipients.map((recipient) => recipient.email),
    );

// The person card's portal permissions, as an administrator sees them.
const portalPermission = (personId, capability) => {
    cy.setupAdminSession();
    cy.visit(`/people/view/${personId}`);
    cy.get("#portal-signin-permissions").should("exist");
    return cy.get("body").then(($body) => $body.find(`#portal-signin-permissions [data-capability="${capability}"]`).length);
};

const emailLogTotal = (personId) =>
    admin("GET", `/api/email/log?personId=${personId}`).then((response) => response.body.total);

describe("A family member proposed in the Member Portal", () => {
    let spouseId;
    let groupId;
    let eventId;
    let pendingFamilyId;
    let originalWeddingDate;
    let originalPortalCalendars;
    let originalKioskVisibility;
    const existingKioskIds = new Set();

    before(() => {
        cy.rememberTestEnv(["admin.username", "admin.password"]);

        admin("POST", "/api/groups/", { groupName: className, isSundaySchool: true }).then((response) => {
            groupId = response.body.Id;
            admin("POST", `/api/groups/${groupId}/addperson/${SAMANTHA_ID}`, { RoleID: STUDENT_ROLE });
            admin("POST", "/api/events/quick-create", { groupId }).then((event) => {
                eventId = event.body.eventId;
            });
        });

        admin("GET", "/admin/api/member-portal/calendars").then((response) => {
            originalPortalCalendars = response.body.calendars
                .filter((calendar) => calendar.visible)
                .map(({ type, id }) => ({ type, id }));
        });
        admin("POST", "/admin/api/member-portal/calendars", {
            visible: [{ type: "system", id: ANNIVERSARIES_CALENDAR }],
        });

        cy.request({
            method: "POST",
            url: "/api/public/register/family",
            body: {
                Name: pendingFamily.name,
                Address1: "1 Review St",
                City: "Testville",
                State: "TS",
                Country: "US",
                Zip: "12345",
                people: [
                    { firstName: pendingFamily.head, lastName: pendingFamily.name, gender: 1, role: 1 },
                    { firstName: pendingFamily.spouse, lastName: pendingFamily.name, gender: 2, role: 2 },
                    { firstName: pendingFamily.child, lastName: pendingFamily.name, gender: 1, role: 3 },
                ],
            },
        }).then((response) => {
            pendingFamilyId = response.body.Id;
        });
        cy.then(() => {
            admin("GET", `/api/persons/search/${pendingFamily.child}`).then((response) => {
                admin("POST", `/api/groups/${groupId}/addperson/${response.body[0].objid}`, { RoleID: STUDENT_ROLE });
            });
        });

        memberLogin();
        cy.request("/api/portal/family").then((response) => {
            originalWeddingDate = response.body.family.weddingDate;
        });
        memberPost("/api/portal/family", { weddingDate: "1998-04-03" });
        memberPost("/api/portal/family/members", { firstName: spouseFirstName, lastName: "Black", role: SPOUSE_ROLE });
        cy.request("/api/portal/family").then((response) => {
            spouseId = response.body.members.find((member) => member.fullName === spouseName).id;
        });

        cy.freshAdminFormLogin();
        cy.then(() => cy.visit(`/PersonEditor.php?PersonID=${spouseId}`));
        cy.get("#Gender").select("1");
        cy.get("#Email").clear().type(spouseEmail);
        cy.get("button[name='PersonSubmit']").click();
        cy.url().should("include", "/people/view/");

        cy.getSystemConfig("sKioskVisibilityTimestamp").then((value) => {
            originalKioskVisibility = value;
        });
        admin("GET", "/kiosk/api/devices").then((response) => {
            (response.body.KioskDevices || []).forEach((device) => existingKioskIds.add(device.Id));
        });
        admin("POST", "/kiosk/api/allowRegistration");
        cy.clearCookies();
        cy.visit("/kiosk/", { failOnStatusCode: false });
        cy.getCookie("kioskCookie").then((cookie) => {
            kioskCookie = cookie;
        });
        cy.then(() => cy.restoreSystemConfig("sKioskVisibilityTimestamp", originalKioskVisibility));
        admin("GET", "/kiosk/api/devices").then((response) => {
            const device = response.body.KioskDevices.find((candidate) => !existingKioskIds.has(candidate.Id));
            admin("POST", `/kiosk/api/devices/${device.Id}/accept`);
            admin("POST", `/kiosk/api/devices/${device.Id}/assignment`, { assignmentType: 1, eventId });
        });
    });

    after(() => {
        admin("GET", "/kiosk/api/devices").then((response) => {
            (response.body.KioskDevices || [])
                .filter((device) => !existingKioskIds.has(device.Id))
                .forEach((device) => admin("DELETE", `/kiosk/api/devices/${device.Id}`));
        });
        cy.cleanupEvents([eventId]);
        cy.cleanupPeople([spouseId]);
        cy.cleanupFamilies([pendingFamilyId]);
        cy.then(() => admin("DELETE", `/api/groups/${groupId}`));
        cy.then(() => admin("POST", "/admin/api/member-portal/calendars", { visible: originalPortalCalendars }));
        memberLogin();
        cy.then(() => memberPost("/api/portal/family", { weddingDate: originalWeddingDate }));
    });

    describe("while it waits for review", () => {
        it("keeps the Spouse role but is not one of the family's adults in the portal", () => {
            memberLogin();
            cy.request("/api/portal/family").then((response) => {
                const spouse = response.body.members.find((member) => member.id === spouseId);
                expect(spouse.role).to.eq("Spouse");
                expect(spouse.awaitingReview).to.eq(true);
                expect(spouse.isAdult).to.eq(false);
            });
        });

        it("holds none of the family adult's portal permissions", () => {
            FAMILY_ADULT_CAPABILITIES.forEach((capability) => {
                portalPermission(spouseId, capability).should("eq", 0);
            });
        });

        it("leaves the family salutation on the staff anniversary calendar alone", () => {
            admin("GET", `/api/systemcalendars/${ANNIVERSARIES_CALENDAR}/events/${FAMILY_ID}`).then((response) => {
                expect(response.body[0].Title).to.eq("Anniversary: Amanda & Lena Black");
            });
        });

        it("leaves the portal anniversary label alone", () => {
            memberLogin();
            cy.request("/api/portal/calendar/events?from=2030-04-03&to=2030-04-03").then((response) => {
                const anniversary = response.body.events.find((event) => event.id === `system-1-${FAMILY_ID}`);
                expect(anniversary.title).to.eq("Anniversary: Amanda & Lena B.");
            });
        });

        it("is not offered as a kiosk pickup adult", () => {
            kiosk("GET", `/kiosk/device/activeClassMember/${SAMANTHA_ID}/family`).then((response) => {
                const ids = response.body.members.map((member) => Number(member.Id));
                expect(ids).to.include(LENA_ID);
                expect(ids).to.not.include(spouseId);
            });
        });

        it("gets no kiosk pickup notification", () => {
            emailLogTotal(LENA_ID).then((lenaBefore) => {
                emailLogTotal(spouseId).then((spouseBefore) => {
                    kiosk("POST", "/kiosk/device/triggerNotification", { PersonId: SAMANTHA_ID });
                    emailLogTotal(LENA_ID).should("eq", lenaBefore + 1);
                    emailLogTotal(spouseId).should("eq", spouseBefore);
                });
            });
        });

        it("is not a parent on the Sunday school class list", () => {
            parentLine("Samantha").should("not.include", spouseName);
        });

        it("is not emailed as a Sunday school parent", () => {
            parentEmails(groupId).should("not.include", spouseEmail);
        });

        it("does not hide the parents of a family that is itself waiting for review", () => {
            parentLine(pendingFamily.child)
                .should("include", `${pendingFamily.head} ${pendingFamily.name}`)
                .and("include", `${pendingFamily.spouse} ${pendingFamily.name}`);
        });
    });

    describe("once staff approve them", () => {
        before(() => {
            admin("POST", `/api/person/${spouseId}/approve-review`);
        });

        it("is one of the family's adults in the portal", () => {
            memberLogin();
            cy.request("/api/portal/family").then((response) => {
                const spouse = response.body.members.find((member) => member.id === spouseId);
                expect(spouse.awaitingReview).to.eq(false);
                expect(spouse.isAdult).to.eq(true);
            });
        });

        it("holds the family adult's portal permissions", () => {
            FAMILY_ADULT_CAPABILITIES.forEach((capability) => {
                portalPermission(spouseId, capability).should("eq", 1);
            });
        });

        it("counts in the family salutation and the portal anniversary label", () => {
            admin("GET", `/api/systemcalendars/${ANNIVERSARIES_CALENDAR}/events/${FAMILY_ID}`).then((response) => {
                expect(response.body[0].Title).to.eq("Anniversary: Black Family");
            });
            memberLogin();
            cy.request("/api/portal/calendar/events?from=2030-04-03&to=2030-04-03").then((response) => {
                const anniversary = response.body.events.find((event) => event.id === `system-1-${FAMILY_ID}`);
                expect(anniversary.title).to.eq(`Anniversary: Amanda & Lena & ${spouseFirstName} B.`);
            });
        });

        it("is offered as a kiosk pickup adult and gets the pickup notification", () => {
            kiosk("GET", `/kiosk/device/activeClassMember/${SAMANTHA_ID}/family`).then((response) => {
                expect(response.body.members.map((member) => Number(member.Id))).to.include(spouseId);
            });

            emailLogTotal(spouseId).then((before) => {
                kiosk("POST", "/kiosk/device/triggerNotification", { PersonId: SAMANTHA_ID });
                emailLogTotal(spouseId).should("eq", before + 1);
            });
        });

        it("is a Sunday school parent", () => {
            parentLine("Samantha").should("include", spouseName);
            parentEmails(groupId).should("include", spouseEmail);
        });
    });
});
