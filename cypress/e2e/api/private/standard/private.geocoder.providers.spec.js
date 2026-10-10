/// <reference types="cypress" />

/**
 * Geocoding provider chain (#9848).
 *
 * `sGeocoderProviders` is a comma-separated ranking of geocoding services
 * ("Nominatim" by default; US Census is opt-in). GeoUtils::getLatLong() asks them
 * one by one. US Census only answers for the US, decided from the record's country,
 * else sDefaultCountry, else sChurchCountry; if all are blank it is skipped.
 *
 * These tests drive POST /api/geocoder/address, which sends no country, so the
 * default/church country settings decide. They use a stable public address (the
 * Empire State Building) that both services resolve and assert the result lands
 * within ~1 km. Every lookup that reaches a service is a live call; the settings
 * are restored afterwards. Retried twice: the public services occasionally time out
 * or rate-limit a CI runner.
 */
describe("API Private Geocoder — provider chain (#9848)", { retries: 2 }, () => {
    const CONFIG = (name) => `/admin/api/system/config/${name}`;
    const SETTINGS = ["sGeocoderProviders", "sDefaultCountry", "sChurchCountry"];
    const DEFAULT_RANKING = "Nominatim";
    const ADDRESS = "350 5th Avenue, New York, NY 10118";
    const EMPIRE_STATE = { lat: 40.7484, lon: -73.9857 };
    const TOLERANCE = 0.01; // ~1 km

    const original = {};

    const setConfig = (name, value) => cy.makePrivateAdminAPICall("POST", CONFIG(name), { value }, 200);
    const setCountries = (defaultCountry, churchCountry) => {
        setConfig("sDefaultCountry", defaultCountry);
        setConfig("sChurchCountry", churchCountry);
    };

    const expectNear = (body) => {
        expect(body).to.have.property("Latitude").that.is.a("number");
        expect(body).to.have.property("Longitude").that.is.a("number");
        expect(Math.abs(body.Latitude - EMPIRE_STATE.lat)).to.be.lessThan(TOLERANCE);
        expect(Math.abs(body.Longitude - EMPIRE_STATE.lon)).to.be.lessThan(TOLERANCE);
    };

    const expectNotFound = (body) => {
        expect(body.Latitude).to.equal(0);
        expect(body.Longitude).to.equal(0);
    };

    const geocode = (address = ADDRESS) =>
        cy.makePrivateAdminAPICall("POST", "/api/geocoder/address", { address }, 200, 40000);

    before(() => {
        SETTINGS.forEach((name) => {
            cy.getSystemConfig(name).then((value) => {
                original[name] = value;
            });
        });
    });

    after(() => {
        SETTINGS.forEach((name) => cy.restoreSystemConfig(name, original[name]));
    });

    it("ships with Nominatim alone as the default ranking (US Census is opt-in)", () => {
        cy.makePrivateAdminAPICall("GET", CONFIG("sGeocoderProviders"), null, 200).then((response) => {
            expect(response.body.value).to.equal(DEFAULT_RANKING);
        });
    });

    it("round-trips a custom ranking through the settings API", () => {
        setConfig("sGeocoderProviders", "US Census, Nominatim").then((response) => {
            expect(response.body.value).to.equal("US Census, Nominatim");
        });
        cy.makePrivateAdminAPICall("GET", CONFIG("sGeocoderProviders"), null, 200).then((response) => {
            expect(response.body.value).to.equal("US Census, Nominatim");
        });
    });

    it("geocodes with Nominatim alone", () => {
        setConfig("sGeocoderProviders", DEFAULT_RANKING);
        cy.wait(1100); // Nominatim usage policy: one request per second
        geocode().then((response) => expectNear(response.body));
    });

    it("geocodes with the US Census Bureau when the default country is the US", () => {
        setConfig("sGeocoderProviders", "US Census");
        setCountries("US", "");
        geocode().then((response) => expectNear(response.body));
    });

    it("keeps a route number intact so US Census can match it (FM 1960 is not 1960th)", () => {
        setConfig("sGeocoderProviders", "US Census");
        setCountries("US", "");
        geocode("4210 FM 1960 Rd W, Houston, TX 77068").then(({ body }) => {
            expect(Math.abs(body.Latitude - 29.9909)).to.be.lessThan(0.05);
            expect(Math.abs(body.Longitude - -95.4929)).to.be.lessThan(0.05);
        });
    });

    it("falls back to the church country when the default country is blank", () => {
        setConfig("sGeocoderProviders", "US Census");
        setCountries("", "US");
        geocode().then((response) => expectNear(response.body));
    });

    it("does not ask US Census about a non-US default country", () => {
        setConfig("sGeocoderProviders", "US Census");
        setCountries("GB", "US");
        geocode().then((response) => expectNotFound(response.body));
    });

    it("does not ask US Census when no country is configured anywhere", () => {
        setConfig("sGeocoderProviders", "US Census");
        setCountries("", "");
        geocode().then((response) => expectNotFound(response.body));
    });

    it("ignores unknown names and still geocodes with the remaining service", () => {
        setConfig("sGeocoderProviders", "Bogus, US Census");
        setCountries("US", "");
        geocode().then((response) => expectNear(response.body));
    });

    it("tries the next service when the first finds nothing", () => {
        // US Census declines (non-US default country), so the answer has to come from Nominatim.
        setConfig("sGeocoderProviders", "US Census, Nominatim");
        setCountries("GB", "");
        cy.wait(1100);
        geocode().then((response) => expectNear(response.body));
    });

    it("falls back to Nominatim when the ranking names no usable service", () => {
        setConfig("sGeocoderProviders", "Nowhere");
        cy.wait(1100);
        geocode().then((response) => expectNear(response.body));
    });
});
