/// <reference types="cypress" />

import {
    askForEmail,
    auditSince,
    db,
    lastAuditId,
    resetEmailSignIn,
    restoreSettings,
    setConfig,
    signInEmail,
    useMemberSignIn,
} from "../../../support/member-sign-in";

/**
 * Member sign-in row 7 (#10463): remembered devices and the Sign-in and devices
 * page (docs/member-sign-in-design.md §6.2, §5, §11.1, §14, §17 "Remembered devices").
 * Seed person 57, Jon Olson: head of household, Member, born 1981. Person 58,
 * Sandra Olson, is his spouse.
 */

const member = { personId: 57, firstName: "Jon", email: "jon.olson@example.com" };
const spouse = { personId: 58, email: "sandra.vasquez@example.com" };
const DEVICE_COOKIE = "CRM-portal-device";
const isSessionCookie = (cookie) => /^CRM-[0-9a-f]{32}$/.test(cookie.name);

const signInWithCode = ({ remember }) => {
    cy.task("mail:clear");
    askForEmail(member.email);
    signInEmail(member.email).then(({ code }) => {
        cy.visit("/portal/login/code");
        cy.get("#portalRememberDevice").should("not.be.checked");
        if (remember) {
            cy.get("#portalRememberDevice").check();
        }
        cy.get("#portalSignInCode").type(code);
        cy.get("#portalSignInCodeSubmit").click();
        cy.location("pathname").should("match", /\/portal\/$/);
    });
};

const saveCookies = () => cy.getCookies();

const useCookies = (cookies) => {
    cy.clearCookies();
    cookies.forEach((cookie) =>
        cy.setCookie(cookie.name, cookie.value, {
            path: cookie.path,
            httpOnly: cookie.httpOnly,
            sameSite: cookie.sameSite,
            ...(cookie.expiry ? { expiry: cookie.expiry } : {}),
        }),
    );
};

const closeBrowser = () =>
    cy.getCookies().then((cookies) => cookies.filter(isSessionCookie).forEach((cookie) => cy.clearCookie(cookie.name)));

const expectSignedIn = () =>
    cy.request({ url: "/portal/", followRedirect: false }).then((response) => {
        expect(response.status, "the portal opens").to.eq(200);
        expect(response.body).to.include(`Hello ${member.firstName}`);
    });

const expectSignedOut = () =>
    cy.request({ url: "/portal/", followRedirect: false }).then((response) => {
        expect(response.status).to.eq(302);
        expect(response.redirectedToUrl).to.match(/\/portal\/login$/);
    });

const devicesOf = (personId) =>
    db("SELECT * FROM person_device_pdv WHERE pdv_per_ID = ? ORDER BY pdv_ID", [personId]);

const events = (rows) => rows.map((row) => `${row.psa_Event}:${row.psa_Method ?? ""}:${row.psa_Reason ?? ""}`);

const adminPost = (url, body) => cy.makePrivateAdminAPICall("POST", url, body, 200);

describe("Member Portal remembered devices", () => {
    const saved = {};

    before(() => {
        cy.task("mail:available").its("available").should("eq", true);
        db("SELECT cfg_value FROM config_cfg WHERE cfg_name = 'iPortalIdleMinutes'").then((rows) => {
            saved.iPortalIdleMinutes = rows.length > 0 ? rows[0].cfg_value : null;
        });
        useMemberSignIn(saved, { iRememberDeviceDays: "30" });
    });

    beforeEach(() => {
        cy.clearCookies();
        resetEmailSignIn([member.personId, spouse.personId]);
        db("DELETE FROM person_device_pdv WHERE pdv_per_ID IN (?, ?)", [member.personId, spouse.personId]);
    });

    afterEach(() => {
        setConfig("iRememberDeviceDays", "30");
        setConfig("sMemberSignInClassifications", "1,2");
        if (saved.iPortalIdleMinutes === null) {
            db("DELETE FROM config_cfg WHERE cfg_name = 'iPortalIdleMinutes'");
        } else {
            setConfig("iPortalIdleMinutes", saved.iPortalIdleMinutes);
        }
        db("UPDATE person_signin_psi SET psi_SignInDisabled = 0 WHERE psi_per_ID = ?", [member.personId]);
    });

    after(() => {
        restoreSettings(saved);
    });

    it("keeps nothing on the device without the box: closing the browser ends the session", () => {
        signInWithCode({ remember: false });

        cy.getCookie(DEVICE_COOKIE).should("be.null");
        cy.getCookies().then((cookies) => {
            const session = cookies.find(isSessionCookie);
            expect(session, "PHP session cookie").to.exist;
            expect(session.expiry, "a browser-session cookie").to.be.undefined;
        });
        devicesOf(member.personId).should("have.length", 0);

        closeBrowser();
        expectSignedOut();
    });

    it("sets a long-lived device cookie when the box is ticked, and stores only its hash", () => {
        lastAuditId().then((startId) => {
            signInWithCode({ remember: true });

            cy.getCookie(DEVICE_COOKIE).then((cookie) => {
                expect(cookie.httpOnly).to.eq(true);
                expect(cookie.sameSite).to.eq("lax");
                expect(cookie.path).to.eq("/portal");
                const days = (cookie.expiry - Date.now() / 1000) / 86400;
                expect(days).to.be.within(29.9, 30.1);

                devicesOf(member.personId).then((rows) => {
                    expect(rows).to.have.length(1);
                    expect(rows[0].pdv_Kind).to.eq("browser");
                    expect(rows[0].pdv_Label).to.match(/^\w[\w ]* · \w+$/);
                    expect(rows[0].pdv_RevokedAt).to.be.null;
                    expect(JSON.stringify(rows)).to.not.include(cookie.value);
                });
                auditSince(startId).then((rows) => {
                    expect(events(rows)).to.include("device_added:email_code:");
                    expect(JSON.stringify(rows)).to.not.include(cookie.value);
                });
            });
        });
    });

    it("brings the session back after the browser closes, and replaces the token at each use", () => {
        signInWithCode({ remember: true });
        cy.getCookie(DEVICE_COOKIE).then((first) => {
            lastAuditId().then((startId) => {
                closeBrowser();
                cy.visit("/portal/profile");
                cy.location("pathname").should("match", /\/portal\/profile$/);
                cy.get("#portal-account-toggle").should("contain.text", `Hello ${member.firstName}`);

                cy.getCookie(DEVICE_COOKIE).its("value").should("not.eq", first.value);
                devicesOf(member.personId).then(([device]) => {
                    expect(device.pdv_PreviousTokenHash, "the replaced token's hash").to.match(/^[0-9a-f]{64}$/);
                    expect(device.pdv_PreviousValidUntil).to.not.be.null;
                    expect(JSON.stringify(device)).to.not.include(first.value);
                });
                auditSince(startId).then((rows) => {
                    expect(events(rows)).to.deep.equal(["signed_in:device:"]);
                });
                db("SELECT psi_LastMethod FROM person_signin_psi WHERE psi_per_ID = ?", [member.personId])
                    .its("0.psi_LastMethod")
                    .should("eq", "device");

                cy.endDeviceTokenGrace(member.personId);
                cy.clearCookies();
                cy.setCookie(DEVICE_COOKIE, first.value, { path: "/portal", httpOnly: true });
                expectSignedOut();
                cy.getCookie(DEVICE_COOKIE).should("be.null");
            });
        });
    });

    it("ends an idle session without the box, and starts a new one from the device with it", () => {
        signInWithCode({ remember: false });
        saveCookies().then((withoutBox) => {
            cy.clearCookies();
            resetEmailSignIn([member.personId]);
            signInWithCode({ remember: true });
            lastAuditId().then((startId) => {
                setConfig("iPortalIdleMinutes", "1");
                cy.wait(61500);

                expectSignedIn();
                auditSince(startId).then((rows) => {
                    expect(events(rows)).to.deep.equal(["session_ended:email_code:idle", "signed_in:device:"]);
                });

                useCookies(withoutBox);
                expectSignedOut();
            });
        });
    });

    it("sends a remembered device from /portal/login straight into the portal", () => {
        signInWithCode({ remember: true });
        closeBrowser();
        cy.visit("/portal/login");
        cy.location("pathname").should("match", /\/portal\/$/);
        cy.get("#portal-account-toggle").should("contain.text", `Hello ${member.firstName}`);
    });

    it("lists the devices on Sign-in and devices, and signing one out stops it and its session", () => {
        signInWithCode({ remember: true });
        saveCookies().then((otherDevice) => {
            cy.clearCookies();
            resetEmailSignIn([member.personId]);
            signInWithCode({ remember: true });

            cy.get("#portal-account-toggle").click();
            cy.get("#portalAccountDevices").click();
            cy.location("pathname").should("match", /\/portal\/devices$/);
            cy.get("#portal-devices-table [data-device-row]").should("have.length", 2);
            cy.get("[data-this-device]").should("have.length", 1);
            cy.get("[data-device-label]").first().invoke("text").should("match", /^\w[\w ]* · \w+$/);

            lastAuditId().then((startId) => {
                cy.get("#portal-devices-table [data-device-row]")
                    .filter(":not(:has([data-this-device]))")
                    .find("[data-device-sign-out]")
                    .click();
                cy.get(".portal-flash").should("contain.text", "That device is signed out.");
                cy.get("#portal-devices-table [data-device-row]").should("have.length", 1).find("[data-this-device]");

                useCookies(otherDevice);
                expectSignedOut();
                cy.getCookie(DEVICE_COOKIE).should("be.null");
                auditSince(startId).then((rows) => {
                    expect(events(rows)).to.deep.equal([
                        "device_removed::signed_out",
                        "session_ended:email_code:device_removed",
                        "refused:device:device_removed",
                    ]);
                });
            });
        });
    });

    it("ends the session kept on a device an administrator signs out on the person page", () => {
        signInWithCode({ remember: true });
        saveCookies().then((memberBrowser) => {
            devicesOf(member.personId).then(([device]) => {
                lastAuditId().then((startId) => {
                    adminPost(`/admin/api/person/${member.personId}/sign-in/devices/${device.pdv_ID}/remove`);
                    useCookies(memberBrowser);
                    expectSignedOut();
                    cy.getCookie(DEVICE_COOKIE).should("be.null");
                    auditSince(startId).then((rows) => {
                        expect(events(rows)).to.include("device_removed::signed_out");
                        expect(rows.find((row) => row.psa_Event === "device_removed").psa_Actor_usr_ID).to.eq(1);
                    });
                });
            });
        });
    });

    it("signs out this device from the page and lands on the sign-in page", () => {
        signInWithCode({ remember: true });
        closeBrowser();
        cy.visit("/portal/devices");
        cy.get("[data-this-device]").parents("[data-device-row]").find("[data-device-sign-out]").click();
        cy.location("pathname").should("match", /\/portal\/login$/);
        cy.get(".portal-flash").should("contain.text", "You are signed out on this device.");
        cy.getCookie(DEVICE_COOKIE).should("be.null");
        devicesOf(member.personId).then(([device]) => {
            expect(device.pdv_RevokedAt).to.not.be.null;
            expect(device.pdv_PreviousTokenHash, "the replaced token is forgotten too").to.be.null;
            expect(device.pdv_PreviousValidUntil).to.be.null;
        });
    });

    it("Sign out every device ends every session and stops every device", () => {
        signInWithCode({ remember: true });
        saveCookies().then((otherDevice) => {
            cy.clearCookies();
            resetEmailSignIn([member.personId]);
            signInWithCode({ remember: true });

            lastAuditId().then((startId) => {
                cy.visit("/portal/devices");
                cy.get("#portal-devices-everywhere-confirm").should("not.have.attr", "open");
                cy.get("#portal-devices-everywhere-open").click();
                cy.get("#portal-devices-everywhere-confirm").should("have.attr", "open");
                cy.get("#portal-devices-everywhere-submit").click();
                cy.location("pathname").should("match", /\/portal\/login$/);
                cy.get(".portal-flash").should("contain.text", "You are signed out on every device.");
                cy.getCookie(DEVICE_COOKIE).should("be.null");

                devicesOf(member.personId).then((rows) => {
                    expect(rows).to.have.length(2);
                    rows.forEach((row) => expect(row.pdv_RevokedAt).to.not.be.null);
                });
                auditSince(startId).then((rows) => {
                    const ended = rows.find((row) => row.psa_Event === "session_ended");
                    expect(ended.psa_Reason).to.eq("signed_out_everywhere");
                    expect(ended.psa_Actor_usr_ID).to.be.null;
                    expect(ended.psa_Detail).to.eq("devices=2");
                });

                useCookies(otherDevice);
                expectSignedOut();
            });
        });
    });

    it("forgets the device when the member signs out", () => {
        signInWithCode({ remember: true });
        lastAuditId().then((startId) => {
            cy.get("#portal-account-toggle").click();
            cy.get("#portalAccountSignOut").click();
            cy.location("pathname").should("match", /\/portal\/login$/);
            cy.getCookie(DEVICE_COOKIE).should("be.null");
            devicesOf(member.personId).its("0.pdv_RevokedAt").should("not.be.null");
            auditSince(startId).then((rows) => {
                expect(events(rows)).to.include("device_removed::signed_out");
            });
        });
    });

    it("drops a device cookie the browser held when someone signs in here without the box", () => {
        signInWithCode({ remember: true });
        saveCookies().then((memberDevice) => {
            cy.clearCookies();
            cy.task("mail:clear");
            askForEmail(spouse.email);
            signInEmail(spouse.email).then(({ link }) => {
                useCookies(memberDevice);
                lastAuditId().then((startId) => {
                    cy.visit(link);
                    cy.get("#portalRememberDevice").should("not.be.checked");
                    cy.get("#portalSignInConfirmSubmit").click();
                    cy.location("pathname").should("match", /\/portal\/$/);

                    cy.getCookie(DEVICE_COOKIE).should("be.null");
                    devicesOf(member.personId).its("0.pdv_RevokedAt").should("not.be.null");
                    devicesOf(spouse.personId).should("have.length", 0);
                    auditSince(startId).then((rows) => {
                        expect(events(rows)).to.deep.equal(["signed_in:email_link:", "device_removed::replaced"]);
                    });
                });
            });
        });
    });

    it("Disable sign-in removes every device", () => {
        signInWithCode({ remember: true });
        saveCookies().then((memberDevice) => {
            adminPost(`/admin/api/person/${member.personId}/sign-in/disable`, { reason: "Remembered devices spec" });
            devicesOf(member.personId).should("have.length", 0);
            adminPost(`/admin/api/person/${member.personId}/sign-in/enable`, {});

            useCookies(memberDevice);
            closeBrowser();
            expectSignedOut();
            cy.getCookie(DEVICE_COOKIE).should("be.null");
        });
    });

    it("stops existing device cookies and removes the box when the days are set to 0", () => {
        signInWithCode({ remember: true });
        lastAuditId().then((startId) => {
            setConfig("iRememberDeviceDays", "0");
            closeBrowser();
            expectSignedOut();
            cy.getCookie(DEVICE_COOKIE).should("be.null");
            auditSince(startId).then((rows) => {
                expect(events(rows)).to.deep.equal(["refused:device:device_expired"]);
            });

            cy.task("mail:clear");
            askForEmail(member.email);
            cy.visit("/portal/login/code");
            cy.get("#portalSignInCode").should("be.visible");
            cy.get("#portalRememberDevice").should("not.exist");
        });
    });

    it("checks the sign-in rule again on a device sign-in, and signs the device out when refused", () => {
        signInWithCode({ remember: true });
        lastAuditId().then((startId) => {
            setConfig("sMemberSignInClassifications", "2");
            closeBrowser();
            expectSignedOut();
            cy.getCookie(DEVICE_COOKIE).should("be.null");
            devicesOf(member.personId).its("0.pdv_RevokedAt").should("not.be.null");
            auditSince(startId).then((rows) => {
                expect(events(rows)).to.deep.equal(["refused:device:classification", "device_removed::classification"]);
            });
        });
    });
});
