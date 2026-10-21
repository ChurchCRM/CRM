const allow = ["church-admin", "staff-all-but-admin", "notes-only-staff"];
const deny = ["office-staff", "finance-only", "finance-no-fundraisers", "groups-only", "zero-permission-staff"];

export const NOTES_GATES = [
    {
        id: "notes-api",
        audience: "standard",
        classification: "private-notes",
        api: { method: "GET", url: "/api/person/2/notes" },
        allow,
        deny,
    },
    {
        id: "notes-family-api",
        audience: "standard",
        classification: "private-notes",
        api: { method: "GET", url: "/api/family/2/notes" },
        allow,
        deny,
    },
];
