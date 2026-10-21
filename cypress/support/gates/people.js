const allow = ["church-admin", "staff-all-but-admin", "office-staff"];
const deny = ["finance-only", "finance-no-fundraisers", "groups-only", "notes-only-staff", "zero-permission-staff"];

const row = (id, url) => ({
    id,
    audience: "standard",
    classification: "people-records",
    api: { method: "GET", url },
    allow,
    deny,
});

export const PEOPLE_GATES = [
    row("people-properties-person-definitions-api", "/api/people/properties/person"),
    row("people-properties-family-definitions-api", "/api/people/properties/family"),
    row("people-properties-person-api", "/api/people/properties/person/2"),
    row("people-properties-family-api", "/api/people/properties/family/1"),
];
