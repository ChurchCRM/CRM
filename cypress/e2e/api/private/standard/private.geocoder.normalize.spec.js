/// <reference types="cypress" />

/**
 * Regression test for issue #9847 — the geocoder must normalise numbered
 * street names before querying Nominatim.
 *
 * POST /api/geocoder/address makes a live Nominatim call, so this uses a
 * stable, well-known public address (the Empire State Building) and asserts
 * the result lands within ~1 km of it. Before the fix, "350 5 Avenue" (no
 * ordinal suffix) returned a fuzzy match on Long Island, ~40 miles away.
 *
 * Nominatim's usage policy allows one request per second; the second call
 * waits so the two lookups never run back to back.
 */
describe("API Private Geocoder — street name normalisation (#9847)", () => {
    const EMPIRE_STATE = { lat: 40.7484, lon: -73.9857 };
    const TOLERANCE = 0.01; // ~1 km

    const expectNear = (body) => {
        expect(body).to.have.property("Latitude").that.is.a("number");
        expect(body).to.have.property("Longitude").that.is.a("number");
        expect(Math.abs(body.Latitude - EMPIRE_STATE.lat)).to.be.lessThan(TOLERANCE);
        expect(Math.abs(body.Longitude - EMPIRE_STATE.lon)).to.be.lessThan(TOLERANCE);
    };

    it("resolves the address written with its ordinal suffix (control)", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/geocoder/address",
            { address: "350 5th Avenue, New York, NY 10118" },
            200,
            30000,
        ).then((response) => expectNear(response.body));
    });

    it("resolves the same address written without the ordinal suffix", () => {
        cy.wait(1100);
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/geocoder/address",
            { address: "350 5 Avenue, New York, NY 10118" },
            200,
            30000,
        ).then((response) => expectNear(response.body));
    });

    it("ignores a trailing unit designator", () => {
        cy.wait(1100);
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/geocoder/address",
            { address: "350 5 Avenue Suite 3300, New York, NY 10118" },
            200,
            30000,
        ).then((response) => expectNear(response.body));
    });

    it("normalises a street line that starts with the street number (no house number)", () => {
        // "5 Avenue" with nothing in front of it: the leading number is the street,
        // not a house number, because the street-type word ends the line.
        // Result is somewhere along Fifth Avenue in Manhattan (street-level match).
        cy.wait(1100);
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/geocoder/address",
            { address: "5 Avenue, New York, NY 10001" },
            200,
            30000,
        ).then((response) => {
            expect(response.body.Latitude).to.be.within(40.70, 40.80);
            expect(response.body.Longitude).to.be.within(-74.02, -73.94);
        });
    });

    it("strips stacked unit designators", () => {
        cy.wait(1100);
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/geocoder/address",
            { address: "350 5 Avenue Apt 215 Suite 3300, New York, NY 10118" },
            200,
            30000,
        ).then((response) => expectNear(response.body));
    });
});
