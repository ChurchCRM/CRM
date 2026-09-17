/// <reference types="cypress" />

/**
 * Member Portal — the header: the church brand link.
 *
 * Product review (2026-09-17): hovering the church name must not restyle it —
 * the core bundle's `a:hover` was painting it link-blue and underlining it.
 *
 * Seed persona: user 100, Lena Black (person 100, family 20). usr_EditSelf=1
 * and no admin flag, so she is confined to the portal. The username column is
 * VARCHAR(32), so the seeded address is stored truncated — log in with the
 * 32-character form.
 */
const MEMBER_USER = "lena.black.editself.notes@exampl";
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
});
