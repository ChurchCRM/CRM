/// <reference types="cypress" />

/**
 * Geocoding provider chain (#9848).
 *
 * `sGeocoderProviders` is a comma-separated ranking of geocoding services;
 * GeoUtils::getLatLong() tries them in order. These tests drive
 * POST /api/geocoder/address under different rankings, using a stable public
 * address (the Empire State Building) that both services resolve, and assert
 * the result lands within ~1 km. Every lookup is a live call to the chosen
 * service; the setting is restored to its default afterwards.
 */
describe("API Private Geocoder — provider chain (#9848)", () => {
    const CONFIG_URL = "/admin/api/system/config/sGeocoderProviders";
    const DEFAULT_RANKING = "Nominatim, Census";
    const ADDRESS = "350 5th Avenue, New York, NY 10118";
    const EMPIRE_STATE = { lat: 40.7484, lon: -73.9857 };
    const TOLERANCE = 0.01; // ~1 km

    const setRanking = (value) => cy.makePrivateAdminAPICall("POST", CONFIG_URL, { value }, 200);

    const expectNear = (body) => {
        expect(body).to.have.property("Latitude").that.is.a("number");
        expect(body).to.have.property("Longitude").that.is.a("number");
        expect(Math.abs(body.Latitude - EMPIRE_STATE.lat)).to.be.lessThan(TOLERANCE);
        expect(Math.abs(body.Longitude - EMPIRE_STATE.lon)).to.be.lessThan(TOLERANCE);
    };

    const geocode = () =>
        cy.makePrivateAdminAPICall("POST", "/api/geocoder/address", { address: ADDRESS }, 200, 40000);

    after(() => {
        setRanking(DEFAULT_RANKING);
    });

    it("ships with Nominatim then Census as the default ranking", () => {
        cy.makePrivateAdminAPICall("GET", CONFIG_URL, null, 200).then((response) => {
            expect(response.body.value).to.equal(DEFAULT_RANKING);
        });
    });

    it("round-trips a custom ranking through the settings API", () => {
        setRanking("Census, Nominatim").then((response) => {
            expect(response.body.value).to.equal("Census, Nominatim");
        });
        cy.makePrivateAdminAPICall("GET", CONFIG_URL, null, 200).then((response) => {
            expect(response.body.value).to.equal("Census, Nominatim");
        });
    });

    it("geocodes with the US Census Bureau alone", () => {
        setRanking("Census");
        geocode().then((response) => expectNear(response.body));
    });

    it("geocodes with Nominatim alone", () => {
        setRanking("Nominatim");
        cy.wait(1100); // Nominatim usage policy: one request per second
        geocode().then((response) => expectNear(response.body));
    });

    it("ignores unknown names and still geocodes with the remaining service", () => {
        setRanking("Bogus, Census");
        geocode().then((response) => expectNear(response.body));
    });

    it("falls back to Nominatim when the ranking names no usable service", () => {
        setRanking("Nowhere");
        cy.wait(1100);
        geocode().then((response) => expectNear(response.body));
    });
});
