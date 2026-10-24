/// <reference types="cypress" />

// Login as Person (#10463 row 12, docs/member-sign-in-design.md §6.5, §11.3, §17).
const MEMBER = 2; // Mathew Campbell
const INACTIVE = 10; // Tom Hart, made inactive below
const DECEASED = 19; // Laurie Ray
const STAFF = 3; // Tony Campbell, an Edit Records user
const DISABLED = 6; // Constance Hart, Disable sign-in set below
const ADMIN = 1;

const csrfFrom = (html) => {
    const match = html.match(/data-csrf-token="([^"]+)"/);
    expect(match, "CSRF token on the page").to.not.eq(null);
    return match[1];
};

const startLoginAsPerson = (personId, options = {}) =>
    cy.request(`/people/view/${MEMBER}`).then(({ body }) =>
        cy.request({
            method: "POST",
            url: `/admin/api/person/${personId}/login-as-person`,
            headers: { "X-CSRF-Token": csrfFrom(body) },
            body: {},
            failOnStatusCode: false,
            ...options,
        }),
    );

const stopLoginAsPerson = () =>
    cy.request("/portal/").then(({ body }) => {
        const token = body.match(/name="csrf_token" value="([^"]+)"/)[1];
        return cy.request({
            method: "POST",
            url: "/portal/login-as-person/stop",
            form: true,
            body: { csrf_token: token },
            followRedirect: false,
        });
    });

const admin = (method, url, body = null, status = 200) => cy.makePrivateAdminAPICall(method, url, body, status);

const setSetting = (name, value) => admin("POST", `admin/api/system/config/${name}`, { value });

const auditFor = (personId) =>
    admin("GET", `admin/api/member-portal/audit?personId=${personId}&length=100`).then(({ body }) => body.rows);

const newestAuditId = (personId) => auditFor(personId).then((rows) => (rows.length > 0 ? rows[0].id : 0));

const auditSince = (personId, afterId) =>
    auditFor(personId).then((rows) =>
        rows
            .filter((row) => row.id > afterId)
            .reverse()
            .map((row) => ({ event: row.event, method: row.method, reason: row.reason, actorId: row.actorId })),
    );

const newestSession = () => cy.get("#login-as-person-history [data-cy=login-as-person-session]").first();

const expectGrayedOutAndRefused = (personId, reason, stored) => {
    cy.visit(`/people/view/${personId}`);
    cy.get("#person-actions-dropdown").click();
    cy.get("#loginAsPerson").should("have.class", "disabled").and("have.attr", "aria-disabled", "true");
    cy.get("#loginAsPersonRefusal").should("have.attr", "title").and("include", reason);

    newestAuditId(personId).then((afterId) => {
        startLoginAsPerson(personId).then((response) => {
            expect(response.status).to.eq(403);
            expect(response.body.message).to.include(reason);
        });
        auditSince(personId, afterId).should("deep.equal", [
            { event: "refused", method: "assisted", reason: stored, actorId: ADMIN },
        ]);
    });
};

describe("Login as Person", () => {
    const saved = {};

    before(() => {
        ["bAllowLoginAsPerson", "bLoginAsPersonCanWrite", "bMemberSignIn"].forEach((name) => {
            cy.getSystemConfig(name).then((value) => {
                saved[name] = value;
            });
        });
        admin("POST", `admin/api/person/${DISABLED}/sign-in/disable`, { reason: "Login as Person spec" }, [200, 409]);
    });

    beforeEach(() => {
        setSetting("bAllowLoginAsPerson", "1");
        setSetting("bLoginAsPersonCanWrite", "0");
        setSetting("bMemberSignIn", "1");
        cy.freshAdminFormLogin();
    });

    after(() => {
        Object.entries(saved).forEach(([name, value]) => cy.restoreSystemConfig(name, value));
        admin("POST", `admin/api/person/${DISABLED}/sign-in/enable`, null, [200, 409]);
        admin("POST", `api/person/${INACTIVE}/activate/true`);
    });

    it("hides the item and answers 404 while the setting is off", () => {
        setSetting("bAllowLoginAsPerson", "0");
        cy.visit(`/people/view/${MEMBER}`);
        cy.get("#person-actions-dropdown").click();
        cy.get("#deletePersonBtn").should("be.visible");
        cy.get("#loginAsPerson").should("not.exist");
        startLoginAsPerson(MEMBER).its("status").should("eq", 404);
    });

    it("grays the item out with the reason for deceased, Disable sign-in and staff accounts", () => {
        const cases = [
            [DECEASED, "This person is deceased.", "deceased"],
            [DISABLED, "Sign-in is disabled for this person.", "sign_in_disabled"],
            [STAFF, "This person has a staff account.", "staff_account"],
            [ADMIN, "This person has a staff account.", "staff_account"],
        ];
        cases.forEach(([personId, reason, stored]) => expectGrayedOutAndRefused(personId, reason, stored));
    });

    it("grays the item out and refuses the start while member sign-in is off", () => {
        setSetting("bMemberSignIn", "0");
        expectGrayedOutAndRefused(MEMBER, "Member sign-in is off.", "member_sign_in_off");
    });

    it("leaves the item on for an inactive person", () => {
        admin("POST", `api/person/${INACTIVE}/activate/false`);
        cy.visit(`/people/view/${INACTIVE}`);
        cy.get("#person-actions-dropdown").click();
        cy.get("#loginAsPerson").should("be.visible").and("not.have.class", "disabled");
        cy.get("#loginAsPersonRefusal").should("not.exist");
    });

    it("starts from the Actions menu, shows the banner, and Stop returns to the person page", () => {
        cy.visit(`/people/view/${MEMBER}`);
        cy.get("#person-actions-dropdown").click();
        cy.get("#loginAsPerson").should("have.text", "Login as Mathew").click();
        cy.get(".bootbox .modal-title").should("have.text", "Login as Mathew");
        cy.get(".bootbox .btn-warning").click();

        cy.location("pathname").should("eq", "/portal/");
        cy.get("#login-as-person-banner")
            .should("contain", "logged in as Mathew Campbell in the Member Portal (read-only).");
        cy.get("#portal-account-toggle").click();
        cy.get("#portalAccountAdminConsole").should("not.exist");
        cy.get("#portalAccountChangePassword").should("not.exist");

        cy.get("#login-as-person-stop").click();
        cy.location("pathname").should("eq", `/people/view/${MEMBER}`);
        newestSession()
            .should("contain", "Church Admin as Mathew Campbell")
            .and("contain", "Stopped")
            .and("contain", "No changes made.");

        cy.visit(`/v2/user/${ADMIN}`);
        newestSession().should("contain", "Church Admin as Mathew Campbell").and("contain", "Stopped");
    });

    it("keeps staff pages closed until Stop", () => {
        startLoginAsPerson(MEMBER).its("status").should("eq", 200);

        cy.visit("/v2/dashboard");
        cy.location("pathname").should("eq", "/portal/");
        cy.get("#portal-toasts").should("contain", "Staff pages stay closed during Login as Person.");
        cy.get("#login-as-person-banner").should("be.visible");

        cy.visit(`/PersonEditor.php?PersonID=${MEMBER}`);
        cy.location("pathname").should("eq", "/portal/");
        cy.visit("/admin/");
        cy.location("pathname").should("eq", "/portal/");
        cy.request({ url: `/api/person/${MEMBER}`, failOnStatusCode: false }).then((response) => {
            expect(response.status).to.eq(403);
            expect(response.body.message).to.include("Staff pages stay closed");
        });
        cy.request("/portal/").then(({ body }) => {
            cy.request({
                method: "POST",
                url: `/admin/api/person/${INACTIVE}/login-as-person`,
                headers: { "X-CSRF-Token": body.match(/name="csrf_token" value="([^"]+)"/)[1] },
                body: {},
                failOnStatusCode: false,
            })
                .its("status")
                .should("eq", 403);
        });

        cy.request({ method: "POST", url: "/portal/login-as-person/stop", form: true, body: {}, failOnStatusCode: false })
            .its("status")
            .should("eq", 403);
        stopLoginAsPerson().then((response) => {
            expect(response.status).to.eq(303);
            expect(response.headers.location).to.match(new RegExp(`/people/view/${MEMBER}$`));
        });
        cy.visit("/v2/dashboard");
        cy.location("pathname").should("eq", "/v2/dashboard");
    });

    it("leaves the member's activity, sign-in record and statistics alone", () => {
        const signInRecord = () =>
            admin("GET", `api/person/${MEMBER}/sign-in`).then(({ body }) => body.lastSignIn);
        const statistics = () =>
            admin("GET", "admin/api/member-portal/stats").then(({ body }) => ({
                activeNow: body.activeNow,
                signedIn24Hours: body.signedIn24Hours,
                recentSignIns: body.recentSignIns,
            }));

        signInRecord().then((lastSignIn) => {
            statistics().then((stats) => {
                newestAuditId(MEMBER).then((afterId) => {
                    startLoginAsPerson(MEMBER).its("status").should("eq", 200);
                    cy.request("/portal/");
                    cy.request("/portal/profile");
                    cy.request("/api/portal/me");
                    stopLoginAsPerson();

                    signInRecord().should("eq", lastSignIn);
                    statistics().should("deep.equal", stats);
                    auditSince(MEMBER, afterId).should("deep.equal", [
                        { event: "login_as_started", method: "assisted", reason: null, actorId: ADMIN },
                        { event: "login_as_stopped", method: "assisted", reason: null, actorId: ADMIN },
                    ]);
                });
            });
        });
    });

    it("refuses an API key", () => {
        admin("POST", `admin/api/person/${MEMBER}/login-as-person`, {}, 403).its("body.message").should("include", "browser session");
    });

    it("gives a non-administrator no item and refuses the start", () => {
        cy.clearCookies();
        cy.setupStandardSession({ forceLogin: true });
        cy.visit(`/people/view/${MEMBER}`);
        cy.get("#person-actions-dropdown").click();
        cy.get("#loginAsPerson").should("not.exist");
        cy.request(`/people/view/${MEMBER}`).then(({ body }) => {
            const token = (body.match(/data-csrf-token="([^"]+)"/) || [])[1] || "";
            cy.request({
                method: "POST",
                url: `/admin/api/person/${MEMBER}/login-as-person`,
                headers: { "X-CSRF-Token": token },
                body: {},
                failOnStatusCode: false,
            })
                .its("status")
                .should("eq", 403);
        });
        cy.visit(`/v2/user/3`);
        cy.get("#login-as-person-history").should("not.exist");
    });
});
