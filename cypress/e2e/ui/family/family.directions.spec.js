/// <reference types="cypress" />

/**
 * Get Directions dropdown on the family profile (7.2.1).
 *
 * The primary "Get Directions" anchor always points at Google Maps so the
 * happy path works on every platform. When the user is on an iOS/iPadOS
 * device, a split dropdown-toggle also appears with an "Open in Apple
 * Maps" option using the maps.apple.com scheme.
 *
 * Non-iOS tests simply verify the Google Maps link renders with the right
 * destination scheme. iOS tests spoof the userAgent so the client-side
 * detector reveals the Apple Maps option.
 */
describe("Get Directions (Google + Apple Maps)", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("always renders a Google Maps link with the correct deep-link scheme", () => {
        cy.visit("people/family/1");
        cy.get(".directions-btn-group a[href*='google.com/maps/dir']")
            .should("exist")
            .and("have.attr", "target", "_blank")
            .and("have.attr", "href")
            .and("match", /destination=/);
    });

    it("hides the Apple Maps option and its dropdown toggle on non-iOS user agents", () => {
        cy.visit("people/family/1");
        // Apple Maps entry is rendered but kept hidden outside iOS.
        cy.get(".directions-btn-group .directions-provider-toggle").should("have.class", "d-none");
        cy.get(".directions-btn-group .apple-maps-option").should("not.be.visible");
    });

    it("shows the Apple Maps option when the user agent is iOS", () => {
        // Spoof a mobile Safari user agent so the client-side detector
        // in family-view.php unhides the Apple Maps dropdown option.
        cy.visit("people/family/1", {
            onBeforeLoad(win) {
                Object.defineProperty(win.navigator, "userAgent", {
                    value:
                        "Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1",
                    configurable: true,
                });
            },
        });

        cy.get(".directions-btn-group .directions-provider-toggle").should("not.have.class", "d-none");
        cy.get(".directions-btn-group .directions-provider-toggle").click();
        cy.get(".directions-btn-group .apple-maps-option")
            .should("be.visible")
            .and("have.attr", "href")
            .and("match", /maps\.apple\.com\/\?daddr=/);
    });
});
