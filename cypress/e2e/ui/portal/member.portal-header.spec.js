/// <reference types="cypress" />

/**
 * Member Portal — the header: the church brand link and the account menu.
 *
 * Product review (2026-09-17):
 *   - hovering the church name must not restyle it (the core bundle's `a:hover`
 *     was painting it link-blue and underlining it)
 *   - the member's name and the bare "Sign out" link are replaced by one
 *     "Hello <first name>" button that opens a menu: Change Password,
 *     Admin Console (staff logins only, never during a masquerade), Sign out
 *
 * Seed persona: user 100, Lena Black (person 100, family 20). usr_EditSelf=1
 * and no admin flag, so she is confined to the portal. The username column is
 * VARCHAR(32), so the seeded address is stored truncated — log in with the
 * 32-character form.
 */
const MEMBER_USER = "lena.black.editself.notes@example.com";
const MEMBER_PASSWORD = "changeme";

const PROBE_CLASS = "portal-state-probe";

/**
 * Put an element into a pseudo-class state (`:hover`, `:focus-visible`) and
 * return the computed style it takes there.
 *
 * A synthetic `mouseover` does not switch CSS's `:hover` on — only a real
 * pointer does — and `:focus-visible` cannot be provoked from a script at all.
 * The state is staged instead: every rule the page carries for that
 * pseudo-class is re-inserted with the pseudo-class swapped for a marker class,
 * and the marker is put on the element. A class and a pseudo-class weigh the
 * same in the cascade, so the copy wins exactly where the real rule would.
 */
const styleUnderState = (win, element, pseudo) => {
    const copies = [];

    const copyRule = (rule) => {
        if (rule.selectorText?.includes(pseudo)) {
            copies.push(`${rule.selectorText.replaceAll(pseudo, `.${PROBE_CLASS}`)}{${rule.style.cssText}}`);
        }
    };

    for (const sheet of Array.from(win.document.styleSheets)) {
        let rules;
        try {
            rules = Array.from(sheet.cssRules);
        } catch {
            // A cross-origin stylesheet refuses to be read; the portal's own
            // are same-origin, so nothing this spec cares about is skipped.
            continue;
        }
        for (const rule of rules) {
            if (rule.media) {
                if (win.matchMedia(rule.conditionText).matches) {
                    for (const inner of Array.from(rule.cssRules)) {
                        copyRule(inner);
                    }
                }
                continue;
            }
            copyRule(rule);
        }
    }

    const style = win.document.createElement("style");
    style.textContent = copies.join("\n");
    win.document.head.append(style);
    element.classList.add(PROBE_CLASS);

    const computed = win.getComputedStyle(element);
    const result = {
        color: computed.color,
        textDecorationLine: computed.textDecorationLine,
        outlineStyle: computed.outlineStyle,
        outlineWidth: computed.outlineWidth,
    };

    element.classList.remove(PROBE_CLASS);
    style.remove();

    return result;
};

const login = () => {
    cy.clearCookies();
    cy.visit("/session/begin");
    cy.get("input[name=User]").type(MEMBER_USER);
    cy.get("input[name=Password]").type(`${MEMBER_PASSWORD}{enter}`);
    cy.url({ timeout: 10000 }).should("include", "/portal");
};

describe("Member Portal header", () => {
    beforeEach(() => {
        login();
    });

    describe("The church brand link", () => {
        it("Does not change colour or gain an underline on hover", () => {
            cy.get(".portal-brand").should("be.visible");

            // The assertion is "the same as it was", not a hard-coded colour,
            // so a church theme that recolours the header still passes.
            cy.window().then((win) => {
                cy.get(".portal-brand").then(($brand) => {
                    const resting = win.getComputedStyle($brand[0]);
                    const restingColor = resting.color;
                    const restingDecoration = resting.textDecorationLine;

                    expect(restingDecoration, "the church name is not underlined at rest").to.equal("none");

                    const hovered = styleUnderState(win, $brand[0], ":hover");
                    expect(hovered.color, "the church name keeps its colour on hover").to.equal(restingColor);
                    expect(hovered.textDecorationLine, "the church name stays un-underlined on hover").to.equal(
                        restingDecoration,
                    );
                });
            });

            // A real pointer event must not restyle it either.
            cy.get(".portal-brand").trigger("mouseover");
            cy.get(".portal-brand").should("have.css", "text-decoration-line", "none");
        });

        it("Keeps a visible focus ring for keyboard users", () => {
            cy.window().then((win) => {
                cy.get(".portal-brand").then(($brand) => {
                    const focused = styleUnderState(win, $brand[0], ":focus-visible");
                    expect(focused.outlineStyle, "the brand link draws a focus outline").to.not.equal("none");
                    expect(focused.outlineWidth, "the focus outline has a width").to.not.equal("0px");
                });
            });
        });

        it("Still navigates to the portal home", () => {
            cy.visit("/portal/profile");
            cy.get(".portal-brand").click();
            cy.url({ timeout: 10000 }).should("match", /\/portal\/?$/);
        });
    });

    describe("The account menu", () => {
        it("Greets the member by first name on the toggle button", () => {
            cy.get("#portal-account-toggle")
                .should("be.visible")
                .and("contain.text", "Hello Lena")
                .and("have.attr", "aria-haspopup", "menu")
                .and("have.attr", "aria-expanded", "false");

            // The bare name and the bare sign-out link are gone.
            cy.get(".portal-member-name").should("not.exist");
            cy.get(".portal-signout").should("not.exist");
        });

        it("Is closed until the button is clicked", () => {
            cy.get("#portal-account-menu").should("not.be.visible");

            cy.get("#portal-account-toggle").click();

            cy.get("#portal-account-menu").should("be.visible").and("have.attr", "role", "menu");
            cy.get("#portal-account-toggle").should("have.attr", "aria-expanded", "true");
        });

        it("Offers Change Password and Sign out, but not Admin Console, to a member", () => {
            cy.get("#portal-account-toggle").click();

            cy.get('#portal-account-menu [role="menuitem"]').should("have.length", 2);
            cy.get("#portal-account-menu")
                .contains('[role="menuitem"]', "Change Password")
                .should("have.attr", "href")
                .and("include", "/portal/profile/password");
            cy.get("#portal-account-menu")
                .contains('[role="menuitem"]', "Sign out")
                .should("have.attr", "href")
                .and("include", "/session/end");
            cy.get("#portal-account-menu").contains("Admin Console").should("not.exist");
        });

        it("Closes on Escape and gives focus back to the button", () => {
            cy.get("#portal-account-toggle").click();
            cy.get("#portal-account-menu").should("be.visible");

            cy.get("#portal-account-menu").trigger("keydown", { key: "Escape" });

            cy.get("#portal-account-menu").should("not.be.visible");
            cy.get("#portal-account-toggle").should("have.attr", "aria-expanded", "false");
            cy.focused().should("have.id", "portal-account-toggle");
        });

        it("Closes when a click lands outside it", () => {
            cy.get("#portal-account-toggle").click();
            cy.get("#portal-account-menu").should("be.visible");

            cy.get("#portal-main").click("topLeft");

            cy.get("#portal-account-menu").should("not.be.visible");
            cy.get("#portal-account-toggle").should("have.attr", "aria-expanded", "false");
        });

        it("Moves between the items with the arrow keys", () => {
            cy.get("#portal-account-toggle").click();

            cy.focused().should("contain.text", "Change Password");
            cy.focused().trigger("keydown", { key: "ArrowDown" });
            cy.focused().should("contain.text", "Sign out");
            cy.focused().trigger("keydown", { key: "ArrowUp" });
            cy.focused().should("contain.text", "Change Password");
        });

        it("Signs the member out from the menu", () => {
            cy.get("#portal-account-toggle").click();
            cy.get("#portal-account-menu").contains('[role="menuitem"]', "Sign out").click();
            cy.url({ timeout: 10000 }).should("include", "/session/begin");
        });

        it("Works beside the navigation toggle on a phone", () => {
            cy.viewport(375, 812);
            cy.visit("/portal/");

            cy.get("#portal-nav-toggle").should("be.visible");
            cy.get("#portal-account-toggle").should("be.visible").click();
            cy.get("#portal-account-menu").should("be.visible");

            // The menu is right-aligned to its button; on a phone the header
            // wraps, and the button must not end up so far to the leading edge
            // that the menu hangs off the side of the screen.
            cy.get("#portal-account-menu").then(($menu) => {
                const box = $menu[0].getBoundingClientRect();
                expect(box.left, "the menu starts inside the viewport").to.be.at.least(0);
                expect(box.right, "the menu ends inside the viewport").to.be.at.most(375);
            });

            // The hamburger still opens the navigation while the menu is up.
            cy.get("#portal-nav-toggle").click();
            cy.get("#portal-nav").should("have.class", "is-open");
        });
    });
});
