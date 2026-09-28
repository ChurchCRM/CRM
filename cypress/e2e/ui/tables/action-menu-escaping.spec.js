/// <reference types="cypress" />

/**
 * Row action menus — markup contract and attribute-context escaping.
 *
 * Companion to action-menu-overflow.spec.js (#9373, dropdown clipping). Where that
 * spec proves the menu is *visible*, this one proves the menu is *correctly built*:
 * the trigger carries exactly the documented attribute set, the item list is the
 * documented one, and every `data-*` value survives a round trip through the
 * attribute it is written into — including names and titles that contain `"`, `'`,
 * `<` and `&`.
 *
 * Why it matters: `window.CRM.render{Person,Family,Event}ActionMenu` assemble their
 * dropdown by string concatenation, so a value that is not escaped for *attribute*
 * context can terminate the attribute it sits in and change the parsed markup.
 * `.agents/skills/churchcrm/table-action-menu.md` is the single source of truth for
 * the markup asserted here.
 *
 * GitHub: https://github.com/ChurchCRM/CRM/issues/9820
 *
 * Two layers:
 *   1. Renderer output — the three renderers are called directly through
 *      `cy.window()` with a hostile string and their returned HTML is parsed and
 *      inspected. Deterministic, and independent of what any server-side input
 *      filter does to a stored name.
 *   2. Live lists — a person, a family and an event whose names contain the same
 *      characters are created, and the menus the real pages render for them are
 *      inspected in the DOM (main dashboard "Latest Families" / "Latest People"
 *      tabs, events dashboard).
 */

/**
 * Hostile literal embedded in every name/title under test.
 *
 * The space after `<` is deliberate: `InputUtils::sanitizeText()` (which the
 * POST /api/events route applies to `Title`) runs `strip_tags()`, and `<` followed
 * immediately by a letter is eaten as an unterminated tag. `< ` is left alone, so
 * the stored title still contains all four characters this spec cares about.
 */
const HOSTILE = `A"B'C < D & E`;

/** Plain-ASCII markers so the cleanup search endpoints can find leftovers. */
const PERSON_LAST_NAME = "Cr2MenuEsc";
const FAMILY_NAME = `${HOSTILE} Cr2MenuEscFam`;
const EVENT_TITLE = `${HOSTILE} Cr2MenuEscEvt`;

/** Ids created by this spec, torn down in `after`. */
const created = { personId: null, personName: null, familyId: null, eventId: null };

/**
 * Local helper — NOT a cy.* command, deliberately copied into this spec rather than
 * added to cypress/support (see `.agents/skills/churchcrm/cypress-testing.md` →
 * "cy.request() API Calls Reset PHP Sessions"). Every cy.request()-backed call makes
 * PHP issue a new session, invalidating the browser session a later cy.visit() needs,
 * and `cy.setupAdminSession({ forceLogin: true })` is documented as not sufficient to
 * recover from that.
 */
function freshAdminLogin() {
    cy.clearAllCookies();
    cy.visit("/session/begin");
    // Type only once the form is actually up. Typing into a page that is still
    // settling occasionally fails with "Cannot read properties of undefined
    // (reading 'KeyboardEvent')" — Cypress lost the AUT window mid-keystroke.
    cy.get("input[name=User]").should("be.visible").type(Cypress.env("admin.username"));
    cy.get("input[name=Password]")
        .should("be.visible")
        .type(Cypress.env("admin.password") + "{enter}");
    cy.url().should("not.include", "/session/begin");
    cy.getCookies().should("satisfy", (cookies) =>
        cookies.some((cookie) => cookie.name.startsWith("CRM-")),
    );
}

/** Delete every person / family / event this spec's markers match. Idempotent. */
function deleteLeftovers() {
    cy.makePrivateAdminAPICall("GET", `/api/persons/search/${PERSON_LAST_NAME}`, null, 200).then(
        (resp) => {
            resp.body.forEach((hit) => {
                cy.makePrivateAdminAPICall("DELETE", `/api/person/${hit.objid}`, null, [200, 404]);
            });
        },
    );
    cy.makePrivateAdminAPICall("GET", "/api/families/search/Cr2MenuEscFam", null, 200).then(
        (resp) => {
            resp.body.Families.forEach((hit) => {
                cy.makePrivateAdminAPICall("DELETE", `/api/family/${hit.Id}`, null, [200, 404]);
            });
        },
    );
    cy.makePrivateAdminAPICall("GET", "/api/events", null, 200).then((resp) => {
        resp.body.Events.filter((evt) => String(evt.Title).includes("Cr2MenuEscEvt")).forEach(
            (evt) => {
                cy.makePrivateAdminAPICall("DELETE", `/api/events/${evt.Id}`, null, [200, 404]);
            },
        );
    });
}

/**
 * Return the attributes of an element as a plain object, so a test can assert the
 * *complete* attribute set rather than only the attributes it remembered to check.
 */
function attributeMap($el) {
    const map = {};
    Array.from($el[0].attributes).forEach((attr) => {
        map[attr.name] = attr.value;
    });
    return map;
}

/** The canonical trigger every action menu must emit (table-action-menu.md). */
const EXPECTED_TRIGGER_ATTRIBUTES = {
    class: "btn btn-sm btn-ghost-secondary",
    type: "button",
    "data-bs-toggle": "dropdown",
    "data-bs-display": "static",
    "aria-expanded": "false",
};

/**
 * Shared structural assertions for one rendered action menu.
 *
 * @param {JQuery} $root      Element containing exactly one `.dropdown` action menu.
 * @param {string[]} itemTexts Expected `.dropdown-item` texts, in order.
 * @param {number} dividers    Expected number of `.dropdown-divider` elements.
 */
function assertMenuShape($root, itemTexts, dividers) {
    expect($root.find(".dropdown").length, "one dropdown wrapper").to.equal(1);

    const $trigger = $root.find("button[data-bs-toggle='dropdown']");
    expect($trigger.length, "one dropdown trigger").to.equal(1);
    // Exact set — a stray attribute is a regression just as much as a missing one.
    expect(attributeMap($trigger), "trigger attributes").to.deep.equal(
        EXPECTED_TRIGGER_ATTRIBUTES,
    );
    expect($trigger.find("i.fa-solid.fa-ellipsis-vertical").length, "ellipsis icon").to.equal(1);

    const $menu = $root.find(".dropdown-menu");
    expect($menu.length, "one dropdown menu").to.equal(1);
    expect($menu.attr("class"), "menu classes").to.equal("dropdown-menu dropdown-menu-end");

    const texts = $root
        .find(".dropdown-item")
        .toArray()
        .map((el) => el.textContent.trim());
    expect(texts, "item count and texts").to.deep.equal(itemTexts);
    expect($root.find(".dropdown-divider").length, "divider count").to.equal(dividers);
}

const PERSON_ITEMS = ["View", "Edit", "Add to Cart", "Delete"];
const FAMILY_ITEMS = ["View", "Edit", "Add to Cart", "Delete"];
const EVENT_ITEMS = ["View", "Edit", "Check-in", "Deactivate", "Delete"];

describe("Row action menus — markup and attribute escaping (#9820)", () => {
    before(() => {
        // ── API setup first, browser login afterwards (never the other way round).
        deleteLeftovers();

        const year = new Date().getFullYear();
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/events",
            {
                Title: EVENT_TITLE,
                Type: 1,
                Desc: "",
                Text: "",
                // 31 December of the current year: inside the events dashboard's
                // default view and still in the future, so the row lands in the
                // always-visible "current events" tbody rather than the collapsed
                // past-events one (#9795).
                Start: `${year}-12-31 23:00:00`,
                End: `${year}-12-31 23:59:00`,
                PinnedCalendars: [],
            },
            200,
        );
        cy.makePrivateAdminAPICall("GET", "/api/events", null, 200).then((resp) => {
            const matches = resp.body.Events.filter((evt) => evt.Title === EVENT_TITLE);
            // Fail loudly here rather than in an assertion below if a server-side
            // input filter ever starts rewriting the title.
            expect(matches, `exactly one event titled ${JSON.stringify(EVENT_TITLE)}`).to.have.length(1);
            created.eventId = matches[0].Id;
        });

        // ── Person and family have no create API; they go through the legacy editors.
        freshAdminLogin();

        cy.visit("/PersonEditor.php");
        cy.get("#FirstName").type(HOSTILE, { parseSpecialCharSequences: false });
        cy.get("#LastName").type(PERSON_LAST_NAME);
        cy.get("#Gender").select("1");
        cy.get("#Classification").select("1");
        cy.get('button[name="PersonSubmit"]').click();
        cy.location("pathname")
            .should("include", "/people/view/")
            .then((pathname) => {
                created.personId = Number(pathname.split("/").pop());
            });

        cy.visit("/FamilyEditor.php");
        cy.get("#FamilyName").type(FAMILY_NAME, { parseSpecialCharSequences: false });
        cy.get('input[name="FirstName1"]').type("Cr2MenuEscMember");
        cy.get('select[name="Classification1"]').select("1", { force: true });
        cy.get('button[name="FamilySubmit"]').click();
        cy.location("pathname")
            .should("include", "/people/family/")
            .then((pathname) => {
                created.familyId = Number(pathname.split("/").pop());
            });

        // Capture the person's name exactly as the dashboard column will receive it
        // (`FirstName + " " + LastName` from /api/persons/latest). Reading it here
        // rather than hard-coding it keeps the assertion honest about whatever the
        // legacy PersonEditor input filter stored.
        cy.makePrivateAdminAPICall("GET", "/api/persons/latest", null, 200).then((resp) => {
            const row = resp.body.people.find((p) => p.PersonId === created.personId);
            expect(row, `person ${created.personId} in /api/persons/latest`).to.not.equal(undefined);
            created.personName = `${row.FirstName} ${row.LastName}`;
            expect(created.personName, "stored name still carries a double quote").to.include('"');
        });
    });

    after(() => {
        if (created.personId) {
            cy.makePrivateAdminAPICall("DELETE", `/api/person/${created.personId}`, null, [200, 404]);
        }
        if (created.familyId) {
            cy.makePrivateAdminAPICall("DELETE", `/api/family/${created.familyId}`, null, [200, 404]);
        }
        if (created.eventId) {
            cy.makePrivateAdminAPICall("DELETE", `/api/events/${created.eventId}`, null, [200, 404]);
        }
        // Belt and braces: anything the id capture missed still matches the markers.
        deleteLeftovers();
    });

    // ─────────────────────────────────────────────────────────────────────────────
    // Layer 1 — the renderers, called directly.
    // ─────────────────────────────────────────────────────────────────────────────
    context("renderer output", () => {
        beforeEach(() => {
            // cy.session()-backed login: no form typing per test, and the cache is
            // created fresh after this spec's `before` hook has finished its API
            // calls, so there is no stale PHP session to inherit.
            cy.setupAdminSession();
            cy.visit("/v2/dashboard");
            cy.waitForLocales();
        });

        /**
         * Call one renderer in the application window and hand the parsed markup to
         * `assertions`. Parsing through the AUT's jQuery means `.attr()` returns the
         * browser-decoded value — i.e. exactly the round trip a consumer performs.
         */
        function withRenderedMenu(rendererName, args, assertions) {
            cy.window().then((win) => {
                expect(win.CRM, `window.CRM.${rendererName}`).to.have.property(rendererName);
                const html = win.CRM[rendererName].apply(null, args);
                assertions(win.jQuery("<div></div>").html(html), html);
            });
        }

        it("person menu: documented shape and a name that round-trips", () => {
            withRenderedMenu("renderPersonActionMenu", [42, HOSTILE, {}], ($root) => {
                assertMenuShape($root, PERSON_ITEMS, 2);

                const $delete = $root.find("button.delete-person");
                expect($delete.length, "one delete button").to.equal(1);
                expect($delete.attr("data-person_id"), "data-person_id").to.equal("42");
                expect($delete.attr("data-person_name"), "data-person_name round trip").to.equal(
                    HOSTILE,
                );
                expect($delete.hasClass("text-danger"), "delete is destructive").to.equal(true);
            });
        });

        it("person menu: View Family item appears only with a familyId", () => {
            withRenderedMenu("renderPersonActionMenu", [42, HOSTILE, { familyId: 7 }], ($root) => {
                assertMenuShape($root, ["View", "Edit", "View Family", "Add to Cart", "Delete"], 2);
            });
        });

        it("person menu: inCart flips the cart item without changing the shape", () => {
            withRenderedMenu("renderPersonActionMenu", [42, HOSTILE, { inCart: true }], ($root) => {
                assertMenuShape($root, ["View", "Edit", "Remove from Cart", "Delete"], 2);
                const $cart = $root.find("button.RemoveFromCart");
                expect($cart.length, "one cart button").to.equal(1);
                expect($cart.attr("data-cart-id")).to.equal("42");
                expect($cart.attr("data-cart-type")).to.equal("person");
                expect($cart.attr("data-label-add")).to.equal("Add to Cart");
                expect($cart.attr("data-label-remove")).to.equal("Remove from Cart");
            });
        });

        it("family menu: documented shape and cart data attributes", () => {
            withRenderedMenu("renderFamilyActionMenu", [99, HOSTILE, {}], ($root) => {
                assertMenuShape($root, FAMILY_ITEMS, 2);

                const $delete = $root.find("button.delete-family");
                expect($delete.length, "one delete button").to.equal(1);
                expect($delete.attr("data-family_id"), "data-family_id").to.equal("99");

                const $cart = $root.find("button.AddToCart");
                expect($cart.attr("data-cart-id")).to.equal("99");
                expect($cart.attr("data-cart-type")).to.equal("family");
            });
        });

        it("event menu: documented shape and a title that round-trips", () => {
            withRenderedMenu("renderEventActionMenu", [7, HOSTILE, {}], ($root) => {
                assertMenuShape($root, EVENT_ITEMS, 2);

                const $delete = $root.find("button.delete-event");
                expect($delete.length, "one delete button").to.equal(1);
                expect($delete.attr("data-event_id"), "data-event_id").to.equal("7");
                expect($delete.attr("data-event_title"), "data-event_title round trip").to.equal(
                    HOSTILE,
                );
            });
        });

        it("event menu: inactive flips Deactivate to Activate", () => {
            withRenderedMenu("renderEventActionMenu", [7, HOSTILE, { inactive: true }], ($root) => {
                assertMenuShape($root, ["View", "Edit", "Check-in", "Activate", "Delete"], 2);
                expect($root.find("button.activate-event").length, "activate button").to.equal(1);
                expect($root.find("button.deactivate-event").length, "no deactivate button").to.equal(0);
            });
        });
    });

    // ─────────────────────────────────────────────────────────────────────────────
    // Layer 2 — the menus the real list pages render.
    // ─────────────────────────────────────────────────────────────────────────────
    context("menus rendered by the list pages", () => {
        beforeEach(() => {
            cy.setupAdminSession();
        });

        it("main dashboard renders the person menu for a person with a hostile name", () => {
            // A successful login already lands on /v2/dashboard, and cy.visit() to the
            // page you are already on does not reload — so the dashboard's DataTables
            // AJAX has usually fired before the test body runs. Wait on the rendered
            // rows rather than on an intercept that would arrive too late to register.
            cy.visit("/v2/dashboard");
            cy.get("#latest-ppl-tab").click();

            cy.get(`button.delete-person[data-person_id="${created.personId}"]`, { timeout: 15000 })
                .should("exist")
                .then(($delete) => {
                    expect($delete.attr("data-person_name"), "data-person_name round trip").to.equal(
                        created.personName,
                    );
                    assertMenuShape($delete.closest(".dropdown").parent(), PERSON_ITEMS, 2);
                });

            // The menu also has to actually open (companion to action-menu-overflow.spec.js).
            cy.get(`button.delete-person[data-person_id="${created.personId}"]`)
                .closest(".dropdown")
                .find("button[data-bs-toggle='dropdown']")
                .click();
            cy.get(".dropdown-menu.show").should("be.visible");
        });

        it("main dashboard renders the family menu for a family with a hostile name", () => {
            cy.visit("/v2/dashboard");

            cy.get(`button.delete-family[data-family_id="${created.familyId}"]`, { timeout: 15000 })
                .should("exist")
                .then(($delete) => {
                    // The family menu carries no name-bearing attribute; the cart item's
                    // translated labels are the attribute values to round-trip here.
                    const $root = $delete.closest(".dropdown").parent();
                    assertMenuShape($root, FAMILY_ITEMS, 2);
                    const $cart = $root.find("button.AddToCart");
                    expect($cart.attr("data-cart-id")).to.equal(String(created.familyId));
                    expect($cart.attr("data-cart-type")).to.equal("family");
                    expect($cart.attr("data-label-add")).to.equal("Add to Cart");
                    expect($cart.attr("data-label-remove")).to.equal("Remove from Cart");
                });
        });

        it("events dashboard renders the event menu for an event with a hostile title", () => {
            cy.visit("/event/dashboard");

            cy.get(`.event-action-menu-placeholder[data-event-id="${created.eventId}"]`, {
                timeout: 10000,
            }).should("be.visible");

            // The menu markup is injected by JS once the locales are ready.
            cy.get(
                `.event-action-menu-placeholder[data-event-id="${created.eventId}"] button.delete-event`,
                { timeout: 10000 },
            )
                .should("exist")
                .then(($delete) => {
                    expect($delete.attr("data-event_id"), "data-event_id").to.equal(
                        String(created.eventId),
                    );
                    expect($delete.attr("data-event_title"), "data-event_title round trip").to.equal(
                        EVENT_TITLE,
                    );
                    assertMenuShape($delete.closest(".dropdown").parent(), EVENT_ITEMS, 2);
                });
        });
    });
});
