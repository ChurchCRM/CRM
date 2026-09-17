/// <reference types="cypress" />

/**
 * Member Portal — notices.
 *
 * Every "saved" / "sent" / "that failed" message in the portal, whether the
 * server queued it as a `flash` or a page bundle raised it after a fetch, is
 * one component: a toast in a fixed container at the top-right of the viewport.
 *
 * What that buys, and what this spec pins down:
 *   - the container is `position: fixed`, so a notice NEVER takes part in the
 *     document flow and nothing on the page moves when one appears. The
 *     regression this guards against put the notice inside the header's flex
 *     row, which shoved the church logo halfway across the screen.
 *   - success is green, like the admin side's showGlobalMessage(…, "success")
 *   - it goes away on its own after about five seconds, without a click
 *   - it is announced politely to a screen reader, and can still be dismissed
 *
 * Persona: Lena Black (user 100, person 100, family 20) — the seeded
 * self-service member the other portal specs use.
 */
describe("Member Portal — notices", () => {
    const memberUser = "lena.black.editself.notes@exampl";
    const password = "changeme";

    const loginAsMember = () => {
        cy.clearCookies();
        cy.visit("/session/begin");
        cy.get("input[name=User]").type(memberUser);
        cy.get("input[name=Password]").type(password + "{enter}");
        cy.url({ timeout: 10000 }).should("include", "/portal");
    };

    /** The church logo's left edge, which must not move when a notice appears. */
    const brandLeft = () => cy.get(".portal-brand").then(($brand) => $brand[0].getBoundingClientRect().left);

    // The live viewport, not Cypress.config("viewportWidth") — the config value
    // is the run's default and does not follow a cy.viewport() inside a test.
    const viewportWidth = () => cy.window().then((win) => win.innerWidth);

    it("gives every portal page a fixed, polite toast container", () => {
        loginAsMember();
        cy.visit("/portal/");

        cy.get("#portal-toasts")
            .should("exist")
            .should("have.attr", "aria-live", "polite")
            .should(($container) => {
                expect(getComputedStyle($container[0]).position, "toast container position").to.equal("fixed");
            });
    });

    it("confirms the family details without moving the header, in green, and fades away on its own", () => {
        loginAsMember();
        cy.visit("/portal/family/confirm");

        cy.then(() => brandLeft()).then((leftBefore) => {
            viewportWidth().then((width) => {
                cy.get("#portal-confirm-submit").click();

                cy.get(".portal-flash-success", { timeout: 10000 }).should("be.visible");

                // 1. The notice is out of the flow, so the header has not moved.
                cy.get(".portal-brand").should(($brand) => {
                    expect($brand[0].getBoundingClientRect().left, "church logo left edge").to.be.closeTo(
                        leftBefore,
                        1,
                    );
                });

                // 2. It lives in the fixed container, pinned to the top-right.
                cy.get(".portal-flash-success").should(($toast) => {
                    const toast = $toast[0];
                    const container = toast.closest("#portal-toasts");
                    expect(container, "toast is inside #portal-toasts").to.not.be.null;
                    expect(getComputedStyle(container).position, "container position").to.equal("fixed");

                    const rect = toast.getBoundingClientRect();
                    expect(rect.top, "distance from the top of the viewport").to.be.lessThan(140);
                    expect(rect.right, "distance from the right of the viewport").to.be.greaterThan(width - 60);
                    expect(rect.left, "the toast sits in the right-hand half").to.be.greaterThan(width / 2);
                });
            });

            // 3. Success is green — the same reading as the admin toast.
            cy.get(".portal-flash-success").should(($toast) => {
                const background = getComputedStyle($toast[0]).backgroundColor;
                const [red, green, blue] = background.match(/\d+/g).map(Number);
                expect(green, `green channel of ${background}`).to.be.greaterThan(red);
                expect(green, `green channel of ${background}`).to.be.greaterThan(blue);
            });

            // 4. A dismiss control is still offered, and the message is announced.
            cy.get(".portal-flash-success").should("have.attr", "role", "status");
            cy.get(".portal-flash-success .portal-flash-dismiss").should("exist");
        });

        // 5. Nobody clicked anything: the toast retires by itself.
        cy.get(".portal-flash-success", { timeout: 15000 }).should("not.exist");
    });

    it("keeps the header still on a phone, where the toast spans the screen", () => {
        cy.viewport("iphone-x");
        loginAsMember();
        cy.visit("/portal/family/confirm");

        cy.then(() => brandLeft()).then((leftBefore) => {
            viewportWidth().then((width) => {
                cy.get("#portal-confirm-submit").click();

                cy.get(".portal-flash-success", { timeout: 10000 }).should("be.visible");
                cy.get(".portal-brand").should(($brand) => {
                    expect($brand[0].getBoundingClientRect().left, "church logo left edge").to.be.closeTo(
                        leftBefore,
                        1,
                    );
                });

                // Still the fixed container — a phone header wraps, which hides
                // a flow-positioned notice's displacement without fixing it.
                cy.get(".portal-flash-success").should(($toast) => {
                    const container = $toast[0].closest("#portal-toasts");
                    expect(container, "toast is inside #portal-toasts").to.not.be.null;
                    expect(getComputedStyle(container).position, "container position").to.equal("fixed");

                    // Full width less a margin on each side, rather than a sliver in the corner.
                    const rect = $toast[0].getBoundingClientRect();
                    expect(rect.width, "toast width on a phone").to.be.greaterThan(width * 0.7);
                });
            });
        });
    });

    it("dismisses a notice on click, before the timeout runs out", () => {
        loginAsMember();
        cy.visit("/portal/family/confirm");

        cy.get("#portal-confirm-submit").click();
        cy.get(".portal-flash-success .portal-flash-dismiss", { timeout: 10000 }).click();
        cy.get(".portal-flash-success").should("not.exist");
    });
});
