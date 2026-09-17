/// <reference types="cypress" />

/**
 * Member Portal — the responsive pass (MP8, #9869).
 *
 * `.agents/skills/churchcrm/responsive-design-guidelines.md` fixes three form factors
 * and two hard rules. The portal, unlike the admin shell, is **not** exempt from the
 * phone: it is the one part of ChurchCRM a member opens, and they open it on a phone.
 *
 * The rules asserted here, for every page the portal has:
 *
 *   1. **The page body never scrolls sideways.** A wide table or grid may scroll
 *      inside its own container — `.portal-calendar-grid` and
 *      `.volunteer-table-wrapper` both do — but `document.scrollWidth` may not exceed
 *      the viewport. Failures name the shallowest offending element, because "something
 *      overflows" is not actionable.
 *   2. **Touch targets are at least 44px.** Apple HIG, and the guideline the repo
 *      settled on. Measured on every visible link and button the member can reach,
 *      not on a sample: the rule is cheap to satisfy and expensive to notice late.
 *
 * Every page is visited at all three form factors. 375x812 is the tightest phone the
 * guidelines name; 768x1024 is the tablet; 1200x800 is the width at which the admin
 * shell's sidebar appears, which the portal does not have but which is still the
 * desktop the guidelines fix.
 *
 * Seed persona: user 100, Lena Black — usr_EditSelf=1, confined to the portal. Her
 * username is stored truncated to the column's 32 characters.
 *
 * NOTE: every x-api-key request replaces the browser session cookie with an API-token
 * session, so all API setup happens before the login, once, in `before`.
 */

const MEMBER_USER = "lena.black.editself.notes@example.com";
const MEMBER_PASSWORD = "changeme";

const CHURCH_CALENDAR_ID = 1;
const MEMBER_PERSON_ID = 100;

/**
 * My Teams only exists for a member who runs a team, and its 403 page is a
 * different layout from the one this spec is here to measure. So the fixture makes
 * the persona a team leader for the duration.
 */
const MINISTRY_NAME = `Responsive9869 ${Cypress._.random(0, 1e6)}`;
let teamId = 0;
let ministryId = 0;
let originalVersion = "v1";

/** The form factors the guidelines name, tightest first. */
const FORM_FACTORS = [
    { label: "phone", width: 375, height: 812 },
    { label: "tablet", width: 768, height: 1024 },
    { label: "desktop", width: 1200, height: 800 },
];

/** Apple HIG, and the number responsive-design-guidelines.md settled on. */
const MIN_TOUCH_TARGET = 44;

/**
 * Every page a member can reach, with the selector that says it finished rendering.
 * `/portal/teams` is included even for a member who leads nothing: it renders its
 * own empty state, and an empty state is a layout too.
 */
const PAGES = [
    ["home", "/portal/", ".portal-home"],
    ["calendar", "/portal/calendar", ".portal-calendar-page"],
    ["profile", "/portal/profile", ".portal-profile-page, .portal-card"],
    ["profile edit", "/portal/profile/edit", "form"],
    ["family", "/portal/family", ".portal-card"],
    ["family edit", "/portal/family/edit", "form"],
    ["my teams", "/portal/teams", ".portal-card, .portal-teams-page"],
    ["one team", () => `/portal/teams/${teamId}`, "#volunteerPositionsTable, .portal-card"],
    ["volunteering", "/portal/volunteer/schedule", "#volunteer-my-schedule"],
    ["opportunities", "/portal/volunteer/opportunities", "#volunteer-opportunities"],
];

const adminKey = () => Cypress.env("admin.api.key");

const setConfig = (name, value) =>
    cy.request({
        method: "POST",
        url: `/admin/api/system/config/${name}`,
        headers: { "content-type": "application/json", "x-api-key": adminKey() },
        body: { value },
        failOnStatusCode: false,
    });

const setVisibleCalendars = (visible) =>
    cy.request({
        method: "POST",
        url: "/admin/api/member-portal/calendars",
        headers: { "content-type": "application/json", "x-api-key": adminKey() },
        body: { visible },
        failOnStatusCode: false,
    });

const loginAsMember = () => {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(MEMBER_USER);
    cy.get("input[name=Password]").type(`${MEMBER_PASSWORD}{enter}`);
    cy.url({ timeout: 10000 }).should("include", "/portal");
};

/** Describe an element well enough to go and fix it. */
function describeNode(node) {
    const id = node.id ? `#${node.id}` : "";
    const cls =
        typeof node.className === "string" && node.className.trim()
            ? `.${node.className.trim().split(/\s+/).slice(0, 3).join(".")}`
            : "";
    return `<${node.tagName.toLowerCase()}${id}${cls}>`;
}

/**
 * The page itself must not scroll sideways. A container with its own
 * `overflow-x: auto` is allowed to be wider than the screen inside its box — that is
 * the documented escape hatch for tables and the calendar grid — so this reads
 * `documentElement.scrollWidth`, which such a container does not contribute to.
 */
function assertNoHorizontalOverflow(label) {
    cy.document().then((doc) => {
        const el = doc.documentElement;
        const limit = el.clientWidth;

        // Only the shallowest offenders: one wide child drags its whole ancestor
        // chain past the edge, and listing all of them buries the cause.
        const offenders = [];
        for (const node of doc.querySelectorAll("body *")) {
            const rect = node.getBoundingClientRect();
            if (rect.width > 0 && rect.right > limit + 1) {
                if (!offenders.some((o) => o.node.contains(node))) {
                    offenders.push({ node, rect });
                }
            }
        }
        const named = offenders
            .slice(0, 5)
            .map((o) => `${describeNode(o.node)} right=${Math.round(o.rect.right)}`)
            .join(" ");

        expect(
            el.scrollWidth,
            `${label} must not scroll the page sideways (scrollWidth ${el.scrollWidth} vs clientWidth ${limit})${named ? ` — widest offenders: ${named}` : ""}`,
        ).to.be.at.most(limit + 1);
    });
}

/**
 * Every visible control a finger has to hit is at least 44px in both directions.
 *
 * Inline links inside a paragraph are excluded: they are read, not tapped, and the
 * guideline is about controls. The test is therefore restricted to elements the
 * portal styles as controls — buttons, inputs, and the links it gives a button or
 * nav class to.
 */
function assertTouchTargets(label) {
    cy.document().then((doc) => {
        const selector = [
            "button",
            "input[type=submit]",
            "input[type=button]",
            ".portal-button",
            ".portal-nav-link",
            ".portal-staff-bar-exit",
            ".portal-card-link",
            "#impersonationExit",
        ].join(",");

        const tooSmall = [];
        for (const node of doc.querySelectorAll(selector)) {
            const rect = node.getBoundingClientRect();
            // Hidden controls have no box; they are measured when they are shown.
            if (rect.width === 0 && rect.height === 0) continue;
            if (
                rect.height < MIN_TOUCH_TARGET - 0.5 ||
                rect.width < MIN_TOUCH_TARGET - 0.5
            ) {
                tooSmall.push(
                    `${describeNode(node)} ${Math.round(rect.width)}x${Math.round(rect.height)}`,
                );
            }
        }

        expect(
            tooSmall,
            `${label}: every control must be at least ${MIN_TOUCH_TARGET}px — too small: ${tooSmall.join(", ")}`,
        ).to.deep.eq([]);
    });
}

describe("Member Portal — responsive (#9869)", () => {
    before(() => {
        // Everything the pages need to render their real content rather than an
        // empty state, done once and before any login. The rollout flag is stashed
        // and put back in `after`: leaving it on `v2` retires the V1 opportunity
        // editor, and the admin specs that open it then fail for no visible reason.
        cy.request({
            url: "/admin/api/system/config/sVolunteerVersion",
            headers: { "x-api-key": adminKey() },
            failOnStatusCode: false,
        }).then((resp) => {
            originalVersion = resp.body.value ?? resp.body.data ?? "v1";
        });

        setConfig("bPortalShowCalendar", "1");
        setConfig("bPortalShowVolunteer", "1");
        setConfig("sVolunteerVersion", "v2");
        setVisibleCalendars([{ type: "calendar", id: CHURCH_CALENDAR_ID }]);

        cy.request({
            method: "POST",
            url: "/api/volunteer/ministries",
            headers: {
                "content-type": "application/json",
                "x-api-key": adminKey(),
            },
            body: { name: MINISTRY_NAME, description: "#9869 responsive fixture" },
        }).then((resp) => {
            ministryId = resp.body.ministry.id;

            // The ministry came with one team (D18); make the persona its leader.
            cy.request({
                method: "GET",
                url: `/api/volunteer/ministries/${ministryId}/teams`,
                headers: { "x-api-key": adminKey() },
            }).then((tResp) => {
                teamId = tResp.body.teams[0].id;
                cy.request({
                    method: "POST",
                    url: "/api/volunteer/scopes",
                    headers: {
                        "content-type": "application/json",
                        "x-api-key": adminKey(),
                    },
                    body: {
                        personId: MEMBER_PERSON_ID,
                        scopeType: "team",
                        scopeId: teamId,
                    },
                    failOnStatusCode: false,
                });
            });
        });
    });

    after(() => {
        setVisibleCalendars([]);
        cy.then(() => {
            // An active ministry cannot be deleted (409): deactivate first (2026-09-17 lifecycle rule).
            cy.request({
                method: "POST",
                url: `/api/volunteer/ministries/${ministryId}`,
                headers: { "x-api-key": adminKey() },
                body: { active: false },
                failOnStatusCode: false,
            });
            cy.request({
                method: "DELETE",
                url: `/api/volunteer/ministries/${ministryId}`,
                headers: { "x-api-key": adminKey() },
                failOnStatusCode: false,
            });
            setConfig("sVolunteerVersion", originalVersion);
        });
    });

    for (const factor of FORM_FACTORS) {
        describe(`at ${factor.label} (${factor.width}x${factor.height})`, () => {
            beforeEach(() => {
                cy.viewport(factor.width, factor.height);
                loginAsMember();
            });

            for (const [name, url, ready] of PAGES) {
                it(`${name} fits and its controls are tappable`, () => {
                    cy.viewport(factor.width, factor.height);
                    // A page whose URL needs a fixture id is given as a thunk, so the
                    // id is read when the test runs rather than when the file loads.
                    cy.visit(typeof url === "function" ? url() : url, {
                        timeout: 30000,
                    });
                    cy.get(ready, { timeout: 20000 }).should("exist");

                    const label = `${name} @ ${factor.width}px`;
                    assertNoHorizontalOverflow(label);
                    assertTouchTargets(label);
                });
            }
        });
    }
});
