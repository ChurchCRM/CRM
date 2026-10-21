export const NOTES_GATES = [
    {
        id: "notes-api",
        audience: "standard",
        classification: "private-notes",
        api: { method: "GET", url: "/api/person/2/notes" },
        allow: ["church-admin", "staff-all-but-admin", "notes-only-staff"],
        deny: ["office-staff", "finance-only", "finance-no-fundraisers", "groups-only", "zero-permission-staff"],
    },
];
